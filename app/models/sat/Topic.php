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
    $this->db()->table('sat_tfidfs')
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
    $this->db()->table(\ETI\Post::DB_NAME($this->app).'.'.\ETI\Post::$TABLE)
              ->fields(\ETI\Post::$FIELDS['user_id']['db'].' AS user_id', 'COUNT(*) AS count')
              ->where([
                      \ETI\Post::$FIELDS['topic_id']['db'] => $this->id
                      ])
              ->group(\ETI\Post::$FIELDS['user_id']['db'])
              ->order('count DESC');
    if ($limit !== Null) {
      $this->db()->limit(intval($limit));
    }
    $postCountQuery = $this->db()->query();
    $postCounts = [];
    while ($postCount = $postCountQuery->fetch()) {
      $postCounts[intval($postCount['user_id'])] = intval($postCount['count']);
    }
    return $postCounts;
  }
  public function postCounts() {
    if (!isset($this->postCounts)) {
      $this->postCounts = $this->getPostCounts();
    }
    return $this->postCounts;
  }
}
?>