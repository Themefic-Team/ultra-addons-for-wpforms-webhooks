<?php

namespace Themefic\UtrawpfWebhooks;

use Themefic\UtrawpfWebhooks\Settings\FormBuilderUI;
use Themefic\UtrawpfWebhooks\Settings\SettingsManager;

final class WebhookAddon {
    
	private static $instance = null;
	public $settings_manager;
	public $processor;

	/**
	 * Get singleton instance.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Bootstrap the addon.
	 */
    public function boot() {

        add_action( 'wpforms_loaded', array( $this, 'register_components' ), 15 );
        add_filter( 'wpforms_helpers_templates_include_html_located', [ $this, 'override_templates' ], 10, 4 );

    }

	/**
	 * Load and initialize components.
	 */
	public function register_components() {

		if ( wpforms_is_admin_page( 'builder' ) || wp_doing_ajax() ) {
			$builder_ui             = new FormBuilderUI();
			$this->settings_manager = new SettingsManager();

			$builder_ui->init();
			$this->settings_manager->init();
		}

		$this->processor = new WebhookProcessor();
		$this->processor->init();
	}

	/**
	 * Allow loading our own template files.
	 */
	public function override_templates( $located, $template, $args, $extract ) {
		if ( strpos( $template, ULTRAWPF_WEBHOOKS_PATH ) === 0 && is_readable( $template ) ) {
			return $template;
		}
		return $located;
	}

}