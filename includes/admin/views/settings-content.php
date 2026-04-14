<?php
/**
 * Content & Languages tab — per-language UI strings.
 *
 * @package Deliz\AI\Advisor\Admin
 * @var array<string, mixed> $settings
 */

defined( 'ABSPATH' ) || exit;

$c         = $settings['content'];
$languages = array(
	'he' => __( 'Hebrew', 'deliz-ai-advisor' ),
	'ru' => __( 'Russian', 'deliz-ai-advisor' ),
	'ar' => __( 'Arabic', 'deliz-ai-advisor' ),
	'en' => __( 'English', 'deliz-ai-advisor' ),
);
?>
<p>
	<button type="button" class="button" id="deliz-copy-from-hebrew">
		<?php esc_html_e( 'Copy from Hebrew to all languages', 'deliz-ai-advisor' ); ?>
	</button>
	<span class="description"><?php esc_html_e( 'Replaces RU/AR/EN fields with their Hebrew values (you can still edit after).', 'deliz-ai-advisor' ); ?></span>
</p>

<?php foreach ( $languages as $code => $label ) : ?>
	<h2 style="margin-top:24px"><?php echo esc_html( $label ); ?> <code>(<?php echo esc_html( $code ); ?>)</code></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Widget title', 'deliz-ai-advisor' ); ?></th>
			<td>
				<input type="text" class="regular-text" data-lang-group="title"
					name="content[title_<?php echo esc_attr( $code ); ?>]"
					value="<?php echo esc_attr( $c[ "title_{$code}" ] ?? '' ); ?>">
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Greeting', 'deliz-ai-advisor' ); ?></th>
			<td>
				<textarea rows="2" class="large-text" data-lang-group="greeting"
					name="content[greeting_<?php echo esc_attr( $code ); ?>]"
				><?php echo esc_textarea( $c[ "greeting_{$code}" ] ?? '' ); ?></textarea>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Placeholder', 'deliz-ai-advisor' ); ?></th>
			<td>
				<input type="text" class="regular-text" data-lang-group="placeholder"
					name="content[placeholder_<?php echo esc_attr( $code ); ?>]"
					value="<?php echo esc_attr( $c[ "placeholder_{$code}" ] ?? '' ); ?>">
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Suggested questions', 'deliz-ai-advisor' ); ?></th>
			<td>
				<?php
				$suggestions = $c[ "suggested_questions_{$code}" ] ?? array();
				if ( ! is_array( $suggestions ) ) {
					$suggestions = array();
				}
				$suggestions = array_pad( array_slice( $suggestions, 0, 3 ), 3, '' );
				foreach ( $suggestions as $i => $q ) :
					?>
					<input type="text" class="regular-text" style="margin-bottom:6px" data-lang-group="suggested"
						name="content[suggested_questions_<?php echo esc_attr( $code ); ?>][]"
						value="<?php echo esc_attr( $q ); ?>"
						placeholder="<?php echo esc_attr( sprintf( /* translators: %d: chip index */ __( 'Suggestion #%d', 'deliz-ai-advisor' ), $i + 1 ) ); ?>"><br>
				<?php endforeach; ?>
			</td>
		</tr>
	</table>
<?php endforeach; ?>
