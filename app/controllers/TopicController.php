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
    $header = $this->app->view('header');
    $footer = $this->app->view('footer');
    $resultView = new \View($this->app, joinPaths(\Config::FS_ROOT, "views", static::MODEL_URL(), $this->app->action.".php"), ['app' => $this->app]);
    switch ($this->app->action) {
      case 'show':
        $object->load('topic');
        $header->attrs['title'] = $header->attrs['subtitle'] = $object->topic->title;

        // sat post graph.
        $startAndEndTimes = $object->db()->table(\ETI\Post::FULL_TABLE_NAME($this->app))
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

        $satTimeline = $object->db()->table(\ETI\Post::FULL_TABLE_NAME($this->app))
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
          'time' => ['title' => 'Time', 'type' => 'datetime'],
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
        } catch (\DbException $e) {
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
            $authorInfo['link'] = $this->link($info['user']->main(), $resultView, 'show', Null, Null, Null, $info['user']->main()->name());
          } else {
            $authorInfo['link'] = $info['user']->name();
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
      case 'secret_santa':
        $header->attrs['title'] = $header->attrs['subtitle'] = "Secret SATna";
        if (isset($_POST['text'])) {
          // parse entrant text.
          $users = [];
          $cantSendTo = [];
          $cantRecieveFrom = [];

          foreach (explode("\n", $_POST['text']) as $line) {
            $lineSplit = explode(":", $line);
            $sender = trim($lineSplit[0]);
            $users[] = $sender;
            if (!isset($cantRecieveFrom[$sender])) {
              $cantRecieveFrom[$sender] = [];
            }
            if (!isset($cantSendTo[$sender])) {
              $cantSendTo[$sender] = [];
            }

            if (count($lineSplit) > 1) {
              // there are restrictions for this user.
              foreach (explode(",", $lineSplit[1]) as $unsendable) {
                $unsendable = trim($unsendable);
                if (!isset($cantRecieveFrom[$unsendable])) {
                  $cantRecieveFrom[$sender] = [$unsendable];
                } else {
                  $cantRecieveFrom[$sender][] = $unsendable;
                }
                if (!isset($cantSendTo[$unsendable])) {
                  $cantSendTo[$unsendable] = [$sender];
                } else {
                  $cantSendTo[$unsendable][] = $sender;
                }
              }
            }
          }

          // construct arrays listing who each user can send to and recieve from.
          $canSendTo = [];
          $canRecieveFrom = [];
          foreach ($users as $recipient) {
            $canSendTo[$recipient] = [];
            foreach ($users as $sender) {
              if ($recipient !== $sender) {
                if (!in_array($sender, $cantSendTo[$recipient])) {
                  $canSendTo[$recipient][] = $sender;
                }
                if (!in_array($recipient, $cantRecieveFrom[$sender])) {
                  $canRecieveFrom[$sender][] = $recipient;
                }
              }
            }
          }

          /*
            match algorithm.
            starting at i=1,
            look for users who can either recieve from or send to i users. (i is the "capability" of the user)
            if there exists a user at this capability, randomly pick a user from the corresponding array to recieve from or send to.
              avoid making pairs of type A->B when B->A already exists
              if there are no possible users, we have to start all over again!
            then add these users as a sender=>recipient pair and remove the sender/recipient from their respective arrays.
            continue, increasing "capabilities" until the number of pairs equals the number of users.
          */
          $originalCanSendTo = $canSendTo;
          $originalCanRecieveFrom = $canRecieveFrom;
          $attempts = 0;
          while (True) {
            $pairs = [];
            $canSendTo = $originalCanSendTo;
            $canRecieveFrom = $originalCanRecieveFrom;
            $brokenMatches = False;
            $capability=1;
            // $this->app->logger->err("users:<pre>".print_r($users, True)."</pre>");
            // $this->app->logger->err("canSendTo:<pre>".print_r($canSendTo, True)."</pre>");

            $numUsers = count($users);

            while (count($pairs) < $numUsers) {
              foreach ($canSendTo as $recipient => $senders) {
                $recipientCapability = count($senders);
                if ($recipientCapability == $capability) {
                  // $this->app->logger->err("Capability for recipient ".$recipient.": ".$recipientCapability);
                  while (True) {
                    // find the senders with the minimum capability and randomly assign one.
                    $minSenderCapability = $numUsers;
                    $potentialSenders = [];

                    foreach ($senders as $potentialSender) {
                      if (isset($canRecieveFrom[$potentialSender])) {
                        $potentialCapability = count($canRecieveFrom[$potentialSender]);
                        $this->app->logger->err("Sender ".$potentialSender." with capability ".$potentialCapability);

                        if ($potentialCapability < $minSenderCapability) {
                          $potentialSenders = [$potentialSender];
                          $minSenderCapability = $potentialCapability;
                        } elseif ($potentialCapability == $minSenderCapability) {
                          $potentialSenders[] = $potentialSender;
                        }
                      }
                    }
                    if (!$potentialSenders) {
                      // $this->app->logger->err("No potential senders for ".$recipient.". Breaking.");
                      $brokenMatches = True;
                      $pairedUsername = Null;
                      break;
                    }

                    $this->app->logger->err("Potential senders for ".$recipient.": ".print_r($potentialSenders, True));

                    $randomIndex = rand(0, count($potentialSenders)-1);
                    $pairedUsername = $potentialSenders[$randomIndex];

                    $this->app->logger->err("Selected sender for ".$recipient.": ".$pairedUsername);

                    // if we have a choice in the matter, avoid pairs of the form A->B if B->A is already established.
                    if (count($potentialSenders) > 1) {
                      if (isset($pairs[$recipient]) && $pairs[$recipient] == $pairedUsername) {
                        continue;
                      }
                    }

                    if (isset($canRecieveFrom[$pairedUsername])) {
                      break;
                    }
                  }

                  $pairs[$pairedUsername] = $recipient;

                  unset($canSendTo[$recipient]);
                  unset($canRecieveFrom[$pairedUsername]);
                }
                // $this->app->logger->err("Pairs: <pre>".print_r($pairs, True)."</pre>");
              }
              // $this->app->logger->err("Pairs after half-cycle: <pre>".print_r($pairs, True)."</pre>");
              // $this->app->logger->err("canSendTo after half-cycle: <pre>".print_r($canSendTo, True)."</pre>");
              // $this->app->logger->err("canRecieveFrom after half-cycle: <pre>".print_r($canRecieveFrom, True)."</pre>");

              foreach ($canRecieveFrom as $sender => $recipients) {
                $senderCapability = count($recipients);
                if ($senderCapability == $capability) {
                  // $this->app->logger->err("Capability for sender ".$sender.": ".$senderCapability);
                  while (True) {
                    // find the recipients with the minimum capability and randomly assign one.
                    $minRecipientCapability = $numUsers;
                    $potentialRecipients = [];

                    foreach ($recipients as $potentialRecipient) {
                      if (isset($canSendTo[$potentialRecipient])) {
                        $potentialCapability = count($canSendTo[$potentialRecipient]);
                        if ($potentialCapability < $minRecipientCapability) {
                          $potentialRecipients = [$potentialRecipient];
                          $minRecipientCapability = $potentialCapability;
                        } elseif ($potentialCapability == $minRecipientCapability) {
                          $potentialRecipients[] = $potentialRecipient;
                        }
                      }
                    }
                    if (!$potentialRecipients) {
                      // $this->app->logger->err("No potential recipients for ".$recipient.". Breaking.");
                      $brokenMatches = True;
                      $pairedUsername = Null;
                      break;
                    }

                    // $this->app->logger->err("Potential senders for ".$sender.": ".print_r($potentialRecipients, True));

                    $randomIndex = rand(0, count($potentialRecipients)-1);
                    $pairedUsername = $potentialRecipients[$randomIndex];

                    // if we have a choice in the matter, avoid pairs of the form A->B if B->A is already established.
                    if (count($potentialRecipients) > 1) {
                      if (isset($pairs[$pairedUsername]) && $pairs[$pairedUsername] == $sender) {
                        continue;
                      }
                    }

                    if (isset($canSendTo[$pairedUsername])) {
                      break;
                    }
                  }

                  $pairs[$sender] = $pairedUsername;

                  unset($canRecieveFrom[$sender]);
                  unset($canSendTo[$pairedUsername]);
                }
              }
              // $this->app->logger->err("Pairs at the end of loop ".$capability.": <pre>".print_r($pairs, True)."</pre>");
              $capability++;
            }

            $attempts++;
            $this->app->logger->err("Broken matches: ".intval($brokenMatches));
            if (!$brokenMatches) {
              break;
            }
          }
          $resultView->attrs['pairs'] = $pairs;
          $resultView->attrs['text'] = $_POST['text'];
        }

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
        $satRows = $this->app->dbs['SAT']->table(\SAT\Topic::$TABLE)
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
                'link' => $this->link($user, $resultView, 'show', Null, Null, Null, $user->main()->name()),
                'count' => (int) $info['count']
              ];
            } else {
              $user = $info['user']->load();
              $authors[$user->id] = [
                'user' => $user,
                'link' => $user->name(),
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