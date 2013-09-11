<?php
  require_once($_SERVER['DOCUMENT_ROOT']."/app/include.php");
  \Application::check_partial_include(__FILE__);

  $this->attrs['topics'] = $this->getDefaultAttr('topics', []);
  $this->attrs['posts'] = $this->getDefaultAttr('posts', []);
  $this->attrs['timeline'] = $this->getDefaultAttr('timeline', []);
?>
<div class='row'>
  <div class='col-md-12'>
    <h2>Posts <small>Times in US/Central</small></h2>
  </div>
</div>
<div class='row'>
  <div class='col-md-7'>
    <div id='user-timeline'></div>
  </div>
  <div class='col-md-5'>
    <div id='user-hourly'></div>
  </div>
</div>
<div class='row'>
  <div class='col-md-6 user-topics'>
    <h2>Topics:</h2>
    <table class='table table-striped table-bordered dataTable'>
      <thead>
        <tr>
          <th class='dataTable-default-sort' data-sort-order='desc'>#</th>
          <th>Topic</th>
          <th>Posts</th>
          <th>Change</th>
        </tr>
      </thead>
      <tbody>
<?php
  $lastCount = 0;
  $topicNum = 1;
  foreach ($this->attrs['topics'] as $topic) {
?>
        <tr>
          <td><?php echo $topicNum; ?></td>
          <td><?php echo $topic['link']; ?></td>
          <td><?php echo $topic['count']; ?></td>
          <td><?php echo $topic['count'] - $lastCount; ?></td>
        </tr>
<?php
    $topicNum++;
    $lastCount = $topic['count'];
  }
?>
      </tbody>
    </table>
  </div>
  <div class='col-md-6 user-posts'>
    <h2>Recent posts:</h2>
    <p>Coming soon!</p>
  </div>
</div>