<?php

namespace Themefic\UtrawpfWebhooks\Settings;

use Themefic\UtrawpfWebhooks\WebhookAddon;

class FormBuilderUI {

    public $allowed_field_types = [];

    public function init() {

		add_filter( 'wpforms_builder_settings_sections',   [ $this, 'add_settings_sidebar_section' ],   40, 2 );
        add_action( 'wpforms_form_settings_panel_content', [ $this, 'render_settings_panel_content' ],   40 );
		add_action( 'wpforms_builder_enqueues',            [ $this, 'enqueue_builder_assets' ],  10 ); 
		add_filter( 'wpforms_builder_strings',             [ $this, 'inject_builder_strings' ], 40, 2 ); 
		add_filter( 'wpforms_get_form_fields_allowed',     [ $this, 'filter_allowed_fields' ] ); 

    }

	/**
	 * Output the settings panel content for Ultra Addons Webhooks.
	 */
	public function render_settings_panel_content( $builder_panel_settings ) {
		
		WebhookAddon::instance()->settings_manager->set_context( $builder_panel_settings );
		?>
			<div class="wpforms-panel-content-section wpforms-panel-content-section-uawpf-webhooks">
				<div class="wpforms-panel-content-section-title">
					<?php esc_html_e( 'Ultra Addons Webhooks', 'ultrawpf-webhooks' ); ?>
				</div>

				<?php
					// Keep original output. CSS expects WPForms classes.
					echo $this->render_enable_toggle();
					echo $this->render_webhooks_html();
				?>
			</div>
		<?php

	}

	/**
	 * Create enable/disable toggle HTML.
	 */
	protected function render_enable_toggle() {

		return wpforms_panel_field(
			'toggle',
			'settings',
			'uawpf-webhooks_enable',
			WebhookAddon::instance()->settings_manager->get_prop( 'form_data' ),
			esc_html__( 'Enable Ultra Addons Webhooks', 'ultrawpf-webhooks' ),
			[],
			false
		);
	}

	/**
	 * Render all existing webhook blocks.
	 */
	protected function render_webhooks_html() {

		$webhooks   = WebhookAddon::instance()->settings_manager->get_prop( 'webhooks' );
		$default_id = min( array_keys( $webhooks ) );
		$result     = '';

		foreach ( $webhooks as $webhook_id => $webhook ) {
			$webhook['webhook_id'] = $webhook_id;
			$result .= $this->render_webhook_block( $webhook, $default_id === $webhook_id );
		}

		return $result;
	}

	/**
	 * Render a single webhook block.
	 */
	protected function render_webhook_block( $webhook, $is_default ) {

		$webhook_id   = $webhook['webhook_id'];
		$form_data    = WebhookAddon::instance()->settings_manager->get_prop( 'form_data' );
		$toggle_icon  = '<i class="fa fa-chevron-circle-up"></i>';
		$closed_style = '';

		if ( ! empty( $form_data['id'] ) &&
			'closed' === wpforms_builder_settings_block_get_state( $form_data['id'], $webhook_id, 'uawpf-webhook' )
		) {
			$toggle_icon  = '<i class="fa fa-chevron-circle-down"></i>';
			$closed_style = 'style="display:none;"';
		}

		$result = wpforms_render(
			ULTRAWPF_WEBHOOKS_PATH . 'render/block',
			[
				'id'            => $webhook_id,
				'name'          => $webhook['name'],
				'toggle_state'  => $toggle_icon,
				'closed_state'  => $closed_style,
				'fields'        => $this->render_webhook_fields( $webhook ),
				'block_classes' => $this->compose_classnames( [
					'wpforms-builder-settings-block',
					'wpforms-builder-settings-block-uawpf-webhook',
					$is_default ? 'wpforms-builder-settings-block-default' : '',
				] ),
			]
		);

		return apply_filters( 'uawpf_webhooks_render_block_html', $result, $form_data, $webhook_id );
	}

	/**
	 * Render webhook fields (URL, method, headers, body, etc).
	 */
	protected function render_webhook_fields( $webhook ) {

		$webhook_id    = $webhook['webhook_id'];
		$webhooks      = WebhookAddon::instance()->settings_manager->get_prop( 'webhooks' );
		$form_data     = WebhookAddon::instance()->settings_manager->get_prop( 'form_data' );
		$form_fields   = wpforms_get_form_fields( $form_data );
		$form_fields   = empty( $form_fields ) && ! is_array( $form_fields ) ? [] : $form_fields;
		$allowed_types = implode( ' ', $this->allowed_field_types );


		// Request URL.
		$result  = wpforms_panel_field(
			'text',
			'uawpf-webhooks',
			'url',
			$form_data,
			esc_html__( 'Endpoint Request URL', 'ultrawpf-webhooks' ),
			[
				'parent'      => 'settings',
				'subsection'  => $webhook_id,
				'input_id'    => 'uawpf-webhooks-url-' . $webhook_id,
				'input_class' => 'wpforms-required wpforms-required-url',
				'placeholder' => esc_html__( 'Enter the webhook endpoint URL…', 'ultrawpf-webhooks' ),
				'tooltip'     => esc_html__( 'Specify the endpoint URL to send webhook requests.', 'ultrawpf-webhooks' ),
			],
			false
		);

		// Request Method.
		$result .= wpforms_panel_field(
			'select',
			'uawpf-webhooks',
			'method',
			$form_data,
			esc_html__( 'Select Request Method', 'ultrawpf-webhooks' ),
			[
				'parent'     => 'settings',
				'subsection' => $webhook_id,
				'default'    => 'get',
				'options'    => uawpf_webhook_get_available_methods(),
				'tooltip'    => esc_html__( 'Choose the HTTP method for this webhook.', 'ultrawpf-webhooks' ),
			],
			false
		);

		// Request Format.
		$result .= wpforms_panel_field(
			'select',
			'uawpf-webhooks',
			'format',
			$form_data,
			esc_html__( 'Select Request Format', 'ultrawpf-webhooks' ),
			[
				'parent'     => 'settings',
				'subsection' => $webhook_id,
				'default'    => 'json',
				'options'    => uawpf_webhook_get_available_formats(),
				'tooltip'    => esc_html__( 'Select the format of the request payload.', 'ultrawpf-webhooks' ),
			],
			false
		);

		// Headers Mapping.
		$result .= wpforms_render(
			ULTRAWPF_WEBHOOKS_PATH . 'render/repeater-fields',
			[
				'title'         => esc_html__( 'Request Headers', 'ultrawpf-webhooks' ),
				'webhook_id'    => $webhook_id,
				'fields'        => $form_fields,
				'allowed_types' => $allowed_types,
				'meta'          => ! empty( $webhooks[ $webhook_id ]['headers'] ) ? $webhooks[ $webhook_id ]['headers'] : [ false ],
				'name'          => "settings[uawpf-webhooks][{$webhook_id}][headers]",
			]
		);

		// Body Mapping.
		$result .= wpforms_render(
			ULTRAWPF_WEBHOOKS_PATH . 'render/repeater-fields',
			[
				'title'         => esc_html__( 'Request Body', 'ultrawpf-webhooks' ),
				'webhook_id'    => $webhook_id,
				'fields'        => $form_fields,
				'allowed_types' => $allowed_types,
				'meta'          => ! empty( $webhooks[ $webhook_id ]['body'] ) ? $webhooks[ $webhook_id ]['body'] : [ false ],
				'name'          => "settings[uawpf-webhooks][{$webhook_id}][body]",
			]
		);

		return apply_filters( 'uawpf_webhooks_render_fields_html', $result, $form_data, $webhook_id );
	}


	/**
	 * Generate HTML class attribute from array.
	 */
	protected function compose_classnames( $classes ) {

		if ( ! is_array( $classes ) ) {
			$classes = (array) $classes;
		}

		if ( ! WebhookAddon::instance()->settings_manager->get_prop( 'is_enabled' ) ) {
			$classes[] = 'hidden';
		}

		return implode( ' ', array_unique( array_map( 'esc_attr', $classes ) ) );
	}


    public function add_settings_sidebar_section( $sections, $form_data ) {

		$sections['uawpf-webhooks'] = esc_html__( 'Webhooks', 'ultrawpf-webhooks' );

		return $sections;
	}

	public function filter_allowed_fields( $allowed_field_types ) {

		if ( ! is_array( $allowed_field_types ) ) {
			$allowed_field_types = [];
		}

		// Add your custom UAWPF field types here.
		$custom_field_types = [
			'uawpf-url',
			'uawpf_phone',
			'file',
		];

		$allowed_field_types = array_unique( array_merge( $allowed_field_types, $custom_field_types ) );

		$this->allowed_field_types = ! empty( $allowed_field_types ) ? $allowed_field_types : [ 'all-fields' ];

		return $allowed_field_types;
	}

    public function inject_builder_strings( $strings, $form ) {

		$strings['uawpf-webhook_prompt']        = esc_html__( 'Provide a webhook name', 'ultrawpf-webhooks' );
		$strings['uawpf-webhook_ph']            = '';
		$strings['uawpf-webhook_error']         = esc_html__( 'You must provide a webhook name to continue', 'ultrawpf-webhooks' );
		$strings['uawpf-webhook_delete']        = esc_html__( 'Are you sure to delete this webhook?', 'ultrawpf-webhooks' );
		$strings['uawpf-webhook_def_name']      = esc_html__( 'Uawpf Webhook', 'ultrawpf-webhooks' );
		$strings['uawpf_webhook_required_flds'] = esc_html__( 'Please configure the required webhook settings.', 'ultrawpf-webhooks' );

		return $strings;
	}

    /**
	 * Enqueue admin builder assets
	 *
	 * @since 1.0.0
	 */
	public function enqueue_builder_assets() {

		wp_enqueue_script(
			'ultrawpf-webhooks-builder',
			ULTRAWPF_WEBHOOKS_URL . "assets/js/webhook-builder.js",
			[ 'wpforms-builder' ],
			ULTRAWPF_WEBHOOKS_VERSION,
			true
		);

		wp_enqueue_style(
			'ultrawpf-webhooks-builder',
			ULTRAWPF_WEBHOOKS_URL . "assets/css/webhook-builder.css",
			[ 'wpforms-builder' ],
			ULTRAWPF_WEBHOOKS_VERSION
		);
	}

}