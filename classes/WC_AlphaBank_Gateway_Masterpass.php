<?php

namespace Papaki\AlphaBank\WooCommerce;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_AlphaBank_Gateway_Masterpass extends WC_AlphaBank_Gateway_Base {
    const PLUGIN_TITLE = 'Payment Gateway – nexi Alpha Bank Masterpass for WooCommerce';

    public function __construct() {
        parent::__construct();

        $this->id                 = 'alphabank_masterpass';
        $this->has_fields         = true;
        $this->notify_url         = WC()->api_request_url( 'WC_AlphaBank_Gateway_Masterpass' );
        $this->method_description = __( static::PLUGIN_TITLE . ' allows you to accept payment through MasterPass.', static::TEXT_DOMAIN );
        $this->method_title       = static::PLUGIN_TITLE;

        $this->init_form_fields();

        $this->init_settings();
        $this->title       = sanitize_text_field( $this->get_option( 'masterpass_title' ) );
        $this->description = sanitize_text_field( $this->get_option( 'masterpass_description' ) );

        if ( $alpha_settings = get_option( 'woocommerce_alphabank_gateway_settings' ) ) {
            $this->ab_merchantId             = $alpha_settings['ab_merchantId'];
            $this->ab_sharedSecretKey        = $alpha_settings['ab_sharedSecretKey'];
            $this->ab_environment            = $alpha_settings['ab_environment'];
            $this->ab_installments           = $alpha_settings['ab_installments'];
            $this->ab_installments_variation = $alpha_settings['ab_installments_variation'];
            $this->ab_transactionType        = $alpha_settings['ab_transactionType'];
            $this->redirect_page_id          = $alpha_settings['redirect_page_id'];
            $this->ab_order_note             = $alpha_settings['ab_order_note'];
        }

        add_action( 'woocommerce_receipt_alphabank_masterpass', array( $this, 'receipt_page' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_api_wc_alphabank_gateway', array( $this, 'check_alphabank_response' ) );
    }

    /**
     * @return void
     */
    public function admin_options() {
        echo '<h3>' . __( 'Alpha Bank MasterPass', static::TEXT_DOMAIN ) . '</h3>';
        echo '<p>' . __( 'Alpha Bank MasterPass allows you to pay with your MasterPass.', static::TEXT_DOMAIN ) . '</p>';
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    /**
     * @return void
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'masterpass_enabled'     => array(
                'title'       => __( 'Enable/Disable', static::TEXT_DOMAIN ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable Alpha Bank MasterPass', static::TEXT_DOMAIN ),
                'description' => __( 'Enable or disable the gateway.', static::TEXT_DOMAIN ),
                'desc_tip'    => true,
                'default'     => 'yes',
            ),
            'masterpass_title'       => array(
                'title'       => __( 'Title', static::TEXT_DOMAIN ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', static::TEXT_DOMAIN ),
                'desc_tip'    => false,
                'default'     => __( 'Pay via MasterPass', static::TEXT_DOMAIN ),
            ),
            'masterpass_description' => array(
                'title'       => __( 'Description', static::TEXT_DOMAIN ),
                'type'        => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', static::TEXT_DOMAIN ),
                'default'     => __( 'Pay Via Alpha Bank MasterPass', static::TEXT_DOMAIN ),
            ),
        );
    }

    /**
     * @param $order
     * @return void
     */
    public function receipt_page( $order ) {
        echo '<p>' . __( 'Thank you for your order. We are now redirecting you to Alpha Bank MasterPass Paycenter to make payment.', static::TEXT_DOMAIN ) . '</p>';
        echo $this->generate_form( $order, 'ab_payment_masterpass_form', 'submit_alphabank_payment_masterpass_form' );
    }
}
