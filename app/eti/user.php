<?php
namespace ETI;

class User extends Base {
  public static $TABLE = "users";
  public static $PLURAL = "Users";
  public static $FIELDS = [
    'id' => [
      'type' => 'int',
      'db' => 'id'
    ],
    'created' => [
      'type' => 'timestamp',
      'db' => 'created'
    ],
    'avatar' => [
      'type' => 'str',
      'db' => 'picture'
    ]
  ];
  public static $JOINS = [
    'posts' => [
      'obj' => 'Post',
      'table' => 'posts',
      'own_col' => 'id',
      'join_col' => 'userid'
    ],
    'topics' => [
      'obj' => 'Topic',
      'table' => 'topics',
      'own_col' => 'id',
      'join_col' => 'userid'
    ]
  ];
  
  public function name() {
    // get the latest username for this user.
    if (!isset($this->name)) {
      try {
        $this->name = $this->db->table('user_names')->fields('name')->where(['user_id' => $this->id])->order('date DESC')->limit(1)->firstValue();
      } catch (\DbConnException $e) {
        $this->name = "";
      }
    }
    return $this->name;
  }
}

?>