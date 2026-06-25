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

function li_get_product_filter_value(string $key): string
{
    return isset($_GET[$key]) ? sanitize_title(wp_unslash($_GET[$key])) : '';
}

function li_get_product_filter_url(array $filters = []): string
{
    $url = get_post_type_archive_link('product') ?: home_url('/products/');
    $query_args = [];
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

    if ($search) {
        $query_args['s'] = $search;
        $query_args['post_type'] = 'product';
    }

    foreach (['product_cat', 'li_industry', 'li_application'] as $taxonomy) {
        $value = $filters[$taxonomy] ?? li_get_product_filter_value($taxonomy);

        if ($value) {
            $query_args[$taxonomy] = $value;
        }
    }

    return $query_args ? add_query_arg($query_args, $url) : $url;
}

function li_render_product_filter_group(string $taxonomy, string $label): string
{
    if (!taxonomy_exists($taxonomy)) {
        return '';
    }

    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => true,
        'number'     => 8,
    ]);

    if (is_wp_error($terms) || !$terms) {
        return '';
    }

    $active = li_get_product_filter_value($taxonomy);
    $output = '<div class="li-product-filter-group">';
    $output .= '<span class="li-product-filter-label">' . esc_html($label) . '</span>';
    $output .= '<div class="li-product-filter-options">';

    foreach ($terms as $term) {
        $classes = 'li-product-filter-link';

        if ($active === $term->slug) {
            $classes .= ' is-active';
        }

        $output .= sprintf(
            '<a class="%1$s" href="%2$s">%3$s</a>',
            esc_attr($classes),
            esc_url(li_get_product_filter_url([$taxonomy => $term->slug])),
            esc_html($term->name)
        );
    }

    $output .= '</div></div>';

    return $output;
}

function li_render_product_filters(): string
{
    $groups = [
        li_render_product_filter_group('product_cat', __('Category', 'lloyds-industrial')),
        li_render_product_filter_group('li_industry', __('Industry', 'lloyds-industrial')),
        li_render_product_filter_group('li_application', __('Application', 'lloyds-industrial')),
    ];

    $groups = array_filter($groups);

    if (!$groups) {
        return '';
    }

    $has_active_filter = (bool) array_filter([
        li_get_product_filter_value('product_cat'),
        li_get_product_filter_value('li_industry'),
        li_get_product_filter_value('li_application'),
    ]);

    $output = '<div class="li-product-filters">';
    $output .= implode('', $groups);

    if ($has_active_filter) {
        $output .= '<a class="li-product-filter-clear" href="' . esc_url(li_get_product_filter_url([
            'product_cat'    => '',
            'li_industry'    => '',
            'li_application' => '',
        ])) . '">' . esc_html__('Clear filters', 'lloyds-industrial') . '</a>';
    }

    $output .= '</div>';

    return $output;
}

function li_render_product_search_form(): string
{
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $output = '<form class="li-product-search-form" role="search" method="get" action="' . esc_url(home_url('/')) . '">';
    $output .= '<label class="screen-reader-text" for="li-product-search">' . esc_html__('Search products', 'lloyds-industrial') . '</label>';
    $output .= '<input id="li-product-search" type="search" name="s" placeholder="' . esc_attr__('Search products, applications, or industries...', 'lloyds-industrial') . '" value="' . esc_attr($search) . '">';
    $output .= '<input type="hidden" name="post_type" value="product">';

    foreach (['product_cat', 'li_industry', 'li_application'] as $taxonomy) {
        $value = li_get_product_filter_value($taxonomy);

        if ($value) {
            $output .= '<input type="hidden" name="' . esc_attr($taxonomy) . '" value="' . esc_attr($value) . '">';
        }
    }

    $output .= '<button type="submit">' . esc_html__('Search', 'lloyds-industrial') . '</button>';
    $output .= '</form>';

    return $output;
}

function li_render_product_card_meta(): string
{
    $product_id = get_the_ID();

    if (!$product_id) {
        return '';
    }

    $items = [];
    $certifications = (string) get_post_meta($product_id, '_li_certifications', true);
    $industries = wp_get_post_terms($product_id, 'li_industry', ['fields' => 'names']);
    $applications = wp_get_post_terms($product_id, 'li_application', ['fields' => 'names']);
    $documents = function_exists('li_get_product_documents') ? li_get_product_documents($product_id) : [];
    $has_sds = false;

    foreach ($documents as $document) {
        if (function_exists('li_is_sds_document') && li_is_sds_document((int) $document->ID)) {
            $has_sds = true;
            break;
        }
    }

    if ($certifications) {
        $items[] = esc_html($certifications);
    }

    if (!is_wp_error($industries) && $industries) {
        $items[] = esc_html(implode(', ', array_slice($industries, 0, 2)));
    }

    if (!is_wp_error($applications) && $applications) {
        $items[] = esc_html(implode(', ', array_slice($applications, 0, 2)));
    }

    if ($has_sds) {
        $items[] = esc_html__('SDS after purchase', 'lloyds-industrial');
    } elseif ($documents) {
        $items[] = esc_html__('Technical documents', 'lloyds-industrial');
    }

    if (!$items) {
        return '';
    }

    return '<div class="li-product-card-meta"><span>' . implode('</span><span>', $items) . '</span></div>';
}

add_shortcode('li_product_search', 'li_render_product_search_form');
add_shortcode('li_product_filters', 'li_render_product_filters');
add_shortcode('li_product_card_meta', 'li_render_product_card_meta');

add_action('pre_get_posts', function (WP_Query $query): void {
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    $post_type = $query->get('post_type');

    if (!is_post_type_archive('product') && $post_type !== 'product') {
        return;
    }

    $tax_query = (array) $query->get('tax_query');

    foreach (['product_cat', 'li_industry', 'li_application'] as $taxonomy) {
        $value = li_get_product_filter_value($taxonomy);

        if (!$value || !taxonomy_exists($taxonomy)) {
            continue;
        }

        $tax_query[] = [
            'taxonomy' => $taxonomy,
            'field'    => 'slug',
            'terms'    => [$value],
        ];
    }

    if ($tax_query) {
        $query->set('tax_query', $tax_query);
    }
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
