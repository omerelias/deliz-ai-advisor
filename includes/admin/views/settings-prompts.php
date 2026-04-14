<?php
/**
 * Prompts tab — system prompt editor with merge-tag reference.
 *
 * @package Deliz\AI\Advisor\Admin
 * @var array<string, mixed> $settings
 */

defined( 'ABSPATH' ) || exit;

use Deliz\AI\Advisor\Services\PromptBuilder;

$p             = $settings['prompts'];
$current_value = ! empty( $p['system_prompt_template'] ) ? $p['system_prompt_template'] : PromptBuilder::DEFAULT_TEMPLATE;
$tags          = array(
	'{{shop_name}}',
	'{{product_name}}',
	'{{product_price}}',
	'{{product_description}}',
	'{{product_category}}',
	'{{product_weight}}',
	'{{related_products}}',
	'{{language}}',
	'{{current_date}}',
);
?>
<div class="notice notice-warning inline">
	<p><strong>⚠️ <?php esc_html_e( 'Advanced.', 'deliz-ai-advisor' ); ?></strong> <?php esc_html_e( 'Changes here can break the chat. Test after editing.', 'deliz-ai-advisor' ); ?></p>
</div>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Merge tags', 'deliz-ai-advisor' ); ?></th>
		<td>
			<?php foreach ( $tags as $t ) : ?>
				<code style="margin-right:8px;display:inline-block;margin-bottom:4px"><?php echo esc_html( $t ); ?></code>
			<?php endforeach; ?>
		</td>
	</tr>
	<tr>
		<th scope="row">
			<label for="deliz-system-prompt"><?php esc_html_e( 'System prompt template', 'deliz-ai-advisor' ); ?></label>
		</th>
		<td>
			<textarea id="deliz-system-prompt" name="prompts[system_prompt_template]" rows="20" class="large-text code"><?php echo esc_textarea( $current_value ); ?></textarea>
			<p>
				<button type="button" class="button" id="deliz-reset-prompt"><?php esc_html_e( 'Reset to default', 'deliz-ai-advisor' ); ?></button>
			</p>
		</td>
	</tr>

	<tr>
		<th scope="row"><?php esc_html_e( 'Off-topic replies', 'deliz-ai-advisor' ); ?></th>
		<td>
			<?php foreach ( array( 'he', 'ru', 'ar', 'en' ) as $code ) : ?>
				<p>
					<label><strong><?php echo esc_html( strtoupper( $code ) ); ?>:</strong>
						<input type="text" class="large-text" name="prompts[off_topic_response_<?php echo esc_attr( $code ); ?>]" value="<?php echo esc_attr( $p[ "off_topic_response_{$code}" ] ?? '' ); ?>">
					</label>
				</p>
			<?php endforeach; ?>
		</td>
	</tr>
</table>

<script>
(function(){
	const defaultPrompt = <?php echo wp_json_encode( PromptBuilder::DEFAULT_TEMPLATE ); ?>;
	const btn = document.getElementById('deliz-reset-prompt');
	const ta  = document.getElementById('deliz-system-prompt');
	if (btn && ta) {
		btn.addEventListener('click', function() {
			if (confirm('<?php echo esc_js( __( 'Reset system prompt to default?', 'deliz-ai-advisor' ) ); ?>')) {
				ta.value = defaultPrompt;
			}
		});
	}
})();
</script>
