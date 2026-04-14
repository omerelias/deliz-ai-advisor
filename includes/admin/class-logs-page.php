<?php
/**
 * Conversation log admin page.
 *
 * @package Deliz\AI\Advisor\Admin
 */

namespace Deliz\AI\Advisor\Admin;

use Deliz\AI\Advisor\Services\ConversationLogger;

defined( 'ABSPATH' ) || exit;

class LogsPage {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$conversations_table = $wpdb->prefix . 'deliz_ai_conversations';
		$messages_table      = $wpdb->prefix . 'deliz_ai_messages';

		$view_id = isset( $_GET['conversation'] ) ? absint( $_GET['conversation'] ) : 0;

		echo '<div class="wrap"><h1>' . esc_html__( 'Conversation Log', 'deliz-ai-advisor' ) . '</h1>';

		if ( $view_id ) {
			$this->render_detail( $view_id, $conversations_table, $messages_table );
		} else {
			$this->render_list( $conversations_table );
		}

		echo '</div>';
	}

	private function render_list( string $conversations_table ): void {
		global $wpdb;

		$per_page = 25;
		$page     = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		$offset   = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$conversations_table}" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$conversations_table} ORDER BY started_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No conversations yet.', 'deliz-ai-advisor' ) . '</p>';
			return;
		}

		echo '<table class="wp-list-table widefat striped">';
		echo '<thead><tr>';
		echo '<th>ID</th>';
		echo '<th>' . esc_html__( 'Started', 'deliz-ai-advisor' ) . '</th>';
		echo '<th>' . esc_html__( 'Product', 'deliz-ai-advisor' ) . '</th>';
		echo '<th>' . esc_html__( 'Language', 'deliz-ai-advisor' ) . '</th>';
		echo '<th>' . esc_html__( 'Messages', 'deliz-ai-advisor' ) . '</th>';
		echo '<th>' . esc_html__( 'Tokens (in/out)', 'deliz-ai-advisor' ) . '</th>';
		echo '<th>' . esc_html__( 'Cost', 'deliz-ai-advisor' ) . '</th>';
		echo '<th>' . esc_html__( 'Converted', 'deliz-ai-advisor' ) . '</th>';
		echo '<th></th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( (int) $row->product_id ) : null;
			$product_name = $product ? $product->get_name() : ( 'ID ' . (int) $row->product_id );
			$view_url = add_query_arg(
				array(
					'page'         => Admin::MENU_SLUG . '-logs',
					'conversation' => (int) $row->id,
				),
				admin_url( 'admin.php' )
			);

			echo '<tr>';
			echo '<td>' . (int) $row->id . '</td>';
			echo '<td>' . esc_html( $row->started_at ) . '</td>';
			echo '<td>';
			if ( $product ) {
				echo '<a href="' . esc_url( get_edit_post_link( (int) $row->product_id ) ) . '">' . esc_html( $product_name ) . '</a>';
			} else {
				echo esc_html( $product_name );
			}
			echo '</td>';
			echo '<td>' . esc_html( strtoupper( $row->language ) ) . '</td>';
			echo '<td>' . (int) $row->message_count . '</td>';
			echo '<td>' . (int) $row->total_tokens_in . ' / ' . (int) $row->total_tokens_out . '</td>';
			echo '<td>$' . esc_html( number_format( (float) $row->total_cost_usd, 4 ) ) . '</td>';
			echo '<td>' . ( (int) $row->converted ? '✔' : '—' ) . '</td>';
			echo '<td><a class="button button-small" href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'deliz-ai-advisor' ) . '</a></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		// Pagination.
		$total_pages = (int) ceil( $total / $per_page );
		if ( $total_pages > 1 ) {
			$links = paginate_links(
				array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'current'   => $page,
					'total'     => $total_pages,
					'prev_text' => '«',
					'next_text' => '»',
				)
			);
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
		}
	}

	private function render_detail( int $id, string $conversations_table, string $messages_table ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$conv = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$conversations_table} WHERE id = %d", $id ) );
		if ( ! $conv ) {
			echo '<p>' . esc_html__( 'Conversation not found.', 'deliz-ai-advisor' ) . '</p>';
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$messages_table} WHERE conversation_id = %d ORDER BY id ASC",
				$id
			)
		);

		$back = add_query_arg( array( 'page' => Admin::MENU_SLUG . '-logs' ), admin_url( 'admin.php' ) );
		echo '<p><a href="' . esc_url( $back ) . '" class="button">← ' . esc_html__( 'Back to list', 'deliz-ai-advisor' ) . '</a></p>';

		echo '<h2>' . esc_html__( 'Conversation', 'deliz-ai-advisor' ) . ' #' . (int) $conv->id . '</h2>';
		echo '<table class="widefat" style="max-width:600px"><tbody>';
		echo '<tr><th>' . esc_html__( 'Session', 'deliz-ai-advisor' ) . '</th><td><code>' . esc_html( $conv->session_id ) . '</code></td></tr>';
		echo '<tr><th>' . esc_html__( 'Product', 'deliz-ai-advisor' ) . '</th><td>ID ' . (int) $conv->product_id . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Language', 'deliz-ai-advisor' ) . '</th><td>' . esc_html( strtoupper( $conv->language ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Started', 'deliz-ai-advisor' ) . '</th><td>' . esc_html( $conv->started_at ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Last activity', 'deliz-ai-advisor' ) . '</th><td>' . esc_html( $conv->last_activity_at ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Tokens', 'deliz-ai-advisor' ) . '</th><td>' . (int) $conv->total_tokens_in . ' in / ' . (int) $conv->total_tokens_out . ' out</td></tr>';
		echo '<tr><th>' . esc_html__( 'Cost', 'deliz-ai-advisor' ) . '</th><td>$' . esc_html( number_format( (float) $conv->total_cost_usd, 6 ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Visitor IP', 'deliz-ai-advisor' ) . '</th><td>' . esc_html( ConversationLogger::unpack_ip( (string) $conv->visitor_ip ) ) . '</td></tr>';
		echo '</tbody></table>';

		echo '<h3 style="margin-top:24px">' . esc_html__( 'Messages', 'deliz-ai-advisor' ) . '</h3>';
		echo '<div style="max-width:800px">';
		foreach ( $messages as $m ) {
			$is_user = 'user' === $m->role;
			$bg      = $is_user ? '#e5e7eb' : '#eef2ff';
			$align   = $is_user ? 'right' : 'left';
			echo '<div style="background:' . esc_attr( $bg ) . ';padding:10px 14px;border-radius:10px;margin:10px 0;text-align:' . esc_attr( $align ) . ';max-width:80%;' . ( $is_user ? 'margin-left:auto' : '' ) . '">';
			echo '<strong>' . esc_html( strtoupper( $m->role ) ) . '</strong><br>';
			echo nl2br( esc_html( $m->content ) );
			if ( ! $is_user ) {
				echo '<div style="font-size:11px;opacity:.6;margin-top:6px;">'
					. (int) $m->tokens_in . ' in · ' . (int) $m->tokens_out . ' out · $' . esc_html( number_format( (float) $m->cost_usd, 6 ) )
					. ( $m->cache_hit ? ' · <em>cache hit</em>' : '' )
					. ( null !== $m->feedback ? ' · ' . ( (int) $m->feedback === 1 ? '👍' : '👎' ) : '' )
					. '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
	}
}
