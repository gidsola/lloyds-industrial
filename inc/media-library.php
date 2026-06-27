<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_filter('manage_media_columns', 'li_media_add_library_columns');
add_action('manage_media_custom_column', 'li_media_render_library_column', 10, 2);
add_action('restrict_manage_posts', 'li_media_render_library_context_filter');
add_action('pre_get_posts', 'li_media_filter_library_query');
add_filter('attachment_fields_to_edit', 'li_media_add_attachment_context_fields', 10, 2);
add_filter('ajax_query_attachments_args', 'li_media_filter_ajax_library_query');

function li_media_add_library_columns(array $columns): array
{
    $columns['li_media_context'] = __('Lloyds Use', 'lloyds-industrial');
    $columns['li_media_storage'] = __('Storage', 'lloyds-industrial');

    return $columns;
}

function li_media_render_library_column(string $column_name, int $attachment_id): void
{
    if ($column_name === 'li_media_context') {
        echo wp_kses_post(li_media_get_attachment_context_markup($attachment_id));
        return;
    }

    if ($column_name === 'li_media_storage') {
        echo esc_html(li_media_get_attachment_storage_label($attachment_id));
    }
}

function li_media_render_library_context_filter(string $post_type): void
{
    if ($post_type !== 'attachment') {
        return;
    }

    $selected = isset($_GET['li_media_context']) ? sanitize_key((string) $_GET['li_media_context']) : '';
    $selected_folder = isset($_GET['li_media_folder']) ? sanitize_text_field(wp_unslash((string) $_GET['li_media_folder'])) : '';
    ?>
    <label class="screen-reader-text" for="li_media_context"><?php esc_html_e('Filter by Lloyds media use', 'lloyds-industrial'); ?></label>
    <select name="li_media_context" id="li_media_context">
        <option value=""><?php esc_html_e('All Lloyds media uses', 'lloyds-industrial'); ?></option>
        <?php foreach (li_media_get_context_filter_options() as $value => $label): ?>
            <option value="<?php echo esc_attr($value); ?>" <?php selected($selected, $value); ?>>
                <?php echo esc_html($label); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <label class="screen-reader-text" for="li_media_folder"><?php esc_html_e('Filter by Lloyds folder', 'lloyds-industrial'); ?></label>
    <select name="li_media_folder" id="li_media_folder">
        <option value=""><?php esc_html_e('All Lloyds folders', 'lloyds-industrial'); ?></option>
        <?php foreach (li_media_get_folder_filter_options() as $folder => $label): ?>
            <option value="<?php echo esc_attr($folder); ?>" <?php selected($selected_folder, $folder); ?>>
                <?php echo esc_html($label); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}

function li_media_filter_library_query(WP_Query $query): void
{
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'attachment') {
        return;
    }

    $context = isset($_GET['li_media_context']) ? sanitize_key((string) $_GET['li_media_context']) : '';

    if (isset(li_media_get_context_filter_options()[$context])) {
        $ids = li_media_get_attachment_ids_for_context($context);
        $query->set('post__in', $ids ?: [0]);
    }

    li_media_apply_folder_query_filter($query);
}

function li_media_filter_ajax_library_query(array $args): array
{
    $context = isset($_REQUEST['query']['li_media_context'])
        ? sanitize_key((string) $_REQUEST['query']['li_media_context'])
        : '';
    $folder = isset($_REQUEST['query']['li_media_folder'])
        ? sanitize_text_field(wp_unslash((string) $_REQUEST['query']['li_media_folder']))
        : '';

    if ($context && isset(li_media_get_context_filter_options()[$context])) {
        $ids = li_media_get_attachment_ids_for_context($context);
        $args['post__in'] = $ids ?: [0];
    }

    if ($folder && isset(li_media_get_folder_filter_options()[$folder])) {
        $ids = li_media_get_attachment_ids_for_folder($folder);

        if (!empty($args['post__in'])) {
            $ids = array_values(array_intersect(array_map('absint', (array) $args['post__in']), $ids));
        }

        $args['post__in'] = $ids ?: [0];
    }

    return $args;
}

function li_media_apply_folder_query_filter(WP_Query $query): void
{
    $folder = isset($_GET['li_media_folder']) ? sanitize_text_field(wp_unslash((string) $_GET['li_media_folder'])) : '';

    if (!isset(li_media_get_folder_filter_options()[$folder])) {
        return;
    }

    $ids = li_media_get_attachment_ids_for_folder($folder);
    $existing_ids = $query->get('post__in');

    if (is_array($existing_ids) && $existing_ids) {
        $ids = array_values(array_intersect(array_map('absint', $existing_ids), $ids));
    }

    $query->set('post__in', $ids ?: [0]);
}

function li_media_add_attachment_context_fields(array $form_fields, WP_Post $post): array
{
    $contexts = li_media_get_attachment_contexts((int) $post->ID);
    $storage = li_media_get_attachment_storage_label((int) $post->ID);

    $form_fields['li_media_context'] = [
        'label' => __('Lloyds Use', 'lloyds-industrial'),
        'input' => 'html',
        'html'  => '<div class="li-media-context-field">' . li_media_get_attachment_context_markup((int) $post->ID) . '</div>',
    ];

    $form_fields['li_media_storage'] = [
        'label' => __('Storage', 'lloyds-industrial'),
        'input' => 'html',
        'html'  => '<code>' . esc_html($storage) . '</code>' . (in_array('sds', array_column($contexts, 'type'), true)
            ? '<p class="description">' . esc_html__('SDS downloads are served only through the protected stream after access checks.', 'lloyds-industrial') . '</p>'
            : ''),
    ];

    return $form_fields;
}

function li_media_get_context_filter_options(): array
{
    return [
        'product'   => __('WooCommerce product images', 'lloyds-industrial'),
        'flipbook'  => __('PDF flipbooks', 'lloyds-industrial'),
        'sds'       => __('Protected SDS', 'lloyds-industrial'),
        'document'  => __('Documents', 'lloyds-industrial'),
        'image'     => __('General images', 'lloyds-industrial'),
        'pdf'       => __('General PDFs', 'lloyds-industrial'),
        'unassigned'=> __('Unassigned', 'lloyds-industrial'),
    ];
}

function li_media_get_folder_filter_options(): array
{
    $folders = [];

    foreach (li_media_get_library_folder_counts() as $folder => $count) {
        $folders[$folder] = sprintf(
            /* translators: 1: folder path, 2: attachment count */
            __('%1$s (%2$d)', 'lloyds-industrial'),
            $folder,
            $count
        );
    }

    return $folders;
}

function li_media_get_attachment_context_markup(int $attachment_id): string
{
    $contexts = li_media_get_attachment_contexts($attachment_id);

    if (!$contexts) {
        return '<span class="li-media-pill li-media-pill--muted">' . esc_html__('Unassigned', 'lloyds-industrial') . '</span>';
    }

    $markup = '';

    foreach ($contexts as $context) {
        $modifier = sanitize_html_class($context['type']);
        $markup .= '<span class="li-media-pill li-media-pill--' . esc_attr($modifier) . '">' . esc_html($context['label']) . '</span> ';
    }

    return trim($markup);
}

function li_media_get_attachment_contexts(int $attachment_id): array
{
    $contexts = [];

    if (function_exists('li_media_get_product_attachment_owner_ids')) {
        $product_ids = li_media_get_product_attachment_owner_ids($attachment_id);

        if ($product_ids) {
            $contexts[] = [
                'type'  => 'product',
                'label' => sprintf(_n('%d product', '%d products', count($product_ids), 'lloyds-industrial'), count($product_ids)),
            ];
        }
    }

    if (function_exists('li_media_get_flipbook_pdf_owner_ids')) {
        $flipbook_ids = li_media_get_flipbook_pdf_owner_ids($attachment_id);

        if ($flipbook_ids) {
            $contexts[] = [
                'type'  => 'flipbook',
                'label' => sprintf(_n('%d flipbook', '%d flipbooks', count($flipbook_ids), 'lloyds-industrial'), count($flipbook_ids)),
            ];
        }
    }

    foreach (li_media_get_document_owner_ids($attachment_id) as $document_id) {
        $is_sds = function_exists('li_is_sds_document') && li_is_sds_document($document_id);
        $contexts[] = [
            'type'  => $is_sds ? 'sds' : 'document',
            'label' => $is_sds ? __('Protected SDS', 'lloyds-industrial') : __('Document', 'lloyds-industrial'),
        ];
    }

    if (!$contexts) {
        $mime = (string) get_post_mime_type($attachment_id);

        if (str_starts_with($mime, 'image/')) {
            $contexts[] = ['type' => 'image', 'label' => __('General image', 'lloyds-industrial')];
        } elseif ($mime === 'application/pdf') {
            $contexts[] = ['type' => 'pdf', 'label' => __('General PDF', 'lloyds-industrial')];
        }
    }

    return $contexts;
}

function li_media_get_document_owner_ids(int $attachment_id): array
{
    $query = new WP_Query([
        'post_type'      => 'li_document',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'   => '_li_document_file_id',
                'value' => $attachment_id,
            ],
        ],
    ]);

    return array_map('absint', $query->posts);
}

function li_media_get_attachment_storage_label(int $attachment_id): string
{
    $file = get_attached_file($attachment_id);

    if (!is_string($file) || $file === '') {
        return __('No file path', 'lloyds-industrial');
    }

    if (function_exists('li_document_path_is_in_protected_storage') && li_document_path_is_in_protected_storage($file)) {
        return __('Protected storage', 'lloyds-industrial');
    }

    $uploads = wp_upload_dir(null, false);
    $base_dir = isset($uploads['basedir']) ? wp_normalize_path((string) $uploads['basedir']) : '';
    $normalized = wp_normalize_path($file);

    if ($base_dir && str_starts_with($normalized, trailingslashit($base_dir))) {
        return ltrim(substr($normalized, strlen(trailingslashit($base_dir))), '/');
    }

    return __('Custom path', 'lloyds-industrial');
}

function li_media_get_attachment_storage_folder(int $attachment_id): string
{
    $file = get_attached_file($attachment_id);

    if (!is_string($file) || $file === '') {
        return '';
    }

    $uploads = wp_upload_dir(null, false);
    $base_dir = isset($uploads['basedir']) ? wp_normalize_path((string) $uploads['basedir']) : '';
    $normalized = wp_normalize_path($file);

    if (function_exists('li_document_path_is_in_protected_storage') && li_document_path_is_in_protected_storage($file)) {
        $protected = li_get_protected_documents_dir();
        $protected_base = isset($protected['path']) ? wp_normalize_path((string) $protected['path']) : '';

        if ($protected_base && str_starts_with($normalized, trailingslashit($protected_base))) {
            return 'li-protected-documents/' . trim(dirname(ltrim(substr($normalized, strlen(trailingslashit($protected_base))), '/')), '.');
        }

        return 'li-protected-documents';
    }

    if ($base_dir && str_starts_with($normalized, trailingslashit($base_dir))) {
        $relative = ltrim(substr($normalized, strlen(trailingslashit($base_dir))), '/');

        return trim(dirname($relative), '.');
    }

    return '';
}

function li_media_get_library_folder_counts(): array
{
    $attachment_ids = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);
    $folders = [];

    foreach ($attachment_ids as $attachment_id) {
        $folder = li_media_get_attachment_storage_folder((int) $attachment_id);

        if ($folder === '') {
            continue;
        }

        if (
            !str_starts_with($folder, 'woocommerce/products/')
            && !str_starts_with($folder, 'lloyds-pdf-flipbook/')
            && !str_starts_with($folder, 'li-protected-documents/')
        ) {
            continue;
        }

        $folders[$folder] = ($folders[$folder] ?? 0) + 1;
    }

    ksort($folders, SORT_NATURAL | SORT_FLAG_CASE);

    return $folders;
}

function li_media_get_attachment_ids_for_folder(string $folder): array
{
    $attachment_ids = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);
    $matched = [];

    foreach ($attachment_ids as $attachment_id) {
        if (li_media_get_attachment_storage_folder((int) $attachment_id) === $folder) {
            $matched[] = (int) $attachment_id;
        }
    }

    return $matched;
}

function li_media_get_attachment_ids_for_context(string $context): array
{
    if ($context === 'product') {
        return li_media_get_product_attachment_ids();
    }

    if ($context === 'flipbook') {
        return li_media_get_meta_attachment_ids('lloyds_flipbook', '_lloyds_flipbook_pdf_id');
    }

    if ($context === 'sds' || $context === 'document') {
        return li_media_get_document_attachment_ids($context === 'sds');
    }

    $mime = $context === 'image' ? 'image' : ($context === 'pdf' ? 'application/pdf' : '');

    if ($mime) {
        return get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'post_mime_type' => $mime,
        ]);
    }

    if ($context === 'unassigned') {
        $assigned = array_unique(array_merge(
            li_media_get_product_attachment_ids(),
            li_media_get_meta_attachment_ids('lloyds_flipbook', '_lloyds_flipbook_pdf_id'),
            li_media_get_document_attachment_ids(true),
            li_media_get_document_attachment_ids(false)
        ));
        $all = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        return array_values(array_diff(array_map('absint', $all), $assigned));
    }

    return [];
}

function li_media_get_product_attachment_ids(): array
{
    global $wpdb;

    $ids = array_map('absint', $wpdb->get_col(
        "SELECT pm.meta_value
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE p.post_type = 'product'
            AND pm.meta_key = '_thumbnail_id'
            AND pm.meta_value REGEXP '^[0-9]+$'"
    ) ?: []);
    $galleries = $wpdb->get_col(
        "SELECT pm.meta_value
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE p.post_type = 'product'
            AND pm.meta_key = '_product_image_gallery'
            AND pm.meta_value != ''"
    ) ?: [];

    foreach ($galleries as $gallery) {
        $ids = array_merge($ids, array_map('absint', explode(',', (string) $gallery)));
    }

    return array_values(array_unique(array_filter($ids)));
}

function li_media_get_meta_attachment_ids(string $post_type, string $meta_key): array
{
    $query = new WP_Query([
        'post_type'      => $post_type,
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => $meta_key,
                'value'   => 0,
                'compare' => '>',
                'type'    => 'NUMERIC',
            ],
        ],
    ]);
    $ids = [];

    foreach ($query->posts as $post_id) {
        $ids[] = (int) get_post_meta((int) $post_id, $meta_key, true);
    }

    return array_values(array_unique(array_filter($ids)));
}

function li_media_get_document_attachment_ids(bool $sds_only): array
{
    $query = new WP_Query([
        'post_type'      => 'li_document',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);
    $ids = [];

    foreach ($query->posts as $document_id) {
        $document_id = (int) $document_id;
        $is_sds = function_exists('li_is_sds_document') && li_is_sds_document($document_id);

        if ($sds_only !== $is_sds) {
            continue;
        }

        $ids[] = (int) get_post_meta($document_id, '_li_document_file_id', true);
    }

    return array_values(array_unique(array_filter($ids)));
}
