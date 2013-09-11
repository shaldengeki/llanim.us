<?php
namespace Term;

class TermController extends \BaseController {
  public $app;

  public static function MODEL_URL() {
    return 'term';
  }
  public static function MODEL_NAME() {
    return '\\Term\\Term';
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
        $header->attrs['title'] = $header->attrs['subtitle'] = $object->id;

        $satIDs = array_map(function($sat) {
          return $sat->id;
        }, \SAT\Topic::GetList($this->app, [
                             'completed' => 1
                             ]));

        // term tfidf timeline.
        $satNum = 1;
        $termTimeline = [];
        $termTopics = $object->topics();
        foreach ($satIDs as $satID) {
          $termTimeline[] = ['sat' => $satNum, 'tfidf' => isset($termTopics[$satID]) ? $termTopics[$satID]['tfidf'] : 0];
          $satNum++;
        }
        $timelineAttrs = [
          'id' => 'term-timeline',
          'type' => 'LineChart',
          'height' => 400,
          'width' => '100%',
          'title' => 'Timeline',
          'chartArea' => ['width' => '100%', 'height' => '80%'],
          'legend' => ['position' => 'none'],
          'backgroundColor' => '#FFFFFF'
        ];
        $timelineSeriesProperties = [
          'sat' => ['title' => 'SAT', 'type' => 'number'],
          'tfidf' => ['title' => 'Prominence', 'type' => 'number']
        ];
        $footer->googleChart($timelineAttrs, $termTimeline, $timelineSeriesProperties);
        break;
      case 'index':
      default:
        $header->attrs['title'] = $header->attrs['subtitle'] = "Terms";
        break;
        // $modelName = static::MODEL_NAME();
        // $terms = $modelName::GetList($this->app);
        // foreach ($terms as $key=>$term) {
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
        // }
        // krsort($terms);
        // $resultView->attrs['terms'] = $terms;
        // break;
    }
    return $resultView->prepend($header)->append($footer);
  }

  public function allow(\SAT\User $user) {
    return True;
  }
}
?>