<?php
namespace ETI;

class Image extends PostNode {
  public $url, $width, $height;

  public static function isContainer(\DOMNode $node) {
    // node must have a class of imgs.
    $thisAttrs = $node->attributes;
    if (!$thisAttrs) {
      return False;
    }
    $thisClass = $thisAttrs->getNamedItem('class');
    if (!$thisClass) {
      return False;
    }
    if (!in_array('imgs', explode(' ', $thisClass->nodeValue))) {
      return False;
    }
    return True;
  }
  public static function isNode(\DOMNode $node) {
    // node must have a class of imgs.
    $thisAttrs = $node->attributes;
    if (!$thisAttrs) {
      return False;
    }
    $imgSrc = $thisAttrs->getNamedItem('imgsrc');
    if (!$imgSrc) {
      return False;
    }
    return True;
  }
  public static function imageURL(\DOMNode $node) {
    $linkChildren = $node->childNodes;
    if (!$linkChildren) {
      return False;
    }
    $jsNode = $linkChildren->item(1);
    if (!$jsNode) {
      return False;
    }
    $scriptText = $jsNode->textContent;
    if (!$scriptText) {
      return False;
    }
    $scriptText = str_replace("\\/", "/", $scriptText);
    $url = "//".get_enclosed_string($scriptText, '"), "//', '", ');
    return $url;
  }
  public static function imageDims(\DOMNode $node) {
    $linkChildren = $node->childNodes;
    if (!$linkChildren) {
      return False;
    }
    $placeholderNode = $linkChildren->item(0);
    if (!$placeholderNode) {
      return False;
    }
    $placeholderAttrs = $placeholderNode->attributes;
    if (!$placeholderAttrs) {
      return False;
    }
    $placeholderStyles = $placeholderAttrs->getNamedItem('style');
    if (!$placeholderStyles) {
      return False;
    }
    $styleList = explode(";", $placeholderStyles->nodeValue);
    $styles = [];
    foreach ($styleList as $style) {
      $splitStyle = explode(":", $style);
      $styles[$splitStyle[0]] = $splitStyle[1];
    }
    if (!isset($styles['width']) || !isset($styles['height'])) {
      return False;
    }
    $width = intval(str_replace(["px", "%"], "", $styles['width']));
    $height = intval(str_replace(["px", "%"], "", $styles['height']));

    return ['width' => $width, 'height' => $height];
  }
  public static function parse(\Application $app, \DOMNode $node) {
    if (!static::isContainer($node)) {
      return False;
    }
    // get a list of images within this container.
    $images = $node->childNodes;
    if (!$images) {
      return False;
    }
    $resultImages = [];
    foreach ($images as $imageNode) {
      if (static::isNode($imageNode)) {
        $url = static::imageURL($imageNode);
        $dims = static::imageDims($imageNode);
        $resultImages[] = new Image($app, $url, $dims['width'], $dims['height']);
      }
    }
    return $resultImages;
  }
  public function __construct(\Application $app, $url, $width, $height) {
    parent::__construct($app);
    $this->url = $url;
    $this->width = intval($width);
    $this->height = intval($height);
  }
  public function render(\View $view, $id="u0_1") {
    $jsUrl = str_replace("/", "\\/", $this->url);
    return <<<IMAGE_MARKUP
<div class="imgs">
  <a target="_blank" imgsrc="https:{$this->url}" href="{$this->url}">
    <span class="img-placeholder" style="width:{$this->width};height:{$this->height}" id="{$id}"></span>
    <script type="text/javascript">
      onDOMContentLoaded(function(){new ImageLoader(\$("{$id}"), "{$jsUrl}", {$this->width}, {$this->height})})
    </script>
  </a>
  <div style="clear:both"></div>
</div>
IMAGE_MARKUP;
  }
}

?>