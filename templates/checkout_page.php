<?php
if($data['error']){
  //There was an error.
  echo $data['error'];
}elseif($data['process']){
  //show process payment page
  echo $data['process'];
}else{
?>
<h3>Your Order # <?php echo $data['order_id'];?></h3>
<?php
if($data['msg']) echo $data['msg'];
?>
<div class="row">
  <div class="col-md-12">
    <p>Dear <?php echo $data['user'];?>, here are the details of your leasing order</p>
    <table class="table table-bordered">
      <thead>
        <tr>
          <th scope="col">Property</th>
          <th scope="col">Leasing Price</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><?php echo $data['property'];?></td>
          <td>$<?php echo number_format($data['price'],2); ?></td>
        </tr>
      </tbody>
    </table>
    <h3>Select Payment Method</h3>
    <div class="gray-section">
      <form id="payment-methods">
      <input type="hidden" name="oid" value="<?php echo $data['order_id'];?>">
    <?php
    foreach($data['payment_methods'] as $payment){
      echo '<p><input type="radio" name="code" value="'. $payment['code'] .'">' . $payment['name'] . "</p>\n";
    }
    ?>
      <div class="gray-section-footer"><button type="submit" class="btn legacy-button lease-search-button">Continue</button></div>
      </form>
    </div>
  </div>
</div>
<?php
}
?>
