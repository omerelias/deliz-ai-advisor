<?php
/**
 * Appearance tab — colors, layout, typography.
 *
 * @package Deliz\AI\Advisor\Admin
 * @var array<string, mixed> $settings
 */

defined( 'ABSPATH' ) || exit;

$a = $settings['appearance'];
?>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Position', 'deliz-ai-advisor' ); ?></th>
		<td>
			<label><input type="radio" name="appearance[position]" value="bottom-right" <?php checked( $a['position'], 'bottom-right' ); ?>> <?php esc_html_e( 'Bottom right', 'deliz-ai-advisor' ); ?></label>
			&nbsp;&nbsp;
			<label><input type="radio" name="appearance[position]" value="bottom-left" <?php checked( $a['position'], 'bottom-left' ); ?>> <?php esc_html_e( 'Bottom left', 'deliz-ai-advisor' ); ?></label>
		</td>
	</tr>

	<tr>
		<th scope="row"><?php esc_html_e( 'Primary color', 'deliz-ai-advisor' ); ?></th>
		<td><input type="text" class="deliz-ai-color" name="appearance[primary_color]" value="<?php echo esc_attr( $a['primary_color'] ); ?>" data-default-color="#7c3aed"></td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Primary text', 'deliz-ai-advisor' ); ?></th>
		<td><input type="text" class="deliz-ai-color" name="appearance[primary_text_color]" value="<?php echo esc_attr( $a['primary_text_color'] ); ?>" data-default-color="#ffffff"></td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Background', 'deliz-ai-advisor' ); ?></th>
		<td><input type="text" class="deliz-ai-color" name="appearance[background_color]" value="<?php echo esc_attr( $a['background_color'] ); ?>" data-default-color="#ffffff"></td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Text color', 'deliz-ai-advisor' ); ?></th>
		<td><input type="text" class="deliz-ai-color" name="appearance[text_color]" value="<?php echo esc_attr( $a['text_color'] ); ?>" data-default-color="#1f2937"></td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'User bubble', 'deliz-ai-advisor' ); ?></th>
		<td>
			<input type="text" class="deliz-ai-color" name="appearance[user_bubble_color]" value="<?php echo esc_attr( $a['user_bubble_color'] ); ?>" data-default-color="#7c3aed">
			&nbsp;&nbsp;
			<?php esc_html_e( 'Text:', 'deliz-ai-advisor' ); ?>
			<input type="text" class="deliz-ai-color" name="appearance[user_text_color]" value="<?php echo esc_attr( $a['user_text_color'] ); ?>" data-default-color="#ffffff">
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Assistant bubble', 'deliz-ai-advisor' ); ?></th>
		<td>
			<input type="text" class="deliz-ai-color" name="appearance[assistant_bubble_color]" value="<?php echo esc_attr( $a['assistant_bubble_color'] ); ?>" data-default-color="#f3f4f6">
			&nbsp;&nbsp;
			<?php esc_html_e( 'Text:', 'deliz-ai-advisor' ); ?>
			<input type="text" class="deliz-ai-color" name="appearance[assistant_text_color]" value="<?php echo esc_attr( $a['assistant_text_color'] ); ?>" data-default-color="#1f2937">
		</td>
	</tr>

	<tr>
		<th scope="row"><?php esc_html_e( 'Font family', 'deliz-ai-advisor' ); ?></th>
		<td>
			<input type="text" name="appearance[font_family]" value="<?php echo esc_attr( $a['font_family'] ); ?>" class="regular-text" placeholder="inherit">
			<p class="description"><?php esc_html_e( 'CSS font-family string. "inherit" uses the theme font.', 'deliz-ai-advisor' ); ?></p>
		</td>
	</tr>

	<tr>
		<th scope="row"><?php esc_html_e( 'Border radius', 'deliz-ai-advisor' ); ?></th>
		<td>
			<input type="number" name="appearance[border_radius]" value="<?php echo esc_attr( $a['border_radius'] ); ?>" min="0" max="32" class="small-text"> px
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Panel width', 'deliz-ai-advisor' ); ?></th>
		<td>
			<input type="number" name="appearance[panel_width]" value="<?php echo esc_attr( $a['panel_width'] ); ?>" min="320" max="480" class="small-text"> px
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Panel height', 'deliz-ai-advisor' ); ?></th>
		<td>
			<input type="number" name="appearance[panel_height]" value="<?php echo esc_attr( $a['panel_height'] ); ?>" min="400" max="700" class="small-text"> px
		</td>
	</tr>

	<tr>
		<th scope="row"><?php esc_html_e( 'Shadow intensity', 'deliz-ai-advisor' ); ?></th>
		<td>
			<?php foreach ( array( 'none', 'light', 'medium', 'heavy' ) as $opt ) : ?>
				<label style="margin-right:12px">
					<input type="radio" name="appearance[shadow_intensity]" value="<?php echo esc_attr( $opt ); ?>" <?php checked( $a['shadow_intensity'], $opt ); ?>>
					<?php echo esc_html( ucfirst( $opt ) ); ?>
				</label>
			<?php endforeach; ?>
		</td>
	</tr>

	<tr>
		<th scope="row"><?php esc_html_e( 'Branding', 'deliz-ai-advisor' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="appearance[show_branding]" value="1" <?php checked( ! empty( $a['show_branding'] ) ); ?>>
				<?php esc_html_e( 'Show "Powered by Claude" at the bottom of the panel', 'deliz-ai-advisor' ); ?>
			</label>
		</td>
	</tr>
</table>
