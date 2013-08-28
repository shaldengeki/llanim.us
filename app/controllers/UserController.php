<?php
namespace ETI;

class UserController extends \BaseController {
  public $app;

  public static function MODEL_URL() {
    return 'user';
  }
  public static function MODEL_NAME() {
    return '\\ETI\\User';
  }

  public function __construct(\Application $app) {
    $this->app = $app;
  }

  public function render($object) {
    $header = \Application::view('header');
    $footer = \Application::view('footer');
    $resultView = new \View(joinPaths(\Config::FS_ROOT, "views", static::MODEL_URL(), $this->app->action.".php"), ['app' => $this->app]);
    switch ($this->app->action) {
      case 'show':
        break;
      case 'index':
      default:
        $header->attrs['title'] = $header->attrs['subtitle'] = "Users";
        $modelName = static::MODEL_NAME();
        $users = $modelName::GetList($this->app);
        foreach ($users as $key=>$user) {
          // $user->load('topic');
          // $titleWords = explode(" ", $user->topic->title);
          // switch (strtolower($titleWords[0])) {
          //   case 'spring':
          //     $panelClass = 'spring';
          //     break;
          //   case 'summer':
          //     $panelClass = 'summer';
          //     break;
          //   case 'autumn':
          //   case 'fall':
          //     $panelClass = 'fall';
          //     break;
          //   case 'winter':
          //     $panelClass = 'winter';
          //     break;
          //   default:
          //     $panelClass = Null;
          //     break;
          // }
          // $authors = [];
          // foreach ($sat->getPostCounts(5) as $userID => $count) {
          //   $user = new \ETI\User($this->app, intval($userID));
          //   $authors[$userID] = [
          //     'user' => $user,
          //     'link' => $this->link($sat, $resultView, 'show', Null, Null, Null, $user->name),
          //     'count' => intval($count)
          //   ];
          // }
          // $sats[$key] = [
          //   'sat' => $sat,
          //   'terms' => $sat->getTerms(10),
          //   'authors' => $authors,
          //   'link' => $this->link($sat, $resultView, 'show', Null, Null, Null, $sat->topic->title), 
          //   'panelClass' => $panelClass
          // ];
        }
        krsort($users);
        $resultView->attrs['users'] = $users;
        break;
    }
    return $resultView->prepend($header)->append($footer);
  }

  public function allow(\ETI\User $user) {
    return True;
  }
}
?>