<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_filter('wp_theme_json_data_theme', 'li_filter_theme_json_brand_palette');

function li_filter_theme_json_brand_palette(WP_Theme_JSON_Data $theme_json): WP_Theme_JSON_Data
{
    $brand = li_get_brand_settings();

    $primary   = sanitize_hex_color($brand['primary_color'] ?? '') ?: '#173449';
    $secondary = sanitize_hex_color($brand['secondary_color'] ?? '') ?: '#234A64';
    $accent    = sanitize_hex_color($brand['accent_color'] ?? '') ?: '#D8A03F';
    $surface   = sanitize_hex_color($brand['surface_color'] ?? '') ?: '#F7F9FB';
    $text      = sanitize_hex_color($brand['text_color'] ?? '') ?: '#1C252D';
    $header_bg = sanitize_hex_color($brand['header_bg'] ?? '') ?: '#FFFFFF';
    $footer_bg = sanitize_hex_color($brand['footer_bg'] ?? '') ?: '#173449';
    $overlay   = sanitize_hex_color($brand['hero_overlay'] ?? '') ?: '#08141E';

    $theme_json->update_with([
        'version'  => 3,
        'settings' => [
            'color' => [
                'palette' => [
                    [
                        'slug'  => 'primary',
                        'name'  => __('Primary', 'lloyds-industrial'),
                        'color' => $primary,
                    ],
                    [
                        'slug'  => 'secondary',
                        'name'  => __('Secondary', 'lloyds-industrial'),
                        'color' => $secondary,
                    ],
                    [
                        'slug'  => 'accent',
                        'name'  => __('Accent', 'lloyds-industrial'),
                        'color' => $accent,
                    ],
                    [
                        'slug'  => 'surface',
                        'name'  => __('Surface', 'lloyds-industrial'),
                        'color' => $surface,
                    ],
                    [
                        'slug'  => 'white',
                        'name'  => __('White', 'lloyds-industrial'),
                        'color' => '#FFFFFF',
                    ],
                    [
                        'slug'  => 'body-text',
                        'name'  => __('Text', 'lloyds-industrial'),
                        'color' => $text,
                    ],
                    [
                        'slug'  => 'header-bg',
                        'name'  => __('Header Background', 'lloyds-industrial'),
                        'color' => $header_bg,
                    ],
                    [
                        'slug'  => 'footer-bg',
                        'name'  => __('Footer Background', 'lloyds-industrial'),
                        'color' => $footer_bg,
                    ],
                    [
                        'slug'  => 'hero-overlay',
                        'name'  => __('Hero Overlay', 'lloyds-industrial'),
                        'color' => $overlay,
                    ],
                ],
            ],
        ],
        'styles' => [
            'color' => [
                'background' => '#FFFFFF',
                'text'       => $text,
            ],
        ],
    ]);

    return $theme_json;
}