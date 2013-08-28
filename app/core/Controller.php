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

abstract class BaseController implements Controller {
  public static function MODEL_URL() {
    return "";
  }

  public static function MODEL_NAME() {
    return "";
  }

  public $app;
  public function __construct(\Application $app) {
    $this->app = $app;
  }

  public function allow(\ETI\User $user) {
    return False;
  }

  public function url(\Model $model, $action="show", $format=Null, array $params=Null, $id=Null) {
    // returns the url that maps to this object and the given action.
    if ($id === Null) {
      $id = intval($model->id);
    }
    $urlParams = "";
    if (is_array($params)) {
      $urlParams = http_build_query($params);
    }
    return "/".rawurlencode(static::MODEL_URL())."/".($action !== "index" ? rawurlencode($id)."/".rawurlencode($action) : "").($format !== Null ? ".".rawurlencode($format) : "").($params !== Null ? "?".$urlParams : "");
  }
  public function link(\Model $model, \View $view, $action="show", $format=Null, array $urlParams=Null, $id=Null, $text="Show", $raw=False, array $params=Null) {
    // returns an HTML link to the current object's profile, with text provided.
    $linkParams = [];
    if ($action == "delete") {
      $urlParams['csrf_token'] = $this->app->csrfToken;
    }
    if (is_array($params) && $params) {
      foreach ($params as $key => $value) {
        $linkParams[] = $view->escape($key)."='".$view->escape($value)."'";
      }
    }
    return "<a href='".$this->url($model, $action, $format, $urlParams, $id)."' ".implode(" ", $linkParams).">".($raw ? $text : $view->escape($text))."</a>";
  }
  public function ajaxLink(\Model $model, \View $view, $action="show", array $urlParams=Null, $id=Null, $text="Show", $source=Null, $target=Null, $raw=False, array $params=Null) {
    if (!is_array($params)) {
      $params = [];
    }
    if ($source !== Null) {
      $params['data-url'] = $source;
    }
    if ($target !== Null) {
      $params['data-target'] = $target;
    }
    return $this->link($model, $view, $action, Null, $urlParams, $id, $text, $raw, $params);
  }
}

?>