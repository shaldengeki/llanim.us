<?php
namespace ETI;

class Post extends Base {
  public static $TABLE = "posts";
  public static $PLURAL = "Posts";
  public static $FIELDS = [
    'id' => [
      'type' => 'int',
      'db' => 'll_messageid'
    ],
    'topic_id' => [
      'type' => 'int',
      'db' => 'll_topicid'
    ],
    'user_id' => [
      'type' => 'int',
      'db' => 'userid'
    ],
    'date' => [
      'type' => 'timestamp',
      'db' => 'date'
    ],
    'html' => [
      'type' => 'str',
      'db' => 'messagetext'
    ],
    'sig' => [
      'type' => 'str',
      'db' => 'sig'
    ]
  ];
  public static $JOINS = [
    'user' => [
      'obj' => '\\ETI\\User',
      'table' => 'users',
      'own_col' => 'userid',
      'join_col' => 'id',
      'type' => 'one'
    ],
    'topic' => [
      'obj' => '\\ETI\\Topic',
      'table' => 'topics',
      'own_col' => 'll_topicid',
      'join_col' => 'll_topicid',
      'type' => 'one'
    ]
  ];

  public $nodes, $text, $quotes, $images, $spoilers, $links, $user, $topic;

  public function parse() {
    $nodes = [];
    if (!isset($this->dom)) {
      $this->getDOM();
    }
    if ($this->dom->documentElement && $this->dom->documentElement->childNodes) {
      $childNodes = $this->dom->documentElement->childNodes->item(0)->childNodes;
      for ($i = 0; $i < $childNodes->length; $i++) {
        $node = $childNodes->item($i);
        if (Quote::isNode($node)) {
          $parseResult = Quote::parseQuote($this->app, $childNodes, $i);
          if ($parseResult !== False) {
            $nodes[] = $parseResult['quote'];
            // $i = $parseResult['offset'];
          }
          continue;
        } elseif (Image::isContainer($node)) {
          $nodes = array_merge($nodes, Image::parse($this->app, $node));
          continue;
        } elseif (Spoiler::isNode($node)) {
          $nodes[] = Spoiler::parse($this->app, $node);
          continue;
        } elseif (Link::isNode($node)) {
          $nodes[] = Link::parse($this->app, $node);
          continue;
        } else {
          $nodes[] = Text::parse($this->app, $node);
        }
      }
    }
    $this->nodes = $nodes;
    return $this;
  }

  public function getDOM() {
    if (!isset($this->html)) {
      $this->load();
    }
    $this->dom = static::CreateDom($this->html);
  }

  protected function getText() {
    // takes an ETI post's message body
    // returns JUST the alphanumeric words posted by the user, not including quotes, images, or links.

    if (!isset($this->dom)) {
      $this->getDOM();
    }

    $domCopy = clone $this->dom;

    $landingDiv = $domCopy->createElement("landingDiv");
    if (!$domCopy->getElementsByTagName("body")->item(0)) {
      return "";
    }
    $domCopy->getElementsByTagName("body")->item(0)->appendChild($landingDiv);

    //remove quotes.
    $divElts = $domCopy->getElementsByTagName("div");
    foreach ($divElts as $divElt) {
      if ($divElt->getAttribute("class") == "quoted-message") {
        if ($divElt->parentNode->textContent == $divElt->textContent) {
          //the whole message is this quote.
          continue;
        } else {
          $divElt->parentNode->removeChild($divElt);
        }
      }
    }
    $divElts = $domCopy->getElementsByTagName("div");
    foreach ($divElts as $divElt) {
      if ($divElt->getAttribute("class") == "quoted-message") {
        //anything left over in the second pass will be a whole-message quote.
        //remove any potential message-tops in this quote.
        $quoteDivElts = $divElt->getElementsByTagName('div');
        if (count($quoteDivElts) > 0) {
          foreach ($quoteDivElts as $quoteDivElt) {
            $quoteDivElt->parentNode->removeChild($quoteDivElt);
          }
        }
        $newP = new \DOMElement('p', \Dom\DomNode::GetInnerHTML($divElt));
        $divElt->parentNode->removeChild($divElt);

      }
    }

    //remove spoilers.
    $spanElts = $domCopy->getElementsByTagName("span");
    foreach ($spanElts as $spanElt) {
      $childNodes = $spanElt->childNodes;
      if ($childNodes && $childNodes->item(0) && $childNodes->item(0)->nodeType == 1 && $childNodes->item(0)->getAttribute("class") == "spoiler_on_close") {
        $spoilerCaption = get_enclosed_string($childNodes->item(1)->getElementsByTagName("a")->item(0)->textContent, "<", ">");
        //there's a more elegant way to do this but fuckit.
        $spoilerInnerLinks = $childNodes->item(1)->getElementsByTagName("a");
        foreach ($spoilerInnerLinks as $spoilerInnerLink) {
          $spoilerInnerLink->parentNode->removeChild($spoilerInnerLink);
        }
        $spoilerInnerLinks = $childNodes->item(1)->getElementsByTagName("a");
        foreach ($spoilerInnerLinks as $spoilerInnerLink) {
          $spoilerInnerLink->parentNode->removeChild($spoilerInnerLink);
        }
        
        $spoilerText = $childNodes->item(1)->textContent;
        if ($spoilerCaption != "spoiler") {
          $spoilerText = $spoilerText." ".$spoilerCaption;
        }
        $newP = new \DOMElement('p', mb_ereg_replace("&", "&amp;", $spoilerText));
        $landingDiv->appendChild($newP);
        $domCopy->getElementsByTagName("body")->item(0)->removeChild($landingDiv);
        $domCopy->getElementsByTagName("body")->item(0)->appendChild($landingDiv);
        $spanElt->parentNode->removeChild($spanElt);
      }
    }

    //remove images.
    $divElts = $domCopy->getElementsByTagName("div");
    foreach ($divElts as $divElt) {
      if ($divElt->getAttribute("class") == "imgs") {
        $divElt->parentNode->removeChild($divElt);
      }
    }

    //remove links.
    $finder = new \DomXPath($domCopy);
    $linkNodes = $domCopy->getElementsByTagName("a");
    while ($linkNodes->length) {
      $linkNodes->item(0)->parentNode->removeChild($linkNodes->item(0));
    }
    
    //remove scripts.
    $scriptNodes = $domCopy->getElementsByTagName("script");
    for ($i = 0; $i < $scriptNodes->length; $i++) {
      $scriptNodes->item($i)->parentNode->removeChild($scriptNodes->item($i));
      $i--;
    }
    
    //remove pre tags.
    $spanElts = $domCopy->getElementsByTagName("span");
    foreach ($spanElts as $spanElt) {
      if ($spanElt->getAttribute("class") == "pr") {
        $newP = new \DOMElement('p', mb_ereg_replace("&", "&amp;", $spanElt->textContent));
        $landingDiv->appendChild($newP);
        $domCopy->getElementsByTagName("body")->item(0)->removeChild($landingDiv);
        $domCopy->getElementsByTagName("body")->item(0)->appendChild($landingDiv);
        $spanElt->parentNode->removeChild($spanElt);
      }
    }
    
    //remove bold, underline, italic tags.
    $formattingTags = ['b', 'u', 'i'];
    foreach ($formattingTags as $formattingTag) {
      $formatElements = $domCopy->getElementsByTagName($formattingTag);
      for ($i = 0; $i < $formatElements->length; $i++) {
        $newP = new \DOMElement('p', mb_ereg_replace("&", "&amp;", $formatElements->item($i)->textContent));
        $landingDiv->appendChild($newP);
        $domCopy->getElementsByTagName("body")->item(0)->removeChild($landingDiv);
        $domCopy->getElementsByTagName("body")->item(0)->appendChild($landingDiv);
        $formatElements->item($i)->parentNode->removeChild($formatElements->item($i));
        $i--;
      }
    }

    //process landingDiv stuff.
    $landingDivChildren = $landingDiv->childNodes;
    $extractedText = [];
    foreach ($landingDivChildren as $landingDivChild) {
      $extractedText[] = $landingDivChild->textContent;
    }

    $bodyChildren = $domCopy->getElementsByTagName("body")->item(0)->childNodes;
    foreach ($bodyChildren as $bodyChild) {
      if ($bodyChild->nodeType !== 3) {
        $extractedText[] = $bodyChild->textContent;
        $domCopy->getElementsByTagName("body")->item(0)->removeChild($bodyChild);
      }
    }
    try {
      $domCopy->getElementsByTagName("body")->item(0)->removeChild($landingDiv);
    } catch (\DOMException $e) {
      // no landing div, just keep goin'.
    }

    $postText = get_biggest_enclosed_string($domCopy->saveHTML(), '<body>', '</body>').implode(' ', $extractedText);
    
    //convert all text to lowercase for consistency.
    $postText = mb_strtolower($postText);

    //replace line breaks.
    $postText = nl2br($postText);
    $postText = preg_replace(["/(<br(( )?\/)?>)+/is", '/(\r\n)+/is', '/[\r\n]+/is'], ' ', $postText);

    //fuckit remove all tags.
    $tag_pattern_open = '(<[a-z0-9]+.*?>)';
    $tag_pattern_close = '(<\/[a-z0-9]+>)';
    $postText = preg_replace("/".$tag_pattern_open."/is", '', $postText);
    $postText = preg_replace("/".$tag_pattern_close."/is", '', $postText);

    //convert htmlentities back to symbols.
    $postText = html_entity_decode($postText, ENT_QUOTES);

    //remove contraction apostrophes.
    $postText = preg_replace("/([a-z0-9])'([a-z0-9])/i", "$1$2", $postText);

    //remove all non-alphanumeric text.
    $postText = preg_replace("/[^a-z0-9\s]/is", " ", $postText);

    //collapse multiple spaces.
    $postText = preg_replace('/\s+/', ' ', $postText);
    
    $this->text = $postText;
  }
  public function text() {
    if (!isset($this->text)) {
      $this->getText();
    }
    return $this->text;
  }

  function __construct(\Application $app, $id) {
    parent::__construct($app, $id);
  }
  public function nodes() {
    if (!isset($this->nodes) || !is_array($this->nodes)) {
      $this->parse();
    }
    return $this->nodes;
  }
  public function quotes() {
    if (!isset($this->quotes)) {
      $this->quotes = [];
      // search for quotes inside all nodes except other quote nodes.
      static::searchNodeType($this->nodes(), 'Quote', $this->quotes, ['Quote']);
    }
    return $this->quotes;
  }

  public function images() {
    if (!isset($this->images)) {
      $this->images = [];
      // search for images inside all nodes except quote nodes.
      static::searchNodeType($this->nodes(), 'Image', $this->images, ['Quote']);
    }
    return $this->images;
  }

  public function spoilers() {
    if (!isset($this->spoilers)) {
      $this->spoilers = [];
      // search for spoilers inside all nodes except quote nodes.
      static::searchNodeType($this->nodes(), 'Spoiler', $this->spoilers, ['Quote']);
    }
    return $this->spoilers;
  }

  public function links() {
    if (!isset($this->links)) {
      $this->links = [];
      // search for links inside all nodes except quote nodes.
      static::searchNodeType($this->nodes(), 'Link', $this->links, ['Quote']);
    }
    return $this->links;
  }

  protected function user() {
    if (!isset($this->user_id)) {
      $this->load();
    }
    if (!isset($this->user)) {
      $this->user = new User($this->app,  (int) $this->user_id);
    }
    return $this->user;
  }

  protected function topic() {
    if (!isset($this->topic_id)) {
      $this->load();
    }
    if (!isset($this->topic)) {
      $this->topic = new Topic($this->app,  (int) $this->topic_id);
    }
    return $this->topic;
  }

  public function exclude() {
    // returns a new Post with all of the types of nodes in get_func_args() stripped out.

    $excludeTypes = func_get_args();
    $newNodeSet = [];
    foreach ($this->nodes() as $node) {
      if (!in_array($node->nodeType(), $excludeTypes)) {
        $newNodeSet[] = $node;
      }
    }
    $newPost = new Post($this->app, $this->id);
    $newPost->set([
                  'topic_id' => $this->topic->id,
                  'topic' => $this->topic,
                  'user_id' => $this->user->id,
                  'user' => $this->user,
                  'nodes' => $newNodeSet
                  ]);
    return $newPost;
  }

  public function render(\View $view) {
    $user = $this->user();
    $topic = $this->topic();
    $date = $this->date->format('n/j/Y h:i:s A');

    $contents = "";
    foreach ($this->nodes() as $node) {
      $contents .= $node->render($view);
    }

    return <<<POST_MARKUP
<div class="message-container" id="m{$this->id}">
  <div class="message-top">
    <b>From:</b> <a href="//endoftheinter.net/profile.php?user={$user->id}">{$user->name}</a> | <b>Posted:</b> {$date} | <a href="//boards.endoftheinter.net/showmessages.php?topic={$topic->id}&amp;u={$user->id}">Filter</a> | <a href="/message.php?id={$this->id}&amp;topic={$topic->id}&amp;r=0">Message Detail</a> | <a href="/postmsg.php?topic={$topic->id}&amp;quote={$this->id}" onclick="return QuickPost.publish('quote', this);">Quote</a>
  </div>
  <table class="message-body">
    <tr>
      <td msgid="t,{$topic->id},{$this->id}@0" class="message">
{$contents}
---<br />
{$this->sig}</td>
      <td class="userpic">
        <div class="userpic-holder">
          <a href="{$user->avatar}">
            <span class="img-placeholder" style="width:150px;height:144px" id="u0_8"></span>
            <script type="text/javascript">
              onDOMContentLoaded(function(){new ImageLoader($("u0_8"), "{$user->avatar}", 150, 144)})
            </script>
          </a>
        </div>
      </td>
    </tr>
  </table>
</div>
POST_MARKUP;
  }
}

?>