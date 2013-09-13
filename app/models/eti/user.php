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
      'join_col' => 'userid',
      'type' => 'many'
    ],
    'topics' => [
      'obj' => '\\ETI\\Topic',
      'table' => 'topics',
      'own_col' => 'id',
      'join_col' => 'userid',
      'type' => 'many'
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

  public function getPostCounts($limit=Null) {
    $this->db()->table(Post::FULL_TABLE_NAME($this->app))
              ->fields(Post::DB_FIELD('user_id').' AS user_id', 'COUNT(*) AS count')
              ->where([
                      Post::DB_FIELD('topic_id') => $this->id
                      ])
              ->group(Post::DB_FIELD('user_id'))
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