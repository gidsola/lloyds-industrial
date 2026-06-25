<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function li_get_product_documents(int $product_id): array
{
    $query = new WP_Query([
        'post_type'      => 'li_document',
        'post_status'    => 'publish',
        'posts_per_page' => 100,
        'meta_query'     => [
            [
                'key'     => '_li_related_product_id',
                'value'   => $product_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
        ],
    ]);

    return $query->posts;
}

function li_ensure_sds_document_type_term(): int
{
    $term = get_term_by('slug', 'sds', 'li_document_type');

    if ($term instanceof WP_Term) {
        return (int) $term->term_id;
    }

    $created = wp_insert_term('SDS', 'li_document_type', [
        'slug' => 'sds',
    ]);

    if (is_wp_error($created)) {
        return 0;
    }

    return (int) ($created['term_id'] ?? 0);
}

function li_get_product_sds_document_id(int $product_id): int
{
    $query = new WP_Query([
        'post_type'      => 'li_document',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'tax_query'      => [
            [
                'taxonomy' => 'li_document_type',
                'field'    => 'slug',
                'terms'    => ['sds'],
            ],
        ],
        'meta_query'     => [
            [
                'key'     => '_li_related_product_id',
                'value'   => $product_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
        ],
    ]);

    return !empty($query->posts[0]) ? (int) $query->posts[0] : 0;
}

function li_get_product_sds_file_id(int $product_id): int
{
    $document_id = li_get_product_sds_document_id($product_id);

    return $document_id ? li_get_document_file_id($document_id) : 0;
}

function li_save_product_sds_document(int $product_id, int $file_id): int
{
    $document_id = li_get_product_sds_document_id($product_id);
    $product_title = get_the_title($product_id);

    if (!$document_id && !$file_id) {
        return 0;
    }

    if (!$document_id) {
        $document_id = wp_insert_post([
            'post_title'  => sprintf(
                /* translators: %s: product title */
                __('%s SDS', 'lloyds-industrial'),
                $product_title ?: __('Product', 'lloyds-industrial')
            ),
            'post_type'   => 'li_document',
            'post_status' => 'publish',
        ], true);

        if (is_wp_error($document_id) || !$document_id) {
            return 0;
        }

        $document_id = (int) $document_id;
    }

    $term_id = li_ensure_sds_document_type_term();

    if ($term_id) {
        wp_set_post_terms($document_id, [$term_id], 'li_document_type');
    }

    update_post_meta($document_id, '_li_related_product_id', $product_id);
    update_post_meta($document_id, '_li_access_level', 'public');

    $existing_file_id = li_get_document_file_id($document_id);

    if ($existing_file_id !== $file_id) {
        li_delete_protected_document_copy($document_id);
    }

    update_post_meta($document_id, '_li_document_file_id', $file_id);

    if ($file_id) {
        li_protect_document_file($document_id);
    }

    return $document_id;
}

function li_render_product_documents_block(): string
{
    if (!is_singular('product')) {
        return '';
    }

    $product_id = get_the_ID();
    $documents = li_get_product_documents($product_id);

    ob_start();
    ?>
    <section class="li-product-documents">
        <div class="li-product-documents__header">
            <p class="li-eyebrow"><?php esc_html_e('Documentation', 'lloyds-industrial'); ?></p>
            <h2><?php esc_html_e('Product Documents', 'lloyds-industrial'); ?></h2>
            <p>
                <?php esc_html_e('Access public technical resources. SDS downloads are available to customers who purchased this product.', 'lloyds-industrial'); ?>
            </p>
        </div>

        <?php if (!$documents): ?>
            <div class="li-doc-empty">
                <p><?php esc_html_e('No documents are currently attached to this product.', 'lloyds-industrial'); ?></p>
            </div>
        <?php else: ?>
            <div class="li-doc-list">
                <?php foreach ($documents as $document): ?>
                    <?php
                    $access_level = (string) get_post_meta($document->ID, '_li_access_level', true);
                    $access_level = $access_level ?: 'public';
                    $download_url = li_get_document_download_url($document->ID);
                    $is_sds = li_is_sds_document($document->ID);
                    $types = wp_get_post_terms($document->ID, 'li_document_type', ['fields' => 'names']);
                    ?>
                    <article class="li-doc-card">
                        <div>
                            <?php if (!empty($types)): ?>
                                <p class="li-doc-type"><?php echo esc_html(implode(', ', $types)); ?></p>
                            <?php endif; ?>

                            <h3><?php echo esc_html(get_the_title($document)); ?></h3>

                            <?php if (has_excerpt($document)): ?>
                                <p><?php echo esc_html(get_the_excerpt($document)); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="li-doc-card__action">
                            <?php if ($download_url): ?>
                                <a class="li-button-primary" href="<?php echo esc_url(li_get_secure_document_url($document->ID)); ?>">
                                    <?php esc_html_e('Download', 'lloyds-industrial'); ?>
                                </a>
                            <?php elseif ($is_sds && is_user_logged_in()): ?>
                                <span class="li-button-primary" aria-disabled="true">
                                    <?php esc_html_e('Purchase Required', 'lloyds-industrial'); ?>
                                </span>
                            <?php else: ?>
                                <a class="li-button-primary" href="<?php echo esc_url(wp_login_url(get_permalink($product_id))); ?>">
                                    <?php esc_html_e('Login Required', 'lloyds-industrial'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php

    return (string) ob_get_clean();
}

add_shortcode('li_product_documents', 'li_render_product_documents_block');
