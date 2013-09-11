<?php
namespace Term;

class Term extends \Model {
  public static $DB = "llAnimu";
  public static $TABLE = "topic_tfidfs";
  public static $PLURAL = "Terms";
  public static $FIELDS = [
    'id' => [
      'type' => 'string',
      'db' => 'term'
    ]
  ];
  public static $JOINS = [
    'topics' => [
      'obj' => '\\SAT\\Topic',
      'table' => 'seinma_llanimu.topic_tfidfs',
      'own_col' => 'term',
      'join_col' => 'term',
      'type' => 'many'
    ]
  ];

  public function getTopics($limit=Null) {
    $this->db()->table(static::$TABLE)
              ->where([
                      'term' => $this->id
                      ])
              ->order('ll_topicid ASC');
    if ($limit !== Null) {
      $this->db()->limit( (int) $limit );
    }
    $topicQuery = $this->db()->query();
    $topics = [];
    while ($topic = $topicQuery->fetch()) {
      $sat = new \SAT\Topic($this->app, (int) $topic['ll_topicid'] );
      $topics[$sat->id] = [
        'topic' => $sat,
        'tfidf' => (float) $topic['tfidf']
      ];
    }
    return $topics;
  }
  public function topics() {
    if (!isset($this->topics)) {
      $this->topics = $this->getTopics();
    }
    return $this->topics;
  }
}
?>