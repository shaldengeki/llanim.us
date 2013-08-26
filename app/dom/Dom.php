<?php
namespace Dom;

class Dom extends DOMDocument {
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
}

?>