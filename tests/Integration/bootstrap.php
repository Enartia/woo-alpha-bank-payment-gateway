<?php
/**
 * Bootstrap for the integration tests: WordPress, WooCommerce and this plugin, on a test database.
 *
 * `composer install` puts WordPress in .wp-tests/wordpress and WooCommerce in .wp-tests/plugins.
 */

$alphabank_root = dirname( __DIR__, 2 );

require $alphabank_root . '/vendor/autoload.php';

$alphabank_db_name = getenv( 'WP_TESTS_DB_NAME' ) ?: 'enartia_tests_alphabank';

// The WordPress test suite drops every table in its database on each run.
if ( false === strpos( $alphabank_db_name, 'test' ) ) {
    fwrite( STDERR, "Refusing to run: the database name '{$alphabank_db_name}' does not contain 'test'.\n" );
    exit( 1 );
}

mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
try {
    $alphabank_db = mysqli_connect(
        getenv( 'WP_TESTS_DB_HOST' ) ?: '127.0.0.1',
        getenv( 'WP_TESTS_DB_USER' ) ?: 'root',
        getenv( 'WP_TESTS_DB_PASSWORD' ) ?: ''
    );
    mysqli_query( $alphabank_db, 'CREATE DATABASE IF NOT EXISTS `' . mysqli_real_escape_string( $alphabank_db, $alphabank_db_name ) . '`' );
    mysqli_close( $alphabank_db );
} catch ( mysqli_sql_exception $alphabank_exception ) {
    fwrite( STDERR, 'Cannot prepare the test database: ' . $alphabank_exception->getMessage() . ". Start MySQL or set the WP_TESTS_DB_* variables.\n" );
    exit( 1 );
}

define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $alphabank_root . '/vendor/yoast/phpunit-polyfills' );

$alphabank_tests_dir = getenv( 'WP_PHPUNIT__DIR' );

require_once $alphabank_tests_dir . '/includes/functions.php';

tests_add_filter(
    'muplugins_loaded',
    static function () use ( $alphabank_root ) {
        require $alphabank_root . '/.wp-tests/plugins/woocommerce/woocommerce.php';
        require $alphabank_root . '/wooshop-alpha.php';
    }
);

// The test suite installs a bare WordPress on every run, so the WooCommerce tables and roles are created here.
tests_add_filter(
    'setup_theme',
    static function () {
        WC_Install::install();
        $GLOBALS['wp_roles'] = null;
        wp_roles();
    }
);

require $alphabank_tests_dir . '/includes/bootstrap.php';
