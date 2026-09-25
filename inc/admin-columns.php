<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_filter('manage_li_document_posts_columns', function (array $columns): array {
    $columns['li_document_type'] = __('Type', 'b2b-industrial');
    $columns['li_access_level'] = __('Access', 'b2b-industrial');
    $columns['li_related_product'] = __('Related Product', 'b2b-industrial');

    return $columns;
});

add_action('manage_li_document_posts_custom_column', function (string $column, int $post_id): void {
    if ($column === 'li_document_type') {
        $terms = wp_get_post_terms($post_id, 'li_document_type', ['fields' => 'names']);
        echo esc_html($terms ? implode(', ', $terms) : '-');
    }

    if ($column === 'li_access_level') {
        echo esc_html(li_get_document_access_label($post_id));
    }

    if ($column === 'li_related_product') {
        $product_id = (int) get_post_meta($post_id, '_li_related_product_id', true);
        echo $product_id ? '<a href="' . esc_url(get_edit_post_link($product_id)) . '">' . esc_html(get_the_title($product_id)) . '</a>' : '-';
    }
}, 10, 2);

add_filter('manage_edit-product_columns', function (array $columns): array {
    $columns['li_industries'] = __('Industries', 'b2b-industrial');
    $columns['li_applications'] = __('Applications', 'b2b-industrial');

    return $columns;
});

add_action('manage_product_posts_custom_column', function (string $column, int $post_id): void {
    if ($column === 'li_industries') {
        $terms = wp_get_post_terms($post_id, 'li_industry', ['fields' => 'names']);
        echo esc_html($terms ? implode(', ', $terms) : '-');
    }

    if ($column === 'li_applications') {
        $terms = wp_get_post_terms($post_id, 'li_application', ['fields' => 'names']);
        echo esc_html($terms ? implode(', ', $terms) : '-');
    }
}, 10, 2);
