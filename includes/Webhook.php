<?php

namespace Themefic\UtrawpfWebhooks;

use Themefic\UtrawpfWebhooks\Settings\FormBuilder;
use Themefic\UtrawpfWebhooks\Settings\Settings;

final class Webhook {
    
    public $form_builder;
    public $settings;
    public $process;

    public static function get_instance() {

		static $instance;

		if ( ! $instance ) {
			$instance = new self();

			$instance->run_webhooks();
		}

		return $instance;
	}

    public function run_webhooks() {

        add_action( 'wpforms_loaded', array( $this, 'load_webhooks_components' ), 15 );
        add_filter( 'wpforms_helpers_templates_include_html_located', [ $this, 'templates' ], 10, 4 );

    }

    public function load_webhooks_components() {

		if (
			wpforms_is_admin_page( 'builder' ) ||
			wp_doing_ajax()
		) {
			$this->form_builder = new FormBuilder();
			$this->settings     = new Settings();

			$this->form_builder->init();
			$this->settings->init();
		}

		$this->process = new Process();
		$this->process->init();
	}

	public function templates( $located, $template, $args, $extract ) {

		if (
			( 0 === strpos( $template, ULTRAWPF_WEBHOOKS_PATH ) ) &&
			is_readable( $template )
		) {
			return $template;
		}

		return $located;
	}

	public function get_available_methods() {

		return [
			'get'    => 'GET',
			'post'   => 'POST',
			'put'    => 'PUT',
			'patch'  => 'PATCH',
			'delete' => 'DELETE',
		];
	}

	public function get_available_formats() {

		return [
			'json' => esc_html__( 'JSON', 'ultrawpf-webhooks' ),
			'form' => esc_html__( 'FORM', 'ultrawpf-webhooks' ),
		];
	}

}