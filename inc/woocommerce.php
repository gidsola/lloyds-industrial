<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', function (): void {
    add_theme_support('woocommerce', [
        'thumbnail_image_width' => 600,
        'single_image_width'    => 900,
        'product_grid'          => [
            'default_rows'    => 4,
            'min_rows'        => 2,
            'max_rows'        => 8,
            'default_columns' => 3,
            'min_columns'     => 2,
            'max_columns'     => 4,
        ],
    ]);

    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
});

add_filter('woocommerce_enqueue_styles', '__return_empty_array');

add_filter('woocommerce_product_description_heading', '__return_empty_string');
add_filter('woocommerce_product_additional_information_heading', '__return_empty_string');

function li_render_woocommerce_notices_shortcode(): string
{
    if (!function_exists('wc_print_notices')) {
        return '';
    }

    ob_start();
    wc_print_notices();
    $notices = trim((string) ob_get_clean());

    if ($notices === '') {
        return '';
    }

    return '<div class="li-woocommerce-notices">' . $notices . '</div>';
}

function li_render_woocommerce_cart_shortcode(): string
{
    if (!shortcode_exists('woocommerce_cart')) {
        return '<div class="li-woo-empty-state">' . esc_html__('Cart is available after WooCommerce is active.', 'lloyds-industrial') . '</div>';
    }

    return do_shortcode('[woocommerce_cart]');
}

function li_render_woocommerce_checkout_shortcode(): string
{
    if (!shortcode_exists('woocommerce_checkout')) {
        return '<div class="li-woo-empty-state">' . esc_html__('Checkout is available after WooCommerce is active.', 'lloyds-industrial') . '</div>';
    }

    return do_shortcode('[woocommerce_checkout]');
}

add_shortcode('li_woocommerce_notices', 'li_render_woocommerce_notices_shortcode');
add_shortcode('li_woocommerce_cart', 'li_render_woocommerce_cart_shortcode');
add_shortcode('li_woocommerce_checkout', 'li_render_woocommerce_checkout_shortcode');
