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
                <?php esc_html_e('Access public product resources and approved customer documentation.', 'lloyds-industrial'); ?>
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
                    $access_level = $access_level ?: 'customer';
                    $download_url = li_get_document_download_url($document->ID);
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