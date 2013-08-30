<?php
  require_once($_SERVER['DOCUMENT_ROOT']."/app/include.php");
  \Application::check_partial_include(__FILE__);

  $this->attrs['timeline'] = isset($this->attrs['timeline']) ? $this->attrs['timeline'] : [];
?>
<div class='row'>
  <div class='col-md-12'>
    <div id='term-timeline'></div>
  </div>
</div>