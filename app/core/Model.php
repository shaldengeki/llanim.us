<?php

class ModelException extends \Exception {
  private $messages;
  public function __construct($messages=Null, $code=0, Exception $previous=Null) {
    if (is_array($messages)) {
      $this->messages = $messages;
    } else {
      $this->messages = [$messages];
    }
    parent::__construct($this->formatMessages(), $code, $previous);
  }
  public function messages() {
    return $this->messages;
  }
  public function formatMessages($separator="<br />\n") {
    // displays a list of this exception's messages.
    if (count($this->messages) > 0) {
      return implode($separator, $this->messages);
    } else {
      return "";
    }
  }
  public function listMessages() {
    // returns an unordered HTML list of this exception's messages.
    if (count($this->messages) > 0) {
      return "<ul><li>".implode("</li><li>", $this->messages)."</li></ul>";
    } else {
      return "";
    }
  }
  public function __toString() {
    return get_class($this).":\n".$this->getFile().":".$this->getLine()."\nMessages: ".$this->formatMessages()."\nStack trace:\n".$this->getTraceAsString()."\n";
  }
  public function display() {
    // displays end user-friendly output explaining the exception that occurred.
    echo "A server error occurred, and I wasn't able to complete your request. I've let the staff know something's wrong - apologies for the problems!";
  }
}

abstract class Model {
  protected $app;
  public $id;

  /*
    DB:     accessor name of database that this object's table is in.
    TABLE:  name of database table that this object maps to.
    PLURAL: capitalised pluralised name of this class.
    FIELDS: mapping from object attributes to database fields
            of the form:
            [ 
              'object_attribute_name' => [
                'type' => 'attribute_type',
                'db' => 'db_column_name'
              ], ...
            ]
            upon construction, an object has a corresponding attribute DB_FIELDS set that reverses object_attribute_name and db_column_name.
            [ 
              'db_column_name' => [
                'type' => 'attribute_type',
                'attr' => 'object_attribute_name'
              ], ...
            ]
    JOINS: [
      'join_name' => [
        'obj' => '\\full\\namespace\\path\\to\\object',
        'table' => 'table_name',
        'own_col' => 'own_col_name',
        'join_col' => 'join_col_name'
      ]
    ]
  */
  public static $DB, $TABLE, $PLURAL, $FIELDS, $JOINS;

  public static function DB_FIELDS() {
    // inverts db_column_name and object_attribute_name in static::$FIELDS
    $invertedFields = [];
    foreach (static::$FIELDS as $attr_name => $attr_props) {
      $invertedFields[$attr_props['db']] = [
        'type' => $attr_props['type'],
        'attr' => $attr_name
      ];
    }
    return $invertedFields;
  }
  public static function Get($db, $params) {
    $objInfo = $db->table(static::$TABLE)->where($params)->limit(1)->firstRow();
    $className = get_called_class();
    $newObj = new $className($db, $objInfo[static::$FIELDS['id']['db']]);
    return $newObj->set($objInfo);
  }
  function __construct(\Application $app, $id) {
    $this->app = $app;
    $this->id = $id;
  }
  public function db() {
    return $this->app->dbs[static::$DB];
  }
  public function set(array $params) {
    /* 
      generic setter. Takes an array of params like:
        [
          'db_column_name' => attr_value,
          ...
        ]
      and sets this object's attributes properly.
    */
    $DB_FIELDS = static::DB_FIELDS();
    foreach ($params as $key => $value) {
      if (isset($DB_FIELDS[$key])) {
        switch ($DB_FIELDS[$key]['type']) {
          case 'int':
            $value = (int) $value;
            break;
          case 'float':
            $value = (float) $value;
            break;
          case 'bool':
            $value = (boolean) $value;
            break;
          case 'timestamp':
            $value = new \DateTime('@'.intval($value));
            break;
          case 'date':
            $value = new \DateTime($value);
            break;
          case 'str':
          default:
            $value = utf8_decode($value);
            break;
        }

        $this->{$DB_FIELDS[$key]['attr']} = $value;
      }
    }
    return $this;
  }

  public function load() {
    $this->db()->table(static::$TABLE);

    $includes = func_get_args();
    if ($includes) {
      foreach ($includes as $include) {
        if (isset(static::$JOINS[$include])) {
          $thisJoin = static::$JOINS[$include];
          $this->db()->join($thisJoin['table']." ON ".static::$TABLE.".".$thisJoin['own_col']."=".$thisJoin['table'].".".$thisJoin['join_col']);
        }
      }
    }
    $this->db()->where([
                      static::$TABLE.".".static::$FIELDS['id']['db'] => $this->id
                     ]);
    if ($includes) {
      $rows = $this->db()->query();
      $infoSet = False;
      while ($row = $rows->fetch()) {
        if (!$infoSet) {
          $this->set($row);
          $infoSet = True;
        }
        foreach ($includes as $include) {
          if (isset(static::$JOINS[$include])) {
            $thisJoin = static::$JOINS[$include];
            if (!isset($this->{$include})) {
              $this->{$include} = [];
            }
            $newObj = new $thisJoin['obj']($this->db(), $row[$thisJoin['obj']::$FIELDS['id']['db']]);
            $this->{$include}[] = $newObj->set($row);
          }
        }
      }
      return $this;
    } else {
      $row = $this->db()->limit(1)
                      ->firstRow();
      return $this->set($row);
    }
  }

  public function __get($property) {
    if (method_exists($this, $property)) {
      // A property accessor exists
      return $this->$property();
    } elseif (property_exists($this, $property)) {
      // The property is already defined.
      if (isset(static::$FIELDS[$property])) {
        $this->load();
      }
      return $this->$property;
    } else {
      if (isset(static::$FIELDS[$property])) {
        return $this->load()
                    ->{$property};
      } else {
        throw new ModelException("Requested attribute does not exist: ".$property." on: ".get_called_class());
      }
    }
  }
}
?>