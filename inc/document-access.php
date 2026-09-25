<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function li_is_sds_document(int $document_id): bool
{
    $terms = wp_get_post_terms($document_id, 'li_document_type');

    if (is_wp_error($terms) || !$terms) {
        return false;
    }

    foreach ($terms as $term) {
        $slug = sanitize_title($term->slug);
        $name = sanitize_title($term->name);

        if (in_array($slug, ['sds', 'safety-data-sheet', 'safety-data-sheets'], true)) {
            return true;
        }

        if (in_array($name, ['sds', 'safety-data-sheet', 'safety-data-sheets'], true)) {
            return true;
        }
    }

    return false;
}

function li_get_document_access_label(int $document_id): string
{
    if (li_is_sds_document($document_id)) {
        return __('SDS - Purchase Required', 'b2b-industrial');
    }

    $access_level = (string) get_post_meta($document_id, '_li_access_level', true);

    if ($access_level === 'internal') {
        return __('Internal', 'b2b-industrial');
    }

    return __('Public', 'b2b-industrial');
}

function li_user_bought_document_product(int $document_id, int $user_id): bool
{
    $product_id = (int) get_post_meta($document_id, '_li_related_product_id', true);

    if (!$product_id || !function_exists('wc_customer_bought_product')) {
        return false;
    }

    $user = get_userdata($user_id);

    if (!$user instanceof WP_User) {
        return false;
    }

    return wc_customer_bought_product($user->user_email, $user_id, $product_id);
}

function li_user_has_document_access_for_document(int $document_id, ?int $user_id = null): bool
{
    $user_id = $user_id ?: get_current_user_id();
    $access_level = (string) get_post_meta($document_id, '_li_access_level', true);

    if (!$access_level) {
        $access_level = 'public';
    }

    if ($user_id && (user_can($user_id, 'manage_options') || user_can($user_id, 'manage_li_documents'))) {
        return true;
    }

    if (li_get_document_access_mode() === 'private') {
        return false;
    }

    if ($access_level === 'internal') {
        return false;
    }

    if (!li_is_sds_document($document_id)) {
        return true;
    }

    if (!$user_id) {
        return false;
    }

    return li_user_bought_document_product($document_id, $user_id);
}

function li_user_can_view_document_listing(int $document_id, ?int $user_id = null): bool
{
    $user_id = $user_id ?: get_current_user_id();
    $access_level = (string) get_post_meta($document_id, '_li_access_level', true);

    if (!$access_level) {
        $access_level = 'public';
    }

    if ($user_id && (user_can($user_id, 'manage_options') || user_can($user_id, 'manage_li_documents'))) {
        return true;
    }

    if (li_get_document_access_mode() === 'private') {
        return false;
    }

    if ($access_level === 'internal') {
        return false;
    }

    if (li_is_sds_document($document_id)) {
        return $user_id ? li_user_bought_document_product($document_id, $user_id) : false;
    }

    return true;
}

function li_get_protected_documents_dir(): array
{
    $upload_dir = wp_upload_dir(null, false);
    $base_dir = trailingslashit($upload_dir['basedir']) . 'li-protected-documents';

    if (!is_dir($base_dir)) {
        wp_mkdir_p($base_dir);
    }

    if (!is_dir($base_dir) || !is_writable($base_dir)) {
        return [
            'path' => $base_dir,
        ];
    }

    li_write_protected_document_directory_guards($base_dir);

    return [
        'path' => $base_dir,
    ];
}

function li_write_protected_document_directory_guards(string $dir): void
{
    if (!is_dir($dir) || !is_writable($dir)) {
        return;
    }

    $index_path = trailingslashit($dir) . 'index.php';

    if (!file_exists($index_path)) {
        @file_put_contents($index_path, "<?php\n// Silence is golden.\n");
    }

    $htaccess_path = trailingslashit($dir) . '.htaccess';

    if (!file_exists($htaccess_path)) {
        @file_put_contents($htaccess_path, "Require all denied\nDeny from all\n");
    }

    $web_config_path = trailingslashit($dir) . 'web.config';

    if (!file_exists($web_config_path)) {
        @file_put_contents(
            $web_config_path,
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n    <security>\n      <authorization>\n        <remove users=\"*\" roles=\"\" verbs=\"\" />\n        <add accessType=\"Deny\" users=\"*\" />\n      </authorization>\n    </security>\n  </system.webServer>\n</configuration>\n"
        );
    }
}

function li_get_sds_protected_documents_dir(int $document_id): array
{
    $base = li_get_protected_documents_dir();

    if (empty($base['path']) || !is_dir($base['path']) || !is_writable($base['path'])) {
        return $base;
    }

    $target_dir = trailingslashit($base['path']) . li_get_sds_protected_relative_dir($document_id);

    if (!is_dir($target_dir)) {
        wp_mkdir_p($target_dir);
    }

    if (!is_dir($target_dir) || !is_writable($target_dir)) {
        return $base;
    }

    li_write_protected_document_directory_guards($target_dir);

    return [
        'path' => $target_dir,
    ];
}

function li_get_sds_protected_relative_dir(int $document_id): string
{
    $product_id = (int) get_post_meta($document_id, '_li_related_product_id', true);
    $category_slug = 'uncategorized';
    $item_slug = sanitize_title(get_post_field('post_name', $document_id));

    if ($product_id && get_post_type($product_id) === 'product') {
        $terms = get_the_terms($product_id, 'product_cat');

        if (is_array($terms) && $terms) {
            usort($terms, static fn (WP_Term $a, WP_Term $b): int => strcasecmp($a->name, $b->name));
            $category_slug = sanitize_title($terms[0]->slug ?: $terms[0]->name);
        }

        $sku = (string) get_post_meta($product_id, '_sku', true);
        $item_slug = $sku !== ''
            ? sanitize_title($sku)
            : sanitize_title(get_post_field('post_name', $product_id));
    }

    if ($item_slug === '') {
        $item_slug = 'sds-' . $document_id;
    }

    return 'sds/' . $category_slug . '/' . $item_slug;
}

function li_get_protected_document_path(int $document_id): ?string
{
    $stored_path = (string) get_post_meta($document_id, '_li_protected_document_path', true);

    if (!$stored_path) {
        return null;
    }

    $dir = li_get_protected_documents_dir();
    $base_path = realpath($dir['path']);
    $file_path = realpath($stored_path);

    if (!$base_path || !$file_path) {
        return null;
    }

    $normalized_base = trailingslashit(wp_normalize_path($base_path));
    $normalized_file = wp_normalize_path($file_path);

    if (strpos($normalized_file, $normalized_base) !== 0) {
        return null;
    }

    return is_readable($file_path) ? $file_path : null;
}

function li_protect_document_file(int $document_id): bool
{
    if (!li_is_sds_document($document_id)) {
        return false;
    }

    $file_id = li_get_document_file_id($document_id);
    $source_path = $file_id ? get_attached_file($file_id) : '';
    $protected_path = li_get_protected_document_path($document_id);
    $dir = li_get_sds_protected_documents_dir($document_id);

    if ($protected_path) {
        if ($file_id && is_string($source_path) && is_readable($source_path) && !li_document_path_is_in_protected_storage($source_path)) {
            update_attached_file($file_id, $protected_path);
            @unlink($source_path);
        }

        li_maybe_move_protected_document_to_dir($document_id, $dir['path']);

        return true;
    }

    if (!$source_path || !is_string($source_path) || !is_readable($source_path)) {
        return false;
    }

    if (!is_dir($dir['path']) || !is_writable($dir['path'])) {
        return false;
    }

    $filename = wp_unique_filename($dir['path'], $document_id . '-' . sanitize_file_name(basename($source_path)));
    $target_path = trailingslashit($dir['path']) . $filename;

    if (!@rename($source_path, $target_path)) {
        if (!@copy($source_path, $target_path)) {
            return false;
        }

        @unlink($source_path);
    }

    if (!is_readable($target_path)) {
        return false;
    }

    update_attached_file($file_id, $target_path);
    update_post_meta($document_id, '_li_protected_document_path', $target_path);

    return true;
}

function li_maybe_move_protected_document_to_dir(int $document_id, string $target_dir): bool
{
    $file_path = li_get_protected_document_path($document_id);

    if (!$file_path || !is_readable($file_path) || !is_dir($target_dir) || !is_writable($target_dir)) {
        return false;
    }

    $target_base = realpath($target_dir);
    $current_path = realpath($file_path);

    if (!$target_base || !$current_path) {
        return false;
    }

    $normalized_target = trailingslashit(wp_normalize_path($target_base));
    $normalized_current = wp_normalize_path($current_path);

    if (strpos($normalized_current, $normalized_target) === 0) {
        return false;
    }

    $filename = wp_unique_filename($target_dir, basename($file_path));
    $target_path = trailingslashit($target_dir) . $filename;

    if (!@rename($file_path, $target_path)) {
        if (!@copy($file_path, $target_path)) {
            return false;
        }

        @unlink($file_path);
    }

    if (!is_readable($target_path)) {
        return false;
    }

    $file_id = li_get_document_file_id($document_id);

    if ($file_id) {
        update_attached_file($file_id, $target_path);
    }

    update_post_meta($document_id, '_li_protected_document_path', $target_path);

    return true;
}

function li_document_path_is_in_protected_storage(string $file_path): bool
{
    $dir = li_get_protected_documents_dir();
    $base_path = realpath($dir['path']);
    $real_file_path = realpath($file_path);

    if (!$base_path || !$real_file_path) {
        return false;
    }

    $normalized_base = trailingslashit(wp_normalize_path($base_path));
    $normalized_file = wp_normalize_path($real_file_path);

    return strpos($normalized_file, $normalized_base) === 0;
}

function li_delete_protected_document_copy(int $document_id): void
{
    $file_path = li_get_protected_document_path($document_id);

    if ($file_path && is_writable($file_path)) {
        @unlink($file_path);
    }

    delete_post_meta($document_id, '_li_protected_document_path');
}

function li_migrate_sds_documents_to_protected_storage(): array
{
    $query = new WP_Query([
        'post_type'      => 'li_document',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    $migrated = 0;
    $skipped = 0;
    $failed = 0;

    foreach ($query->posts as $document_id) {
        $document_id = (int) $document_id;

        if (!li_is_sds_document($document_id)) {
            $skipped++;
            continue;
        }

        $file_id = li_get_document_file_id($document_id);
        $file_path = $file_id ? get_attached_file($file_id) : '';
        $protected_path = li_get_protected_document_path($document_id);

        if ($protected_path && (!$file_id || !is_string($file_path) || li_document_path_is_in_protected_storage($file_path))) {
            if (li_maybe_move_protected_document_to_dir(
                $document_id,
                li_get_sds_protected_documents_dir($document_id)['path']
            )) {
                $migrated++;
                continue;
            }

            $skipped++;
            continue;
        }

        if (li_protect_document_file($document_id)) {
            $migrated++;
            continue;
        }

        $failed++;
    }

    return [
        'migrated' => $migrated,
        'skipped'  => $skipped,
        'failed'   => $failed,
    ];
}

function li_get_document_file_id(int $document_id): int
{
    return (int) get_post_meta($document_id, '_li_document_file_id', true);
}

function li_get_document_public_file_url(int $document_id): ?string
{
    if (li_is_sds_document($document_id)) {
        return null;
    }

    $file_id = li_get_document_file_id($document_id);

    return $file_id ? wp_get_attachment_url($file_id) ?: null : null;
}

function li_get_document_download_url(int $document_id): ?string
{
    if (!li_user_has_document_access_for_document($document_id)) {
        return null;
    }

    if (li_is_sds_document($document_id)) {
        return li_get_protected_document_path($document_id) || li_get_document_file_id($document_id)
            ? li_get_secure_document_url($document_id)
            : null;
    }

    return li_get_document_public_file_url($document_id);
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
