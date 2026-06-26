<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('after_switch_theme', function (): void {
    flush_rewrite_rules();

    if (!get_option('li_theme_settings')) {
        update_option('li_theme_settings', li_get_global_layout_defaults());
    }
});

add_action('switch_theme', function (): void {
    flush_rewrite_rules();
});
