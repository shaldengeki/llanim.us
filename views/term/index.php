<?php
  require_once($_SERVER['DOCUMENT_ROOT']."/app/include.php");
  \Application::check_partial_include(__FILE__);

  if (isset($this->attrs['terms'])) {
    $numTerms = count($this->attrs['terms']);
  } else {
    $this->attrs['terms'] = [];
    $numTerms = 0;
  }
?>
Coming soon!
<ol class="media-list term-list">
</ol>