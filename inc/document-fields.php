<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', function (): void {
    add_meta_box(
        'li_document_settings',
        __('Document Settings', 'lloyds-industrial'),
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

    if (!$access_level || !in_array($access_level, ['public', 'internal'], true)) {
        $access_level = 'public';
    }

    ?>
    <p>
        <label for="li_document_file_id">
            <strong><?php esc_html_e('Attachment ID', 'lloyds-industrial'); ?></strong>
        </label>
        <input
            type="number"
            id="li_document_file_id"
            name="li_document_file_id"
            value="<?php echo esc_attr((string) $file_id); ?>"
            class="widefat"
        >
    </p>

    <p>
        <label for="li_related_product_id">
            <strong><?php esc_html_e('Related Product ID', 'lloyds-industrial'); ?></strong>
        </label>
        <input
            type="number"
            id="li_related_product_id"
            name="li_related_product_id"
            value="<?php echo esc_attr((string) $related_product_id); ?>"
            class="widefat"
        >
    </p>

    <p>
        <label for="li_access_level">
            <strong><?php esc_html_e('Access Level', 'lloyds-industrial'); ?></strong>
        </label>
        <select id="li_access_level" name="li_access_level" class="widefat">
            <option value="public" <?php selected($access_level, 'public'); ?>>
                <?php esc_html_e('Public', 'lloyds-industrial'); ?>
            </option>
            <option value="internal" <?php selected($access_level, 'internal'); ?>>
                <?php esc_html_e('Internal', 'lloyds-industrial'); ?>
            </option>
        </select>
    </p>
    <?php
}

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

    update_post_meta(
        $post_id,
        '_li_document_file_id',
        isset($_POST['li_document_file_id']) ? absint($_POST['li_document_file_id']) : 0
    );

    update_post_meta(
        $post_id,
        '_li_related_product_id',
        isset($_POST['li_related_product_id']) ? absint($_POST['li_related_product_id']) : 0
    );

    $access_level = isset($_POST['li_access_level'])
        ? sanitize_key(wp_unslash($_POST['li_access_level']))
        : 'public';

    if (!in_array($access_level, ['public', 'internal'], true)) {
        $access_level = 'public';
    }

    update_post_meta($post_id, '_li_access_level', $access_level);
});
