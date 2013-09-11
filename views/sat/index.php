<?php
  require_once($_SERVER['DOCUMENT_ROOT']."/app/include.php");
  \Application::check_partial_include(__FILE__);

  if (isset($this->attrs['sats'])) {
    $numSats = count($this->attrs['sats']);
  } else {
    $this->attrs['sats'] = [];
    $numSats = 0;
  }
  $this->attrs['pagination'] = $this->getDefaultAttr('pagination', '');

  echo $this->attrs['pagination'];
?>
<ol class="media-list sat-list">
<?php
  $lastClass = current($this->attrs['sats'])['panelClass'];
  foreach ($this->attrs['sats'] as $num=>$sat) {
    $satDuration = new \DateIntervalFormat('PT'.$sat['sat']->length.'S');
?>
  <li class="media">
    <div class="pull-left">
      <h1><?php echo $num+1; ?></h1>
    </div>
    <div class="media-body">
      <div class="panel panel-primary sat-<?php echo $sat['panelClass'] === Null ? $lastClass : $sat['panelClass']; ?>">
        <div class="panel-heading">
          <h3 class="panel-title"><?php echo $sat['link']; ?></h3>
          <span class='sat-length pull-right'>
            <?php echo $satDuration->formatShort(); ?>
          </span>
        </div>
        <div class="panel-body">
          <div class='row'>
<?php
  if (isset($sat['terms']) && $sat['terms']) {
?>
            <div class='sat-terms col-md-6'>
              <h4>Things discussed:</h4>
              <ol>
<?php
    foreach ($sat['terms'] as $term=>$info) {
?>
                <li><?php echo $info['link']; ?></li>
<?php
    }
?>
              </ol>
            </div>
<?php
  }
?>
<?php
  if (isset($sat['authors'])) {
?>
            <div class='sat-authors col-md-6'>
              <h4>Primary authors:</h4>
              <ol>
<?php
    foreach ($sat['authors'] as $author) {
?>
                <li><?php echo $author['link']; ?> (<?php echo $author['count']; ?>)</li>
<?php
    }
?>
              </ol>
            </div>
<?php
  }
?>
          </div>
        </div>
      </div>
    </div>
  </li>
<?php
    if ($sat['panelClass'] !== Null) {
      $lastClass = $sat['panelClass'];
    }
  }
?>
</ol>
<?php
  echo $this->attrs['pagination'];
?>