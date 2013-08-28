<?php

class DateIntervalFormat extends DateInterval {
  public function formatShort() {
    $pieces = [];
    $years = intval($this->s/31536000);
    $months = intval(($this->s/2628000) - ($years*12));
    $days = intval(($this->s/86400) -  ($years * 365) - ($months * 30));
    $hours = intval ( ($this->s/3600) - ($days*24) - ($months * 720) - ($years * 8760));
    $minutes = intval( ($this->s/60) - ($hours*60) - ($days*1440) - ($months * 43200) - ($years * 525600) );
    $seconds = intval($this->s - ($minutes*60) - ($hours*3600) - ($days*86400) - ($months * 2592000) - ($years * 31536000) );

    if ($years) {
      $pieces[] = $years."yr";
    }
    if ($months) {
      $pieces[] = $months."mo";
    }
    if ($days) {
      $pieces[] = $days."d";
    }
    if ($hours) {
      $pieces[] = $hours."hr";
    }
    if ($minutes) {
      $pieces[] = $minutes."min";
    }
    if ($seconds) {
      $pieces[] = $seconds."sec";
    }
    return implode(" ", $pieces);
  }
}
?>