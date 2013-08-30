<?php
namespace SAT;

class User extends \Model {
  public static $DB = "llAnimu";
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
}
?>