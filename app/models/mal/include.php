<?php

$files = [
  'MALList.php'
];

foreach ($files as $file) {
  require_once(Config::FS_ROOT.'/app/models/mal/'.$file);
}

?>