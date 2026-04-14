<?php
/**
 * Outputs the chat widget HTML in wp_footer on qualifying pages.
 *
 * @package Deliz\AI\Advisor\Frontend
 */

namespace Deliz\AI\Advisor\Frontend;

use Deliz\AI\Advisor\Models\Settings;

defined( 'ABSPATH' ) || exit;

class Widget {

	public function register(): void {
		add_action( 'wp_footer', array( $this, 'maybe_render' ), 20 );
	}

	/**
	 * Should the widget render on the current page?
	 */
	public function should_render(): bool {
		$general = Settings::group( 'general' );
		if ( empty( $general['enabled'] ) ) {
			return false;
		}
		if ( empty( Settings::api_key() ) ) {
			return false;
		}
		if ( ! function_exists( 'is_product' ) ) {
			return false;
		}

		$enable_on = (array) ( $general['enable_on'] ?? array() );

		if ( in_array( 'product', $enable_on, true ) && is_product() ) {
			// Check excluded categories.
			$excluded = (array) ( $general['excluded_categories'] ?? array() );
			if ( ! empty( $excluded ) ) {
				$product_id = get_queried_object_id();
				$terms      = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
				if ( is_array( $terms ) && array_intersect( $terms, array_map( 'absint', $excluded ) ) ) {
					return false;
				}
			}
			return true;
		}

		if ( in_array( 'category', $enable_on, true ) && is_product_category() ) {
			return true;
		}

		if ( in_array( 'shop', $enable_on, true ) && is_shop() ) {
			return true;
		}

		return false;
	}

	public function maybe_render(): void {
		if ( ! $this->should_render() ) {
			return;
		}
		$this->render();
	}

	public function render(): void {
		$appearance = Settings::group( 'appearance' );
		$content    = Settings::group( 'content' );
		$behavior   = Settings::group( 'behavior' );

		$lang = $this->current_language( $behavior );
		$rtl  = is_rtl() || in_array( $lang, array( 'he', 'ar' ), true );

		$title       = $content[ "title_{$lang}" ] ?? $content['title_he'];
		$greeting    = $content[ "greeting_{$lang}" ] ?? $content['greeting_he'];
		$placeholder = $content[ "placeholder_{$lang}" ] ?? $content['placeholder_he'];
		$suggestions = $content[ "suggested_questions_{$lang}" ] ?? $content['suggested_questions_he'];
		if ( ! is_array( $suggestions ) ) {
			$suggestions = array();
		}

		$product_id = function_exists( 'is_product' ) && is_product() ? (int) get_queried_object_id() : 0;

		// Build CSS vars from settings.
		$css_vars = $this->build_css_vars( $appearance );

		?>
		<style id="deliz-ai-vars">
		.deliz-ai-widget {
			<?php echo esc_html( $css_vars ); ?>
		}
		</style>
		<div
			id="deliz-ai-widget"
			class="deliz-ai-widget deliz-ai-widget--<?php echo esc_attr( $appearance['position'] ?? 'bottom-right' ); ?>"
			data-dir="<?php echo esc_attr( $rtl ? 'rtl' : 'ltr' ); ?>"
			data-lang="<?php echo esc_attr( $lang ); ?>"
			data-product-id="<?php echo esc_attr( $product_id ); ?>"
			aria-live="polite"
		>
			<!-- Closed state: bubble -->
			<button
				type="button"
				class="deliz-ai-bubble"
				aria-label="<?php echo esc_attr__( 'Open chat advisor', 'deliz-ai-advisor' ); ?>"
			>
				<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true" fill="currentColor">
					<path d="M12 2l1.8 5.4L19 9l-5.2 1.6L12 16l-1.8-5.4L5 9l5.2-1.6L12 2zm7 11l.9 2.8 2.8.9-2.8.9L19 20l-.9-2.4-2.8-.9 2.8-.9.9-2.8z"/>
				</svg>
			</button>

			<!-- Open state: panel (hidden by default) -->
			<div class="deliz-ai-panel" role="dialog" aria-labelledby="deliz-ai-title" hidden>
				<header class="deliz-ai-panel__header">
					<span class="deliz-ai-panel__title" id="deliz-ai-title"><?php echo esc_html( $title ); ?></span>
					<button type="button" class="deliz-ai-panel__close" aria-label="<?php echo esc_attr__( 'Close chat', 'deliz-ai-advisor' ); ?>">
						<svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true" fill="currentColor">
							<path d="M4.3 4.3a1 1 0 0 1 1.4 0L10 8.6l4.3-4.3a1 1 0 1 1 1.4 1.4L11.4 10l4.3 4.3a1 1 0 0 1-1.4 1.4L10 11.4l-4.3 4.3a1 1 0 0 1-1.4-1.4L8.6 10 4.3 5.7a1 1 0 0 1 0-1.4z"/>
						</svg>
					</button>
				</header>

				<div class="deliz-ai-panel__messages" role="log" aria-live="polite">
					<div class="deliz-ai-msg deliz-ai-msg--assistant deliz-ai-msg--greeting">
						<?php echo esc_html( $greeting ); ?>
					</div>

					<?php if ( ! empty( $suggestions ) ) : ?>
						<div class="deliz-ai-suggestions">
							<?php foreach ( $suggestions as $q ) : ?>
								<button type="button" class="deliz-ai-chip" data-question="<?php echo esc_attr( $q ); ?>">
									<?php echo esc_html( $q ); ?>
								</button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<form class="deliz-ai-panel__input">
					<input
						type="text"
						class="deliz-ai-input"
						maxlength="500"
						placeholder="<?php echo esc_attr( $placeholder ); ?>"
						aria-label="<?php echo esc_attr__( 'Type your question', 'deliz-ai-advisor' ); ?>"
						autocomplete="off"
					>
					<button type="submit" class="deliz-ai-send" aria-label="<?php echo esc_attr__( 'Send', 'deliz-ai-advisor' ); ?>">
						<svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true" fill="currentColor">
							<path d="M2 10L18 2l-3 16-5-5 3-4-7 5-4-4z"/>
						</svg>
					</button>
				</form>

				<?php if ( ! empty( $appearance['show_branding'] ) ) : ?>
					<div class="deliz-ai-panel__footer"><?php esc_html_e( 'Powered by Claude', 'deliz-ai-advisor' ); ?></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Build CSS custom properties from settings.
	 */
	private function build_css_vars( array $a ): string {
		$shadow = array(
			'none'   => 'none',
			'light'  => '0 4px 16px rgba(0,0,0,.08)',
			'medium' => '0 10px 40px rgba(0,0,0,.12)',
			'heavy'  => '0 20px 60px rgba(0,0,0,.22)',
		)[ $a['shadow_intensity'] ?? 'medium' ] ?? '0 10px 40px rgba(0,0,0,.12)';

		return sprintf(
			'--deliz-primary:%s;--deliz-primary-text:%s;--deliz-bg:%s;--deliz-text:%s;--deliz-user-bubble:%s;--deliz-user-text:%s;--deliz-assistant-bubble:%s;--deliz-assistant-text:%s;--deliz-radius:%dpx;--deliz-panel-w:%dpx;--deliz-panel-h:%dpx;--deliz-font:%s;--deliz-shadow:%s;',
			$this->color( $a['primary_color'] ?? '#7c3aed' ),
			$this->color( $a['primary_text_color'] ?? '#ffffff' ),
			$this->color( $a['background_color'] ?? '#ffffff' ),
			$this->color( $a['text_color'] ?? '#1f2937' ),
			$this->color( $a['user_bubble_color'] ?? '#7c3aed' ),
			$this->color( $a['user_text_color'] ?? '#ffffff' ),
			$this->color( $a['assistant_bubble_color'] ?? '#f3f4f6' ),
			$this->color( $a['assistant_text_color'] ?? '#1f2937' ),
			(int) ( $a['border_radius'] ?? 16 ),
			(int) ( $a['panel_width'] ?? 380 ),
			(int) ( $a['panel_height'] ?? 560 ),
			esc_html( $a['font_family'] ?? 'inherit' ),
			$shadow
		);
	}

	private function color( string $hex ): string {
		return preg_match( '/^#[0-9a-f]{3,8}$/i', $hex ) ? $hex : '#000';
	}

	/**
	 * Best guess at the page language for initial UI labels.
	 * (Actual chat language detection happens per-message server-side.)
	 */
	private function current_language( array $behavior ): string {
		$default = (string) ( $behavior['default_language'] ?? 'he' );
		$locale  = determine_locale();
		$map     = array(
			'he_IL' => 'he',
			'ru_RU' => 'ru',
			'ar'    => 'ar',
			'en_US' => 'en',
			'en_GB' => 'en',
		);
		if ( isset( $map[ $locale ] ) ) {
			return $map[ $locale ];
		}
		$prefix = substr( $locale, 0, 2 );
		if ( in_array( $prefix, array( 'he', 'ru', 'ar', 'en' ), true ) ) {
			return $prefix;
		}
		return $default;
	}
}
