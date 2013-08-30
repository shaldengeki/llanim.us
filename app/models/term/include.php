<?php

$files = [
  'Term.php'
];

foreach ($files as $file) {
  require_once('app/models/term/'.$file);
}

?>