<?php

namespace Themefic\UtrawpfWebhooks\Settings;

use Themefic\UtrawpfWebhooks\Helpers\Formatting;
use Themefic\UtrawpfWebhooks\Webhook;
use Themefic\UtrawpfWebhooks\WebhookAddon;
class FormBuilder {

    public $allowed_field_types = [];

    public function init() {

		add_filter( 'wpforms_builder_settings_sections',   [ $this, 'settings_panel_sidebar' ],   40, 2 ); // Done
        add_action( 'wpforms_form_settings_panel_content', [ $this, 'options_panel_content' ],   40 );

		add_action( 'wpforms_builder_enqueues',            [ $this, 'enqueue_assets' ],  10 ); // Done

		add_filter( 'wpforms_builder_strings',             [ $this, 'builder_strings' ], 40, 2 ); // Done
		add_filter( 'wpforms_get_form_fields_allowed',     [ $this, 'form_fields_allowed' ] ); // Done

    }

	public function options_panel_content( $builder_panel_settings ) {
		
		WebhookAddon::get_instance()->settings->set_props( $builder_panel_settings );
		$webhooks = WebhookAddon::get_instance()->settings->get_prop( 'webhooks' );

		echo wpforms_render(
			ULTRAWPF_WEBHOOKS_PATH . 'views/settings/section',
			[
				'next_id'             => max( array_keys( $webhooks ) ) + 1,
				'enable_control_html' => $this->get_enable_control_html(),
				'webhooks_html'       => $this->get_webhooks_html(),
				'add_new_btn_classes' => $this->get_html_class(
					[
						'wpforms-builder-settings-block-add',
						'wpforms-uawpf-webooks-add',
					]
				),
			]
		);
	}


	/**
	 * Retrieve a HTML for On/Off select control.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_enable_control_html() {

		$control = wpforms_panel_field(
			'toggle',
			'settings',
			'uawpf-webhooks_enable',
			WebhookAddon::get_instance()->settings->get_prop( 'form_data' ),
			esc_html__( 'Enable Ultra Addons Webhooks', 'ultrawpf-webhooks' ),
			[],
			false
		);

		return $control;
	}


	/**
	 * Retrieve a HTML for settings.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function get_webhooks_html() {

		$webhooks   = WebhookAddon::get_instance()->settings->get_prop( 'webhooks' );
		$default_id = min( array_keys( $webhooks ) );
		$result     = '';

		foreach ( $webhooks as $webhook_id => $webhook ) {
			$webhook['webhook_id'] = $webhook_id;

			$result .= $this->get_webhook_block( $webhook, $default_id === $webhook_id );
		}

		return $result;
	}

	/**
	 * Retrieve a HTML for setting block.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Added a `is_default` parameter.
	 *
	 * @param array   $webhook    Webhook data.
	 * @param boolean $is_default True if it's a default (first) webhook block.
	 *
	 * @return string
	 */
	protected function get_webhook_block( $webhook, $is_default ) {

		$webhook_id   = $webhook['webhook_id'];
		$form_data    = WebhookAddon::get_instance()->settings->get_prop( 'form_data' );
		$toggle_state = '<i class="fa fa-chevron-circle-up"></i>';
		$closed_state = '';

		if (
			! empty( $form_data['id'] ) &&
			'closed' === wpforms_builder_settings_block_get_state( $form_data['id'], $webhook_id, 'uawpf-webhook' )
		) {
			$toggle_state = '<i class="fa fa-chevron-circle-down"></i>';
			$closed_state = 'style="display:none;"';
		}

		$result = wpforms_render(
			ULTRAWPF_WEBHOOKS_PATH . 'views/settings/block',
			[
				'id'            => $webhook_id,
				'name'          => $webhook['name'],
				'toggle_state'  => $toggle_state,
				'closed_state'  => $closed_state,
				'fields'        => $this->get_webhook_fields( $webhook ),
				'block_classes' => $this->get_html_class(
					[
						'wpforms-builder-settings-block',
						'wpforms-builder-settings-block-webhook',
						$is_default ? 'wpforms-builder-settings-block-default' : '',
					]
				),
			]
		);

		/**
		 * Filter a HTML for setting block.
		 *
		 * @since 1.0.0
		 *
		 * @param string $result     HTML for setting block.
		 * @param array  $form_data  Form data.
		 * @param int    $webhook_id Webhook ID.
		 */
		return apply_filters( 'wpforms_webhooks_form_builder_get_webhook_block', $result, $form_data, $webhook_id );
	}

	/**
	 * Retrieve HTML for fields.
	 *
	 * @since 1.0.0
	 *
	 * @param array $webhook Webhook data.
	 *
	 * @return string
	 */
	protected function get_webhook_fields( $webhook ) {

		$webhook_id    = $webhook['webhook_id'];
		$webhooks      = WebhookAddon::get_instance()->settings->get_prop( 'webhooks' );
		$form_data     = WebhookAddon::get_instance()->settings->get_prop( 'form_data' );
		$form_fields   = wpforms_get_form_fields( $form_data );
		$form_fields   = empty( $form_fields ) && ! is_array( $form_fields ) ? [] : $form_fields;
		$allowed_types = implode( ' ', $this->allowed_field_types );

		$result = wpforms_panel_field(
			'text',
			'uawpf-webhooks',
			'url',
			$form_data,
			esc_html__( 'Request URL', 'wpforms-webhooks' ),
			[
				'parent'      => 'settings',
				'subsection'  => $webhook_id,
				'input_id'    => 'wpforms-panel-field-webhooks-request-url-' . $webhook_id,
				'input_class' => 'wpforms-required wpforms-required-url',
				'default'     => '',
				'placeholder' => esc_html__( 'Enter a Request URL&hellip;', 'wpforms-webhooks' ),
				'tooltip'     => esc_html__( 'Enter the URL to be used in the webhook request.', 'wpforms-webhooks' ),
			],
			false
		);

		$result .= wpforms_panel_field(
			'select',
			'uawpf-webhooks',
			'method',
			$form_data,
			esc_html__( 'Request Method', 'wpforms-webhooks' ),
			[
				'parent'     => 'settings',
				'subsection' => $webhook_id,
				'default'    => 'get',
				'options'    => uawpf_get_available_methods(),
				'tooltip'    => esc_html__( 'Select the HTTP method used for the webhook request.', 'wpforms-webhooks' ),
			],
			false
		);

		$result .= wpforms_panel_field(
			'select',
			'uawpf-webhooks',
			'format',
			$form_data,
			esc_html__( 'Request Format', 'wpforms-webhooks' ),
			[
				'parent'     => 'settings',
				'subsection' => $webhook_id,
				'default'    => 'json',
				'options'    => uawpf_get_available_formats(),
				'tooltip'    => esc_html__( 'Select the format for the webhook request.', 'wpforms-webhooks' ),
			],
			false
		);

		$result .= wpforms_render(
			ULTRAWPF_WEBHOOKS_PATH . 'views/settings/fields-mapping',
			[
				'title'         => esc_html__( 'Request Headers', 'wpforms-webhooks' ),
				'webhook_id'    => $webhook_id,
				'fields'        => $form_fields,
				'allowed_types' => $allowed_types,
				'meta'          => ! empty( $webhooks[ $webhook_id ]['headers'] ) ? $webhooks[ $webhook_id ]['headers'] : [ false ],
				'name'          => "settings[uawpf-webhooks][{$webhook_id}][headers]",
			]
		);

		$result .= wpforms_render(
			ULTRAWPF_WEBHOOKS_PATH . 'views/settings/fields-mapping',
			[
				'title'         => esc_html__( 'Request Body', 'wpforms-webhooks' ),
				'webhook_id'    => $webhook_id,
				'fields'        => $form_fields,
				'allowed_types' => $allowed_types,
				'meta'          => ! empty( $webhooks[ $webhook_id ]['body'] ) ? $webhooks[ $webhook_id ]['body'] : [ false ],
				'name'          => "settings[uawpf-webhooks][{$webhook_id}][body]",
			]
		);

		/**
		 * Filter HTML for fields.
		 *
		 * @since 1.0.0
		 *
		 * @param string $result     HTML for fields.
		 * @param array  $form_data  Form data.
		 * @param int    $webhook_id Webhook ID.
		 */
		return apply_filters( 'wpforms_webhooks_form_builder_get_webhook_fields', $result, $form_data, $webhook_id );
	}

	protected function get_html_class( $classes ) {

		if ( ! is_array( $classes ) ) {
			$classes = (array) $classes;
		}

		if ( ! WebhookAddon::get_instance()->settings->get_prop( 'is_enabled' ) ) {
			$classes[] = 'hidden';
		}

		$classes = array_unique( array_map( 'esc_attr', $classes ) );

		return implode( ' ', $classes );
	}


    public function settings_panel_sidebar( $sections, $form_data ) {

		$sections['uawpf-webhooks'] = esc_html__( 'Webhooks', 'ultrawpf-webhooks' );

		return $sections;
	}

	public function form_fields_allowed( $allowed_field_types ) {

		$this->allowed_field_types = ! empty( $allowed_field_types ) ? $allowed_field_types : [ 'all-fields' ];

		return $allowed_field_types;
	}

    public function builder_strings( $strings, $form ) {

		$strings['uawpf-webhook_prompt']        = esc_html__( 'Enter a webhook name', 'wpforms-webhooks' );
		$strings['uawpf-webhook_ph']            = '';
		$strings['uawpf-webhook_error']         = esc_html__( 'You must provide a webhook name', 'wpforms-webhooks' );
		$strings['uawpf-webhook_delete']        = esc_html__( 'Are you sure that you want to delete this webhook?', 'wpforms-webhooks' );
		$strings['uawpf-webhook_def_name']      = esc_html__( 'Unnamed Webhook', 'wpforms-webhooks' );
		$strings['uawpf-webhook_required_flds'] = esc_html__( 'Your form contains required Webhook settings that have not been configured. Please double-check and configure these settings to complete the connection setup.', 'wpforms-webhooks' );

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