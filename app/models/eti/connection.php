<?php
namespace ETI;

class Connection {
  private $cookie, $app;
  private static $SITES = [
    'main' => [
      'url' => 'https://endoftheinter.net',
      'username' => 'b',
      'password' => 'p'
    ],
    'mobile' => [
      'url' => 'https://iphone.endoftheinter.net',
      'username' => 'username',
      'password' => 'password'
    ]
  ];

  public function __construct(\Application $app, $username, $password, $site="main") {
    $this->app = $app;
    if (!$this->login($username, $password, $site)) {
      throw new \Exception("Could not sign in to ETI with given credentials.");
    }
  }

  public function get($url) {
    $curl = new \Curl($url);
    return $curl->ssl(False)
                ->cookie($this->cookie)
                ->get();
  }
  public function parallelGet($urls, $callback, &$opts, $curlOpts=[]) {
    // override some default cURL opts with the $curlOpts provided.
    $defaultOpts = [
      CURLOPT_COOKIE => $this->cookie,
      CURLOPT_USERAGENT => "LLAnim.us",
      CURLOPT_ENCODING => "gzip,deflate",
      CURLOPT_REFERER => "",
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_FOLLOWLOCATION => 1,
      CURLOPT_MAXREDIRS => 2
    ];
    foreach ($curlOpts as $opt=>$value) {
      $defaultOpts[$opt] = $value;
    }

    // now get all of the given URLs in parallel.
    $parallelCurl = new \ParallelCurl(20, $defaultOpts);
    foreach ($urls as $url) {
      $parallelCurl->startRequest($url, $callback, $opts);
    }
    $parallelCurl->finishAllRequests();
  }

  public function post($url, $fields) {
    $curl = new \Curl($url);
    return $curl->ssl(False)
                ->cookie($this->cookie)
                ->fields($fields)
                ->post();
  }

  public function login($username, $password, $site="main") {
    if (!isset(static::$SITES[$site])) {
      throw new \Exception("No such login site method was found: ".$site);
    }
    $fields = [
              static::$SITES[$site]['username'] => $username,
              static::$SITES[$site]['password'] => $password,
              ];

    $curl = new \Curl(static::$SITES[$site]['url']);
    $header = $curl->ssl(False)
                    ->header(True)
                    ->fields($fields)
                    ->post();
    if (!$header) {
      return False;
    }
    $headers = http_parse_headers($header);

    if (!isset($headers["Set-Cookie"])) {
      return False;
    }
    $this->cookie = implode(";", $headers["Set-Cookie"]);
    return $this;
  }

  public function pmThreads($start=0, $limit=50) {
    // returns a list of PMs sent to this user.
    $threads = [];
    $opts = [
            'app' => $this->app,
            'threads' => &$threads
            ];
    //now loop over each page.
    $startPage = intval($start/50)+1;
    $numPages = intval(($limit-1)/50) + 1;
    $pmUrls = [];
    for ($currPage = $startPage; $currPage < $startPage + $numPages; $currPage++) {
      $pmUrls[] = "https://endoftheinter.net/inbox.php?page=".$currPage;
    }
    $this->parallelGet($pmUrls, '\\ETI\\PMThread::ParseListing', $opts, []);

    // sort the list of PMs by last-post, newest to oldest.
    array_sort_by_property($threads, 'last_post');
    return $threads;
  }
}
?>