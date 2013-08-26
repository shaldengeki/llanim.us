<?php
namespace MAL;

class List {
  public static function parse($username, $type="anime") {
    // hits a MAL list of the given type for the given username.
    // returns an associative array containing the resultant XML, or False if an error occurred.

    $outputTimezone = new DateTimeZone(Config::OUTPUT_TZ);
    $serverTimezone = new DateTimeZone(Config::SERVER_TZ);
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
}

?>