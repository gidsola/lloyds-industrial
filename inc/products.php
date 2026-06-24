<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function (): void {
    register_taxonomy('li_industry', ['product'], [
        'labels' => [
            'name'          => __('Industries', 'lloyds-industrial'),
            'singular_name' => __('Industry', 'lloyds-industrial'),
        ],
        'public'       => true,
        'show_ui'      => true,
        'show_in_rest' => true,
        'hierarchical' => true,
        'rewrite'      => [
            'slug' => 'industry',
        ],
    ]);

    register_taxonomy('li_application', ['product'], [
        'labels' => [
            'name'          => __('Applications', 'lloyds-industrial'),
            'singular_name' => __('Application', 'lloyds-industrial'),
        ],
        'public'       => true,
        'show_ui'      => true,
        'show_in_rest' => true,
        'hierarchical' => true,
        'rewrite'      => [
            'slug' => 'application',
        ],
    ]);
});

add_action('init', function (): void {
    register_post_meta('product', '_li_public_summary', [
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'sanitize_textarea_field',
        'auth_callback'     => '__return_true',
    ]);

    register_post_meta('product', '_li_requires_document_login', [
        'type'              => 'boolean',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'rest_sanitize_boolean',
        'auth_callback'     => '__return_true',
    ]);

    register_post_meta('product', '_li_certifications', [
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback'     => '__return_true',
    ]);
});