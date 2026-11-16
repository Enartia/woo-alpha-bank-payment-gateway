<?php

namespace Papaki\AlphaBank\WooCommerce\Tests\Unit;

use Papaki\AlphaBank\WooCommerce\WC_AlphaBank_Gateway_Base;
use PHPUnit\Framework\TestCase;

/**
 * The digest signs the request to the bank and verifies the bank's response:
 * the SHA-256 hash of the input as raw bytes, base64 encoded.
 */
class DigestTest extends TestCase {
    /**
     * @param string $input
     * @return string
     */
    private function digest( $input ) {
        $gateway = new class() extends WC_AlphaBank_Gateway_Base {
            public function digest( $input ) {
                return $this->calculate_digest( $input );
            }
        };

        return $gateway->digest( $input );
    }

    public function test_digest_matches_the_published_sha256_value_for_abc(): void {
        // SHA-256 test vector for "abc" from FIPS 180-4, as base64 of the raw hash.
        $this->assertSame( 'ungWv48Bz+pBQUDeXa4iI7ADYaOWF3qctBD/YfIAFa0=', $this->digest( 'abc' ) );
    }

    public function test_digest_changes_when_the_secret_at_the_end_changes(): void {
        $fields = '29990001el0EUR';

        $this->assertNotSame( $this->digest( $fields . 'secret-one' ), $this->digest( $fields . 'secret-two' ) );
    }

    public function test_digest_is_44_base64_characters(): void {
        $this->assertMatchesRegularExpression( '/^[A-Za-z0-9+\/]{43}=$/', $this->digest( 'any input' ) );
    }
}
