<?php
namespace MAL;

class MALListController implements \Controller {
  public $app;

  public static function MODEL_URL() {
    return 'mal';
  }
  public static function MODEL_NAME() {
    return '\\MAL\\MALList';
  }

  public function __construct(\Application $app) {
    $this->app = $app;
  }

  public function render($object) {
    $header = \Application::view('header');
    $footer = \Application::view('footer');
    $resultView = new \View(joinPaths(\Config::FS_ROOT, "views", "mal", $this->app->action.".php"), ['app' => $this->app]);
    switch ($this->app->action) {
      default:
        break;
    }
    return $resultView->prepend($header)->append($footer);
  }

  public function allow(\ETI\User $user) {
    return True;
  }
}

function parseMALList($username, $type="anime") {
  // hits a MAL list of the given type for the given username.
  // returns an associative array containing the resultant XML, or False if an error occurred.

  $outputTimezone = new DateTimeZone(Config::OUTPUT_TIMEZONE);
  $serverTimezone = new DateTimeZone(Config::SERVER_TIMEZONE);
  $nowTime = new DateTime("now", $serverTimezone);

  $curl = new Curl("http://myanimelist.net/malappinfo.php?u=".rawurlencode($username)."&status=all&type=".rawurlencode($type));
  $xmlPage = $curl->get();

  $listXML = new DOMDocument();
  $listXML->loadXML($xmlPage);
  $animeNodes = $listXML->getElementsByTagName('anime');
  $animeList = [];
  foreach ($animeNodes as $animeNode) {
    $animeID = intval($animeNode->getElementsByTagName('series_animedb_id')->item(0)->nodeValue);
    $episode = intval($animeNode->getElementsByTagName('my_watched_episodes')->item(0)->nodeValue);
    $startDate = $animeNode->getElementsByTagName('my_start_date')->item(0)->nodeValue;
    $endDate = $animeNode->getElementsByTagName('my_finish_date')->item(0)->nodeValue;
    $lastUpdated = intval($animeNode->getElementsByTagName('my_last_updated')->item(0)->nodeValue);
    $status = intval($animeNode->getElementsByTagName('my_status')->item(0)->nodeValue);
    $score = intval($animeNode->getElementsByTagName('my_score')->item(0)->nodeValue);

    if ($lastUpdated === '0000-00-00') {
      if ($endDate === '0000-00-00') {
        if (!$startDate) {
          $time = $nowTime;
        } else {
          $time = new DateTime($startDate, $serverTimezone);
        }
      } else {
        $time = new DateTime($endDate, $serverTimezone);
      }
    } else {
      $time = new DateTime('@'.$lastUpdated, $serverTimezone);
    }
    $animeList[intval($animeID)] = [
      'anime_id' => $animeID,
      'episode' => $episode,
      'score' => $score,
      'status' => $status,
      'time' => $time->format("Y-m-d H:i:s")
    ];
  }
  return $animeList;
}
function getGlobalMALList($database) {
  // returns a list of the latest animeid,title,score,status arrays for each anime belonging to this user's MAL list.
  return $database->toArray("SELECT `p`.`animeid`, `mal_anime_info`.`title`, `p`.`score`, `p`.`status` FROM (
                      SELECT v.* FROM (
                          SELECT `animeid`, MAX(`list_id`) `list_id` FROM `seinma_llanimu`.`mal_animelist_changes`
                          group by `animeid`
                      ) `m`
                      INNER JOIN `seinma_llanimu`.`mal_animelist_changes` `v` ON `m`.`animeid` = `v`.`animeid` AND `m`.`list_id` = `v`.`list_id`
                  ) `p` LEFT OUTER JOIN `mal_anime_info` ON `mal_anime_info`.`animeid` = `p`.`animeid`
                  ORDER BY `p`.`score` DESC, `mal_anime_info`.`title` ASC");
}

function getUserMALList($database, $userID=Null, $onlyScored=False, $malID=False) {
  // returns a list of the latest animeid,title,score,status arrays for each anime belonging to this user's MAL list.
  // if no userID is provided, fetches the global 
  if ($userID === Null) {
    $userIDFilter = "";
  } else {
    $userIDFilter = "WHERE `mal_userid` = ".intval($userID);
  }
  if ($onlyScored === False) {
    $scoreFilter = "";
  } else {
    $scoreFilter = " && `p`.`score` != 0";
  }
  return $database->toArray("SELECT `p`.`mal_userid`, `sat_users`.`ll_userid`, `users`.`username`, `p`.`animeid`, `mal_anime_info`.`title`, `p`.`score`, `p`.`status` FROM (
                      SELECT v.* FROM (
                          SELECT `animeid`, MAX(`list_id`) `list_id` FROM `seinma_llanimu`.`mal_animelist_changes`
                          ".$userIDFilter."
                          GROUP BY `animeid`
                      ) `m`
                      INNER JOIN `seinma_llanimu`.`mal_animelist_changes` `v` ON `m`.`animeid` = `v`.`animeid` AND `m`.`list_id` = `v`.`list_id`
                  ) `p` LEFT OUTER JOIN `mal_anime_info` ON `mal_anime_info`.`animeid` = `p`.`animeid`
                  LEFT OUTER JOIN `sat_users` ON `sat_users`.`mal_userid` = `p`.`mal_userid`
                  LEFT OUTER JOIN `seinma_llusers`.`users` ON `users`.`id` = `sat_users`.`ll_userid`
                  WHERE `p`.`status` != 0".$scoreFilter." && `sat_users`.`visible` = 1
                  ORDER BY `sat_users`.`ll_userid` ASC, `p`.`score` DESC, `mal_anime_info`.`title` ASC");
}

function getAnimeRatings($database, $malID=Null, $latest=True) {
  // returns a list of mal_userid,username,time,rating,status arrays for the ratings belonging to this anime.
  // if no malID is provided, fetches ratings for all anime.
  // if latest is False, fetches all non-zero ratings for the anime provided.
  if ($malID === Null) {
    $animeIDField = ", `animeid`";
    $malIDFilter = "WHERE `animeid` = `animeid`";
  } else {
    $animeIDField = "";
    $malIDFilter = "WHERE `animeid` = ".intval($malID);
  }
  if ($latest === False) {
    // grab everything.
    return $database->toArray("SELECT `mal_animelist_changes`.`mal_userid`, `users`.`username`".$animeIDField.", `mal_animelist_changes`.`time`, `mal_animelist_changes`.`score`, `mal_animelist_changes`.`status` FROM `mal_animelist_changes` 
                                        LEFT OUTER JOIN `sat_users` ON `sat_users`.`mal_userid` = `mal_animelist_changes`.`mal_userid`
                                        LEFT OUTER JOIN `seinma_llusers`.`users` ON `users`.`id` = `sat_users`.`ll_userid`
                                        ".$malIDFilter." && `score` != 0 && `sat_users`.`visible` = 1
                                        ORDER BY `time` ASC");
  } else {
    return $database->toArray("SELECT `p`.`mal_userid`, `users`.`username`".$animeIDField.", `p`.`time`, `p`.`score`, `p`.`status` FROM (
                      SELECT v.* FROM (
                          SELECT `mal_userid`, MAX(`list_id`) `list_id` FROM `seinma_llanimu`.`mal_animelist_changes`
                          ".$malIDFilter."
                          GROUP BY `mal_userid`, `animeid`
                      ) `m`
                      INNER JOIN `seinma_llanimu`.`mal_animelist_changes` `v` ON `m`.`mal_userid` = `v`.`mal_userid` AND `m`.`list_id` = `v`.`list_id`
                    ) `p` 
                    LEFT OUTER JOIN `sat_users` ON `sat_users`.`mal_userid` = `mal_animelist_changes`.`mal_userid`
                    LEFT OUTER JOIN `seinma_llusers`.`users` ON `users`.`id` = `sat_users`.`ll_userid`
                    WHERE `p`.`score` != 0 && `p`.`status` != 0 && `sat_users`.`visible` = 1
                    ORDER BY `p`.`time` ASC");
  }
}
?>