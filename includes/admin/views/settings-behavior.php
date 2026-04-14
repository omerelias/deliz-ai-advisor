<?php
/**
 * Behavior tab.
 *
 * @package Deliz\AI\Advisor\Admin
 * @var array<string, mixed> $settings
 */

defined( 'ABSPATH' ) || exit;

$b = $settings['behavior'];
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Rate limit per IP per hour', 'deliz-ai-advisor' ); ?></th>
		<td>
			<input type="number" name="behavior[rate_limit_per_ip_per_hour]" value="<?php echo esc_attr( $b['rate_limit_per_ip_per_hour'] ); ?>" min="0" max="1000" class="small-text">
			<p class="description"><?php esc_html_e( 'Set to 0 to disable per-IP limiting.', 'deliz-ai-advisor' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Max message length', 'deliz-ai-advisor' ); ?></th>
		<td>
			<input type="number" name="behavior[max_message_length]" value="<?php echo esc_attr( $b['max_message_length'] ); ?>" min="50" max="2000" class="small-text"> chars
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Response cache', 'deliz-ai-advisor' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="behavior[enable_cache]" value="1" <?php checked( ! empty( $b['enable_cache'] ) ); ?>>
				<?php esc_html_e( 'Enable — identical first-turn questions reuse prior answers', 'deliz-ai-advisor' ); ?>
			</label>
			<br>
			<label style="display:inline-block;margin-top:6px">
				<?php esc_html_e( 'TTL (days):', 'deliz-ai-advisor' ); ?>
				<input type="number" name="behavior[cache_ttl_days]" value="<?php echo esc_attr( $b['cache_ttl_days'] ); ?>" min="1" max="365" class="small-text">
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Enabled languages', 'deliz-ai-advisor' ); ?></th>
		<td>
			<?php
			$enabled = (array) $b['enabled_languages'];
			foreach ( array( 'he' => 'Hebrew', 'ru' => 'Russian', 'ar' => 'Arabic', 'en' => 'English' ) as $code => $label ) :
				?>
				<label style="margin-right:12px">
					<input type="checkbox" name="behavior[enabled_languages][]" value="<?php echo esc_attr( $code ); ?>"
						<?php checked( in_array( $code, $enabled, true ) ); ?>>
					<?php echo esc_html( $label ); ?> (<?php echo esc_html( $code ); ?>)
				</label>
			<?php endforeach; ?>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Default language', 'deliz-ai-advisor' ); ?></th>
		<td>
			<select name="behavior[default_language]">
				<?php foreach ( array( 'he', 'ru', 'ar', 'en' ) as $code ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $b['default_language'], $code ); ?>>
						<?php echo esc_html( strtoupper( $code ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Used when detection is ambiguous.', 'deliz-ai-advisor' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Feedback buttons', 'deliz-ai-advisor' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="behavior[show_feedback_buttons]" value="1" <?php checked( ! empty( $b['show_feedback_buttons'] ) ); ?>>
				<?php esc_html_e( 'Show 👍 / 👎 below each assistant reply', 'deliz-ai-advisor' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Related products in context', 'deliz-ai-advisor' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="behavior[include_related_products_in_context]" value="1" <?php checked( ! empty( $b['include_related_products_in_context'] ) ); ?>>
				<?php esc_html_e( 'Include related products in the system prompt so the advisor can recommend pairings', 'deliz-ai-advisor' ); ?>
			</label>
			<br>
			<label style="display:inline-block;margin-top:6px">
				<?php esc_html_e( 'Max related products:', 'deliz-ai-advisor' ); ?>
				<input type="number" name="behavior[max_related_products]" value="<?php echo esc_attr( $b['max_related_products'] ); ?>" min="1" max="20" class="small-text">
			</label>
		</td>
	</tr>
</table>
