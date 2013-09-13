<?php

$files = [
  'base.php',
  'user.php',
  'post_node.php',
  'post.php',
  'text.php',
  'link.php',
  'quote.php',
  'image.php',
  'spoiler.php',
  'tag.php',
  'topic.php'
];

foreach ($files as $file) {
  require_once(Config::FS_ROOT.'/app/models/eti/'.$file);
}

?>