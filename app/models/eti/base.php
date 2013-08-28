<?php
namespace ETI;

abstract class Base extends \Model {
  public static $DB = "ETI";

  public static function searchNodeType($nodes, $type, &$result=Null, $exclude=[]) {
    // recursively searches for instances of $type within a list of nodes $nodes, 
    // optionally appending the instances to $result.
    // $exclude is a list of object names to exclude from recursive search.
    if ($result === Null) {
      $result = [];
    }
    foreach ($nodes as $thisNode) {
      $className = str_replace("ETI\\", "", get_class($thisNode));
      if (property_exists($thisNode, 'nodes') && is_array($thisNode->nodes) && !in_array($className, $exclude)) {
        static::searchNodeType($thisNode->nodes, $type, $result, $exclude);
      }
      if ($className === $type) {
        $result[] = $thisNode;
      }
    }
    return $result;
  }
}

?>