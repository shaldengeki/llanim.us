<?php

$files = [
  'MALList.php'
];

foreach ($files as $file) {
  require_once('app/models/mal/'.$file);
}

?>