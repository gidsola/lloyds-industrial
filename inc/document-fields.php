<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', function (): void {
    add_meta_box(
        'li_document_settings',
        __('Document Settings', 'b2b-industrial'),
        'li_render_document_settings_metabox',
        'li_document',
        'normal',
        'high'
    );
});

function li_render_document_settings_metabox(WP_Post $post): void
{
    wp_nonce_field('li_save_document_settings', 'li_document_settings_nonce');

    $file_id = (int) get_post_meta($post->ID, '_li_document_file_id', true);
    $related_product_id = (int) get_post_meta($post->ID, '_li_related_product_id', true);
    $access_level = (string) get_post_meta($post->ID, '_li_access_level', true);
    $file_label = $file_id ? get_the_title($file_id) : __('No file selected', 'b2b-industrial');
    $is_sds = li_is_sds_document($post->ID);
    $related_product_title = $related_product_id ? get_the_title($related_product_id) : '';

    if (!$access_level || !in_array($access_level, ['public', 'internal'], true)) {
        $access_level = 'public';
    }

    ?>
    <div class="li-editor-panel li-document-editor-panel">
        <div class="li-editor-panel__intro">
            <span class="dashicons dashicons-media-document"></span>
            <div>
                <h2><?php esc_html_e('Document Access Record', 'b2b-industrial'); ?></h2>
                <p><?php esc_html_e('Attach the source file, connect it to a product when needed, and control whether customers can access it publicly or through purchase-gated SDS rules.', 'b2b-industrial'); ?></p>
            </div>
        </div>

    <p class="li-editor-panel__field">
        <label for="li_document_file_id">
            <strong><?php esc_html_e('Document File', 'b2b-industrial'); ?></strong>
        </label>
        <span data-li-media-picker>
            <input
                type="hidden"
                id="li_document_file_id"
                name="li_document_file_id"
                value="<?php echo esc_attr((string) $file_id); ?>"
                data-li-media-id
            >
            <span data-li-media-label><?php echo esc_html($file_label); ?></span>
            <br>
            <button
                type="button"
                class="button"
                data-li-media-select
                data-li-media-title="<?php esc_attr_e('Select Document File', 'b2b-industrial'); ?>"
                data-li-media-button="<?php esc_attr_e('Use This File', 'b2b-industrial'); ?>"
            >
                <?php esc_html_e('Select / Upload File', 'b2b-industrial'); ?>
            </button>
            <button type="button" class="button" data-li-media-remove <?php echo $file_id ? '' : 'hidden'; ?>>
                <?php esc_html_e('Remove', 'b2b-industrial'); ?>
            </button>
        </span>
        <span class="description">
            <?php esc_html_e('SDS files are copied into protected storage when this document is saved.', 'b2b-industrial'); ?>
        </span>
    </p>

    <p class="li-editor-panel__field">
        <label for="li_related_product_id">
            <strong><?php esc_html_e('Related Product ID', 'b2b-industrial'); ?></strong>
        </label>
        <span data-li-product-finder>
            <input
                type="search"
                class="widefat"
                placeholder="<?php esc_attr_e('Search products by name...', 'b2b-industrial'); ?>"
                value="<?php echo esc_attr($related_product_title); ?>"
                data-li-product-search
            >
            <span class="description">
                <?php esc_html_e('Search and select a product, or enter the product ID manually below.', 'b2b-industrial'); ?>
            </span>
            <div class="li-product-search-results" data-li-product-results hidden></div>
        </span>
        <input
            type="number"
            id="li_related_product_id"
            name="li_related_product_id"
            value="<?php echo esc_attr((string) $related_product_id); ?>"
            class="widefat"
            data-li-product-id
        >
    </p>

    <p class="li-editor-panel__field">
        <label for="li_access_level">
            <strong><?php esc_html_e('Access Level', 'b2b-industrial'); ?></strong>
        </label>
        <?php if ($is_sds): ?>
            <input type="hidden" name="li_access_level" value="sds_purchase">
            <strong><?php esc_html_e('SDS - Purchase Required', 'b2b-industrial'); ?></strong>
            <span class="description">
                <?php esc_html_e('SDS files are never public. Customers must be logged in and have purchased the related product.', 'b2b-industrial'); ?>
            </span>
        <?php else: ?>
            <select id="li_access_level" name="li_access_level" class="widefat">
                <option value="public" <?php selected($access_level, 'public'); ?>>
                    <?php esc_html_e('Public', 'b2b-industrial'); ?>
                </option>
                <option value="internal" <?php selected($access_level, 'internal'); ?>>
                    <?php esc_html_e('Internal', 'b2b-industrial'); ?>
                </option>
            </select>
            <span class="description">
                <?php esc_html_e('If this document is assigned the SDS type, this setting is overridden by purchase history.', 'b2b-industrial'); ?>
            </span>
        <?php endif; ?>
    </p>
    </div>
    <?php
}

add_action('wp_ajax_li_search_products', function (): void {
    check_ajax_referer('li_admin_search_products');

    if (!current_user_can('edit_products') && !current_user_can('edit_posts') && !current_user_can('manage_options')) {
        wp_send_json_error([
            'message' => __('You do not have permission to search products.', 'b2b-industrial'),
        ], 403);
    }

    $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';

    if (strlen($search) < 2) {
        wp_send_json_success([]);
    }

    $query = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => ['publish', 'draft', 'pending', 'private'],
        'posts_per_page' => 10,
        's'              => $search,
        'fields'         => 'ids',
    ]);

    $products = [];

    foreach ($query->posts as $product_id) {
        $products[] = [
            'id'    => (int) $product_id,
            'title' => get_the_title((int) $product_id),
        ];
    }

    wp_send_json_success($products);
});

add_action('save_post_li_document', function (int $post_id): void {
    if (!isset($_POST['li_document_settings_nonce'])) {
        return;
    }

    if (!wp_verify_nonce(
        sanitize_text_field(wp_unslash($_POST['li_document_settings_nonce'])),
        'li_save_document_settings'
    )) {
        return;
    }

    if (!current_user_can('manage_li_documents') && !current_user_can('manage_options')) {
        return;
    }

    $old_file_id = li_get_document_file_id($post_id);
    $new_file_id = isset($_POST['li_document_file_id']) ? absint($_POST['li_document_file_id']) : 0;

    if ($old_file_id !== $new_file_id) {
        li_delete_protected_document_copy($post_id);
    }

    update_post_meta($post_id, '_li_document_file_id', $new_file_id);

    update_post_meta(
        $post_id,
        '_li_related_product_id',
        isset($_POST['li_related_product_id']) ? absint($_POST['li_related_product_id']) : 0
    );

    $access_level = isset($_POST['li_access_level'])
        ? sanitize_key(wp_unslash($_POST['li_access_level']))
        : 'public';

    if (!in_array($access_level, ['public', 'internal', 'sds_purchase'], true)) {
        $access_level = 'public';
    }

    if (li_is_sds_document($post_id)) {
        $access_level = 'sds_purchase';
    } elseif ($access_level === 'sds_purchase') {
        $access_level = 'public';
    }

    update_post_meta($post_id, '_li_access_level', $access_level);

    if (li_is_sds_document($post_id) && $new_file_id) {
        li_protect_document_file($post_id);
    }
});
