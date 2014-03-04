<?php
namespace SAT {
  class UserController extends \BaseController {
    public $app;

    public static function MODEL_URL() {
      return 'sater';
    }
    public static function MODEL_NAME() {
      return '\\SAT\\User';
    }

    public function __construct(\Application $app) {
      $this->app = $app;
    }

    public function render($object) {
      $header = $this->app->view('header');
      $footer = $this->app->view('footer');
      $resultView = new \View($this->app, joinPaths(\Config::FS_ROOT, "views", static::MODEL_URL(), $this->app->action.".php"), ['app' => $this->app]);
      switch ($this->app->action) {
        case 'log_in':
          // check to see if this user is signed into ETI.
          $urlParams = http_build_query([
                                        'username' => $object->user->name,
                                        'ip' => $_SERVER['REMOTE_ADDR']
                                        ]);
          try {
            $checkETI = new \Curl('https://boards.endoftheinter.net/scripts/login.php?'.$urlParams);
            $checkETI = (bool) ( $checkETI->ssl()->get() === "1:".$object->user->name );
          } catch (CurlException $e) {
            $this->app->delayedMessage("An error occurred while trying to verify your login. Please try again later!", "error");
            $this->app->redirect();
          }
          if (!$checkETI) {
            $this->app->delayedMessage("You're not currently signed into ETI. Please do so and then try again!");
            $this->app->redirect();
          }
          $object->setSession();
          if ($object->isLoggedIn()) {
            $this->app->delayedMessage("You're now logged in as ".$resultView->escape($object->user->name).".", "success");
          } else {
            $this->app->delayedMessage("An error occurred while signing you in. Please try again!", "error");
          }
          $this->app->redirect();
          break;
        case 'log_out':
          // unset session and redirect.
          $object->unsetSession();
          $this->app->redirect('/');
          break;
        case 'show':
          if (!$object->isMain()) {
            $this->app->redirect('/'.static::MODEL_URL().'/'.$object->main()->id);
          }

          $this->app->addTiming("controller start");
          $header->attrs['title'] = $header->attrs['subtitle'] = $object->user->name;

          if ($object->alts()) {
            $header->attrs['subsubtitle'] = "Alts: ".implode(", ", array_map(function($alt) {
              return $alt->name;
            }, $object->alts()));
          }
          $this->app->addTiming("alts set");

          $satIDs = array_map(function($sat) {
            return $sat->id;
          }, \SAT\Topic::GetList($this->app, [
                                'completed' => 1
                               ]));
          $this->app->addTiming("sat IDs set");

          $userIDs = $object->altIDs();
          $userIDs[] = $object->main()->id;
          $this->app->addTiming("account IDs set");

          // user post timeline.
          $startAndEndTimes = $object->db()->table(\ETI\Post::FULL_TABLE_NAME($this->app))
                                    ->fields("MIN(".\ETI\Post::$FIELDS['date']['db'].") AS start_time", "MAX(".\ETI\Post::$FIELDS['date']['db'].") AS end_time")
                                    ->where([
                                            \ETI\Post::DB_FIELD('user_id') => $userIDs,
                                            \ETI\Post::DB_FIELD('topic_id') => $satIDs
                                            ])
                                    ->firstRow();
          $this->app->addTiming("timeline boundaries fetched");

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

          $userTimeline = $object->db()->table(\ETI\Post::FULL_TABLE_NAME($this->app))
                                  ->fields("ROUND(".\ETI\Post::$FIELDS['date']['db']."/".intval($groupBySeconds).")*".intval($groupBySeconds)." AS time, COUNT(*) AS count")
                                  ->where([
                                          \ETI\Post::DB_FIELD('user_id') => $userIDs,
                                          \ETI\Post::DB_FIELD('topic_id') => $satIDs
                                          ])
                                  ->group('time')
                                  ->order('time ASC')
                                  ->assoc();
          $this->app->addTiming("timeline points fetched");
          foreach ($userTimeline as $key=>$row) {
            $dateObject = new \DateTime('@'.$row['time']);
            $dateObject->setTimeZone($this->app->outputTimeZone);
            $userTimeline[$key]['time'] = $dateObject;
          }
          $this->app->addTiming("timeline points processed");

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
          $this->app->addTiming("timeline chart properties set");

          // hourly post graph.
          $hourlyPosts = $object->db()->table(\ETI\Post::FULL_TABLE_NAME($this->app))
                                          ->fields("HOUR(FROM_UNIXTIME(".\ETI\Post::$FIELDS['date']['db']."+".intval($this->app->timeZoneOffset).")) AS hour, COUNT(*) AS count")
                                          ->where([
                                                  \ETI\Post::DB_FIELD('user_id') => $userIDs,
                                                  \ETI\Post::DB_FIELD('topic_id') => $satIDs
                                                  ])
                                          ->group('hour')
                                          ->order('hour ASC')
                                          ->assoc();
          $this->app->addTiming("hourly points fetched");

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
          $this->app->addTiming("hourly chart properties set");


          // sats, sorted by post counts
          $topics = [];
          $postCounts = $object->db()->table(\ETI\Post::FULL_TABLE_NAME($this->app))
                                      ->fields(\ETI\Post::DB_FIELD('topic_id').' AS topic_id', 'COUNT(*) AS count')
                                      ->where([
                                              \ETI\Post::DB_FIELD('user_id') => $userIDs,
                                              \ETI\Post::DB_FIELD('topic_id') => $satIDs
                                              ])
                                      ->group("topic_id")
                                      ->order("topic_id ASC")
                                      ->assoc('topic_id', 'count');
          $this->app->addTiming("sat postcounts fetched");
          foreach ($postCounts as $topicID => $count) {
            $sat = new \SAT\Topic($this->app, (int) $topicID);
            $sat->load('topic');
            $topics[$topicID] = [
              'topic' => $sat->topic,
              'link' => $this->link($sat, $resultView, 'show', Null, Null, Null, $sat->topic->title),
              'count' => (int) $count
            ];
          }
          $resultView->attrs['topics'] = $topics;
          $this->app->addTiming("sat properties set");

          // $posts = [];
          // $postQuery = $object->db()->table(\ETI\Post::FULL_TABLE_NAME($this->app))
          //                           ->where([
          //                                   \ETI\Post::DB_FIELD('user_id') => $userIDs,
          //                                   \ETI\Post::DB_FIELD('topic_id') => $satIDs
          //                                   ])
          //                           ->order(\ETI\Post::DB_FIELD('id').' DESC')
          //                           ->limit(75)
          //                           ->query();
          // $this->app->addTiming("user posts fetched");
          // while ($post = $postQuery->fetch()) {
          //   $newPost = new \ETI\Post($this->app, (int) $post[\ETI\Post::DB_FIELD('id')]);
          //   $posts[$post[\ETI\Post::DB_FIELD('id')]] = $newPost->set($post);
          // }
          // $resultView->attrs['posts'] = $posts;
          // $this->app->addTiming("user posts set");

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

    public function allow(\SAT\User $user) {
      switch($this->app->action) {
        case 'index':
        case 'show':
          return True;
          break;

        case 'log_in':
          return !$user->isLoggedIn();
          break;

        case 'log_out':
          return $user->isLoggedIn();
          break;
      }

      return True;
    }
  }
}
namespace ETI {
  class UserController extends \SAT\UserController {}
}
?>