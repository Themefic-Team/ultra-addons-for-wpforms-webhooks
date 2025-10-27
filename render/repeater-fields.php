<?php
/**
 * Fields mapping table.
 *
 * @var array $args
 */

// Exit if accessed directly.
use WPForms\Helpers\Crypto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$webhook_id = $args['webhook_id'];
$fields     = ! empty( $args['fields'] ) ? $args['fields'] : [];
$meta       = isset( $args['meta'] ) ? $args['meta'] : [ false ];
$base_name  = $args['name'] ?? 'settings[uawpf-webhooks][' . $webhook_id . '][params]';
$allowed    = $args['allowed_types'] ?? '';

?>

<div class="uawpf-field-map-table wpforms-field-map-table">
	<h3><?php echo wp_kses_post( $args['title'] ); ?></h3>
	<table>
		<tbody>
		<?php foreach ( $meta as $key => $value ) :

			$flds_name   = [
				'source' => '',
				'custom' => '',
				'secure' => '',
			];
			$extra_class = '';
			$is_custom   = false;

			$key = ( $value !== false ) ? uawpf_webhook_sanitize_header_name( $key ) : '';

			if ( ! wpforms_is_empty_string( $key ) ) {
				$is_custom = ( 0 === strpos( $key, 'custom_' ) && is_array( $value ) );

				if ( $is_custom ) {
					$key                 = substr_replace( $key, '', 0, 7 );
					// decrypt only if Crypto available
					$value['value']      = ( ! empty( $value['secure'] ) && class_exists( '\WPForms\Helpers\Crypto' ) ) ? Crypto::decrypt( $value['value'] ) : $value['value'];
					$flds_name['custom'] = sprintf( '%1$s[custom_%2$s][value]', $args['name'], $key );
					$flds_name['secure'] = sprintf( '%1$s[custom_%2$s][secure]', $args['name'], $key );

					$extra_class = ' uawpf-field-is-custom-value';

				} else {
					$flds_name['source'] = sprintf( '%1$s[%2$s]', $args['name'], $key );
				}
			}

			$is_secure_checked = $is_custom && $value['value'] !== false && ! empty( $value['secure'] );
		?>
			<tr>
				<td class="key uawpf-field-map-key">
					<input type="text" value="<?php echo esc_attr( $key ); ?>" placeholder="<?php esc_attr_e( 'Enter a parameter key&hellip;', 'ultrawpf-webhooks' ); ?>" class="http-key-source uawpf-http-key-source" autocomplete="off">
				</td>
				<td class="field uawpf-field-map-field<?php echo esc_attr( $extra_class ); ?>">
					<div class="wpforms-field-map-wrap uawpf-field-map-wrap">
						<div class="wpforms-field-map-wrap-l uawpf-field-map-wrap-l">
							<select class="key-destination uawpf-field-map-select uawpf-field-map-select" name="<?php echo esc_attr( $flds_name['source'] ); ?>" data-name="<?php echo esc_attr( $args['name'] ); ?>" data-suffix="[{source}]" data-field-map-allowed="<?php echo esc_attr( $args['allowed_types'] ); ?>" data-custom-value-support="true">
								<option value=""><?php esc_html_e( '--- Select Field ---', 'ultrawpf-webhooks' ); ?></option>
								<?php
								if ( ! empty( $args['fields'] ) ) {
									foreach ( $args['fields'] as $field_id => $field ) {
										$label    = ! empty( $field['label'] )
													? $field['label']
													: sprintf( /* translators: %d - field ID. */
														__( 'Field #%d', 'ultrawpf-webhooks' ),
														absint( $field_id )
													);
										$selected = ! $is_custom ? selected( $value, $field_id, false ) : '';

										printf( '<option value="%s" %s>%s</option>', esc_attr( $field['id'] ), esc_attr( $selected ), esc_html( $label ) );
									}
								}
								?>
								<option value="custom_value" class="wpforms-field-map-option-custom-value uawpf-field-map-option-custom-value"><?php esc_html_e( 'Add Custom Value', 'ultrawpf-webhooks' ); ?></option>
							</select>
							<div class="wpforms-field-map-custom-wrap uawpf-field-map-custom-wrap">
								<label class="uawpf-field-map-is-secure uawpf-field-map-is-secure <?php echo $is_secure_checked ? 'disabled' : ''; ?>">
									<input class="uawpf-field-map-is-secure-checkbox uawpf-field-map-is-secure-checkbox" name="<?php echo esc_attr( $flds_name['secure'] ); ?>" data-suffix="[custom_{source}][secure]" type="checkbox" value="1" <?php checked( $is_secure_checked ); ?> autocomplete="off">
								</label>
								<input class="uawpf-field-map-custom-value wpforms-smart-tags-enabled uawpf-field-map-custom-value"
									name="<?php echo esc_attr( $flds_name['custom'] ); ?>"
									data-suffix="[custom_{source}][value]" type="text"
									data-type="other"
									placeholder="<?php esc_html_e( 'Custom Value', 'ultrawpf-webhooks' ); ?>"
									value="<?php echo esc_attr( $is_custom ? $value['value'] : '' ); ?>" <?php wpforms_readonly( $is_secure_checked ); ?>>
								<a href="#" class="uawpf-field-map-custom-value-close uawpf-field-map-custom-value-close fa fa-close"></a>
							</div>
						</div>
					</div>
				</td>
				<td class="actions uawpf-field-map-actions">
					<a class="add uawpf-field-map-add" href="#"><i class="fa fa-plus-square"></i></a>
					<a class="remove uawpf-field-map-remove" href="#"><i class="fa fa-minus-square"></i></a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>