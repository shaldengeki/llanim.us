<?php

class EmptyDependency {
  /*
    Empty dependency class that returns False on any accessor.
  */
  public function __isset($name) {
    return False;
  }
  public function __set($name, $value) {
    return;
  }
  public function __unset($name) {
    return False;
  }
  public function __get($name) {
    return False;
  }
  public function __call($name, $args) {
    return False;
  }
  public static function __callStatic($name, $args) {
    return False;
  }
}

class AppException extends Exception {
  private $app, $messages;
  public function __construct($app, $messages=Null, $code=0, Exception $previous=Null) {
    if (is_array($messages)) {
      $this->messages = $messages;
    } else {
      $this->messages = [$messages];
    }
    parent::__construct($this->formatMessages(), $code, $previous);
    $this->app = $app;
  }
  public function messages() {
    return $this->messages;
  }
  public function formatMessages($separator="<br />\n") {
    // displays a list of this exception's messages.
    if (count($this->messages) > 0) {
      return implode($separator, $this->messages);
    } else {
      return "";
    }
  }
  public function listMessages() {
    // returns an unordered HTML list of this exception's messages.
    if (count($this->messages) > 0) {
      return "<ul><li>".implode("</li><li>", $this->messages)."</li></ul>";
    } else {
      return "";
    }
  }
  public function __toString() {
    return get_class($this).":\n".$this->getFile().":".$this->getLine()."\nMessages: ".$this->formatMessages()."\nStack trace:\n".$this->getTraceAsString()."\n";
  }
  public function display() {
    // displays end user-friendly output explaining the exception that occurred.
    echo "A server error occurred, and I wasn't able to complete your request. I've let the staff know something's wrong - apologies for the problems!";
  }
}

function ErrorHandler($errno, $errstr, $errfile, $errline, array $errcontext) {
  // error was suppressed with the @-operator
  if (error_reporting() === 0) {
    return false;
  }
  throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

class Application {
  /*
    Class that serves to store all the application-relevant parameters
    E.g. this request's model/action/id/format
    And configuration parameters
    Also serves as DI container (stores database, logger, recommendation engine objects)
  */
  private $_classes=[],$_controllers=[],$_observers,$messages=[],$timings=[],$_statsdConn;
  // protected $totalPoints=Null;
  // public $achievements=[];
  public $logger, $cache, $dbs, $mailer, $serverTimeZone, $outputTimeZone, $timeZoneOffset, $user, $target, $startRender, $view, $csrfToken=Null;
  public $cliOpts=[];

  public $model,$action,$status,$format,$class="";
  public $id=0;
  public $ajax=False;
  public $page=1;
  public $csrfField="csrf_token";

  private function _generateCSRFToken() {
    // generates a CSRF token, if one has not already been created for this Application object.
    if ($this->csrfToken === Null) {
      $this->csrfToken = hash('sha256', 'csrf-token:'.session_id());
    }
    return $this->csrfToken;
  }
  private function _loadDependency($absPath) {
    // requires an application dependency from its absolute path
    // e.g. /ABSOLUTE_PATH/app/Config.php
    if (file_exists($absPath)) {
      include_once($absPath);
    } else {
      throw new AppException($this, 'required library not found at '.$absPath);
    }
  }
  private function _connectStatsD() {
    if ($this->statsd == Null) {
      $this->_statsdConn = new \Domnikl\Statsd\Connection\Socket('localhost', 8125);
      $this->statsd = new \Domnikl\Statsd\Client($this->_statsdConn, "shaldengeki.animurecs");
    }
    return $this->statsd;
  }
  private function _connectLogger() {
    if (Config::DEBUG_ON) {
      return log::factory('file', Config::LOG_FILE, 'Animurecs', [], PEAR_LOG_DEBUG);
    } else {
      return log::factory('file', Config::LOG_FILE, 'Animurecs', [], PEAR_LOG_WARNING);
    }
  }
  private function _connectCache() {
    if ($this->cache === Null) {
      $this->cache = new Cache();
    }
    return $this->cache;
  }
  private function _connectDB() {
    if ($this->dbs === Null) {
      $this->dbs = [];
      foreach (Config::$DB_NAMES as $accessor=>$name) {
        $this->dbs[$accessor] = new DbConn(Config::DB_HOST, Config::DB_PORT, Config::DB_USERNAME, Config::DB_PASSWORD, $name);
      }
    }
    return $this->dbs;
  }
  private function _connectMailer() {
    if ($this->mailer === Null) {
      // Create the Transport
      $transporter = Swift_SmtpTransport::newInstance(Config::SMTP_HOST, Config::SMTP_PORT, 'ssl')
        ->setUsername(Config::SMTP_USERNAME)
        ->setPassword(Config::SMTP_PASSWORD);
      $this->mailer = Swift_Mailer::newInstance($transporter);
    }
    return $this->mailer;
  }
  public function loadDependencies() {
    // Loads configuration and all application objects from library files.
    // Connects database, logger, recommendation engine.
    // Creates a list of objects that can be accessed via URL.
    require_once(dirname(__DIR__)."/app/Config.php");
    date_default_timezone_set(Config::SERVER_TZ);
    $this->serverTimeZone = new DateTimeZone(Config::SERVER_TZ);
    $this->outputTimeZone = new DateTimeZone(Config::OUTPUT_TZ);

    // get offset between server and output timezones in seconds.
    $nowServer = new \DateTime("now", $this->serverTimeZone);
    $nowOutput = new \DateTime("now", $this->outputTimeZone);
    $this->timeZoneOffset = $this->outputTimeZone->getOffset($nowOutput) - $this->serverTimeZone->getOffset($nowServer);

    require_once(Config::FS_ROOT.'/vendor/autoload.php');
    require_once('Log.php');
    $this->logger = $this->_connectLogger();

    try {
      $preloads = [
        'traits',
        'core', 
        'models'
      ];
      foreach ($preloads as $preload) {
        // include all traits before models that depend on them.
        foreach (glob(Config::FS_ROOT."/app/".$preload."/*.php") as $filename) {
          $this->_loadDependency($filename);
        }
      }
      $nonLinkedClasses = count(get_declared_classes());

      $this->dbs = $this->_connectDB();

      // models.
      foreach (glob(joinPaths(Config::FS_ROOT, "app", "models", "*"), GLOB_ONLYDIR) as $subdirectory) {
        $this->_loadDependency(joinPaths($subdirectory, "include.php"));
      }
      
      // controllers.
      foreach (glob(joinPaths(Config::FS_ROOT, "app", "controllers", "*.php")) as $controller) {
        $this->_loadDependency($controller);
      }
    } catch (AppException $e) {
      $this->logger->alert($e);
      $this->display_error(500);
    }

    session_set_cookie_params(0, '/', '.llanim.us', False, True);
    session_start();

    // _controllers is a modelUrl:controller mapping for controllers.
    foreach (get_declared_classes() as $className) {
      $interfaces = class_implements($className);
      if (isset($interfaces['Controller'])) {
        $class = new ReflectionClass($className);
        if (!$class->isAbstract()) {
          $this->_controllers[$className::MODEL_URL()] = new $className($this);
        }
      }
    }

    // // include all achievements.
    // // achievements is an ID:object mapping of achievements.
    // $nonAchievementClasses = count(get_declared_classes());
    // try {
    //   foreach (glob(Config::FS_ROOT."/app/achievements/*.php") as $filename) {
    //     $this->_loadDependency($filename);
    //   }
    // } catch (AppException $e) {
    //   $this->logger->alert($e);
    //   $this->display_error(500);
    // }
    // $achievementSlice = array_slice(get_declared_classes(), $nonAchievementClasses);
    // foreach ($achievementSlice as $achievementName) {
    //   $blankAchieve = new $achievementName($this);
    //   $this->achievements[$blankAchieve->id] = $blankAchieve;
    // }
    // ksort($this->achievements);
  }
  public function bindEvents() {
    // binds all event observers.

    // // clear cache for a database object every time it's updated or deleted.
    // $this->bind(['Base.afterUpdate', 'Base.afterDelete'], new Observer(function($event, $parent, $updateParams) {
    //   $parentClass = get_class($parent);
    //   $parent->app->cache->delete($parentClass::MODEL_NAME()."-".intval($parent->id));
    // }));

    // // bind each achievement to its events.
    // foreach ($this->achievements as $achievement) {
    //   foreach ($achievement->events() as $event) {
    //     $this->bind($event, $achievement);
    //   }
    // }
  }

  public function checkCSRF() {
    // compare the POSTed CSRF token to this app's CSRF token.
    // if either is not set, returns False.
    if (!isset($this->csrfField) || !isset($this->csrfToken) || $this->csrfField === Null || $this->csrfToken === Null) {
      return False;
    }
    if (empty($_POST[$this->csrfField]) && isset($_REQUEST[$this->csrfField])) {
      $_POST[$this->csrfField] = $_REQUEST[$this->csrfField];
    }
    if (empty($_POST[$this->csrfField]) || $_POST[$this->csrfField] != $this->csrfToken) {
      return False;
    }
    return True;
  }
  // bind/unbind/fire event handlers for objects.
  // event names are of the form modelName.eventName
  // e.g. User.afterCreate
  public function bind($events, $observer) {
    // binds a function to an event or events.
    // can be either anonymous function or string name of class method.
    if (!method_exists($observer, 'update')) {
      if (Config::DEBUG_ON) {
        throw new AppException($this, sprintf('Invalid observer: %s.', print_r($observer, True)));
      } else {
        return False;
      }
    }
    if (!is_array($events)) {
      $events = [$events];
    }
    foreach ($events as $event) {
      if (!isset($this->_observers[$event])) {
        $this->_observers[$event] = [$observer];
      } else {
        //check if this observer is bound to this event already
        $elements = array_keys($this->_observers[$event], $o);
        $notinarray = True;
        foreach ($elements as $value) {
          if ($observer === $this->_observers[$event][$value]) {
              $notinarray = False;
              break;
          }
        }
        if ($notinarray) {
          $this->_observers[$event][] = $observer;
        }
      }
    }
    return [$event, count($this->_observers[$event])-1];
  }
  public function unbind($observer) {
    // callback is array of form [event_name, position]
    // alternatively, also accepts a string for event name.
    if (is_array($observer)) {
      if (count($observer) < 2) {
        return False;
      } elseif (!isset($this->_observers[$observer[0]])) {
        return True;
      }
      unset($this->_observers[$observer[0]][$observer[1]]);
      return !isset($this->_observers[$observer[0]][$observer[1]]);
    } else {
      if (!isset($this->_observers[$observer])) {
        return True;
      }
      unset($this->_observers[$observer]);
      return !isset($this->_observers[$observer]);
    }
  }
  public function fire($event, $object, $updateParams=Null) {
    if (!isset($this->_observers[$event])) {
      return;
    }
    foreach ($this->_observers[$event] as $observer) {
      if (!method_exists($observer, 'update')) {
        continue;
      }
      $observer->update($event, $object, $updateParams);
    }
  }

  public function display_error($code) {
    http_response_code( (int) $code );
    $view = $this->view('header');
    echo $view->append($this->view( (int) $code ))
              ->append($this->view('footer'))
              ->render();
    exit;
  }
  public function display_exception($e) {
    // formats a (subclassed) instance of AppException for display to the end user.
    $view = $this->view('header');
    echo $view->append($this->view('exception', ['exception' => $e]))
              ->append($this->view('footer'))
              ->render();
    exit;
  }
  public function check_partial_include($filename) {
    // displays the standard 404 page if the user is requesting a partial directly.
    if (str_replace("\\", "/", $filename) === $_SERVER['SCRIPT_FILENAME']) {
      $this->display_error(404);
    }
  }

  public function delayedMessage($message, $class=Null) {
    // appends message to delayed message queue.
    if (!isset($_SESSION['delayedMessages'])) {
      $_SESSION['delayedMessages'] = [];
    }
    if (!is_array($message)) {
      $message = ['text' => $message];
    }
    if ($class) {
      $message['class'] = $class;
    }
    $_SESSION['delayedMessages'][] = $message;
  }
  public function delayedMessages() {
    // returns delayed message queue.
    if (!isset($_SESSION['delayedMessages'])) {
      $_SESSION['delayedMessages'] = [];
    }
    return $_SESSION['delayedMessages'];    
  }
  public function clearDelayedMessages() {
    // empties delayed message queue.
    $_SESSION['delayedMessages'] = [];
    return True;
  }
  public function message($message) {
    // appends message to message queue.
    if (!is_array($message)) {
      $message = ['text' => $message];
    }
    if ($class) {
      $message['class'] = $class;
    }
    $this->messages[] = $message;
  }
  public function messages() {
    // returns message queue.
    return $this->messages;
  }
  public function clearMessages() {
    // clears message queue.
    $this->messages = [];
    return True;
  }
  public function allMessages() {
    // returns delayed and immediate message queues.
    return array_merge($this->delayedMessages(), $this->messages());
  }
  public function clearAllMessages() {
    // clears delayed and immediate message queues.
    return $this->clearMessages() && $this->clearDelayedMessages();
  }

  public function currentUrl() {
    return $_SERVER['REQUEST_URI'];
  }
  public function previousUrl() {
    if (!isset($_SESSION['prev_url'])) {
      $_SESSION['prev_url'] = '/';
    }
    return $_SESSION['prev_url'];
  }
  public function setPreviousUrl($url=Null) {
    $_SESSION['prev_url'] = $url === Null ? $this->currentUrl() : $url;
  }

  public function redirect($location=Null) {
    if ($location === Null) {
      $location = $this->previousUrl();
    }
    header("Location: ".$location);
    exit;
  }
  public function jsRedirect($location) {
    echo "window.location.replace(\"".Config::ROOT_URL."/".$location."\");";
  }

  public function clearOutput() {
    // clears all content in the output buffer(s).
    while (ob_get_level()) {
      ob_end_clean();
    }
  }

  public function initScript($shortOpts=Null, $longOpts=Null) {
    // start of application logic for scripts.
    // loads dependencies and parses arguments.

    $this->startRender = microtime(true);
    set_error_handler('ErrorHandler', E_ALL & ~E_NOTICE);
    mb_internal_encoding('UTF-8');

    $this->loadDependencies();

    if ($shortOpts || $longOpts) {
      $shortOpts = $shortOpts ? $shortOpts : "";
      $longOpts = $longOpts ? $longOpts : [];

      $formattedLongOpts = [];
      foreach ($longOpts as $opt=>$settings) {
        if ($settings['value'] === False) {
          $formattedLongOpts[] = $opt;
        } else {
          if ($settings['required'] === False) {
            $formattedLongOpts[] = $opt.'::';
          } else {
            $formattedLongOpts[] = $opt.':';
          }
        }
      }

      $this->cliOpts = getopt($shortOpts, $formattedLongOpts);
      if ($this->cliOpts === False) {
        echo "Invalid arguments provided:\n".print_r($shortOpts, True).print_r($longOpts, True)."\n";
        exit;
      }
    }
  }

  public function init() {
    // start of application logic.
    // loads dependencies, binds events, sets request variables, then attempts to render the current request.

    $this->startRender = microtime(true);
    set_error_handler('ErrorHandler', E_ALL & ~E_NOTICE);
    mb_internal_encoding('UTF-8');

    $this->loadDependencies();

    $this->bindEvents();

    if (isset($_SESSION['id']) && is_numeric($_SESSION['id'])) {
      $this->user = new \SAT\User($this->dbs['ETI'], (int) $_SESSION['id']);
      // if user has not recently been active, update their last-active.
      // if (!$this->user->isCurrentlyActive()) {
      //   $this->user->updateLastActive();
      // }
    } else {
      // check to see if this user has a last-ip in our user table.
      try {
        // check to see if this user is in the SAT.
        $this->user = \SAT\User::Get($this, [
                                    \SAT\User::$FIELDS['last_ip']['db'] => $_SERVER['REMOTE_ADDR']
                                  ]);
        $this->user->setSession();
      } catch (DbException $e) {
        // no matching last-ip entry. user is a guest.
        $this->user = new \SAT\User($this, 0);
      }
    }

    // check to see if this request is being made via ajax.
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && trim(strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])) == 'xmlhttprequest') {
      $this->ajax = True;
    }

    // protect against CSRF attacks.
    // only generate CSRF token if the user is logged in.
    if ($this->user->id !== 0) {
      $this->csrfToken = $this->_generateCSRFToken();
      // if request came in through AJAX, or there isn't a POST, don't run CSRF filter.
      if (!$this->ajax && !empty($_POST) && !$this->checkCSRF()) {
        $this->display_error(403);
      }
    }

    // set model, action, ID, status, class from request parameters.
    if (isset($_REQUEST['status'])) {
      $this->status = $_REQUEST['status'];
    }
    if (isset($_REQUEST['class'])) {
      $this->class = $_REQUEST['class'];
    }

    if (isset($_REQUEST['controller']) && isset($this->_controllers[$_REQUEST['controller']])) {
      $this->controller = $this->_controllers[$_REQUEST['controller']];
    } elseif (!isset($_REQUEST['controller']) || $_REQUEST['controller'] === "") {
      $this->controller = $this->_controllers['mal'];
    } else {
      $this->delayedMessage("This thing doesn't exist!", "danger");
      $this->logger->err("Controller doesn't exist: ".$_REQUEST['controller']."\n".print_r(array_keys($this->_controllers), True));
      $this->redirect("/");
    }
    $controllerName = get_class($this->controller);
    $this->model = $controllerName::MODEL_NAME();

    $this->view = $this->view();

    if (isset($_REQUEST['id'])) {
      if (is_numeric($_REQUEST['id'])) {
        $this->id = (int) $_REQUEST['id'];
      } else {
        $this->id = $_REQUEST['id'];
      }
    }
    if (!isset($_REQUEST['action']) || $_REQUEST['action'] == '') {
      if ($this->id) {
        $this->action = 'show';
      } else {
        $this->action = 'index';
      }
    } else {
      $this->action = $_REQUEST['action'];
    }
    if (!isset($_REQUEST['page'])) {
      $this->page = 1;
    } else {
      $this->page = max(1, (int) $_REQUEST['page']);
    }
    if (!isset($_REQUEST['format']) || $_REQUEST['format'] === "") {
      $this->format = 'html';
    } else {
      $this->format = $this->view->escape($_REQUEST['format']);
    }

    try {
      // kludge to allow model names in URLs.
      if (($this->model === "User" || $this->model === "Thread") && $this->id !== "") {
        $this->target = new $this->model($this, Null, rawurldecode($this->id));
      } else {
        $this->target = new $this->model($this, $this->id);
      }
    } catch (DbException $e) {
      $this->display_error(404);
    }
    if ($this->target->id === 0 && $this->action === "edit") {
      $this->action = "new";
    }
    if (!$this->controller->allow($this->user)) {
      $targetClass = get_class($this->target);
      $error = new AppException($this, $this->user->name." attempted to ".$this->action." ".$targetClass." ID#".$this->target->id);
      $this->logger->warning($error->__toString());
      $this->display_error(403);
    } else {
      header('X-Frame-Options: SAMEORIGIN');
      try {
        ob_start();
          echo $this->controller->render($this->target)->render();
        echo ob_get_clean();
        $this->setPreviousUrl();
        exit;
      } catch (AppException $e) {
        $this->logger->err($e->__toString());
        $this->clearOutput();
        $this->display_exception($e);
      } catch (Exception $e) {
        $this->logger->err($e->__toString());
        $this->clearOutput();
        $this->display_error(500);
      }
    }
  }

  public function view($view="index", $params=Null) {
    // includes a provided application-level view.
    $file = joinPaths(Config::FS_ROOT, 'views', 'application', "$view.php");
    return new View($this, $file, $params);
  }
  public function render(\View $view, $params=Null) {
    // renders the given HTML text surrounded by the standard application header and footer.
    // passes $params into the header and footer views.
    $appVars = get_object_vars($this);
    if ($params !== Null && is_array($params)) {
      foreach ($params as $key=>$value) {
        $appVars[$key] = $value;
      }
    }
    $completeView = $view->copyAttrsTo($this->view('header', $appVars));
    $completeView->append($view)
                  ->append($view->copyAttrsTo($this->view('footer', $appVars)));
    return $completeView->render();
  }
  public function addTiming($name) {
    $this->timings[] = ['name' => $name, 'time' => microtime(true)];
  }
  public function timingInfo($separator='\\n') {
    $outputLines = [];
    if (!$this->timings) {
      return "";
    }
    $startTime = $prevTime = $this->timings[0]['time'];
    foreach ($this->timings as $timing) {
      $outputLines[] = ($timing['time'] - $prevTime)." | ".$timing['name'];
      $prevTime = $timing['time'];
    }
    $outputLines[] = "Total time: ".($prevTime - $startTime);
    return $outputLines;
  }
}

?>