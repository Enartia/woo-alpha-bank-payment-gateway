<?php

namespace Papaki\AlphaBank\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Create and upgrade the plugin's own alphabank_transactions table.
 *
 * Loaded with require_once from the activation hook, before the plugin's autoloader is registered,
 * so this class must not depend on any other plugin class.
 */
class Installer {
    const DB_VERSION        = '1';
    const DB_VERSION_OPTION = 'alphabank_db_version';

    /**
     * Run install() once per DB_VERSION, for sites that updated the plugin without re-activating it.
     *
     * @return void
     */
    public static function maybe_install() {
        if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
            self::install();
        }
    }

    /**
     * Create the transactions table or bring its columns up to date, then store the schema version.
     *
     * Side effects: CREATE or ALTER TABLE through dbDelta(), and an option write.
     *
     * @return void
     */
    public static function install() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $wpdb->prefix . 'alphabank_transactions';

        dbDelta(
            "CREATE TABLE {$table} (
  id int(11) unsigned NOT NULL AUTO_INCREMENT,
  merchantreference varchar(30) NOT NULL,
  reference varchar(100) NOT NULL,
  trans_ticket varchar(100) NOT NULL,
  timestamp datetime DEFAULT NULL,
  PRIMARY KEY  (id)
) {$wpdb->get_charset_collate()};"
        );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }
}
