<?php
  require_once($_SERVER['DOCUMENT_ROOT']."/app/include.php");
  \Application::check_partial_include(__FILE__);

  $this->attrs['authors'] = $this->attrs['authors'] ? $this->attrs['authors'] : [];
  $this->attrs['terms'] = $this->attrs['terms'] ? $this->attrs['terms'] : [];
?>
<div class='row'>
  <div class='col-md-12 sat-graph' style='height: 400px; border: 1px solid black; text-align: center; padding-top: 180px;'>
    Graph goes here.
  </div>
</div>
<div class='row'>
  <div class='col-md-4 sat-authors'>
    <h2>Authors:</h2>
    <ol>
<?php
  foreach ($this->attrs['authors'] as $author) {
?>
      <li><?php echo $author['link']; ?>: <?php echo $author['count']; ?></li>
<?php
  }
?>
    </ol>
  </div>
  <div class='col-md-4 sat-terms'>
    <h2>Topics:</h2>
    <ol>
<?php
  foreach ($this->attrs['terms'] as $term => $tfidf) {
?>
      <li><?php echo $term; ?>: <?php echo $tfidf; ?></li>
<?php
  }
?>
    </ol>
  </div>
</div>