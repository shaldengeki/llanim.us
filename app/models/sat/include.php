<?php

$files = [
  'SAT.php'
];

foreach ($files as $file) {
  require_once('app/models/sat/'.$file);
}

?>