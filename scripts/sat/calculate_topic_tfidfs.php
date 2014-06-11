<?php
// calculate_idfs.php [--start=START_TOPIC_ID]
// for each SAT since the provided ID (optional), gets all posts and splits them into bag-of-words
// for each word, calculates number of posts containing that word and saves the counts to sat_tfs
// also calculates grand total counts for each word across all calculated topics and saves the counts to sat_idfs
// finally, calculates SAT tf-idfs.

require_once("../../app/Application.php");
$app = new Application();
$app->initScript(Null, [
                        'start' => [
                          'required' => False,
                          'value' => True
                        ],
                        'clear-tfs' => [
                          'value' => False
                        ],
                        'clear-tfidfs' => [
                          'value' => False
                        ],
                        'clear-all' => [
                          'value' => False
                        ]
                       ]);

function loadStopWords() {
  // loads all stopwords from a file into an associative array
  // of the format stopword:1
  // for fast lookups
  $stopwords_string = file_get_contents('stop_words.txt');
  $stopwordList = explode(',',$stopwords_string);
  $stopwords = [];
  foreach ($stopwordList as $word) {
    $stopwords[$word] = 1;
  }
  return $stopwords;  
}
function removeStopWords($text, $stopwords=Null) {
  // removes all stopwords from a corpus.
  $words = explode(' ', $text);
  if ($stopwords === Null) {
    $stopwords = loadStopWords();
  }
  foreach ($words as $key=>$word) {
    if (isset($stopwords[$word])) {
      unset($words[$key]);
    }
  }
  return implode(' ', $words);
}

if (isset($app->cliOpts['clear-all']) || isset($app->cliOpts['clear-tfs'])) {
  // clear all extant tfs.
  echo "Clearing TFs.\n";
  $app->dbs['SAT']->table('sat_tfs')
    ->truncate();
  $app->dbs['SAT']->table('sat_idfs')
    ->truncate();
}

if (!isset($app->cliOpts['start'])) {
  $app->cliOpts['start'] = 0;
}

$topicQueue = new DbQueue($app->dbs['SAT'], "sat_tfs", ["term", "ll_topicid", "freq"], 1000);
$topicQueue->ignore(True);
$satQueue = new DbQueue($app->dbs['SAT'], "sat_idfs", ["term", "freq"], 1000);
$satQueue->onDuplicateUpdate('freq=freq+VALUES(freq)');

// load stopwords only once.
$stopwords = loadStopWords();

$sats = \SAT\Topic::GetList($app, [
                            \SAT\Topic::DB_FIELD('id').' >= '.intval($app->cliOpts['start']),
                            \SAT\Topic::DB_FIELD('completed') => 1
                            ]);
$numSATs = count($sats);
for ($i = 0; $i < $numSATs; $i++) {
  $sat = $sats[$i];
  echo "Now processing SAT ".$sat->id." TFs and IDFs.\n";
  // check to see if this SAT has been previously-processed.
  $findTFs = $app->dbs['SAT']->table("sat_tfs")->fields("COUNT(*)")->where(['ll_topicid' => $sat->id])->count();
  if ($findTFs > 0) {
    echo "SAT ".$sat->id." has already been processed, skipping.\n";
    continue;    
  }

  $sat->topic->load('posts');
  $corpus = new Corpus();
  //first, update document frequencies for each topic.
  foreach ($sat->topic->posts as $key=>$post) {
    // skip the first five posts in this topic so we don't grab the OPs.
    $doc = new Document(removeStopWords($post->text(), $stopwords), $corpus);
  }
  //update database records for topic-specific IDFs each of these words.
  foreach ($corpus->dfs() as $term=>$df) {
    $term = mb_substr($term, 0, 64);
    $topicQueue->insert(['term' => $term, 'll_topicid' => intval($sat->id), 'freq' => $df]);
    $satQueue->insert(['term' => $term, 'freq' => $df]);
  }
  unset($sats[$i]);  
}
$topicQueue->flush();
$satQueue->flush();

if (isset($app->cliOpts['clear-all']) || isset($app->cliOpts['clear-tfidfs'])) {
  // clear all extant tfidfs.
  echo "Clearing TF-IDFs.\n";
  $app->dbs['SAT']->table('sat_tfidfs')
                  ->truncate();
}

$satQueue = new DbQueue($app->dbs['SAT'], "sat_tfidfs", ["ll_topicid", "term", "tfidf"], 1000);
$satQueue->onDuplicateUpdate("tfidf=VALUES(tfidf)");

$sats = \SAT\Topic::GetList($app, [
                            \SAT\Topic::DB_FIELD('completed') => 1,
                            \SAT\Topic::DB_FIELD('id').' >= '.intval($app->cliOpts['start'])
                            ]);

// get total postcount across all SATs.
$postCount = $app->dbs['SAT']->table(\SAT\Topic::FULL_TABLE_NAME($app))
  ->fields('COUNT(*)')
  ->join(\ETI\Post::FULL_TABLE_NAME($app).' ON '.\ETI\Post::FULL_DB_FIELD_NAME($app, 'topic_id').' = '.\SAT\Topic::FULL_DB_FIELD_NAME($app, 'id'))
  ->where([
          \SAT\Topic::FULL_DB_FIELD_NAME($app, 'completed') => 1
          ])
  ->count();
// get global term frequencies.
$globalDFs = $app->dbs['SAT']->table('sat_idfs')
  ->fields('term', 'freq')
  ->assoc('term', 'freq');
$satsCorpus = new Corpus();
$satsCorpus->dfs($globalDFs)
  ->length($postCount);

// loop over all sats.
$numSATs = count($sats);
for ($i = 0; $i < $numSATs; $i++) {
  // calculate the tf-idfs within this sat.
  $sat = $sats[$i];
  echo "Processing SAT ".intval($sat->id)." TF-IDFs.\n";
  $tfs = $app->dbs['SAT']->table('sat_tfs')
    ->fields('term', 'freq')
    ->where([
            'll_topicid' => intval($sat->id)
            ])
    ->assoc('term', 'freq');
  $satDoc = new Document("");
  $satDoc->corpus($satsCorpus)->tfs($tfs);
  foreach ($satDoc->tfidfs() as $term=>$tfidf) {
    $term = mb_substr($term, 0, 64);
    $satQueue->insert(['ll_topicid' => intval($sat->id), 'term' => $term, 'tfidf' => $tfidf]);
  }
  unset($sats[$i]);
}
$satQueue->flush();
echo "Finished.\n";
?>