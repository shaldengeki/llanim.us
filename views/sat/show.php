<?php
  require_once($_SERVER['DOCUMENT_ROOT']."/app/include.php");
  \Application::check_partial_include(__FILE__);

  $this->attrs['authors'] = isset($this->attrs['authors']) ? $this->attrs['authors'] : [];
  $this->attrs['terms'] = isset($this->attrs['terms']) ? $this->attrs['terms'] : [];
  $this->attrs['timeline'] = isset($this->attrs['timeline']) ? $this->attrs['timeline'] : [];
?>
<div class='row'>
  <div class='col-md-12'>
    <div id='sat-timeline'></div>
  </div>
</div>
<div class='row'>
  <div class='col-md-6 sat-authors'>
    <h2>Authors:</h2>
    <table class='table table-striped table-bordered dataTable'>
      <thead>
        <tr>
          <th>Author</th>
          <th class='dataTable-default-sort' data-sort-order='desc'>Posts</th>
          <th>Change</th>
        </tr>
      </thead>
      <tbody>
<?php
  foreach ($this->attrs['authors'] as $author) {
?>
        <tr>
          <td><?php echo $author['link']; ?></td>
          <td><?php echo $author['count']; ?></td>
          <td><?php echo $author['change']; ?></td>
        </tr>
<?php
  }
?>
      </tbody>
    </table>
  </div>
  <div class='col-md-6 sat-terms'>
    <h2>Topics:</h2>
    <table class='table table-striped table-bordered dataTable'>
      <thead>
        <tr>
          <th>Topic</th>
          <th class='dataTable-default-sort' data-sort-order='desc'><a href='https://en.wikipedia.org/wiki/TF_IDF'>Prominence</a></th>
          <th>Change</th>
        </tr>
      </thead>
      <tbody>
<?php
  foreach ($this->attrs['terms'] as $term) {
?>
        <tr>
          <td><?php echo $term['link']; ?></td>
          <td><?php echo $term['tfidf']; ?></td>
          <td><?php echo $term['change']; ?></td>
        </tr>
<?php
  }
?>
      </tbody>
    </table>
  </div>
</div>