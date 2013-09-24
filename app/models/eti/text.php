<?php
namespace ETI;

class Text extends PostNode {
  public $text;

  public static function parse(\Application $app, \DOMNode $node) {
    $newDOM = new \Dom\Dom();
    $newDOM->appendChild($newDOM->importNode($node, True));
    return new Text($app, $newDOM->saveHTML());
  }
  public function __construct(\Application $app, $text) {
    parent::__construct($app);
    $this->text = $text;
  }
  public function render(\View $view) {
    return $view->escape($this->text);
  }
}

?>