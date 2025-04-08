<?php
//Define log file
define("LOG_FILE", "ipn.log");
// Set this to 0 once you go live
define("DEBUG", 0);

class paypal_payment extends abstractPayment{
    function __construct(){
      $this->code = 'paypal';
      $this->name = 'Paypal';

      //get settings
      $this->get_settings();
    }

    //Build Settings page for current payment method
    function settings_page(){
      $fields['email'] = array('type' => 'text', 'label' => 'Paypal email', 'value' => $this->settings->email);
      $fields['email_sandbox'] = array('type' => 'text', 'label' => 'Paypal Sandbox Email', 'value' => $this->settings->email_sandbox);
      $fields['live_server'] = array('type' => 'select', 'label' => 'Production Server', 'value' => $this->settings->live_server, 'description' => 'Change to Yes when you are ready to go live', 'options' => array('Yes' => 1, 'No' => 0));

      return array('title' => 'Paypal Settings', 'description' => '', 'fields' => $this->build_form($fields));
    }

    function process_payment($order){
      if($this->settings->live_server){
        $url = 'https://www.paypal.com/cgi-bin/webscr';
        $email = $this->settings->email;
      }else{
        $url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
        $email = $this->settings->email_sandbox;
      }
      $URL_OK = home_url("/payment-thank-you?oid=" . $order['id']);
      $URL_NOK =  home_url("/checkout?err=payment-error&oid=" . $order['id']);

      $output = '<p>We are processing your payment. You\'ll be redirected to paypal website in a few seconds. Please wait.</p>';
      $output.= '<form name="payment-form" id="payment-form" action="'. $url .'" method="post">' . "\n";
      $fields['business'] = array('type' => 'hidden', 'value' => $email);
      $fields['charset'] = array('type' => 'hidden', 'value' => 'utf-8');
      $fields['cmd'] = array('type' => 'hidden', 'value' => '_xclick');
      $fields['amount'] = array('type' => 'hidden', 'value' => $order['price']);
      $fields['item_name'] = array('type' => 'hidden', 'value' => $order['description']);
      $fields['item_number'] = array('type' => 'hidden', 'value' => $order['id']);
      $fields['currency_code'] = array('type' => 'hidden', 'value' => 'USD');
      $fields['cancel_return'] = array('type' => 'hidden', 'value' => $URL_NOK);
      $fields['return'] = array('type' => 'hidden', 'value' => $URL_OK);
      $fields['notify_url'] = array('type' => 'hidden', 'value' => home_url('/payment-notification?code=paypal'));
      $fields['custom'] = array('type' => 'hidden', 'value' => $order['id']);
      //$fields['ls'] = array('type' => 'hidden', 'value' => IDIOMA_WEB);
      $fields['rm'] = array('type' => 'hidden', 'value' => '2');

      $output .= $this->build_form($fields);

      $output.= "</form>\n";
      $output.= '<script>(function($){ setTimeout( function(){ $("#payment-form").submit(); }, 2000); })(jQuery); </script>';
      return $output;

    }

    //Process notification from paypal
    function notification(){
      // Read POST data
      // reading posted data directly from $_POST causes serialization
      // issues with array data in POST. Reading raw POST data from input stream instead.
      $raw_post_data = file_get_contents('php://input');
      $raw_post_array = explode('&', $raw_post_data);
      $myPost = array();
      foreach ($raw_post_array as $keyval) {
        $keyval = explode ('=', $keyval);
        if (count($keyval) == 2)
          $myPost[$keyval[0]] = urldecode($keyval[1]);
      }
      // read the post from PayPal system and add 'cmd'
      $req = 'cmd=_notify-validate';
      if(function_exists('get_magic_quotes_gpc')) {
        $get_magic_quotes_exists = true;
      }
      foreach ($myPost as $key => $value) {
        if($get_magic_quotes_exists == true && get_magic_quotes_gpc() == 1) {
          $value = urlencode(stripslashes($value));
        } else {
          $value = urlencode($value);
        }
        $req .= "&$key=$value";
      }

      // Post IPN data back to PayPal to validate the IPN data is genuine
      // Without this step anyone can fake IPN data
      if($this->settings->live_server){
        $paypal_url = 'https://www.paypal.com/cgi-bin/webscr';
      }else{
        $paypal_url = 'https://www.sandbox.paypal.com/cgi-bin/webscr';
      }

      $ch = curl_init($paypal_url);
      if ($ch == FALSE) {
        return FALSE;
      }

      curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
      curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);

      if(DEBUG == true) {
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
      }

      // Set TCP timeout to 30 seconds
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

      $res = curl_exec($ch);
      if (curl_errno($ch) != 0) // cURL error
        {
        if(DEBUG == true) {
          $this->save_log(date('[Y-m-d H:i e] '). "Can't connect to PayPal to validate IPN message: " . curl_error($ch) . PHP_EOL);
        }
        curl_close($ch);
        exit;

      } else {
          // Log the entire HTTP response if debug is switched on.
          if(DEBUG == true) {
            $this->save_log(date('[Y-m-d H:i e] '). "HTTP request of validation request:". curl_getinfo($ch, CURLINFO_HEADER_OUT) ." for IPN payload: $req" . PHP_EOL);
            $this->save_log(date('[Y-m-d H:i e] '). "HTTP response of validation request: $res" . PHP_EOL);

            // Split response headers and payload
            list($headers, $res) = explode("\r\n\r\n", $res, 2);
          }
          curl_close($ch);
      }

      // Inspect IPN validation result and act accordingly
      if (strpos($res, "ERIFIED")) {
        // check whether the payment_status is Completed
        // check that txn_id has not been previously processed
        // check that receiver_email is your PayPal email
        // check that payment_amount/payment_currency are correct
        // process payment and mark item as paid.

        if($myPost['custom']){
          $this->payment_complete($myPost['custom'], 0);
        }

        if(DEBUG == true) {
          $this->save_log(date('[Y-m-d H:i e] '). "Verified IPN: $req ". PHP_EOL);
        }
      } else if (strpos ($res, "INVALID")) {
        // log for manual investigation
        // Add business logic here which deals with invalid IPN messages
        if(DEBUG == true) {
          $this->save_log(date('[Y-m-d H:i e] '). "Invalid IPN: $req" . PHP_EOL);
        }
      }
    }

    private function save_log($str){
      $fh = fopen(LOG_FILE, 'a');
      fwrite($fh, $str);
      fclose($fh);
    }
}
