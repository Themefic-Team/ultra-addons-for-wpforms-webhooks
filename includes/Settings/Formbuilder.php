<?php

namespace Themefic\UtrawpfWebhooks\Settings;


class FormBuilder {

    public $allowed_field_types = [];

    public function init() {

        add_action( 'wpforms_form_settings_panel_content', [ $this, 'panel_content' ],   40 );
		add_action( 'wpforms_builder_enqueues',            [ $this, 'enqueue_assets' ],  10 );

		add_filter( 'wpforms_builder_settings_sections',   [ $this, 'panel_sidebar' ],   40, 2 );
		add_filter( 'wpforms_builder_strings',             [ $this, 'builder_strings' ], 40, 2 );
		add_filter( 'wpforms_get_form_fields_allowed',     [ $this, 'form_fields_allowed' ] );

    }

    /**
	 * Add a content for `Webhooks` panel.
	 *
	 * @since 1.0.0
	 *
	 * @param \WPForms_Builder_Panel_Settings $builder_panel_settings WPForms_Builder_Panel_Settings object.
	 */
	public function panel_content( $builder_panel_settings ) {

		// wpforms_webhooks()->settings->set_props( $builder_panel_settings );
		// $webhooks = wpforms_webhooks()->settings->get_prop( 'webhooks' );

		// echo wpforms_render(
		// 	WPFORMS_WEBHOOKS_PATH . 'views/settings/section',
		// 	[
		// 		'next_id'             => max( array_keys( $webhooks ) ) + 1,
		// 		'enable_control_html' => $this->get_enable_control_html(),
		// 		'webhooks_html'       => $this->get_webhooks_html(),
		// 		'add_new_btn_classes' => $this->get_html_class(
		// 			[
		// 				'wpforms-builder-settings-block-add',
		// 				'wpforms-webooks-add',
		// 			]
		// 		),
		// 	]
		// );
	}


    public function panel_sidebar( $sections, $form_data ) {

		$sections['uawpf-webhooks'] = esc_html__( 'Webhooks', 'ultrawpf-webhooks' );

		return $sections;
	}

    /**
	 * Save allowed field types to property.
	 *
	 * @since 1.0.0
	 *
	 * @param array $allowed_field_types White list of field types to allow.
	 *
	 * @return array
	 */
	public function form_fields_allowed( $allowed_field_types ) {

		$this->allowed_field_types = ! empty( $allowed_field_types ) ? $allowed_field_types : [ 'all-fields' ];

		return $allowed_field_types;
	}

    public function builder_strings( $strings, $form ) {

		$strings['webhook_prompt']        = esc_html__( 'Enter a webhook name', 'wpforms-webhooks' );
		$strings['webhook_ph']            = '';
		$strings['webhook_error']         = esc_html__( 'You must provide a webhook name', 'wpforms-webhooks' );
		$strings['webhook_delete']        = esc_html__( 'Are you sure that you want to delete this webhook?', 'wpforms-webhooks' );
		$strings['webhook_def_name']      = esc_html__( 'Unnamed Webhook', 'wpforms-webhooks' );
		$strings['webhook_required_flds'] = esc_html__( 'Your form contains required Webhook settings that have not been configured. Please double-check and configure these settings to complete the connection setup.', 'wpforms-webhooks' );

		return $strings;
	}

    /**
	 * Enqueue admin builder assets
	 *
	 * @since 1.0.0
	 */
	public function enqueue_assets() {

		wp_enqueue_script(
			'ultrawpf-webhooks-builder',
			ULTRAWPF_WEBHOOKS_URL . "assets/js/webhook-builder.js",
			[ 'wpforms-builder' ],
			WEBHOOKS_VERSION,
			true
		);

		wp_enqueue_style(
			'ultrawpf-webhooks-builder',
			ULTRAWPF_WEBHOOKS_URL . "assets/css/webhook-builder.css",
			[ 'wpforms-builder' ],
			WEBHOOKS_VERSION
		);
	}

}