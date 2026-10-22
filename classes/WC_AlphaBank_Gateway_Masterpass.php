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
        /* translators: %s: payment gateway name */
        $this->method_description = sprintf( __( '%s allows you to accept payment through MasterPass.', 'woo-alpha-bank-payment-gateway' ), static::PLUGIN_TITLE );
        $this->method_title       = static::PLUGIN_TITLE;

        $this->init_form_fields();

        $this->init_settings();
        $this->title       = sanitize_text_field( $this->get_option( 'masterpass_title' ) );
        $this->description = sanitize_text_field( $this->get_option( 'masterpass_description' ) );

        $alpha_settings = get_option( 'woocommerce_alphabank_gateway_settings', [] );
        $this->ab_merchantId             = $alpha_settings['ab_merchantId'] ?? '';
        // The card gateway stores the secret encrypted; decrypt it the same way its get_option() does,
        // or MasterPass signs the bank request with the ciphertext and the bank rejects the digest.
        $this->ab_sharedSecretKey        = sanitize_text_field( $this->decrypt_stored_secret( $alpha_settings['ab_sharedSecretKey'] ?? '' ) );
        $this->ab_environment            = $alpha_settings['ab_environment'] ?? 'yes';
        $this->ab_installments           = (int) ( $alpha_settings['ab_installments'] ?? 1 );
        $this->ab_installments_variation = $alpha_settings['ab_installments_variation'] ?? '';
        $this->ab_transactionType        = $alpha_settings['ab_transactionType'] ?? 'no';
        $this->redirect_page_id          = $alpha_settings['redirect_page_id'] ?? null;
        $this->ab_order_note             = $alpha_settings['ab_order_note'] ?? 'no';
        $this->ab_enable_log             = $alpha_settings['ab_enable_log'] ?? 'no';
        $this->ab_render_logo            = $alpha_settings['ab_render_logo'] ?? 'yes';
        $this->ab_allowMasterpass        = $alpha_settings['ab_allowMasterpass'] ?? 'yes';

        add_action( 'woocommerce_receipt_alphabank_masterpass', array( $this, 'receipt_page' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
     * @return void
     */
    public function admin_options() {
        echo '<h3>' . esc_html__( 'Alpha Bank MasterPass', 'woo-alpha-bank-payment-gateway' ) . '</h3>';
        echo '<p>' . esc_html__( 'Alpha Bank MasterPass allows you to pay with your MasterPass.', 'woo-alpha-bank-payment-gateway' ) . '</p>';
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
                'title'       => __( 'Enable/Disable', 'woo-alpha-bank-payment-gateway' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable Alpha Bank MasterPass', 'woo-alpha-bank-payment-gateway' ),
                'description' => __( 'Enable or disable the gateway.', 'woo-alpha-bank-payment-gateway' ),
                'desc_tip'    => true,
                'default'     => 'yes',
            ),
            'masterpass_title'       => array(
                'title'       => __( 'Title', 'woo-alpha-bank-payment-gateway' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'woo-alpha-bank-payment-gateway' ),
                'desc_tip'    => false,
                'default'     => __( 'Pay via MasterPass', 'woo-alpha-bank-payment-gateway' ),
            ),
            'masterpass_description' => array(
                'title'       => __( 'Description', 'woo-alpha-bank-payment-gateway' ),
                'type'        => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'woo-alpha-bank-payment-gateway' ),
                'default'     => __( 'Pay Via Alpha Bank MasterPass', 'woo-alpha-bank-payment-gateway' ),
            ),
        );
    }

    /**
     * @param $order
     * @return void
     */
    public function receipt_page( $order ) {
        echo '<p>' . esc_html__( 'Thank you for your order. We are now redirecting you to Alpha Bank MasterPass Paycenter to make payment.', 'woo-alpha-bank-payment-gateway' ) . '</p>';
        echo wp_kses( $this->generate_form( $order, 'ab_payment_masterpass_form', 'submit_alphabank_payment_masterpass_form' ), static::FORM_ALLOWED_HTML );
    }
}
