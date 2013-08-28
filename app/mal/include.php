<?php

$files = [
  'MALList.php',
  'MALListController.php'
];

foreach ($files as $file) {
  require_once('app/mal/'.$file);
}

?>