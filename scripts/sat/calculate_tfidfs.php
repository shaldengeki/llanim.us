<?php

// calculate_tfidfs.php [--start START_TOPIC_ID]
// calculates tf-idfs for each SAT starting from a given (optional) SAT topic ID.

require_once("../../app/Application.php");
$app = new Application();
$app->initScript(Null, [
                        'start' => [
                          'required' => False,
                          'value' => True
                        ]
                       ]);

$satQueue = new DbQueue($app->dbs['SAT'], "sat_tfidfs", ["ll_topicid", "term", "tfidf"], 1000);
$satQueue->onDuplicateUpdate("tfidf=VALUES(tfidf)");

$sats = \SAT\Topic::GetList($app, [
                            'completed' => 1,
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
    $satQueue->insert(['ll_topicid' => intval($sat->id), 'term' => $term, 'tfidf' => $tfidf]);
  }
  unset($sats[$i]);
}
$satQueue->flush();
echo "Finished.\n";
?>