<?php
/**
 * General settings tab view.
 *
 * @package Deliz\AI\Advisor\Admin
 * @var array<string, mixed> $settings The merged settings array (from SettingsPage::render).
 */

defined( 'ABSPATH' ) || exit;

use Deliz\AI\Advisor\Services\Encryption;
use Deliz\AI\Advisor\Models\Settings;

$general   = $settings['general'];
$has_key   = '' !== ( $general['api_key_encrypted'] ?? '' );
$key_mask  = $has_key ? Encryption::mask( Settings::api_key() ) : '';
$enable_on = (array) $general['enable_on'];
?>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Enable Advisor', 'deliz-ai-advisor' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $general['enabled'] ) ); ?>>
				<?php esc_html_e( 'Show the chat bubble on qualifying pages', 'deliz-ai-advisor' ); ?>
			</label>
		</td>
	</tr>

	<tr>
		<th scope="row">
			<label for="deliz-api-key"><?php esc_html_e( 'Anthropic API Key', 'deliz-ai-advisor' ); ?></label>
		</th>
		<td>
			<input
				type="password"
				id="deliz-api-key"
				name="api_key"
				class="regular-text"
				placeholder="<?php echo esc_attr( $has_key ? $key_mask : 'sk-ant-…' ); ?>"
				autocomplete="off"
			>
			<button type="button" class="button" id="deliz-test-key">
				<?php esc_html_e( 'Test Connection', 'deliz-ai-advisor' ); ?>
			</button>
			<span id="deliz-test-result" class="deliz-test-result" aria-live="polite"></span>

			<p class="description">
				<?php esc_html_e( 'Get a key from console.anthropic.com. Stored encrypted. Leave blank to keep the current key.', 'deliz-ai-advisor' ); ?>
			</p>
		</td>
	</tr>

	<tr>
		<th scope="row">
			<label for="deliz-model"><?php esc_html_e( 'Model', 'deliz-ai-advisor' ); ?></label>
		</th>
		<td>
			<select id="deliz-model" name="model">
				<option value="claude-haiku-4-5-20251001" <?php selected( $general['model'], 'claude-haiku-4-5-20251001' ); ?>>
					<?php esc_html_e( 'Claude Haiku 4.5 — Fast & cheap (recommended)', 'deliz-ai-advisor' ); ?>
				</option>
				<option value="claude-sonnet-4-6" <?php selected( $general['model'], 'claude-sonnet-4-6' ); ?>>
					<?php esc_html_e( 'Claude Sonnet 4.6 — Smarter, pricier', 'deliz-ai-advisor' ); ?>
				</option>
			</select>
		</td>
	</tr>

	<tr>
		<th scope="row">
			<label for="deliz-shop-name"><?php esc_html_e( 'Shop Name', 'deliz-ai-advisor' ); ?></label>
		</th>
		<td>
			<input
				type="text"
				id="deliz-shop-name"
				name="shop_name"
				value="<?php echo esc_attr( $general['shop_name'] ); ?>"
				class="regular-text"
				placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
			>
			<p class="description">
				<?php esc_html_e( 'Shown in the greeting and system prompt. Leave blank to use the site name.', 'deliz-ai-advisor' ); ?>
			</p>
		</td>
	</tr>

	<tr>
		<th scope="row">
			<label for="deliz-daily-cap"><?php esc_html_e( 'Daily Cap (USD)', 'deliz-ai-advisor' ); ?></label>
		</th>
		<td>
			<input
				type="number"
				id="deliz-daily-cap"
				name="daily_cap_usd"
				value="<?php echo esc_attr( $general['daily_cap_usd'] ); ?>"
				step="0.10"
				min="0"
				class="small-text"
			> USD
			<p class="description">
				<?php esc_html_e( 'Hard limit on spend per UTC day. Once reached, chat returns a friendly error.', 'deliz-ai-advisor' ); ?>
			</p>
		</td>
	</tr>

	<tr>
		<th scope="row">
			<label for="deliz-max-tokens"><?php esc_html_e( 'Max tokens per response', 'deliz-ai-advisor' ); ?></label>
		</th>
		<td>
			<input
				type="number"
				id="deliz-max-tokens"
				name="max_tokens_per_response"
				value="<?php echo esc_attr( $general['max_tokens_per_response'] ); ?>"
				step="50"
				min="50"
				max="4096"
				class="small-text"
			>
			<p class="description">
				<?php esc_html_e( 'Lower = cheaper, tighter answers. 400 is a good default.', 'deliz-ai-advisor' ); ?>
			</p>
		</td>
	</tr>

	<tr>
		<th scope="row"><?php esc_html_e( 'Show chat on', 'deliz-ai-advisor' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="enable_on[]" value="product" <?php checked( in_array( 'product', $enable_on, true ) ); ?>>
				<?php esc_html_e( 'Product pages', 'deliz-ai-advisor' ); ?>
			</label><br>
			<label>
				<input type="checkbox" name="enable_on[]" value="category" <?php checked( in_array( 'category', $enable_on, true ) ); ?>>
				<?php esc_html_e( 'Category pages', 'deliz-ai-advisor' ); ?>
			</label><br>
			<label>
				<input type="checkbox" name="enable_on[]" value="shop" <?php checked( in_array( 'shop', $enable_on, true ) ); ?>>
				<?php esc_html_e( 'Shop page', 'deliz-ai-advisor' ); ?>
			</label>
		</td>
	</tr>
</table>
