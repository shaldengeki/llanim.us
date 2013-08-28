<?php
namespace ETI;

class Text extends PostNode {
  public $text;

  public static function parse(\DOMNode $node) {
    $newDOM = new \DOM();
    $newDOM->appendChild($newDOM->importNode($node, True));
    return new Text($newDOM->saveHTML());
  }
  public function __construct($text) {
    parent::__construct();
    $this->text = $text;
  }
  public function render(\DbConn $db) {
    return $this->text;
  }
}

?>