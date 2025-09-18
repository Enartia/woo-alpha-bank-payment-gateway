<?php

/**
 * @return void
 */
function alphabank_message()
{
    $order_id = absint(get_query_var('order-received'));
    $order = new WC_Order($order_id);
    if (method_exists($order, 'get_payment_method')) {
        $payment_method = $order->get_payment_method();
    } else {
        $payment_method = $order->payment_method;
    }
    if ('alphabank_gateway' === $payment_method && is_order_received_page()) {
        $alphabank_message = $order->get_meta('_alphabank_message');

        if (!empty($alphabank_message)) {
            $message = $alphabank_message['message'];
            $message_type = $alphabank_message['message_type'];
            if (method_exists($order, 'delete_meta_data')) {
                $order->delete_meta_data('_alphabank_message');
                $order->save_meta_data();
            } else {
                delete_post_meta($order_id, '_alphabank_message');
            }
            wc_add_notice($message, $message_type);
        }
    }
}

/**
 * @param array $methods
 * @return array
 */
function woocommerce_add_alphabank_gateway($methods)
{
    $methods[] = 'WC_AlphaBank_Gateway';
    $methods[] = 'WC_AlphaBank_Gateway_Masterpass';
    return $methods;
}