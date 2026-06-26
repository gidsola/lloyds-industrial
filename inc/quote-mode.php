<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function li_is_quote_mode_enabled(): bool
{
    $settings = get_option('li_theme_settings', []);

    return !empty($settings['quote_mode']);
}

add_action('admin_init', function (): void {
    add_settings_field(
        'li_quote_mode',
        __('Quote Mode', 'lloyds-industrial'),
        'li_render_quote_mode_field',
        'lloyds-industrial-settings',
        'li_site_behavior_section'
    );
});

function li_render_quote_mode_field(): void
{
    $settings = get_option('li_theme_settings', []);
    $checked = !empty($settings['quote_mode']);
    ?>
    <label>
        <input
            type="checkbox"
            name="li_theme_settings[quote_mode]"
            value="1"
            <?php checked($checked); ?>
        >
        <?php esc_html_e('Replace standard ecommerce emphasis with request-quote workflows.', 'lloyds-industrial'); ?>
    </label>
    <?php
}

add_filter('woocommerce_get_price_html', function (string $price): string {
    if (!li_is_quote_mode_enabled()) {
        return $price;
    }

    return '<span class="li-quote-price">' . esc_html__('Request Quote', 'lloyds-industrial') . '</span>';
});

add_filter('woocommerce_product_single_add_to_cart_text', function (string $text): string {
    return li_is_quote_mode_enabled()
        ? __('Request Quote', 'lloyds-industrial')
        : $text;
});

add_filter('woocommerce_product_add_to_cart_text', function (string $text): string {
    return li_is_quote_mode_enabled()
        ? __('Request Quote', 'lloyds-industrial')
        : $text;
});
