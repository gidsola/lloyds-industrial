<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function li_get_global_layout_settings(): array
{
    return wp_parse_args(li_get_theme_settings(), li_get_global_layout_defaults());
}

function li_get_global_layout_defaults(): array
{
    return [
        'partner_mode'               => false,
        'quote_mode'                 => false,
        'documents_mode'             => 'controlled',
        'sticky_header'              => true,
        'compact_header'             => false,
        'show_site_title'            => true,
        'show_header_actions_mobile' => false,
        'site_width'                 => 1400,
        'footer_density'             => 'comfortable',
        'announcement_enabled'       => true,
        'announcement_text'          => __('Industrial chemical solutions engineered for modern industry.', 'lloyds-industrial'),
        'announcement_link_label'    => __('Contact technical support', 'lloyds-industrial'),
        'announcement_link_url'      => '/contact',
        'header_primary_label'       => __('Shop', 'lloyds-industrial'),
        'header_primary_url'         => '/products',
        'header_secondary_label'     => __('Request Quote', 'lloyds-industrial'),
        'header_secondary_url'       => '/contact',
        'show_account_link'          => true,
        'show_cart_link'             => true,
        'footer_tagline'             => __('Industrial chemical solutions, product knowledge, and SDS access for professional customers.', 'lloyds-industrial'),
        'footer_legal_text'          => '&copy; {year} {site}. All rights reserved.',
        'footer_note_enabled'        => true,
        'footer_note_text'           => __('Built on the Lloyds Industrial multisite framework.', 'lloyds-industrial'),
        'footer_column_1_enabled'    => true,
        'footer_column_1_heading'    => __('Products', 'lloyds-industrial'),
        'footer_column_1_links'      => "Products|/products\nCatalogue|/catalogue\nLubricants & Corrosion Inhibitors|/product-category/lubricants-corrosion-inhibitors\nCleaners & Degreasers|/product-category/cleaner-degreasers",
        'footer_column_2_enabled'    => true,
        'footer_column_2_heading'    => __('Resources', 'lloyds-industrial'),
        'footer_column_2_links'      => "Knowledge Center|/documentation\nSDS Access|/documentation/sds\nTechnical Data Sheets|/documentation/technical\nTechnical Support|/contact\nCustomer Login|/account",
        'footer_column_3_enabled'    => true,
        'footer_column_3_heading'    => __('Company', 'lloyds-industrial'),
        'footer_column_3_links'      => "About|/about\nIndustries|/industries\nPlace Orders|/contact\nAccounting|/contact\nContact|/contact",
    ];
}

function li_get_footer_column_indexes(): array
{
    return [1, 2, 3];
}

function li_theme_setting_checkbox_value(array $settings, string $key, bool $default = true): bool
{
    if (!array_key_exists($key, $settings)) {
        return $default;
    }

    return !empty($settings[$key]);
}

function li_format_layout_text(string $text): string
{
    return strtr($text, [
        '{year}' => (string) current_time('Y'),
        '{site}' => get_bloginfo('name'),
    ]);
}

function li_resolve_site_url(string $url): string
{
    $url = trim($url);

    if ($url === '' || str_starts_with($url, '#')) {
        return $url;
    }

    if (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $url) || preg_match('#^(?:mailto|tel):#i', $url)) {
        return $url;
    }

    if (str_starts_with($url, '/')) {
        return home_url($url);
    }

    return $url;
}

function li_rewrite_root_relative_content_links(string $content): string
{
    if ($content === '') {
        return $content;
    }

    return (string) preg_replace_callback(
        '/\s(href)=("|\')(\/(?!\/|wp-admin\/|wp-content\/|wp-includes\/)[^"\']*)\2/i',
        static function (array $matches): string {
            return ' ' . $matches[1] . '=' . $matches[2] . esc_url(li_resolve_site_url($matches[3])) . $matches[2];
        },
        $content
    );
}

add_filter('the_content', 'li_rewrite_root_relative_content_links', 20);
add_filter('render_block', static function (string $block_content): string {
    return li_rewrite_root_relative_content_links($block_content);
}, 20);

add_filter('body_class', function (array $classes): array {
    $settings = li_get_global_layout_settings();

    if (!li_theme_setting_checkbox_value($settings, 'sticky_header')) {
        $classes[] = 'li-header-static';
    }

    if (!empty($settings['compact_header'])) {
        $classes[] = 'li-header-compact';
    }

    if (!li_theme_setting_checkbox_value($settings, 'show_site_title')) {
        $classes[] = 'li-site-title-hidden';
    }

    if (!empty($settings['show_header_actions_mobile'])) {
        $classes[] = 'li-mobile-header-actions';
    }

    if (($settings['footer_density'] ?? 'comfortable') === 'compact') {
        $classes[] = 'li-footer-compact';
    }

    return $classes;
});

function li_get_account_url(): string
{
    if (function_exists('wc_get_page_permalink')) {
        $account_url = wc_get_page_permalink('myaccount');

        if ($account_url) {
            return $account_url;
        }
    }

    return home_url('/account/');
}

function li_get_cart_url(): string
{
    if (function_exists('wc_get_cart_url')) {
        return wc_get_cart_url();
    }

    return home_url('/cart/');
}

function li_is_woocommerce_available(): bool
{
    return class_exists('WooCommerce') || function_exists('wc_get_page_permalink');
}

function li_render_action_link(string $label, string $url, string $class_name): string
{
    $label = trim($label);
    $url = trim($url);

    if ($label === '' || $url === '') {
        return '';
    }

    return sprintf(
        '<a class="%1$s" href="%2$s">%3$s</a>',
        esc_attr($class_name),
        esc_url(li_resolve_site_url($url)),
        esc_html($label)
    );
}

function li_parse_footer_links(string $links): array
{
    $items = [];
    $lines = preg_split('/\r\n|\r|\n/', $links);

    if (!$lines) {
        return [];
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        $parts = array_map('trim', explode('|', $line, 2));
        $label = $parts[0] ?? '';
        $url = $parts[1] ?? '';

        if ($label === '' || $url === '') {
            continue;
        }

        $items[] = [
            'label' => $label,
            'url'   => $url,
        ];
    }

    return $items;
}

add_shortcode('li_announcement_bar', function (): string {
    $settings = li_get_global_layout_settings();

    if (!li_theme_setting_checkbox_value($settings, 'announcement_enabled')) {
        return '';
    }

    $text = trim((string) $settings['announcement_text']);
    $link_label = trim((string) $settings['announcement_link_label']);
    $link_url = trim((string) $settings['announcement_link_url']);

    if ($text === '' && ($link_label === '' || $link_url === '')) {
        return '';
    }

    ob_start();
    ?>
    <div class="li-announcement">
        <div class="li-announcement__inner">
            <?php if ($text !== '') : ?>
                <p><?php echo esc_html($text); ?></p>
            <?php endif; ?>

            <?php if ($link_label !== '' && $link_url !== '') : ?>
                <p><a href="<?php echo esc_url(li_resolve_site_url($link_url)); ?>"><?php echo esc_html($link_label); ?></a></p>
            <?php endif; ?>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
});

add_shortcode('li_header_actions', function (): string {
    $settings = li_get_global_layout_settings();

    $links = [
        li_render_action_link(
            (string) $settings['header_primary_label'],
            (string) $settings['header_primary_url'],
            'li-header-action li-header-action--link'
        ),
        li_render_action_link(
            (string) $settings['header_secondary_label'],
            (string) $settings['header_secondary_url'],
            'li-header-action li-header-action--primary'
        ),
    ];

    if (li_theme_setting_checkbox_value($settings, 'show_account_link')) {
        $links[] = li_render_action_link(
            __('Account', 'lloyds-industrial'),
            li_get_account_url(),
            'li-header-action li-header-action--link li-header-action--account'
        );
    }

    if (li_is_woocommerce_available() && li_theme_setting_checkbox_value($settings, 'show_cart_link')) {
        $links[] = li_render_action_link(
            __('Cart', 'lloyds-industrial'),
            li_get_cart_url(),
            'li-header-action li-header-action--link li-header-action--cart'
        );
    }

    $links = array_filter($links);

    if (!$links) {
        return '';
    }

    return '<nav class="li-header-actions" aria-label="' . esc_attr__('Header actions', 'lloyds-industrial') . '">' . implode('', $links) . '</nav>';
});

add_shortcode('li_footer_tagline', function (): string {
    $settings = li_get_global_layout_settings();
    $tagline = trim((string) $settings['footer_tagline']);

    if ($tagline === '') {
        return '';
    }

    return '<p class="li-footer-tagline">' . esc_html($tagline) . '</p>';
});

add_shortcode('li_footer_columns', function (): string {
    $settings = li_get_global_layout_settings();
    $output = '';

    foreach (li_get_footer_column_indexes() as $index) {
        if (!li_theme_setting_checkbox_value($settings, 'footer_column_' . $index . '_enabled')) {
            continue;
        }

        $heading = trim((string) $settings['footer_column_' . $index . '_heading']);
        $links = li_parse_footer_links((string) $settings['footer_column_' . $index . '_links']);

        if ($heading === '' && !$links) {
            continue;
        }

        ob_start();
        ?>
        <div class="li-footer-column">
            <?php if ($heading !== '') : ?>
                <h3><?php echo esc_html($heading); ?></h3>
            <?php endif; ?>

            <?php if ($links) : ?>
                <ul class="li-footer-list">
                    <?php foreach ($links as $link) : ?>
                        <li><a href="<?php echo esc_url(li_resolve_site_url($link['url'])); ?>"><?php echo esc_html($link['label']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
        $output .= (string) ob_get_clean();
    }

    if ($output === '') {
        return '';
    }

    return '<div class="li-footer-columns">' . $output . '</div>';
});

add_shortcode('li_footer_legal', function (): string {
    $settings = li_get_global_layout_settings();
    $legal = trim((string) $settings['footer_legal_text']);

    if ($legal === '') {
        return '';
    }

    return '<p class="has-small-font-size">' . wp_kses_post(li_format_layout_text($legal)) . '</p>';
});

add_shortcode('li_footer_note', function (): string {
    $settings = li_get_global_layout_settings();

    if (!li_theme_setting_checkbox_value($settings, 'footer_note_enabled')) {
        return '';
    }

    $note = trim((string) $settings['footer_note_text']);

    if ($note === '') {
        return '';
    }

    return '<p class="has-small-font-size">' . esc_html(li_format_layout_text($note)) . '</p>';
});
