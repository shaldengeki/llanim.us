<?php
namespace ETI;

class Tag extends Base {
  public static $TABLE = "tags";
  public static $PLURAL = "Tags";
  public static $FIELDS = [
    'id' => [
      'type' => 'int',
      'db' => 'id'
    ],
    'name' => [
      'type' => 'str',
      'db' => 'name'
    ],
    'description' => [
      'type' => 'str',
      'db' => 'description'
    ],
    'access' => [
      'type' => 'bool',
      'db' => 'access'
    ],
    'participation' => [
      'type' => 'bool',
      'db' => 'participation'
    ],
    'permanent' => [
      'type' => 'bool',
      'db' => 'permanent'
    ],
    'inceptive' => [
      'type' => 'bool',
      'db' => 'inceptive'
    ]
  ];

  public function getStaff() {
    $staffQuery = $this->db()->table('users')
                            ->join('tags_users ON '.User::$TABLE.'.'.User::$FIELDS['id']['db'].' = tags_users.user_id')
                            ->fields('users.*', 'tags_users.role')
                            ->where([
                                    'tags_users.tag_id' => $this->id
                                    ])
                            ->order('role DESC')
                            ->query();
    $this->staff = [];
    while ($staff = $staffQuery->fetch()) {
      $newUser = new User($this->app, $staff['user_id']);
      $this->staff[] = [
        'user' => $newUser->set($staff),
        'role' => $staff['role']
      ];
    }
    return $this->staff;
  }
  public function staff() {
    if (!isset($this->staff)) {
      $this->getStaff();
    }
    return $this->staff;
  }
  public function isStaff(User $user) {
    $staff = False;
    foreach ($this->staff() as $staff) {
      if ($staff['user']->id === $user->id) {
        $staff = True;
        break;
      }
    }
    return $staff;
  }

  public function getTopics() {
    $this->topics = [];
    $joins = $this->db()->table('topics')
                        ->join('tags_topics ON '.Topic::$TABLE.'.'.Topic::$FIELDS['id']['db'].' = tags_topics.topic_id')
                        ->where([
                                'tags_topics.tag_id' => intval($this->id)
                                ])
                        ->order('topics.lastPostTime DESC')
                        ->query();
    while ($join = $joins->fetch()) {
      $newTopic = new Topic($this->app, $join['topic_id']);
      $this->topics[] = $newTopic->set($join);
    }
    return $this->topics;
  }
  public function topics() {
    if (!isset($this->topics)) {
      $this->getTopics();
    }
    return $this->topics;
  }
}

?>