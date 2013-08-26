<?php
namespace ETI;

class Spoiler extends PostNode {
  public $caption, $text;

  public static function isNode(\DOMNode $node) {
    // node must have a class of spoiler_closed.
    $thisAttrs = $node->attributes;
    if (!$thisAttrs) {
      return False;
    }
    $thisClass = $thisAttrs->getNamedItem('class');
    if (!$thisClass) {
      return False;
    }
    if (!in_array('spoiler_closed', explode(' ', $thisClass->nodeValue))) {
      return False;
    }
    return True;
  }
  public static function spoilerCaption(\DOMNode $node) {
    $onCloseNode = $node->firstChild;
    if (!$onCloseNode) {
      return False;
    }
    $captionNode = $onCloseNode->firstChild;
    if (!$captionNode) {
      return False;
    }
    $boldNode = $captionNode->firstChild;
    if (!$boldNode) {
      return False;
    }
    $captionText = $boldNode->textContent;
    if (!$captionText) {
      return False;
    }
    $caption = get_enclosed_string($captionText, "<", " />");
    return $caption;
  }
  public static function spoilerText(\DOMNode $node) {
    $nodeChildren = $node->childNodes;
    if (!$nodeChildren) {
      return False;
    }
    $onOpenNode = $nodeChildren->item(1);
    if (!$onOpenNode) {
      return False;
    }

    // within the onOpen node, there's a start and end <a> node that surround the actual spoiler contents.
    $newOpenNode = clone $onOpenNode;
    $newOpenNode->removeChild($newOpenNode->firstChild);
    $newOpenNode->removeChild($newOpenNode->lastChild);

    $text = "";
    foreach ($newOpenNode->childNodes as $child) {
      $text .= $child->ownerDocument->saveHTML($child);
    }
    return trim($text);
  }
  public static function parse(\DOMNode $node) {
    if (!static::isNode($node)) {
      return False;
    }
    $caption = static::spoilerCaption($node);
    $text = static::spoilerText($node);

    return new Spoiler($caption, $text);
  }
  public function __construct($caption, $text) {
    parent::__construct();
    $this->caption = $caption;
    $this->text = $text;

    // get nested nodes.
    $dom = static::getDOM($this->text);
    $this->nodes = static::getNested($dom);
  }
  public function render(\DbConn $db, $id="u0_1") {
    $escapedCaption = escape_output($this->caption);

    $content = "";
    foreach ($this->nodes as $node) {
      $content .= $node->render($db);
    }

    return <<<SPOILER_MARKUP
<span class="spoiler_closed" id="$id">
  <span class="spoiler_on_close">
    <a class="caption" href="#">
      <b>&lt;$escapedCaption /&gt;</b>
    </a>
  </span>
  <span class="spoiler_on_open">
    <a class="caption" href="#">&lt;$escapedCaption&gt;</a>
    {$content}
    <a class="caption" href="#">&lt;/$escapedCaption&gt;</a>
  </span>
</span>
<script type="text/javascript">
  onDOMContentLoaded(function(){new llmlSpoiler(\$("$id"))})
</script>
SPOILER_MARKUP;
  }
}

?>