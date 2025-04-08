<div class="wrap">
  <h1>Payment Methods</h1>
  <?php
  if($message){
  ?>
  <div id="message" class="notice notice-<?php echo $message['type'];?> is-dismissible"><p><?php echo $message['content'];?></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>
  <?php
  }
  ?>
<?php
if($payments){
?>
  <table class="wp-list-table widefat fixed striped posts">
  	<thead>
  	<tr>
      <th>Payment Methods</th>
      <th></th>
      <th></th>
    </tr>
  	</thead>
  	<tbody id="the-list">
    <?php
    $admin_url = admin_url();
    foreach($payments as $payment){
      $alert = $payment->settings ? '' : '<span class="dashicons dashicons-warning" style="color: red;" title="Please configure">';
      if($payment->enable){
        $enable ='<span class="dashicons dashicons-marker" style="color: green;"></span> <a href="' . $admin_url . 'admin.php?page=payment-method&do=disable&code=' . $payment->code . '">Enabled';
        $enable_action ='<span class="enable"><a href="' . $admin_url . 'admin.php?page=payment-method&do=disable&code=' . $payment->code . '">Enabled</span>';
      }else{
        $enable = '<span class="dashicons dashicons-marker" style="color: red;"></span> <a href="' . $admin_url . 'admin.php?page=payment-method&do=enable&code=' . $payment->code . '">Disable</a>';
        $enable_action ='<span class="enable"><a href="' . $admin_url . 'admin.php?page=payment-method&do=enable&code=' . $payment->code . '">Disable</span>';
      }
    ?>
  		<tr>
      <td class="title column-title has-row-actions column-primary page-title" data-colname="Title">
        <strong><a class="row-title" href="javascript: return void(0);" aria-label="“<?php echo $payment->name;?>” (Edit)"><?php echo $payment->name;?></a></strong>
        <div class="row-actions">
          <span class="edit"><a href="<?php echo $admin_url . 'admin.php?page=payment-method&do=settings&code=' . $payment->code;?>" aria-label="Settings “Activity”">Settings</a> | </span>
          <?php echo $enable_action;?>
        </div>
      </td>
      <td class="column-settings" data-colname="Settings"><a href="<?php echo $admin_url . 'admin.php?page=payment-method&do=settings&code=' . $payment->code;?>">Settings</a> <?php echo $alert;?></span></td>
      <td class="column-enable" data-colname="Enabled"><?php echo $enable;?></td>
    </tr>
    <?php
    }
    ?>
  	</tbody>
  </table>
<?php
}
?>
</div>
<div id="dialog-confirm" title="Confirm Delete" style="display: none;">
  <p></span><span id="dialog-body"></span></p>
</div>
<div id="card-preview" title="Card" style="display: none;">
  <img id="card-view" src="" style="max-width: 100%; height: auto;">
</div>
<script type="text/javascript">
( function($) {

  $('document').ready(function(){
    //Delete confirmation
    $('.submitdelete').on('click',function(e){
      e.preventDefault();
      var href = $(this).attr('href');
      var cname = $(this).parent().parent().prev('strong').children('a').html();
      $('#dialog-body').html( 'Are you sure you want to delete card <b>' + cname + '</b>?' );

      $( "#dialog-confirm" ).dialog({
        resizable: false,
        height: "auto",
        width: 400,
        modal: true,
        buttons: {
          "Delete": function() {
            window.location.href = href;
          },
          Cancel: function() {
            $( this ).dialog( "close" );
          }
        }
      });
    });

    //View card
    $('.viewcard').on('click', function(e){
      e.preventDefault();

      var aid = $(this).attr('data-attid');
      $.get(
          ajaxurl,
          {
               action: 'get_card_img',
               aid: aid
          },
          function(src){
            $('#card-view').attr('src',src);
            $( "#card-preview" ).dialog({
              resizable: true,
              height: "auto",
              width: "60%",
              modal: true,
              buttons: {
                Close: function() {
                  $( this ).dialog( "close" );
                }
              }
            });
          }
      );
    });

  });

})(jQuery);
</script>
