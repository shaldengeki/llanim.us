<?php
  require_once($_SERVER['DOCUMENT_ROOT']."/app/include.php");
  $this->app->check_partial_include(__FILE__);

  $text = isset($this->attrs['text']) ? $this->escape($this->attrs['text']) : 'Type usernames here, one entrant per row.
If an entrant has any people that they cannot give gifts to, enter a colon and then a comma-separated list of those usernames after the user\'s username.
MAKE SURE ALL THE USERNAMES ARE CONSISTENT!

Example:
shaldengeki:fucking,everyone,since,he,sent,things,out,to,everyone,last,year
shard:shaldengeki
akj
son ryo';

if (isset($this->attrs['pairs'])) {
?>
<h2>Pairs:</h2>
<table class='table table-hover'>
  <thead>
    <tr><th>Sender</th><th>Recipient</th></tr>
  </thead>
  <tbody>
<?php
  foreach ($this->attrs['pairs'] as $sender => $recipient) {
?>
    <tr><td><?php echo $this->escape($sender); ?></td><td><?php echo $this->escape($recipient); ?></td></tr>
<?php
  }
?>
</table>
<?php
}

echo $this->form([
                 'class' => 'form-horizontal',
                 'role' => 'form',
                 'action' => $this->app->currentUrl()
                 ]);
?>
  <div class='form-group'>
    <label for='text'>SATers</label>
    <?php echo $this->textarea([
                              'rows' => 20,
                              'cols' => 200,
                              'name' => 'text',
                              'autofocus' => 'true',
                              'required' => 'true'
                            ], $text); ?>
  </div>
  <div class='form-group'>
    <button type='submit' class='btn btn-default'>Get Pairs</button>
  </div>
</form>