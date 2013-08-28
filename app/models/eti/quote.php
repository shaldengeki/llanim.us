<?php
namespace ETI;

class Quote extends PostNode {
  public $quoting, $text;

  public static function isNode(\DOMNode $node) {
    // node must have a class of quoted-message.
    $thisAttrs = $node->attributes;
    if (!$thisAttrs) {
      return False;
    }
    $thisClass = $thisAttrs->getNamedItem('class');
    if (!$thisClass) {
      return False;
    }
    if (!in_array('quoted-message', explode(' ', $thisClass->nodeValue))) {
      return False;
    }
    return True;
  }
  public static function quotedID(\DOMNode $node) {
    // echo "NODE: <pre>".print_r($node, True)."</pre>";    
    $quoteIDs = explode(",", $node->attributes->getNamedItem("msgid")->nodeValue);
    if (count($quoteIDs) < 3) {
      return False;
    }
    return intval(explode("@", $quoteIDs[2])[0]);
  }
  public static function quoteText(\DOMNodeList $nodeList, $offset) {
    // get all nodes up until the next quote node to fetch text.
    $newDOM = new \DOM();
    for ($j = $offset; $j < $nodeList->length && !Quote::isNode($nodeList->item($j)); $j++) {
      $newDOM->appendChild($newDOM->importNode($nodeList->item($j), True));
    }
    return [$newDOM->saveHTML(), $j+1];
  }
  public static function parseQuote(\DOMNodeList $nodeList, $offset) {
    // $offset marks where the quote node is in $nodeList.
    if (!static::isNode($nodeList->item($offset))) {
      return False;
    }
    // start at $offset+2 when fetching text to skip over the quote and the appended <br /> after the quote.
    $id = static::quotedID($nodeList->item($offset));
    list($text, $endOffset) = static::quoteText($nodeList, $offset+2);
    return ['quote' => new Quote($id, $text), 'offset' => $endOffset];
  }

  public function __construct($quoting, $text) {
    parent::__construct();
    $this->quoting = intval($quoting);
    $this->text = $text;

    // get nested nodes.
    $dom = static::getDOM($this->text);
    $this->nodes = static::getNested($dom);
  }
  public function render(\DbConn $db) {
    $quotedPost = new Post($db, $this->quoting);
    $quotedDate = $quotedPost->date->format('n/j/Y h:i:s A');
    $content = "";
    foreach ($this->nodes as $node) {
      $content .= $node->render($db);
    }
    return <<<QUOTE_MARKUP
<div class="quoted-message" msgid="t,{$quotedPost->topic_id},{$quotedPost->id}@0">
  <div class="message-top">
    From: <a href="//endoftheinter.net/profile.php?user={$quotedPost->user_id}">{$quotedPost->user->name}</a> | Posted: {$quotedDate}
  </div>
  {$quotedPost->html}
</div>
QUOTE_MARKUP;
  }
}

?>