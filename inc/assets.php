<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', function (): void {
    li_enqueue_google_fonts();

    wp_enqueue_style(
        'lloyds-style',
        get_template_directory_uri() . '/assets/css/main.css',
        [],
        filemtime(get_template_directory() . '/assets/css/main.css')
    );

    wp_add_inline_style(
        'lloyds-style',
        li_get_dynamic_brand_css()
    );

    wp_enqueue_script(
        'lloyds-theme',
        get_template_directory_uri() . '/assets/js/theme.js',
        [],
        filemtime(get_template_directory() . '/assets/js/theme.js'),
        true
    );
});

add_action('enqueue_block_editor_assets', function (): void {
    li_enqueue_google_fonts('lloyds-editor-fonts');

    wp_enqueue_style(
        'lloyds-editor-main',
        get_template_directory_uri() . '/assets/css/main.css',
        [],
        filemtime(get_template_directory() . '/assets/css/main.css')
    );

    wp_add_inline_style(
        'lloyds-editor-main',
        li_get_dynamic_brand_css()
    );

    wp_enqueue_style(
        'lloyds-editor',
        get_template_directory_uri() . '/assets/css/editor.css',
        ['lloyds-editor-main'],
        filemtime(get_template_directory() . '/assets/css/editor.css')
    );
});

add_action('admin_enqueue_scripts', function (): void {
    $screen = get_current_screen();

    if (!$screen || !in_array($screen->post_type, ['product', 'li_document'], true)) {
        return;
    }

    wp_enqueue_media();

    wp_enqueue_script(
        'lloyds-admin',
        get_template_directory_uri() . '/assets/js/admin.js',
        ['jquery'],
        filemtime(get_template_directory() . '/assets/js/admin.js'),
        true
    );
});

function li_enqueue_google_fonts(string $handle = 'lloyds-fonts'): void
{
    wp_enqueue_style(
        $handle,
        'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap',
        [],
        null
    );
}

function li_get_dynamic_brand_css(): string
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

    return sprintf(
        ':root,
        .editor-styles-wrapper {
            --wp--preset--color--primary:%1$s;
            --wp--preset--color--secondary:%2$s;
            --wp--preset--color--accent:%3$s;
            --wp--preset--color--surface:%4$s;
            --wp--preset--color--body-text:%5$s;

            --li-primary:%1$s;
            --li-secondary:%2$s;
            --li-accent:%3$s;
            --li-surface:%4$s;
            --li-text:%5$s;
            --li-header-bg:%6$s;
            --li-footer-bg:%7$s;
            --li-hero-overlay:%8$s;
        }

        .has-primary-background-color,
        .editor-styles-wrapper .has-primary-background-color {
            background-color:%1$s !important;
        }

        .has-secondary-background-color,
        .editor-styles-wrapper .has-secondary-background-color {
            background-color:%2$s !important;
        }

        .has-accent-background-color,
        .editor-styles-wrapper .has-accent-background-color,
        .wp-block-button__link.has-accent-background-color,
        .editor-styles-wrapper .wp-block-button__link.has-accent-background-color {
            background-color:%3$s !important;
        }

        .has-surface-background-color,
        .editor-styles-wrapper .has-surface-background-color {
            background-color:%4$s !important;
        }

        .site-header,
        .editor-styles-wrapper .site-header {
            background-color:%6$s !important;
        }

        .li-footer,
        .editor-styles-wrapper .li-footer {
            background-color:%7$s !important;
        }',
        esc_html($primary),
        esc_html($secondary),
        esc_html($accent),
        esc_html($surface),
        esc_html($text),
        esc_html($header_bg),
        esc_html($footer_bg),
        esc_html($overlay)
    );
}
