<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function (): void {
    add_rewrite_rule(
        '^secure-document/([0-9]+)/?',
        'index.php?li_secure_document=$matches[1]',
        'top'
    );
});

add_filter('query_vars', function (array $vars): array {
    $vars[] = 'li_secure_document';

    return $vars;
});

add_action('template_redirect', function (): void {
    $document_id = absint(get_query_var('li_secure_document'));

    if (!$document_id) {
        return;
    }

    if (!li_user_has_document_access_for_document($document_id)) {
        if (!is_user_logged_in()) {
            wp_safe_redirect(li_get_account_login_url(li_get_secure_document_url($document_id)));
            exit;
        }

        wp_die(
            esc_html__('You do not have access to this document.', 'lloyds-industrial'),
            esc_html__('Document Access Denied', 'lloyds-industrial'),
            ['response' => 403]
        );
        exit;
    }

    if (li_is_sds_document($document_id)) {
        li_stream_protected_document($document_id);
        exit;
    }

    $download_url = li_get_document_public_file_url($document_id);

    if (!$download_url) {
        wp_die(
            esc_html__('The requested document file could not be found.', 'lloyds-industrial'),
            esc_html__('Document Not Found', 'lloyds-industrial'),
            ['response' => 404]
        );
        exit;
    }

    wp_safe_redirect($download_url);
    exit;
});

function li_get_secure_document_url(int $document_id): string
{
    return home_url('/secure-document/' . $document_id . '/');
}

function li_stream_protected_document(int $document_id): void
{
    $file_id = li_get_document_file_id($document_id);
    $file_path = li_get_protected_document_path($document_id);

    if (!$file_path) {
        li_protect_document_file($document_id);
        $file_path = li_get_protected_document_path($document_id);
    }

    if (!$file_path) {
        wp_die(
            esc_html__('The requested document file could not be found.', 'lloyds-industrial'),
            esc_html__('Document Not Found', 'lloyds-industrial'),
            ['response' => 404]
        );
        exit;
    }

    $mime_type = get_post_mime_type($file_id) ?: 'application/octet-stream';
    $filename = basename($file_path);
    $file_size = filesize($file_path);

    while (ob_get_level()) {
        ob_end_clean();
    }

    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow', true);
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
    header('Content-Transfer-Encoding: binary');

    if ($file_size !== false) {
        header('Content-Length: ' . (string) $file_size);
    }

    readfile($file_path);
}
