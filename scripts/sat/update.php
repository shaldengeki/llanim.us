<?php

/*
  update.php 
    [--new=NEW_TOPIC_ID]
    [--old=OLD_TOPIC_ID [--old=OLD_TOPIC_ID]]
    [--remove=REMOVE_TOPIC_ID [--remove=REMOVE_TOPIC_ID]]
    [--posts]
    [--mal]
    [--mail]
  updates SAT topic / post data.
*/

set_time_limit(120);
require_once("../../app/Application.php");
$app = new \Application();
$app->initScript(Null, [
                        'new' => [
                          'required' => False,
                          'value' => True
                        ],
                        'old' => [
                          'required' => False,
                          'value' => True
                        ],
                        'remove' => [
                          'required' => False,
                          'value' => True
                        ],
                        'posts' => [
                          'value' => False
                        ],
                        'mal' => [
                          'value' => False
                        ],
                        'mail' => [
                          'value' => False
                        ]
                       ]);
$view = new \View($app);

//update with Tomoyo.
echo "Updating SAT stats...\n";

if (isset($app->cliOpts['remove'])) {
  //delete topic(s).
  $app->cliOpts['remove'] = is_array($app->cliOpts['remove']) ? $app->cliOpts['remove'] : [$app->cliOpts['remove']];

  foreach ($app->cliOpts['remove'] as $removeSATID) {
    //delete said topic from topic list.
    $app->dbs['SAT']->table(\SAT\Topic::FULL_TABLE_NAME($app))
                    ->where([
                            \SAT\Topic::DB_FIELD('id') => intval($removeSATID)
                            ])
                    ->limit(1)
                    ->delete();
    echo "Removed topicID ".intval($removeSATID)." from SAT stats.\n";
  }
}
$topicsToUpdate = [];
if (isset($app->cliOpts['old'])) {
  //add this topic / these topics to the database.
  $app->cliOpts['old'] = is_array($app->cliOpts['old']) ? $app->cliOpts['old'] : [$app->cliOpts['old']];
  foreach ($app->cliOpts['old'] as $key => $topicID) {
    //check to see if this topic is already in the db.
    try {
      $topicLength = $app->dbs['SAT']->table(\SAT\Topic::FULL_TABLE_NAME($app))
                                      ->fields("length")
                                      ->where([
                                              \SAT\Topic::DB_FIELD('id') => intval($topicID)
                                              ])
                                      ->firstValue();
      //mark each topic as completed.
      $markCompleted = $app->dbs['SAT']->table(\SAT\Topic::FULL_TABLE_NAME($app))
                                        ->set([
                                              \SAT\Topic::DB_FIELD('completed') => 1
                                              ])
                                        ->where([
                                                \SAT\Topic::DB_FIELD('id') => intval($topicID)
                                                ])
                                        ->limit(1)
                                        ->update();
      if (!$markCompleted) {
        echo "Error updating topic ID ".intval($topicID)." and marking it as completed.\n";
      } else {
        echo "Marked topic ID ".intval($topicID)." as completed.\n";
      }
      $topicsToUpdate[] = [
        'll_topicid' => $topicID,
        'length' => $topicLength
      ];
    } catch (DbException $e) {
      $addTopic = $app->dbs['SAT']->table(\SAT\Topic::FULL_TABLE_NAME($app))
                                  ->set([
                                        \SAT\Topic::DB_FIELD('id') => intval($topicID),
                                        \SAT\Topic::DB_FIELD('completed') => 1
                                        ])
                                  ->insert();
      if (!$addTopic) {
        echo "Error adding topic ID ".intval($topicID)." to the SAT stats.\n";
      } else {
        echo "Added topic ID ".intval($topicID)." to the SAT stats.\n";
      }
      $topicsToUpdate[] = [
        'll_topicid' => $topicID,
        'length' => Null
      ];
    }
    $latestTopicID = intval($topicID);
  }
} else {
  // find the latest topicID in the database.
  $latestTopicID = intval($app->dbs['SAT']->table(\SAT\Topic::FULL_TABLE_NAME($app))
                                          ->fields(\SAT\Topic::DB_FIELD('id'))
                                          ->order(\SAT\Topic::DB_FIELD('id').' DESC')
                                          ->limit(1)
                                          ->firstValue());
}

$priorPostRanks = [];
$priorPostCounts = [];
if (isset($app->cliOpts['new'])) {
  //we're posting in a new topic.    
  //prepare an array of the old post totals and ranks.
  $oldRanksQuery = $app->dbs['SAT']->table(\SAT\User::FULL_TABLE_NAME($app))
                                    ->join(\ETI\User::FULL_TABLE_NAME($app).' ON '.\ETI\User::FULL_DB_FIELD_NAME($app, 'id').'='.\SAT\User::FULL_DB_FIELD_NAME($app, 'id'))
                                    ->join('sat_users_alts ON sat_users_alts.main_id='.\SAT\User::FULL_DB_FIELD_NAME($app, 'id'))
                                    ->join('sat_postcounts ON sat_postcounts.ll_userid=sat_users_alts.alt_id')
                                    ->fields(\SAT\User::FULL_DB_FIELD_NAME($app, 'id'), 'username', 'SUM(sat_postcounts.posts) AS total_posts')
                                    ->group(\SAT\User::FULL_DB_FIELD_NAME($app, 'id'))
                                    ->order('total_posts DESC')
                                    ->query();
  $rank = 1;
  while ($old_poster = $oldRanksQuery->fetch()) {
    $priorPostCounts[$old_poster['username']] = $old_poster['total_posts'];
    $priorPostRanks[$rank] = $old_poster['username'];
    $rank++;
  }
  
  //now add this topic to the list, marking it as not-yet completed.
  $app->dbs['SAT']->table(\SAT\Topic::FULL_TABLE_NAME($app))
                  ->fields(\SAT\Topic::DB_FIELD('id'), \SAT\Topic::DB_FIELD('completed'))
                  ->values([intval($app->cliOpts['new']), 0])
                  ->insert();
}
//get a list of alts and primary userIDs.
$mainUserIDs = $app->dbs['SAT']->table('sat_users_alts')
                                ->assoc('alt_id', 'main_id');
//hit each topic, adding up postcounts for each user.
if (isset($app->cliOpts['posts'])) {
  $postCountsToInsert = [];
  $total_topics = count($topicsToUpdate);
  foreach ($topicsToUpdate as $topic_num => $topic) {
    // check to see if this topic is archived, and fetch the last page's number.
    //see if sat length exists for this sat.
    if ($topic['length'] === Null) {
      //get topic duration and update topic entry.
      $postBounds = $app->dbs['ETI']->table(\ETI\Post::FULL_TABLE_NAME($app))
                                    ->fields('MIN('.\ETI\Post::DB_FIELD('date').') AS start', 'MAX('.\ETI\Post::DB_FIELD('date').') AS end')
                                    ->where([
                                            \ETI\Post::DB_FIELD('topic_id') => intval($topic['ll_topicid'])
                                            ])
                                    ->firstRow();
      $length = intval($postBounds['end']) - intval($postBounds['start']);
      if ($length > 0) {
        $app->dbs['SAT']->table(\SAT\Topic::FULL_TABLE_NAME($app))
                        ->set([
                              \SAT\Topic::DB_FIELD('length') => intval($length)
                              ])
                        ->where([
                                \SAT\Topic::DB_FIELD('id') => intval($topic['ll_topicid'])
                                ])
                        ->limit(1)
                        ->update();
      }
    }
    //now loop through the users who posted in this topic, counting user posts.
    $thisSAT = new \SAT\Topic($app, intval($topic['ll_topicid']));
    $userTopicPosts[$thisSAT->id] = [];
    foreach ($thisSAT->postCounts() as $userID=>$info) {
      if (isset($mainUserIDs[$userID])) {
        $userTopicPosts[$thisSAT->id][$userID] = $info['count'];
      }
    }
    
    //now that we have a complete count, go through the topic's posts and prepare to insert missing entries.
    foreach ($userTopicPosts[$thisSAT->id] as $userID => $count) {
      if ($count > 0) {
        $checkPostCount = $app->dbs['SAT']->table('sat_postcounts')
                                          ->fields('COUNT(*)')
                                          ->where([
                                                  'll_topicid' => intval($thisSAT->id),
                                                  'll_userid' => intval($userID)
                                                  ])
                                          ->count();
        if ($checkPostCount < 1) {
          $postCountsToInsert[] = [intval($thisSAT->id), intval($userID), intval($count)];
        }
      }
    }
    //insert these postcount entries.
    if (count($postCountsToInsert) > 0) {
      $app->dbs['SAT']->table('sat_postcounts')
                      ->fields('ll_topicid', 'll_userid', 'posts');
      call_user_func_array([$app->dbs['SAT'], 'values'], $postCountsToInsert);
      $app->dbs['SAT']->insert();
      $postCountsToInsert = [];
    }
    echo "Finished with topic ".($topic_num+1)." / ".$total_topics.".\n";
  }
}
if (isset($app->cliOpts['mal'])) {
  //get list of users.
  $userList = \SAT\User::GetList($app);

  //for each user, update MAL stats (if necessary).
  foreach ($userList as $user) {
    if ($user->mal_name != '') {
      //process MAL stats for this user.
      //check to make sure the stats for this topic for this user haven't already been submitted.
      $checkStats = $app->dbs['SAT']->table('anime_stats')
                                    ->fields('COUNT(*)')
                                    ->where([
                                            'userid' => intval($user->id),
                                            'topicid' => intval($latestTopicID)
                                            ])
                                    ->limit(1)
                                    ->count();
      if ($checkStats < 1) {
        //get the MAL stats for this user.
        echo "Getting MAL list for userID ".$user->id.".\n";
        $curl = new \Curl("http://myanimelist.net/malappinfo.php?u=".$user->mal_name);
        try {
          $malList = $curl->get();
        } catch (CurlException $e) {
          echo "Failed to retrieve MAL list for userID ".$user->id.": ".$e->getMessage()."\n";
          continue;
        }
        $listXML = new \Dom\Dom();
        $listXML->loadXML('<?xml version="1.0" encoding="utf-8"?>'.$malList);
        $userInfoNode = $listXML->getElementsByTagName("myinfo")->item(0);

        $time = (float) $userInfoNode->getElementsByTagName("user_days_spent_watching")->item(0)->textContent;
        $watching = (int) $userInfoNode->getElementsByTagName("user_watching")->item(0)->textContent;
        $completed = (int) $userInfoNode->getElementsByTagName("user_completed")->item(0)->textContent;
        $onHold = (int) $userInfoNode->getElementsByTagName("user_onhold")->item(0)->textContent;
        $dropped = (int) $userInfoNode->getElementsByTagName("user_dropped")->item(0)->textContent;
        $planToWatch = (int) $userInfoNode->getElementsByTagName("user_plantowatch")->item(0)->textContent;

        //insert stats row.
        $app->dbs['SAT']->table('anime_stats')
                        ->set([
                              'userid' => intval($user->id),
                              'topicid' => intval($latestTopicID),
                              'timestamp' => intval(time()),
                              'time' => $time,
                              'watching' => $watching,
                              'completed' => $completed,
                              'onhold' => $onHold,
                              'dropped' => $dropped,
                              'plantowatch' => $planToWatch
                              ])
                        ->insert();
      }
    }
  }
}
if (isset($app->cliOpts['new'])) {
  //prepare message to post.
  $previousTopicID = $app->dbs['SAT']->table(\SAT\Topic::FULL_TABLE_NAME($app))
                                      ->fields(\SAT\Topic::DB_FIELD('id'))
                                      ->where([
                                              \SAT\Topic::DB_FIELD('id').'<'.$latestTopicID
                                              ])
                                      ->order(\SAT\Topic::DB_FIELD('id')." DESC")
                                      ->limit(1)
                                      ->firstValue();
  $message = "<b>LL Animu Statistics updated!</b>\nhttp://llanim.us/sat\n\n<u>Sample statistics from the last SAT:</u>\n<spoiler caption=\"Rank changes\">\n";
  //prepare a few statistics to post.
  //changes in ranks and MAL stats.
  
  $time_array = [];
  $watching_array = [];
  $completed_array = [];
  $onhold_array = [];
  $dropped_array = [];
  $postRanksQuery = $app->dbs['SAT']->table(\SAT\User::FULL_TABLE_NAME($app))
                                  ->join(\ETI\User::FULL_TABLE_NAME($app)." ON ".\ETI\User::FULL_DB_FIELD_NAME($app, 'id').'='.\SAT\User::FULL_DB_FIELD_NAME($app, 'id'))
                                  ->join('sat_users_alts ON sat_users_alts.main_id='.\SAT\User::FULL_DB_FIELD_NAME($app, 'id'))
                                  ->join('sat_postcounts ON sat_postcounts.ll_userid=sat_users_alts.alt_id')
                                  ->fields(\SAT\User::FULL_DB_FIELD_NAME($app, 'id'), 'username', 'SUM(sat_postcounts.posts) AS total_posts')
                                  ->group(\SAT\User::FULL_DB_FIELD_NAME($app, 'id'))
                                  ->order('total_posts DESC')
                                  ->query();
  $rank = 1;
  $newPostRanks = [];
  while ($postCount = $postRanksQuery->fetch()) {      
    $newPostCounts[$postCount['username']] = $postCount['total_posts'];
    $newPostRanks[$rank] = $postCount['username'];
    $rank++;

    try {
      $latestUserStats = $app->dbs['SAT']->table('anime_stats')
                                          ->where([
                                                  'userid' => intval($postCount['ll_userid']),
                                                  'topicid' => intval($latestTopicID)
                                                  ])
                                          ->firstRow();
      $previousUserStats = $app->dbs['SAT']->table('anime_stats')
                                            ->where([
                                                    'userid' => intval($postCount['ll_userid']),
                                                    'topicid < '.intval($latestTopicID)
                                                    ])
                                            ->order('topicid DESC')
                                            ->limit(1)
                                            ->firstRow();
      if ($latestUserStats['time'] - $previousUserStats['time'] > 0) {
        $time_array[$postCount['username']] = $latestUserStats['time'] - $previousUserStats['time'];
      }
      if ($latestUserStats['watching'] > 0) {
        $watching_array[$postCount['username']] = $latestUserStats['watching'];
      }
      if ($latestUserStats['completed'] - $previousUserStats['completed'] > 0) {
        $completed_array[$postCount['username']] = $latestUserStats['completed'] - $previousUserStats['completed'];
      }
      if ($latestUserStats['onhold'] - $previousUserStats['onhold'] > 0) {
        $onhold_array[$postCount['username']] = $latestUserStats['onhold'] - $previousUserStats['onhold'];
      }
      if ($latestUserStats['dropped'] - $previousUserStats['dropped'] > 0) {
        $dropped_array[$postCount['username']] = $latestUserStats['dropped'] - $previousUserStats['dropped'];
      }
    } catch (DbException $e) {
      // the user lacks either current or previous-topic MAL stats.
      continue;
    }
  }
  arsort($time_array);
  arsort($watching_array);
  arsort($completed_array);
  arsort($onhold_array);
  arsort($dropped_array);

  foreach ($newPostRanks as $rank=>$username) {
    if ($priorPostRanks[$rank] != $username) {
      if (array_search($username, $priorPostRanks) < $rank) {
        $direction = "sank down";
      } else {
        $direction = "surged past ".$priorPostRanks[$rank];
      }
      $message .= $username." ".$direction." from rank ".array_search($username, $priorPostRanks)." to rank ".$rank.", posting ".($newPostCounts[$username] - $priorPostCounts[$username])." times in the previous SAT.\n";
    }
  }

  $diff_postcounts = [];
  //max postcount, max increase, max decrease.
  foreach ($newPostCounts as $username=>$postcount) {
    $diff_postcounts[$username] = $postcount - $priorPostCounts[$username];
  }
  arsort($diff_postcounts);
  foreach ($diff_postcounts as $username=>$postcount) {
    $message .= $username." posted the most times in the last SAT, adding <b>".$postcount."</b> posts.\n";
    break;
  }
  foreach ($diff_postcounts as $username=>$postcount) {
    if ($postcount > 1000) {
      $message .= $username." did the impossible and saw the invisible, posting more than <b>1000</b> times in the last SAT.\n";
    } elseif ($postcount > 900) {
      $message .= $username." is one light orb away from a good end, posting more than <b>900</b> times in the last SAT.\n";
    } elseif ($postcount > 800) {
      $message .= $username." seems to be stuck in an endless recursion of posting, posting more than <b>800</b> times in the last SAT.\n";
    } elseif ($postcount > 700) {
      $message .= $username." was so busy in the SATs he accidentally left lobster in the fridge, posting more than <b>700</b> times in the last SAT.\n";
    } elseif ($postcount > 600) {
      $message .= $username." has lost it and now he can kill, posting more than <b>600</b> times in the last SAT.\n";
    } elseif ($postcount > 500) {
      $message .= $username." probably lost his job and failed his classes, posting more than <b>500</b> times in the last SAT.\n";
    } elseif ($postcount > 400) {
      $message .= $username." is a firm believer in 2D > 3D, posting more than <b>400</b> times in the last SAT.\n";
    }
  }
  
  //MAL anime stat differences from last topic.
  $message .= "</spoiler>\n\n<spoiler caption=\"MAL Activity Stats\">\n";
  $mal_activity_string = "";
  reset($time_array);
  reset($watching_array);
  reset($completed_array);
  reset($onhold_array);
  reset($dropped_array);
  
  if (count($time_array) > 0) {
    $mal_activity_string .= "<b>Time wasted watching anime since last update:</b>\n";
    foreach ($time_array as $username=>$value) {
      $mal_activity_string .= $username." wasted ".round($value*24*60)." minutes of his life.\n";
    }
    $mal_activity_string .= "\n";
  }
  if (count($watching_array) > 0) {
    $watching_user1 = key($watching_array);
    $watching_value1 = current($watching_array);
    if ($watching_value1 > 50) {
      $watching_first_adjective = "has gone on an anime-watching frenzy, with a massive";
    } elseif ($watching_value1 > 25) {
      $watching_first_adjective = "may as well be watching the entire season, piling";        
    } elseif ($watching_value1 > 12) {
      $watching_first_adjective = "is a big fan of the shotgun approach when it comes to anime, targeting";     
    } elseif ($watching_value1 > 6) {
      $watching_first_adjective = "is stimulating the SAT out of an anime-watching recession, managing";
    } else {
      $watching_first_adjective = "had an especially bad season, tentatively penciling in";       
    }
  
    $mal_activity_string .= $watching_user1." ".$watching_first_adjective." ".$watching_value1." series on his watching list.\n";
    if (count($watching_array) > 1) {
      next($watching_array);
      if (current($watching_array) / $watching_value1 > .9) {
        $watching_second_adjective = "must have groupwatched everything with him, with";
      } elseif (current($watching_array) / $watching_value1 > .75) {
        $watching_second_adjective = "barely kept up with the seasonal anime, with";
      } elseif (current($watching_array) / $watching_value1 > .5) {
        $watching_second_adjective = "is winding down his anime-watching career, with";
      } else {
        $watching_second_adjective = "got a job and aced his classes, with";
      }
      $mal_activity_string .= key($watching_array)." ".$watching_second_adjective." ".current($watching_array)." series on his plate.\n";       
    }
    $mal_activity_string .= "\n";
  }
  if (count($completed_array) > 0) {
    foreach ($completed_array as $username=>$difference) {
      $mal_activity_string .= $username." finished ".$difference." series since the last topic.\n";     
    }
    $mal_activity_string .= "\n";
  }
  if (count($onhold_array) > 0) {
    foreach ($onhold_array as $username=>$difference) {
      $mal_activity_string .= $username." put ".$difference." series on hold since the last topic.\n";      
    }
    $mal_activity_string .= "\n";
  }
  if (count($dropped_array) > 0) {
    foreach ($dropped_array as $username=>$difference) {
      $mal_activity_string .= $username." dropped ".$difference." series since the last topic.\n";      
    }
  }
  if ($mal_activity_string != "") {
    $message .= $mal_activity_string;
  }
  $message .= "</spoiler>\n";

  //now get some reader mail!
  $pmArray = [];
  $etiConn = new \ETI\Connection($app, \Config::ETI_USERNAME, \Config::ETI_PASSWORD);
  if (isset($app->cliOpts['mail'])) {
    //get each PM off this list, and if unread process it.
    foreach ($etiConn->pmThreads() as $thread) {
      if (!$thread->read) {
        // exclude spoilers since we can't nest them.
        $message = $thread->messages[0]->exclude('Spoiler')->render();

        $pmArray[] = [
                      'username' => $thread->other_user->name(), 
                      'subject' => $thread->subject,
                      'message' => $message
                      ];
      }
    }
    if ($pmArray) {
      $message .= "\n<spoiler caption=\"Fan Mail! (WARNING: SPOILERS FOR EVERYTHING)\">\n";
      foreach ($pmArray as $pmInfo) {
        $message .= "<b>".$view->escape($pmInfo['username'])."</b> writes in with the title <u>".$view->escape($pmInfo['subject'])."</u>:\n".$pmInfo['message']."\n\n";
      }
      $message .= "\nTo write in, simply send me a PM! Spoiler tags are unfortunately not-supported, but quotes and images should work fine!\n</spoiler>\n";
    }
  }
  $message .= "\nThat's it! Please let me know if you see any errors.\n---\n";

  $messageChunks = str_split($message, 10240);
  $postResults = [];
  foreach ($messageChunks as $chunk) {
    //get posting key.
    $postKey = get_enclosed_string(
                                  $etiConn->get("https://boards.endoftheinter.net/showmessages.php?board=42&topic=".intval($app->cliOpts['new'])),
                                  '<input type="hidden" name="h" value="',
                                  '" />'
                                  );
    $postFields = [
      'topic' => $app->cliOpts['new'],
      'h' => $postKey,
      'message' => $chunk,
      '-ajaxCounter' => 2
    ];

    //post in the new topic.
    $postITT = $etiConn->post("https://boards.endoftheinter.net/async-post.php", $postFields);
    echo "Post fields: ".print_r($postFields, True)."\n";
    echo "Post result: ".print_r($postITT, True)."\n";
    $postResults[] = $postITT;
    sleep(31);
  }

  if (!in_array(False, $postResults)) {
    echo "Posted in topicid ".intval($app->cliOpts['new']).".\n";
  } else {
    echo "Failures were encountered while posting in topicid ".intval($app->cliOpts['new']).".\n";
  }
}
?>