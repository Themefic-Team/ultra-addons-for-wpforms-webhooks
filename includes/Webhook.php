<?php

namespace Themefic\UtrawpfWebhooks;

use WPForms\Helpers\Crypto;

/**
 * Class Webhook.
 *
 * @since 1.0.0
 */
class Webhook {

	/**
	 * Webhook configuration data.
	 *
	 * @var array
	 */
	protected $config = [];

	/**
	 * Whether to encrypt secure fields.
	 *
	 * @var bool
	 */
	protected $encrypt_mode = false;

	/**
	 * Constructor.
	 *
	 * @param array $data Optional initial data.
	 * @param bool  $encrypt Whether to enable encryption.
	 */
	public function __construct( $data = [], $encrypt = false ) {
		$this->encrypt_mode = (bool) $encrypt;

		if ( ! empty( $data ) && is_array( $data ) ) {
			$this->prepare_data( $data );
		}
	}

	/**
	 * Prepare and sanitize webhook data.
	 *
	 * @param array $raw_data Raw webhook data.
	 */
	protected function prepare_data( $raw_data ) {

		$defaults = $this->get_defaults();
		$data     = wp_parse_args( $raw_data, $defaults );
		$methods  = uawpf_webhook_get_available_methods();

		$data['id']      = absint( $data['id'] ?? 0 );
		$data['name']    = sanitize_text_field( $data['name'] ?? '' );
		$data['url']     = esc_url_raw( $data['url'] ?? '' );
		$data['method']  = isset( $methods[ $data['method'] ] ) ? $data['method'] : $defaults['method'];

		$data['headers'] = $this->parse_request_params( $data['headers'] ?? [], 'header' );
		$data['body']    = $this->parse_request_params( $data['body'] ?? [], 'body' );

		$this->config = $data;
	}

	/**
	 * Return sanitized data.
	 *
	 * @return array
	 */
	public function get_data() {
		return $this->config;
	}

	/**
	 * Parse request parameters (headers or body).
	 *
	 * @param array  $params Parameter array.
	 * @param string $context Context string (header|body).
	 *
	 * @return array
	 */
	protected function parse_request_params( $params, $context ) {
		$output = [];

		foreach ( $params as $key => $value ) {

			$key = uawpf_webhook_clean_key( $key );

			if ( '' === $key ) {
				continue;
			}

			// Handle custom fields.
			if ( 0 === strpos( $key, 'custom_' ) && is_array( $value ) ) {
				$value = $this->prepare_custom_field( $value, $context );
			} elseif ( '' !== trim( $value ) ) {
				$value = sanitize_text_field( $value );
			} else {
				continue;
			}

			$output[ $key ] = $value;
		}

		return $output;
	}

	/**
	 * Prepare custom secure field.
	 *
	 * @param array  $field   Field array.
	 * @param string $context Context of usage.
	 *
	 * @return array|false
	 */
	protected function prepare_custom_field( $field, $context ) {

		if ( empty( $field['value'] ) && '0' !== $field['value'] ) {
			return false;
		}

		$value = ( 'header' === $context )
			? uawpf_webhook_clean_header_value( $field['value'] )
			: wp_kses_post( $field['value'] );

		$secure = ! empty( $field['secure'] );

		if ( $this->encrypt_mode && $secure ) {
			$value = Crypto::encrypt( $value );
		}

		return [
			'value'  => $value,
			'secure' => (bool) $secure,
		];
	}

	/**
	 * Default webhook structure.
	 *
	 * @return array
	 */
	public function get_defaults() {
		return [
			'id'      => 0,
			'name'    => __( 'UAWPF Webhook', 'uawpf-webhook' ),
			'method'  => 'post',
			'url'     => '',
			'headers' => [],
			'body'    => [],
		];
	}

}
