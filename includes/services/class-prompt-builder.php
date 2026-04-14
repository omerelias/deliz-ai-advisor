<?php
/**
 * Builds the system prompt + message array for the Anthropic API.
 *
 * @package Deliz\AI\Advisor\Services
 */

namespace Deliz\AI\Advisor\Services;

use Deliz\AI\Advisor\Models\Settings;

defined( 'ABSPATH' ) || exit;

class PromptBuilder {

	/**
	 * Default system prompt template (spec section 10).
	 */
	const DEFAULT_TEMPLATE = <<<PROMPT
You are the AI advisor for "{{shop_name}}", a specialty delicatessen.
Your job is to help visitors understand the products they're looking at and make confident purchasing decisions.

PERSONALITY:
- Warm, knowledgeable, concise. Like a trusted local butcher/deli owner.
- Not salesy. Focus on being genuinely helpful.
- Use at most 1 emoji per response.
- Keep responses under 3 sentences unless the user asks for detail.

LANGUAGE:
- Detect the language of the user's most recent message.
- Reply in that same language: Hebrew, Russian, Arabic, or English.
- If the language is mixed or unclear, default to {{language}}.
- Never switch language mid-conversation unless the user does first.

SCOPE (strict):
You may discuss:
- The current product (cooking, storage, serving, pairings, comparisons)
- Related products in our catalog
- Food safety basics for meat/fish/dairy
- Traditional preparations relevant to the product

You MAY NOT discuss:
- Politics, religion, personal advice, medical advice, other shops, pricing negotiations, promotions
- Anything off-topic

If asked off-topic, politely redirect: "I'm just the deli advisor 🙂 Shall we get back to food?" (translated to their language).

NEVER:
- Invent products or prices. Only reference what's in CONTEXT below.
- Make health claims.
- Say "I am an AI" unless directly asked. Just be the advisor.

CURRENT PRODUCT:
- Name: {{product_name}}
- Price: {{product_price}}
- Category: {{product_category}}
- Weight: {{product_weight}}
- Description: {{product_description}}

COMPLEMENTARY PRODUCTS CURRENTLY AVAILABLE:
{{related_products}}

When recommending a pairing, prefer products from this list. Always mention them by their exact name.

Current date: {{current_date}}
PROMPT;

	/**
	 * Build the system prompt for a given product + language.
	 */
	public function build_system( int $product_id, string $language = 'he' ): string {
		$general  = Settings::group( 'general' );
		$prompts  = Settings::group( 'prompts' );
		$behavior = Settings::group( 'behavior' );

		$template = ! empty( $prompts['system_prompt_template'] )
			? (string) $prompts['system_prompt_template']
			: self::DEFAULT_TEMPLATE;

		$shop_name = $general['shop_name'] ?: get_bloginfo( 'name' );

		$product_ctx = $this->product_context( $product_id );
		$related     = '';
		if ( ! empty( $behavior['include_related_products_in_context'] ) ) {
			$related = $this->related_products_text( $product_id, (int) ( $behavior['max_related_products'] ?? 8 ) );
		}

		$replacements = array(
			'{{shop_name}}'           => $shop_name,
			'{{product_name}}'        => $product_ctx['name'],
			'{{product_price}}'       => $product_ctx['price'],
			'{{product_category}}'    => $product_ctx['category'],
			'{{product_weight}}'      => $product_ctx['weight'],
			'{{product_description}}' => $product_ctx['description'],
			'{{related_products}}'    => $related !== '' ? $related : '(none)',
			'{{language}}'            => $language,
			'{{current_date}}'        => gmdate( 'Y-m-d' ),
		);

		return strtr( $template, $replacements );
	}

	/**
	 * Build the messages array (history + current user message).
	 *
	 * @param array<int, array{role:string, content:string}> $history
	 * @param string                                         $new_user_message
	 *
	 * @return array<int, array{role:string, content:string}>
	 */
	public function build_messages( array $history, string $new_user_message ): array {
		$messages = array();
		foreach ( $history as $turn ) {
			if ( ! is_array( $turn ) ) {
				continue;
			}
			$role    = isset( $turn['role'] ) ? (string) $turn['role'] : '';
			$content = isset( $turn['content'] ) ? (string) $turn['content'] : '';
			if ( ! in_array( $role, array( 'user', 'assistant' ), true ) || '' === $content ) {
				continue;
			}
			$messages[] = array(
				'role'    => $role,
				'content' => sanitize_textarea_field( wp_strip_all_tags( $content ) ),
			);
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => $new_user_message,
		);

		return $messages;
	}

	/**
	 * Extract product context fields.
	 *
	 * @return array{name:string, price:string, category:string, weight:string, description:string}
	 */
	private function product_context( int $product_id ): array {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return array(
				'name'        => '',
				'price'       => '',
				'category'    => '',
				'weight'      => '',
				'description' => '',
			);
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array(
				'name'        => '',
				'price'       => '',
				'category'    => '',
				'weight'      => '',
				'description' => '',
			);
		}

		$categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
		$category   = is_array( $categories ) && ! empty( $categories ) ? implode( ', ', $categories ) : '';

		$desc = $product->get_short_description() ?: $product->get_description();
		$desc = wp_strip_all_tags( (string) $desc );
		if ( mb_strlen( $desc ) > 500 ) {
			$desc = mb_substr( $desc, 0, 500 ) . '…';
		}

		$weight = $product->get_weight();

		return array(
			'name'        => $product->get_name(),
			'price'       => wp_strip_all_tags( (string) $product->get_price_html() ),
			'category'    => $category,
			'weight'      => '' !== $weight ? $weight . ' ' . get_option( 'woocommerce_weight_unit', 'kg' ) : '',
			'description' => $desc,
		);
	}

	/**
	 * Render related-products list as plain text for the system prompt.
	 */
	private function related_products_text( int $product_id, int $limit ): string {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return '';
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return '';
		}

		$cat_ids = $product->get_category_ids();
		$related = wc_get_products(
			array(
				'limit'      => $limit,
				'category'   => $cat_ids ? wp_list_pluck( get_terms( array( 'include' => $cat_ids ) ), 'slug' ) : array(),
				'exclude'    => array( $product_id ),
				'status'     => 'publish',
				'orderby'    => 'rand',
			)
		);

		if ( empty( $related ) ) {
			return '';
		}

		$lines = array();
		foreach ( $related as $p ) {
			$lines[] = sprintf(
				'- %s (%s)',
				$p->get_name(),
				wp_strip_all_tags( (string) $p->get_price_html() )
			);
		}
		return implode( "\n", $lines );
	}
}
