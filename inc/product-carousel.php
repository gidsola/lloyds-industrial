<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function li_product_carousel_defaults(): array
{
    return [
        'enabled'      => true,
        'eyebrow'      => __('Featured Products', 'lloyds-industrial'),
        'title'        => __('Industrial Product Highlights', 'lloyds-industrial'),
        'description'  => __('Browse selected Lloyds products, then open a product record for applications, documentation, quote support, and eligible SDS access.', 'lloyds-industrial'),
        'source'       => 'featured',
        'product_ids'  => '',
        'category'     => '',
        'limit'        => 8,
        'autoplay'     => true,
        'interval'     => 5200,
        'show_price'   => true,
        'show_summary' => true,
        'show_meta'    => true,
        'show_cta'     => true,
    ];
}

function li_get_product_carousel_settings(): array
{
    $settings = get_option('li_product_carousel_settings', []);

    return wp_parse_args(is_array($settings) ? $settings : [], li_product_carousel_defaults());
}

function li_sanitize_product_carousel_settings(array $input): array
{
    $defaults = li_product_carousel_defaults();

    $source = sanitize_key((string) ($input['source'] ?? $defaults['source']));
    $source = in_array($source, ['featured', 'latest', 'manual', 'category'], true) ? $source : $defaults['source'];

    $limit = absint($input['limit'] ?? $defaults['limit']);
    $interval = absint($input['interval'] ?? $defaults['interval']);

    return [
        'enabled'      => !empty($input['enabled']),
        'eyebrow'      => sanitize_text_field((string) ($input['eyebrow'] ?? $defaults['eyebrow'])),
        'title'        => sanitize_text_field((string) ($input['title'] ?? $defaults['title'])),
        'description'  => sanitize_textarea_field((string) ($input['description'] ?? $defaults['description'])),
        'source'       => $source,
        'product_ids'  => implode(',', array_filter(array_map('absint', preg_split('/[\s,]+/', (string) ($input['product_ids'] ?? ''))))),
        'category'     => sanitize_title((string) ($input['category'] ?? '')),
        'limit'        => min(24, max(1, $limit ?: (int) $defaults['limit'])),
        'autoplay'     => !empty($input['autoplay']),
        'interval'     => min(12000, max(2500, $interval ?: (int) $defaults['interval'])),
        'show_price'   => !empty($input['show_price']),
        'show_summary' => !empty($input['show_summary']),
        'show_meta'    => !empty($input['show_meta']),
        'show_cta'     => !empty($input['show_cta']),
    ];
}

add_action('admin_menu', function (): void {
    add_menu_page(
        __('Lloyds Product Carousel', 'lloyds-industrial'),
        __('Product Carousel', 'lloyds-industrial'),
        'manage_options',
        'lloyds-product-carousel',
        'li_render_product_carousel_admin_page',
        'dashicons-images-alt2',
        62
    );
});

add_action('admin_init', function (): void {
    register_setting('li_product_carousel_settings', 'li_product_carousel_settings', [
        'type'              => 'array',
        'sanitize_callback' => 'li_sanitize_product_carousel_settings',
        'default'           => li_product_carousel_defaults(),
    ]);
});

function li_render_product_carousel_admin_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $settings = li_get_product_carousel_settings();
    $categories = taxonomy_exists('product_cat') ? get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    ]) : [];
    ?>
    <div class="wrap li-settings-page">
        <div class="li-settings-hero">
            <div>
                <p class="li-settings-kicker"><?php esc_html_e('Product Discovery', 'lloyds-industrial'); ?></p>
                <h1><?php esc_html_e('Lloyds Product Carousel', 'lloyds-industrial'); ?></h1>
                <p><?php esc_html_e('Configure a reusable product carousel for home pages, landing pages, product libraries, or partner sites.', 'lloyds-industrial'); ?></p>
            </div>
            <div class="li-settings-hero__meta">
                <span><?php echo esc_html($settings['enabled'] ? __('Enabled', 'lloyds-industrial') : __('Disabled', 'lloyds-industrial')); ?></span>
                <span><?php echo esc_html(sprintf(__('Source: %s', 'lloyds-industrial'), ucfirst((string) $settings['source']))); ?></span>
                <span><code>[li_product_carousel]</code></span>
            </div>
        </div>

        <form method="post" action="options.php" class="li-settings-grid">
            <?php settings_fields('li_product_carousel_settings'); ?>

            <section class="li-settings-card">
                <h2><?php esc_html_e('Content', 'lloyds-industrial'); ?></h2>
                <label>
                    <input type="checkbox" name="li_product_carousel_settings[enabled]" value="1" <?php checked(!empty($settings['enabled'])); ?>>
                    <?php esc_html_e('Enable carousel output.', 'lloyds-industrial'); ?>
                </label>

                <label>
                    <span><?php esc_html_e('Eyebrow', 'lloyds-industrial'); ?></span>
                    <input type="text" name="li_product_carousel_settings[eyebrow]" value="<?php echo esc_attr((string) $settings['eyebrow']); ?>">
                </label>

                <label>
                    <span><?php esc_html_e('Title', 'lloyds-industrial'); ?></span>
                    <input type="text" name="li_product_carousel_settings[title]" value="<?php echo esc_attr((string) $settings['title']); ?>">
                </label>

                <label>
                    <span><?php esc_html_e('Description', 'lloyds-industrial'); ?></span>
                    <textarea name="li_product_carousel_settings[description]" rows="4"><?php echo esc_textarea((string) $settings['description']); ?></textarea>
                </label>
            </section>

            <section class="li-settings-card">
                <h2><?php esc_html_e('Products', 'lloyds-industrial'); ?></h2>
                <label>
                    <span><?php esc_html_e('Source', 'lloyds-industrial'); ?></span>
                    <select name="li_product_carousel_settings[source]">
                        <option value="featured" <?php selected($settings['source'], 'featured'); ?>><?php esc_html_e('Featured products', 'lloyds-industrial'); ?></option>
                        <option value="latest" <?php selected($settings['source'], 'latest'); ?>><?php esc_html_e('Latest products', 'lloyds-industrial'); ?></option>
                        <option value="category" <?php selected($settings['source'], 'category'); ?>><?php esc_html_e('Product category', 'lloyds-industrial'); ?></option>
                        <option value="manual" <?php selected($settings['source'], 'manual'); ?>><?php esc_html_e('Manual product IDs', 'lloyds-industrial'); ?></option>
                    </select>
                </label>

                <label>
                    <span><?php esc_html_e('Product category', 'lloyds-industrial'); ?></span>
                    <select name="li_product_carousel_settings[category]">
                        <option value=""><?php esc_html_e('Select a category', 'lloyds-industrial'); ?></option>
                        <?php if (!is_wp_error($categories)) : ?>
                            <?php foreach ($categories as $category) : ?>
                                <?php if ($category instanceof WP_Term) : ?>
                                    <option value="<?php echo esc_attr($category->slug); ?>" <?php selected($settings['category'], $category->slug); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </label>

                <label>
                    <span><?php esc_html_e('Manual product IDs', 'lloyds-industrial'); ?></span>
                    <input type="text" name="li_product_carousel_settings[product_ids]" value="<?php echo esc_attr((string) $settings['product_ids']); ?>" placeholder="12, 18, 24">
                </label>

                <label>
                    <span><?php esc_html_e('Product limit', 'lloyds-industrial'); ?></span>
                    <input type="number" min="1" max="24" name="li_product_carousel_settings[limit]" value="<?php echo esc_attr((string) $settings['limit']); ?>">
                </label>
            </section>

            <section class="li-settings-card">
                <h2><?php esc_html_e('Behavior and Display', 'lloyds-industrial'); ?></h2>
                <label>
                    <input type="checkbox" name="li_product_carousel_settings[autoplay]" value="1" <?php checked(!empty($settings['autoplay'])); ?>>
                    <?php esc_html_e('Autoplay carousel.', 'lloyds-industrial'); ?>
                </label>

                <label>
                    <span><?php esc_html_e('Autoplay interval', 'lloyds-industrial'); ?></span>
                    <input type="number" min="2500" max="12000" step="100" name="li_product_carousel_settings[interval]" value="<?php echo esc_attr((string) $settings['interval']); ?>">
                </label>

                <label><input type="checkbox" name="li_product_carousel_settings[show_price]" value="1" <?php checked(!empty($settings['show_price'])); ?>> <?php esc_html_e('Show price or quote label.', 'lloyds-industrial'); ?></label>
                <label><input type="checkbox" name="li_product_carousel_settings[show_summary]" value="1" <?php checked(!empty($settings['show_summary'])); ?>> <?php esc_html_e('Show product summary.', 'lloyds-industrial'); ?></label>
                <label><input type="checkbox" name="li_product_carousel_settings[show_meta]" value="1" <?php checked(!empty($settings['show_meta'])); ?>> <?php esc_html_e('Show categories and document cues.', 'lloyds-industrial'); ?></label>
                <label><input type="checkbox" name="li_product_carousel_settings[show_cta]" value="1" <?php checked(!empty($settings['show_cta'])); ?>> <?php esc_html_e('Show action buttons.', 'lloyds-industrial'); ?></label>
            </section>

            <section class="li-settings-card">
                <h2><?php esc_html_e('Usage', 'lloyds-industrial'); ?></h2>
                <p><?php esc_html_e('Place the carousel anywhere shortcodes are supported.', 'lloyds-industrial'); ?></p>
                <p><code>[li_product_carousel]</code></p>
                <p><code>[li_product_carousel source="latest" limit="6"]</code></p>
                <p><code>[li_product_carousel source="category" category="cleaner-degreasers"]</code></p>
            </section>

            <p class="submit li-settings-submit">
                <?php submit_button(__('Save Carousel Settings', 'lloyds-industrial'), 'primary', 'submit', false); ?>
            </p>
        </form>
    </div>
    <?php
}

function li_get_product_carousel_product_ids(array $settings): array
{
    if (!post_type_exists('product')) {
        return [];
    }

    $limit = absint($settings['limit'] ?? 8) ?: 8;
    $source = sanitize_key((string) ($settings['source'] ?? 'featured'));

    if ($source === 'manual') {
        return array_slice(array_filter(array_map('absint', explode(',', (string) ($settings['product_ids'] ?? '')))), 0, $limit);
    }

    $args = [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'fields'         => 'ids',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    if ($source === 'featured' && taxonomy_exists('product_visibility')) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'product_visibility',
                'field'    => 'name',
                'terms'    => ['featured'],
            ],
        ];
    } elseif ($source === 'category' && !empty($settings['category']) && taxonomy_exists('product_cat')) {
        $args['tax_query'] = [
            [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => [sanitize_title((string) $settings['category'])],
            ],
        ];
    }

    $query = new WP_Query($args);

    if ($source === 'featured' && !$query->posts) {
        unset($args['tax_query']);
        $query = new WP_Query($args);
    }

    return array_map('absint', $query->posts);
}

function li_render_product_carousel_shortcode(mixed $atts = []): string
{
    $settings = li_get_product_carousel_settings();
    $atts = shortcode_atts([
        'source'   => '',
        'category' => '',
        'limit'    => '',
        'title'    => '',
    ], is_array($atts) ? $atts : [], 'li_product_carousel');

    foreach (['source', 'category', 'title'] as $key) {
        if ($atts[$key] !== '') {
            $settings[$key] = sanitize_text_field((string) $atts[$key]);
        }
    }

    if ($atts['limit'] !== '') {
        $settings['limit'] = min(24, max(1, absint($atts['limit'])));
    }

    if (empty($settings['enabled'])) {
        return '';
    }

    $product_ids = li_get_product_carousel_product_ids($settings);

    if (!$product_ids) {
        return '';
    }

    $carousel_id = 'li-product-carousel-' . wp_unique_id();
    $autoplay = !empty($settings['autoplay']) ? 'true' : 'false';
    $interval = absint($settings['interval'] ?? 5200) ?: 5200;

    ob_start();
    ?>
    <section id="<?php echo esc_attr($carousel_id); ?>" class="li-product-carousel" data-li-product-carousel data-autoplay="<?php echo esc_attr($autoplay); ?>" data-interval="<?php echo esc_attr((string) $interval); ?>" aria-label="<?php echo esc_attr((string) $settings['title']); ?>">
        <div class="li-product-carousel__header">
            <div>
                <?php if (!empty($settings['eyebrow'])) : ?>
                    <p class="li-eyebrow"><?php echo esc_html((string) $settings['eyebrow']); ?></p>
                <?php endif; ?>
                <?php if (!empty($settings['title'])) : ?>
                    <h2><?php echo esc_html((string) $settings['title']); ?></h2>
                <?php endif; ?>
                <?php if (!empty($settings['description'])) : ?>
                    <p><?php echo esc_html((string) $settings['description']); ?></p>
                <?php endif; ?>
            </div>
            <div class="li-product-carousel__controls">
                <button type="button" data-li-carousel-prev aria-label="<?php esc_attr_e('Previous products', 'lloyds-industrial'); ?>">‹</button>
                <button type="button" data-li-carousel-next aria-label="<?php esc_attr_e('Next products', 'lloyds-industrial'); ?>">›</button>
            </div>
        </div>

        <div class="li-product-carousel__viewport" data-li-carousel-viewport tabindex="0">
            <div class="li-product-carousel__track">
                <?php foreach ($product_ids as $product_id) : ?>
                    <?php echo li_render_product_carousel_card($product_id, $settings); ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php

    return (string) ob_get_clean();
}

function li_render_product_carousel_card(int $product_id, array $settings): string
{
    $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
    $summary = (string) get_post_meta($product_id, '_li_public_summary', true);

    if ($summary === '') {
        $summary = get_the_excerpt($product_id);
    }

    $image = get_the_post_thumbnail($product_id, 'medium_large', [
        'loading' => 'lazy',
    ]);

    if ($image === '' && function_exists('wc_placeholder_img')) {
        $image = wc_placeholder_img('medium_large');
    }

    $price = is_object($product) && method_exists($product, 'get_price_html') ? (string) $product->get_price_html() : '';
    $url = get_permalink($product_id);

    ob_start();
    ?>
    <article class="li-product-carousel-card">
        <a class="li-product-carousel-card__media" href="<?php echo esc_url($url); ?>">
            <?php echo wp_kses_post($image); ?>
        </a>
        <div class="li-product-carousel-card__body">
            <?php if (!empty($settings['show_meta'])) : ?>
                <div class="li-product-carousel-card__meta">
                    <?php echo wp_kses_post(li_render_product_card_meta_for_product($product_id)); ?>
                </div>
            <?php endif; ?>
            <h3><a href="<?php echo esc_url($url); ?>"><?php echo esc_html(get_the_title($product_id)); ?></a></h3>
            <?php if (!empty($settings['show_summary']) && $summary !== '') : ?>
                <p><?php echo esc_html(wp_trim_words(wp_strip_all_tags($summary), 20)); ?></p>
            <?php endif; ?>
            <?php if (!empty($settings['show_price']) && $price !== '') : ?>
                <div class="li-product-carousel-card__price"><?php echo wp_kses_post($price); ?></div>
            <?php endif; ?>
            <?php if (!empty($settings['show_cta'])) : ?>
                <div class="li-product-carousel-card__actions">
                    <a class="li-button-primary" href="<?php echo esc_url($url); ?>"><?php esc_html_e('View Product', 'lloyds-industrial'); ?></a>
                    <a class="li-button-secondary" href="<?php echo esc_url(home_url('/contact/')); ?>"><?php esc_html_e('Ask Lloyds', 'lloyds-industrial'); ?></a>
                </div>
            <?php endif; ?>
        </div>
    </article>
    <?php

    return (string) ob_get_clean();
}

function li_render_product_card_meta_for_product(int $product_id): string
{
    $terms = taxonomy_exists('product_cat') ? wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']) : [];

    if (is_wp_error($terms) || !$terms) {
        return '<span>' . esc_html__('Product', 'lloyds-industrial') . '</span>';
    }

    return '<span>' . esc_html(implode(', ', array_slice($terms, 0, 2))) . '</span>';
}

add_shortcode('li_product_carousel', 'li_render_product_carousel_shortcode');
