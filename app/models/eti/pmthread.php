<?php
namespace ETI;

class PMThread extends Base {
  public static $TABLE = "pm_threads";
  public static $PLURAL = "PMThreads";
  public static $FIELDS = [
    'id' => [
      'type' => 'int',
      'db' => 'id'
    ],
    'subject' => [
      'type' => 'string',
      'db' => 'subject'
    ],
    'user_1_id' => [
      'type' => 'int',
      'db' => 'user_1_id'
    ],
    'user_2_id' => [
      'type' => 'int',
      'db' => 'user_2_id'
    ],
    'read' => [
      'type' => 'bool',
      'db' => 'read'
    ]
  ];
  public static $JOINS = [
    'user_1' => [
      'obj' => '\\ETI\\User',
      'table' => 'users',
      'own_col' => 'user_1_id',
      'join_col' => 'id',
      'type' => 'one'
    ],
    'user_2' => [
      'obj' => '\\ETI\\User',
      'table' => 'users',
      'own_col' => 'user_2_id',
      'join_col' => 'id',
      'type' => 'one'
    ],
    'pms' => [
      'obj' => '\\ETI\\PrivateMessage',
      'table' => 'private_messages',
      'own_col' => 'id',
      'join_col' => 'thread_id',
      'type' => 'many'
    ]
  ];
  public static function ParseListing($html, $url, $curl, $opts) {
    // takes the HTML of a PM page and appends the PM threads contained within to $threads.
    // $opts should contain:
    // 'app' => \Application
    // 'threads' => list of PM threads (constructed by reference!)

    $app = $opts['app'];
    $threads = &$opts['threads'];

    $dom = static::CreateDom($html);
    $pmTable = $dom->getElementsByClassName("grid")[0];
    foreach ($pmTable->getElementsByTagName("tr") as $pmRow) {
      $pmFields = $pmRow->getElementsByTagName("td");
      if (!$pmFields || $pmFields->length == 0) {
        // header row for PM table.
        continue;
      }
      if ($pmFields->item(0)->getElementsByTagName("b")->length == 0) {
        $threadRead = True;
        $threadLink = $pmFields->item(0)->getElementsByTagName("a")->item(0);
      } else {
        $threadRead = False;
        $threadLink = $pmFields->item(0)->getElementsByTagName("b")->item(0)->getElementsByTagName("a")->item(0);
      }
      $id = intval(get_enclosed_string($threadLink->attributes->getNamedItem("href")->nodeValue, "thread=", ""));

      $otherUserLink = $pmFields->item(1)->getElementsByTagName("a")->item(0);
      $otherUserID = intval(get_enclosed_string($otherUserLink->attributes->getNamedItem("href")->nodeValue, "user=", ""));
      $otherUser = new User($app, intval($otherUserID));
      $otherUser->set([
                      'name' => $otherUserLink->textContent
                      ]);

      $lastPostText = $pmFields->item(3)->textContent;
      $pmAttrs = [
        'id' => $id,
        'subject' => $threadLink->textContent,
        'other_user' => $otherUser,
        'length' => intval($pmFields->item(2)->textContent),
        'last_post' => \DateTime::createFromFormat('n/j/Y G:i', $lastPostText, new \DateTimeZone("America/Chicago")),
        'read' => $threadRead
      ];
      $newPM = new PMThread($app, $id);
      $newPM->set($pmAttrs);
      $threads[] = $newPM;
    }
  }

  public static function ParseThread($html, $url, $curl, $opts) {
    // takes the HTML of a PM thread and appends the PM posts contained within to $pms.
    // $opts should contain:
    // 'app' => \Application
    // 'pms' => list of PMs (constructed by reference!)
    // 'thread' => PMThread these PMs are associated with

    $app = $opts['app'];
    $pms = &$opts['pms'];
    $thread = $opts['thread'];

    $dom = static::CreateDom($html);
    foreach ($dom->getElementsByClassName("message-container") as $messageContainer) {
      $nodeAttrs = \Dom\DomNode::GetAttributes($messageContainer);
      $pmID = (int) substr($nodeAttrs['id'], 1);
      $newPM = new PrivateMessage($app, $pmID);
      $newPM->set([
                  'thread' => $thread,
                  'thread_id' => $thread->id
                  ]);

      $messageTop = $messageContainer->getElementsByTagName("div")->item(0);
      $userLink = $messageTop->getElementsByTagName("a")->item(0);
      $userID = (int) get_enclosed_string(\Dom\DomNode::GetAttributes($userLink)['href'], 'user=', '');
      $username = $userLink->textContent;
      $user = new User($app, $userID);
      $user->set([
                 'name' => $username
                 ]);

      $dateLabel = $messageTop->getElementsByTagName("b")->item(1);
      $dateText = $dateLabel->nextSibling->textContent;
      $date = \DateTime::createFromFormat(' n/j/Y h:i:s A \| ', $dateText, new \DateTimeZone("America/Chicago"));

      $bodyNode = $messageContainer->getElementsByTagName("table")->item(0)->getElementsByTagName("tr")->item(0)->getElementsByTagName("td")->item(0);
      $messageText = \Dom\DomNode::GetInnerHTML($bodyNode);

      if (preg_match("/^(?P<message>.*?)(<br\/>\\n)?(---<br\/>\\n(?P<sig>.*))?$/ms", $messageText, $matches) !== 0) {
        $message = $matches['message'];
        $sig = $matches['sig'];
      } else {
        $message = $messageText;
        $sig = "";
      }

      $newPM->set([
                  'user_id' => $user->id,
                  'user' => $user,
                  'date' => $date->format('U'), 
                  'messagetext' => $message,
                  'sig' => $sig
                  ]);
      $pms[] = $newPM;
    }
  }

  public function fetch(Connection $etiConn) {
    // fetches a PM thread from ETI.
    $startPage = 1;
    $pms = [];
    $opts = [
            'app' => $this->app,
            'pms' => &$pms,
            'thread' => $this
            ];

    // if this thread does not has a length defined then we must fetch the number of pages.
    if (!isset($this->length) || $this->length === Null) {
      $firstPageUrl = 'https://endoftheinter.net/inboxthread.php?thread='.$this->id;
      $firstPage = $etiConn->get($firstPageUrl);
      $dom = static::CreateDom($firstPage);
      $numPages = intval($dom->getElementsByClassName("infobar")[0]->getElementsByTagName("span")->item(0)->textContent);

      // parse this first page.
      \ETI\PMThread::ParseThread($firstPage, $firstPageUrl, Null, $opts);
      $startPage = 2;
    } else {
      $numPages = intval(($this->length-1)/50)+1;
    }

    $pmPageUrls = [];
    //now loop over each page.
    for ($pageNum = $startPage; $pageNum < $startPage + $numPages; $pageNum++) {
      $pmPageUrls[] = "https://endoftheinter.net/inboxthread.php?thread=".$this->id."&page=".$pageNum;
    }
    $etiConn->parallelGet($pmPageUrls, '\\ETI\\PMThread::ParseThread', $opts);

    // sort the list of PMs by last-post, newest to oldest.
    array_sort_by_property($pms, 'date');
    return $pms;
  }

}
?>