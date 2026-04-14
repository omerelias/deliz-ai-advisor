<?php
/**
 * Statistics admin page.
 *
 * @package Deliz\AI\Advisor\Admin
 */

namespace Deliz\AI\Advisor\Admin;

defined( 'ABSPATH' ) || exit;

class StatsPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$range = isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : '7d';
		if ( ! in_array( $range, array( 'today', '7d', '30d', '90d', 'all' ), true ) ) {
			$range = '7d';
		}

		$stats = $this->compute( $range );

		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
			array(),
			'4.4.1',
			true
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Deliz AI — Statistics', 'deliz-ai-advisor' ); ?></h1>

			<p class="subsubsub" style="margin: 10px 0 20px;">
				<?php foreach ( array( 'today' => 'Today', '7d' => '7 days', '30d' => '30 days', '90d' => '90 days', 'all' => 'All time' ) as $k => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => Admin::MENU_SLUG . '-stats', 'range' => $k ), admin_url( 'admin.php' ) ) ); ?>"
					   class="<?php echo $range === $k ? 'current' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
					<?php if ( $k !== 'all' ) echo ' | '; ?>
				<?php endforeach; ?>
			</p>

			<div class="deliz-ai-kpi-grid">
				<?php
				$this->kpi_card( __( 'Conversations', 'deliz-ai-advisor' ), number_format_i18n( $stats['conversations'] ), '💬' );
				$this->kpi_card( __( 'Messages', 'deliz-ai-advisor' ), number_format_i18n( $stats['messages'] ), '🗣️' );
				$this->kpi_card( __( 'Unique Visitors', 'deliz-ai-advisor' ), number_format_i18n( $stats['unique_visitors'] ), '👥' );
				$this->kpi_card( __( 'Conversion Rate', 'deliz-ai-advisor' ), number_format_i18n( $stats['conversion_rate'], 1 ) . '%', '🛒' );
				$this->kpi_card( __( 'Revenue Attributed', 'deliz-ai-advisor' ), '$' . number_format_i18n( $stats['revenue_attributed'], 2 ), '💰' );
				$this->kpi_card( __( 'Cache Hit Rate', 'deliz-ai-advisor' ), number_format_i18n( $stats['cache_hit_rate'], 1 ) . '%', '🧠' );
				$this->kpi_card( __( 'Total Cost', 'deliz-ai-advisor' ), '$' . number_format_i18n( $stats['total_cost'], 4 ), '💵' );
				$this->kpi_card( __( 'Feedback', 'deliz-ai-advisor' ), $stats['feedback_up'] . ' 👍 / ' . $stats['feedback_down'] . ' 👎', '📊' );
				?>
			</div>

			<h2 style="margin-top:32px"><?php esc_html_e( 'Activity', 'deliz-ai-advisor' ); ?></h2>
			<div style="background:#fff;padding:16px;border:1px solid #dcdcde;border-radius:8px;max-width:900px">
				<canvas id="deliz-activity-chart" height="110"></canvas>
			</div>

			<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:900px;margin-top:24px">
				<div style="background:#fff;padding:16px;border:1px solid #dcdcde;border-radius:8px">
					<h3 style="margin-top:0"><?php esc_html_e( 'Language Distribution', 'deliz-ai-advisor' ); ?></h3>
					<canvas id="deliz-lang-chart" height="200"></canvas>
				</div>
				<div style="background:#fff;padding:16px;border:1px solid #dcdcde;border-radius:8px">
					<h3 style="margin-top:0"><?php esc_html_e( 'Top 10 Products Discussed', 'deliz-ai-advisor' ); ?></h3>
					<?php if ( empty( $stats['top_products'] ) ) : ?>
						<p><em><?php esc_html_e( 'No data yet.', 'deliz-ai-advisor' ); ?></em></p>
					<?php else : ?>
						<ol style="padding-inline-start:20px;margin:0">
							<?php foreach ( $stats['top_products'] as $p ) : ?>
								<li style="margin-bottom:4px"><strong><?php echo esc_html( $p['name'] ); ?></strong> — <?php echo (int) $p['count']; ?></li>
							<?php endforeach; ?>
						</ol>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<style>
		.deliz-ai-kpi-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
			gap: 12px;
			margin: 16px 0;
			max-width: 900px;
		}
		.deliz-ai-kpi {
			background: #fff;
			border: 1px solid #dcdcde;
			border-radius: 8px;
			padding: 14px 16px;
		}
		.deliz-ai-kpi__label {
			font-size: 12px;
			color: #50575e;
			text-transform: uppercase;
			letter-spacing: .04em;
		}
		.deliz-ai-kpi__value {
			font-size: 24px;
			font-weight: 600;
			margin-top: 6px;
			color: #1f2937;
		}
		.deliz-ai-kpi__icon {
			float: right;
			font-size: 22px;
			opacity: .65;
		}
		</style>

		<script>
		document.addEventListener('DOMContentLoaded', function () {
			if (typeof Chart === 'undefined') return;

			const activityData = <?php echo wp_json_encode( $stats['activity_chart'] ); ?>;
			const langData = <?php echo wp_json_encode( $stats['language_chart'] ); ?>;

			const activityEl = document.getElementById('deliz-activity-chart');
			if (activityEl && activityData.labels.length) {
				new Chart(activityEl, {
					type: 'line',
					data: {
						labels: activityData.labels,
						datasets: [{
							label: 'Conversations',
							data: activityData.values,
							borderColor: '#7c3aed',
							backgroundColor: 'rgba(124,58,237,0.1)',
							fill: true,
							tension: 0.3,
						}]
					},
					options: { responsive: true, plugins: { legend: { display: false } } }
				});
			}

			const langEl = document.getElementById('deliz-lang-chart');
			if (langEl && langData.labels.length) {
				new Chart(langEl, {
					type: 'doughnut',
					data: {
						labels: langData.labels,
						datasets: [{
							data: langData.values,
							backgroundColor: ['#7c3aed', '#f59e0b', '#10b981', '#3b82f6'],
						}]
					},
					options: { responsive: true }
				});
			}
		});
		</script>
		<?php
	}

	private function kpi_card( string $label, string $value, string $icon ): void {
		echo '<div class="deliz-ai-kpi">';
		echo '<div class="deliz-ai-kpi__icon">' . esc_html( $icon ) . '</div>';
		echo '<div class="deliz-ai-kpi__label">' . esc_html( $label ) . '</div>';
		echo '<div class="deliz-ai-kpi__value">' . esc_html( $value ) . '</div>';
		echo '</div>';
	}

	/**
	 * Compute all stats for a range.
	 *
	 * @return array<string,mixed>
	 */
	private function compute( string $range ): array {
		$cache_key = 'deliz_ai_stats_cache_' . $range;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$conv_table = $wpdb->prefix . 'deliz_ai_conversations';
		$msg_table  = $wpdb->prefix . 'deliz_ai_messages';

		$since_sql = $this->since_sql( $range );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$conv_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$conv_table} WHERE 1=1 {$since_sql}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$msg_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$msg_table} m INNER JOIN {$conv_table} c ON m.conversation_id = c.id WHERE 1=1 " . str_replace( 'started_at', 'c.started_at', $since_sql ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$unique = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT session_id) FROM {$conv_table} WHERE 1=1 {$since_sql}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$converted = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$conv_table} WHERE converted = 1 {$since_sql}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$revenue = (float) $wpdb->get_var(
			"SELECT COALESCE(SUM(CAST(pm.meta_value AS DECIMAL(10,2))), 0)
			 FROM {$conv_table} c
			 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = c.conversion_order_id AND pm.meta_key = '_order_total'
			 WHERE c.converted = 1 " . $since_sql
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_cost = (float) $wpdb->get_var( "SELECT COALESCE(SUM(total_cost_usd), 0) FROM {$conv_table} WHERE 1=1 {$since_sql}" );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$cache_hits = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$msg_table} m
			 INNER JOIN {$conv_table} c ON m.conversation_id = c.id
			 WHERE m.role='assistant' AND m.cache_hit=1 " . str_replace( 'started_at', 'c.started_at', $since_sql )
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$assistant_msgs = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$msg_table} m
			 INNER JOIN {$conv_table} c ON m.conversation_id = c.id
			 WHERE m.role='assistant' " . str_replace( 'started_at', 'c.started_at', $since_sql )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$fb_up = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$msg_table} m
			 INNER JOIN {$conv_table} c ON m.conversation_id = c.id
			 WHERE m.feedback=1 " . str_replace( 'started_at', 'c.started_at', $since_sql )
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$fb_down = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$msg_table} m
			 INNER JOIN {$conv_table} c ON m.conversation_id = c.id
			 WHERE m.feedback=0 " . str_replace( 'started_at', 'c.started_at', $since_sql )
		);

		// Activity by day.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$activity_rows = $wpdb->get_results(
			"SELECT DATE(started_at) AS d, COUNT(*) AS c
			 FROM {$conv_table}
			 WHERE 1=1 {$since_sql}
			 GROUP BY DATE(started_at)
			 ORDER BY d ASC"
		);
		$labels = array();
		$values = array();
		foreach ( $activity_rows as $r ) {
			$labels[] = $r->d;
			$values[] = (int) $r->c;
		}

		// Language distribution.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$lang_rows = $wpdb->get_results(
			"SELECT language, COUNT(*) AS c
			 FROM {$conv_table}
			 WHERE 1=1 {$since_sql}
			 GROUP BY language
			 ORDER BY c DESC"
		);
		$lang_labels = array();
		$lang_values = array();
		foreach ( $lang_rows as $r ) {
			$lang_labels[] = strtoupper( $r->language );
			$lang_values[] = (int) $r->c;
		}

		// Top products.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$top_rows    = $wpdb->get_results(
			"SELECT product_id, COUNT(*) AS c
			 FROM {$conv_table}
			 WHERE 1=1 {$since_sql}
			 GROUP BY product_id
			 ORDER BY c DESC
			 LIMIT 10"
		);
		$top_products = array();
		foreach ( $top_rows as $row ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $row->product_id ) : null;
			$top_products[] = array(
				'name'  => $product ? $product->get_name() : 'ID ' . (int) $row->product_id,
				'count' => (int) $row->c,
			);
		}

		$out = array(
			'range'              => $range,
			'conversations'      => $conv_count,
			'messages'           => $msg_count,
			'unique_visitors'    => $unique,
			'conversion_rate'    => $conv_count ? ( $converted / $conv_count ) * 100 : 0,
			'revenue_attributed' => $revenue,
			'total_cost'         => $total_cost,
			'cache_hit_rate'     => $assistant_msgs ? ( $cache_hits / $assistant_msgs ) * 100 : 0,
			'feedback_up'        => $fb_up,
			'feedback_down'      => $fb_down,
			'activity_chart'     => array( 'labels' => $labels, 'values' => $values ),
			'language_chart'     => array( 'labels' => $lang_labels, 'values' => $lang_values ),
			'top_products'       => $top_products,
		);

		set_transient( $cache_key, $out, 5 * MINUTE_IN_SECONDS );
		return $out;
	}

	/**
	 * SQL fragment like " AND started_at >= '2026-04-07 00:00:00'"  (or "" for all).
	 */
	private function since_sql( string $range ): string {
		$gmt_now = current_time( 'timestamp', true );

		$since = null;
		switch ( $range ) {
			case 'today':
				$since = gmdate( 'Y-m-d 00:00:00', $gmt_now );
				break;
			case '7d':
				$since = gmdate( 'Y-m-d 00:00:00', $gmt_now - 7 * DAY_IN_SECONDS );
				break;
			case '30d':
				$since = gmdate( 'Y-m-d 00:00:00', $gmt_now - 30 * DAY_IN_SECONDS );
				break;
			case '90d':
				$since = gmdate( 'Y-m-d 00:00:00', $gmt_now - 90 * DAY_IN_SECONDS );
				break;
		}

		if ( null === $since ) {
			return '';
		}

		global $wpdb;
		return $wpdb->prepare( ' AND started_at >= %s', $since );
	}
}
