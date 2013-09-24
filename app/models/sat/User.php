<?php
namespace SAT;

class User extends \Model {
  public static $DB = "SAT";
  public static $TABLE = "sat_users";
  public static $PLURAL = "Infos";
  public static $FIELDS = [
    'id' => [
      'type' => 'int',
      'db' => 'll_userid'
    ],
    'mal_user_id' => [
      'type' => 'int',
      'db' => 'mal_userid'
    ],
    'mal_name' => [
      'type' => 'string',
      'db' => 'mal_username'
    ],
    'color' => [
      'type' => 'string',
      'db' => 'color'
    ],
    'visible' => [
      'type' => 'bool',
      'db' => 'visible'
    ],
    'last_ip' => [
      'type' => 'string',
      'db' => 'last_ip'
    ]
  ];
  public static $JOINS = [
    'user' => [
      'obj' => '\\ETI\\User',
      'table' => 'seinma_llusers.users',
      'own_col' => 'll_userid',
      'join_col' => 'id',
      'type' => 'one'
    ]
  ];


  public $main_id, $main, $alts, $alt_ids, $posts;

  public function __construct(\Application $app, $id) {
    parent::__construct($app, $id);

    // look up this SAT user in the list of alts.
    $this->main_id = (int) $this->db()->table('sat_users_alts')
                                      ->fields('main_id')
                                      ->where([
                                              'alt_id' => $id
                                              ])
                                      ->limit(1)
                                      ->firstValue();
  }
  public function setSession() {
    // sets session information to this user.
    $_SESSION['id'] = $this->id;

    return $this;
  }
  public function unsetSession() {
    unset($_SESSION['id']);

    return $this;
  }
  public static function HasSession() {
    return isset($_SESSION['id']) && intval($_SESSION['id']) !== 0;
  }
  public function isLoggedIn() {
    return static::HasSession() && $_SESSION['id'] === $this->id;
  }

  public function main() {
    if (!isset($this->main)) {
      $this->main = new \ETI\User($this->app, $this->main_id);
    }
    return $this->main;
  }

  public function isMain() {
    return $this->main_id === $this->id;
  }

  public function isAlt() {
    return !$this->isMain();
  }

  public function alts() {
    if ($this->alts === Null) {
      $alts = $this->db()->table('sat_users_alts')
                            ->join(\ETI\User::FULL_TABLE_NAME($this->app)." ON sat_users_alts.alt_id=".\ETI\User::FULL_DB_FIELD_NAME($this->app, 'id'))
                            ->where([
                                    'sat_users_alts.main_id' => $this->main()->id
                                    ])
                            ->assoc('alt_id');
      if (!$alts) {
        $this->alts = [];
      } else {
        if (isset($alts[$this->main_id])) {
          unset($alts[$this->main_id]);
        }
        $this->alts = array_map(function($alt) {
          $newUser = new \ETI\User($this->app, $alt[\ETI\User::DB_FIELD('id')]);
          return $newUser->set($alt);
        }, $alts);
      }
    }
    return $this->alts;
  }

  public function altIDs() {
    if ($this->alt_ids === Null) {
      $this->alt_ids = array_map(function($alt) {
        return $alt->id;
      }, $this->alts());
    }
    return $this->alt_ids;
  }

  public function posts($limit=50) {
    if ($this->posts === Null) {
      $this->app->db->log($this->app->logger);
      $satIDs = array_map(function($sat) {
        return $sat->id;
      }, \SAT\Topic::GetList($this->app));
      $this->posts = \ETI\Post::GetList($this->app, [
                                          \ETI\Post::DB_FIELD('user_id') => $this->id,
                                          \ETI\Post::DB_FIELD('topic_id') => $satIDs
                                        ], (int) $limit);
      $this->app->db->unlog();
    }
    return $this->posts;
  }
}
?>