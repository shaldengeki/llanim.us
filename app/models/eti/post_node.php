<?php
namespace ETI;

 abstract class PostNode {
  // trait for post content nodes.
  // can contain other content nodes.

  public $app, $dom, $nodes;

  public static function CreateDom($html) {
    // load DOM.
    libxml_use_internal_errors(True);
    $dom = new \Dom\Dom();
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    // dirty fix
    foreach ($dom->childNodes as $item) {
      if ($item->nodeType == XML_PI_NODE) {
        $dom->removeChild($item); // remove hack
      }
    }
    $dom->encoding = 'UTF-8'; // insert proper
    return $dom;    
  }

  public static function getNested(\Application $app, \DOMDocument $dom) {
    $nodes = [];
    if (!$dom->documentElement || !$dom->documentElement->childNodes) {
      return $nodes;
    }
    $childNodes = $dom->documentElement->childNodes->item(0)->childNodes;
    for ($i = 0; $i < $childNodes->length; $i++) {
      $node = $childNodes->item($i);
      if (Quote::isNode($node)) {
        $parseResult = Quote::parseQuote($app, $childNodes, $i);
        if ($parseResult !== False) {
          $nodes[] = $parseResult['quote'];
          // $i = $parseResult['offset'];
        }
        continue;
      } elseif (Image::isContainer($node)) {
        $nodes = array_merge($nodes, Image::parse($app, $node));
        continue;
      } elseif (Spoiler::isNode($node)) {
        $nodes[] = Spoiler::parse($app, $node);
        continue;
      } elseif (Link::isNode($node)) {
        $nodes[] = Link::parse($app, $node);
        continue;
      } else {
        $nodes[] = Text::parse($app, $node);
      }
    }
    return $nodes;
  }
  public function nodeType() {
    return get_called_class();
  }

  public static function isNode(\DOMNode $node) {
    // returns a bool.
    return False;
  }
  public static function parse(\Application $app, \DOMNode $node) {
    // returns an instance of the current post node class.
    if (!static::isNode($node)) {
      return False;
    }
  }
  public function render(\View $view) {
    // returns DOM markup.
    return "";
  }

  public function __construct(\Application $app) {
    $this->app = $app;
    $this->nodes = [];
  }
}

?>