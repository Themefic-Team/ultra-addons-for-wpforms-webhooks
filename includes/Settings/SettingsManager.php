<?php

namespace Themefic\UtrawpfWebhooks\Settings;

use Themefic\UtrawpfWebhooks\Webhook;

class SettingsManager {

	protected $form_data  = [];
	protected $webhooks   = [];
	protected $is_enabled = false;

    public function init() {
        if ( empty( $this->webhooks ) ) {
			$this->webhooks[1] = ( new Webhook() )->get_defaults();
		}

		add_filter( 'wpforms_save_form_args', [ $this, 'save_form_data' ], 11, 3 );
    }
	
	/**
	 * Sanitize webhook settings before saving form.
	 */
	public function save_form_data( $form, $data, $args ) {

		if ( empty( $data['settings']['uawpf-webhooks'] ) ) {
			return $form;
		}

		$form_json = json_decode( stripslashes( $form['post_content'] ), true );
		
		foreach ( $form_json['settings']['uawpf-webhooks'] as $id => &$hook_data ) {
			if ( empty( $hook_data['url'] ) || ! uawpf_webhook_is_url( $hook_data['url'] ) ) {
				unset( $form_json['settings']['uawpf-webhooks'][ $id ] );
				continue;
			}

			$hook_data['id'] = $id;
			$webhook_obj     = new Webhook( $hook_data, true );
			$hook_data       = $webhook_obj->get_data();
		}
		unset( $hook_data );

		$form['post_content'] = wpforms_encode( $form_json );

		return $form;
	}

	/**
	 * Assign builder context data.
	 */
	public function set_context( $panel ) {
		if ( ! ( $panel instanceof \WPForms_Builder_Panel_Settings ) ) {
			return;
		}

		$this->form_data = $panel->form_data;

		if ( empty( $this->form_data['settings'] ) ) {
			return;
		}

		$settings = $this->form_data['settings'];

		if ( isset( $settings['uawpf-webhooks_enable'] ) ) {
			$this->is_enabled = (bool) $settings['uawpf-webhooks_enable'];
		}

		if ( ! empty( $settings['uawpf-webhooks'] ) ) {
			$this->webhooks = $settings['uawpf-webhooks'];
		}
	}

	public function get_prop( $key ) {
		return property_exists( $this, $key ) ? $this->{$key} : null;
	}

}

