<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('woocommerce_product_options_general_product_data', function (): void {
    global $post;

    $product_id = $post instanceof WP_Post ? (int) $post->ID : 0;
    $sds_file_id = $product_id ? li_get_product_sds_file_id($product_id) : 0;
    $sds_file_label = $sds_file_id ? get_the_title($sds_file_id) : __('No SDS file selected', 'lloyds-industrial');

    echo '<div class="options_group li-product-admin-panel">';

    woocommerce_wp_textarea_input([
        'id'          => '_li_public_summary',
        'label'       => __('Public Summary', 'lloyds-industrial'),
        'description' => __('Short public-facing product summary used for search, APIs and product cards.', 'lloyds-industrial'),
        'desc_tip'    => true,
    ]);

    woocommerce_wp_text_input([
        'id'          => '_li_certifications',
        'label'       => __('Certifications', 'lloyds-industrial'),
        'description' => __('Comma-separated certification or compliance highlights.', 'lloyds-industrial'),
        'desc_tip'    => true,
    ]);

    ?>
    <p class="form-field li_sds_file_field">
        <label for="_li_sds_file_id"><?php esc_html_e('SDS File', 'lloyds-industrial'); ?></label>
        <span data-li-media-picker>
            <input
                type="hidden"
                id="_li_sds_file_id"
                name="_li_sds_file_id"
                value="<?php echo esc_attr((string) $sds_file_id); ?>"
                data-li-media-id
            >
            <span data-li-media-label><?php echo esc_html($sds_file_label); ?></span>
            <button
                type="button"
                class="button"
                data-li-media-select
                data-li-media-title="<?php esc_attr_e('Select SDS File', 'lloyds-industrial'); ?>"
                data-li-media-button="<?php esc_attr_e('Use This SDS', 'lloyds-industrial'); ?>"
                data-li-media-type="application/pdf"
            >
                <?php esc_html_e('Select / Upload SDS', 'lloyds-industrial'); ?>
            </button>
            <button type="button" class="button" data-li-media-remove <?php echo $sds_file_id ? '' : 'hidden'; ?>>
                <?php esc_html_e('Remove', 'lloyds-industrial'); ?>
            </button>
            <span class="description">
                <?php esc_html_e('Saving the product creates or updates the related SDS document and protected download copy.', 'lloyds-industrial'); ?>
            </span>
        </span>
    </p>
    <?php

    echo '</div>';
});

add_action('woocommerce_process_product_meta', function (int $post_id): void {
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['_li_public_summary'])) {
        update_post_meta(
            $post_id,
            '_li_public_summary',
            sanitize_textarea_field(wp_unslash($_POST['_li_public_summary']))
        );
    }

    if (isset($_POST['_li_certifications'])) {
        update_post_meta(
            $post_id,
            '_li_certifications',
            sanitize_text_field(wp_unslash($_POST['_li_certifications']))
        );
    }

    if (isset($_POST['_li_sds_file_id'])) {
        li_save_product_sds_document($post_id, absint($_POST['_li_sds_file_id']));
    }
});
