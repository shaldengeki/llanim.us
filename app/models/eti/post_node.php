<?php
namespace ETI;

 abstract class PostNode {
  // trait for post content nodes.
  // can contain other content nodes.

  public $dom, $nodes;

  public static function getDOM($html) {
    //load document.
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
  public static function getNested(\DOMDocument $dom) {
    $nodes = [];
    if (!$dom->documentElement || !$dom->documentElement->childNodes) {
      return;
    }
    $childNodes = $dom->documentElement->childNodes->item(0)->childNodes;
    for ($i = 0; $i < $childNodes->length; $i++) {
      $node = $childNodes->item($i);
      if (Quote::isNode($node)) {
        $parseResult = Quote::parseQuote($childNodes, $i);
        $nodes[] = $parseResult['quote'];
        // $i = $parseResult['offset'];
        continue;
      } elseif (Image::isContainer($node)) {
        $nodes = array_merge($nodes, Image::parse($node));
        continue;
      } elseif (Spoiler::isNode($node)) {
        $nodes[] = Spoiler::parse($node);
        continue;
      } elseif (Link::isNode($node)) {
        $nodes[] = Link::parse($node);
        continue;
      } else {
        $nodes[] = Text::parse($node);
      }
    }
    return $nodes;
  }
  public static function isNode(\DOMNode $node) {
    // returns a bool.
    return False;
  }
  public static function parse(\DOMNode $node) {
    // returns an instance of the current post node class.
    if (!static::isNode($node)) {
      return False;
    }
  }
  public function render(\DbConn $db) {
    // returns DOM markup.
    return "";
  }

  public function __construct() {
    $this->nodes = [];
  }
}

?>