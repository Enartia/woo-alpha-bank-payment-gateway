<?php

namespace Papaki\AlphaBank\WooCommerce;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_AlphaBank_Gateway extends WC_AlphaBank_Gateway_Base {
    const PLUGIN_TITLE = 'Payment Gateway – nexi Alpha Bank for WooCommerce';

    public function __construct() {
        parent::__construct();

        $this->id                 = 'alphabank_gateway';
        $this->notify_url         = WC()->api_request_url( 'WC_AlphaBank_Gateway' );
        $this->method_description = __( static::PLUGIN_TITLE . ' allows you to accept payment through various channels such as Visa, Mastercard, Maestro, American Express, Diners, Discover cards on your Woocommerce Powered Site.', static::TEXT_DOMAIN );
        $this->redirect_page_id   = intval( $this->get_option( 'redirect_page_id' ) );
        $this->method_title       = static::PLUGIN_TITLE;

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings
        $this->init_settings();

        $this->title                     = sanitize_text_field( $this->get_option( 'title' ) );
        $this->description               = sanitize_text_field( $this->get_option( 'description' ) );
        $this->ab_merchantId             = sanitize_text_field( $this->get_option( 'ab_merchantId' ) );
        $this->ab_sharedSecretKey        = sanitize_text_field( $this->get_option( 'ab_sharedSecretKey' ) );
        $this->ab_environment            = sanitize_text_field( $this->get_option( 'ab_environment' ) );
        $this->ab_installments           = absint( $this->get_option( 'ab_installments' ) );
        $this->ab_installments_variation = sanitize_text_field( $this->get_option( 'ab_installments_variation' ) );
        $this->ab_transactionType        = sanitize_text_field( $this->get_option( 'ab_transactionType' ) );
        $this->ab_allowMasterpass        = sanitize_text_field( $this->get_option( 'ab_allowMasterpass' ) );
        $this->ab_render_logo            = $this->get_option( 'ab_render_logo' );
        $this->ab_enable_log             = sanitize_text_field( $this->get_option( 'ab_enable_log' ) );
        $this->ab_order_note             = $this->get_option( 'ab_order_note' );

        //Actions
        add_action( 'woocommerce_receipt_alphabank_gateway', array( $this, 'receipt_page' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        // Payment listener/API hook
        add_action( 'woocommerce_api_wc_alphabank_gateway', array( $this, 'check_alphabank_response' ) );

        if ( $this->ab_render_logo === "yes" ) {
            $this->icon = apply_filters( 'alphabank_icon', plugins_url( '../img/logo.png', __FILE__ ) );
        }
    }

    /**
     * @return void
     */
    public function admin_options() {
        echo '<h3>' . __( static::PLUGIN_TITLE, static::TEXT_DOMAIN ) . '</h3>';
        echo '<p>' . __( static::PLUGIN_TITLE . ' allows you to accept payment through various channels such as Visa, Mastercard, Maestro, American Express, Diners, Discover cards.', static::TEXT_DOMAIN ) . '</p>';
        echo '<div style="background: #f1e5bc; padding: 0.3rem 1rem; max-width:900px;"> <p>' . __( 'In order to enable <strong> ' . WC_AlphaBank_Gateway_Masterpass::PLUGIN_TITLE . '</strong> you should go to <a style="color:#000" href="/wp-admin/admin.php?page=wc-settings&tab=checkout">Woocommerce Payment methods</a> and enable the «<a style="color:#000" href="/wp-admin/admin.php?page=wc-settings&tab=checkout&section=alphabank_masterpass">' . WC_AlphaBank_Gateway_Masterpass::PLUGIN_TITLE . '</a>» payment method', static::TEXT_DOMAIN ) . '</p></div>';
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    /**
     * @return void
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled'                   => array(
                'title'       => __( 'Enable/Disable', static::TEXT_DOMAIN ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable ' . static::PLUGIN_TITLE, static::TEXT_DOMAIN ),
                'description' => __( 'Enable or disable the gateway.', static::TEXT_DOMAIN ),
                'desc_tip'    => true,
                'default'     => 'yes',
            ),
            'ab_environment'            => array(
                'title'   => __( 'Test Environment', static::TEXT_DOMAIN ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable ' . static::PLUGIN_TITLE . ' test environment', static::TEXT_DOMAIN ),
                'default' => 'yes',
            ),
            'title'                     => array(
                'title'       => __( 'Title', static::TEXT_DOMAIN ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', static::TEXT_DOMAIN ),
                'desc_tip'    => false,
                'default'     => __( 'Credit card via Alpha Bank', static::TEXT_DOMAIN ),
            ),
            'description'               => array(
                'title'       => __( 'Description', static::TEXT_DOMAIN ),
                'type'        => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', static::TEXT_DOMAIN ),
                'default'     => __( 'Pay Via Alpha Bank: Accepts Visa, Mastercard, Maestro, American Express, Diners, Discover', static::TEXT_DOMAIN ),
            ),
            'ab_render_logo'            => array(
                'title'   => __( 'Display the logo of Alpha Bank', static::TEXT_DOMAIN ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable to display the logo of Alpha Bank during checkout', static::TEXT_DOMAIN ),
                'default' => 'yes',
            ),
            'ab_merchantId'             => array(
                'title'       => __( 'Alpha Bank Merchant ID', static::TEXT_DOMAIN ),
                'type'        => 'text',
                'description' => __( 'Enter Your Alpha Bank Merchant ID', static::TEXT_DOMAIN ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'ab_sharedSecretKey'        => array(
                'title'       => __( 'Alpha Bank Shared Secret key', static::TEXT_DOMAIN ),
                'type'        => 'password',
                'description' => __( 'Enter your Shared Secret key', static::TEXT_DOMAIN ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'ab_installments'           => array(
                'title'       => __( 'Maximum number of installments regardless of the total order amount', static::TEXT_DOMAIN ),
                'type'        => 'select',
                'options'     => $this->ab_get_installments(),
                'description' => __( '1 to 24 Installments,1 for one time payment. You must contact Alpha Bank first<br /> If you have filled the "Max Number of installments depending on the total order amount", the value of this field will be ignored.', static::TEXT_DOMAIN ),
            ),
            'ab_installments_variation' => array(
                'title'       => __( 'Maximum number of installments depending on the total order amount', static::TEXT_DOMAIN ),
                'type'        => 'text',
                'description' => __( 'Example 80:2, 160:4, 300:8</br> total order greater or equal to 80 -> allow 2 installments, total order greater or equal to 160 -> allow 4 installments, total order greater or equal to 300 -> allow 8 installments</br> Leave the field blank if you do not want to limit the number of installments depending on the amount of the order.', static::TEXT_DOMAIN ),
            ),
            'ab_transactionType'        => array(
                'title'   => __( 'Pre-Authorize', static::TEXT_DOMAIN ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable to capture preauthorized payments', static::TEXT_DOMAIN ),
                'default' => 'no',
            ),
            'redirect_page_id'          => array(
                'title'       => __( 'Return page URL <br />(Successful or Failed Transactions)', static::TEXT_DOMAIN ),
                'type'        => 'select',
                'options'     => $this->ab_get_pages( 'Select Page' ),
                'description' => __( 'We recommend you to select the default “Thank You Page”, in order to automatically serve both successful and failed transactions, with the latter also offering the option to try the payment again.<br /> If you select a different page, you will have to handle failed payments yourself by adding custom code.', static::TEXT_DOMAIN ),
                'default'     => "-1",
            ),
            'ab_enable_log'             => array(
                'title'       => __( 'Enable Debug mode', static::TEXT_DOMAIN ),
                'type'        => 'checkbox',
                'label'       => __( 'Enabling this will log certain information', static::TEXT_DOMAIN ),
                'default'     => 'no',
                'description' => __( 'Enabling this (and the debug mode from your wp-config file) will log information, e.g. bank responses, which will help in debugging issues.', static::TEXT_DOMAIN ),
            ),
            'ab_order_note'             => array(
                'title'       => __( 'Enable 2nd “payment received” email', static::TEXT_DOMAIN ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable sending Customer order note with transaction details', static::TEXT_DOMAIN ),
                'default'     => 'no',
                'description' => __( 'Enabling this will send an email with the support reference id and transaction id to the customer, after the transaction has been completed (either on success or failure)', static::TEXT_DOMAIN ),
            ),
        );
    }

    /**
     * @param int $order
     * @return void
     */
    public function receipt_page( $order ) {
        echo '<p>' . __( 'Thank you for your order. We are now redirecting you to Alpha Bank Paycenter to make payment.', static::TEXT_DOMAIN ) . '</p>';
        echo $this->generate_form( $order, 'ab_payment_form', 'submit_alphabank_payment_form' );
    }
}
