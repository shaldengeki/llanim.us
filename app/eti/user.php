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
      'obj' => '\\ETI\\Post',
      'table' => 'posts',
      'own_col' => 'id',
      'join_col' => 'userid'
    ],
    'topics' => [
      'obj' => '\\ETI\\Topic',
      'table' => 'topics',
      'own_col' => 'id',
      'join_col' => 'userid'
    ]
  ];

  public function name() {
    // get the latest username for this user.
    if (!isset($this->name)) {
      try {
        $this->name = $this->db()->table('user_names')->fields('name')->where(['user_id' => $this->id])->order('date DESC')->limit(1)->firstValue();
      } catch (\DbException $e) {
        $this->name = "";
      }
    }
    return $this->name;
  }

  public function isTagStaff(Tag $tag) {
    $staff = False;
    foreach ($tag->staff as $staff) {
      if ($staff['user']->id === $this->id) {
        $staff = True;
        break;
      }
    }
    return $staff;
  }
}

?>