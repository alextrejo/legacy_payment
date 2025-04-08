<?php
abstract class abstractPayment{
  public $code, $name, $settings, $enable;

  //Get Settings of payment method
  function get_settings(){
    global $wpdb;

    //get settings
    $table = $wpdb->prefix . 'payment_methods';
    $qry = "SELECT enable, settings FROM $table WHERE code = '". $this->code ."'";
    $row = $wpdb->get_row($qry);

    if($row){
      $this->settings = json_decode($row->settings);
      $this->enable = $row->enable;
    }else{
      $this->settings = null;
      $this->enable = null;
    }
  }

  //Setting page for payment method
  abstract function settings_page();

  //Process payment page
  abstract function process_payment($order);

  //Save payment method Settings
  function save_settings($settings){
    global $wpdb;

    $data = json_encode($settings);
    $table = $wpdb->prefix . 'payment_methods';

    if($this->settings){
      //Update setings
      $qry = $wpdb->prepare("UPDATE $table SET settings = %s WHERE code=%s", $data, $this->code);
    }else{
      //Insert payment method into database
      $qry = $wpdb->prepare("INSERT INTO $table (code, name, settings) VALUES (%s, %s, %s)", $this->code, $this->name, $data);
      $this->enable = 1; //Update enable property
    }

    $wpdb->query($qry);
    $this->settings = json_decode($data); //Update setting property with new values
  }

  //Build input fields
  protected function build_form($fields){
    $output = '';
    foreach($fields as $name => $field){
      switch($field['type']){
        case 'hidden':
          $output.= $this->field_hidden($name,$field);
          break;
        case 'select':
          $output.= $this->field_select($name, $field);
          break;
        case 'radio':
          $output.= $this->field_radio($name, $field);
          break;
        case 'checkbox':
          $output.= $this->field_checkbox($name, $field);
          break;
        default:
          $output.= $this->field_text($name,$field);
          break;
      }
    }
    return $output;
  }

  private function field_text($name, $field){
    $output = '<tr>';
    $output .= '<th scope="row"><label>' . $field['label'] . '</label></th>';
    $output .= '<td>';
    $output .= '<input name="' . $name . '" type="text" value="'. $field['value'] .'" class="regular-text">';
    if($field['description']) $output .= '<p class="description">'. $field['description'] .'</p>';
    $output .= '</td>';
    $output .= '</tr>';

    return $output;
  }

  private function field_hidden($name, $field){
    return '<input type="hidden" name="' . $name . '" value="'. $field['value'] .'">' . "\n";
  }

  private function field_select($name, $field){
    $output =  "<tr>\n";
    $output.=  '<th scope="row">' . $field['label'] . "</th>\n";
    $output.=  '<td><select name="' . $name . '">' . "\n";
    foreach($field['options'] as $k => $v) {
      $output.=  '<option value="'.$v.'"';
      if($field['value'] == $v) $output.=  ' selected';
      $output.=  '>'.$k.'</option>';
    }
    $output.=  '</select>';
    if($field['description']) $output .= '<p class="description">'. $field['description'] .'</p>';
    $output.= "</td>\n";
    $output.= "</tr>\n";

    return $output;
  }

  private function field_radio($name, $field){
    $output =  "<tr>\n";
    $output.=  '<th scope="row">' . $field['label'] . "</th>\n";
    $output.=  "<td><fieldset>\n";
    foreach($field['options'] as $k => $v) {
      $output.= "<label>\n";
      $output.=  '<input type="radio" name="'. $name .'" value="'.$v.'"';
      if($field['value'] == $v) $output.=  ' checked';
      $output.=  '><span>'.$k.'</span>';
      $output.= "</label><br>\n";
    }
    $output.= "</fieldset>\n";
    if($field['description']) $output .= '<p class="description">'. $field['description'] .'</p>';
    $output.= "</td>\n";
    $output.= "</tr>\n";

    return $output;
  }

  private function field_checkbox($name, $field){
    $output = '<tr>';
    $output .= '<th scope="row"><label>' . $field['label'] . '</label></th>';
    $output .= '<td><fieldset>';
    $output.= "<label>";
    $output .= '<input name="' . $name . '" type="checkbox" value="'. $field['option']['value'] .'"';
    if($field['value'] == $field['option']['value']) $output.=  ' checked';
    $output .='>' . $field['option']['label'];
    $output.= "</label>";
    $output .= '</fieldset>';
    if($field['description']) $output .= '<p class="description">'. $field['description'] .'</p>';
    $output .= '</td>';
    $output .= '</tr>';

    return $output;
  }

  protected function payment_complete($order_id){
    global $wpdb, $lgcyLeasing;

    //update order payment info
    update_post_meta($order_id, 'order_payment_date', time());
    //update order status
    wp_set_object_terms( $order_id, 'completed', 'order_status');

    //Get property info to send confirmation emails
    $qry = "SELECT p2.post_title, p.post_author, pm.meta_value AS property_id
            FROM wp_posts p, wp_posts p2, wp_postmeta pm
            WHERE p.ID = $order_id
            AND p.post_type =  'lease_order'
            AND p.ID = pm.post_id
            AND pm.meta_key =  'order_property_id'
            AND pm.meta_value = p2.ID";

    $row = $wpdb->get_row($qry);

    $user = get_userdata( $row->post_author );
    $to = $user->user_email;
    $subject = 'Your Payment for Order ' . $order_id . ' has been recieved';
    $body = '<p>Dear, ' . $user->display_name . '</p>';
    $body .= '<p>Thank you for payment for <b>' . $row->post_title . '</b>.</p>';
    $body .= '<p>You will receive an email with the Lease agreement. Please sign it to finish the lease process</p>';
    $body .= '<p>Best Regards</p><p>Legacy Wildlife Services Team</p>';
    $from = 'Legacy <no-reply@' . ( isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : gethostname() ) . '>';
    $headers = array("From: $from", 'Content-Type: text/html; charset=UTF-8');
    //wp_mail($to, $subject, $body, $headers );

    $order_url = home_url('/wp-admin/post.php?post='. $order_id .'&action=edit');
    $subject = 'Payment for Order ' . $request_id;
    $body = '<p>Payment was recieved for <a href="'. $order_url .'">order '. $order_id . '</a>.</p>';
    $body.= '<p>This is a payment for property <b>' . $row->post_title . '</b>.</p>';
    wp_mail(get_bloginfo('admin_email'), $subject, $body, $headers);

    //Send contract
    $lgcyLeasing->sendContract( get_post_meta($order_id, 'order_lease_id',true) );
  }

}
