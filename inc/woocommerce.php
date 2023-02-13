<?php

function wporg_apply_custom_code($post) {
  ?>
  <style>
  .post-type-shop_subscription .button.add-coupon {
    display: none;
  }    
  </style>
  <button id="apply_new_custom_coupon" type="button" class="button">Apply New Coupon</button>
  <script>
    let apply_new_custom_coupon = jQuery('#apply_new_custom_coupon');
    apply_new_custom_coupon.on('click', function(){
      let coupon = prompt("Please enter coupon code");
      if (!coupon) return;
      apply_new_custom_coupon.html('Applying');
      jQuery.post("<?php echo admin_url( 'admin-ajax.php' ); ?>", {coupon: coupon, action: 'new_custom_coupon', order_id:"<?php echo $post->ID; ?>" }, function(response){
        response = JSON.parse(response);
        apply_new_custom_coupon.html('Apply New Coupon');
        if(response.success) {
          alert('Coupon applied please refresh to see the change');
          return;
        }
        alert('Error: '+response.message)

      });
    });
  </script>
  <?php
}

function wporg_add_custom_box() {
    add_meta_box(
        'wporg_apply_custom_code',
        'Apply Custom Coupon',
        'wporg_apply_custom_code',
        'shop_subscription'
    );
}
add_action( 'add_meta_boxes', 'wporg_add_custom_box' );

function wp_ajax_new_custom_coupon() {
  global $woocommerce;
  $res = ['success'=>true];
  $order = new WC_Order( $_POST['order_id'] );
  $coupon = new WC_Coupon($_POST['coupon']);
  if(!$coupon->get_id()){
    $res['success'] = false;
    $res['message'] = 'Coupon code is not found.';
    echo json_encode($res);
    wp_die();
  }

  if (!$coupon->is_type('percent_andor_recurring_percent')){
    $res['success'] = false;
    $res['message'] = 'Coupon code only compatible with Percent And Or Recurring Percent.';
    echo json_encode($res);
    wp_die();
  }
  $discount_total = $coupon->get_amount();

  foreach($order->get_items() as $order_item){
    $product_id = $order_item->get_product_id();
    $total = $order_item->get_total();
    $order_item->set_subtotal($total);
    $discount = (( $total / 100) * $discount_total );
    $order_item->set_total($total - $discount);
    $order_item->save();
  }
  $item = new WC_Order_Item_Coupon();
  $item->set_props(array('code' => $_POST['coupon'], 'discount' => $discount_total, 'discount_tax' => 0));
  $order->add_item($item);
  $order->calculate_totals();
  $order->save();
  echo json_encode($res);
  wp_die();
}
add_action("wp_ajax_new_custom_coupon", "wp_ajax_new_custom_coupon");

/*
 * New Custom Coupon Type
 */
add_filter( 'woocommerce_coupon_discount_types', function ( $discount_types ) {        
    $discount_types['percent_andor_recurring_percent'] =__('Percentage and/or Recurring Product % Discount', 'woocommerce');  
    return $discount_types;
}, 10, 1);

add_filter('woocommerce_subscriptions_validate_coupon_type', function($bool, $coupon, $valid) {
  if ( $coupon->is_type( array('percent_andor_recurring_percent') ) ) {
    $valid = true;
    $bool = false;
  }
  return $bool;
}, 10, 3);

/*
 * Validate Coupon Type
 */
add_filter('woocommerce_coupon_is_valid_for_product', function ($valid, $product, $coupon, $values){
    if ( ! $coupon->is_type( array('percent_andor_recurring_percent') ) ) {
        return $valid;
    }

    $product_cats = wp_get_post_terms( $product->get_id(), 'product_cat', array( "fields" => "ids" ) );
    
    // SPECIFIC PRODUCTS ARE DISCOUNTED
    if ( sizeof( $coupon->get_product_ids() ) > 0 ) {
        if ( in_array( $product->get_id(), $coupon->get_product_ids() ) || ( isset( $product->variation_id ) && in_array( $product->variation_id, $coupon->get_product_ids() ) ) || in_array( $product->get_parent(), $coupon->get_product_ids() ) ) {
            $valid = true;
        }
    }

    // CATEGORY DISCOUNTS
    if ( sizeof( $coupon->get_product_categories() ) > 0 ) {
        if ( sizeof( array_intersect( $product_cats, $coupon->get_product_categories() ) ) > 0 ) {
            $valid = true;
        }
    }

    // IF ALL ITEMS ARE DISCOUNTED
    if ( ! sizeof( $coupon->get_product_ids() ) && ! sizeof( $coupon->get_product_categories() ) ) {            
        $valid = true;
    }
    
    // SPECIFIC PRODUCT IDs EXLCUDED FROM DISCOUNT
    if ( sizeof( $coupon->get_excluded_product_ids() ) > 0 ) {
        if ( in_array( $product->id, $coupon->get_excluded_product_ids() ) || ( isset( $product->variation_id ) && in_array( $product->variation_id, $coupon->get_excluded_product_ids() ) ) || in_array( $product->get_parent(), $coupon->get_excluded_product_ids() ) ) {
            $valid = false;
        }
    }
    
    // SPECIFIC CATEGORIES EXLCUDED FROM THE DISCOUNT
    if ( sizeof( $coupon->get_excluded_product_categories() ) > 0 ) {
        if ( sizeof( array_intersect( $product_cats, $coupon->get_excluded_product_categories() ) ) > 0 ) {
            $valid = false;
        }
    }

    // SALE ITEMS EXCLUDED FROM DISCOUNT
    if ( $coupon->get_exclude_sale_items() == 'yes' ) {
        $product_ids_on_sale = wc_get_product_ids_on_sale();

        if ( isset( $product->variation_id ) ) {
            if ( in_array( $product->variation_id, $product_ids_on_sale, true ) ) {
                $valid = false;
            }
        } elseif ( in_array( $product->get_id(), $product_ids_on_sale, true ) ) {
            $valid = false;
        }
    }

    return true;
}, 10, 4);

/*
 * Get Discount Amount
 */
add_filter('woocommerce_coupon_get_discount_amount', function ($discount, $discounting_amount, $cart_item, $single, $coupon) {
    // IF TYPE MATCHES PERFORM CUSTOM CALCULATION
    if ($coupon->is_type('percent_andor_recurring_percent')){
      $price = ((int)$cart_item['line_subtotal']);
      $discount = $price * (($coupon->get_amount()/((int)$cart_item['quantity']))/100);
    }
    return $discount;
}, 10, 5);

/*
 * Remove coupons
 */
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
    if (!class_exists('WC_Subscriptions_Cart')) {
      return;
    }
    remove_action( 'woocommerce_before_calculate_totals', 'WC_Subscriptions_Coupon::remove_coupons', 10 );
    $calculation_type = WC_Subscriptions_Cart::get_calculation_type();
    if ( 'none' == $calculation_type || ! WC_Subscriptions_Cart::cart_contains_subscription() || ( ! is_checkout() && ! is_cart() && ! defined( 'WOOCOMMERCE_CHECKOUT' ) && ! defined( 'WOOCOMMERCE_CART' ) ) ) {
        return;
    }
    $applied_coupons = $cart->get_applied_coupons();
    if ( ! empty( $applied_coupons ) ) {
        $coupons_to_reapply = array();
        foreach ( $applied_coupons as $coupon_code ) {
            $coupon = new WC_Coupon( $coupon_code );
            $coupon_type = wcs_get_coupon_property( $coupon, 'discount_type' );
            if ( in_array( $coupon_type, array( 'percent_andor_recurring_percent','recurring_fee', 'recurring_percent', 'sign_up_and_recurring_percent' ) ) ) {  // always apply coupons to their specific calculation case
                if ( 'recurring_total' == $calculation_type ) {
                    $coupons_to_reapply[] = $coupon_code;
                } elseif ( 'none' == $calculation_type && ! WC_Subscriptions_Cart::all_cart_items_have_free_trial() ) { // sometimes apply recurring coupons to initial total
                    $coupons_to_reapply[] = $coupon_code;
                } else {
                    $removed_coupons[] = $coupon_code;
                }
            } elseif ( ( 'none' == $calculation_type ) && ! in_array( $coupon_type, array( 'recurring_fee', 'recurring_percent' ) ) ) { // apply all coupons to the first payment
                $coupons_to_reapply[] = $coupon_code;
            } else {
                $removed_coupons[] = $coupon_code;
            }
        }
        $cart->remove_coupons();
        $cart->applied_coupons = $coupons_to_reapply;
        if ( isset( $cart->coupons ) ) {
            $cart->coupons = $cart->get_coupons();
        }
    }
}, 10 );
