<?php
namespace ETI;

class PrivateMessage extends Post {
  public static $TABLE = "private_messages";
  public static $PLURAL = "PrivateMessages";
  public static $FIELDS = [
    'id' => [
      'type' => 'int',
      'db' => 'id'
    ],
    'thread_id' => [
      'type' => 'int',
      'db' => 'thread_id'
    ],
    'user_id' => [
      'type' => 'int',
      'db' => 'user_id'
    ],
    'date' => [
      'type' => 'timestamp',
      'db' => 'date'
    ],
    'html' => [
      'type' => 'str',
      'db' => 'messagetext'
    ],
    'sig' => [
      'type' => 'str',
      'db' => 'sig'
    ]
  ];
  public static $JOINS = [
    'user' => [
      'obj' => '\\ETI\\User',
      'table' => 'users',
      'own_col' => 'user_id',
      'join_col' => 'id',
      'type' => 'one'
    ],
    'thread' => [
      'obj' => '\\ETI\\PMThread',
      'table' => 'pm_threads',
      'own_col' => 'thread_id',
      'join_col' => 'id',
      'type' => 'one'
    ]
  ];

  public $thread;

  protected function thread() {
    if (!isset($this->thread_id)) {
      $this->load();
    }
    if (!isset($this->thread)) {
      $this->thread = new PMThread($this->app,  (int) $this->thread_id);
    }
    return $this->thread;
  }

  public function exclude() {
    // returns a new Post with all of the types of nodes in get_func_args() stripped out.

    $excludeTypes = func_get_args();
    $newNodeSet = [];
    foreach ($this->nodes() as $node) {
      if (!in_array($node->nodeType(), $excludeTypes)) {
        $newNodeSet[] = $node;
      }
    }
    $newPost = new PrivateMessage($this->app, $this->id);
    $newPost->set([
                  'thread_id' => $this->thread->id,
                  'thread' => $this->thread,
                  'user_id' => $this->user->id,
                  'user' => $this->user,
                  'nodes' => $newNodeSet
                  ]);
    return $newPost;
  }

  public function render(\View $view) {
    $user = $this->user();
    $thread = $this->thread();
    $date = $this->date->format('n/j/Y h:i:s A');

    $contents = "";
    foreach ($this->nodes() as $node) {
      $contents .= $node->render($view);
    }

    return <<<POST_MARKUP
<div class="message-container" id="m{$this->id}">
  <div class="message-top">
    <b>From:</b> <a href="//endoftheinter.net/profile.php?user={$user->id}">{$user->name}</a> | <b>Posted:</b> {$date} | <a href="/postmsg.php?pm={$thread->id}&amp;quote={$this->id}" onclick="return QuickPost.publish('quote', this);">Quote</a>
  </div>
  <table class="message-body">
    <tr>
      <td msgid="p,{$thread->id},{$this->id}@0" class="message">
{$contents}
---<br />
{$this->sig}</td>
      <td class="userpic">
        <div class="userpic-holder">
          <a href="{$user->avatar}">
            <span class="img-placeholder" style="width:150px;height:144px" id="u0_8"></span>
            <script type="text/javascript">
              onDOMContentLoaded(function(){new ImageLoader($("u0_8"), "{$user->avatar}", 150, 144)})
            </script>
          </a>
        </div>
      </td>
    </tr>
  </table>
</div>
POST_MARKUP;
  }
}

?>