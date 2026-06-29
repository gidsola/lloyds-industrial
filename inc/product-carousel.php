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
        'display_mode' => 'products',
        'source'       => 'featured',
        'product_ids'  => '',
        'category'     => '',
        'category_slugs' => '',
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
    $display_mode = sanitize_key((string) ($input['display_mode'] ?? $defaults['display_mode']));
    $display_mode = in_array($display_mode, ['products', 'categories'], true) ? $display_mode : $defaults['display_mode'];

    $limit = absint($input['limit'] ?? $defaults['limit']);
    $interval = absint($input['interval'] ?? $defaults['interval']);

    return [
        'enabled'      => !empty($input['enabled']),
        'eyebrow'      => sanitize_text_field((string) ($input['eyebrow'] ?? $defaults['eyebrow'])),
        'title'        => sanitize_text_field((string) ($input['title'] ?? $defaults['title'])),
        'description'  => sanitize_textarea_field((string) ($input['description'] ?? $defaults['description'])),
        'display_mode' => $display_mode,
        'source'       => $source,
        'product_ids'  => implode(',', array_filter(array_map('absint', preg_split('/[\s,]+/', (string) ($input['product_ids'] ?? ''))))),
        'category'     => sanitize_title((string) ($input['category'] ?? '')),
        'category_slugs' => implode(',', array_filter(array_map('sanitize_title', preg_split('/[\s,]+/', (string) ($input['category_slugs'] ?? ''))))),
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
    add_submenu_page(
        'lloyds',
        __('Lloyds Product Carousel', 'lloyds-industrial'),
        __('Product Carousel', 'lloyds-industrial'),
        'manage_options',
        'lloyds-product-carousel',
        'li_render_product_carousel_admin_page'
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
    <div class="wrap li-settings-page li-product-carousel-admin">
        <div class="li-settings-hero">
            <div>
                <p class="li-settings-kicker"><?php esc_html_e('Product Discovery', 'lloyds-industrial'); ?></p>
                <h1><?php esc_html_e('Lloyds Product Carousel', 'lloyds-industrial'); ?></h1>
                <p><?php esc_html_e('Configure the global carousel defaults used by the base shortcode. Individual shortcode attributes can override selected defaults for one placement.', 'lloyds-industrial'); ?></p>
            </div>
            <div class="li-settings-summary">
                <div><span><?php esc_html_e('Status', 'lloyds-industrial'); ?></span><strong><?php echo esc_html($settings['enabled'] ? __('Enabled', 'lloyds-industrial') : __('Disabled', 'lloyds-industrial')); ?></strong></div>
                <div><span><?php esc_html_e('Default Mode', 'lloyds-industrial'); ?></span><strong><?php echo esc_html(ucfirst((string) $settings['display_mode'])); ?></strong></div>
                <div><span><?php esc_html_e('Base Shortcode', 'lloyds-industrial'); ?></span><strong><code>[li_product_carousel]</code></strong></div>
            </div>
        </div>

        <nav class="li-admin-tabs" data-li-admin-tabs=".li-product-carousel-admin" data-li-tabs-key="li-product-carousel-tab" aria-label="<?php esc_attr_e('Carousel settings sections', 'lloyds-industrial'); ?>">
            <button class="li-admin-tab" type="button" data-li-tab-target="carousel-content"><?php esc_html_e('Content Defaults', 'lloyds-industrial'); ?></button>
            <button class="li-admin-tab" type="button" data-li-tab-target="carousel-slides"><?php esc_html_e('Slide Defaults', 'lloyds-industrial'); ?></button>
            <button class="li-admin-tab" type="button" data-li-tab-target="carousel-display"><?php esc_html_e('Display', 'lloyds-industrial'); ?></button>
            <button class="li-admin-tab" type="button" data-li-tab-target="carousel-usage"><?php esc_html_e('Shortcode Usage', 'lloyds-industrial'); ?></button>
        </nav>

        <form method="post" action="options.php" class="li-settings-form li-admin-tab-panels li-carousel-settings-form">
            <?php settings_fields('li_product_carousel_settings'); ?>

            <section class="li-admin-panel li-admin-tab-panel" data-li-tab-panel="carousel-content">
                <h2><?php esc_html_e('Content Defaults', 'lloyds-industrial'); ?></h2>
                <p><?php esc_html_e('These values appear whenever the shortcode does not provide its own title-related attributes.', 'lloyds-industrial'); ?></p>
                <label class="li-carousel-check">
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
                <?php submit_button(__('Save Carousel Settings', 'lloyds-industrial'), 'primary', 'submit', false); ?>
            </section>

            <section class="li-admin-panel li-admin-tab-panel" data-li-tab-panel="carousel-slides">
                <h2><?php esc_html_e('Slide Defaults', 'lloyds-industrial'); ?></h2>
                <p><?php esc_html_e('These defaults decide which slides appear for the base shortcode. Placement-specific shortcodes can override mode, source, category, category slugs, and limit.', 'lloyds-industrial'); ?></p>
                <label>
                    <span><?php esc_html_e('Display mode', 'lloyds-industrial'); ?></span>
                    <select name="li_product_carousel_settings[display_mode]">
                        <option value="products" <?php selected($settings['display_mode'], 'products'); ?>><?php esc_html_e('Products', 'lloyds-industrial'); ?></option>
                        <option value="categories" <?php selected($settings['display_mode'], 'categories'); ?>><?php esc_html_e('Product categories', 'lloyds-industrial'); ?></option>
                    </select>
                </label>

                <label>
                    <span><?php esc_html_e('Product source', 'lloyds-industrial'); ?></span>
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
                    <span><?php esc_html_e('Manual category slugs', 'lloyds-industrial'); ?></span>
                    <input type="text" name="li_product_carousel_settings[category_slugs]" value="<?php echo esc_attr((string) $settings['category_slugs']); ?>" placeholder="cleaner-degreasers, automotive-fleet-maintenance">
                </label>

                <label>
                    <span><?php esc_html_e('Product limit', 'lloyds-industrial'); ?></span>
                    <input type="number" min="1" max="24" name="li_product_carousel_settings[limit]" value="<?php echo esc_attr((string) $settings['limit']); ?>">
                </label>
                <?php submit_button(__('Save Carousel Settings', 'lloyds-industrial'), 'primary', 'submit', false); ?>
            </section>

            <section class="li-admin-panel li-admin-tab-panel" data-li-tab-panel="carousel-display">
                <h2><?php esc_html_e('Behavior and Display', 'lloyds-industrial'); ?></h2>
                <p><?php esc_html_e('These display controls are global defaults for every carousel placement.', 'lloyds-industrial'); ?></p>
                <label class="li-carousel-check">
                    <input type="checkbox" name="li_product_carousel_settings[autoplay]" value="1" <?php checked(!empty($settings['autoplay'])); ?>>
                    <?php esc_html_e('Autoplay carousel.', 'lloyds-industrial'); ?>
                </label>

                <label>
                    <span><?php esc_html_e('Autoplay interval', 'lloyds-industrial'); ?></span>
                    <input type="number" min="2500" max="12000" step="100" name="li_product_carousel_settings[interval]" value="<?php echo esc_attr((string) $settings['interval']); ?>">
                </label>

                <label class="li-carousel-check"><input type="checkbox" name="li_product_carousel_settings[show_price]" value="1" <?php checked(!empty($settings['show_price'])); ?>> <?php esc_html_e('Show price or quote label.', 'lloyds-industrial'); ?></label>
                <label class="li-carousel-check"><input type="checkbox" name="li_product_carousel_settings[show_summary]" value="1" <?php checked(!empty($settings['show_summary'])); ?>> <?php esc_html_e('Show product summary.', 'lloyds-industrial'); ?></label>
                <label class="li-carousel-check"><input type="checkbox" name="li_product_carousel_settings[show_meta]" value="1" <?php checked(!empty($settings['show_meta'])); ?>> <?php esc_html_e('Show categories and document cues.', 'lloyds-industrial'); ?></label>
                <label class="li-carousel-check"><input type="checkbox" name="li_product_carousel_settings[show_cta]" value="1" <?php checked(!empty($settings['show_cta'])); ?>> <?php esc_html_e('Show action buttons.', 'lloyds-industrial'); ?></label>
                <?php submit_button(__('Save Carousel Settings', 'lloyds-industrial'), 'primary', 'submit', false); ?>
            </section>

            <section class="li-admin-panel li-admin-tab-panel" data-li-tab-panel="carousel-usage">
                <h2><?php esc_html_e('Shortcode Usage', 'lloyds-industrial'); ?></h2>
                <p><?php esc_html_e('The base shortcode uses every saved default on this page. Attribute values override only the matching default for that one carousel.', 'lloyds-industrial'); ?></p>
                <div class="li-carousel-usage-grid">
                    <div>
                        <h3><?php esc_html_e('Examples', 'lloyds-industrial'); ?></h3>
                        <p><code>[li_product_carousel]</code></p>
                        <p><code>[li_product_carousel source="latest" limit="6"]</code></p>
                        <p><code>[li_product_carousel source="category" category="cleaner-degreasers"]</code></p>
                        <p><code>[li_product_carousel mode="categories" limit="6"]</code></p>
                        <p><code>[li_product_carousel mode="categories" category_slugs="cleaner-degreasers,automotive-fleet-maintenance"]</code></p>
                    </div>
                    <div>
                        <h3><?php esc_html_e('Override Map', 'lloyds-industrial'); ?></h3>
                        <dl class="li-carousel-override-list">
                            <div><dt><code>mode</code></dt><dd><?php esc_html_e('Overrides display mode: products or categories.', 'lloyds-industrial'); ?></dd></div>
                            <div><dt><code>source</code></dt><dd><?php esc_html_e('Overrides product source: featured, latest, category, or manual.', 'lloyds-industrial'); ?></dd></div>
                            <div><dt><code>category</code></dt><dd><?php esc_html_e('Overrides the product category when source is category.', 'lloyds-industrial'); ?></dd></div>
                            <div><dt><code>category_slugs</code></dt><dd><?php esc_html_e('Overrides the category list when mode is categories.', 'lloyds-industrial'); ?></dd></div>
                            <div><dt><code>limit</code></dt><dd><?php esc_html_e('Overrides the number of products or categories shown.', 'lloyds-industrial'); ?></dd></div>
                            <div><dt><code>title</code></dt><dd><?php esc_html_e('Overrides the carousel heading only.', 'lloyds-industrial'); ?></dd></div>
                        </dl>
                    </div>
                </div>
            </section>
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

function li_get_product_carousel_categories(array $settings): array
{
    if (!taxonomy_exists('product_cat')) {
        return [];
    }

    $limit = absint($settings['limit'] ?? 8) ?: 8;
    $slugs = array_filter(array_map('sanitize_title', explode(',', (string) ($settings['category_slugs'] ?? ''))));

    $args = [
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'number'     => $limit,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ];

    if ($slugs) {
        $args['slug'] = $slugs;
        $args['orderby'] = 'slug__in';
    }

    $terms = get_terms($args);

    if (is_wp_error($terms) || !$terms) {
        return [];
    }

    return array_values(array_filter($terms, static fn ($term): bool => $term instanceof WP_Term));
}

function li_render_product_carousel_shortcode(mixed $atts = []): string
{
    $settings = li_get_product_carousel_settings();
    $atts = shortcode_atts([
        'mode'     => '',
        'source'   => '',
        'category' => '',
        'category_slugs' => '',
        'limit'    => '',
        'title'    => '',
    ], is_array($atts) ? $atts : [], 'li_product_carousel');

    if ($atts['mode'] !== '') {
        $mode = sanitize_key((string) $atts['mode']);
        $settings['display_mode'] = in_array($mode, ['products', 'categories'], true) ? $mode : $settings['display_mode'];
    }

    foreach (['source', 'category', 'category_slugs', 'title'] as $key) {
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

    $display_mode = $settings['display_mode'] ?? 'products';
    $slides = $display_mode === 'categories'
        ? li_get_product_carousel_categories($settings)
        : li_get_product_carousel_product_ids($settings);

    if (!$slides) {
        return '';
    }

    $carousel_id = 'li-product-carousel-' . wp_unique_id();
    $autoplay = !empty($settings['autoplay']) ? 'true' : 'false';
    $interval = absint($settings['interval'] ?? 5200) ?: 5200;

    ob_start();
    ?>
    <section id="<?php echo esc_attr($carousel_id); ?>" class="li-product-carousel li-product-carousel--<?php echo esc_attr((string) $display_mode); ?>" data-li-product-carousel data-autoplay="<?php echo esc_attr($autoplay); ?>" data-interval="<?php echo esc_attr((string) $interval); ?>" aria-label="<?php echo esc_attr((string) $settings['title']); ?>">
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
                <button type="button" data-li-carousel-prev aria-label="<?php esc_attr_e('Previous slides', 'lloyds-industrial'); ?>">&lt;</button>
                <button type="button" data-li-carousel-next aria-label="<?php esc_attr_e('Next slides', 'lloyds-industrial'); ?>">&gt;</button>
            </div>
        </div>

        <div class="li-product-carousel__viewport" data-li-carousel-viewport tabindex="0">
            <div class="li-product-carousel__track">
                <?php foreach ($slides as $slide) : ?>
                    <?php
                    echo $slide instanceof WP_Term
                        ? li_render_product_carousel_category_card($slide, $settings)
                        : li_render_product_carousel_card((int) $slide, $settings);
                    ?>
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

function li_render_product_carousel_category_card(WP_Term $term, array $settings): string
{
    $url = get_term_link($term);
    $url = is_wp_error($url) ? home_url('/products/') : $url;
    $description = trim(wp_strip_all_tags(term_description($term, 'product_cat')));
    $thumbnail_id = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
    $image = $thumbnail_id ? wp_get_attachment_image($thumbnail_id, 'medium_large', false, ['loading' => 'lazy']) : '';

    if ($description === '') {
        $description = sprintf(
            /* translators: %s: product category name */
            __('Browse Lloyds products in the %s family.', 'lloyds-industrial'),
            $term->name
        );
    }

    ob_start();
    ?>
    <article class="li-product-carousel-card li-product-carousel-card--category">
        <a class="li-product-carousel-card__media li-product-carousel-card__media--category" href="<?php echo esc_url($url); ?>">
            <?php if ($image !== '') : ?>
                <?php echo wp_kses_post($image); ?>
            <?php else : ?>
                <span><?php echo esc_html(strtoupper(substr($term->name, 0, 1))); ?></span>
            <?php endif; ?>
        </a>
        <div class="li-product-carousel-card__body">
            <?php if (!empty($settings['show_meta'])) : ?>
                <div class="li-product-carousel-card__meta">
                    <span><?php echo esc_html(sprintf(_n('%d product', '%d products', (int) $term->count, 'lloyds-industrial'), (int) $term->count)); ?></span>
                </div>
            <?php endif; ?>
            <h3><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($term->name); ?></a></h3>
            <?php if (!empty($settings['show_summary'])) : ?>
                <p><?php echo esc_html(wp_trim_words($description, 18)); ?></p>
            <?php endif; ?>
            <?php if (!empty($settings['show_cta'])) : ?>
                <div class="li-product-carousel-card__actions">
                    <a class="li-button-primary" href="<?php echo esc_url($url); ?>"><?php esc_html_e('View All Products', 'lloyds-industrial'); ?></a>
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
