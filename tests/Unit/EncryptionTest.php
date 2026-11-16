<?php

namespace Papaki\AlphaBank\WooCommerce\Tests\Unit;

use Exception;
use Papaki\AlphaBank\WooCommerce\WC_AlphaBank_Gateway_Base;
use PHPUnit\Framework\TestCase;

/**
 * The shared secret is stored encrypted with the first 32 bytes of NONCE_KEY.
 */
class EncryptionTest extends TestCase {
    private const KEY       = '0123456789abcdef0123456789abcdef';
    private const OTHER_KEY = 'fedcba9876543210fedcba9876543210';
    private const SECRET    = 'shared-secret-from-the-bank';

    public function test_decrypt_returns_the_secret_that_was_encrypted(): void {
        $encrypted = WC_AlphaBank_Gateway_Base::encrypt( self::SECRET, self::KEY );

        $this->assertSame( self::SECRET, WC_AlphaBank_Gateway_Base::decrypt( $encrypted, self::KEY ) );
    }

    public function test_encrypted_value_does_not_contain_the_secret(): void {
        $encrypted = WC_AlphaBank_Gateway_Base::encrypt( self::SECRET, self::KEY );

        $this->assertStringNotContainsString( self::SECRET, $encrypted );
    }

    public function test_encrypting_the_same_secret_twice_gives_different_values(): void {
        $first  = WC_AlphaBank_Gateway_Base::encrypt( self::SECRET, self::KEY );
        $second = WC_AlphaBank_Gateway_Base::encrypt( self::SECRET, self::KEY );

        $this->assertNotSame( $first, $second );
    }

    public function test_decrypt_with_another_key_does_not_return_the_secret(): void {
        $encrypted = WC_AlphaBank_Gateway_Base::encrypt( self::SECRET, self::KEY );

        $this->assertNotSame( self::SECRET, WC_AlphaBank_Gateway_Base::decrypt( $encrypted, self::OTHER_KEY ) );
    }

    public function test_encrypt_rejects_a_key_that_is_not_32_bytes(): void {
        $this->expectException( Exception::class );

        WC_AlphaBank_Gateway_Base::encrypt( self::SECRET, 'too-short' );
    }

    public function test_decrypt_rejects_a_key_that_is_not_32_bytes(): void {
        $this->expectException( Exception::class );

        WC_AlphaBank_Gateway_Base::decrypt( 'anything', 'too-short' );
    }

    public function test_secret_saved_through_the_settings_field_is_read_back_as_plain_text(): void {
        $gateway = new WC_AlphaBank_Gateway_Base();

        $gateway->settings['ab_sharedSecretKey'] = $gateway->validate_ab_sharedSecretKey_field( 'ab_sharedSecretKey', self::SECRET );

        $this->assertNotSame( self::SECRET, $gateway->settings['ab_sharedSecretKey'] );
        $this->assertSame( self::SECRET, $gateway->get_option( 'ab_sharedSecretKey' ) );
    }

    public function test_stored_secret_that_cannot_be_decrypted_reads_as_empty(): void {
        $gateway = new WC_AlphaBank_Gateway_Base();

        $gateway->settings['ab_sharedSecretKey'] = 'not base64 and not encrypted!';

        $this->assertSame( '', $gateway->get_option( 'ab_sharedSecretKey' ) );
    }
}
