<?php
namespace Themefic\UtrawpfWebhooks;

use Themefic\UtrawpfWebhooks\Settings\FormBuilder;
use Themefic\UtrawpfWebhooks\Settings\Settings;
use Themefic\UtrawpfWebhooks\Helpers\Formatting;
use WPForms\Tasks\Meta;
use WPForms\Helpers\Crypto;

class Process {

    	/**
	 * Action name for async task.
	 *
	 * @since 1.0.0
	 */
	const ACTION = 'uawpf_webhooks_process_delivery';

	/**
	 * Array of form fields.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected $fields = [];

	/**
	 * Submitted form content.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected $entry = [];

	/**
	 * Form data and settings.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected $form_data = [];

	/**
	 * ID of a saved entry.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	protected $entry_id;

	/**
	 * Initialize.
	 *
	 * @since 1.0.0
	 */
    public function init() {
        add_action( 'wpforms_process_complete', [ $this, 'process' ], 10, 4 );
		add_action( self::ACTION,               [ $this, 'delivery' ] );
    }

    public function process( $fields, $entry, $form_data, $entry_id ) {

		// Only run if we have webhooks.
		if (
			empty( $form_data['settings']['uawpf-webhooks'] ) ||
			! isset( $form_data['settings']['uawpf-webhooks_enable'] ) ||
			! wp_validate_boolean( $form_data['settings']['uawpf-webhooks_enable'] )
		) {
			return;
		}

		$this->fields    = $fields;
		$this->entry     = $entry;
		$this->form_data = $form_data;
		$this->entry_id  = $entry_id;

		foreach ( $form_data['settings']['uawpf-webhooks'] as $webhook ) {

			// // Check for conditional logic.
			// if ( ! $this->is_conditionals_passed( $webhook ) ) {
			// 	continue;
			// }

			wpforms()->obj( 'tasks' )
					 ->create( self::ACTION )->async()
					 ->params( $webhook, $this->fields, $this->form_data, $this->entry_id )
					 ->register();
		}
	}

    /**
	 * Process the addon async task - delivery the webhook.
	 *
	 * @since 1.0.0
	 *
	 * @param int $meta_id Task meta ID.
	 */
	public function delivery( $meta_id ) {

		$meta = $this->get_task_meta( $meta_id );

		// We expect a certain type and number of params.
		if ( ! is_array( $meta ) || count( $meta ) !== 4 ) {
			return;
		}

		// We expect a certain meta data structure for this task.
		list( $webhook, $this->fields, $this->form_data, $this->entry_id ) = $meta;

		$webhook      = new Webhook( $webhook );
		$webhook_data = $webhook->get_data();
		$start_time   = microtime( true );

		// Request method.
		$method = strtoupper( $webhook_data['method'] );

		// Request headers.
		$headers                 = $this->fill_http_header_params_value( $webhook_data['headers'] );
		$headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=utf-8';

		// Request body.
		$body = $this->fill_http_body_params_value( $webhook_data['body'] );

		// Format request data.
		if (
			$method !== 'GET' &&
			( $webhook_data['format'] === 'json' )
		) {

			// Add content type header.
			$headers['Content-Type'] = 'application/json; charset=utf-8';

			/**
			 * Filter options, that will be use for JSON encode.
			 *
			 * Maybe you need specific options like `JSON_UNESCAPED_UNICODE` etc.
			 * for resolve issue with Cyrillic alphabet.
			 *
			 * @see https://www.php.net/manual/en/function.json-encode.php
			 *
			 * @since 1.0.0
			 *
			 * @param mixed $json_encode_options Bitmask consisting of constants.
			 */
			$json_encode_options = apply_filters( 'uawpf_webhooks_process_delivery_json_encode_options', 0 );

			// Encode request body.
			$body = wp_json_encode( $body, $json_encode_options );
		}

		// Prepare request arguments.
		$options = [
			'method'      => $method,
			'timeout'     => MINUTE_IN_SECONDS,
			'redirection' => 0,
			'httpversion' => '1.0',
			'blocking'    => true,
			'user-agent'  => sprintf( 'wpforms-webhooks/%s', WEBHOOKS_VERSION ),
			'headers'     => $headers,
			'body'        => $body,
			'cookies'     => [],
		];

		/**
		 * Filter options used in an HTTP request.
		 *
		 * @since 1.0.0
		 *
		 * @param array $options      An array of HTTP request arguments.
		 * @param array $webhook_data Webhook data.
		 * @param array $fields       Fields data.
		 * @param array $fields       Form data.
		 * @param int   $entry_id     Entry ID.
		 */
		$options = (array) apply_filters( 'uawpf_webhooks_process_delivery_request_options', $options, $webhook_data, $this->fields, $this->form_data, $this->entry_id );

		// Add custom headers.
		$options['headers']['X-WPForms-Webhook-Id']        = $webhook_data['id'];
		// $options['headers']['X-WPForms-Webhook-Signature'] = $this->generate_signature( $body, 'sha256' );

		// Retrieve the raw response from a safe HTTP request.
		$response = wp_remote_request( $webhook_data['url'], $options );
		$duration = round( microtime( true ) - $start_time, 5 );

		$this->log_errors( $response, $webhook_data, $duration );

		/**
		 * Fire when Webhook was delivered or not.
		 *
		 * @since 1.0.0
		 *
		 * @param array          $options  An array of HTTP request arguments.
		 * @param \WP_Error|array $response Response data.
		 * @param array          $args     Additional arguments.
		 */
		do_action(
			'wpforms_webhooks_process_delivery_after',
			$options,
			$response,
			[
				'webhook'   => $webhook_data,
				'fields'    => $this->fields,
				'form_data' => $this->form_data,
				'entry_id'  => $this->entry_id,
			]
		);
	}

    	/**
	 * Fill in HTTP header params value a form submitted values.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params HTTP header parameters.
	 *
	 * @return array
	 */
	protected function fill_http_header_params_value( $params ) {

		$filled_params = [];

		foreach ( $params as $param_name => $param_value ) {

			// Determine if it a custom value.
			if ( is_array( $param_value ) && strpos( $param_name, 'custom_' ) === 0 ) {
				$param_name = substr_replace( $param_name, '', 0, 7 );
				$value      = ! empty( $param_value['secure'] ) ? Crypto::decrypt( $param_value['value'] ) : $param_value['value'];
				$value      = wpforms_process_smart_tags( $value, $this->form_data, $this->fields, $this->entry_id, 'webhooks-http-header' );
			} else {
				$value = isset( $this->fields[ $param_value ] ) ? $this->fields[ $param_value ]['value'] : '';
			}

			$value = Formatting::sanitize_header_value( $value );

			if ( ! wpforms_is_empty_string( $value ) ) {
				$filled_params[ $param_name ] = $value;
			}
		}

		// phpcs:disable WPForms.Comments.ParamTagHooks.InvalidParamTagsQuantity
		/**
		 * Filter HTTP header parameters.
		 *
		 * @since 1.0.0
		 * @since 1.2.0 Argument $fields added.
		 *
		 * @param array   $filled_params Sanitized HTTP header parameters.
		 * @param array   $params        HTTP header parameters before sanitization.
		 * @param Process $process       Process object.
		 * @param array   $fields        Array of form fields.
		 */
		return (array) apply_filters( 'uawpf_webhooks_process_fill_http_header_params_value', $filled_params, $params, $this, $this->fields );
		// phpcs:enable WPForms.Comments.ParamTagHooks.InvalidParamTagsQuantity
	}

    /**
	 * Fill in HTTP body params value a form submitted values.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params HTTP body parameters.
	 *
	 * @return array
	 */
	protected function fill_http_body_params_value( $params ) {

		$filled_params = [];

		foreach ( $params as $param_name => $param_value ) {

			// Determine if it a custom value.
			if ( is_array( $param_value ) && strpos( $param_name, 'custom_' ) === 0 ) {
				$param_name = substr_replace( $param_name, '', 0, 7 );
				$value      = ! empty( $param_value['secure'] ) ? Crypto::decrypt( $param_value['value'] ) : $param_value['value'];
				$value      = wpforms_process_smart_tags( $value, $this->form_data, $this->fields, $this->entry_id, 'webhooks-http-body' );
			} else {
				$value = isset( $this->fields[ $param_value ] ) ? $this->format( $this->fields[ $param_value ] ) : '';
			}

			$filled_params[ $param_name ] = $value;
		}

		// phpcs:disable WPForms.Comments.ParamTagHooks.InvalidParamTagsQuantity
		/**
		 * Filter HTTP body parameters.
		 *
		 * @since 1.0.0
		 * @since 1.2.0 Argument $fields added.
		 *
		 * @param array   $filled_params Sanitized HTTP body parameters.
		 * @param array   $params        HTTP body parameters before sanitization.
		 * @param Process $process       Process object.
		 * @param array   $fields        Array of form fields.
		 */
		return (array) apply_filters( 'uawpf_webhooks_process_fill_http_body_params_value', $filled_params, $params, $this, $this->fields );
		// phpcs:enable WPForms.Comments.ParamTagHooks.InvalidParamTagsQuantity
	}
    

    /**
	 * Apply a special formatting for some WPForms fields.
	 *
	 * @since 1.1.0
	 *
	 * @param array $field Field data.
	 *
	 * @return string|array
	 */
	protected function format( $field ) {

		// We want to prepare data for better using.
		$value = $this->maybe_has_multiple_delimiter( $field['value'], $field['type'] );

		switch ( $field['type'] ) {
			case 'date-time':
				$value = [
					'value' => $field['value'],
					'unix'  => $field['unix'],
				];
				break;

			case 'file-upload':
				$value = $this->get_field_file_value( $field );
				break;

			case 'payment-single':
			case 'payment-checkbox':
			case 'payment-multiple':
			case 'payment-select':
			case 'payment-total':
				$value = Formatting::decode_currency_symbols( $value );
				break;
		}

		return $value;
	}

    /**
	 * Prepare a submitted `file-uplaod` field value(s).
	 *
	 * @since 1.0.0
	 *
	 * @param array $field Field data.
	 *
	 * @return string|array Retrieve a value for the Classic or an array of values for the Modern File Upload field.
	 */
	protected function get_field_file_value( $field ) {

		if ( ! empty( $field['value_raw'] ) && is_array( $field['value_raw'] ) ) {
			$value = array_column( $field['value_raw'], 'value' );
		} else {
			$value = $field['value'];
		}

		return $value;
	}

    /**
	 * Replace newline and carriage-return symbols on `||`.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field_value Field value.
	 * @param string $field_type  Field type.
	 *
	 * @return string
	 */
	protected function maybe_has_multiple_delimiter( $field_value, $field_type ) {

		$multiple_field_types = [
			'address'          => true,
			'select'           => true,
			'checkbox'         => true,
			'likert_scale'     => true,
			'payment-checkbox' => true,
			'payment-multiple' => true,
			'payment-select'   => true,
		];

		if ( isset( $multiple_field_types[ $field_type ] ) ) {
			$field_value = str_replace( [ "\r\n", "\n" ], '||', $field_value );
		}

		return $field_value;
	}

    /**
	 * Generate a signature.
	 *
	 * @since 1.0.0
	 *
	 * @param string|array $body   Request body.
	 * @param string       $secret Secret used for generating signature.
	 *
	 * @return string
	 */
	private function generate_signature( $body, $secret ) {

		// Prepare a signature.
		// We do it like a Stripe - https://stripe.com/docs/webhooks/signatures.
		$timestamp = time();
		$payload   = sprintf( '%d.%s', $timestamp, is_array( $body ) ? wp_json_encode( $body ) : $body );
		$signature = hash_hmac( 'sha256', $payload, $secret );

		return "t={$timestamp},v={$signature}";
	}

    /**
	 * Get task meta data.
	 *
	 * @since 1.0.0
	 *
	 * @param int $meta_id Task meta ID.
	 *
	 * @return array|null Null when no data available.
	 */
	protected function get_task_meta( $meta_id ) {

		$task_meta = new Meta();
		$meta      = $task_meta->get( (int) $meta_id );

		// We should actually receive something.
		if ( empty( $meta ) || empty( $meta->data ) ) {
			return null;
		}

		return $meta->data;
	}

    /**
	 * Log an API-related error with all the data.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $response Response data, may be array or \WP_Error.
	 * @param array $webhook  Specific webhook data that errored.
	 * @param float $duration Request duration.
	 */
	protected function log_errors( $response, $webhook, $duration ) {

		if ( ! is_wp_error( $response ) ) {
			return;
		}

		wpforms_log(
			esc_html__( 'Webhook delivery failed.', 'wpforms-webhooks' ) . "(#{$this->entry_id})",
			[
				'response' => $response,
				'webhook'  => $webhook,
				'duration' => $duration,
			],
			[
				'type'    => [ 'addon', 'error' ],
				'parent'  => $this->entry_id,
				'form_id' => $this->form_data['id'],
			]
		);
	}

}