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
        'primary_color'   => '#3f3f3f',
        'secondary_color' => '#dd3333',
        'accent_color'    => '#dd9933',
        'surface_color'   => '#3f3f3f',
        'text_color'      => '#141414',
        'header_bg'       => '#c1c1c1',
        'footer_bg'       => '#3f3f3f',
        'hero_overlay'    => '#3f3f3f',
    ];
}

function li_get_brand_color_labels(): array
{
    return [
        'primary_color'   => __('Primary Color', 'b2b-industrial'),
        'secondary_color' => __('Secondary Color', 'b2b-industrial'),
        'accent_color'    => __('Accent Color', 'b2b-industrial'),
        'surface_color'   => __('Surface Color', 'b2b-industrial'),
        'text_color'      => __('Text Color', 'b2b-industrial'),
        'header_bg'       => __('Header Background', 'b2b-industrial'),
        'footer_bg'       => __('Footer Background', 'b2b-industrial'),
        'hero_overlay'    => __('Hero Overlay Color', 'b2b-industrial'),
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
