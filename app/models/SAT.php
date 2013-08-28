<?php
namespace SAT;

class SAT extends \Model {
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
      'join_col' => 'll_topicid'
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
    $this->db()->table('seinma_llusers.posts')
              ->fields('userid AS user_id', 'COUNT(*) AS count')
              ->where([
                      'll_topicid' => $this->id
                      ])
              ->group('userid')
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
    if (!isset($this->terms)) {
      $this->terms = $this->getPostCounts();
    }
    return $this->terms;
  }


}
?>