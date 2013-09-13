<?php
namespace Dom;

class DomNode extends \DOMNode {
  public static function GetInnerHTML(\DOMNode $node) {
    $innerHTML= '';
    $children = $node->childNodes; 
    foreach ($children as $child) { 
        $innerHTML .= $child->ownerDocument->saveXML( $child ); 
    } 
    return $innerHTML; 
  }
  public function inner_html() {
    return static::GetInnerHTML($this);
  }
}