<?php
/**
 * WordPress configuration for the integration tests.
 *
 * The test run drops and recreates every table in this database, so it must be a
 * database that holds nothing else. Override the defaults with the WP_TESTS_DB_* variables.
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/.wp-tests/wordpress/' );

define( 'DB_NAME', getenv( 'WP_TESTS_DB_NAME' ) ?: 'enartia_tests_alphabank' );
define( 'DB_USER', getenv( 'WP_TESTS_DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'WP_TESTS_DB_PASSWORD' ) ?: '' );
define( 'DB_HOST', getenv( 'WP_TESTS_DB_HOST' ) ?: '127.0.0.1' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

// Fixed test values, not secrets. NONCE_KEY must be at least 32 bytes: the gateway encrypts with it.
define( 'AUTH_KEY', 'integration-test-auth-key-0123456789-abcdefghijklmnopqrstuvwxyz' );
define( 'SECURE_AUTH_KEY', 'integration-test-secure-auth-key-0123456789-abcdefghijklmnopq' );
define( 'LOGGED_IN_KEY', 'integration-test-logged-in-key-0123456789-abcdefghijklmnopqrst' );
define( 'NONCE_KEY', 'integration-test-nonce-key-0123456789-abcdefghijklmnopqrstuvwx' );
define( 'AUTH_SALT', 'integration-test-auth-salt-0123456789-abcdefghijklmnopqrstuvwx' );
define( 'SECURE_AUTH_SALT', 'integration-test-secure-auth-salt-0123456789-abcdefghijklmnop' );
define( 'LOGGED_IN_SALT', 'integration-test-logged-in-salt-0123456789-abcdefghijklmnopqr' );
define( 'NONCE_SALT', 'integration-test-nonce-salt-0123456789-abcdefghijklmnopqrstuvw' );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Alpha Bank gateway tests' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );

define( 'WP_DEBUG', true );
