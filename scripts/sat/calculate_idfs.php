<?php
// calculate_idfs.php [--start=START_TOPIC_ID]
// for each SAT since the provided ID (optional), gets all posts and splits them into bag-of-words
// for each word, calculates number of posts containing that word and saves the counts to sat_tfs
// also calculates grand total counts for each word across all calculated topics and saves the counts to sat_idfs

require_once("../../app/Application.php");
$app = new Application();
$app->initScript(Null, [
                        'start' => [
                          'required' => False,
                          'value' => True
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

$topicQueue = new DbQueue($app->dbs['SAT'], "sat_tfs", ["term", "ll_topicid", "freq"], 1000);
$topicQueue->ignore(True);
$satQueue = new DbQueue($app->dbs['SAT'], "sat_idfs", ["term", "freq"], 1000);
$satQueue->onDuplicateUpdate('freq=freq+VALUES(freq)');

// load stopwords only once.
$stopwords = loadStopWords();

$sats = \SAT\Topic::GetList($app, [
                            \SAT\Topic::$FIELDS['id']['db'].' >= '.intval($app->cliOpts['start'])
                            ]);
$numSATs = count($sats);
for ($i = 0; $i < $numSATs; $i++) {
  $sat = $sats[$i];
  echo "Now processing SAT ".$sat->id." TFs and IDFs.\n";
  $sat->topic->load('posts');
  $corpus = new Corpus();
  //first, update document frequencies for each topic.
  foreach ($sat->topic->posts as $key=>$post) {
    // skip the first five posts in this topic so we don't grab the OPs.
    $doc = new Document(removeStopWords($post->text(), $stopwords), $corpus);
  }
  //update database records for topic-specific IDFs each of these words.
  foreach ($corpus->dfs() as $term=>$df) {
    $topicQueue->insert(['term' => $term, 'll_topicid' => intval($sat->id), 'freq' => $df]);
    $satQueue->insert(['term' => $term, 'freq' => $df]);
  }
  unset($sats[$i]);  
}
$topicQueue->flush();
$satQueue->flush();
?>