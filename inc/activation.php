<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('after_switch_theme', function (): void {
    flush_rewrite_rules();

    if (!get_option('li_theme_settings')) {
        update_option('li_theme_settings', [
            'partner_mode'          => false,
            'documents_mode'        => 'controlled',
            'announcement_enabled'  => true,
            'show_account_link'     => true,
            'show_cart_link'        => true,
        ]);
    }
});

add_action('switch_theme', function (): void {
    flush_rewrite_rules();
});
