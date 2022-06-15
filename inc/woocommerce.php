<?php

/*
 * New Custom Coupon Type
 */
add_filter( 'woocommerce_coupon_discount_types', function ( $discount_types ) {        
    $discount_types['percent_andor_recurring_percent'] =__('Percentage and/or Recurring Product % Discount', 'woocommerce');  
    return $discount_types;
}, 10, 1);

/*
 * Validate Coupon Type
 */
add_filter('woocommerce_coupon_is_valid_for_product', function ($valid, $product, $coupon, $values){
    if ( ! $coupon->is_type( array('percent_andor_recurring_percent') ) ) {
        return $valid;
    }

    // $product_cats = wp_get_post_terms( $product->id, 'product_cat', array( "fields" => "ids" ) );
    
    // SPECIFIC PRODUCTS ARE DISCOUNTED
    // if ( sizeof( $coupon->product_ids ) > 0 ) {
    //     if ( in_array( $product->id, $coupon->product_ids ) || ( isset( $product->variation_id ) && in_array( $product->variation_id, $coupon->product_ids ) ) || in_array( $product->get_parent(), $coupon->product_ids ) ) {
    //         $valid = true;
    //     }
    // }

    // CATEGORY DISCOUNTS
    // if ( sizeof( $coupon->product_categories ) > 0 ) {
    //     if ( sizeof( array_intersect( $product_cats, $coupon->product_categories ) ) > 0 ) {
    //         $valid = true;
    //     }
    // }

    // IF ALL ITEMS ARE DISCOUNTED
    // if ( ! sizeof( $coupon->product_ids ) && ! sizeof( $coupon->product_categories ) ) {            
    //     $valid = true;
    // }
    
    // SPECIFIC PRODUCT IDs EXLCUDED FROM DISCOUNT
    // if ( sizeof( $coupon->exclude_product_ids ) > 0 ) {
    //     if ( in_array( $product->id, $coupon->exclude_product_ids ) || ( isset( $product->variation_id ) && in_array( $product->variation_id, $coupon->exclude_product_ids ) ) || in_array( $product->get_parent(), $coupon->exclude_product_ids ) ) {
    //         $valid = false;
    //     }
    // }
    
    // SPECIFIC CATEGORIES EXLCUDED FROM THE DISCOUNT
    // if ( sizeof( $coupon->exclude_product_categories ) > 0 ) {
    //     if ( sizeof( array_intersect( $product_cats, $coupon->exclude_product_categories ) ) > 0 ) {
    //         $valid = false;
    //     }
    // }

    // SALE ITEMS EXCLUDED FROM DISCOUNT
    // if ( $coupon->exclude_sale_items == 'yes' ) {
    //     $product_ids_on_sale = wc_get_product_ids_on_sale();

    //     if ( isset( $product->variation_id ) ) {
    //         if ( in_array( $product->variation_id, $product_ids_on_sale, true ) ) {
    //             $valid = false;
    //         }
    //     } elseif ( in_array( $product->id, $product_ids_on_sale, true ) ) {
    //         $valid = false;
    //     }
    // }

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
