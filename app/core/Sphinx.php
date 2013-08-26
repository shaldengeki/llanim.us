<?php

// Sphinx.php
// Classes to assist with sphinxsearch.

class Sphinx extends SphinxClient {
  public static function Connect() {
    $cl = new Sphinx();
    $cl->setServer("localhost", Config::SPHINX_PORT);
    $cl->setMatchMode(SPH_MATCH_EXTENDED);
    return $cl;
  }
  public static function formatSearch($query, $fields) {
    if (is_array($fields) && empty($fields)) {
      return False;
    }
    $query = preg_replace("/[\s]+/", " ", $query);
    if (is_string($fields)) {
      $searchString = "@".$fields;
    } elseif (count($fields) == 1) {
      $searchString = "@".$fields[0];
    } else {
      $searchString = "@(".implode(",",$fields).")";
    }
    preg_match_all("/([\\+\\-\\!]?[\"\']\ *.*?\ *[\"\'])/",$query, $quoteArray);
    foreach ($quoteArray[0] as $splitQuote) {
      $searchString .= " ".$database->escape(substr($splitQuote, 1, strlen($splitQuote)-2));
    }
    $query = preg_replace("/[\+\-\!]?[\"\']\ *.*?\ *[\"\']/", "", $query);

    $query = preg_replace("/[\s]+/", " ", $query);
    $splitSpaces = explode(" ",$query);
    foreach ($splitSpaces as $searchParam) {
      if ($searchParam != "") {
        if ($searchParam[0] == "-" || $searchParam[0] == "!") {
          $searchString .= " -".$database->escape(substr($searchParam, 1, strlen($searchParam)));
        } else {
          $searchString .= " ".$database->escape($searchParam);
        }
      }
    }
    return $searchString;
  }
}
?>