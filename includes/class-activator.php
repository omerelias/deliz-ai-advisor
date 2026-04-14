<?php
/**
 * Plugin activator. Creates DB tables and seeds default settings.
 *
 * @package Deliz\AI\Advisor
 */

namespace Deliz\AI\Advisor;

defined( 'ABSPATH' ) || exit;

class Activator {

	const DB_VERSION = '1.0.0';

	/**
	 * Runs once on activation.
	 */
	public static function activate(): void {
		self::create_tables();
		self::seed_default_settings();
		update_option( 'deliz_ai_db_version', self::DB_VERSION, false );
	}

	/**
	 * Create 3 custom tables via dbDelta.
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$conversations   = $wpdb->prefix . 'deliz_ai_conversations';
		$messages        = $wpdb->prefix . 'deliz_ai_messages';
		$cache           = $wpdb->prefix . 'deliz_ai_cache';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql_conversations = "CREATE TABLE {$conversations} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(64) NOT NULL,
			visitor_ip VARBINARY(16) NOT NULL,
			user_id BIGINT(20) UNSIGNED NULL,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			language VARCHAR(5) NOT NULL DEFAULT 'he',
			message_count INT UNSIGNED NOT NULL DEFAULT 0,
			total_tokens_in INT UNSIGNED NOT NULL DEFAULT 0,
			total_tokens_out INT UNSIGNED NOT NULL DEFAULT 0,
			total_cost_usd DECIMAL(10,6) NOT NULL DEFAULT 0,
			converted TINYINT(1) NOT NULL DEFAULT 0,
			conversion_order_id BIGINT(20) UNSIGNED NULL,
			started_at DATETIME NOT NULL,
			last_activity_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_session (session_id),
			KEY idx_product (product_id),
			KEY idx_started (started_at),
			KEY idx_converted (converted)
		) {$charset_collate};";

		$sql_messages = "CREATE TABLE {$messages} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			conversation_id BIGINT(20) UNSIGNED NOT NULL,
			role ENUM('user','assistant') NOT NULL,
			content TEXT NOT NULL,
			tokens_in INT UNSIGNED NULL,
			tokens_out INT UNSIGNED NULL,
			cost_usd DECIMAL(10,6) NULL,
			cache_hit TINYINT(1) NOT NULL DEFAULT 0,
			feedback TINYINT(1) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_conv (conversation_id),
			KEY idx_created (created_at)
		) {$charset_collate};";

		$sql_cache = "CREATE TABLE {$cache} (
			hash CHAR(40) NOT NULL,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			language VARCHAR(5) NOT NULL,
			question TEXT NOT NULL,
			answer TEXT NOT NULL,
			hit_count INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			last_hit_at DATETIME NULL,
			PRIMARY KEY  (hash),
			KEY idx_product (product_id),
			KEY idx_created (created_at)
		) {$charset_collate};";

		dbDelta( $sql_conversations );
		dbDelta( $sql_messages );
		dbDelta( $sql_cache );
	}

	/**
	 * Write default settings if none exist yet.
	 */
	private static function seed_default_settings(): void {
		$existing = get_option( 'deliz_ai_settings', null );
		if ( is_array( $existing ) && ! empty( $existing ) ) {
			return;
		}

		add_option( 'deliz_ai_settings', self::default_settings(), '', false );
	}

	/**
	 * Default settings payload. Matches spec section 5.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'general'    => array(
				'enabled'                 => true,
				'api_key_encrypted'       => '',
				'model'                   => 'claude-haiku-4-5-20251001',
				'shop_name'               => '',
				'daily_cap_usd'           => 5.00,
				'max_tokens_per_response' => 400,
				'enable_on'               => array( 'product' ),
				'excluded_categories'     => array(),
			),
			'appearance' => array(
				'position'               => 'bottom-right',
				'bubble_icon'            => 'default',
				'bubble_icon_custom_url' => '',
				'primary_color'          => '#7c3aed',
				'primary_text_color'     => '#ffffff',
				'background_color'       => '#ffffff',
				'text_color'             => '#1f2937',
				'user_bubble_color'      => '#7c3aed',
				'user_text_color'        => '#ffffff',
				'assistant_bubble_color' => '#f3f4f6',
				'assistant_text_color'   => '#1f2937',
				'font_family'            => 'inherit',
				'border_radius'          => 16,
				'panel_width'            => 380,
				'panel_height'           => 560,
				'show_branding'          => true,
				'shadow_intensity'       => 'medium',
			),
			'content'    => array(
				'title_he'                 => 'יועץ הדליקטסן',
				'title_ru'                 => 'Консультант деликатесов',
				'title_ar'                 => 'مستشار المأكولات الشهية',
				'title_en'                 => 'Deli Advisor',
				'greeting_he'              => 'שלום! אני היועץ של החנות. שאל אותי על הנתח הזה, איך לבשל, מה מתאים...',
				'greeting_ru'              => 'Здравствуйте! Я консультант магазина. Спросите меня о нарезке, приготовлении, подаче…',
				'greeting_ar'              => 'مرحبا! أنا مستشار المتجر. اسألني عن القطعة، طريقة الطهي، ما يناسبها…',
				'greeting_en'              => "Hi! I'm the shop's advisor. Ask me about this cut, how to cook it, what pairs well...",
				'suggested_questions_he'   => array( 'איך לבשל את זה?', 'מה מתאים כתוספת?', 'באיזו טמפרטורה לצלות?' ),
				'suggested_questions_ru'   => array( 'Как это готовить?', 'Что подать к этому?', 'При какой температуре жарить?' ),
				'suggested_questions_ar'   => array( 'كيف أطبخ هذا؟', 'ماذا يناسب معه؟', 'ما درجة الحرارة للشوي؟' ),
				'suggested_questions_en'   => array( 'How do I cook this?', 'What goes well with it?', 'What temperature to grill?' ),
				'placeholder_he'           => 'שאל אותי משהו...',
				'placeholder_ru'           => 'Спросите меня...',
				'placeholder_ar'           => 'اسألني شيئا...',
				'placeholder_en'           => 'Ask me anything...',
			),
			'behavior'   => array(
				'rate_limit_per_ip_per_hour'         => 10,
				'max_message_length'                 => 500,
				'cache_ttl_days'                     => 7,
				'enable_cache'                       => true,
				'enabled_languages'                  => array( 'he', 'ru', 'ar', 'en' ),
				'default_language'                   => 'he',
				'conversation_timeout_minutes'       => 30,
				'show_feedback_buttons'              => true,
				'include_related_products_in_context' => true,
				'max_related_products'               => 8,
			),
			'prompts'    => array(
				'system_prompt_template'  => '',
				'off_topic_response_he'   => 'אני רק היועץ של הדליקטסן 🙂 בוא נחזור לאוכל?',
				'off_topic_response_ru'   => 'Я лишь консультант деликатесов 🙂 Вернёмся к еде?',
				'off_topic_response_ar'   => 'أنا مجرد مستشار المأكولات 🙂 لنعد إلى الطعام؟',
				'off_topic_response_en'   => "I'm just the deli advisor 🙂 Shall we get back to food?",
			),
			'advanced'   => array(
				'debug_mode'                => false,
				'log_all_requests'          => true,
				'anonymize_ips_after_days'  => 30,
				'delete_logs_after_days'    => 365,
				'delete_data_on_uninstall'  => false,
			),
		);
	}
}
