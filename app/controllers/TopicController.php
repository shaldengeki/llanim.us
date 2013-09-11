<?php
namespace SAT;

class TopicController extends \BaseController {
  public $app;

  public static function MODEL_URL() {
    return 'sat';
  }
  public static function MODEL_NAME() {
    return '\\SAT\\Topic';
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
        $object->load('topic');
        $header->attrs['title'] = $header->attrs['subtitle'] = $object->topic->title;

        // sat post graph.
        $startAndEndTimes = $object->db()->table(\ETI\Post::DB_NAME($this->app).'.'.\ETI\Post::$TABLE)
                                  ->fields("MIN(".\ETI\Post::$FIELDS['date']['db'].") AS start_time", "MAX(".\ETI\Post::$FIELDS['date']['db'].") AS end_time")
                                  ->where([
                                          \ETI\Post::$FIELDS['topic_id']['db'] => $object->id
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

        $satTimeline = $object->db()->table(\ETI\Post::DB_NAME($this->app).'.'.\ETI\Post::$TABLE)
                              ->fields("ROUND(".\ETI\Post::$FIELDS['date']['db']."/".intval($groupBySeconds).")*".intval($groupBySeconds)." AS time, COUNT(*) AS count")
                              ->where([
                                      \ETI\Post::$FIELDS['topic_id']['db'] => $object->id
                                      ])
                              ->group('time')
                              ->order('time ASC')
                              ->assoc();
        foreach ($satTimeline as $key=>$row) {
          $dateObject = new \DateTime('@'.$row['time']);
          $dateObject->setTimeZone($this->app->outputTimeZone);
          $satTimeline[$key]['time'] = $dateObject;
        }

        $timelineAttrs = [
          'id' => 'sat-timeline',
          'type' => 'LineChart',
          'height' => 400,
          'width' => '100%',
          'title' => 'Posting timeline',
          'chartArea' => ['width' => '100%', 'height' => '80%'],
          'legend' => ['position' => 'none'],
          'backgroundColor' => '#FFFFFF'
        ];
        $timelineSeriesProperties = [
          'time' => ['title' => 'Time', 'type' => 'date'],
          'count' => ['title' => 'Posts', 'type' => 'number']
        ];
        $footer->googleChart($timelineAttrs, $satTimeline, $timelineSeriesProperties);

        // sat authors, sorted by post counts
        $authors = [];

        // build an array of postcounts for the previous SAT, grouped by main ID.
        try {
          $prevSAT = $object->prev();
          $prevCounts = $prevSAT->postCounts();
          $prevTerms = $prevSAT->getTerms();
        } catch (DbException $e) {
          $prevCounts = [];
          $prevTerms = [];
        }
        // now calculate changes and figure out potential links.
        foreach ($object->postCounts() as $mainID=>$info) {
          $authorInfo = [
            'user' => $info['user'],
            'count' => (int) $info['count'],
            'change' => isset($prevCounts[$mainID]) ? $info['count'] - $prevCounts[$mainID]['count'] : $info['count']
          ];
          if ($authorInfo['user'] instanceof \SAT\User) {
            $authorInfo['link'] = $this->link($info['user'], $resultView, 'show', Null, Null, Null, $info['user']->main()->name);
          } else {
            $authorInfo['link'] = $info['user']->name;
          }
          $authors[$mainID] = $authorInfo;
        }
        array_sort_by_key($authors, 'count');
        $resultView->attrs['authors'] = $authors;

        // sat tf-idfs
        $terms = [];
        foreach ($object->getTerms(50) as $term=>$tfidf) {
          $termObj = new \Term\Term($this->app, $term);
          $terms[$term] = [
            'link' => $this->link($termObj, $resultView, 'show', Null, Null, Null, $term),
            'tfidf' => round($tfidf, 2),
            'change' => round(isset($prevTerms[$term]) ? $tfidf - $prevTerms[$term] : $tfidf, 2)
          ];
        }
        $resultView->attrs['terms'] = $terms;
        break;
      case 'index':
      default:
        $header->attrs['title'] = $header->attrs['subtitle'] = "Seasonal Anime Topics";
        $header->attrs['subsubtitle'] = "(hehe)";

        // get the number of SATs so we can paginate and enumerate them properly.
        $modelName = static::MODEL_NAME();
        $numSATs = $modelName::Count($this->app, [
                                      'completed' => 1
                                     ]);

        $satsPerPage = 50;
        $numPages = intval($numSATs / $satsPerPage) + 1;
        $resultView->attrs['pagination'] = $this->paginate($this->url(\SAT\Topic::Get($this->app), 'index').'?page=', $numPages);

        // now get a paginated list of SATs.
        $offset = ($this->app->page - 1) * $satsPerPage;
        $satRows = $this->app->dbs['llAnimu']->table(\SAT\Topic::$TABLE)
                                              ->where([
                                                      'completed' => 1
                                                      ])
                                              ->offset($offset)
                                              ->limit($satsPerPage)
                                              ->order(\SAT\Topic::$FIELDS['id']['db'].' DESC')
                                              ->assoc();
        $sats = [];
        $currSAT = $numSATs - $offset - 1;
        foreach ($satRows as $satInfo) {
          $sat = new \SAT\Topic($this->app, intval($satInfo[\SAT\Topic::$FIELDS['id']['db']]));
          $sat->set($satInfo)
              ->load('topic');
          $titleWords = explode(" ", $sat->topic->title);
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
              // inherit from previous panel.
              $panelClass = Null;
              break;
          }
          $authors = [];
          foreach ($sat->getPostCounts(5) as $info) {
            if ($info['user'] instanceof \SAT\User) {
              $user = $info['user']->load('user');
              $authors[$user->id] = [
                'user' => $user,
                'link' => $this->link($user, $resultView, 'show', Null, Null, Null, $user->main()->name),
                'count' => (int) $info['count']
              ];
            } else {
              $user = $info['user']->load();
              $authors[$user->id] = [
                'user' => $user,
                'link' => $user->name,
                'count' => (int) $info['count']
              ];
            }
          }
          $terms = [];
          foreach ($sat->getTerms(10) as $term=>$tfidf) {
            $termObj = new \Term\Term($this->app, $term);
            $terms[$term] = [
              'link' => $this->link($termObj, $resultView, 'show', Null, Null, Null, $term),
              'tfidf' => $tfidf
            ];
          }
          $sats[$currSAT] = [
            'sat' => $sat,
            'terms' => $terms,
            'authors' => $authors,
            'link' => $this->link($sat, $resultView, 'show', Null, Null, Null, $sat->topic->title), 
            'panelClass' => $panelClass
          ];
          $currSAT--;
        }
        $resultView->attrs['sats'] = $sats;
        break;
    }
    return $resultView->prepend($header)->append($footer);
  }

  public function allow(\SAT\User $user) {
    return True;
  }
}


?>