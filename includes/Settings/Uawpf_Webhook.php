<?php
namespace Themefic\UtrawpfWebhooks\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Uawpf_Webhook {

    protected $allowed_field_types = [];
    const LOG_OPTION = 'uawpf_webhooks_logs';
    const QUEUE_OPTION = 'uawpf_webhooks_queue';

    public function init() {
        // Builder panel (Lite-compatible hooks)
        add_action( 'wpforms_form_settings_panel_content', [ $this, 'settings_panel_content' ], 50 );
        add_filter( 'wpforms_builder_settings_sections', [ $this, 'panel_sidebar' ], 50, 2 );
        add_action( 'wpforms_builder_enqueues', [ $this, 'enqueue_assets' ], 10 );

        add_filter( 'wpforms_builder_strings', [ $this, 'builder_strings' ], 50, 2 );
        add_filter( 'wpforms_get_form_fields_allowed', [ $this, 'form_fields_allowed' ] );

        // Normalize keys on save (defensive)
        add_filter( 'wpforms_form_settings_update', [ $this, 'normalize_webhooks_on_save' ], 10, 2 );
        // sometimes older plugins hook different filters; keep a fallback if necessary:
        add_filter( 'wpforms_save_form_args', [ $this, 'normalize_webhooks_on_save_save_args' ], 11, 3 );

        // Process submissions
        add_action( 'wpforms_process_complete', [ $this, 'process_submission' ], 10, 4 );

        // Cron fallback: process queue
        add_action( 'uawpf_webhooks_process_queue', [ $this, 'process_queue' ] );
    }

    /**
     * Add Webhooks tab in builder sidebar
     */
    public function panel_sidebar( $sections, $form_data ) {
        $sections['uawpf-webhooks'] = esc_html__( 'Webhooks', 'uawpf-webhooks' );
        return $sections;
    }

    /**
     * Render the panel content (uses builder_panel_settings object from Lite)
     *
     * @param object $builder_panel_settings
     */
    public function settings_panel_content( $builder_panel_settings ) {

        $form_data = isset( $builder_panel_settings->form_data ) ? $builder_panel_settings->form_data : array();
        $webhooks = isset( $form_data['settings']['uawpf_webhooks'] ) && is_array( $form_data['settings']['uawpf_webhooks'] ) ? $form_data['settings']['uawpf_webhooks'] : array();

        // default single entry if none
        if ( empty( $webhooks ) ) {
            $webhooks = array(
                1 => array(
                    'name' => __( 'Webhook', 'uawpf-webhooks' ),
                    'url'  => '',
                    'method' => 'POST',
                    'format' => 'json',
                    'headers' => '',
                    'enabled' => 1,
                ),
            );
        }

        // Ensure numeric next id safely
        $keys = array_keys( $webhooks );
        $int_keys = array_filter( $keys, 'is_numeric' );
        $next_id = empty( $int_keys ) ? 1 : ( max( $int_keys ) + 1 );

        // Output panel
        ?>
        <div class="wpforms-panel-content-section wpforms-panel-content-section-uawpf_webhooks">
            <h4><?php esc_html_e( 'Webhooks', 'uawpf-webhooks' ); ?></h4>

            <?php
            // Enable control
            echo wpforms_panel_field(
                'select',
                'settings',
                'uawpf_webhooks_enable',
                $form_data,
                esc_html__( 'Enable Webhooks', 'uawpf-webhooks' ),
                [
                    'parent'  => 'settings',
                    'default' => isset( $form_data['settings']['uawpf_webhooks_enable'] ) ? $form_data['settings']['uawpf_webhooks_enable'] : '0',
                    'options' => [
                        '1' => esc_html__( 'On', 'uawpf-webhooks' ),
                        '0' => esc_html__( 'Off', 'uawpf-webhooks' ),
                    ],
                ],
                false
            );
            ?>

            <div id="uawpf-webhooks-repeater" data-template="<?php echo esc_attr( $this->get_block_template( $form_data ) ); ?>">
                <?php foreach ( $webhooks as $id => $wh ) : ?>
                    <div class="wpforms-builder-settings-block uawpf-webhook-block" data-block-type="uawpf-webhook" data-block-id="<?php echo esc_attr( $id ); ?>">
                        <div class="wpforms-builder-settings-block-header">
                            <div class="wpforms-builder-settings-block-actions">
                                <button class="wpforms-builder-settings-block-edit"><i class="fa fa-pencil"></i></button>
                                <button class="wpforms-builder-settings-block-toggle"><i class="fa fa-chevron-up"></i></button>
                                <button class="wpforms-builder-settings-block-delete"><i class="fa fa-times-circle"></i></button>
                            </div>
                            <div class="wpforms-builder-settings-block-name-holder">
                                <span class="wpforms-builder-settings-block-name"><?php echo esc_html( isset( $wh['name'] ) ? $wh['name'] : '' ); ?></span>
                                <div class="wpforms-builder-settings-block-name-edit">
                                    <input type="text" name="settings[uawpf_webhooks][<?php echo esc_attr( $id ); ?>][name]" value="<?php echo esc_attr( isset( $wh['name'] ) ? $wh['name'] : '' ); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="wpforms-builder-settings-block-content">
                            <?php
                            // Request URL
                            echo wpforms_panel_field(
                                'text',
                                'uawpf_webhooks',
                                'url',
                                $form_data,
                                __( 'Request URL', 'uawpf-webhooks' ),
                                [
                                    'parent'      => 'settings',
                                    'subsection'  => $id,
                                    'default'     => isset( $wh['url'] ) ? $wh['url'] : '',
                                    'placeholder' => __( 'https://example.com/webhook', 'uawpf-webhooks' ),
                                ],
                                false
                            );

                            // Method
                            echo wpforms_panel_field(
                                'select',
                                'uawpf_webhooks',
                                'method',
                                $form_data,
                                __( 'Request Method', 'uawpf-webhooks' ),
                                [
                                    'parent' => 'settings',
                                    'subsection' => $id,
                                    'default' => isset( $wh['method'] ) ? $wh['method'] : 'POST',
                                    'options' => [
                                        'POST' => 'POST',
                                        'GET'  => 'GET',
                                        'PUT'  => 'PUT',
                                        'PATCH'=> 'PATCH',
                                        'DELETE' => 'DELETE',
                                    ],
                                ],
                                false
                            );

                            // Format
                            echo wpforms_panel_field(
                                'select',
                                'uawpf_webhooks',
                                'format',
                                $form_data,
                                __( 'Request Format', 'uawpf-webhooks' ),
                                [
                                    'parent'     => 'settings',
                                    'subsection' => $id,
                                    'default'    => isset( $wh['format'] ) ? $wh['format'] : 'json',
                                    'options'    => [
                                        'json' => 'JSON',
                                        'form' => 'Form Encoded',
                                    ],
                                ],
                                false
                            );

                            // Headers (simple textarea, one-per-line)
                            ?>
                            <div class="wpforms-panel-field">
                                <label><?php esc_html_e( 'Custom Headers (one per line, Header: Value)', 'uawpf-webhooks' ); ?></label>
                                <textarea name="settings[uawpf_webhooks][<?php echo esc_attr( $id ); ?>][headers]" rows="3" class="widefat"><?php echo esc_textarea( isset( $wh['headers'] ) ? $wh['headers'] : '' ); ?></textarea>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <p>
                <button type="button" class="wpforms-builder-settings-block-add wpforms-webooks-add button button-secondary"><?php esc_html_e( 'Add New Webhook', 'uawpf-webhooks' ); ?></button>
            </p>

            <input type="hidden" class="next-webhook-id" value="<?php echo esc_attr( $next_id ); ?>">
        </div>
        <?php
    }

    /**
     * Build a block template (string) for JS to clone; uses placeholders {{index}}
     */
    protected function get_block_template( $form_data ) {
        $index = '{{index}}';
        // Build a compact HTML string for one block (we keep minimal markup & inputs)
        ob_start();
        ?>
        <div class="wpforms-builder-settings-block uawpf-webhook-block" data-block-type="uawpf-webhook" data-block-id="<?php echo $index; ?>">
            <div class="wpforms-builder-settings-block-header">
                <div class="wpforms-builder-settings-block-actions">
                    <button class="wpforms-builder-settings-block-edit"><i class="fa fa-pencil"></i></button>
                    <button class="wpforms-builder-settings-block-toggle"><i class="fa fa-chevron-up"></i></button>
                    <button class="wpforms-builder-settings-block-delete"><i class="fa fa-times-circle"></i></button>
                </div>
                <div class="wpforms-builder-settings-block-name-holder">
                    <span class="wpforms-builder-settings-block-name"><?php echo esc_html__( 'Webhook', 'uawpf-webhooks' ); ?></span>
                    <div class="wpforms-builder-settings-block-name-edit">
                        <input type="text" name="settings[uawpf_webhooks][<?php echo $index; ?>][name]" value="<?php echo esc_attr( __( 'Webhook', 'uawpf-webhooks' ) ); ?>">
                    </div>
                </div>
            </div>
            <div class="wpforms-builder-settings-block-content">
                <div class="wpforms-panel-field">
                    <label><?php esc_html_e( 'Request URL', 'uawpf-webhooks' ); ?></label>
                    <input type="text" name="settings[uawpf_webhooks][<?php echo $index; ?>][url]" value="" placeholder="https://example.com/webhook" class="widefat">
                </div>

                <div class="wpforms-panel-field">
                    <label><?php esc_html_e( 'Method', 'uawpf-webhooks' ); ?></label>
                    <select name="settings[uawpf_webhooks][<?php echo $index; ?>][method]">
                        <option value="POST">POST</option>
                        <option value="GET">GET</option>
                        <option value="PUT">PUT</option>
                        <option value="PATCH">PATCH</option>
                        <option value="DELETE">DELETE</option>
                    </select>
                </div>

                <div class="wpforms-panel-field">
                    <label><?php esc_html_e( 'Format', 'uawpf-webhooks' ); ?></label>
                    <select name="settings[uawpf_webhooks][<?php echo $index; ?>][format]">
                        <option value="json">JSON</option>
                        <option value="form">Form Encoded</option>
                    </select>
                </div>

                <div class="wpforms-panel-field">
                    <label><?php esc_html_e( 'Custom Headers (one per line, Header: Value)', 'uawpf-webhooks' ); ?></label>
                    <textarea name="settings[uawpf_webhooks][<?php echo $index; ?>][headers]" rows="3" class="widefat"></textarea>
                </div>

                <div class="wpforms-panel-field">
                    <label><?php esc_html_e( 'Enabled', 'uawpf-webhooks' ); ?></label>
                    <select name="settings[uawpf_webhooks][<?php echo $index; ?>][enabled]">
                        <option value="1"><?php esc_html_e( 'Yes', 'uawpf-webhooks' ); ?></option>
                        <option value="0"><?php esc_html_e( 'No', 'uawpf-webhooks' ); ?></option>
                    </select>
                </div>
            </div>
        </div>
        <?php
        $html = ob_get_clean();
        // Minify newlines for safe data-attr embedding
        return preg_replace( '/\s+/', ' ', $html );
    }

    /**
     * Normalize webhook keys when form settings are updated to avoid 'NaN' or string keys
     *
     * @param array $settings
     * @param int $form_id
     * @return array
     */
    public function normalize_webhooks_on_save( $settings, $form_id ) {
        if ( isset( $settings['uawpf_webhooks'] ) && is_array( $settings['uawpf_webhooks'] ) ) {
            $normalized = array();
            $i = 1;
            foreach ( $settings['uawpf_webhooks'] as $key => $wh ) {
                // skip empty entries w/o URL/name etc
                if ( is_array( $wh ) && ( ! empty( $wh['url'] ) || ! empty( $wh['name'] ) ) ) {
                    $normalized[ $i ] = $wh;
                    $i++;
                }
            }
            $settings['uawpf_webhooks'] = $normalized;
        }
        return $settings;
    }

    // Fallback hook signature used by some plugins for save
    public function normalize_webhooks_on_save_save_args( $form, $data, $args ) {
        // $form is array used for wp_update_post; $data contains processed $_POST etc
        if ( isset( $form['post_content'] ) ) {
            $form_data = json_decode( stripslashes( $form['post_content'] ), true );
            if ( isset( $form_data['settings']['uawpf_webhooks'] ) && is_array( $form_data['settings']['uawpf_webhooks'] ) ) {
                $normalized = array();
                $i = 1;
                foreach ( $form_data['settings']['uawpf_webhooks'] as $key => $wh ) {
                    if ( is_array( $wh ) && ( ! empty( $wh['url'] ) || ! empty( $wh['name'] ) ) ) {
                        $normalized[ $i ] = $wh;
                        $i++;
                    }
                }
                $form_data['settings']['uawpf_webhooks'] = $normalized;
                $form['post_content'] = wpforms_encode( $form_data );
            }
        }
        return $form;
    }

    /**
     * Process submission hook: send configured webhooks
     *
     * @param array $fields
     * @param array $entry
     * @param array $form_data
     * @param int $entry_id
     */
    public function process_submission( $fields, $entry, $form_data, $entry_id ) {
        // Check global enable for this form
        $enabled_global = isset( $form_data['settings']['uawpf_webhooks_enable'] ) ? $form_data['settings']['uawpf_webhooks_enable'] : '0';
        if ( '1' !== (string) $enabled_global ) {
            return;
        }

        if ( empty( $form_data['settings']['uawpf_webhooks'] ) || ! is_array( $form_data['settings']['uawpf_webhooks'] ) ) {
            return;
        }

        foreach ( $form_data['settings']['uawpf_webhooks'] as $wh ) {

            // skip disabled rows
            if ( isset( $wh['enabled'] ) && (string) $wh['enabled'] === '0' ) {
                continue;
            }

            if ( empty( $wh['url'] ) ) {
                continue;
            }

            // prepare job (we support WPForms tasks if available - else fallback to queue & WP Cron)
            $job = array(
                'webhook'   => $wh,
                'fields'    => $fields,
                'form'      => $form_data,
                'entry_id'  => $entry_id,
                'time'      => time(),
            );

            // try WPForms tasks if present (non-fatal)
            if ( function_exists( 'wpforms' ) && is_object( wpforms() ) ) {
                try {
                    $tasks = null;
                    if ( method_exists( wpforms(), 'get' ) ) {
                        $tasks = wpforms()->get( 'tasks' );
                    }
                    if ( $tasks && method_exists( $tasks, 'create' ) ) {
                        try {
                            $tasks->create( 'uawpf_webhook_delivery' )->async()->params( $job )->register();
                            continue; // scheduled by WPForms tasks
                        } catch ( \Exception $e ) {
                            // fallback to queue
                        }
                    }
                } catch ( \Exception $e ) {
                    // ignore and fallback
                }
            }

            // fallback: enqueue and schedule cron
            $this->enqueue_job( $job );
        }
    }

    protected function enqueue_job( $job ) {
        $queue = get_option( self::QUEUE_OPTION, array() );
        $queue[] = $job;
        update_option( self::QUEUE_OPTION, $queue );
        if ( ! wp_next_scheduled( 'uawpf_webhooks_process_queue' ) ) {
            wp_schedule_single_event( time() + 5, 'uawpf_webhooks_process_queue' );
        }
    }

    /**
     * Process queued jobs (cron callback)
     */
    public function process_queue() {
        $queue = get_option( self::QUEUE_OPTION, array() );
        if ( empty( $queue ) || ! is_array( $queue ) ) {
            return;
        }

        foreach ( $queue as $k => $job ) {
            $result = $this->deliver_job( $job );
            $this->log_attempt( $job, $result );
            unset( $queue[ $k ] );
        }
        update_option( self::QUEUE_OPTION, array_values( $queue ) );
    }

    /**
     * Deliver job now (sends HTTP request)
     */
    protected function deliver_job( $job ) {
        $wh = isset( $job['webhook'] ) ? $job['webhook'] : array();
        $fields = isset( $job['fields'] ) ? $job['fields'] : array();

        $url = isset( $wh['url'] ) ? trim( $wh['url'] ) : '';
        if ( empty( $url ) ) {
            return array( 'success' => false, 'error' => 'Empty URL' );
        }

        $method = isset( $wh['method'] ) ? strtoupper( $wh['method'] ) : 'POST';
        $format = isset( $wh['format'] ) ? $wh['format'] : 'json';
        $headers_raw = isset( $wh['headers'] ) ? $wh['headers'] : '';

        $headers = $this->parse_headers_text( $headers_raw );

        // build payload from fields (use field name if available, else id)
        $payload = array();
        foreach ( $fields as $f ) {
            $key = isset( $f['name'] ) && $f['name'] ? $f['name'] : ( isset( $f['id'] ) ? 'field_' . $f['id'] : uniqid( 'field_' ) );
            $payload[ $key ] = isset( $f['value'] ) ? $f['value'] : '';
        }

        $args = array(
            'timeout' => 15,
            'headers' => $headers,
            'method'  => $method,
        );

        if ( 'json' === $format ) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode( $payload );
        } else {
            $args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
            $args['body'] = http_build_query( $payload );
        }

        // send request (blocking true to ensure delivery in cron)
        $resp = wp_remote_request( $url, $args );

        if ( is_wp_error( $resp ) ) {
            return array( 'success' => false, 'error' => $resp->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $resp );
        $body = wp_remote_retrieve_body( $resp );
        $response_headers = wp_remote_retrieve_headers( $resp );

        return array( 'success' => true, 'code' => $code, 'body' => $body, 'headers' => (array) $response_headers );
    }

    /**
     * Parse headers textarea: "Header: Value" per line
     */
    protected function parse_headers_text( $text ) {
        $out = array();
        if ( empty( $text ) ) {
            return $out;
        }
        $lines = preg_split( "/\r\n|\n|\r/", $text );
        foreach ( $lines as $line ) {
            if ( strpos( $line, ':' ) !== false ) {
                list( $k, $v ) = explode( ':', $line, 2 );
                $k = trim( $k );
                $v = trim( $v );
                if ( $k !== '' ) {
                    $out[ $k ] = $v;
                }
            }
        }
        return $out;
    }

    /**
     * Small log for attempts (keeps last 200)
     */
    protected function log_attempt( $job, $result ) {
        $logs = get_option( self::LOG_OPTION, array() );
        $logs[] = array(
            'time' => time(),
            'job'  => $job,
            'result' => $result,
        );
        if ( count( $logs ) > 200 ) {
            $logs = array_slice( $logs, -200 );
        }
        update_option( self::LOG_OPTION, $logs );
    }

    /**
     * Return allowed field types (pass-through)
     */
    public function form_fields_allowed( $allowed_field_types ) {
        $this->allowed_field_types = ! empty( $allowed_field_types ) ? $allowed_field_types : [ 'all-fields' ];
        return $allowed_field_types;
    }

    /**
     * Add localized strings for builder
     */
    public function builder_strings( $strings, $form ) {
        $strings['webhook_prompt']        = esc_html__( 'Enter a webhook name', 'uawpf-webhooks' );
        $strings['webhook_ph']            = '';
        $strings['webhook_error']         = esc_html__( 'You must provide a webhook name', 'uawpf-webhooks' );
        $strings['webhook_delete']        = esc_html__( 'Are you sure that you want to delete this webhook?', 'uawpf-webhooks' );
        $strings['webhook_def_name']      = esc_html__( 'Unnamed Webhook', 'uawpf-webhooks' );
        $strings['webhook_required_flds'] = esc_html__( 'Your form contains required Webhook settings that have not been configured. Please double-check and configure these settings to complete the connection setup.', 'uawpf-webhooks' );
        return $strings;
    }

    public function enqueue_assets() {

		wp_enqueue_script(
			'ultrawpf-webhooks-builder',
			ULTRAWPF_WEBHOOKS_URL . "assets/js/webhooks-builder-panel.js",
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

