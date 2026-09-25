<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_filter('parent_file', 'li_admin_menu_parent_file');
add_filter('submenu_file', 'li_admin_menu_submenu_file');

function li_admin_menu_parent_file(?string $parent_file): ?string
{
    $menu_state = li_get_admin_menu_state();

    return $menu_state['parent'] ?? $parent_file;
}

function li_admin_menu_submenu_file(?string $submenu_file): ?string
{
    $menu_state = li_get_admin_menu_state();

    return $menu_state['submenu'] ?? $submenu_file;
}

function li_get_admin_menu_state(): array
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $post_type = $screen && !empty($screen->post_type) ? (string) $screen->post_type : '';
    $taxonomy = $screen && !empty($screen->taxonomy) ? (string) $screen->taxonomy : '';

    if ($post_type === '' && isset($_GET['post_type'])) {
        $post_type = sanitize_key((string) $_GET['post_type']);
    }

    if ($taxonomy === '' && isset($_GET['taxonomy'])) {
        $taxonomy = sanitize_key((string) $_GET['taxonomy']);
    }

    if ($taxonomy !== '') {
        $taxonomy_menu_state = [
            'li_document_type' => [
                'parent' => 'b2b-documents',
                'submenu' => 'edit-tags.php?taxonomy=li_document_type&post_type=li_document',
            ],
            'li_mail_tag' => [
                'parent' => 'b2b-campaigns',
                'submenu' => 'edit-tags.php?taxonomy=li_mail_tag&post_type=li_mail_subscriber',
            ],
        ];

        if (isset($taxonomy_menu_state[$taxonomy])) {
            return $taxonomy_menu_state[$taxonomy];
        }
    }

    if ($post_type === '') {
        return [];
    }

    $post_type_menu_state = [
        'li_document' => [
            'parent' => 'b2b-documents',
            'submenu' => 'b2b-documents',
        ],
        'li_mail_subscriber' => [
            'parent' => 'b2b-campaigns',
            'submenu' => 'edit.php?post_type=li_mail_subscriber',
        ],
        'li_contact_msg' => [
            'parent' => 'b2b-channels',
            'submenu' => 'edit.php?post_type=li_contact_msg',
        ],
        'li_reseller' => [
            'parent' => 'b2b-channels',
            'submenu' => 'b2b-channels',
        ],
        'b2b_flipbook' => [
            'parent' => 'b2b',
            'submenu' => 'edit.php?post_type=b2b_flipbook',
        ],
    ];

    return $post_type_menu_state[$post_type] ?? [];
}
