<?php
/*
  Plugin Name: Payment Gateway – nexi Alpha Bank for WooCommerce
  Plugin URI: https://www.papaki.com
  Description: Payment Gateway – nexi Alpha Bank for WooCommerce allows you to accept payment through various channels such as American Express, Visa, Mastercard, Maestro, Diners Club cards On your Woocommerce Powered Site.
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

/**
 * @property string $notify_url
 * @property string $redirect_page_id
 * @property string $ab_merchantId
 * @property string $ab_sharedSecretKey
 * @property string $ab_environment
 * @property int $ab_installments
 * @property string $ab_installments_variation
 * @property string $ab_transactionType
 * @property string $ab_allowMasterpass
 * @property string $ab_render_logo
 * @property string $ab_enable_log
 * @property string $ab_order_note
 */

class WC_AlphaBank_Gateway_Base extends WC_Payment_Gateway
{
    public function __construct()
    {
        global $wpdb;

        $this->has_fields = true;

        $tableCheck = $wpdb->get_var("SHOW TABLES LIKE '" . $wpdb->prefix . "alphabank_transactions'");

        if ($tableCheck !== $wpdb->prefix . 'alphabank_transactions') {
            // Table does not exist
            $wpdb->query('CREATE TABLE IF NOT EXISTS ' . $wpdb->prefix . 'alphabank_transactions (id int(11) unsigned NOT NULL AUTO_INCREMENT,merchantreference varchar(30) not null, reference varchar(100) not null, trans_ticket varchar(100) not null , timestamp datetime default null, PRIMARY KEY (id))');
        }
    }

    /**
     * @param string|bool $title
     * @param bool $indent
     * @return array
     */
    public function ab_get_pages($title = false, $indent = true)
    {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();

        if ($title) {
            $page_list[] = $title;
        }

        foreach ($wp_pages as $page) {
            $prefix = '';

            if ($indent) {
                $has_parent = $page->post_parent;
                while ($has_parent) {
                    $prefix .= ' - ';
                    $next_page = get_post($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }

            $page_list[$page->ID] = $prefix . $page->post_title;
        }

        $page_list[-1] = __('Thank you page', 'woo-alpha-bank-payment-gateway');

        return $page_list;
    }

    /**
     * @return void
     */
    public function payment_fields()
    {
        global $woocommerce;

        $amount = 0;

        //get: order or cart total, to compute max installments number.
        if (absint(get_query_var('order-pay'))) {
            $order_id = absint(get_query_var('order-pay'));
            $order = new WC_Order($order_id);
            $amount = $order->get_total();
        } elseif (!$woocommerce->cart->is_empty()) {
            $amount = $woocommerce->cart->total;
        }

        if ($description = $this->get_description()) {
            echo wpautop(wptexturize($description));
        }

        $max_installments = $this->ab_installments;
        $installments_variation = $this->ab_installments_variation;

        if (!empty($installments_variation)) {
            $max_installments = 1; // initialize the max installments
            $installments_split = explode(',', $installments_variation);
            foreach ($installments_split as $value) {
                $installment = explode(':', $value);
                if ((is_array($installment) && count($installment) !== 2) ||
                    (!is_numeric($installment[0]) || !is_numeric($installment[1]))) {
                    // not valid rule for installments
                    continue;
                }

                if ($amount >= ($installment[0])) {
                    $max_installments = $installment[1];
                }
            }
        }

        if ($max_installments > 1) {
            $doseis_field = '<p class="form-row ">
                    <label for="' . esc_attr($this->id) . '-card-doseis">' . __('Choose Installments', 'woo-alpha-bank-payment-gateway') . ' <span class="required">*</span></label>
                                <select id="' . esc_attr($this->id) . '-card-doseis" name="' . esc_attr($this->id) . '-card-doseis" class="input-select wc-credit-card-form-card-doseis">
                                ';
            for ($i = 1; $i <= $max_installments; $i++) {
                $doseis_field .= '<option value="' . $i . '">' . ($i === 1 ? __('Without installments', 'woo-alpha-bank-payment-gateway') : $i) . '</option>';
            }
            $doseis_field .= '</select>
                        </p>'; // <img width="100%" height="100%" style="max-height:100px!important" src="'. plugins_url('img/alpha_cards.png', __FILE__) .'" >

            echo $doseis_field;
        }
    }

    /**
     * @param int $order_id
     * @param string $form_id
     * @param string $button_id
     * @return string
     */
    protected function generate_form($order_id, $form_id, $button_id)
    {
        global $wpdb;

        $lang = get_locale() === 'el' ? 'el' : 'en';
        $version = 2;
        $currency = 'EUR';

        $post_url = $this->ab_environment === "yes" ? "https://alphaecommerce-test.cardlink.gr/vpos/shophandlermpi" : "https://www.alphaecommerce.gr/vpos/shophandlermpi";
        $trType = $this->ab_transactionType === 'yes' ? 2 : 1;

        $order = new WC_Order($order_id);

        if (method_exists($order, 'get_meta')) {
            $installments = $order->get_meta('_doseis');
            if ($installments === '') {
                $installments = 1;
            }
        } else {
            $installments = get_post_meta($order_id, '_doseis', 1);
        }

        $country = $order->get_billing_country();
        $state_code = $order->get_billing_state();

        $wpdb->insert($wpdb->prefix . 'alphabank_transactions', array('trans_ticket' => $order_id, 'merchantreference' => $order_id, 'timestamp' => current_time('mysql', 1)));

        wc_enqueue_js('
            $.blockUI({
            message: "' . esc_js(__('Thank you for your order. We are now redirecting you to Alpha Bank to make payment.', 'woo-alpha-bank-payment-gateway')) . '",
            baseZ: 99999,
            overlayCSS:
            {
            background: "#fff",
            opacity: 0.6
            },
            css: {
            padding:        "20px",
            zindex:         "9999999",
            textAlign:      "center",
            color:          "#555",
            border:         "3px solid #aaa",
            backgroundColor:"#fff",
            cursor:         "wait",
            lineHeight:		"24px",
            }
            });
            jQuery("#' . $form_id . '").submit();
            ');

        $_SESSION['order_id'] = $order_id;
        WC()->session->set('ab_order_id', $order_id);

        $form_data_array = [
            'version' => $version,
            'mid' => $this->ab_merchantId,
            'lang' => $lang,
            'orderid' => $order_id . 'at' . date('Ymdhisu'),
            'orderDesc' => 'Order #' . $order_id,
            'orderAmount' => $order->get_total(),
            'currency' => $currency,
            'payerEmail' => $order->get_billing_email(),
            'billCountry' => $country,
            'billState' => $state_code,
            'billZip' => $order->get_billing_postcode(),
            'billCity' => $order->get_billing_city(),
            'billAddress' => $order->get_billing_address_1(),
            'trType' => $trType,
            'confirmUrl' => get_site_url() . "/?wc-api=WC_alphabank_Gateway&result=success",
            'cancelUrl' => get_site_url() . "/?wc-api=WC_alphabank_Gateway&result=failure",
            'var2' => $order_id,
        ];

        if ($installments > 1) {
            $form_data_array['extInstallmentoffset'] = 0;
            $form_data_array['extInstallmentperiod'] = $installments;
        }

        if (strtolower($country) === 'gr') {
            unset($form_data_array['billState']);
        }

        $form_secret = $this->ab_sharedSecretKey;
        $form_data = iconv('utf-8', 'utf-8//IGNORE', implode("", $form_data_array)) . $form_secret;
        $digest = $this->calculate_digest($form_data);

        $html = '<form action="' . esc_url($post_url) . '" method="POST" id="' . $form_id . '" target="_top" accept-charset="UTF-8">';

        foreach ($form_data_array as $key => $value) {
            $html .= '<input type="hidden" id ="' . $key . '" name ="' . $key . '" value="' . iconv('utf-8', 'utf-8//IGNORE', $value) . '"/>';
        }

        $html .= '<input type="hidden" id="digest" name ="digest" value="' . esc_attr($digest) . '"/>';
        $html .= '<!-- Button Fallback -->
            <div class="payment_buttons">
                <input type="submit" class="button alt" id="' . $button_id . '" value="' . __('Pay via Alpha Bank', 'woo-alpha-bank-payment-gateway') . '" /> <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'woo-alpha-bank-payment-gateway') . '</a>
            </div>
            <script type="text/javascript">
                jQuery(".payment_buttons").hide();
            </script>';
        $html .= '</form>';

        return $html;
    }

    /**
     * @param string $input
     * @return string
     */
    protected function calculate_digest($input)
    {
        return base64_encode(hash('sha256', ($input), true));
    }

    /**
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = new WC_Order($order_id);
        $doseis = (int)$_POST[esc_attr($this->id) . '-card-doseis'];

        if ($doseis > 0) {
            $this->generic_add_meta($order_id, '_doseis', $doseis);
        }

        return array(
            'result' => 'success',
            'redirect' => add_query_arg('order-pay', $order->get_id(), add_query_arg('key', $order->get_order_key(), wc_get_page_permalink('checkout')))
        );
    }

    /**
     * Verify a successful Payment!
     *
     * @return void
     */
    public function check_alphabank_response()
    {
        if ($this->ab_enable_log === 'yes') {
            error_log('---- Alpha Bank Response -----');
            error_log(print_r($_POST, true));
            error_log('---- End of Alpha Bank Response ----');
        }

        $orderid_session = WC()->session->get('ab_order_id');
        $orderid_post = filter_var($_POST['orderid'], FILTER_SANITIZE_STRING);

        preg_match('/^(.*?)at/', $orderid_post, $matches);

        $orderid = !empty($matches) ? $matches[1] : $orderid_session;

        if ($orderid === '') {
            $orderid = $orderid_post;
            error_log("Alpha Bank: something went wrong with order id ");
            error_log(print_r($_POST, true));
            error_log(print_r($matches, true));
            error_log($orderid_session);
        }

        $status = filter_var($_POST['status'], FILTER_SANITIZE_STRING);
        $message = isset($_POST['message']) ? filter_var($_POST['message'], FILTER_SANITIZE_STRING) : '';
        $paymentRef = isset($_POST['paymentRef']) ? filter_var($_POST['paymentRef'], FILTER_SANITIZE_STRING) : '';
        $digest = filter_var($_POST['digest'], FILTER_SANITIZE_STRING);

        $form_data = '';
        foreach ($_POST as $k => $v) {
            if (!in_array($k, array('_charset_', 'digest', 'submitButton'))) {
                $form_data .= filter_var($v, FILTER_SANITIZE_STRING);
            }
        }

        $form_data .= $this->ab_sharedSecretKey;
        $computed_digest = $this->calculate_digest($form_data);

        $order = new WC_Order($orderid);

        if ($digest !== $computed_digest) {
            $message = __('A technical problem occurred. <br />The transaction wasn\'t successful, payment wasn\'t received.', 'woo-alpha-bank-payment-gateway');
            $message_type = 'error';
            $ab_message = array('message' => $message, 'message_type' => $message_type);
            $this->generic_add_meta($orderid, '_alphabank_message', $ab_message);
            $order->update_status('failed', 'DIGEST');
            $checkout_url = wc_get_checkout_url();
            wp_redirect($checkout_url);
            exit;
        }

        if ($status === 'CAPTURED' || $status === 'AUTHORIZED') {
            $order->payment_complete($paymentRef);

            if ($order->get_status() === 'processing') {
                $order->add_order_note(__('Payment Via Alpha Bank<br />Transaction ID: ', 'woo-alpha-bank-payment-gateway') . $paymentRef);
                $message = __('Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is currently being processed.', 'woo-alpha-bank-payment-gateway');

                if ($this->ab_order_note === 'yes') {
                    $order->add_order_note(__('Payment Received.<br />Your order is currently being processed.<br />We will be shipping your order to you soon.<br />Alpha Bank Bank ID: ', 'woo-alpha-bank-payment-gateway') . $paymentRef, 1);
                }
            } elseif ($order->get_status() === 'completed') {
                $message = __('Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is now complete.', 'woo-alpha-bank-payment-gateway');
                if ($this->ab_order_note === 'yes') {
                    $order->add_order_note(__('Payment Received.<br />Your order is now complete.<br />Alpha Bank Transaction ID: ', 'woo-alpha-bank-payment-gateway') . $paymentRef, 1);
                }
            }

            $this->updateStatus($message, 'success', $order);
            WC()->cart->empty_cart();
        } elseif ($status === 'CANCELED') {
            $this->updateStatus('Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment was cancelled.', 'notice', $order, 'failed', 'ERROR ' . $message);
        } elseif ($status === 'REFUSED') {
            $this->updateStatus('Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.', 'error', $order, 'failed', 'REFUSED ' . $message);
        } elseif ($status === 'ERROR') {
            $this->updateStatus('Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.', 'error', $order, 'failed', 'ERROR ' . $message);
        } else {
            $this->updateStatus('Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.', 'error', $order, 'failed', 'Unknown: ' . $message);
        }

        $redirect_url = in_array((string)$this->redirect_page_id, ['', '0', '-1']) ? $this->get_return_url($order) : get_permalink($this->redirect_page_id);
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * @param int $orderid
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function generic_add_meta($orderid, $key, $value)
    {
        $order = new WC_Order($orderid);
        if (method_exists($order, 'add_meta_data') && method_exists($order, 'save_meta_data')) {
            $order->add_meta_data($key, $value, true);
            $order->save_meta_data();
        } else {
            update_post_meta($orderid, $key, $value);
        }
    }

    /**
     * @return void
     */
    public function validate_fields()
    {
        $requiredFields = [
            'billing_email' => 'E-mail address',
            'billing_city' => 'Billing town/city',
            'billing_country' => 'Billing country / region',
            'billing_address_1' => 'Billing street address',
            'billing_postcode' => 'Billing postcode / ZIP'
        ];

        foreach ($requiredFields as $field => $info) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                wc_add_notice(
                    __($info . ' is a mandatory field!'),
                    'error'
                );
            }
        }
    }

    /**
     * @return array
     */
    protected function ab_get_installments()
    {
        for ($i = 1; $i <= 24; $i++) {
            $installment_list[$i] = $i;
        }

        return $installment_list;
    }

    /**
     * @param string $client_message
     * @param string $message_type
     * @param WC_Order $order
     * @param string $status
     * @param string $message
     * @return void
     */
    protected function updateStatus($client_message, $message_type, $order, $status = null, $message = null)
    {
        $ab_message = array('message' => __($client_message, 'woo-alpha-bank-payment-gateway'), 'message_type' => $message_type);
        $this->generic_add_meta($order->get_id(), '_alphabank_message', $ab_message);

        if ($status !== null) {
            $order->update_status($status, $message);
        }
    }

    public static function encrypt($message, $key)
    {
        if (mb_strlen($key, '8bit') !== 32) {
            throw new Exception("Needs a 256-bit key! ".mb_strlen($key, '8bit'));
        }
        $ivsize = openssl_cipher_iv_length('aes-128-cbc');
        $iv = openssl_random_pseudo_bytes($ivsize);

        $ciphertext = openssl_encrypt(
            $message,
            'aes-128-cbc',
            $key,
            0,
            $iv
        );

        return $iv . $ciphertext;
    }

    public static function decrypt($message, $key)
    {
        if (mb_strlen($key, '8bit') !== 32) {
            throw new Exception("Needs a 256-bit key! ".mb_strlen($key, '8bit'));
        }
        $ivsize = openssl_cipher_iv_length('aes-128-cbc');
        $iv = mb_substr($message, 0, $ivsize, '8bit');
        $ciphertext = mb_substr($message, $ivsize, null, '8bit');

        return openssl_decrypt(
            $ciphertext,
            'aes-128-cbc',
            $key,
            0,
            $iv
        );
    }

    public function get_option($key, $empty_value = null)
    {
        $option_value = parent::get_option($key, $empty_value);
        if ($key == 'ab_sharedSecretKey') {
            $decrypted = $this->decrypt(base64_decode($option_value), substr(NONCE_KEY, 0, 32));
            $option_value = $decrypted;
        }
        return $option_value;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return string
     * @throws Exception
     */
    public function validate_ab_sharedSecretKey_field($key, $value)
    {
        $encrypted = self::encrypt($value, substr(NONCE_KEY, 0, 32));
        return base64_encode($encrypted);
    }
}