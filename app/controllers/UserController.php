<?php
namespace SAT;

class UserController extends \BaseController {
  public $app;

  public static function MODEL_URL() {
    return 'user';
  }
  public static function MODEL_NAME() {
    return '\\SAT\\User';
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
        $header->attrs['title'] = $header->attrs['subtitle'] = $object->user->name;

        $satIDs = array_map(function($sat) {
          return $sat->id;
        }, \SAT\Topic::GetList($this->app, [
                              'completed' => 1
                             ]));

        // user post timeline.
        $startAndEndTimes = $object->db()->table(\ETI\Post::DB_NAME($this->app).'.'.\ETI\Post::$TABLE)
                                  ->fields("MIN(".\ETI\Post::$FIELDS['date']['db'].") AS start_time", "MAX(".\ETI\Post::$FIELDS['date']['db'].") AS end_time")
                                  ->where([
                                          \ETI\Post::$FIELDS['user_id']['db'] => $object->id,
                                          \ETI\Post::$FIELDS['topic_id']['db'] => $satIDs
                                          ])
                                  ->firstRow();
        $startTime = $startAndEndTimes['start_time'];
        $endTime = $startAndEndTimes['end_time'];
        $groupBySeconds = ceil(($endTime - $startTime)/50);
        $dateFormatArray = ['n'];
        if ($groupBySeconds < 2592000) {
          $dateFormatArray[] = 'j';
        }
        if (date('y', $startTime) != date('y', $endTime)) {
          $dateFormatArray[] = 'y';
        }
        $dateFormatString = implode("/",$dateFormatArray);
        
        if ($groupBySeconds < 3600) {
          $groupBySeconds = 3600;
        }

        $userTimeline = $object->db()->table(\ETI\Post::DB_NAME($this->app).'.'.\ETI\Post::$TABLE)
                                ->fields("ROUND(".\ETI\Post::$FIELDS['date']['db']."/".intval($groupBySeconds).")*".intval($groupBySeconds)." AS time, COUNT(*) AS count")
                                ->where([
                                        \ETI\Post::$FIELDS['user_id']['db'] => $object->id,
                                        \ETI\Post::$FIELDS['topic_id']['db'] => $satIDs
                                        ])
                                ->group('time')
                                ->order('time ASC')
                                ->assoc();
        foreach ($userTimeline as $key=>$row) {
          $dateObject = new \DateTime('@'.$row['time']);
          $dateObject->setTimeZone($this->app->outputTimeZone);
          $userTimeline[$key]['time'] = $dateObject;
        }

        $timelineAttrs = [
          'id' => 'user-timeline',
          'type' => 'LineChart',
          'height' => 400,
          'width' => '100%',
          'title' => 'Historical',
          'chartArea' => ['width' => '100%', 'height' => '80%'],
          'legend' => ['position' => 'none'],
          'backgroundColor' => '#FFFFFF'
        ];
        $timelineSeriesProperties = [
          'time' => ['title' => 'Time', 'type' => 'date'],
          'count' => ['title' => 'Posts', 'type' => 'number']
        ];
        $footer->googleChart($timelineAttrs, $userTimeline, $timelineSeriesProperties);

        // hourly post graph.
        $hourlyPosts = $object->db()->table(\ETI\Post::DB_NAME($this->app).'.'.\ETI\Post::$TABLE)
                                        ->fields("HOUR(FROM_UNIXTIME(".\ETI\Post::$FIELDS['date']['db']."+".intval($this->app->timeZoneOffset).")) AS hour, COUNT(*) AS count")
                                        ->where([
                                                \ETI\Post::$FIELDS['user_id']['db'] => $object->id,
                                                \ETI\Post::$FIELDS['topic_id']['db'] => $satIDs
                                                ])
                                        ->group('hour')
                                        ->order('hour ASC')
                                        ->assoc();
        $hourlyAttrs = [
          'id' => 'user-hourly',
          'type' => 'ColumnChart',
          'height' => 400,
          'width' => '100%',
          'title' => 'By hour',
          'chartArea' => ['width' => '100%', 'height' => '80%'],
          'legend' => ['position' => 'none'],
          'backgroundColor' => '#FFFFFF'
        ];
        $hourlySeriesProperties = [
          'hour' => ['title' => 'Hour', 'type' => 'number'],
          'count' => ['title' => 'Posts', 'type' => 'number']
        ];
        $footer->googleChart($hourlyAttrs, $hourlyPosts, $hourlySeriesProperties);


        // sat authors, sorted by post counts
        $topics = [];
        $postCounts = $object->db()->table(\ETI\Post::$TABLE)
                                    ->fields(\ETI\Post::$FIELDS['topic_id']['db'].' AS topic_id', 'COUNT(*) AS count')
                                    ->where([
                                            \ETI\Post::$FIELDS['user_id']['db'] => $object->id,
                                            \ETI\Post::$FIELDS['topic_id']['db'] => $satIDs
                                            ])
                                    ->group("topic_id")
                                    ->order("topic_id ASC")
                                    ->assoc('topic_id', 'count');
        foreach ($postCounts as $topicID => $count) {
          $sat = new \SAT\Topic($this->app, intval($topicID));
          $sat->load('topic');
          $topics[$topicID] = [
            'topic' => $sat->topic,
            'link' => $this->link($sat, $resultView, 'show', Null, Null, Null, $sat->topic->title),
            'count' => intval($count)
          ];
        }
        $resultView->attrs['topics'] = $topics;

        // // sat tf-idfs
        // $terms = [];
        // foreach ($object->getTerms(50) as $term=>$tfidf) {
        //   $terms[$term] = [
        //     'tfidf' => round($tfidf, 2),
        //     'change' => round(isset($prevTerms[$term]) ? $tfidf - $prevTerms[$term] : $tfidf, 2)
        //   ];
        // }
        // $resultView->attrs['terms'] = $terms;
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