<?php
  require_once($_SERVER['DOCUMENT_ROOT']."/app/include.php");
  \Application::check_partial_include(__FILE__);

  if (isset($this->attrs['sats'])) {
    $numSats = count($this->attrs['sats']);
  } else {
    $this->attrs['sats'] = [];
    $numSats = 0;
  }
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
<?php
  if (isset($sat['terms']) && $sat['terms']) {
?>
          <p class='sat-discussed'>
            Things discussed: <span class='sat-terms'><?php echo implode(", ", array_keys($sat['terms'])); ?></span>
          </p>
<?php
  }
?>
<?php
  if (isset($sat['authors'])) {
    $authors = [];
    foreach ($sat['authors'] as $info) {
      $authors[] = $info['user']->name." (".$info['count'].")";
    }
?>
          <p class='sat-authors'>
            Primary authors: <span class='sat-authors'><?php echo implode(", ", $authors); ?></span>
          </p>
<?php
  }
?>
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