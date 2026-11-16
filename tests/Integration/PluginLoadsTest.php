<?php

namespace Papaki\AlphaBank\WooCommerce\Tests\Integration;

use Papaki\AlphaBank\WooCommerce\WC_AlphaBank_Gateway;
use WP_UnitTestCase;

/**
 * The plugin loads next to WooCommerce and registers its gateways.
 *
 * WP_UnitTestCase fails a test when the code under test calls a deprecated WordPress or
 * WooCommerce function, or triggers a "doing it wrong" notice. Every integration test
 * therefore also checks that the plugin stays quiet in the debug log.
 */
class PluginLoadsTest extends WP_UnitTestCase {
    private const SETTINGS_OPTION = 'woocommerce_alphabank_gateway_settings';

    public function test_both_gateways_are_registered_with_woocommerce(): void {
        $gateways = WC()->payment_gateways()->payment_gateways();

        $this->assertArrayHasKey( 'alphabank_gateway', $gateways );
        $this->assertArrayHasKey( 'alphabank_masterpass', $gateways );
    }

    public function test_transactions_table_is_created(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'alphabank_transactions';

        $this->assertSame( $table, $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
    }

    public function test_gateway_is_hidden_at_checkout_without_merchant_id_and_secret(): void {
        delete_option( self::SETTINGS_OPTION );

        $gateway = new WC_AlphaBank_Gateway();

        $this->assertFalse( $gateway->is_available() );
    }

    public function test_gateway_is_available_at_checkout_once_merchant_id_and_secret_are_saved(): void {
        $gateway = new WC_AlphaBank_Gateway();
        update_option(
            self::SETTINGS_OPTION,
            array_merge(
                $gateway->settings,
                array(
                    'enabled'            => 'yes',
                    'ab_merchantId'      => '9990001',
                    'ab_sharedSecretKey' => $gateway->validate_ab_sharedSecretKey_field( 'ab_sharedSecretKey', 'test-shared-secret' ),
                )
            )
        );

        $configured_gateway = new WC_AlphaBank_Gateway();

        $this->assertTrue( $configured_gateway->is_available() );
    }

    public function test_saved_secret_is_not_stored_as_plain_text(): void {
        $gateway = new WC_AlphaBank_Gateway();
        update_option(
            self::SETTINGS_OPTION,
            array_merge(
                $gateway->settings,
                array(
                    'ab_sharedSecretKey' => $gateway->validate_ab_sharedSecretKey_field( 'ab_sharedSecretKey', 'test-shared-secret' ),
                )
            )
        );

        $stored = get_option( self::SETTINGS_OPTION );

        $this->assertNotSame( 'test-shared-secret', $stored['ab_sharedSecretKey'] );
        $this->assertSame( 'test-shared-secret', ( new WC_AlphaBank_Gateway() )->get_option( 'ab_sharedSecretKey' ) );
    }
}
