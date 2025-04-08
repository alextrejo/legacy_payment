<?php
class check_payment extends abstractPayment{
  function __construct(){
    $this->code = 'check';
    $this->name = 'Pay by Check';

    //get settings
    $this->get_settings();
  }

  //Build Settings page for current payment method
  function settings_page(){
    $fields['recipient'] = array('type' => 'text', 'label' => 'Payment Recipient', 'value' => $this->settings->recipient);
    $fields['address'] = array('type' => 'text', 'label' => 'Mail Address', 'value' => $this->settings->address);
    $fields['city'] = array('type' => 'text', 'label' => 'City', 'value' => $this->settings->city);
    $fields['state'] = array('type' => 'text', 'label' => 'State', 'value' => $this->settings->state);
    $fields['zip'] = array('type' => 'text', 'label' => 'Zip', 'value' => $this->settings->zip);

    $description = '<p>Please write who is receiving the check and the address the check should be me mail to</p>';

    return array('title' => 'Check Settings', 'description' => $description, 'fields' => $this->build_form($fields));
  }

  function process_payment($order){
    $output = '<p>Please write a check payable to <b>'. $this->settings->recipient .'</b> for the amount of <b>$'. number_format($order['price'],2) .'</b></p>';
    $output.= '<p>The check should be mailed to following address:</p>';
    $output.= '<p>' . $this->settings->address . '<br>' . $this->settings->city . '. ' . $this->settings->state . ', ' . $this->settings->zip . '</p>';
    $output.= '<p>Once your check is received, you get the Leasing Agreement for signing</p>';
    $output.= '<p>Thank you for your order</p>';
    $output.= '<p><a class="legacy-button" href="'. home_url('/') .'">Continue</a></p>';
    return $output;
  }

  //check if Order status has changed to Completed
  function order_status($post_ID, $post_after, $post_before){
    $order_completed = 24;
    if($_POST['tax_input']['order_status'][0] == $order_completed ){
      $this->payment_complete($post_ID, 0);
    }
  }
}
