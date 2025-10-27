<?php
if ( ! defined( 'ABSPATH' ) ) { exit;}

?>
<div class="wpforms-panel-content-section wpforms-panel-content-section-uawpf-webhooks">
	<div class="wpforms-panel-content-section-title">
		<?php esc_html_e( 'Ultra Addons Webhooks', 'ultrawpf-webhooks' ); ?>
		<button type="button"
			class="<?php echo esc_attr( $args['add_new_btn_classes'] ); ?> wpforms-uawpf-webooks-add"
			data-block-type="uawpf-webhook"
			data-next-id="<?php echo absint( $args['next_id'] ); ?>">
			<?php esc_html_e( 'Add New Webhook', 'ultrawpf-webhooks' ); ?>
		</button>
	</div>

	<?php
		// Keep original output. CSS expects WPForms classes.
		echo $args['enable_control_html'];
		echo $args['webhooks_html'];
	?>
</div>
