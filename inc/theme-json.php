<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_filter('wp_theme_json_data_theme', 'li_filter_theme_json_brand_palette');

function li_filter_theme_json_brand_palette(WP_Theme_JSON_Data $theme_json): WP_Theme_JSON_Data
{
    $brand = li_get_brand_settings();
    $defaults = li_get_brand_defaults();

    $primary   = sanitize_hex_color($brand['primary_color'] ?? '') ?: $defaults['primary_color'];
    $secondary = sanitize_hex_color($brand['secondary_color'] ?? '') ?: $defaults['secondary_color'];
    $accent    = sanitize_hex_color($brand['accent_color'] ?? '') ?: $defaults['accent_color'];
    $surface   = sanitize_hex_color($brand['surface_color'] ?? '') ?: $defaults['surface_color'];
    $text      = sanitize_hex_color($brand['text_color'] ?? '') ?: $defaults['text_color'];
    $header_bg = sanitize_hex_color($brand['header_bg'] ?? '') ?: $defaults['header_bg'];
    $footer_bg = sanitize_hex_color($brand['footer_bg'] ?? '') ?: $defaults['footer_bg'];
    $overlay   = sanitize_hex_color($brand['hero_overlay'] ?? '') ?: $defaults['hero_overlay'];

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
                        'slug'  => 'muted',
                        'name'  => __('Muted', 'lloyds-industrial'),
                        'color' => '#66736F',
                    ],
                    [
                        'slug'  => 'border',
                        'name'  => __('Border', 'lloyds-industrial'),
                        'color' => '#DDE5E2',
                    ],
                    [
                        'slug'  => 'alert',
                        'name'  => __('Alert', 'lloyds-industrial'),
                        'color' => '#B83A34',
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
                    [
                        'slug'  => 'black',
                        'name'  => __('Black', 'lloyds-industrial'),
                        'color' => '#000000',
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
