<?php
namespace Dom;

class Dom extends \DOMDocument {
  public function getElementsByClassName($name) {
    $elts = $this->getElementsByTagName("*");
    $matches = [];

    foreach ($elts as $node) {
      if (!$node->hasAttributes()) {
        continue;
      }
      $classAttribute = $node->attributes->getNamedItem('class');

      if (!$classAttribute) {
        continue;
      }
      $classes = explode(' ', $classAttribute->nodeValue);
      if (in_array($name, $classes)) {
        $matches[] = $node;
      }
    }
    return $matches;
  }
  public function getElementsByAttributes($attrs) {
    $elts = $this->getElementsByTagName("*");
    $matches = [];

    foreach ($elts as $node) {
      if (!$node->hasAttributes()) {
        continue;
      }
      $eltAttrs = $node->attributes;
      $matchesAttrs = True;
      foreach ($attrs as $attr=>$val) {
        if ($eltAttrs->getNamedItem($attr) !== $val) {
          $matchesAttrs = False;
          break;
        }
      }
      if (!$matchesAttrs) {
        continue;
      }
      $matches[] = $node;
    }
    return $matches;
  }
}

?>