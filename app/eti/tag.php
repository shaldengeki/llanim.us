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

}

?>