<?php
/*
  Plugin Name: Payment Gateway – nexi Alpha Bank Masterpass for WooCommerce
  Plugin URI: https://www.papaki.com
  Description: Payment Gateway – nexi Alpha Bank Masterpass allows you to accept payment through MasterPass
  Version: 2.0.3
  Author: Papaki
  Author URI: https://www.papaki.com
  License: GPL-3.0+
  License URI: http://www.gnu.org/licenses/gpl-3.0.txt
  WC tested: 8.5.0
  Text Domain: woo-alpha-bank-payment-gateway
*/

if (!defined('ABSPATH')) {
    exit;
}

class WC_AlphaBank_Gateway_Masterpass extends WC_AlphaBank_Gateway_Base
{
    const PLUGIN_TITLE = 'Payment Gateway – nexi Alpha Bank Masterpass for WooCommerce';

    public function __construct()
    {
        parent::__construct();

        $this->id = 'alphabank_masterpass';
        $this->has_fields = true;
        $this->notify_url = WC()->api_request_url('WC_AlphaBank_Gateway_Masterpass');
        $this->method_description = __(self::PLUGIN_TITLE . ' allows you to accept payment through MasterPass.', 'woo-alpha-bank-payment-gateway');
        $this->method_title = self::PLUGIN_TITLE;

        $this->init_form_fields();

        $this->init_settings();
        $alpha_settings = get_option('woocommerce_alphabank_gateway_settings');
        $this->title = sanitize_text_field($this->get_option('masterpass_title'));
        $this->description = sanitize_text_field($this->get_option('masterpass_description'));
        $this->ab_merchantId = $alpha_settings['ab_merchantId'];
        $this->ab_sharedSecretKey = $alpha_settings['ab_sharedSecretKey'];
        $this->ab_environment = $alpha_settings['ab_environment'];
        $this->ab_installments = $alpha_settings['ab_installments'];
        $this->ab_installments_variation = $alpha_settings['ab_installments_variation'];
        $this->ab_transactionType = $alpha_settings['ab_transactionType'];
        $this->redirect_page_id = $alpha_settings['redirect_page_id'];
        $this->ab_order_note = $alpha_settings['ab_order_note'];

        add_action('woocommerce_receipt_alphabank_masterpass', array($this, 'receipt_page'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_wc_alphabank_gateway', array($this, 'check_alphabank_response'));
    }

    /**
     * @return void
     */
    public function admin_options()
    {
        echo '<h3>' . __('Alpha Bank MasterPass', 'woo-alpha-bank-payment-gateway') . '</h3>';
        echo '<p>' . __('Alpha Bank MasterPass allows you to pay with your MasterPass.', 'woo-alpha-bank-payment-gateway') . '</p>';
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    /**
     * @return void
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'masterpass_enabled' => array(
                'title' => __('Enable/Disable', 'woo-alpha-bank-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Alpha Bank MasterPass', 'woo-alpha-bank-payment-gateway'),
                'description' => __('Enable or disable the gateway.', 'woo-alpha-bank-payment-gateway'),
                'desc_tip' => true,
                'default' => 'yes'
            ),
            'masterpass_title' => array(
                'title' => __('Title', 'woo-alpha-bank-payment-gateway'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woo-alpha-bank-payment-gateway'),
                'desc_tip' => false,
                'default' => __('Pay via MasterPass', 'woo-alpha-bank-payment-gateway')
            ),
            'masterpass_description' => array(
                'title' => __('Description', 'woo-alpha-bank-payment-gateway'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'woo-alpha-bank-payment-gateway'),
                'default' => __('Pay Via Alpha Bank MasterPass', 'woo-alpha-bank-payment-gateway')
            )
        );
    }

    /**
     * @param $order
     * @return void
     */
    public function receipt_page($order)
    {
        echo '<p>' . __('Thank you for your order. We are now redirecting you to Alpha Bank MasterPass Paycenter to make payment.', 'woo-alpha-bank-payment-gateway') . '</p>';
        echo $this->generate_form($order, 'ab_payment_masterpass_form', 'submit_alphabank_payment_masterpass_form');
    }
}