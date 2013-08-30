<?php

$files = [
  'Topic.php',
  'User.php',
];

foreach ($files as $file) {
  require_once('app/models/sat/'.$file);
}

?>