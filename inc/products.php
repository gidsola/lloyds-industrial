<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function (): void {
    if (!taxonomy_exists('product_brand')) {
        register_taxonomy('product_brand', ['product'], [
            'labels' => [
                'name'          => __('Brands', 'b2b-industrial'),
                'singular_name' => __('Brand', 'b2b-industrial'),
            ],
            'public'       => true,
            'show_ui'      => true,
            'show_in_rest' => true,
            'hierarchical' => false,
            'rewrite'      => [
                'slug' => 'brand',
            ],
        ]);
    }

    register_taxonomy('li_industry', ['product'], [
        'labels' => [
            'name'          => __('Industries', 'b2b-industrial'),
            'singular_name' => __('Industry', 'b2b-industrial'),
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
            'name'          => __('Applications', 'b2b-industrial'),
            'singular_name' => __('Application', 'b2b-industrial'),
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

    $url = $query_args ? add_query_arg($query_args, $url) : $url;

    return $url . '#li-product-results';
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
        li_render_product_filter_group('product_cat', __('Category', 'b2b-industrial')),
        li_render_product_filter_group('li_industry', __('Industry', 'b2b-industrial')),
        li_render_product_filter_group('li_application', __('Application', 'b2b-industrial')),
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
        ])) . '">' . esc_html__('Clear filters', 'b2b-industrial') . '</a>';
    }

    $output .= '</div>';

    return $output;
}

function li_render_product_category_cards(): string
{
    if (!taxonomy_exists('product_cat')) {
        return '';
    }

    $terms = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);

    if (is_wp_error($terms) || !$terms) {
        return '';
    }

    $excluded_slugs = apply_filters('li_product_family_card_excluded_slugs', [
        'popular-products',
        'uncategorized',
    ]);
    $excluded_slugs = array_map('sanitize_title', is_array($excluded_slugs) ? $excluded_slugs : []);
    $terms = array_values(array_filter($terms, static function ($term) use ($excluded_slugs): bool {
        return $term instanceof WP_Term && !in_array($term->slug, $excluded_slugs, true);
    }));

    if (!$terms) {
        return '';
    }

    ob_start();
    ?>
    <div class="alignwide li-dynamic-category-grid">
        <?php foreach ($terms as $term) : ?>
            <?php
            $url = get_term_link($term, 'product_cat');

            if (is_wp_error($url)) {
                continue;
            }

            $description = trim(wp_strip_all_tags(term_description($term, 'product_cat')));
            ?>
            <article class="li-card li-product-family-card">
                <h3><?php echo esc_html($term->name); ?></h3>
                <?php if ($description !== '') : ?>
                    <p><?php echo esc_html(wp_trim_words($description, 20)); ?></p>
                <?php else : ?>
                    <p>
                        <?php
                        echo esc_html(sprintf(
                            /* translators: %s: product category name */
                            __('Browse B2B products in %s.', 'b2b-industrial'),
                            $term->name
                        ));
                        ?>
                    </p>
                <?php endif; ?>
                <p class="li-card-link">
                    <a href="<?php echo esc_url($url); ?>"><?php esc_html_e('View Products', 'b2b-industrial'); ?></a>
                </p>
            </article>
        <?php endforeach; ?>
    </div>
    <?php

    return (string) ob_get_clean();
}

function li_register_product_category_cards_block(): void
{
    register_block_type('b2b-industrial/product-category-grid', [
        'api_version'     => 2,
        'render_callback' => 'li_render_product_category_cards',
        'supports'        => [
            'html' => false,
        ],
    ]);
}

add_action('init', 'li_register_product_category_cards_block');

function li_render_product_search_form(): string
{
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $instance_id = 'li-product-search-' . wp_unique_id();

    $output = '<div class="li-product-search" data-li-product-search>';
    $output .= '<form class="li-product-search-form" role="search" method="get" action="' . esc_url(home_url('/')) . '" data-li-product-search-form>';
    $output .= '<label class="screen-reader-text" for="' . esc_attr($instance_id) . '">' . esc_html__('Search products', 'b2b-industrial') . '</label>';
    $output .= '<input id="' . esc_attr($instance_id) . '" type="search" name="s" placeholder="' . esc_attr__('Search products, applications, or industries...', 'b2b-industrial') . '" value="' . esc_attr($search) . '" autocomplete="off" data-li-product-search-input>';
    $output .= '<input type="hidden" name="post_type" value="product">';

    foreach (['product_cat', 'li_industry', 'li_application'] as $taxonomy) {
        $value = li_get_product_filter_value($taxonomy);

        if ($value) {
            $output .= '<input type="hidden" name="' . esc_attr($taxonomy) . '" value="' . esc_attr($value) . '">';
        }
    }

    $output .= '<button type="submit">' . esc_html__('Search', 'b2b-industrial') . '</button>';
    $output .= '</form>';
    $output .= '<div class="li-product-search-results" data-li-product-search-results hidden aria-live="polite"></div>';
    $output .= '</div>';

    return $output;
}

function li_get_product_search_term_ids(string $search): array
{
    $ids = [];

    foreach (['product_cat', 'li_industry', 'li_application', 'product_brand', 'product_tag'] as $taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            continue;
        }

        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'search'     => $search,
            'fields'     => 'ids',
            'number'     => 12,
        ]);

        if (is_wp_error($terms) || !$terms) {
            continue;
        }

        $term_query = new WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 12,
            'fields'         => 'ids',
            'tax_query'      => [
                [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => array_map('absint', $terms),
                ],
            ],
        ]);

        $ids = array_merge($ids, array_map('absint', $term_query->posts));
    }

    return array_values(array_unique($ids));
}

function li_get_product_search_results(string $search, int $limit = 8): array
{
    $search = trim($search);

    if (strlen($search) < 2 || !post_type_exists('product')) {
        return [];
    }

    $query = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        's'              => $search,
        'fields'         => 'ids',
    ]);

    $ids = array_map('absint', $query->posts);
    $ids = array_values(array_unique(array_merge($ids, li_get_product_search_term_ids($search))));
    $ids = array_slice($ids, 0, $limit);
    $results = [];

    foreach ($ids as $product_id) {
        $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);
        $industries = wp_get_post_terms($product_id, 'li_industry', ['fields' => 'names']);
        $summary = (string) get_post_meta($product_id, '_li_public_summary', true);

        if ($summary === '') {
            $summary = get_the_excerpt($product_id);
        }

        $results[] = [
            'id'         => $product_id,
            'title'      => get_the_title($product_id),
            'url'        => get_permalink($product_id),
            'summary'    => wp_trim_words(wp_strip_all_tags($summary), 22),
            'categories' => is_wp_error($categories) ? [] : array_values($categories),
            'industries' => is_wp_error($industries) ? [] : array_values($industries),
        ];
    }

    return $results;
}

function li_ajax_frontend_product_search(): void
{
    check_ajax_referer('li_frontend_product_search');

    $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
    $results = li_get_product_search_results($search);

    wp_send_json_success($results);
}

add_action('wp_ajax_li_frontend_product_search', 'li_ajax_frontend_product_search');
add_action('wp_ajax_nopriv_li_frontend_product_search', 'li_ajax_frontend_product_search');

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
        $items[] = esc_html__('SDS after purchase', 'b2b-industrial');
    } elseif ($documents) {
        $items[] = esc_html__('Technical documents', 'b2b-industrial');
    }

    if (!$items) {
        return '';
    }

    return '<div class="li-product-card-meta"><span>' . implode('</span><span>', $items) . '</span></div>';
}

function li_current_user_is_reseller(): bool
{
    $user = wp_get_current_user();

    return $user instanceof WP_User && in_array('li_distributor', (array) $user->roles, true);
}

function li_get_product_reseller_search_term(int $product_id): string
{
    $terms = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);

    if (!is_wp_error($terms) && !empty($terms[0])) {
        return (string) $terms[0];
    }

    return get_the_title($product_id);
}

function li_render_product_b2b_actions(): string
{
    if (!is_singular('product')) {
        return '';
    }

    $product_id = get_the_ID();

    if (!$product_id) {
        return '';
    }

    $product_title = get_the_title($product_id);
    $quote_url = add_query_arg([
        'li_inquiry_type' => 'quote',
        'li_product'      => $product_title,
    ], home_url('/contact/'));
    $reseller_url = add_query_arg([
        'reseller_product' => li_get_product_reseller_search_term($product_id),
    ], home_url('/find-a-reseller/'));
    $sds_document_id = function_exists('li_get_product_sds_document_id') ? li_get_product_sds_document_id($product_id) : 0;
    $sds_download_url = $sds_document_id && function_exists('li_get_document_download_url')
        ? li_get_document_download_url($sds_document_id)
        : null;

    ob_start();
    ?>
    <div class="li-product-b2b-actions">
        <?php if (!is_user_logged_in()) : ?>
            <p><?php esc_html_e('B2B products are supplied through approved reseller and distributor channels.', 'b2b-industrial'); ?></p>
            <a class="li-button-primary" href="<?php echo esc_url($reseller_url); ?>">
                <?php esc_html_e('Find A Reseller For This Product', 'b2b-industrial'); ?>
            </a>
            <a class="li-button-secondary" href="<?php echo esc_url(li_get_account_login_url(get_permalink($product_id))); ?>">
                <?php esc_html_e('Sign In', 'b2b-industrial'); ?>
            </a>
        <?php elseif (li_current_user_is_reseller()) : ?>
            <p><?php esc_html_e('Use your reseller account to request pricing or access eligible product SDS documents.', 'b2b-industrial'); ?></p>
            <a class="li-button-primary" href="<?php echo esc_url($quote_url); ?>">
                <?php esc_html_e('Request Quote', 'b2b-industrial'); ?>
            </a>
            <?php if ($sds_download_url) : ?>
                <a class="li-button-secondary" href="<?php echo esc_url($sds_download_url); ?>">
                    <?php esc_html_e('Download SDS', 'b2b-industrial'); ?>
                </a>
            <?php elseif ($sds_document_id) : ?>
                <span class="li-button-secondary" aria-disabled="true">
                    <?php esc_html_e('SDS Available After Purchase', 'b2b-industrial'); ?>
                </span>
            <?php endif; ?>
        <?php else : ?>
            <p><?php esc_html_e('Need pricing or SDS access? Contact B2B support or use the account tied to your purchase history.', 'b2b-industrial'); ?></p>
            <a class="li-button-primary" href="<?php echo esc_url($quote_url); ?>">
                <?php esc_html_e('Request Support', 'b2b-industrial'); ?>
            </a>
            <?php if ($sds_download_url) : ?>
                <a class="li-button-secondary" href="<?php echo esc_url($sds_download_url); ?>">
                    <?php esc_html_e('Download SDS', 'b2b-industrial'); ?>
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php

    return (string) ob_get_clean();
}

add_shortcode('li_product_search', 'li_render_product_search_form');
add_shortcode('li_product_filters', 'li_render_product_filters');
add_shortcode('li_product_category_cards', 'li_render_product_category_cards');
add_shortcode('li_product_card_meta', 'li_render_product_card_meta');
add_shortcode('li_product_b2b_actions', 'li_render_product_b2b_actions');

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
