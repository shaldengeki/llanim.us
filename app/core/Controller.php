<?php

interface Controller {
  public static function MODEL_URL();
  public static function MODEL_NAME();

  // called upon app initialization.
  public function __construct(\Application $app);

  // takes an object and returns a view.
  public function render($object);

  public function allow(\ETI\User $user);
}

?>