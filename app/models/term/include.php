<?php

$files = [
  'Term.php'
];

foreach ($files as $file) {
  require_once(Config::FS_ROOT.'/app/models/term/'.$file);
}

?>