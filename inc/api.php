<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function (): void {
    register_rest_route('b2b/v1', '/products', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'li_rest_get_products',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('b2b/v1', '/products/(?P<id>\d+)/documents', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'li_rest_get_product_documents',
        'permission_callback' => '__return_true',
        'args'                => [
            'id' => [
                'validate_callback' => fn ($param): bool => is_numeric($param),
            ],
        ],
    ]);
});

function li_rest_get_products(WP_REST_Request $request): WP_REST_Response
{
    $query = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
    ]);

    $products = [];

    foreach ($query->posts as $product) {
        $products[] = [
            'id'             => $product->ID,
            'title'          => get_the_title($product),
            'url'            => get_permalink($product),
            'summary'        => get_post_meta($product->ID, '_li_public_summary', true),
            'categories'     => wp_get_post_terms($product->ID, 'product_cat', ['fields' => 'names']),
            'industries'     => wp_get_post_terms($product->ID, 'li_industry', ['fields' => 'names']),
            'applications'   => wp_get_post_terms($product->ID, 'li_application', ['fields' => 'names']),
            'certifications' => get_post_meta($product->ID, '_li_certifications', true),
            'documents'      => [
                'authorization' => 'sds_purchase_history',
                'endpoint'      => rest_url('b2b/v1/products/' . $product->ID . '/documents'),
            ],
        ];
    }

    return rest_ensure_response($products);
}

function li_rest_get_product_documents(WP_REST_Request $request): WP_REST_Response
{
    $product_id = absint($request['id']);

    $query = new WP_Query([
        'post_type'      => 'li_document',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'meta_query'     => [
            [
                'key'     => '_li_related_product_id',
                'value'   => $product_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
        ],
    ]);

    $documents = [];

    foreach ($query->posts as $document) {
        if (!li_user_can_view_document_listing((int) $document->ID)) {
            continue;
        }

        $access_level = (string) get_post_meta($document->ID, '_li_access_level', true);

        if (!$access_level) {
            $access_level = 'public';
        }

        $download_url = li_get_document_download_url($document->ID);
        $is_sds = li_is_sds_document($document->ID);
        $access_level = $is_sds ? 'sds_purchase' : $access_level;
        $has_access = $download_url !== null;

        $documents[] = [
            'id'             => $document->ID,
            'title'          => get_the_title($document),
            'type'           => wp_get_post_terms($document->ID, 'li_document_type', ['fields' => 'names']),
            'excerpt'        => get_the_excerpt($document),
            'access_level'   => $access_level,
            'is_sds'         => $is_sds,
            'available'      => $has_access,
            'download_url'   => $download_url,
        ];
    }

    return rest_ensure_response($documents);
}
