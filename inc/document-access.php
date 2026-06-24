<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function li_user_has_document_access(string $access_level, ?int $user_id = null): bool
{
    $user_id = $user_id ?: get_current_user_id();

    if ($access_level === 'public') {
        return true;
    }

    if (!$user_id) {
        return false;
    }

    if (user_can($user_id, 'manage_options')) {
        return true;
    }

    if ($access_level === 'customer') {
        return user_can($user_id, 'read_li_documents');
    }

    if ($access_level === 'distributor') {
        return user_can($user_id, 'read_li_documents') && user_can($user_id, 'read');
    }

    if ($access_level === 'internal') {
        return user_can($user_id, 'manage_li_documents');
    }

    return false;
}

function li_get_document_download_url(int $document_id): ?string
{
    $access_level = (string) get_post_meta($document_id, '_li_access_level', true);

    if (!$access_level) {
        $access_level = 'customer';
    }

    if (!li_user_has_document_access($access_level)) {
        return null;
    }

    $file_id = (int) get_post_meta($document_id, '_li_document_file_id', true);

    if (!$file_id) {
        return null;
    }

    return wp_get_attachment_url($file_id) ?: null;
}

add_action('init', function (): void {
    foreach (['administrator', 'shop_manager'] as $role_name) {
        $role = get_role($role_name);

        if (!$role) {
            continue;
        }

        $role->add_cap('read_li_documents');
        $role->add_cap('manage_li_documents');
    }
});