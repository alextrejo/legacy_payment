<div class="wrap">
  <h1><?php echo $content['title'] ? $content['title'] : 'Payment Method';?></h1>
  <?php
  if($message){
  ?>
  <div id="message" class="notice notice-<?php echo $message['type'];?> is-dismissible"><p><?php echo $message['content'];?></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>
  <?php
  }

  if($content['description']) echo '<div>' . $content['description'] . '</div>';

  if($content['fields']){
  ?>
  <form method="post">
    <table class="form-table">
    <?php echo $content['fields'];?>
    </table>
    <p class="submit"><input type="submit" class="button button-primary" value="Save"></p>
  </form>
  <?php
  }
  ?>

</div>
