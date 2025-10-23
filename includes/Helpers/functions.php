<?php

function uawpf_get_available_methods() {

    return [
        'get'    => 'GET',
        'post'   => 'POST',
        'put'    => 'PUT',
        'patch'  => 'PATCH',
        'delete' => 'DELETE',
    ];
}

function uawpf_get_available_formats() {

    return [
        'json' => esc_html__( 'JSON', 'ultrawpf-webhooks' ),
        'form' => esc_html__( 'FORM', 'ultrawpf-webhooks' ),
    ];
}
    
