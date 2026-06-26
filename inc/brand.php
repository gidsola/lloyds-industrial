<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function li_get_theme_settings(): array
{
    $settings = get_option('li_theme_settings', []);

    return is_array($settings) ? $settings : [];
}

function li_get_brand_defaults(): array
{
    return [
        'primary_color'   => '#c62d2d',
        'secondary_color' => '#2E5F73',
        'accent_color'    => '#D9A441',
        'surface_color'   => '#F5F7F6',
        'text_color'      => '#182126',
        'header_bg'       => '#FFFFFF',
        'footer_bg'       => '#12332E',
        'hero_overlay'    => '#081A18',
    ];
}

function li_get_brand_color_labels(): array
{
    return [
        'primary_color'   => __('Primary Color', 'lloyds-industrial'),
        'secondary_color' => __('Secondary Color', 'lloyds-industrial'),
        'accent_color'    => __('Accent Color', 'lloyds-industrial'),
        'surface_color'   => __('Surface Color', 'lloyds-industrial'),
        'text_color'      => __('Text Color', 'lloyds-industrial'),
        'header_bg'       => __('Header Background', 'lloyds-industrial'),
        'footer_bg'       => __('Footer Background', 'lloyds-industrial'),
        'hero_overlay'    => __('Hero Overlay Color', 'lloyds-industrial'),
    ];
}

function li_get_brand_settings(): array
{
    $settings = get_option('li_brand_settings', []);

    return wp_parse_args(
        is_array($settings) ? $settings : [],
        li_get_brand_defaults()
    );
}

function li_get_brand_color(string $key): string
{
    $settings = li_get_brand_settings();
    $defaults = li_get_brand_defaults();

    return sanitize_hex_color($settings[$key] ?? '') ?: ($defaults[$key] ?? '#000000');
}

function li_get_brand_name(): string
{
    return (string) get_bloginfo('name');
}

function li_get_brand_accent_color(): string
{
    return li_get_brand_color('accent_color');
}

function li_get_document_access_mode(): string
{
    $settings = li_get_theme_settings();
    $mode = $settings['documents_mode'] ?? 'controlled';

    return in_array($mode, ['controlled', 'private'], true)
        ? $mode
        : 'controlled';
}
