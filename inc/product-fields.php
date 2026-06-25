<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('woocommerce_product_options_general_product_data', function (): void {
    echo '<div class="options_group">';

    woocommerce_wp_textarea_input([
        'id'          => '_li_public_summary',
        'label'       => __('Public Summary', 'lloyds-industrial'),
        'description' => __('Short public-facing product summary used for search, APIs and product cards.', 'lloyds-industrial'),
        'desc_tip'    => true,
    ]);

    woocommerce_wp_text_input([
        'id'          => '_li_certifications',
        'label'       => __('Certifications', 'lloyds-industrial'),
        'description' => __('Comma-separated certification or compliance highlights.', 'lloyds-industrial'),
        'desc_tip'    => true,
    ]);

    echo '</div>';
});

add_action('woocommerce_process_product_meta', function (int $post_id): void {
    if (isset($_POST['_li_public_summary'])) {
        update_post_meta(
            $post_id,
            '_li_public_summary',
            sanitize_textarea_field(wp_unslash($_POST['_li_public_summary']))
        );
    }

    if (isset($_POST['_li_certifications'])) {
        update_post_meta(
            $post_id,
            '_li_certifications',
            sanitize_text_field(wp_unslash($_POST['_li_certifications']))
        );
    }
});
