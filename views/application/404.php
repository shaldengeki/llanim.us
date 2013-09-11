<?php
  if (str_replace("\\", "/", __FILE__) === $_SERVER['SCRIPT_FILENAME']) {
    echo "This partial cannot be rendered by itself.";
    exit;
  }
?>
    <div class='center-horizontal'>
      <h1>Error (404): Not Found</h1>
      <img src='/img/404.png' />
      <p>The thing you're looking for no longer exists, sorry!</p>
    </div>