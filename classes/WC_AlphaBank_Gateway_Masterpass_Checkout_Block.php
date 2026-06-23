<?php

namespace Papaki\AlphaBank\WooCommerce;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {

    final class WC_AlphaBank_Gateway_Masterpass_Checkout_Block extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
        private $gateway;
        protected $name = 'alphabank_masterpass';

        public function initialize() {
            $this->gateway = new WC_AlphaBank_Gateway_Masterpass();
        }

        public function is_active() {
            return $this->gateway->is_available();
        }

        public function get_payment_method_script_handles() {
            $handle = $this->name . '_gc-blocks-integration';
            wp_register_script(
                $handle,
                plugins_url( 'assets/js/blocks/checkout-masterpass.js', dirname( __FILE__ ) ),
                [
                    'wc-blocks-registry',
                    'wc-settings',
                    'wp-element',
                    'wp-html-entities',
                    'wp-i18n',
                ],
                null,
                true
            );

            if ( function_exists( 'wp_set_script_translations' ) ) {
                wp_set_script_translations( $handle, Application::TEXT_DOMAIN, dirname( __DIR__ ) . '/languages' );

            }
            return [ $handle ];
        }

        public function get_payment_method_data() {
            $data = [
                'title'       => $this->gateway->title,
                'description' => $this->gateway->description,
            ];

            if ( ! empty( $this->gateway->icon ) ) {
                $data['icon'] = $this->gateway->icon;
            }

            return $data;
        }
    }

} else {

    final class WC_AlphaBank_Gateway_Masterpass_Checkout_Block {
        public function __construct() {
            // Do nothing - blocks not supported
        }
    }

}
