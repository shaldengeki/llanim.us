<?php
require_once "app/include.php";

$newView = new View(Null, [
                          'title' => 'LLAnim.us - Scheduled maintenance'
                          ]);
$newView->css[] = [
  'src' => '
      body {
        font: 13pt tahoma;
        background: url(/img/tomoyo.jpg) no-repeat top center;
        text-align: center;
        margin: 0;
      }
      div.maintenance {
        position: absolute;
        bottom: 10%;
        width: 100%;
      }
      div.maintenance p {
        background: rgba(255, 255, 255, 1);
        text-align: center;
        width: 30%;
        margin: 0 auto;
        padding: 3px;
        border-radius: 15px;
      }
      .navbar, .nav, hr, footer {
        display: none !important;
      }'
];
ob_start();
?>
  </div>
  <div class='maintenance'>
    <p>
      llanim.us is down while we transition to a shiny new server! This should take most of the weekend of the 24th, so please bear with us and check back soon!
    </p>
  </div>
  <div class='container'>
<?php
$newView->html(ob_get_clean());
echo $app->render($newView);
?>