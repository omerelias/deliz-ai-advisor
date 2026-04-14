<?php
/**
 * Settings page with tabbed navigation.
 *
 * @package Deliz\AI\Advisor\Admin
 */

namespace Deliz\AI\Advisor\Admin;

use Deliz\AI\Advisor\Models\Settings;
use Deliz\AI\Advisor\Services\Encryption;

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
			/*
			 * Other tabs persist in later phases — intentionally no-op now.
			 */
		}
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
}
