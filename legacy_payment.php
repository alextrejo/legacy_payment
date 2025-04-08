<?php
/*
Plugin Name: Legacy Payment
Description: Payment process for Hunting Leases
Author: Alexander Trejo
Version: 1.0
*/

//Load base class for payment methods
require('inc/abstract_payment.class.php');

class lgcyPayment{

  function __construct(){
    //When plugin is activaded create checkout page and DB Tables
    register_activation_hook( __FILE__, array( $this, 'on_install' ) );

    //Add Dashboard payment page
    add_action( 'admin_menu', array($this, 'menu_page') );

    //Shortcode for checkout page
    add_shortcode('lgcy-checkout', array($this, 'output'));

    //Shortcode for thank you page
    add_shortcode('payment-thank-you', array($this, 'thanks_output'));

    //Add Endpoint for payment service notification
    add_action( 'init', array($this, 'notification_endpoint') );

    //Handler for endpoint page
    add_action( 'template_redirect', array($this, 'webhook_template_redirect') );

    //Handler for Order Status updated
    add_action( 'post_updated', array($this, 'check_status'), 10, 3 );
  }

  function on_install(){
    global $wpdb;

    //Create Page for checkout
    $qry = "SELECT ID FROM $wpdb->posts WHERE post_name = 'checkout'";
    $var = $wpdb->get_var($qry);

    if(!$var){
      $my_post = array(
          'post_title'   => 'Checkout',
          'post_content' => '[lgcy-checkout]',
          'post_type'    => 'page',
          'post_name'    => 'checkout',
          'post_status'  => 'publish',
      );

      wp_insert_post( $my_post );
    }

    //Create Thank you page
    $qry = "SELECT ID FROM $wpdb->posts WHERE post_name = 'payment-thank-you'";
    $var = $wpdb->get_var($qry);

    if(!$var){
      $my_post = array(
          'post_title'   => 'Thank you',
          'post_content' => '[payment-thank-you]',
          'post_type'    => 'page',
          'post_name'    => 'payment-thank-you',
          'post_status'  => 'publish',
      );

      wp_insert_post( $my_post );
    }

    //Create database table
    $table = $wpdb->prefix . 'payment_methods';
    $qry = "CREATE TABLE IF NOT EXISTS $table (payment_id INT UNSIGNED NOT NULL AUTO_INCREMENT, code VARCHAR(32) NOT NULL, name VARCHAR(128) NOT NULL, enable INT(1) DEFAULT 1, settings TEXT, PRIMARY KEY (payment_id), INDEX code_idx (code) )";
    $wpdb->query($qry);
  }

  //Endpoint callback function
  function notification_endpoint(){
    add_rewrite_endpoint( 'payment-notification', EP_ROOT );
  }

  //create payment object
  function payment_factory($code){
    $file = dirname(__FILE__) . '/payments/' . $code . '/' . $code . '.php';
    if( file_exists($file) ){
      require($file);
      $class = $code . '_payment';
      $payment = new $class();

      return $payment;
    }else{
      return null;
    }
  }

  //Handle Endpoint request
  function webhook_template_redirect(){
    global $wp_query;

    // if this is not notification endpoint, bail
    if ( !isset( $wp_query->query_vars['payment-notification'] ) ) return;

    if(isset($_GET['code'])){
      if($payment = $this->payment_factory($_GET['code']) ){
        //Parse notification from payment gateway
        $payment->notification();
      }
    }
  }

  function menu_page(){
    add_menu_page('Payment Methods', 'Payment Methods', 'manage_options', 'payment-method',array($this, 'menu_page_output'), 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><defs><style>.cls-1{fill:#fff;}</style></defs><path class="cls-1" d="M512,153.6H0a64.19,64.19,0,0,1,64-64H448A64.19,64.19,0,0,1,512,153.6ZM0,204.8H512V358.4a64.19,64.19,0,0,1-64,64H64a64.19,64.19,0,0,1-64-64Zm64,89.6a12.8,12.8,0,0,0,12.8,12.8h76.8a12.8,12.8,0,1,0,0-25.6H76.8A12.8,12.8,0,0,0,64,294.4Zm0,51.2a12.8,12.8,0,0,0,12.8,12.8H217.6a12.8,12.8,0,1,0,0-25.6H76.8A12.8,12.8,0,0,0,64,345.6Z"/></svg>') );
  }

  //Admin payment page
  function menu_page_output(){
    global $wpdb;

    //Shows setting page
    if($_GET['do'] == 'settings' && isset($_GET['code'])){
        if($payment = $this->payment_factory($_GET['code']) ){

        //Save payment settings
        if($_POST){
          $payment->save_settings($_POST);
          $message = array('content' => 'Payment method settings updated successfully', 'type' => 'success');
        }

        $content = $payment->settings_page();
      }else{
        $message = array('content' => 'Payment method not found', 'type' => 'error');
      }

      include('inc/settings.php');
      return;
    }

    //Disable/enable a method
    if( ($_GET['do'] == 'disable' || $_GET['do'] == 'enable') && $_GET['code']){
      $value = $_GET['do'] == 'enable' ? 1 : 0;
      $table = $wpdb->prefix . 'payment_methods';
      $qry = $wpdb->prepare("UPDATE $table SET enable = $value WHERE code=%s", $_GET['code']);
      $wpdb->query($qry);

      $message = array('content' =>'Payment method has been updated', 'type' => 'success');
    }

    //Load all payments methods
    $dir = dirname(__FILE__) . '/payments';
    $payments = array();
    if (is_dir($dir)) {
      if ($dh = opendir($dir)) {
        while (($dirname = readdir($dh)) !== false) {
            if( !is_file($dirname) && $dirname != '.' && $dirname != '..' ){
              $path = $dir . '/' . $dirname . '/' . $dirname . '.php';
              if(file_exists( $path )){
                require($path);
                $class = $dirname . '_payment';
                $payments[] = new $class();
              }
            }
        }
        closedir($dh);
      }
    }

    include('inc/payment_list.php');
  }

  function output(){
    global $wpdb;

    $error = $msg = $process = '';
    //User must be logged in
    $user = wp_get_current_user();
    if($user->ID){
      $order_id = (int)$_GET['oid'];

      $qry = "SELECT p2.post_title, p.post_author, pm.meta_value AS property_id, pm2.meta_value AS property_price
              FROM $wpdb->posts p, $wpdb->posts p2, $wpdb->postmeta pm, $wpdb->postmeta pm2
              WHERE p.ID = $order_id
              AND p.post_type =  'lease_order'
              AND p.ID = pm.post_id
              AND pm.meta_key =  'order_property_id'
              AND pm.meta_value = p2.ID
              AND pm.meta_value = pm2.post_id
              AND pm2.meta_key =  '_hunting_lease_price'";

      $row = $wpdb->get_row($qry);

      //Order belong to logged in user?
      if($user->ID == $row->post_author){
        //Payment method selected
        if(isset($_GET['code'])){
          //load payment method
          if( $payment = $this->payment_factory($_GET['code']) ){
            //update order payment method info
            update_post_meta($order_id, 'order_payment_method', $payment->name);
            update_post_meta($order_id, 'order_payment_amount', $row->property_price);

            //process payment
            $order = array('id' => $order_id, 'price' => $row->property_price, 'description' => $row->post_title);
            $process = $payment->process_payment( $order );
          }else{
            $error = wpMessage::format('Invalid payment method selected. Please try again','error');
          }
        }else{
          //Check if order is completed
          $status = wp_get_post_terms( $order_id, 'order_status');
          if($status[0]->name != 'Completed'){
            //get active payment methods and shows payment page
            $table = $wpdb->prefix . 'payment_methods';
            $qry = "SELECT code, name from $table WHERE enable=1";
            $rows = $wpdb->get_results($qry);
            $payments = array();
            foreach($rows as $r) $payments[] = array('code' => $r->code, 'name' => $r->name);
          }else{
            $error = wpMessage::format('The Order provided is already completed. You don\'t need to perform check out again','error');
          }
        }
      }else{
        $error = wpMessage::format('You have a wrong order. Please try again','error');
      }
    }else{
      $error = wpMessage::format('Please <a href="'. wp_login_url( '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) .'">login</a> to access this page', 'error');
    }

    if($_GET['err'] == 'payment-error') $msg = wpMessage::format('There was an error processing your payment. Please try again', 'notice');
    return mvcView::render('checkout_page.php', array('order_id' => $order_id, 'payment_methods' => $payments, 'property' => $row->post_title, 'user' => $user->display_name, 'price' => $row->property_price, 'error' => $error, 'process' => $process, 'msg' => $msg));
  }

  //If the Payment method has a function to check Order status when updated, run it
  function check_status($post_ID, $post_after, $post_before){
    global $wpdb;

    //It is not a Lease Order, nothing to do
    if($post_after->post_type != 'lease_order') return;

    //get all enable payment methods
    $dir = dirname(__FILE__) . '/payments';
    $table = $wpdb->prefix . 'payment_methods';
    $qry = "SELECT code FROM $table WHERE enable=1";
    $rows = $wpdb->get_results($qry);
    $payment_method = get_post_meta($post_ID, 'order_payment_method', true); //Order payment method
    foreach($rows as $row){
        //method class file
        $path = $dir . '/' . $row->code . '/' . $row->code . '.php';
        if(file_exists( $path )){
          require($path);
          $class = $row->code . '_payment';
          $payment = new $class();
          //Call method if exists and is the one used on current order
          if( $payment_method == $payment->name && method_exists( $payment , 'order_status' ) ) $payment->order_status($post_ID, $post_after, $post_before);
        }
    }
  }

  //Thank you page when payment is completed
  function thanks_output(){
    global $wpdb;

    $order_id = (int)$_GET['oid'];
    if(!$order_id) return;

    //Get Order Details
    //Get property info to send confirmation emails
    $qry = "SELECT p2.post_title, p.post_author, pm.meta_value AS property_id
            FROM $wpdb->posts p, $wpdb->posts p2, $wpdb->postmeta pm
            WHERE p.ID = $order_id
            AND p.post_type =  'lease_order'
            AND p.ID = pm.post_id
            AND pm.meta_key =  'order_property_id'
            AND pm.meta_value = p2.ID";
    $row = $wpdb->get_row($qry);

    $user = get_userdata( $row->post_author );

    $output = '<p>Dear ' . $user->display_name . '</p>';
    $output.= '<p>We have received your payment for Order number ' . $order_id . '</p><p>Please check your email inbox, we will sent you the lease agreement for property <b><a href="'. get_the_permalink($row->property_id) .'">'. $row->post_title .'</a></p>';
    return $output;
  }
}

$lgcyPayment = new lgcyPayment();
