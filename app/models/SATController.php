<?php
namespace SAT;

class SATController extends \BaseController {
  public $app;

  public static function MODEL_URL() {
    return 'sat';
  }
  public static function MODEL_NAME() {
    return '\\SAT\\SAT';
  }

  public function __construct(\Application $app) {
    $this->app = $app;
  }

  public function render($object) {
    $header = \Application::view('header');
    $footer = \Application::view('footer');
    $resultView = new \View(Null, ['app' => $this->app]);
    switch ($this->app->action) {
      case 'index':
      default:
        $resultView = new \View(joinPaths(\Config::FS_ROOT, "views", "sat", $this->app->action.".php"), ['app' => $this->app]);
        $modelName = static::MODEL_NAME();
        $sats = $modelName::GetList($this->app, [
                                     'completed' => 1
                                    ]);
        foreach ($sats as $key=>$sat) {
          $sat->load('topic');
          $titleWords = explode(" ", $sat->topic[0]->title);
          switch (strtolower($titleWords[0])) {
            case 'spring':
              $panelClass = 'spring';
              break;
            case 'summer':
              $panelClass = 'summer';
              break;
            case 'autumn':
            case 'fall':
              $panelClass = 'fall';
              break;
            case 'winter':
              $panelClass = 'winter';
              break;
            default:
              $panelClass = Null;
              break;
          }
          $authors = [];
          foreach ($sat->getPostCounts(5) as $userID => $count) {
            $authors[$userID] = [
              'user' => new \ETI\User($this->app, intval($userID)),
              'count' => intval($count)
            ];
          }
          $sats[$key] = [
            'sat' => $sat,
            'terms' => $sat->getTerms(10),
            'authors' => $authors,
            'link' => $this->link($sat, $resultView, 'show', Null, Null, Null, $sat->topic[0]->title), 
            'panelClass' => $panelClass
          ];
        }
        krsort($sats);
        $resultView->attrs['sats'] = $sats;
        break;
    }
    return $resultView->prepend($header)->append($footer);
  }

  public function allow(\ETI\User $user) {
    return True;
  }
}


?>