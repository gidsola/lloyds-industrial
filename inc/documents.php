<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function (): void {
    register_post_type('li_document', [
        'labels' => [
            'name'          => __('Documents', 'b2b-industrial'),
            'singular_name' => __('Document', 'b2b-industrial'),
            'add_new_item'  => __('Add New Document', 'b2b-industrial'),
            'edit_item'     => __('Edit Document', 'b2b-industrial'),
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => false,
        'show_in_rest'        => true,
        'menu_icon'           => 'dashicons-media-document',
        'supports'            => ['title', 'editor', 'excerpt'],
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'exclude_from_search' => true,
    ]);

    register_taxonomy('li_document_type', ['li_document'], [
        'labels' => [
            'name'          => __('Document Types', 'b2b-industrial'),
            'singular_name' => __('Document Type', 'b2b-industrial'),
        ],
        'public'       => false,
        'show_ui'      => true,
        'show_in_rest' => true,
        'hierarchical' => true,
    ]);

    li_ensure_default_document_types();

    register_post_meta('li_document', '_li_document_file_id', [
        'type'              => 'integer',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'absint',
        'auth_callback'     => fn (): bool => current_user_can('manage_li_documents') || current_user_can('manage_options'),
    ]);

    register_post_meta('li_document', '_li_related_product_id', [
        'type'              => 'integer',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'absint',
        'auth_callback'     => fn (): bool => current_user_can('manage_li_documents') || current_user_can('manage_options'),
    ]);

    register_post_meta('li_document', '_li_access_level', [
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'sanitize_key',
        'auth_callback'     => fn (): bool => current_user_can('manage_li_documents') || current_user_can('manage_options'),
    ]);

    register_post_meta('li_document', '_li_protected_document_path', [
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => false,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback'     => fn (): bool => current_user_can('manage_li_documents') || current_user_can('manage_options'),
    ]);
});

add_action('admin_menu', function (): void {
    add_menu_page(
        __('B2B Documents', 'b2b-industrial'),
        __('Documents', 'b2b-industrial'),
        'edit_posts',
        'b2b-documents',
        'li_redirect_to_documents_admin',
        'dashicons-media-document',
        60
    );

    add_submenu_page(
        'b2b-documents',
        __('Document Types', 'b2b-industrial'),
        __('Document Types', 'b2b-industrial'),
        'manage_categories',
        'edit-tags.php?taxonomy=li_document_type&post_type=li_document'
    );
}, 1);

function li_redirect_to_documents_admin(): void
{
    if (!headers_sent()) {
        wp_safe_redirect(admin_url('edit.php?post_type=li_document'));
        exit;
    }
}

function li_ensure_default_document_types(): void
{
    li_ensure_sds_document_type_term();
}
