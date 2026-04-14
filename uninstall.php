<?php
/**
 * Uninstall handler. Drops tables and options only if the user opted in.
 *
 * @package Deliz\AI\Advisor
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings   = get_option( 'deliz_ai_settings', array() );
$opt_in     = isset( $settings['advanced']['delete_data_on_uninstall'] )
	? (bool) $settings['advanced']['delete_data_on_uninstall']
	: false;

if ( ! $opt_in ) {
	return;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'deliz_ai_conversations',
	$wpdb->prefix . 'deliz_ai_messages',
	$wpdb->prefix . 'deliz_ai_cache',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

delete_option( 'deliz_ai_settings' );
delete_option( 'deliz_ai_db_version' );
delete_option( 'deliz_ai_stats_cache' );

// Transients — best effort.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_deliz_ai_%' OR option_name LIKE '_transient_timeout_deliz_ai_%'" );
