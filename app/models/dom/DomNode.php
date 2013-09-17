<?php
namespace Dom;

class DomNode extends \DOMNode {
  public $attrs;
  public static function GetInnerHTML(\DOMNode $node) {
    $innerHTML= '';
    $children = $node->childNodes; 
    foreach ($children as $child) { 
        $innerHTML .= $child->ownerDocument->saveXML( $child ); 
    } 
    return $innerHTML; 
  }
  public static function GetAttributes(\DOMNode $node) {
    $attributes = [];
    if ($node->hasAttributes()) {
      foreach ($node->attributes as $attr) {
        $attributes[$attr->nodeName] = $attr->nodeValue;
      }
    }
    return $attributes;
  }
  public function innerHTML() {
    return static::GetInnerHTML($this);
  }
  public function attr($attr) {
    if (!isset($this->attrs)) {
      $this->attrs = static::GetAttributes();
    }
    return isset($this->attrs[$attr]) ? $this->attrs[$attr] : Null;
  }
}