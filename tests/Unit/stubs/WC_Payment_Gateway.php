<?php
/**
 * Stand-in for WooCommerce's WC_Payment_Gateway, so the gateway classes can be loaded
 * without WooCommerce. It holds only what the unit tests use.
 */

// phpcs:ignore Generic.Classes.DuplicateClassName.Found -- Deliberate stand-in for the WooCommerce class.
class WC_Payment_Gateway {
    /**
     * Saved settings, as WooCommerce would read them from the options table.
     *
     * @var array<string,mixed>
     */
    public $settings = array();

    /**
     * @var bool
     */
    public $has_fields = false;

    /**
     * @param string $key
     * @param mixed  $empty_value
     * @return mixed
     */
    public function get_option( $key, $empty_value = null ) {
        return $this->settings[ $key ] ?? $empty_value;
    }
}
