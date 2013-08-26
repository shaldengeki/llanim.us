<!DOCTYPE html>
<!--[if lt IE 7]>      <html class="no-js lt-ie9 lt-ie8 lt-ie7"> <![endif]-->
<!--[if IE 7]>         <html class="no-js lt-ie9 lt-ie8"> <![endif]-->
<!--[if IE 8]>         <html class="no-js lt-ie9"> <![endif]-->
<!--[if gt IE 8]><!--> <html class="no-js"> <!--<![endif]-->
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <title>LL Animu Stats: <?php echo $this->escape($this->attrs['title']); ?></title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width">
    <link rel="icon" href="/images/wynaut.png" type="image/png" />
    <link rel="shortcut icon" href="/images/wynaut.png" type="image/png" />
<?php
  foreach ($this->css as $css) {
    if (isset($css['url'])) {
?>
    <link rel="stylesheet" type="text/css" href="<?php echo $css['url']; ?>" />
<?php      
    } elseif (isset($css['src'])) {
?>
    <style>
      <?php echo $css['src']; ?>
    </style>
<?php
    }
  }
  foreach ($this->preJs as $preJs) {
?>
    <script type="text/javascript"<?php echo isset($preJs['url']) ? 'src="'.$preJs['url'].'"' : ""; ?>><?php echo isset($preJs['src']) ? $preJs['src'] : ""; ?></script>

<?php
  }
?>
  </head>
  <body>
    <!--[if lt IE 7]>
        <p class="chromeframe">You are using an <strong>outdated</strong> browser. Please <a href="http://browsehappy.com/">upgrade your browser</a> or <a href="http://www.google.com/chromeframe/?redirect=true">activate Google Chrome Frame</a> to improve your experience.</p>
    <![endif]-->
    <div class="navbar navbar-inverse navbar-fixed-top">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a href="/" class="navbar-brand">LLAnimu</a>
        </div>
        <div class="collapse navbar-collapse">
          <ul class="nav navbar-nav">
            <li><a href="http://blog.llanim.us">Blog</a></li>
            <li class="dropdown">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                SAT
                <b class="caret"></b>
              </a>
              <ul class="dropdown-menu">
                <li><a href="https://animurecs.com">Animurecs</a></li>
                <li><a href="networkGraph.php">Network Graph</a></li>
                <li><a href="postCorrelationStats.php">Post Correlations</a></li>
                <li><a href="postGap.php">Post Gap</a></li>
                <li><a href="satTopAnime.php">Top Anime</a></li>
                <li><a href="animu_stats.php">SAT stats</a></li>
                <li><a href="searchPosts.php">Search posts</a></li>
              </ul>
            </li>
            <li class="dropdown">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                MAL
                <b class="caret"></b>
              </a>
              <ul class="dropdown-menu">
                <li><a href="mal_anime.php">Anime/manga ratings</a></li>
                <li><a href="mal_friends.php">Compatibility</a></li>
                <li><a href="liveAnime.php">Live Viewer Stats</a></li>
                <li><a href="mal_anime_stats.php">LL Club stats</a></li>
              </ul>
            </li>
            <li class="dropdown">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                ETI
                <b class="caret"></b>
              </a>
              <ul class="dropdown-menu">
                <li><a href="llActivityStats.php">Activity stats</a></li>
                <li class="disabled"><a href="#">Link stats</a></li>
                <li><a href="etiNetworkGraph.php">Network Graph</a></li>
                <li><a href="llTrendingTopics.php">Trending topics</a></li>
              </ul>
            </li>
          </ul>
        </div>
      </div>
    </div>
    <div class="container">
      <div style='text-align: center;'>
        <script type='text/javascript'><!--
          google_ad_client = 'pub-0014134617959836';
          /* 468x60, created 2/25/11 */
          google_ad_slot = '7505137736';
          google_ad_width = 468;
          google_ad_height = 60;
          //-->
        </script>
        <script type='text/javascript' src='http://pagead2.googlesyndication.com/pagead/show_ads.js'>
        </script>
      </div>
      <div class='row'>
        <div class='col-md-12'>
          <h1><?php echo $this->escape($this->attrs['subtitle']); ?></h1>
        </div>
      </div>