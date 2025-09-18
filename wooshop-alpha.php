<?php
/*
  Plugin Name: Payment Gateway – nexi Alpha Bank for WooCommerce
  Plugin URI: https://www.papaki.com
  Description: Payment Gateway – nexi Alpha Bank for WooCommerce allows you to accept payment through various channels such as American Express, Visa, Mastercard, Maestro, Diners Club cards On your Woocommerce Powered Site.
  Version: 2.0.4
  Author: Papaki
  Author URI: https://www.papaki.com
  License: GPL-3.0+
  License URI: http://www.gnu.org/licenses/gpl-3.0.txt
  WC tested: 8.5.0
  Tested with: 6.4.2
  Requires at least: 6.4.2
  Stable tag: 2.0.4
  Text Domain: woo-alpha-bank-payment-gateway
*/

if (!defined('ABSPATH')) {
    exit;
}
add_action('plugins_loaded', 'woocommerce_alphabank_init', 0);

function woocommerce_alphabank_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    load_plugin_textdomain('woo-alpha-bank-payment-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    add_action('before_woocommerce_init', function(){
        global $wpdb;

        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( $wpdb->prefix . 'alphabank_transactions', __FILE__, true );
        }
    });

    require_once 'classes/WC_AlphaBank_Gateway_Base.php';
    require_once 'classes/WC_AlphaBank_Gateway.php';
    require_once 'classes/WC_AlphaBank_Gateway_Masterpass.php';
    require_once 'include/functions.php';

    add_action('wp', 'alphabank_message');
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_alphabank_gateway');

    /**
     * @param $links
     * @param $file
     * @return mixed
     */
    function alphabank_plugin_action_links($links, $file)
    {
        static $this_plugin;

        if (!$this_plugin) {
            $this_plugin = plugin_basename(__FILE__);
        }

        if ($file === $this_plugin) {
            $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=WC_alphabank_Gateway">Settings</a> | <a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=WC_alphabank_Gateway_Masterpass">Masterpass Settings</a>';
            array_unshift($links, $settings_link);
        }

        return $links;
    }

    add_filter('plugin_action_links', 'alphabank_plugin_action_links', 10, 2);
}
