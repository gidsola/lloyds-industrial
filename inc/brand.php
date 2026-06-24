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

function li_get_brand_settings(): array
{
    $defaults = [
        'primary_color'   => '#173449',
        'secondary_color' => '#234A64',
        'accent_color'    => '#D8A03F',
        'surface_color'   => '#F7F9FB',
        'text_color'      => '#1C252D',
        'header_bg'       => '#FFFFFF',
        'footer_bg'       => '#173449',
        'hero_overlay'    => '#08141E',
    ];

    $settings = get_option('li_brand_settings', []);

    return wp_parse_args(
        is_array($settings) ? $settings : [],
        $defaults
    );
}

function li_get_brand_color(string $key): string
{
    $settings = li_get_brand_settings();

    return sanitize_hex_color($settings[$key] ?? '') ?: li_get_brand_settings()[$key];
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

    return in_array($mode, ['controlled', 'public', 'private'], true)
        ? $mode
        : 'controlled';
}