<?php

interface Controller {
  public static function MODEL_URL();
  public static function MODEL_NAME();

  // called upon app initialization.
  public function __construct(\Application $app);

  // takes an object and returns a view.
  public function render($object);

  public function allow(\SAT\User $user);
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

  public function allow(\SAT\User $user) {
    return False;
  }

  public function url(\Model $model, $action="show", $format=Null, array $params=Null, $id=Null) {
    // returns the url that maps to this object and the given action.
    if ($id === Null) {
      $id = $model->id;
    }
    $urlParams = "";
    if (is_array($params)) {
      $urlParams = http_build_query($params);
    }
    $controllerName = get_class($model)."Controller";
    return "/".rawurlencode($controllerName::MODEL_URL())."/".($action !== "index" ? rawurlencode($id)."/".rawurlencode($action) : "").($format !== Null ? ".".rawurlencode($format) : "").($params !== Null ? "?".$urlParams : "");
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

  public function paginate($baseLink, $maxPages=Null, $ajaxTarget=Null) {
    // displays a pagination bar.
    // baseLink should be everything up to and including &page=
    $pageIncrement = 10;
    $displayFirstPages = 10;
    if ($ajaxTarget) {
      $link = "<a class='ajaxLink' data-url='".$baseLink."[PAGE]' data-target='".$ajaxTarget."' href='".$baseLink."[PAGE]'>";
    } else {
      $link = "<a href='".$baseLink."[PAGE]'>";
    }

    $output = "<div class='center-horizontal'><ul class='pagination'>\n";
    $i = 1;
    if ($this->app->page > 1) {
      $output .= "    <li>".str_replace("[PAGE]", $this->app->page-1, $link)."«</a></li>\n";
    }
    if ($maxPages !== Null) {
      while ($i <= $maxPages) {
        if ($i == $this->app->page) {
          $output .= "    <li class='active'><a href='#'>".$i."</a></li>";     
        } else {
          $output .= "    <li>".str_replace("[PAGE]", $i, $link).$i."</a></li>";
        }
        if ($i < $displayFirstPages || abs($this->app->page - $i) <= $pageIncrement ) {
          $i++;
        } elseif ($i >= $displayFirstPages && $maxPages <= $i + $pageIncrement) {
          $i++;
        } elseif ($i >= $displayFirstPages && $maxPages > $i + $pageIncrement) {
          $i += $pageIncrement;
        }
      }
    } else {
      while ($i < $this->app->page) {
          $output .= "    <li>".str_replace("[PAGE]", $i, $link).$i."</a></li>";
          $i++;
      }
      $output .= "<li class='active'><a href='#'>".$this->app->page."</a></li>";
    }
    if ($maxPages === Null || $this->app->page < $maxPages) {
      $output .= "    <li>".str_replace("[PAGE]", $this->app->page+1, $link)."»</a></li>\n";
    }
    $output .= "</ul></div>\n";
    return $output;
  }
  
}

?>