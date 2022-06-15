<?php

/**
 * @link              https://estradaenterprises.biz/
 * @since             0.0.1
 * @package           Woo_Percent_and_or_Recurring_Percent_Coupon_Type
 *
 * @wordpress-plugin
 * Plugin Name:       Woo Percent and or Recurring Percent Coupon Type
 * Plugin URI:        https://estradaenterprises.biz/
 * Description:       WordPress Plugin
 * Version:           0.0.1
 * Author:            Estrada Enterprises
 * Author URI:        https://estradaenterprises.biz/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woo-percent-and-or-recurring-percent-coupon-type
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'WPAORPCT_WP_VERSION', '0.0.1' );

function woo_percent_and_or_recurring_percent_coupon_type_init() {
    require_once plugin_dir_path( __FILE__ ) . 'inc/woocommerce.php';
}
woo_percent_and_or_recurring_percent_coupon_type_init();
