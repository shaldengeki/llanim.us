<?php
namespace ETI;

class Topic extends Base {
  public static $TABLE = "topics";
  public static $PLURAL = "Topics";
  public static $FIELDS = [
    'id' => [
      'type' => 'int',
      'db' => 'll_topicid'
    ],
    'user_id' => [
      'type' => 'int',
      'db' => 'userid'
    ],
    'title' => [
      'type' => 'str',
      'db' => 'title'
    ],
    'post_count' => [
      'type' => 'int',
      'db' => 'postCount'
    ],
    'last_post_time' => [
      'type' => 'timestamp',
      'db' => 'lastPostTime'
    ]
  ];
  public static $JOINS = [
    'posts' => [
      'obj' => 'Post',
      'table' => 'posts',
      'own_col' => 'll_topicid',
      'join_col' => 'll_topicid'
    ],
    'user' => [
      'obj' => 'User',
      'table' => 'users',
      'own_col' => 'userid',
      'join_col' => 'id'
    ]
  ];

  protected function user() {
    if (!isset($this->user_id)) {
      $this->load();
    }
    if (!isset($this->user)) {
      $this->user = new User($this->db,  (int) $this->user_id);
    }
    return $this->user;
  }

  protected function users() {
    if (!isset($this->posts)) {
      $this->load('posts');
    }

    if (!isset($this->users)) {
      $userIDs = [];
      foreach ($this->posts as $post) {
        $userIDs[$post->user->id] = 1;
      }
      $userInfo = $this->db->table(\ETI\User::$TABLE)
                            ->where([
                                \ETI\User::$FIELDS['id']['db'] => array_keys($userIDs)
                              ])
                            ->assoc(\ETI\User::$FIELDS['id']['db']);
      $this->users = [];
      foreach ($userInfo as $user) {
        $newUser = new \ETI\User($this->db, $user[\ETI\User::$FIELDS['id']['db']]);
        $this->users[] = $newUser->set($user);
      }
    }
    return $this->users;
  }
}

?>