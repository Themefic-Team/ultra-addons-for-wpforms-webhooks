<?php
namespace Themefic\UtrawpfWebhooks;

use WPForms\Tasks\Meta;
use WPForms\Helpers\Crypto;

class WebhookProcessor {

	protected $fields    = [];
	protected $form_data = [];
	protected $entry     = [];
	protected $entry_id  = 0;

    public function init() {
        add_action( 'wpforms_process_complete', [ $this, 'uawpf_handle_submission' ], 10, 4 );
		add_action( 'uawpf_webhooks_trigger_delivery',               [ $this, 'uawpf_execute_delivery' ] );
    }

	/**
	 * Handle WPForms submission and queue webhook tasks.
	 */
	public function uawpf_handle_submission($fields, $entry, $form_data, $entry_id) {

		if ( empty($form_data['settings']['uawpf-webhooks']) || empty($form_data['settings']['uawpf-webhooks_enable']) || ! wp_validate_boolean($form_data['settings']['uawpf-webhooks_enable'])
		) {
			return;
		}
		uawpf_print_r($entry);
		$this->fields    = $fields;
		$this->entry     = $entry;
		$this->form_data = $form_data;
		$this->entry_id  = $entry_id;

		foreach ($form_data['settings']['uawpf-webhooks'] as $webhook) {
			wpforms()
				->obj('tasks')
				->create('uawpf_webhooks_trigger_delivery')
				->async()
				->params($webhook, $this->fields, $this->form_data, $this->entry_id)
				->register();
		}
	}

	/**
	 * Execute webhook delivery from async queue.
	 */
	public function uawpf_execute_delivery($meta_id) {

		$data = $this->uawpf_fetch_task_data($meta_id);
		if (!is_array($data) || count($data) !== 4) {
			return;
		}

		list($webhook, $this->fields, $this->form_data, $this->entry_id) = $data;

		$webhook_obj = new Webhook($webhook);
		$webhook_data = $webhook_obj->get_data();

		$start = microtime(true);
		$method = strtoupper($webhook_data['method']);

		$headers = $this->uawpf_prepare_headers($webhook_data['headers']);
		$body    = $this->uawpf_prepare_body($webhook_data['body']);

		// Adjust content type for JSON format
		if ($method !== 'GET' && $webhook_data['format'] === 'json') {
			$headers['Content-Type'] = 'application/json; charset=utf-8';
			$encode_opts = apply_filters('uawpf_webhooks_json_encode_flags', 0);
			$body = wp_json_encode($body, $encode_opts);
		}

		$request_args = [
			'method'      => $method,
			'timeout'     => 60,
			'redirection' => 0,
			'httpversion' => '1.0',
			'blocking'    => true,
			'user-agent'  => sprintf('ultra-addons-for-wpforms-webhooks/%s', defined('WEBHOOKS_VERSION') ? WEBHOOKS_VERSION : '1.0.0'),
			'headers'     => $headers,
			'body'        => $body,
			'cookies'     => [],
		];

		$request_args = apply_filters(
			'uawpf_webhooks_request_args',
			$request_args,
			$webhook_data,
			$this->fields,
			$this->form_data,
			$this->entry_id
		);

		$request_args['headers']['X-UAWPF-Webhook-Id'] = $webhook_data['id'];

		$response = wp_remote_request($webhook_data['url'], $request_args);
		$duration = round(microtime(true) - $start, 5);

		$this->uawpf_log_errors($response, $webhook_data, $duration);

		do_action('uawpf_webhooks_after_delivery', $request_args, $response, [
			'webhook'   => $webhook_data,
			'fields'    => $this->fields,
			'form_data' => $this->form_data,
			'entry_id'  => $this->entry_id,
		]);
	}

	/**
	 * Replace placeholders in headers with dynamic values.
	 */
	protected function uawpf_prepare_headers($params) {
		$result = [];

		foreach ((array) $params as $key => $val) {
			if (is_array($val) && strpos($key, 'custom_') === 0) {
				$new_key = substr($key, 7);
				$decoded = !empty($val['secure']) ? Crypto::decrypt($val['value']) : $val['value'];
				$value   = wpforms_process_smart_tags($decoded, $this->form_data, $this->fields, $this->entry_id, 'uawpf-headers');
			} else {
				$new_key = $key;
				$value   = isset($this->fields[$val]) ? $this->fields[$val]['value'] : '';
			}

			$value = uawpf_webhook_sanitize_header_value($value);

			if ($value !== '') {
				$result[$new_key] = $value;
			}
		}

		return apply_filters('uawpf_webhooks_filled_headers', $result, $params, $this, $this->fields);
	}

	/**
	 * Replace placeholders in body parameters with form data.
	 */
	protected function uawpf_prepare_body($params) {
		$output = [];

		foreach ((array) $params as $key => $val) {
			if (is_array($val) && strpos($key, 'custom_') === 0) {
				$new_key = substr($key, 7);
				$decoded = !empty($val['secure']) ? Crypto::decrypt($val['value']) : $val['value'];
				$value   = wpforms_process_smart_tags($decoded, $this->form_data, $this->fields, $this->entry_id, 'uawpf-body');
			} else {
				$new_key = $key;
				$value   = isset($this->fields[$val]) ? $this->uawpf_format_field($this->fields[$val]) : '';
			}

			$output[$new_key] = $value;
		}

		return apply_filters('uawpf_webhooks_filled_body', $output, $params, $this, $this->fields);
	}

	/**
	 * Apply formatting depending on field type.
	 */
	protected function uawpf_format_field($field) {
		$value = $this->uawpf_maybe_flatten_field($field['value'], $field['type']);

		switch ($field['type']) {
			case 'date-time':
				$value = [
					'value' => $field['value'],
					'unix'  => $field['unix'],
				];
				break;

			case 'file-upload':
				$value = $this->uawpf_resolve_file_field($field);
				break;

			case 'payment-single':
			case 'payment-checkbox':
			case 'payment-multiple':
			case 'payment-select':
			case 'payment-total':
				$value = uawpf_webhook_decode_currency_symbols($value);
				break;
		}

		return $value;
	}

	protected function uawpf_resolve_file_field($field) {
		if (!empty($field['value_raw']) && is_array($field['value_raw'])) {
			return array_column($field['value_raw'], 'value');
		}
		return $field['value'];
	}

	protected function uawpf_maybe_flatten_field($value, $type) {
		$multi_types = [
			'address', 'select', 'checkbox', 'likert_scale',
			'payment-checkbox', 'payment-multiple', 'payment-select',
		];

		if (in_array($type, $multi_types, true)) {
			$value = str_replace(["\r\n", "\n"], '||', $value);
		}

		return $value;
	}

	protected function uawpf_fetch_task_data($meta_id) {
		$meta = (new Meta())->get((int) $meta_id);
		return !empty($meta->data) ? $meta->data : null;
	}

	protected function uawpf_log_errors($response, $webhook, $duration) {
		if (!is_wp_error($response)) {
			return;
		}

		wpforms_log(
			sprintf(__('Webhook delivery failed (Entry #%d)', 'ultrawpf-webhooks'), $this->entry_id),
			[
				'response' => $response,
				'webhook'  => $webhook,
				'duration' => $duration,
			],
			[
				'type'    => ['addon', 'error'],
				'parent'  => $this->entry_id,
				'form_id' => $this->form_data['id'] ?? 0,
			]
		);
	}

}