<?php
namespace ETI;

class Link extends PostNode {
  public $url, $components;

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
    if (!in_array('l', explode(' ', $thisClass->nodeValue))) {
      return False;
    }
    return True;
  }
  public static function linkURL(\DOMNode $node) {
    $thisAttrs = $node->attributes;
    $url = $thisAttrs->getNamedItem('href');
    if (!$url) {
      return False;
    }
    return $url->nodeValue;
  }
  public static function parse(\DOMNode $node) {
    if (!static::isNode($node)) {
      return False;
    }
    $url = static::linkURL($node);
    return new Link($url);
  }
  public function __construct($url) {
    parent::__construct();
    $this->url = $url;
  }
  private function setComponents() {
    $this->components = parse_url($this->url);
  }
  public function scheme() {
    if (!isset($this->components)) {
      $this->setComponents();
    }
    return $this->components['scheme'];
  }
  public function host() {
    if (!isset($this->components)) {
      $this->setComponents();
    }
    return $this->components['host'];
  }
  public function path() {
    if (!isset($this->components)) {
      $this->setComponents();
    }
    return $this->components['path'];
  }
  public function query() {
    if (!isset($this->components)) {
      $this->setComponents();
    }
    return $this->components['query'];
  }
  public function fragment() {
    if (!isset($this->components)) {
      $this->setComponents();
    }
    return $this->components['fragment'];
  }

  public function render(\DbConn $db, $id="u0_1") {
    $escapedCaption = escape_output($this->caption);
    $markup = <<<LINK_MARKUP
<a class="l" target="_blank" title="{$this->url}" href="{$this->url}">
LINK_MARKUP;

    if (mb_strlen($this->url) > 61) {
      $linkBeginning = mb_substr($this->url, 0, 28);
      $linkMiddle = mb_substr($this->url, 28, mb_strlen($this->url) - 58);
      $linkEnd = mb_substr($this->url, mb_strlen($this->url) - 30, 29);

      $markup .= <<<LINK_MARKUP
  {$linkBeginning}
  <span class="m">
    <span>{$linkMiddle}</span>
  </span>
  {$linkEnd}
LINK_MARKUP;
    } else {
      $markup .= <<<LINK_MARKUP
  {$this->url}
LINK_MARKUP;
    }

    $markup .= <<<LINK_MARKUP
</a>
LINK_MARKUP;
    return $markup;
  }
}

?>