<?php

class User {
  
  public $app, $id;
  
  public function __init(Application $app, $id) {
    $this->app = $app;
    $this->id = $id;
  }
}
?>