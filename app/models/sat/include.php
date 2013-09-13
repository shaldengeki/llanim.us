<?php

$files = [
  'Topic.php',
  'User.php',
];

foreach ($files as $file) {
  require_once(Config::FS_ROOT.'/app/models/sat/'.$file);
}

?>