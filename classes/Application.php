<?php

namespace Papaki\AlphaBank\WooCommerce;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Application {
    public const PLUGIN_TITLE = 'Payment Gateway – nexi Alpha Bank for WooCommerce';
    public const TEXT_DOMAIN  = 'woo-alpha-bank-payment-gateway';

    private $entrypoint_path;

    public function __construct( $entrypoint ) {
        $this->entrypoint_path = $entrypoint;

        // add_action( 'plugins_loaded', [ $this, 'init' ], 0 );
        // we're in a plugins_loaded hook already, @see wooshop-alpha.php
        $this->init();

        add_action( 'init', [ $this, 'load_languages' ] );
        add_action( 'before_woocommerce_init', [ $this, 'declare_transactions' ] );

        $checkout_block = new Checkout_Block( $entrypoint );
        $checkout_block->init();
    }

    public function init() {
        add_action( 'wp', [ $this, 'message' ] );
        add_filter( 'woocommerce_payment_gateways', [ $this, 'add_gateway' ] );
        add_filter( 'plugin_action_links', [ $this, 'plugin_action_links' ], 10, 2 );
    }

    public function load_languages() {
        load_plugin_textdomain( static::TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/../languages/' );
    }

    public function declare_transactions() {
        global $wpdb;

        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( $wpdb->prefix . 'alphabank_transactions', $this->entrypoint_path, true );
        }
    }

    public function message() {
        $order_id = absint( get_query_var( 'order-received' ) );
        $order    = new \WC_Order( $order_id );
        if ( method_exists( $order, 'get_payment_method' ) ) {
            $payment_method = $order->get_payment_method();
        } else {
            $payment_method = $order->payment_method;
        }

        if ( 'alphabank_gateway' === $payment_method && is_order_received_page() ) {
            $alphabank_message = '';
            if ( method_exists( $order, 'get_meta' ) ) {
                $alphabank_message = $order->get_meta( '_alphabank_message', true );
            } else {
                $alphabank_message = get_post_meta( $order_id, '_alphabank_message', true );
            }

            if ( ! empty( $alphabank_message ) ) {
                $message      = $alphabank_message['message'];
                $message_type = $alphabank_message['message_type'];
                if ( method_exists( $order, 'delete_meta_data' ) ) {
                    $order->delete_meta_data( '_alphabank_message' );
                    $order->save_meta_data();
                } else {
                    delete_post_meta( $order_id, '_alphabank_message' );
                }
                wc_add_notice( $message, $message_type );
            }
        }
    }

    public function plugin_action_links( $links, $file ) {
        static $this_plugin;

        if ( ! $this_plugin ) {
            $this_plugin = $this->entrypoint_path;
        }

        if ( $file === $this_plugin ) {
            $settings_link = '<a href="' . get_bloginfo( 'wpurl' ) . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=WC_alphabank_Gateway">Settings</a> | <a href="' . get_bloginfo( 'wpurl' ) . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=WC_alphabank_Gateway_Masterpass">Masterpass Settings</a>';
            array_unshift( $links, $settings_link );
        }

        return $links;
    }

    public function add_gateway( $methods ) {
        $methods[] = '\Papaki\AlphaBank\WooCommerce\WC_AlphaBank_Gateway';
        $methods[] = '\Papaki\AlphaBank\WooCommerce\WC_AlphaBank_Gateway_Masterpass';

        return $methods;
    }
}
