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

    $download_url = li_get_document_download_url($document_id);

    if (!$download_url) {
        auth_redirect();
        exit;
    }

    wp_safe_redirect($download_url);
    exit;
});

function li_get_secure_document_url(int $document_id): string
{
    return home_url('/secure-document/' . $document_id . '/');
}