<?php

/**
 * Addons - Ultra addons for wpforms webhooks
 * @author Jewel Hossain
 * @param $option
 * @since 1.0.1
 */
if ( ! function_exists( 'ultrawpf_settings_options_webhooks' ) ) {
	function ultrawpf_settings_options_webhooks( $option ) {
        
		// if ( apply_filters( 'ultrawpf_checked_license_status', '' ) != false ) {

			$option['general_addons']['fields']['uawpf_enable_webhook']['is_pro']  = false;

		// }else {

        //     // add_action( 'admin_notices', 'license_activation_notice' );

        // }

		return $option;
	}
	add_filter( 'ultrawpf_settings_options', 'ultrawpf_settings_options_webhooks', 16, 2 );
}

if(!function_exists('license_activation_notice')) {
    function license_activation_notice() {

        if ( apply_filters( 'ultrawpf_checked_license_status', '' ) == false ) {

        ?>
        
        <div class="notice notice-error is-dismissible">
            <p>
				<?php 
					echo esc_html__( 'Ultra Addons For WPForms Webhooks requires to ', 'ultrawpf-webhooks' );
					echo '<a href="' . esc_url( admin_url('admin.php?page=ultrawpf_license_info') ) . '"> activate license. </a>'; 
				?>
            </p>
        </div>

        <?php
        }
    }
}

function uawpf_webhook_get_available_methods() {

    return [
        'get'    => 'GET',
        'post'   => 'POST',
        'put'    => 'PUT',
        'patch'  => 'PATCH',
        'delete' => 'DELETE',
    ];
}

function uawpf_webhook_get_available_formats() {

    return [
        'json' => esc_html__( 'JSON', 'ultrawpf-webhooks' ),
        'form' => esc_html__( 'FORM', 'ultrawpf-webhooks' ),
    ];
}

function uawpf_webhook_clean_key( $name ) {

    // Remove non-alphanumeric characters
    $sanitized = preg_replace( '/[^a-zA-Z0-9._-]/', '', $name );

    return $sanitized;
}

function uawpf_webhook_clean_header_value( $value ) {

    $value     = (string) $value;
    $length    = strlen( $value );
    $sanitized = '';

    for ( $i = 0; $i < $length; ++$i ) {
        $ascii = ord( $value[ $i ] );

        // Control characters
        if (
            ( $ascii < 32 && $ascii !== 9 ) ||
            $ascii === 127 ||
            $ascii > 254
        ) {
            continue;
        }

        $sanitized .= $value[ $i ];
    }

    return $sanitized;
}

function uawpf_webhook_is_url( $url ) {

    $valid = wpforms_is_url( $url );

    if ( ! wpforms_debug() ) {
        return $valid;
    }

    if ( $valid ) {
        return $valid;
    }

    $whitelist = [ 'localhost', '127.0.0.1', '192.168.' ];

    foreach ( $whitelist as $env ) {

        if ( false !== strpos( $url, $env ) ) {
            $valid = true;
            break;
        }
    }

    return $valid;
}

function uawpf_webhook_decode_currency_symbols( $str ): string {

    foreach ( wpforms_get_currencies() as $currency ) {
        $symbol = $currency['symbol'] ?? '';
        $str    = str_replace( $symbol, html_entity_decode( $symbol ), $str );
    }

    return $str;
}

function uawpf_webhook_encrypt( $text ) {
    if ( empty( $text ) ) {
        return '';
    }
    $key = wp_salt( 'uawpf-webhook' );
    return base64_encode( openssl_encrypt( $text, 'aes-128-ecb', $key, 0, '' ) );
}

function uawpf_webhook_decrypt( $encoded ) {
    $key = wp_salt( 'uawpf-webhook' );
    return openssl_decrypt( base64_decode( $encoded ), 'aes-128-ecb', $key, 0, '' );
}
    
