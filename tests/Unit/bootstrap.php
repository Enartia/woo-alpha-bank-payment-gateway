<?php
/**
 * Bootstrap for the unit tests. WordPress and WooCommerce are not loaded.
 */

// The plugin classes exit when ABSPATH is not defined.
define( 'ABSPATH', __DIR__ . '/' );

// The gateways encrypt the shared secret with the first 32 bytes of NONCE_KEY.
define( 'NONCE_KEY', 'unit-test-nonce-key-0123456789-abcdefghijklmnopqrstuvwxyz' );

require __DIR__ . '/stubs/WC_Payment_Gateway.php';
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
