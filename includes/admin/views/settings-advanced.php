<?php
/**
 * Advanced tab.
 *
 * @package Deliz\AI\Advisor\Admin
 * @var array<string, mixed> $settings
 */

defined( 'ABSPATH' ) || exit;

$adv = $settings['advanced'];

$clear_cache_url = wp_nonce_url(
	add_query_arg(
		array(
			'page'        => \Deliz\AI\Advisor\Admin\Admin::MENU_SLUG,
			'tab'         => 'advanced',
			'deliz_action' => 'clear_cache',
		),
		admin_url( 'admin.php' )
	),
	'deliz_ai_admin_action'
);
$clear_rate_url = wp_nonce_url(
	add_query_arg(
		array(
			'page'        => \Deliz\AI\Advisor\Admin\Admin::MENU_SLUG,
			'tab'         => 'advanced',
			'deliz_action' => 'clear_rate',
		),
		admin_url( 'admin.php' )
	),
	'deliz_ai_admin_action'
);
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Debug mode', 'deliz-ai-advisor' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="advanced[debug_mode]" value="1" <?php checked( ! empty( $adv['debug_mode'] ) ); ?>>
				<?php esc_html_e( 'Log extra info to the browser console', 'deliz-ai-advisor' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Log all requests', 'deliz-ai-advisor' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="advanced[log_all_requests]" value="1" <?php checked( ! empty( $adv['log_all_requests'] ) ); ?>>
				<?php esc_html_e( 'Store every conversation in the DB (recommended)', 'deliz-ai-advisor' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Anonymize IPs after', 'deliz-ai-advisor' ); ?></th>
		<td>
			<input type="number" name="advanced[anonymize_ips_after_days]" value="<?php echo esc_attr( $adv['anonymize_ips_after_days'] ); ?>" min="0" max="3650" class="small-text"> <?php esc_html_e( 'days', 'deliz-ai-advisor' ); ?>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Auto-delete logs after', 'deliz-ai-advisor' ); ?></th>
		<td>
			<input type="number" name="advanced[delete_logs_after_days]" value="<?php echo esc_attr( $adv['delete_logs_after_days'] ); ?>" min="0" max="3650" class="small-text"> <?php esc_html_e( 'days', 'deliz-ai-advisor' ); ?>
			<p class="description"><?php esc_html_e( '0 = keep forever.', 'deliz-ai-advisor' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Delete data on uninstall', 'deliz-ai-advisor' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="advanced[delete_data_on_uninstall]" value="1" <?php checked( ! empty( $adv['delete_data_on_uninstall'] ) ); ?>>
				<?php esc_html_e( 'Drop all tables and options if the plugin is uninstalled', 'deliz-ai-advisor' ); ?>
			</label>
		</td>
	</tr>
</table>

<h2><?php esc_html_e( 'Maintenance', 'deliz-ai-advisor' ); ?></h2>
<p>
	<a class="button" href="<?php echo esc_url( $clear_cache_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Clear response cache?', 'deliz-ai-advisor' ) ); ?>');">
		<?php esc_html_e( 'Clear response cache', 'deliz-ai-advisor' ); ?>
	</a>
	<a class="button" href="<?php echo esc_url( $clear_rate_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Clear all rate limits and today\'s spend counter?', 'deliz-ai-advisor' ) ); ?>');">
		<?php esc_html_e( 'Clear rate limits', 'deliz-ai-advisor' ); ?>
	</a>
</p>
