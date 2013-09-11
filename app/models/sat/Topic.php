<?php
namespace SAT;

class Topic extends \Model {
  public static $DB = "llAnimu";
  public static $TABLE = "sats";
  public static $PLURAL = "SATs";
  public static $FIELDS = [
    'id' => [
      'type' => 'int',
      'db' => 'll_topicid'
    ],
    'length' => [
      'type' => 'int',
      'db' => 'length'
    ],
    'completed' => [
      'type' => 'bool',
      'db' => 'completed'
    ]
  ];
  public static $JOINS = [
    'topic' => [
      'obj' => '\\ETI\\Topic',
      'table' => 'seinma_llusers.topics',
      'own_col' => 'll_topicid',
      'join_col' => 'll_topicid',
      'type' => 'one'
    ]
  ];

  public function getTerms($limit=Null) {
    $this->db()->table('topic_tfidfs')
              ->where([
                      'll_topicid' => $this->id
                      ])
              ->order('tfidf DESC');
    if ($limit !== Null) {
      $this->db()->limit(intval($limit));
    }
    $tfidfs = $this->db()->query();
    $terms = [];
    while ($tfidf = $tfidfs->fetch()) {
      $terms[$tfidf['term']] = floatval($tfidf['tfidf']);
    }
    return $terms;
  }
  public function terms() {
    if (!isset($this->terms)) {
      $this->terms = $this->getTerms();
    }
    return $this->terms;
  }
  
  public function getPostCounts($limit=Null) {
    $postCountQuery = $this->db()->table(\ETI\Post::DB_NAME($this->app).'.'.\ETI\Post::$TABLE)
                                  ->fields(\ETI\Post::$FIELDS['user_id']['db'].' AS user_id', 'COUNT(*) AS count')
                                  ->where([
                                          \ETI\Post::$FIELDS['topic_id']['db'] => $this->id
                                          ])
                                  ->group(\ETI\Post::$FIELDS['user_id']['db'])
                                  ->order('count DESC')
                                  ->query();
    $postCounts = [];

    // group postcounts by main userID.
    while ($postCount = $postCountQuery->fetch()) {
      try {
        $user = new \SAT\User($this->app, (int) $postCount['user_id']);
        if (isset($postCounts[$user->main()->id])) {
          $postCounts[$user->main()->id]['count'] += (int) $postCount['count'];
        } else {
          $postCounts[$user->main()->id] = ['user' => $user, 'count' => (int) $postCount['count']];
        }
      } catch (\DbException $e) {
        // no such SAT user. add an ETI user instead.
        $user = new \ETI\User($this->app, (int) $postCount['user_id']);
        $postCounts[$user->id] = ['user' => $user, 'count' => (int) $postCount['count']];
      }
    }
    array_sort_by_key($postCounts, 'count');
    return array_slice($postCounts, 0, $limit, True);
  }
  public function postCounts() {
    if (!isset($this->postCounts)) {
      $this->postCounts = $this->getPostCounts();
    }
    return $this->postCounts;
  }
}
?>