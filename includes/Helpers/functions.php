<?php

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

function uawpf_webhook_sanitize_header_name( $name ) {

    // Allow characters (lowercase and uppercase), numbers, decimal point, underscore and minus.
    $sanitized = preg_replace( '/[^a-zA-Z0-9._-]/', '', $name );

    return $sanitized;

}

function uawpf_webhook_clean_key( $name ) {

    // Allow characters (lowercase and uppercase), numbers, decimal point, underscore and minus.
    $sanitized = preg_replace( '/[^a-zA-Z0-9._-]/', '', $name );

    return $sanitized;
}

function uawpf_webhook_sanitize_header_value( $value ) {

    $value     = (string) $value;
    $length    = strlen( $value );
    $sanitized = '';

    for ( $i = 0; $i < $length; ++$i ) {
        $ascii = ord( $value[ $i ] );

        // Non-visible, non-whitespace characters
        // 9 === horizontal tab
        // 32-126, 128-254 === visible
        // 127 === DEL
        // 255 === null byte.
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

function uawpf_webhook_clean_header_value( $value ) {

    $value     = (string) $value;
    $length    = strlen( $value );
    $sanitized = '';

    for ( $i = 0; $i < $length; ++$i ) {
        $ascii = ord( $value[ $i ] );

        // Non-visible, non-whitespace characters
        // 9 === horizontal tab
        // 32-126, 128-254 === visible
        // 127 === DEL
        // 255 === null byte.
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
    
