<?php
/**
 * Settings page with tabbed navigation.
 *
 * @package Deliz\AI\Advisor\Admin
 */

namespace Deliz\AI\Advisor\Admin;

use Deliz\AI\Advisor\Models\Settings;
use Deliz\AI\Advisor\Services\Encryption;
use Deliz\AI\Advisor\Services\RateLimiter;
use Deliz\AI\Advisor\Services\ResponseCache;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

	/**
	 * Map tab slug → label + view file.
	 *
	 * @return array<string, array{label:string, view:string}>
	 */
	public function tabs(): array {
		return array(
			'general'    => array(
				'label' => __( 'General', 'deliz-ai-advisor' ),
				'view'  => 'settings-general.php',
			),
			'appearance' => array(
				'label' => __( 'Appearance', 'deliz-ai-advisor' ),
				'view'  => 'settings-appearance.php',
			),
			'content'    => array(
				'label' => __( 'Content & Languages', 'deliz-ai-advisor' ),
				'view'  => 'settings-content.php',
			),
			'behavior'   => array(
				'label' => __( 'Behavior', 'deliz-ai-advisor' ),
				'view'  => 'settings-behavior.php',
			),
			'prompts'    => array(
				'label' => __( 'Prompts', 'deliz-ai-advisor' ),
				'view'  => 'settings-prompts.php',
			),
			'advanced'   => array(
				'label' => __( 'Advanced', 'deliz-ai-advisor' ),
				'view'  => 'settings-advanced.php',
			),
		);
	}

	/**
	 * Render the full settings page with tab navigation.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs    = $this->tabs();
		$current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		if ( ! isset( $tabs[ $current ] ) ) {
			$current = 'general';
		}

		$settings = Settings::all();

		?>
		<div class="wrap deliz-ai-settings">
			<h1>
				<span class="dashicons dashicons-format-chat" style="font-size:30px"></span>
				<?php esc_html_e( 'Deliz AI Advisor — Settings', 'deliz-ai-advisor' ); ?>
			</h1>

			<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'deliz-ai-advisor' ); ?></p>
				</div>
			<?php endif; ?>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $def ) : ?>
					<a
						href="<?php echo esc_url( add_query_arg( array( 'page' => Admin::MENU_SLUG, 'tab' => $slug ), admin_url( 'admin.php' ) ) ); ?>"
						class="nav-tab <?php echo $current === $slug ? 'nav-tab-active' : ''; ?>"
					>
						<?php echo esc_html( $def['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . Admin::MENU_SLUG ) ); ?>" class="deliz-ai-form">
				<?php wp_nonce_field( 'deliz_ai_save_settings' ); ?>
				<input type="hidden" name="current_tab" value="<?php echo esc_attr( $current ); ?>">

				<?php
				$view = DELIZ_AI_PLUGIN_DIR . 'includes/admin/views/' . $tabs[ $current ]['view'];
				if ( file_exists( $view ) ) {
					include $view;
				} else {
					echo '<p>' . esc_html__( 'This section is not implemented yet.', 'deliz-ai-advisor' ) . '</p>';
				}
				?>

				<p class="submit">
					<button type="submit" name="deliz_ai_save" class="button button-primary">
						<?php esc_html_e( 'Save Settings', 'deliz-ai-advisor' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Persist settings from the posted form.
	 *
	 * @param array<string, mixed> $post Raw $_POST.
	 */
	public function save( array $post ): void {
		$tab = isset( $post['current_tab'] ) ? sanitize_key( $post['current_tab'] ) : 'general';
		switch ( $tab ) {
			case 'general':
				$this->save_general( $post );
				break;
			case 'appearance':
				$this->save_appearance( $post );
				break;
			case 'content':
				$this->save_content( $post );
				break;
			case 'behavior':
				$this->save_behavior( $post );
				break;
			case 'prompts':
				$this->save_prompts( $post );
				break;
			case 'advanced':
				$this->save_advanced( $post );
				break;
		}
	}

	/**
	 * Handle maintenance actions (clear cache / rate limits).
	 * Called from Admin on `admin_init`.
	 */
	public static function handle_maintenance_action(): void {
		if ( ! isset( $_GET['deliz_action'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'deliz_ai_admin_action' );

		$action = sanitize_key( wp_unslash( $_GET['deliz_action'] ) );
		$msg    = '';

		if ( 'clear_cache' === $action ) {
			$count = ResponseCache::clear();
			$msg   = sprintf( /* translators: %d: rows deleted */ __( 'Cache cleared (%d rows).', 'deliz-ai-advisor' ), $count );
		} elseif ( 'clear_rate' === $action ) {
			RateLimiter::clear_all();
			$msg = __( 'Rate limits cleared.', 'deliz-ai-advisor' );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => Admin::MENU_SLUG,
					'tab'              => 'advanced',
					'settings-updated' => 'true',
					'deliz_notice'     => rawurlencode( $msg ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Save the General tab.
	 *
	 * @param array<string, mixed> $post
	 */
	private function save_general( array $post ): void {
		$current = Settings::group( 'general' );

		$values = array(
			'enabled'                 => ! empty( $post['enabled'] ),
			'model'                   => isset( $post['model'] ) ? sanitize_text_field( wp_unslash( $post['model'] ) ) : $current['model'],
			'shop_name'               => isset( $post['shop_name'] ) ? sanitize_text_field( wp_unslash( $post['shop_name'] ) ) : '',
			'daily_cap_usd'           => isset( $post['daily_cap_usd'] ) ? (float) $post['daily_cap_usd'] : 5.0,
			'max_tokens_per_response' => isset( $post['max_tokens_per_response'] ) ? absint( $post['max_tokens_per_response'] ) : 400,
			'enable_on'               => isset( $post['enable_on'] ) && is_array( $post['enable_on'] )
				? array_map( 'sanitize_key', wp_unslash( $post['enable_on'] ) )
				: array( 'product' ),
		);

		// API key: only update if user typed a new one; empty input keeps existing.
		$posted_key = isset( $post['api_key'] ) ? trim( (string) wp_unslash( $post['api_key'] ) ) : '';
		if ( '' !== $posted_key && false === strpos( $posted_key, '•' ) ) {
			$values['api_key_encrypted'] = Encryption::encrypt( $posted_key );
		} else {
			$values['api_key_encrypted'] = $current['api_key_encrypted'] ?? '';
		}

		// Allowed models — protect against bad input.
		if ( ! in_array( $values['model'], array( 'claude-haiku-4-5-20251001', 'claude-sonnet-4-6' ), true ) ) {
			$values['model'] = 'claude-haiku-4-5-20251001';
		}

		// Clamp numbers.
		$values['daily_cap_usd']           = max( 0.0, min( 10000.0, $values['daily_cap_usd'] ) );
		$values['max_tokens_per_response'] = max( 50, min( 4096, $values['max_tokens_per_response'] ) );

		Settings::save_group( 'general', $values );
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function save_appearance( array $post ): void {
		$input = isset( $post['appearance'] ) && is_array( $post['appearance'] ) ? wp_unslash( $post['appearance'] ) : array();
		$current = Settings::group( 'appearance' );

		$positions = array( 'bottom-right', 'bottom-left' );
		$shadows   = array( 'none', 'light', 'medium', 'heavy' );

		$values = array(
			'position'               => in_array( $input['position'] ?? '', $positions, true ) ? $input['position'] : 'bottom-right',
			'primary_color'          => $this->sanitize_hex( $input['primary_color']          ?? $current['primary_color'] ),
			'primary_text_color'     => $this->sanitize_hex( $input['primary_text_color']     ?? $current['primary_text_color'] ),
			'background_color'       => $this->sanitize_hex( $input['background_color']       ?? $current['background_color'] ),
			'text_color'             => $this->sanitize_hex( $input['text_color']             ?? $current['text_color'] ),
			'user_bubble_color'      => $this->sanitize_hex( $input['user_bubble_color']      ?? $current['user_bubble_color'] ),
			'user_text_color'        => $this->sanitize_hex( $input['user_text_color']        ?? $current['user_text_color'] ),
			'assistant_bubble_color' => $this->sanitize_hex( $input['assistant_bubble_color'] ?? $current['assistant_bubble_color'] ),
			'assistant_text_color'   => $this->sanitize_hex( $input['assistant_text_color']   ?? $current['assistant_text_color'] ),
			'font_family'            => sanitize_text_field( $input['font_family']            ?? 'inherit' ),
			'border_radius'          => max( 0, min( 32, (int) ( $input['border_radius']      ?? 16 ) ) ),
			'panel_width'            => max( 300, min( 600, (int) ( $input['panel_width']     ?? 380 ) ) ),
			'panel_height'           => max( 380, min( 900, (int) ( $input['panel_height']    ?? 560 ) ) ),
			'shadow_intensity'       => in_array( $input['shadow_intensity'] ?? '', $shadows, true ) ? $input['shadow_intensity'] : 'medium',
			'show_branding'          => ! empty( $input['show_branding'] ),
		);

		Settings::save_group( 'appearance', $values );
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function save_content( array $post ): void {
		$input = isset( $post['content'] ) && is_array( $post['content'] ) ? wp_unslash( $post['content'] ) : array();

		$values = array();
		foreach ( array( 'he', 'ru', 'ar', 'en' ) as $code ) {
			$values[ "title_{$code}" ]       = isset( $input[ "title_{$code}" ] ) ? sanitize_text_field( $input[ "title_{$code}" ] ) : '';
			$values[ "greeting_{$code}" ]    = isset( $input[ "greeting_{$code}" ] ) ? sanitize_textarea_field( $input[ "greeting_{$code}" ] ) : '';
			$values[ "placeholder_{$code}" ] = isset( $input[ "placeholder_{$code}" ] ) ? sanitize_text_field( $input[ "placeholder_{$code}" ] ) : '';

			$qs = isset( $input[ "suggested_questions_{$code}" ] ) && is_array( $input[ "suggested_questions_{$code}" ] )
				? array_values( array_filter( array_map( 'sanitize_text_field', $input[ "suggested_questions_{$code}" ] ) ) )
				: array();
			$values[ "suggested_questions_{$code}" ] = array_slice( $qs, 0, 3 );
		}

		Settings::save_group( 'content', $values );
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function save_behavior( array $post ): void {
		$input = isset( $post['behavior'] ) && is_array( $post['behavior'] ) ? wp_unslash( $post['behavior'] ) : array();
		$langs = isset( $input['enabled_languages'] ) && is_array( $input['enabled_languages'] )
			? array_values( array_intersect( array_map( 'sanitize_key', $input['enabled_languages'] ), array( 'he', 'ru', 'ar', 'en' ) ) )
			: array( 'he', 'ru', 'ar', 'en' );

		$values = array(
			'rate_limit_per_ip_per_hour'          => max( 0, min( 1000, (int) ( $input['rate_limit_per_ip_per_hour'] ?? 10 ) ) ),
			'max_message_length'                  => max( 50, min( 2000, (int) ( $input['max_message_length'] ?? 500 ) ) ),
			'enable_cache'                        => ! empty( $input['enable_cache'] ),
			'cache_ttl_days'                      => max( 1, min( 365, (int) ( $input['cache_ttl_days'] ?? 7 ) ) ),
			'enabled_languages'                   => ! empty( $langs ) ? $langs : array( 'he' ),
			'default_language'                    => in_array( ( $input['default_language'] ?? 'he' ), array( 'he', 'ru', 'ar', 'en' ), true ) ? $input['default_language'] : 'he',
			'show_feedback_buttons'               => ! empty( $input['show_feedback_buttons'] ),
			'include_related_products_in_context' => ! empty( $input['include_related_products_in_context'] ),
			'max_related_products'                => max( 1, min( 20, (int) ( $input['max_related_products'] ?? 8 ) ) ),
		);
		Settings::save_group( 'behavior', $values );
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function save_prompts( array $post ): void {
		$input = isset( $post['prompts'] ) && is_array( $post['prompts'] ) ? wp_unslash( $post['prompts'] ) : array();

		$values = array(
			'system_prompt_template' => isset( $input['system_prompt_template'] ) ? wp_kses_post( $input['system_prompt_template'] ) : '',
		);
		foreach ( array( 'he', 'ru', 'ar', 'en' ) as $code ) {
			$values[ "off_topic_response_{$code}" ] = isset( $input[ "off_topic_response_{$code}" ] )
				? sanitize_text_field( $input[ "off_topic_response_{$code}" ] )
				: '';
		}
		Settings::save_group( 'prompts', $values );
	}

	/**
	 * @param array<string,mixed> $post
	 */
	private function save_advanced( array $post ): void {
		$input = isset( $post['advanced'] ) && is_array( $post['advanced'] ) ? wp_unslash( $post['advanced'] ) : array();
		$values = array(
			'debug_mode'                => ! empty( $input['debug_mode'] ),
			'log_all_requests'          => ! empty( $input['log_all_requests'] ),
			'anonymize_ips_after_days'  => max( 0, min( 3650, (int) ( $input['anonymize_ips_after_days'] ?? 30 ) ) ),
			'delete_logs_after_days'    => max( 0, min( 3650, (int) ( $input['delete_logs_after_days'] ?? 365 ) ) ),
			'delete_data_on_uninstall'  => ! empty( $input['delete_data_on_uninstall'] ),
		);
		Settings::save_group( 'advanced', $values );
	}

	private function sanitize_hex( string $hex ): string {
		return preg_match( '/^#[0-9a-f]{3,8}$/i', $hex ) ? $hex : '#000000';
	}
}
