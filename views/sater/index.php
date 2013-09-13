<?php
  require_once($_SERVER['DOCUMENT_ROOT']."/app/include.php");
  $this->app->check_partial_include(__FILE__);

  if (isset($this->attrs['users'])) {
    $numUsers = count($this->attrs['users']);
  } else {
    $this->attrs['users'] = [];
    $numUsers = 0;
  }
?>
Coming soon!
<ol class="media-list user-list">
</ol>