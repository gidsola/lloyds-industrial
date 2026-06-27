<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('save_post_product', 'li_media_organize_product_images_on_save', 100, 3);
add_action('save_post_lloyds_flipbook', 'li_media_organize_flipbook_pdf_on_save', 100, 3);
add_action('added_post_meta', 'li_media_organize_product_images_on_meta_change', 100, 4);
add_action('updated_post_meta', 'li_media_organize_product_images_on_meta_change', 100, 4);
add_action('added_post_meta', 'li_media_organize_flipbook_pdf_on_meta_change', 100, 4);
add_action('updated_post_meta', 'li_media_organize_flipbook_pdf_on_meta_change', 100, 4);
add_action('set_post_thumbnail', 'li_media_organize_product_thumbnail', 100, 2);
add_action('admin_menu', 'li_media_register_product_media_tools_page');

function li_media_register_product_media_tools_page(): void
{
    add_management_page(
        __('Lloyds Media Organizer', 'lloyds-industrial'),
        __('Lloyds Media Organizer', 'lloyds-industrial'),
        'manage_woocommerce',
        'lloyds-product-media-organizer',
        'li_media_render_product_media_tools_page'
    );
}

function li_media_render_product_media_tools_page(): void
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('You do not have permission to organize product media.', 'lloyds-industrial'));
    }

    $product_result = null;
    $flipbook_result = null;

    if (isset($_POST['li_product_media_organize'])) {
        check_admin_referer('li_product_media_organize');
        $product_result = li_media_organize_all_product_images();
    } elseif (isset($_POST['li_flipbook_media_organize'])) {
        check_admin_referer('li_flipbook_media_organize');
        $flipbook_result = li_media_organize_all_flipbook_pdfs();
    }
    ?>
    <div class="wrap li-settings-page">
        <div class="li-settings-hero">
            <div>
                <p class="li-settings-kicker"><?php esc_html_e('Media Library', 'lloyds-industrial'); ?></p>
                <h1><?php esc_html_e('Lloyds Media Organizer', 'lloyds-industrial'); ?></h1>
                <p><?php esc_html_e('Move controlled Lloyds media into purpose-built upload folders without changing regular site media or protected documents.', 'lloyds-industrial'); ?></p>
            </div>
            <div class="li-settings-summary">
                <div>
                    <span><?php esc_html_e('Products', 'lloyds-industrial'); ?></span>
                    <strong><?php esc_html_e('woocommerce/products/category/item', 'lloyds-industrial'); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Flipbooks', 'lloyds-industrial'); ?></span>
                    <strong><?php esc_html_e('lloyds-pdf-flipbook/flipbooks/item', 'lloyds-industrial'); ?></strong>
                </div>
            </div>
        </div>

        <?php if (is_array($product_result)): ?>
            <div class="notice notice-success">
                <p>
                    <?php
                    printf(
                        esc_html__('Checked %1$d products and moved %2$d image attachment files. %3$d images were already organized or skipped.', 'lloyds-industrial'),
                        (int) $product_result['products'],
                        (int) $product_result['moved'],
                        (int) $product_result['skipped']
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if (is_array($flipbook_result)): ?>
            <div class="notice notice-success">
                <p>
                    <?php
                    printf(
                        esc_html__('Checked %1$d flipbooks and moved %2$d PDF attachment files. %3$d PDFs were already organized or skipped.', 'lloyds-industrial'),
                        (int) $flipbook_result['flipbooks'],
                        (int) $flipbook_result['moved'],
                        (int) $flipbook_result['skipped']
                    );
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <form method="post" class="li-settings-form">
            <?php wp_nonce_field('li_product_media_organize'); ?>
            <h2><?php esc_html_e('Organize Existing Product Images', 'lloyds-industrial'); ?></h2>
            <p><?php esc_html_e('New product images are organized automatically when product thumbnails or galleries are saved. Use this tool after large imports to clean up existing product media.', 'lloyds-industrial'); ?></p>
            <div class="form-table">
                <div class="li-product-media-tool-card">
                    <p>
                        <button type="submit" name="li_product_media_organize" value="1" class="button button-primary">
                            <?php esc_html_e('Organize Product Images Now', 'lloyds-industrial'); ?>
                        </button>
                    </p>
                </div>
            </div>
        </form>

        <form method="post" class="li-settings-form">
            <?php wp_nonce_field('li_flipbook_media_organize'); ?>
            <h2><?php esc_html_e('Organize Existing Flipbook PDFs', 'lloyds-industrial'); ?></h2>
            <p><?php esc_html_e('New flipbook PDFs are organized automatically when a PDF is assigned to a flipbook. Use this tool to clean up existing catalogue and document PDFs.', 'lloyds-industrial'); ?></p>
            <div class="form-table">
                <div class="li-product-media-tool-card">
                    <p>
                        <button type="submit" name="li_flipbook_media_organize" value="1" class="button button-primary">
                            <?php esc_html_e('Organize Flipbook PDFs Now', 'lloyds-industrial'); ?>
                        </button>
                    </p>
                </div>
            </div>
        </form>
    </div>
    <?php
}

function li_media_organize_product_images_on_save(int $post_id, WP_Post $post, bool $update): void
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    li_media_organize_product_images($post_id);
}

function li_media_organize_product_images_on_meta_change(int $meta_id, int $post_id, string $meta_key, mixed $meta_value): void
{
    if (!in_array($meta_key, ['_thumbnail_id', '_product_image_gallery'], true) || get_post_type($post_id) !== 'product') {
        return;
    }

    li_media_organize_product_images($post_id);
}

function li_media_organize_flipbook_pdf_on_save(int $post_id, WP_Post $post, bool $update): void
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    li_media_organize_flipbook_pdf($post_id);
}

function li_media_organize_flipbook_pdf_on_meta_change(int $meta_id, int $post_id, string $meta_key, mixed $meta_value): void
{
    if ($meta_key !== '_lloyds_flipbook_pdf_id' || get_post_type($post_id) !== 'lloyds_flipbook') {
        return;
    }

    li_media_organize_flipbook_pdf($post_id);
}

function li_media_organize_product_thumbnail(int $post_id, int $thumbnail_id): void
{
    if (get_post_type($post_id) !== 'product') {
        return;
    }

    li_media_organize_product_attachment($thumbnail_id, $post_id);
}

function li_media_organize_product_images(int $product_id): array
{
    static $running = [];

    if (isset($running[$product_id]) || get_post_type($product_id) !== 'product') {
        return ['moved' => 0, 'skipped' => 0];
    }

    $running[$product_id] = true;
    $moved = 0;
    $skipped = 0;

    foreach (li_media_get_product_image_ids($product_id) as $attachment_id) {
        $result = li_media_organize_product_attachment($attachment_id, $product_id);

        if ($result) {
            $moved++;
        } else {
            $skipped++;
        }
    }

    unset($running[$product_id]);

    return [
        'moved'   => $moved,
        'skipped' => $skipped,
    ];
}

function li_media_organize_all_product_images(): array
{
    $product_ids = get_posts([
        'post_type'      => 'product',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);
    $moved = 0;
    $skipped = 0;

    foreach ($product_ids as $product_id) {
        $result = li_media_organize_product_images((int) $product_id);
        $moved += (int) $result['moved'];
        $skipped += (int) $result['skipped'];
    }

    return [
        'products' => count($product_ids),
        'moved'    => $moved,
        'skipped'  => $skipped,
    ];
}

function li_media_organize_flipbook_pdf(int $flipbook_id): array
{
    static $running = [];

    if (isset($running[$flipbook_id]) || get_post_type($flipbook_id) !== 'lloyds_flipbook') {
        return ['moved' => 0, 'skipped' => 0];
    }

    $running[$flipbook_id] = true;
    $pdf_id = (int) get_post_meta($flipbook_id, '_lloyds_flipbook_pdf_id', true);
    $moved = 0;
    $skipped = 0;

    if ($pdf_id && li_media_organize_flipbook_pdf_attachment($pdf_id, $flipbook_id)) {
        $moved++;
    } else {
        $skipped++;
    }

    unset($running[$flipbook_id]);

    return [
        'moved'   => $moved,
        'skipped' => $skipped,
    ];
}

function li_media_organize_all_flipbook_pdfs(): array
{
    $flipbook_ids = get_posts([
        'post_type'      => 'lloyds_flipbook',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);
    $moved = 0;
    $skipped = 0;

    foreach ($flipbook_ids as $flipbook_id) {
        $result = li_media_organize_flipbook_pdf((int) $flipbook_id);
        $moved += (int) $result['moved'];
        $skipped += (int) $result['skipped'];
    }

    return [
        'flipbooks' => count($flipbook_ids),
        'moved'     => $moved,
        'skipped'   => $skipped,
    ];
}

function li_media_get_product_image_ids(int $product_id): array
{
    $image_ids = [];
    $thumbnail_id = (int) get_post_thumbnail_id($product_id);

    if ($thumbnail_id) {
        $image_ids[] = $thumbnail_id;
    }

    $gallery = (string) get_post_meta($product_id, '_product_image_gallery', true);

    if ($gallery !== '') {
        $image_ids = array_merge($image_ids, array_map('absint', explode(',', $gallery)));
    }

    return array_values(array_unique(array_filter($image_ids)));
}

function li_media_organize_product_attachment(int $attachment_id, int $product_id): bool
{
    if (!$attachment_id || get_post_type($attachment_id) !== 'attachment' || !wp_attachment_is_image($attachment_id)) {
        return false;
    }

    $attached_file = (string) get_post_meta($attachment_id, '_wp_attached_file', true);

    if ($attached_file === '') {
        return false;
    }

    $uploads = wp_upload_dir(null, false);

    if (!empty($uploads['error'])) {
        return false;
    }

    $owners = li_media_get_product_attachment_owner_ids($attachment_id);
    $target_dir = count($owners) > 1
        ? 'woocommerce/products/shared'
        : li_media_get_product_media_relative_dir($product_id);

    if (str_starts_with(wp_normalize_path($attached_file), trailingslashit($target_dir))) {
        return false;
    }

    return li_media_move_attachment_files($attachment_id, $attached_file, $target_dir, $uploads);
}

function li_media_organize_flipbook_pdf_attachment(int $attachment_id, int $flipbook_id): bool
{
    if (!$attachment_id || get_post_type($attachment_id) !== 'attachment' || get_post_mime_type($attachment_id) !== 'application/pdf') {
        return false;
    }

    $attached_file = (string) get_post_meta($attachment_id, '_wp_attached_file', true);

    if ($attached_file === '') {
        return false;
    }

    $uploads = wp_upload_dir(null, false);

    if (!empty($uploads['error'])) {
        return false;
    }

    $owners = li_media_get_flipbook_pdf_owner_ids($attachment_id);
    $target_dir = count($owners) > 1
        ? 'lloyds-pdf-flipbook/flipbooks/shared'
        : li_media_get_flipbook_media_relative_dir($flipbook_id);

    if (str_starts_with(wp_normalize_path($attached_file), trailingslashit($target_dir))) {
        return false;
    }

    return li_media_move_attachment_files($attachment_id, $attached_file, $target_dir, $uploads);
}

function li_media_get_product_attachment_owner_ids(int $attachment_id): array
{
    global $wpdb;

    $ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT pm.post_id
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
                AND (
                    (pm.meta_key = '_thumbnail_id' AND pm.meta_value = %s)
                    OR (pm.meta_key = '_product_image_gallery' AND FIND_IN_SET(%d, pm.meta_value))
                )",
            (string) $attachment_id,
            $attachment_id
        )
    );

    return array_map('absint', $ids ?: []);
}

function li_media_get_flipbook_pdf_owner_ids(int $attachment_id): array
{
    global $wpdb;

    $ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT pm.post_id
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.post_type = 'lloyds_flipbook'
                AND pm.meta_key = '_lloyds_flipbook_pdf_id'
                AND pm.meta_value = %s",
            (string) $attachment_id
        )
    );

    return array_map('absint', $ids ?: []);
}

function li_media_get_product_media_relative_dir(int $product_id): string
{
    $category_slug = 'uncategorized';
    $terms = get_the_terms($product_id, 'product_cat');

    if (is_array($terms) && $terms) {
        usort($terms, static fn (WP_Term $a, WP_Term $b): int => strcasecmp($a->name, $b->name));
        $category_slug = sanitize_title($terms[0]->slug ?: $terms[0]->name);
    }

    $sku = (string) get_post_meta($product_id, '_sku', true);
    $item_slug = $sku !== ''
        ? sanitize_title($sku)
        : sanitize_title(get_post_field('post_name', $product_id));

    if ($item_slug === '') {
        $item_slug = 'product-' . $product_id;
    }

    return 'woocommerce/products/' . $category_slug . '/' . $item_slug;
}

function li_media_get_flipbook_media_relative_dir(int $flipbook_id): string
{
    $item_slug = sanitize_title(get_post_field('post_name', $flipbook_id));

    if ($item_slug === '') {
        $item_slug = 'flipbook-' . $flipbook_id;
    }

    return 'lloyds-pdf-flipbook/flipbooks/' . $item_slug;
}

function li_media_move_attachment_files(int $attachment_id, string $attached_file, string $target_dir, array $uploads): bool
{
    $base_dir = wp_normalize_path((string) $uploads['basedir']);
    $base_url = (string) $uploads['baseurl'];
    $old_relative = wp_normalize_path($attached_file);
    $old_file = wp_normalize_path(trailingslashit($base_dir) . $old_relative);

    if (!is_readable($old_file)) {
        return false;
    }

    $target_abs_dir = wp_normalize_path(trailingslashit($base_dir) . trim($target_dir, '/'));

    if (!wp_mkdir_p($target_abs_dir)) {
        return false;
    }

    $metadata = wp_get_attachment_metadata($attachment_id);
    $metadata = is_array($metadata) ? $metadata : [];
    $old_dir = wp_normalize_path(dirname($old_file));
    $new_filename = wp_unique_filename($target_abs_dir, basename($old_file));
    $new_file = trailingslashit($target_abs_dir) . $new_filename;

    if (!li_media_move_file($old_file, $new_file)) {
        return false;
    }

    $new_relative = trailingslashit(trim($target_dir, '/')) . $new_filename;
    $metadata['file'] = $new_relative;

    if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
        foreach ($metadata['sizes'] as $size => $size_data) {
            if (empty($size_data['file'])) {
                continue;
            }

            $size_file = wp_normalize_path(trailingslashit($old_dir) . $size_data['file']);

            if (!is_readable($size_file)) {
                continue;
            }

            $size_filename = wp_unique_filename($target_abs_dir, basename($size_file));
            $size_target = trailingslashit($target_abs_dir) . $size_filename;

            if (li_media_move_file($size_file, $size_target)) {
                $metadata['sizes'][$size]['file'] = $size_filename;
            }
        }
    }

    if (!empty($metadata['original_image'])) {
        $original_file = wp_normalize_path(trailingslashit($old_dir) . $metadata['original_image']);

        if (is_readable($original_file)) {
            $original_filename = wp_unique_filename($target_abs_dir, basename($original_file));
            $original_target = trailingslashit($target_abs_dir) . $original_filename;

            if (li_media_move_file($original_file, $original_target)) {
                $metadata['original_image'] = $original_filename;
            }
        }
    }

    update_post_meta($attachment_id, '_wp_attached_file', $new_relative);
    wp_update_attachment_metadata($attachment_id, $metadata);
    wp_update_post([
        'ID'   => $attachment_id,
        'guid' => trailingslashit($base_url) . str_replace('\\', '/', $new_relative),
    ]);

    return true;
}

function li_media_move_file(string $source, string $target): bool
{
    if ($source === $target) {
        return true;
    }

    if (@rename($source, $target)) {
        return true;
    }

    if (@copy($source, $target)) {
        @unlink($source);

        return true;
    }

    return false;
}
