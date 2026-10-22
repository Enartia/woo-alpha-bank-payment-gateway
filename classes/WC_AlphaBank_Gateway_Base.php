<?php

namespace Papaki\AlphaBank\WooCommerce;
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @property string $notify_url
 * @property string|null $redirect_page_id
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

class WC_AlphaBank_Gateway_Base extends \WC_Payment_Gateway {
    const PLUGIN_TITLE          = Application::PLUGIN_TITLE;
    const TEXT_DOMAIN           = Application::TEXT_DOMAIN;
    public const ENCRYPTION_METHOD     = 'aes-128-cbc';
    public const ENCRYPTION_KEY_LENGTH = 32; // 32-byte NONCE_KEY slice; cipher is AES-128 (matches the 2.0.x storage format — do NOT switch to AES-256)

    /**
     * Tags and attributes the redirect form may contain when it is echoed through wp_kses().
     */
    public const FORM_ALLOWED_HTML = array(
        'form'  => array( 'action' => true, 'method' => true, 'id' => true, 'target' => true, 'accept-charset' => true ),
        'input' => array( 'type' => true, 'id' => true, 'name' => true, 'value' => true, 'class' => true ),
        'div'   => array( 'class' => true ),
        'a'     => array( 'class' => true, 'href' => true ),
    );

    /**
     * Currency the plugin sends to the bank and expects back.
     */
    public const CURRENCY = 'EUR';

    /**
     * Response fields the bank signs, in the order the digest is computed over.
     */
    public const RESPONSE_DIGEST_FIELDS = array( 'version', 'mid', 'orderid', 'status', 'orderAmount', 'currency', 'paymentTotal', 'message', 'riskScore', 'payMethod', 'txId', 'paymentRef' );

    /**
     * Payment method IDs whose orders the bank-return handler may update.
     */
    public const OWN_PAYMENT_METHODS = array( 'alphabank_gateway', 'alphabank_masterpass' );

    protected string $notify_url;
    protected ?string $redirect_page_id;
    protected string $ab_merchantId;
    protected string $ab_sharedSecretKey;
    protected string $ab_environment;
    protected int $ab_installments;
    protected string $ab_installments_variation;
    protected string $ab_transactionType;
    protected string $ab_allowMasterpass;
    protected string $ab_render_logo;
    protected string $ab_enable_log;
    protected string $ab_order_note;

    public function __construct() {
        $this->has_fields = true;
    }

    /**
     * Hide the gateway at checkout until the merchant ID and shared secret are saved.
     *
     * Without both, the bank rejects the request and the plugin cannot verify the response.
     * MasterPass reads both values from the card gateway's settings, so it is hidden too.
     *
     * @return bool
     */
    public function is_available() {
        if ( $this->ab_merchantId === '' || $this->ab_sharedSecretKey === '' ) {
            return false;
        }

        return parent::is_available();
    }

    /**
     * @param string|bool $title
     * @param bool $indent
     * @return array
     */
    public function ab_get_pages( $title = false, $indent = true ) {
        $wp_pages  = get_pages( 'sort_column=menu_order' );
        $page_list = array();

        if ( $title ) {
            $page_list[] = $title;
        }

        foreach ($wp_pages as $page) {
            $prefix = '';

            if ( $indent ) {
                $has_parent = $page->post_parent;
                while ( $has_parent ) {
                    $prefix     .= ' - ';
                    $next_page   = get_post( $has_parent );
                    $has_parent  = $next_page->post_parent;
                }
            }

            $page_list[ $page->ID ] = $prefix . $page->post_title;
        }

        $page_list[ -1 ] = __( 'Thank you page', 'woo-alpha-bank-payment-gateway' );

        return $page_list;
    }

    /**
     * @return void
     */
    public function payment_fields() {
        global $woocommerce;

        $amount = 0;

        //get: order or cart total, to compute max installments number.
        if ( absint( get_query_var( 'order-pay' ) ) ) {
            $order_id = absint( get_query_var( 'order-pay' ) );
            $order    = new \WC_Order( $order_id );
            $amount   = $order->get_total();
        } elseif ( ! $woocommerce->cart->is_empty() ) {
            $amount = $woocommerce->cart->total;
        }

        if ( $description = $this->get_description() ) {
            echo wp_kses_post( wpautop( wptexturize( $description ) ) );
        }

        $max_installments       = $this->ab_installments;
        $installments_variation = $this->ab_installments_variation;

        if ( ! empty( $installments_variation ) ) {
            $max_installments   = 1; // initialize the max installments
            $installments_split = explode( ',', $installments_variation );
            foreach ($installments_split as $value) {
                $installment = explode( ':', $value );
                if ( ( is_array( $installment ) && count( $installment ) !== 2 ) ||
                    ( ! is_numeric( $installment[0] ) || ! is_numeric( $installment[1] ) ) ) {
                    // not valid rule for installments
                    continue;
                }

                if ( $amount >= ( $installment[0] ) ) {
                    $max_installments = $installment[1];
                }
            }
        }

        if ( $max_installments > 1 ) {
            $field_id     = esc_attr( $this->id ) . '-card-doseis';
            $doseis_field = '<p class="form-row ">
                    <label for="' . $field_id . '">' . esc_html__( 'Choose Installments', 'woo-alpha-bank-payment-gateway' ) . ' <span class="required">*</span></label>
                                <select id="' . $field_id . '" name="' . $field_id . '" class="input-select wc-credit-card-form-card-doseis">
                                ';
            for ( $i = 1; $i <= $max_installments; $i++ ) {
                $doseis_field  .= '<option value="' . absint( $i ) . '">' . ( $i === 1 ? esc_html__( 'Without installments', 'woo-alpha-bank-payment-gateway' ) : absint( $i ) ) . '</option>';
            }
            $doseis_field  .= '</select>
                        </p>'; // <img width="100%" height="100%" style="max-height:100px!important" src="'. plugins_url('img/alpha_cards.png', __FILE__) .'" >

            echo wp_kses(
                $doseis_field,
                array(
                    'p'      => array( 'class' => true ),
                    'label'  => array( 'for' => true ),
                    'span'   => array( 'class' => true ),
                    'select' => array( 'id' => true, 'name' => true, 'class' => true ),
                    'option' => array( 'value' => true ),
                )
            );
        }
    }

    /**
     * @param int $order_id
     * @param string $form_id
     * @param string $button_id
     * @return string
     */
    protected function generate_form( $order_id, $form_id, $button_id ) {
        global $wpdb;

        $lang     = get_locale() === 'el' ? 'el' : 'en';
        $version  = 2;
        $currency = static::CURRENCY;

        $post_url = $this->ab_environment === "yes" ? "https://alphaecommerce-test.cardlink.gr/vpos/shophandlermpi" : "https://www.alphaecommerce.gr/vpos/shophandlermpi";
        $trType   = $this->ab_transactionType === 'yes' ? 2 : 1;

        $order = new \WC_Order( $order_id );

        $installments = $order->get_meta( '_doseis' );
        if ( $installments === '' ) {
            $installments = 1;
        }

        $country    = $order->get_billing_country();
        $state_code = $order->get_billing_state();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insert into the plugin's own table; WordPress has no API for it.
        $wpdb->insert( $wpdb->prefix . 'alphabank_transactions', array( 'trans_ticket' => $order_id, 'merchantreference' => $order_id, 'reference' => '', 'timestamp' => current_time( 'mysql', 1 ) ) );

        wc_enqueue_js( '
            $.blockUI({
            message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to Alpha Bank to make payment.', 'woo-alpha-bank-payment-gateway' ) ) . '",
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
            lineHeight:        "24px",
            }
            });
            jQuery(".payment_buttons").hide();
            jQuery("#' . esc_js( $form_id ) . '").submit();
            ' );

        $_SESSION['order_id'] = $order_id;
        WC()->session->set( 'ab_order_id', $order_id );

        $form_data_array = [
            'version'     => $version,
            'mid'         => $this->ab_merchantId,
            'lang'        => $lang,
            'deviceCategory' => '0',
            'orderid'     => $order_id . 'at' . wp_date( 'Ymdhisu' ),
            'orderDesc'   => 'Order #' . $order_id,
            'orderAmount' => $order->get_total(),
            'currency'    => $currency,
            'payerEmail'  => $order->get_billing_email(),
            'billCountry' => $country,
            'billState'   => $state_code,
            'billZip'     => $order->get_billing_postcode(),
            'billCity'    => $order->get_billing_city(),
            'billAddress' => $order->get_billing_address_1(),
            'trType'      => $trType,
            'extInstallmentoffset' => null,
            'extInstallmentperiod' => null,
            'confirmUrl'  => get_site_url() . "/?wc-api=WC_alphabank_Gateway&result=success",
            'cancelUrl'   => get_site_url() . "/?wc-api=WC_alphabank_Gateway&result=failure",
            'var2'        => $order_id,
        ];

        if ( $installments > 1 ) {
            $form_data_array['extInstallmentoffset'] = 0;
            $form_data_array['extInstallmentperiod'] = $installments;
        }

        if ( strtolower( $country ) === 'gr' ) {
            unset( $form_data_array['billState'] );
        }

        $form_secret = $this->ab_sharedSecretKey;
        $form_data   = iconv( 'utf-8', 'utf-8//IGNORE', implode( "", $form_data_array ) ) . $form_secret;
        $digest      = $this->calculate_digest( $form_data );

        $html = '<form action="' . esc_url( $post_url ) . '" method="POST" id="' . esc_attr( $form_id ) . '" target="_top" accept-charset="UTF-8">';

        foreach ( $form_data_array as $key => $value ) {
            $html  .= '<input type="hidden" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( iconv( 'utf-8', 'utf-8//IGNORE', (string) $value ) ) . '"/>';
        }

        $html  .= '<input type="hidden" id="digest" name="digest" value="' . esc_attr( $digest ) . '"/>';
        $html  .= '<!-- Button Fallback -->
            <div class="payment_buttons">
                <input type="submit" class="button alt" id="' . esc_attr( $button_id ) . '" value="' . esc_attr__( 'Pay via Alpha Bank', 'woo-alpha-bank-payment-gateway' ) . '" /> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . esc_html__( 'Cancel order &amp; restore cart', 'woo-alpha-bank-payment-gateway' ) . '</a>
            </div>';
        $html  .= '</form>';

        return $html;
    }

    /**
     * @param string $input
     * @return string
     */
    protected function calculate_digest( $input ) {
        return base64_encode( hash( 'sha256', ( $input ), true ) );
    }

    /**
     * @param int $order_id
     * @return array
     */
    public function process_payment( $order_id ) {
        $order  = new \WC_Order( $order_id );
        $doseis_key = $this->id . '-card-doseis';
        $doseis     = isset( $_POST[ $doseis_key ] ) ? absint( wp_unslash( $_POST[ $doseis_key ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout or Store API nonce before it calls process_payment().

        if ( $doseis > 0 ) {
            $this->generic_add_meta( $order_id, '_doseis', $doseis );
        }

        return array(
            'result'   => 'success',
            'redirect' => add_query_arg( 'order-pay', $order->get_id(), add_query_arg( 'key', $order->get_order_key(), wc_get_page_permalink( 'checkout' ) ) ),
        );
    }

    /**
     * Authenticate the bank's return POST and update the order it names.
     *
     * Runs on the public woocommerce_api_wc_alphabank_gateway endpoint, so the request is untrusted
     * until authenticate_response() accepts it. A rejected request changes no order: it gets a generic
     * notice and a redirect to checkout. An accepted request may change the order status, add order
     * notes, write _alphabank_message meta and empty the cart.
     *
     * @return void
     */
    public function check_alphabank_response() {
        $response = $this->read_response();
        $this->safe_log( 'Alpha Bank response', $response );

        $order = $this->authenticate_response( $response );
        if ( ! $order ) {
            wc_add_notice( __( 'A technical problem occurred. <br />The transaction wasn\'t successful, payment wasn\'t received.', 'woo-alpha-bank-payment-gateway' ), 'error' );
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }

        $redirect_url = $this->get_response_redirect_url( $order );

        if ( ! $order->needs_payment() ) {
            $this->safe_log( 'Alpha Bank: response ignored, the order no longer needs payment', array( 'order_id' => $order->get_id(), 'status' => $response['status'] ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        $status     = sanitize_text_field( $response['status'] );
        $message    = sanitize_text_field( $response['message'] );
        $paymentRef = sanitize_text_field( $response['paymentRef'] );

        if ( ( $status === 'CAPTURED' || $status === 'AUTHORIZED' ) && ! $this->response_matches_order( $response, $order ) ) {
            $order->update_status(
                'on-hold',
                sprintf(
                    /* translators: 1: amount reported by the bank, 2: currency, 3: merchant ID, 4: bank transaction ID */
                    __( 'Alpha Bank reported a payment that does not match this order (amount %1$s %2$s, merchant ID %3$s, transaction ID %4$s). Check it in the Alpha Bank back office before fulfilling the order.', 'woo-alpha-bank-payment-gateway' ),
                    sanitize_text_field( $response['orderAmount'] ),
                    sanitize_text_field( $response['currency'] ),
                    sanitize_text_field( $response['mid'] ),
                    $paymentRef
                )
            );
            wp_safe_redirect( $redirect_url );
            exit;
        }

        if ( $status === 'CAPTURED' || $status === 'AUTHORIZED' ) {
            $order->payment_complete( $paymentRef );

            if ( $order->get_status() === 'processing' ) {
                $order->add_order_note( __( 'Payment Via Alpha Bank<br />Transaction ID: ', 'woo-alpha-bank-payment-gateway' ) . $paymentRef );
                $message = __( 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is currently being processed.', 'woo-alpha-bank-payment-gateway' );

                if ( $this->ab_order_note === 'yes' ) {
                    $order->add_order_note( __( 'Payment Received.<br />Your order is currently being processed.<br />We will be shipping your order to you soon.<br />Alpha Bank Bank ID: ', 'woo-alpha-bank-payment-gateway' ) . $paymentRef, 1 );
                }
            } elseif ( $order->get_status() === 'completed' ) {
                $message = __( 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is now complete.', 'woo-alpha-bank-payment-gateway' );
                if ( $this->ab_order_note === 'yes' ) {
                    $order->add_order_note( __( 'Payment Received.<br />Your order is now complete.<br />Alpha Bank Transaction ID: ', 'woo-alpha-bank-payment-gateway' ) . $paymentRef, 1 );
                }
            }

            $this->updateStatus( $message, 'success', $order );
            WC()->cart->empty_cart();
        } elseif ( $status === 'CANCELED' ) {
            $this->updateStatus( __( 'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment was cancelled.', 'woo-alpha-bank-payment-gateway' ), 'notice', $order, 'failed', 'ERROR ' . $message );
        } elseif ( $status === 'REFUSED' ) {
            $this->updateStatus( __( 'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.', 'woo-alpha-bank-payment-gateway' ), 'error', $order, 'failed', 'REFUSED ' . $message );
        } elseif ( $status === 'ERROR' ) {
            $this->updateStatus( __( 'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.', 'woo-alpha-bank-payment-gateway' ), 'error', $order, 'failed', 'ERROR ' . $message );
        } else {
            $this->updateStatus( __( 'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.', 'woo-alpha-bank-payment-gateway' ), 'error', $order, 'failed', 'Unknown: ' . $message );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * Read the bank's signed fields and digest from the POST body, unslashed but otherwise raw.
     *
     * The digest is computed over the exact values the bank sent, so nothing is sanitized here;
     * callers sanitize each value before storing or displaying it.
     *
     * @return array<string,string> Field name => raw value, '' when absent or not a string.
     */
    protected function read_response() {
        $response = array();
        foreach ( array_merge( static::RESPONSE_DIGEST_FIELDS, array( 'digest' ) ) as $field ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The bank's POST cannot carry a WordPress nonce; authenticate_response() verifies its digest instead. Raw values are required to recompute that digest.
            $value              = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : '';
            $response[ $field ] = is_string( $value ) ? $value : '';
        }

        return $response;
    }

    /**
     * Return the order a bank response belongs to, or false when it must not touch any order.
     *
     * Accepts only when a shared secret is configured, version and mid are digits, mid equals the
     * saved merchant ID, orderid has the "{id}at{timestamp}" form that generate_form() sends, the
     * digest matches in constant time, the order exists, and it was placed with one of
     * OWN_PAYMENT_METHODS. Logs the reason for a rejection when debug logging is on.
     *
     * @param array<string,string> $response Output of read_response().
     * @return \WC_Order|false
     */
    protected function authenticate_response( array $response ) {
        if ( $this->ab_sharedSecretKey === '' ) {
            $this->safe_log( 'Alpha Bank: response rejected, no shared secret is configured' );
            return false;
        }

        // The request digest (printed on the order-pay page) and the response digest are both
        // SHA-256 of plain concatenated fields plus the same shared secret, with no separators. A
        // shopper controls part of the request fields (billing address, city, zip, email), so they
        // could place a response-shaped string in them and replay the page's digest as a "signed"
        // response. Accept only the shape a genuine response has: digits for version and mid, then
        // "{digits}at{digits}" for orderid. A genuine response concatenation therefore runs digits
        // then "at", while every request concatenation runs digits (version, mid) then the language
        // code ("el" or "en"), so the two can never be equal.
        foreach ( array( 'version', 'mid' ) as $digits_only_field ) {
            if ( 1 !== preg_match( '/^\d+\z/', $response[ $digits_only_field ] ) ) {
                $this->safe_log( 'Alpha Bank: response rejected, unexpected ' . $digits_only_field . ' format' );
                return false;
            }
        }

        // Without separators, digits can also move between mid and orderid without changing the
        // digest: a genuine response for order 1234 re-sent as mid "{mid}12", orderid "34at…" would
        // name order 34. Pinning mid to the saved merchant ID fixes where orderid starts.
        if ( $response['mid'] !== $this->ab_merchantId ) {
            $this->safe_log( 'Alpha Bank: response rejected, merchant ID does not match the saved one' );
            return false;
        }

        if ( 1 !== preg_match( '/^(\d+)at\d+\z/', $response['orderid'], $matches ) ) {
            $this->safe_log( 'Alpha Bank: response rejected, unexpected orderid format' );
            return false;
        }

        $concat = '';
        foreach ( static::RESPONSE_DIGEST_FIELDS as $field ) {
            $concat .= $response[ $field ];
        }
        $computed_digest = $this->calculate_digest( iconv( 'UTF-8', 'UTF-8//IGNORE', $concat ) . $this->ab_sharedSecretKey );

        if ( ! hash_equals( $computed_digest, $response['digest'] ) ) {
            $this->safe_log( 'Alpha Bank: response rejected, digest mismatch' );
            return false;
        }

        $order = wc_get_order( absint( $matches[1] ) );
        if ( ! $order || ! in_array( $order->get_payment_method(), static::OWN_PAYMENT_METHODS, true ) ) {
            $this->safe_log( 'Alpha Bank: response rejected, order not found or not paid with Alpha Bank' );
            return false;
        }

        return $order;
    }

    /**
     * Check that a signed response is for this merchant, in the plugin's currency, for the order total.
     *
     * @param array<string,string> $response Output of read_response().
     * @param \WC_Order            $order    Order returned by authenticate_response().
     * @return bool
     */
    protected function response_matches_order( array $response, \WC_Order $order ) {
        return $response['mid'] === $this->ab_merchantId
            && $response['currency'] === static::CURRENCY
            && wc_format_decimal( $response['orderAmount'], 2 ) === wc_format_decimal( $order->get_total(), 2 );
    }

    /**
     * Resolve where the shopper lands after the bank returns: the configured page, else the thank-you page.
     *
     * @param \WC_Order $order Order the response belongs to.
     * @return string
     */
    protected function get_response_redirect_url( \WC_Order $order ) {
        return in_array( (string) $this->redirect_page_id, array( '', '0', '-1' ), true ) ? $this->get_return_url( $order ) : get_permalink( $this->redirect_page_id );
    }

    /**
     * @param int $orderid
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function generic_add_meta( $orderid, $key, $value ) {
        $order = new \WC_Order( $orderid );
        $order->add_meta_data( $key, $value, true );
        $order->save_meta_data();
    }

    /**
     * @return void
     */
    public function validate_fields() {
        $requiredFields = [
            'billing_email'     => __( 'E-mail address', 'woo-alpha-bank-payment-gateway' ),
            'billing_city'      => __( 'Billing town/city', 'woo-alpha-bank-payment-gateway' ),
            'billing_country'   => __( 'Billing country / region', 'woo-alpha-bank-payment-gateway' ),
            'billing_address_1' => __( 'Billing street address', 'woo-alpha-bank-payment-gateway' ),
            'billing_postcode'  => __( 'Billing postcode / ZIP', 'woo-alpha-bank-payment-gateway' ),
        ];

        $validation_failed = false;

        foreach ($requiredFields as $field => $info) {
            $raw_value = isset( $_POST[ $field ] ) ? wp_unslash( $_POST[ $field ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WooCommerce verifies the checkout or Store API nonce before it calls validate_fields(); the value is only tested for emptiness and sanitized on the next line.
            $value     = is_string( $raw_value ) ? trim( sanitize_text_field( $raw_value ) ) : '';

            if ( '' === $value ) {
                if ( defined( 'REST_REQUEST' ) ) {
                    return false;
                }

                wc_add_notice(
                    /* translators: %s: name of the required checkout field */
                    sprintf( __( '%s is a mandatory field!', 'woo-alpha-bank-payment-gateway' ), $info ),
                    'error'
                );

                $validation_failed = true;
            }
        }

        return ! $validation_failed;
    }

    /**
     * @return array
     */
    protected function ab_get_installments() {
        for ( $i = 1; $i <= 24; $i++ ) {
            $installment_list[ $i ] = $i;
        }

        return $installment_list;
    }

    /**
     * @param string $client_message Message shown to the shopper, already translated by the caller.
     * @param string $message_type
     * @param WC_Order $order
     * @param string $status
     * @param string $message
     * @return void
     */
    protected function updateStatus( $client_message, $message_type, $order, $status = null, $message = null ) {
        $ab_message = array( 'message' => $client_message, 'message_type' => $message_type );
        $this->generic_add_meta( $order->get_id(), '_alphabank_message', $ab_message );

        if ( $status !== null ) {
            $order->update_status( $status, $message );
        }
    }

    public static function encrypt( $message, $key ) {
        if ( mb_strlen( $key, '8bit' ) !== static::ENCRYPTION_KEY_LENGTH ) {
            throw new \Exception( esc_html( sprintf( 'Needs a %d-bit key! Got %d bytes', static::ENCRYPTION_KEY_LENGTH * 8, mb_strlen( $key, '8bit' ) ) ) );
        }
        $ivsize = openssl_cipher_iv_length( static::ENCRYPTION_METHOD );
        $iv     = openssl_random_pseudo_bytes( $ivsize );

        $ciphertext = openssl_encrypt(
            $message,
            static::ENCRYPTION_METHOD,
            $key,
            0,
            $iv
        );

        return $iv . $ciphertext;
    }

    public static function decrypt( $message, $key ) {
        if ( mb_strlen( $key, '8bit' ) !== static::ENCRYPTION_KEY_LENGTH ) {
            throw new \Exception( esc_html( sprintf( 'Needs a %d-bit key! Got %d bytes', static::ENCRYPTION_KEY_LENGTH * 8, mb_strlen( $key, '8bit' ) ) ) );
        }
        $ivsize     = openssl_cipher_iv_length( static::ENCRYPTION_METHOD );
        $iv         = mb_substr( $message, 0, $ivsize, '8bit' );
        $ciphertext = mb_substr( $message, $ivsize, null, '8bit' );

        return openssl_decrypt(
            $ciphertext,
            static::ENCRYPTION_METHOD,
            $key,
            0,
            $iv
        );
    }

    public function get_option( $key, $empty_value = null ) {
        $option_value = parent::get_option( $key, $empty_value );
        if ( $key == 'ab_sharedSecretKey' ) {
            $option_value = $this->decrypt_stored_secret( $option_value );
        }
        return $option_value;
    }

    /**
     * Decrypt the shared secret as it is stored in the card gateway's settings.
     *
     * @param mixed $stored Stored (base64, encrypted) value.
     * @return mixed The plain secret; '' when it cannot be decoded or decrypted; $stored unchanged when empty.
     */
    protected function decrypt_stored_secret( $stored ) {
        if ( empty( $stored ) ) {
            return $stored;
        }

        $decoded = base64_decode( $stored, true );
        if ( $decoded === false ) {
            return '';
        }

        $decrypted = $this->decrypt( $decoded, substr( NONCE_KEY, 0, static::ENCRYPTION_KEY_LENGTH ) );

        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return string
     * @throws Exception
     */
    public function validate_ab_sharedSecretKey_field( $key, $value ) {
        $encrypted = self::encrypt( $value, substr( NONCE_KEY, 0, static::ENCRYPTION_KEY_LENGTH ) );
        return base64_encode( $encrypted );
    }

    /**
     * Write a debug line to WooCommerce > Status > Logs, source "alphabank", when debug logging is on.
     *
     * Data goes through sanitize_log_data() first, so the digest and the shared secret never reach the log.
     *
     * @param string $message Log line.
     * @param mixed  $data    Optional context, printed below the message after redaction.
     * @return void
     */
    private function safe_log( $message, $data = null ) {
        if ( $this->ab_enable_log !== 'yes' ) {
            return;
        }

        $line = $message;
        if ( $data !== null ) {
            $line .= "\n" . wc_print_r( $this->sanitize_log_data( $data ), true );
        }

        wc_get_logger()->debug( $line, array( 'source' => 'alphabank' ) );
    }

    /**
     * Remove sensitive information from data before logging
     *
     * @param mixed $data
     * @return mixed
     */
    private function sanitize_log_data( $data ) {
        if ( is_string( $data ) ) {
            $data = str_replace( $this->ab_sharedSecretKey, '[REDACTED]', $data );
        } elseif ( is_array( $data ) ) {
            $sensitive_keys = [ 'ab_sharedSecretKey', 'sharedSecretKey', 'digest' ];
            foreach ($data as $key => $value) {
                if ( in_array( $key, $sensitive_keys ) ) {
                    $data[ $key ] = '[REDACTED]';
                } else {
                    $data[ $key ] = $this->sanitize_log_data( $value );
                }
            }
        }

        return $data;
    }
}
