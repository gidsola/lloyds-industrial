<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const LI_SEO_SETTINGS_OPTION = 'li_seo_settings';
const LI_SEO_CACHE_VERSION_OPTION = 'li_seo_cache_version';
const LI_SEO_REWRITE_VERSION_OPTION = 'li_seo_rewrite_version';
const LI_SEO_ADVANCED_CACHE_SIGNATURE = 'B2B SEO Advanced Page Cache';

add_action('init', 'li_seo_register_routes');
add_action('init', 'li_seo_disable_default_canonical', 20);
add_action('after_switch_theme', 'li_seo_after_switch_theme');
add_action('admin_menu', 'li_seo_register_admin_menu');
add_action('admin_init', 'li_seo_register_settings');
add_action('admin_init', 'li_seo_handle_admin_actions');
add_action('admin_init', 'li_seo_maybe_flush_rewrites');
add_action('init', 'li_seo_register_post_meta', 30);
add_action('add_meta_boxes', 'li_seo_add_meta_boxes');
add_action('save_post', 'li_seo_save_post_meta', 10, 2);
add_action('save_post', 'li_seo_purge_cache_for_post', 20);
add_action('deleted_post', 'li_seo_purge_all_cache');
add_action('edited_terms', 'li_seo_purge_all_cache', 10, 0);
add_action('update_option_' . LI_SEO_SETTINGS_OPTION, 'li_seo_purge_all_cache', 10, 0);
add_action('update_option_' . LI_SEO_SETTINGS_OPTION, 'li_seo_write_cache_config', 11, 0);
add_action('wp_head', 'li_seo_render_head', 1);
add_action('send_headers', 'li_seo_send_cache_capability_headers');
add_action('template_redirect', 'li_seo_maybe_serve_cache', 0);
add_action('enqueue_block_editor_assets', 'li_seo_enqueue_block_editor_panel');
add_filter('robots_txt', 'li_seo_filter_robots_txt', 10, 2);
add_filter('document_title_parts', 'li_seo_filter_document_title_parts');

function li_seo_get_defaults(): array
{
    return [
        'enabled'                  => true,
        'site_title_pattern'       => '%title% | %site%',
        'home_title'               => get_bloginfo('name'),
        'default_description'      => get_bloginfo('description'),
        'separator'                => '|',
        'organization_name'        => get_bloginfo('name'),
        'organization_logo_id'     => 0,
        'social_image_id'          => 0,
        'twitter_site'             => '',
        'facebook_app_id'          => '',
        'enable_open_graph'        => true,
        'enable_twitter_cards'     => true,
        'enable_json_ld'           => true,
        'enable_canonical'         => true,
        'enable_robots_meta'       => true,
        'noindex_search'           => true,
        'noindex_404'              => true,
        'noindex_private_docs'     => true,
        'enable_xml_sitemap'       => true,
        'sitemap_post_types'       => ['page', 'post', 'product'],
        'sitemap_taxonomies'       => ['category', 'post_tag', 'product_cat', 'li_industry', 'li_application'],
        'enable_robots_txt'        => true,
        'enable_page_cache'        => true,
        'cache_ttl'                => 3600,
        'cache_mobile_separately'  => true,
        'cache_products'           => true,
        'cache_archives'           => true,
    ];
}

function li_seo_get_settings(): array
{
    $settings = get_option(LI_SEO_SETTINGS_OPTION, []);

    return wp_parse_args(is_array($settings) ? $settings : [], li_seo_get_defaults());
}

function li_seo_register_routes(): void
{
    add_rewrite_rule('^li-sitemap\.xml$', 'index.php?li_seo_sitemap=1', 'top');
    add_rewrite_tag('%li_seo_sitemap%', '1');
}

function li_seo_after_switch_theme(): void
{
    if (!get_option(LI_SEO_SETTINGS_OPTION)) {
        update_option(LI_SEO_SETTINGS_OPTION, li_seo_get_defaults());
    }

    if (!get_option(LI_SEO_CACHE_VERSION_OPTION)) {
        update_option(LI_SEO_CACHE_VERSION_OPTION, (string) time());
    }

    li_seo_register_routes();
    flush_rewrite_rules();
    update_option(LI_SEO_REWRITE_VERSION_OPTION, '1');
}

function li_seo_maybe_flush_rewrites(): void
{
    if (get_option(LI_SEO_REWRITE_VERSION_OPTION) === '1') {
        return;
    }

    li_seo_register_routes();
    flush_rewrite_rules();
    update_option(LI_SEO_REWRITE_VERSION_OPTION, '1');
}

function li_seo_disable_default_canonical(): void
{
    if (!empty(li_seo_get_settings()['enable_canonical'])) {
        remove_action('wp_head', 'rel_canonical');
    }
}

function li_seo_register_admin_menu(): void
{
    add_submenu_page(
        'b2b',
        __('B2B SEO', 'b2b-industrial'),
        __('SEO', 'b2b-industrial'),
        'manage_options',
        'b2b-seo',
        'li_seo_render_admin_page'
    );
}

function li_seo_register_settings(): void
{
    register_setting('li_seo_settings', LI_SEO_SETTINGS_OPTION, [
        'type'              => 'array',
        'sanitize_callback' => 'li_seo_sanitize_settings',
        'default'           => li_seo_get_defaults(),
    ]);
}

function li_seo_sanitize_settings(array $input): array
{
    $defaults = li_seo_get_defaults();
    $post_types = li_seo_get_supported_public_post_types();
    $taxonomies = li_seo_get_supported_public_taxonomies();
    $cache_ttl = absint($input['cache_ttl'] ?? $defaults['cache_ttl']);

    return [
        'enabled'                  => !empty($input['enabled']),
        'site_title_pattern'       => sanitize_text_field((string) ($input['site_title_pattern'] ?? $defaults['site_title_pattern'])),
        'home_title'               => sanitize_text_field((string) ($input['home_title'] ?? $defaults['home_title'])),
        'default_description'      => sanitize_textarea_field((string) ($input['default_description'] ?? '')),
        'separator'                => sanitize_text_field((string) ($input['separator'] ?? $defaults['separator'])),
        'organization_name'        => sanitize_text_field((string) ($input['organization_name'] ?? $defaults['organization_name'])),
        'organization_logo_id'     => absint($input['organization_logo_id'] ?? 0),
        'social_image_id'          => absint($input['social_image_id'] ?? 0),
        'twitter_site'             => sanitize_text_field((string) ($input['twitter_site'] ?? '')),
        'facebook_app_id'          => sanitize_text_field((string) ($input['facebook_app_id'] ?? '')),
        'enable_open_graph'        => !empty($input['enable_open_graph']),
        'enable_twitter_cards'     => !empty($input['enable_twitter_cards']),
        'enable_json_ld'           => !empty($input['enable_json_ld']),
        'enable_canonical'         => !empty($input['enable_canonical']),
        'enable_robots_meta'       => !empty($input['enable_robots_meta']),
        'noindex_search'           => !empty($input['noindex_search']),
        'noindex_404'              => !empty($input['noindex_404']),
        'noindex_private_docs'     => !empty($input['noindex_private_docs']),
        'enable_xml_sitemap'       => !empty($input['enable_xml_sitemap']),
        'sitemap_post_types'       => array_values(array_intersect(array_map('sanitize_key', (array) ($input['sitemap_post_types'] ?? [])), $post_types)),
        'sitemap_taxonomies'       => array_values(array_intersect(array_map('sanitize_key', (array) ($input['sitemap_taxonomies'] ?? [])), $taxonomies)),
        'enable_robots_txt'        => !empty($input['enable_robots_txt']),
        'enable_page_cache'        => !empty($input['enable_page_cache']),
        'cache_ttl'                => min(DAY_IN_SECONDS, max(300, $cache_ttl ?: (int) $defaults['cache_ttl'])),
        'cache_mobile_separately'  => !empty($input['cache_mobile_separately']),
        'cache_products'           => !empty($input['cache_products']),
        'cache_archives'           => !empty($input['cache_archives']),
    ];
}

function li_seo_get_supported_public_post_types(): array
{
    $post_types = get_post_types(['public' => true], 'names');
    unset($post_types['attachment']);

    if (post_type_exists('product')) {
        $post_types['product'] = 'product';
    }

    return array_values($post_types);
}

function li_seo_get_supported_public_taxonomies(): array
{
    $taxonomies = get_taxonomies(['public' => true], 'names');

    foreach (['product_cat', 'li_industry', 'li_application'] as $taxonomy) {
        if (taxonomy_exists($taxonomy)) {
            $taxonomies[$taxonomy] = $taxonomy;
        }
    }

    return array_values($taxonomies);
}

function li_seo_register_post_meta(): void
{
    $string_meta = [
        '_li_seo_title'              => 'sanitize_text_field',
        '_li_seo_description'        => 'sanitize_textarea_field',
        '_li_seo_canonical'          => 'esc_url_raw',
        '_li_seo_robots'             => 'li_seo_sanitize_robots_meta',
        '_li_seo_focus_keyword'      => 'sanitize_text_field',
        '_li_seo_social_title'       => 'sanitize_text_field',
        '_li_seo_social_description' => 'sanitize_textarea_field',
    ];

    foreach (li_seo_get_supported_public_post_types() as $post_type) {
        foreach ($string_meta as $key => $sanitize_callback) {
            register_post_meta($post_type, $key, [
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => $sanitize_callback,
                'auth_callback'     => 'li_seo_can_edit_post_meta',
            ]);
        }

        register_post_meta($post_type, '_li_seo_social_image_id', [
            'type'              => 'integer',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'absint',
            'auth_callback'     => 'li_seo_can_edit_post_meta',
        ]);
    }
}

function li_seo_can_edit_post_meta(mixed $allowed, string $meta_key, int $post_id): bool
{
    return current_user_can('edit_post', $post_id);
}

function li_seo_sanitize_robots_meta(mixed $robots): string
{
    $robots = is_string($robots) ? $robots : '';

    return in_array($robots, ['', 'index,follow', 'noindex,follow', 'noindex,nofollow'], true) ? $robots : '';
}

function li_seo_enqueue_block_editor_panel(): void
{
    $screen = get_current_screen();

    if (!$screen || !in_array((string) $screen->post_type, li_seo_get_supported_public_post_types(), true)) {
        return;
    }

    wp_enqueue_media();

    wp_enqueue_style(
        'b2b-settings-admin',
        get_template_directory_uri() . '/assets/css/settings-admin.css',
        [],
        filemtime(get_template_directory() . '/assets/css/settings-admin.css')
    );

    wp_enqueue_script(
        'b2b-seo-editor',
        get_template_directory_uri() . '/assets/js/seo-editor.js',
        ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data'],
        filemtime(get_template_directory() . '/assets/js/seo-editor.js'),
        true
    );

    wp_localize_script('b2b-seo-editor', 'b2bSeoEditor', [
        'supportedPostTypes' => li_seo_get_supported_public_post_types(),
        'labels' => [
            'eyebrow'           => __('B2B SEO', 'b2b-industrial'),
            'title'             => __('Search and Social Preview', 'b2b-industrial'),
            'description'       => __('Tune how this content appears in search results, social cards, and canonical discovery.', 'b2b-industrial'),
            'seoTitle'          => __('SEO Title', 'b2b-industrial'),
            'seoTitleHelp'      => __('Recommended: 50-60 characters. Leave blank to auto-generate.', 'b2b-industrial'),
            'metaDescription'   => __('Meta Description', 'b2b-industrial'),
            'metaDescriptionHelp' => __('Recommended: 140-160 characters. Product summaries and excerpts are used as fallback.', 'b2b-industrial'),
            'focusKeyword'      => __('Focus Keyword', 'b2b-industrial'),
            'canonical'         => __('Canonical URL', 'b2b-industrial'),
            'robots'            => __('Robots', 'b2b-industrial'),
            'socialTitle'       => __('Social Title', 'b2b-industrial'),
            'socialDescription' => __('Social Description', 'b2b-industrial'),
            'socialImage'       => __('Social Image', 'b2b-industrial'),
            'selectImage'       => __('Select Image', 'b2b-industrial'),
            'removeImage'       => __('Remove', 'b2b-industrial'),
            'noImage'           => __('No image selected', 'b2b-industrial'),
            'selectImageTitle'  => __('Select SEO Social Image', 'b2b-industrial'),
            'useImage'          => __('Use This Image', 'b2b-industrial'),
            'defaults'          => __('Use global defaults', 'b2b-industrial'),
            'sidebarNote'       => __('SEO fields save with this content item.', 'b2b-industrial'),
        ],
    ]);
}

function li_seo_handle_admin_actions(): void
{
    if (!current_user_can('manage_options') || empty($_GET['li_seo_action'])) {
        return;
    }

    $action = sanitize_key((string) $_GET['li_seo_action']);
    check_admin_referer('li_seo_' . $action);

    if ($action === 'purge_cache') {
        li_seo_purge_all_cache();
        set_transient('li_seo_notice', 'cache_purged', MINUTE_IN_SECONDS);
    } elseif ($action === 'install_dropin') {
        $result = li_seo_install_advanced_cache_dropin();
        set_transient('li_seo_notice', $result ? 'dropin_installed' : 'dropin_failed', MINUTE_IN_SECONDS);
    } elseif ($action === 'install_htaccess') {
        $result = li_seo_install_htaccess_rules();
        set_transient('li_seo_notice', $result ? 'htaccess_installed' : 'htaccess_failed', MINUTE_IN_SECONDS);
    } elseif ($action === 'enable_wp_cache') {
        $result = li_seo_enable_wp_cache_constant();
        if (in_array($result, ['wp_cache_enabled', 'wp_cache_already_enabled'], true)) {
            li_seo_install_advanced_cache_dropin();
        }
        set_transient('li_seo_notice', $result, MINUTE_IN_SECONDS);
    }

    wp_safe_redirect(admin_url('admin.php?page=b2b-seo'));
    exit;
}

add_action('admin_notices', function (): void {
    $notice = get_transient('li_seo_notice');

    if (!in_array($notice, ['cache_purged', 'dropin_installed', 'dropin_failed', 'htaccess_installed', 'htaccess_failed', 'wp_cache_enabled', 'wp_cache_already_enabled', 'wp_cache_failed'], true)) {
        return;
    }

    delete_transient('li_seo_notice');

    $messages = [
        'cache_purged'       => __('SEO page cache purged.', 'b2b-industrial'),
        'dropin_installed'   => __('Advanced cache drop-in installed. Add define(\'WP_CACHE\', true); to wp-config.php if it is not already enabled.', 'b2b-industrial'),
        'dropin_failed'      => __('Advanced cache drop-in could not be installed. Check wp-content file permissions.', 'b2b-industrial'),
        'htaccess_installed' => __('Apache browser-cache rules installed in .htaccess.', 'b2b-industrial'),
        'htaccess_failed'    => __('Apache browser-cache rules could not be installed. Check .htaccess permissions.', 'b2b-industrial'),
        'wp_cache_enabled'   => __('WP_CACHE enabled in wp-config.php. A timestamped backup was created beside the original file.', 'b2b-industrial'),
        'wp_cache_already_enabled' => __('WP_CACHE is already enabled.', 'b2b-industrial'),
        'wp_cache_failed'    => __('WP_CACHE could not be enabled automatically. Check wp-config.php file permissions.', 'b2b-industrial'),
    ];

    li_render_admin_notice(
        $messages[$notice],
        in_array($notice, ['dropin_failed', 'htaccess_failed', 'wp_cache_failed'], true) ? 'error' : 'success'
    );
});

function li_seo_add_meta_boxes(): void
{
    foreach (li_seo_get_supported_public_post_types() as $post_type) {
        if (use_block_editor_for_post_type($post_type)) {
            continue;
        }

        add_meta_box(
            'li_seo_meta',
            __('B2B SEO', 'b2b-industrial'),
            'li_seo_render_meta_box',
            $post_type,
            'normal',
            'high'
        );
    }
}

function li_seo_render_meta_box(WP_Post $post): void
{
    wp_nonce_field('li_seo_save_meta', 'li_seo_nonce');

    $title = (string) get_post_meta($post->ID, '_li_seo_title', true);
    $description = (string) get_post_meta($post->ID, '_li_seo_description', true);
    $canonical = (string) get_post_meta($post->ID, '_li_seo_canonical', true);
    $robots = (string) get_post_meta($post->ID, '_li_seo_robots', true);
    $focus_keyword = (string) get_post_meta($post->ID, '_li_seo_focus_keyword', true);
    $social_title = (string) get_post_meta($post->ID, '_li_seo_social_title', true);
    $social_description = (string) get_post_meta($post->ID, '_li_seo_social_description', true);
    $social_image_id = (int) get_post_meta($post->ID, '_li_seo_social_image_id', true);
    $social_image_label = $social_image_id ? get_the_title($social_image_id) : __('No image selected', 'b2b-industrial');
    ?>
    <div class="li-seo-metabox">
        <p>
            <label for="li_seo_title"><strong><?php esc_html_e('SEO Title', 'b2b-industrial'); ?></strong></label>
            <input class="widefat" id="li_seo_title" name="li_seo_title" type="text" value="<?php echo esc_attr($title); ?>" maxlength="70">
            <span class="description"><?php esc_html_e('Recommended: 50-60 characters. Leave blank to auto-generate.', 'b2b-industrial'); ?></span>
        </p>
        <p>
            <label for="li_seo_description"><strong><?php esc_html_e('Meta Description', 'b2b-industrial'); ?></strong></label>
            <textarea class="widefat" id="li_seo_description" name="li_seo_description" rows="3" maxlength="180"><?php echo esc_textarea($description); ?></textarea>
            <span class="description"><?php esc_html_e('Recommended: 140-160 characters. Product summaries and excerpts are used as fallback.', 'b2b-industrial'); ?></span>
        </p>
        <p>
            <label for="li_seo_focus_keyword"><strong><?php esc_html_e('Focus Keyword', 'b2b-industrial'); ?></strong></label>
            <input class="regular-text" id="li_seo_focus_keyword" name="li_seo_focus_keyword" type="text" value="<?php echo esc_attr($focus_keyword); ?>">
        </p>
        <p>
            <label for="li_seo_canonical"><strong><?php esc_html_e('Canonical URL', 'b2b-industrial'); ?></strong></label>
            <input class="widefat" id="li_seo_canonical" name="li_seo_canonical" type="url" value="<?php echo esc_attr($canonical); ?>">
        </p>
        <p>
            <label for="li_seo_robots"><strong><?php esc_html_e('Robots', 'b2b-industrial'); ?></strong></label>
            <select id="li_seo_robots" name="li_seo_robots">
                <option value="" <?php selected($robots, ''); ?>><?php esc_html_e('Use global defaults', 'b2b-industrial'); ?></option>
                <option value="index,follow" <?php selected($robots, 'index,follow'); ?>>index, follow</option>
                <option value="noindex,follow" <?php selected($robots, 'noindex,follow'); ?>>noindex, follow</option>
                <option value="noindex,nofollow" <?php selected($robots, 'noindex,nofollow'); ?>>noindex, nofollow</option>
            </select>
        </p>
        <hr>
        <p>
            <label for="li_seo_social_title"><strong><?php esc_html_e('Social Title', 'b2b-industrial'); ?></strong></label>
            <input class="widefat" id="li_seo_social_title" name="li_seo_social_title" type="text" value="<?php echo esc_attr($social_title); ?>">
        </p>
        <p>
            <label for="li_seo_social_description"><strong><?php esc_html_e('Social Description', 'b2b-industrial'); ?></strong></label>
            <textarea class="widefat" id="li_seo_social_description" name="li_seo_social_description" rows="2"><?php echo esc_textarea($social_description); ?></textarea>
        </p>
        <p data-li-media-picker>
            <input type="hidden" id="li_seo_social_image_id" name="li_seo_social_image_id" value="<?php echo esc_attr((string) $social_image_id); ?>" data-li-media-id>
            <strong><?php esc_html_e('Social Image', 'b2b-industrial'); ?></strong><br>
            <span data-li-media-label><?php echo esc_html($social_image_label); ?></span><br>
            <button type="button" class="button" data-li-media-select data-li-media-title="<?php esc_attr_e('Select SEO Social Image', 'b2b-industrial'); ?>" data-li-media-button="<?php esc_attr_e('Use This Image', 'b2b-industrial'); ?>" data-li-media-type="image"><?php esc_html_e('Select Image', 'b2b-industrial'); ?></button>
            <button type="button" class="button" data-li-media-remove <?php echo $social_image_id ? '' : 'hidden'; ?>><?php esc_html_e('Remove', 'b2b-industrial'); ?></button>
        </p>
    </div>
    <?php
}

function li_seo_save_post_meta(int $post_id, WP_Post $post): void
{
    if (!isset($_POST['li_seo_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['li_seo_nonce'])), 'li_seo_save_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $robots = isset($_POST['li_seo_robots']) ? sanitize_text_field(wp_unslash((string) $_POST['li_seo_robots'])) : '';

    if (!in_array($robots, ['', 'index,follow', 'noindex,follow', 'noindex,nofollow'], true)) {
        $robots = '';
    }

    $fields = [
        '_li_seo_title'              => isset($_POST['li_seo_title']) ? sanitize_text_field(wp_unslash((string) $_POST['li_seo_title'])) : '',
        '_li_seo_description'        => isset($_POST['li_seo_description']) ? sanitize_textarea_field(wp_unslash((string) $_POST['li_seo_description'])) : '',
        '_li_seo_canonical'          => isset($_POST['li_seo_canonical']) ? esc_url_raw(wp_unslash((string) $_POST['li_seo_canonical'])) : '',
        '_li_seo_robots'             => $robots,
        '_li_seo_focus_keyword'      => isset($_POST['li_seo_focus_keyword']) ? sanitize_text_field(wp_unslash((string) $_POST['li_seo_focus_keyword'])) : '',
        '_li_seo_social_title'       => isset($_POST['li_seo_social_title']) ? sanitize_text_field(wp_unslash((string) $_POST['li_seo_social_title'])) : '',
        '_li_seo_social_description' => isset($_POST['li_seo_social_description']) ? sanitize_textarea_field(wp_unslash((string) $_POST['li_seo_social_description'])) : '',
        '_li_seo_social_image_id'    => isset($_POST['li_seo_social_image_id']) ? absint($_POST['li_seo_social_image_id']) : 0,
    ];

    foreach ($fields as $key => $value) {
        if ($value === '' || $value === 0) {
            delete_post_meta($post_id, $key);
        } else {
            update_post_meta($post_id, $key, $value);
        }
    }
}

function li_seo_filter_document_title_parts(array $parts): array
{
    if (is_admin() || empty(li_seo_get_settings()['enabled'])) {
        return $parts;
    }

    $title = li_seo_get_title();

    if ($title !== '') {
        $parts['title'] = $title;
        unset($parts['site'], $parts['tagline']);
    }

    return $parts;
}

function li_seo_get_title(): string
{
    $settings = li_seo_get_settings();

    if (is_front_page()) {
        return (string) $settings['home_title'];
    }

    if (is_singular()) {
        $post_id = get_queried_object_id();
        $custom = (string) get_post_meta($post_id, '_li_seo_title', true);

        if ($custom !== '') {
            return $custom;
        }

        return strtr((string) $settings['site_title_pattern'], [
            '%title%' => get_the_title($post_id),
            '%site%'  => get_bloginfo('name'),
            '%sep%'   => (string) $settings['separator'],
        ]);
    }

    if (is_tax() || is_category() || is_tag()) {
        return single_term_title('', false) . ' ' . $settings['separator'] . ' ' . get_bloginfo('name');
    }

    if (is_search()) {
        return sprintf(__('Search results for %s', 'b2b-industrial'), get_search_query(false)) . ' ' . $settings['separator'] . ' ' . get_bloginfo('name');
    }

    return '';
}

function li_seo_get_description(): string
{
    $settings = li_seo_get_settings();

    if (is_singular()) {
        $post_id = get_queried_object_id();
        $custom = (string) get_post_meta($post_id, '_li_seo_description', true);

        if ($custom !== '') {
            return $custom;
        }

        if (get_post_type($post_id) === 'product') {
            $summary = (string) get_post_meta($post_id, '_li_public_summary', true);

            if ($summary !== '') {
                return wp_trim_words(wp_strip_all_tags($summary), 28, '');
            }
        }

        $excerpt = get_the_excerpt($post_id);

        if ($excerpt !== '') {
            return wp_trim_words(wp_strip_all_tags($excerpt), 28, '');
        }
    }

    if (is_tax() || is_category() || is_tag()) {
        $description = term_description();

        if ($description !== '') {
            return wp_trim_words(wp_strip_all_tags($description), 28, '');
        }
    }

    return (string) $settings['default_description'];
}

function li_seo_get_canonical_url(): string
{
    if (is_singular()) {
        $post_id = get_queried_object_id();
        $custom = (string) get_post_meta($post_id, '_li_seo_canonical', true);

        return $custom !== '' ? $custom : (string) get_permalink($post_id);
    }

    if (is_tax() || is_category() || is_tag()) {
        $term = get_queried_object();

        if ($term instanceof WP_Term) {
            $url = get_term_link($term);
            return is_wp_error($url) ? home_url('/') : (string) $url;
        }
    }

    if (is_front_page()) {
        return home_url('/');
    }

    return home_url(add_query_arg([], (string) ($_SERVER['REQUEST_URI'] ?? '/')));
}

function li_seo_get_robots(): string
{
    $settings = li_seo_get_settings();

    if (is_singular()) {
        $custom = (string) get_post_meta(get_queried_object_id(), '_li_seo_robots', true);

        if ($custom !== '') {
            return $custom;
        }
    }

    if ((!empty($settings['noindex_search']) && is_search()) || (!empty($settings['noindex_404']) && is_404())) {
        return 'noindex,follow';
    }

    if (!empty($settings['noindex_private_docs']) && is_singular('li_document')) {
        return 'noindex,nofollow';
    }

    return 'index,follow,max-image-preview:large';
}

function li_seo_get_social_image_url(): string
{
    $settings = li_seo_get_settings();
    $image_id = 0;

    if (is_singular()) {
        $post_id = get_queried_object_id();
        $image_id = (int) get_post_meta($post_id, '_li_seo_social_image_id', true);

        if (!$image_id) {
            $image_id = (int) get_post_thumbnail_id($post_id);
        }
    }

    if (!$image_id) {
        $image_id = (int) $settings['social_image_id'];
    }

    return $image_id ? (string) wp_get_attachment_image_url($image_id, 'large') : '';
}

function li_seo_render_head(): void
{
    $settings = li_seo_get_settings();

    if (empty($settings['enabled'])) {
        return;
    }

    $title = li_seo_get_title();

    if ($title === '') {
        $title = wp_get_document_title();
    }

    $description = li_seo_get_description();
    $canonical = li_seo_get_canonical_url();
    $robots = li_seo_get_robots();
    $image_url = li_seo_get_social_image_url();
    $social_title = is_singular() ? (string) get_post_meta(get_queried_object_id(), '_li_seo_social_title', true) : '';
    $social_description = is_singular() ? (string) get_post_meta(get_queried_object_id(), '_li_seo_social_description', true) : '';
    $social_title = $social_title !== '' ? $social_title : $title;
    $social_description = $social_description !== '' ? $social_description : $description;
    $type = is_singular('product') ? 'product' : (is_singular('post') ? 'article' : 'website');
    ?>
    <?php if (!empty($settings['enable_robots_meta'])) : ?>
        <meta name="robots" content="<?php echo esc_attr($robots); ?>">
    <?php endif; ?>
    <?php if ($description !== '') : ?>
        <meta name="description" content="<?php echo esc_attr($description); ?>">
    <?php endif; ?>
    <?php if (!empty($settings['enable_canonical']) && $canonical !== '') : ?>
        <link rel="canonical" href="<?php echo esc_url($canonical); ?>">
    <?php endif; ?>
    <?php if (!empty($settings['enable_xml_sitemap'])) : ?>
        <link rel="sitemap" type="application/xml" href="<?php echo esc_url(home_url('/li-sitemap.xml')); ?>">
    <?php endif; ?>
    <?php if (!empty($settings['enable_open_graph'])) : ?>
        <meta property="og:locale" content="<?php echo esc_attr(str_replace('-', '_', get_bloginfo('language'))); ?>">
        <meta property="og:type" content="<?php echo esc_attr($type); ?>">
        <meta property="og:title" content="<?php echo esc_attr($social_title); ?>">
        <?php if ($social_description !== '') : ?><meta property="og:description" content="<?php echo esc_attr($social_description); ?>"><?php endif; ?>
        <meta property="og:url" content="<?php echo esc_url($canonical); ?>">
        <meta property="og:site_name" content="<?php echo esc_attr(get_bloginfo('name')); ?>">
        <?php if ($image_url !== '') : ?><meta property="og:image" content="<?php echo esc_url($image_url); ?>"><?php endif; ?>
        <?php if (!empty($settings['facebook_app_id'])) : ?><meta property="fb:app_id" content="<?php echo esc_attr((string) $settings['facebook_app_id']); ?>"><?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($settings['enable_twitter_cards'])) : ?>
        <meta name="twitter:card" content="<?php echo esc_attr($image_url !== '' ? 'summary_large_image' : 'summary'); ?>">
        <?php if (!empty($settings['twitter_site'])) : ?><meta name="twitter:site" content="<?php echo esc_attr((string) $settings['twitter_site']); ?>"><?php endif; ?>
        <meta name="twitter:title" content="<?php echo esc_attr($social_title); ?>">
        <?php if ($social_description !== '') : ?><meta name="twitter:description" content="<?php echo esc_attr($social_description); ?>"><?php endif; ?>
        <?php if ($image_url !== '') : ?><meta name="twitter:image" content="<?php echo esc_url($image_url); ?>"><?php endif; ?>
    <?php endif; ?>
    <?php
    if (!empty($settings['enable_json_ld'])) {
        li_seo_render_json_ld($settings, $title, $description, $canonical, $image_url);
    }
}

function li_seo_render_json_ld(array $settings, string $title, string $description, string $canonical, string $image_url): void
{
    $graph = [
        [
            '@type' => 'Organization',
            '@id'   => home_url('/#organization'),
            'name'  => (string) $settings['organization_name'],
            'url'   => home_url('/'),
        ],
        [
            '@type' => 'WebSite',
            '@id'   => home_url('/#website'),
            'url'   => home_url('/'),
            'name'  => get_bloginfo('name'),
            'publisher' => ['@id' => home_url('/#organization')],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => home_url('/?s={search_term_string}'),
                'query-input' => 'required name=search_term_string',
            ],
        ],
        [
            '@type' => is_singular('product') ? 'Product' : 'WebPage',
            '@id'   => $canonical . '#webpage',
            'url'   => $canonical,
            'name'  => $title,
            'description' => $description,
            'isPartOf' => ['@id' => home_url('/#website')],
        ],
    ];

    $logo_id = (int) $settings['organization_logo_id'];

    if ($logo_id) {
        $logo_url = wp_get_attachment_image_url($logo_id, 'full');

        if ($logo_url) {
            $graph[0]['logo'] = [
                '@type' => 'ImageObject',
                'url' => $logo_url,
            ];
        }
    }

    if (is_singular('product') && function_exists('wc_get_product')) {
        $product = wc_get_product(get_queried_object_id());

        if ($product) {
            $graph[2]['sku'] = $product->get_sku();
            $graph[2]['image'] = $image_url;

            if ($product->get_price() !== '') {
                $graph[2]['offers'] = [
                    '@type' => 'Offer',
                    'price' => $product->get_price(),
                    'priceCurrency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
                    'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url' => $canonical,
                ];
            }
        }
    }

    printf(
        '<script type="application/ld+json">%s</script>' . "\n",
        wp_json_encode([
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}

function li_seo_filter_robots_txt(string $output, bool $public): string
{
    $settings = li_seo_get_settings();

    if (empty($settings['enable_robots_txt'])) {
        return $output;
    }

    if (strpos($output, 'Sitemap:') === false && !empty($settings['enable_xml_sitemap'])) {
        $output .= "\nSitemap: " . home_url('/li-sitemap.xml') . "\n";
    }

    return $output;
}

add_action('template_redirect', function (): void {
    if (get_query_var('li_seo_sitemap') !== '1') {
        return;
    }

    li_seo_render_sitemap();
    exit;
}, 1);

function li_seo_render_sitemap(): void
{
    $settings = li_seo_get_settings();

    if (empty($settings['enable_xml_sitemap'])) {
        status_header(404);
        return;
    }

    nocache_headers();
    header('Content-Type: application/xml; charset=' . get_bloginfo('charset'));

    echo '<?xml version="1.0" encoding="' . esc_attr(get_bloginfo('charset')) . '"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    li_seo_print_sitemap_url(home_url('/'), gmdate('c'));

    foreach ((array) $settings['sitemap_post_types'] as $post_type) {
        $query = new WP_Query([
            'post_type'      => sanitize_key((string) $post_type),
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        foreach ($query->posts as $post_id) {
            li_seo_print_sitemap_url((string) get_permalink((int) $post_id), get_post_modified_time('c', true, (int) $post_id));
        }
    }

    foreach ((array) $settings['sitemap_taxonomies'] as $taxonomy) {
        if (!taxonomy_exists((string) $taxonomy)) {
            continue;
        }

        $terms = get_terms([
            'taxonomy'   => (string) $taxonomy,
            'hide_empty' => true,
        ]);

        if (is_wp_error($terms)) {
            continue;
        }

        foreach ($terms as $term) {
            $url = get_term_link($term);

            if (!is_wp_error($url)) {
                li_seo_print_sitemap_url((string) $url, gmdate('c'));
            }
        }
    }

    echo '</urlset>';
}

function li_seo_print_sitemap_url(string $url, string $modified): void
{
    echo "\t<url><loc>" . esc_url($url) . '</loc><lastmod>' . esc_html($modified) . "</lastmod></url>\n";
}

function li_seo_cache_dir(): string
{
    $uploads = wp_upload_dir(null, false);
    $base = !empty($uploads['basedir']) ? (string) $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';

    return trailingslashit($base) . 'b2b-page-cache';
}

function li_seo_cache_key(): string
{
    $settings = li_seo_get_settings();
    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
    $variant = !empty($settings['cache_mobile_separately']) && wp_is_mobile() ? 'mobile' : 'desktop';
    $host = isset($_SERVER['HTTP_HOST']) ? strtolower(sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_HOST']))) : parse_url(home_url('/'), PHP_URL_HOST);

    return md5($host . '|' . $request_uri . '|' . $variant);
}

function li_seo_is_cacheable_request(): bool
{
    $settings = li_seo_get_settings();

    if (empty($settings['enable_page_cache']) || is_admin() || is_user_logged_in() || wp_doing_ajax() || wp_doing_cron()) {
        return false;
    }

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
        return false;
    }

    if (!empty($_GET)) {
        return false;
    }

    if (is_404() || is_search() || is_preview() || is_feed() || is_robots()) {
        return false;
    }

    if (function_exists('is_cart') && (is_cart() || is_checkout() || is_account_page())) {
        return false;
    }

    if (is_singular('product') && empty($settings['cache_products'])) {
        return false;
    }

    if ((is_archive() || is_home()) && empty($settings['cache_archives'])) {
        return false;
    }

    return true;
}

function li_seo_send_cache_capability_headers(): void
{
    $settings = li_seo_get_settings();

    if (empty($settings['enable_page_cache']) || is_admin() || is_user_logged_in() || wp_doing_ajax() || wp_doing_cron()) {
        return;
    }

    header('X-Cache-Enabled: B2B SEO Page Cache');
}

function li_seo_send_page_cache_headers(string $status, int $ttl, int $modified_time = 0): void
{
    header('X-Cache-Enabled: B2B SEO Page Cache');
    header('X-Cache-Status: ' . $status);
    header('Cache-Control: public, max-age=' . max(0, $ttl));

    if ($modified_time > 0) {
        header('Age: ' . max(0, time() - $modified_time));
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified_time) . ' GMT');
        header('Expires: ' . gmdate('D, d M Y H:i:s', $modified_time + $ttl) . ' GMT');
    } else {
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $ttl) . ' GMT');
    }
}

function li_seo_maybe_serve_cache(): void
{
    if (!li_seo_is_cacheable_request()) {
        return;
    }

    $settings = li_seo_get_settings();
    $cache_file = trailingslashit(li_seo_cache_dir()) . li_seo_cache_key() . '.html';
    $ttl = (int) $settings['cache_ttl'];

    if (is_readable($cache_file) && filemtime($cache_file) > (time() - $ttl)) {
        if (function_exists('li_analytics_track_page_view')) {
            li_analytics_track_page_view();
        }

        li_seo_send_page_cache_headers('HIT', $ttl, (int) filemtime($cache_file));
        readfile($cache_file);
        exit;
    }

    li_seo_send_page_cache_headers('MISS', $ttl);
    ob_start('li_seo_cache_output');
}

function li_seo_cache_output(string $html): string
{
    if ($html === '' || stripos($html, '</html>') === false) {
        return $html;
    }

    $dir = li_seo_cache_dir();

    if (!wp_mkdir_p($dir)) {
        return $html;
    }

    li_seo_write_cache_config();

    $cache_file = trailingslashit($dir) . li_seo_cache_key() . '.html';
    if (wp_is_writable($dir)) {
        file_put_contents($cache_file, $html, LOCK_EX);
    }

    return $html;
}

function li_seo_write_cache_config(): bool
{
    $dir = li_seo_cache_dir();

    if (!wp_mkdir_p($dir)) {
        return false;
    }

    $settings = li_seo_get_settings();
    $config = [
        'enabled' => !empty($settings['enable_page_cache']),
        'ttl' => (int) $settings['cache_ttl'],
        'mobile_separately' => !empty($settings['cache_mobile_separately']),
    ];

    if (!wp_is_writable($dir)) {
        return false;
    }

    return file_put_contents(trailingslashit($dir) . 'config.json', wp_json_encode($config), LOCK_EX) !== false;
}

function li_seo_advanced_cache_path(): string
{
    return trailingslashit(WP_CONTENT_DIR) . 'advanced-cache.php';
}

function li_seo_is_advanced_cache_installed(): bool
{
    $path = li_seo_advanced_cache_path();

    return is_readable($path) && strpos((string) file_get_contents($path), LI_SEO_ADVANCED_CACHE_SIGNATURE) !== false;
}

function li_seo_install_advanced_cache_dropin(): bool
{
    if (!wp_mkdir_p(li_seo_cache_dir()) || !li_seo_write_cache_config()) {
        return false;
    }

    $dropin = <<<'PHP'
<?php
/**
 * B2B SEO Advanced Page Cache
 *
 * Served before WordPress fully loads when WP_CACHE is enabled.
 */

if (!defined('ABSPATH') || PHP_SAPI === 'cli') {
    return;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method !== 'GET' || !empty($_GET)) {
    return;
}

$request_uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$path = (string) parse_url($request_uri, PHP_URL_PATH);

if (
    preg_match('#^/(wp-admin|wp-login\.php|wp-json|xmlrpc\.php)#i', $path)
    || preg_match('#/(cart|checkout|my-account|account)(/|$)#i', $path)
    || preg_match('#\.(?:php|xml|json|txt)$#i', $path)
) {
    return;
}

foreach ($_COOKIE as $name => $value) {
    if (preg_match('/^(wordpress_logged_in_|wordpress_sec_|wp-postpass_|woocommerce_items_in_cart|wp_woocommerce_session_|comment_author_)/', (string) $name)) {
        return;
    }
}

$uploads_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/uploads' : dirname(__DIR__) . '/uploads';
$cache_dir = $uploads_dir . '/b2b-page-cache';
$config_file = $cache_dir . '/config.json';
$config = [
    'enabled' => true,
    'ttl' => 3600,
    'mobile_separately' => true,
];

if (is_readable($config_file)) {
    $decoded = json_decode((string) file_get_contents($config_file), true);

    if (is_array($decoded)) {
        $config = array_merge($config, $decoded);
    }
}

if (empty($config['enabled'])) {
    return;
}

$user_agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$is_mobile = (bool) preg_match('/mobile|iphone|ipod|android|blackberry|opera mini|iemobile|tablet|ipad/i', $user_agent);
$variant = !empty($config['mobile_separately']) && $is_mobile ? 'mobile' : 'desktop';
$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$cache_file = $cache_dir . '/' . md5($host . '|' . $request_uri . '|' . $variant) . '.html';
$ttl = max(300, (int) ($config['ttl'] ?? 3600));

if (!is_readable($cache_file) || filemtime($cache_file) <= (time() - $ttl)) {
    header('X-Cache-Enabled: B2B SEO Advanced Page Cache');
    header('X-Cache-Status: MISS');
    return;
}

$modified = (int) filemtime($cache_file);

header('X-Cache-Enabled: B2B SEO Advanced Page Cache');
header('X-Cache-Status: HIT');
header('Cache-Control: public, max-age=' . $ttl);
header('Age: ' . max(0, time() - $modified));
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
header('Expires: ' . gmdate('D, d M Y H:i:s', $modified + $ttl) . ' GMT');
readfile($cache_file);
exit;
PHP;

    return file_put_contents(li_seo_advanced_cache_path(), $dropin, LOCK_EX) !== false;
}

function li_seo_find_wp_config_path(): string
{
    $candidates = [
        trailingslashit(ABSPATH) . 'wp-config.php',
        dirname(trailingslashit(ABSPATH)) . '/wp-config.php',
    ];

    foreach ($candidates as $candidate) {
        if (is_readable($candidate)) {
            return $candidate;
        }
    }

    return '';
}

function li_seo_enable_wp_cache_constant(): string
{
    if (defined('WP_CACHE') && WP_CACHE) {
        return 'wp_cache_already_enabled';
    }

    $path = li_seo_find_wp_config_path();

    if ($path === '' || !is_readable($path) || !is_writable($path)) {
        return 'wp_cache_failed';
    }

    $contents = (string) file_get_contents($path);

    if ($contents === '') {
        return 'wp_cache_failed';
    }

    $updated = $contents;

    if (preg_match('/define\s*\(\s*[\'"]WP_CACHE[\'"]\s*,\s*(true|false|0|1)\s*\)\s*;/i', $contents)) {
        $updated = preg_replace(
            '/define\s*\(\s*[\'"]WP_CACHE[\'"]\s*,\s*(true|false|0|1)\s*\)\s*;/i',
            "define('WP_CACHE', true);",
            $contents,
            1
        );
    } else {
        $define = "define('WP_CACHE', true); // Added by B2B SEO cache\n";
        $markers = [
            "/* That's all, stop editing!",
            "/* That's all, stop editing! Happy publishing. */",
            "require_once ABSPATH . 'wp-settings.php';",
            'require_once(ABSPATH . \'wp-settings.php\');',
            'require_once ABSPATH . "wp-settings.php";',
        ];

        foreach ($markers as $marker) {
            $position = strpos($contents, $marker);

            if ($position !== false) {
                $updated = substr($contents, 0, $position) . $define . substr($contents, $position);
                break;
            }
        }

        if ($updated === $contents) {
            $updated = rtrim($contents) . "\n\n" . $define;
        }
    }

    if (!is_string($updated) || $updated === $contents) {
        return 'wp_cache_failed';
    }

    $backup_path = $path . '.b2b-seo-backup-' . gmdate('YmdHis');

    if (!copy($path, $backup_path)) {
        return 'wp_cache_failed';
    }

    return file_put_contents($path, $updated, LOCK_EX) !== false
        ? 'wp_cache_enabled'
        : 'wp_cache_failed';
}

function li_seo_install_htaccess_rules(): bool
{
    $path = trailingslashit(ABSPATH) . '.htaccess';
    $existing = is_readable($path) ? (string) file_get_contents($path) : '';
    $rules = <<<'HTACCESS'
# BEGIN B2B SEO Browser Cache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType text/javascript "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType font/woff "access plus 1 year"
    ExpiresByType font/woff2 "access plus 1 year"
</IfModule>
<IfModule mod_headers.c>
    <FilesMatch "\.(css|js|jpg|jpeg|png|gif|webp|svg|woff|woff2)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>
</IfModule>
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json image/svg+xml
</IfModule>
# END B2B SEO Browser Cache
HTACCESS;

    $pattern = '/# BEGIN B2B SEO Browser Cache.*?# END B2B SEO Browser Cache\s*/s';
    $updated = preg_match($pattern, $existing)
        ? preg_replace($pattern, $rules . "\n", $existing)
        : rtrim($existing) . "\n\n" . $rules . "\n";

    return is_string($updated) && file_put_contents($path, $updated, LOCK_EX) !== false;
}

function li_seo_purge_cache_for_post(int $post_id): void
{
    li_seo_purge_all_cache();
}

function li_seo_purge_all_cache(): void
{
    update_option(LI_SEO_CACHE_VERSION_OPTION, (string) time());

    $dir = li_seo_cache_dir();

    if (!is_dir($dir)) {
        return;
    }

    foreach (glob(trailingslashit($dir) . '*.html') ?: [] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }

    li_seo_write_cache_config();
}

function li_seo_render_admin_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $settings = li_seo_get_settings();
    $post_types = li_seo_get_supported_public_post_types();
    $taxonomies = li_seo_get_supported_public_taxonomies();
    $cache_dir = li_seo_cache_dir();
    $cache_files = is_dir($cache_dir) ? count(glob(trailingslashit($cache_dir) . '*.html') ?: []) : 0;
    $purge_url = wp_nonce_url(admin_url('admin.php?page=b2b-seo&li_seo_action=purge_cache'), 'li_seo_purge_cache');
    $dropin_url = wp_nonce_url(admin_url('admin.php?page=b2b-seo&li_seo_action=install_dropin'), 'li_seo_install_dropin');
    $htaccess_url = wp_nonce_url(admin_url('admin.php?page=b2b-seo&li_seo_action=install_htaccess'), 'li_seo_install_htaccess');
    $wp_cache_url = wp_nonce_url(admin_url('admin.php?page=b2b-seo&li_seo_action=enable_wp_cache'), 'li_seo_enable_wp_cache');
    $dropin_installed = li_seo_is_advanced_cache_installed();
    $wp_cache_enabled = defined('WP_CACHE') && WP_CACHE;
    $wp_config_path = li_seo_find_wp_config_path();
    $wp_config_writable = $wp_config_path !== '' && is_writable($wp_config_path);
    ?>
    <div class="wrap li-settings-page li-seo-page">
        <div class="li-settings-hero">
            <div>
                <p class="li-settings-kicker"><?php esc_html_e('Search and Performance', 'b2b-industrial'); ?></p>
                <h1><?php esc_html_e('B2B SEO', 'b2b-industrial'); ?></h1>
                <p><?php esc_html_e('Manage metadata, social sharing, structured data, sitemap discovery, robots directives, and anonymous page caching for pages, products, archives, and content.', 'b2b-industrial'); ?></p>
            </div>
            <div class="li-settings-summary">
                <div>
                    <span><?php esc_html_e('SEO', 'b2b-industrial'); ?></span>
                    <strong><?php echo esc_html(!empty($settings['enabled']) ? __('Enabled', 'b2b-industrial') : __('Disabled', 'b2b-industrial')); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Cache Files', 'b2b-industrial'); ?></span>
                    <strong><?php echo esc_html(number_format_i18n($cache_files)); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Sitemap', 'b2b-industrial'); ?></span>
                    <strong><?php echo esc_html(!empty($settings['enable_xml_sitemap']) ? __('Active', 'b2b-industrial') : __('Off', 'b2b-industrial')); ?></strong>
                </div>
            </div>
        </div>

        <nav class="li-admin-tabs" data-li-admin-tabs=".li-seo-page" data-li-tabs-key="li-seo-admin-tab" aria-label="<?php esc_attr_e('SEO settings sections', 'b2b-industrial'); ?>">
            <button class="li-admin-tab" type="button" data-li-tab-target="seo-metadata"><?php esc_html_e('Metadata', 'b2b-industrial'); ?></button>
            <button class="li-admin-tab" type="button" data-li-tab-target="seo-social"><?php esc_html_e('Social & Schema', 'b2b-industrial'); ?></button>
            <button class="li-admin-tab" type="button" data-li-tab-target="seo-indexing"><?php esc_html_e('Indexing', 'b2b-industrial'); ?></button>
            <button class="li-admin-tab" type="button" data-li-tab-target="seo-cache"><?php esc_html_e('Page Cache', 'b2b-industrial'); ?></button>
            <button class="li-admin-tab" type="button" data-li-tab-target="seo-tools"><?php esc_html_e('Tools', 'b2b-industrial'); ?></button>
        </nav>

        <form class="li-settings-form li-seo-settings-form li-admin-tab-panels" method="post" action="options.php">
            <?php settings_fields('li_seo_settings'); ?>

            <section class="li-admin-tab-panel" data-li-tab-panel="seo-metadata">
                <h2><?php esc_html_e('Global Metadata', 'b2b-industrial'); ?></h2>
                <p><?php esc_html_e('Control default titles and descriptions used when individual content does not provide overrides.', 'b2b-industrial'); ?></p>
                <table class="form-table" role="presentation"><tbody>
                    <?php li_seo_render_checkbox_row('enabled', __('Enable SEO output', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_text_row('site_title_pattern', __('Title pattern', 'b2b-industrial'), $settings, '%title% | %site%'); ?>
                    <?php li_seo_render_text_row('home_title', __('Home title', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_textarea_row('default_description', __('Default description', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_text_row('separator', __('Title separator', 'b2b-industrial'), $settings); ?>
                </tbody></table>
                <?php submit_button(__('Save SEO Settings', 'b2b-industrial')); ?>
            </section>

            <section class="li-admin-tab-panel" data-li-tab-panel="seo-social">
                <h2><?php esc_html_e('Social and Schema', 'b2b-industrial'); ?></h2>
                <p><?php esc_html_e('Add share metadata and JSON-LD structured data for the organization, website, pages, articles, and products.', 'b2b-industrial'); ?></p>
                <table class="form-table" role="presentation"><tbody>
                    <?php li_seo_render_text_row('organization_name', __('Organization name', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_media_row('organization_logo_id', __('Organization logo', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_media_row('social_image_id', __('Default social image', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_text_row('twitter_site', __('Twitter/X site handle', 'b2b-industrial'), $settings, '@b2b'); ?>
                    <?php li_seo_render_text_row('facebook_app_id', __('Facebook app ID', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_checkbox_row('enable_open_graph', __('Enable Open Graph tags', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_checkbox_row('enable_twitter_cards', __('Enable Twitter cards', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_checkbox_row('enable_json_ld', __('Enable JSON-LD schema', 'b2b-industrial'), $settings); ?>
                </tbody></table>
                <?php submit_button(__('Save SEO Settings', 'b2b-industrial')); ?>
            </section>

            <section class="li-admin-tab-panel" data-li-tab-panel="seo-indexing">
                <h2><?php esc_html_e('Indexing and Discovery', 'b2b-industrial'); ?></h2>
                <p><?php esc_html_e('Control canonical URLs, robots directives, XML sitemap content, and robots.txt sitemap discovery.', 'b2b-industrial'); ?></p>
                <table class="form-table" role="presentation"><tbody>
                    <?php li_seo_render_checkbox_row('enable_canonical', __('Enable canonical URLs', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_checkbox_row('enable_robots_meta', __('Enable robots meta tags', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_checkbox_row('noindex_search', __('Noindex search result pages', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_checkbox_row('noindex_404', __('Noindex 404 pages', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_checkbox_row('noindex_private_docs', __('Noindex private documents', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_checkbox_row('enable_xml_sitemap', __('Enable XML sitemap', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_checkbox_row('enable_robots_txt', __('Add sitemap to robots.txt', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_checklist_row('sitemap_post_types', __('Sitemap post types', 'b2b-industrial'), $settings, $post_types); ?>
                    <?php li_seo_render_checklist_row('sitemap_taxonomies', __('Sitemap taxonomies', 'b2b-industrial'), $settings, $taxonomies); ?>
                </tbody></table>
                <?php submit_button(__('Save SEO Settings', 'b2b-industrial')); ?>
            </section>

            <section class="li-admin-tab-panel" data-li-tab-panel="seo-cache">
                <h2><?php esc_html_e('Page Caching', 'b2b-industrial'); ?></h2>
                <p><?php esc_html_e('Cache anonymous full-page HTML for fast repeat visits. Logged-in users, carts, checkouts, account pages, previews, searches, feeds, and query-string URLs are bypassed.', 'b2b-industrial'); ?></p>
                <table class="form-table" role="presentation"><tbody>
                    <?php li_seo_render_checkbox_row('enable_page_cache', __('Enable page caching', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_number_row('cache_ttl', __('Cache TTL seconds', 'b2b-industrial'), $settings, 300, DAY_IN_SECONDS); ?>
                    <?php li_seo_render_checkbox_row('cache_mobile_separately', __('Keep separate mobile cache files', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_checkbox_row('cache_products', __('Cache product pages', 'b2b-industrial'), $settings); ?>
                    <?php li_seo_render_checkbox_row('cache_archives', __('Cache archive and taxonomy pages', 'b2b-industrial'), $settings); ?>
                </tbody></table>
                <?php submit_button(__('Save SEO Settings', 'b2b-industrial')); ?>
            </section>
        </form>

        <section class="li-admin-panel li-admin-tab-panel" data-li-tab-panel="seo-tools">
            <h2><?php esc_html_e('SEO Tools and Status', 'b2b-industrial'); ?></h2>
            <p><?php esc_html_e('Inspect generated assets and manage page cache integrations.', 'b2b-industrial'); ?></p>
            <p class="li-seo-actions">
                <a class="button button-secondary" href="<?php echo esc_url(home_url('/li-sitemap.xml')); ?>" target="_blank" rel="noopener"><?php esc_html_e('View Sitemap', 'b2b-industrial'); ?></a>
                <a class="button button-secondary" href="<?php echo esc_url($purge_url); ?>"><?php esc_html_e('Purge Page Cache', 'b2b-industrial'); ?></a>
                <a class="button button-secondary" href="<?php echo esc_url($dropin_url); ?>"><?php esc_html_e('Install Advanced Cache Drop-in', 'b2b-industrial'); ?></a>
                <a class="button button-secondary" href="<?php echo esc_url($wp_cache_url); ?>"><?php esc_html_e('Enable WP_CACHE', 'b2b-industrial'); ?></a>
                <a class="button button-secondary" href="<?php echo esc_url($htaccess_url); ?>"><?php esc_html_e('Install Apache Cache Rules', 'b2b-industrial'); ?></a>
            </p>

            <div class="li-seo-status-grid">
                <div class="li-seo-status-card">
                    <span><?php esc_html_e('Advanced Drop-in', 'b2b-industrial'); ?></span>
                    <strong><?php echo esc_html($dropin_installed ? __('Installed', 'b2b-industrial') : __('Not Installed', 'b2b-industrial')); ?></strong>
                </div>
                <div class="li-seo-status-card">
                    <span><?php esc_html_e('WP_CACHE', 'b2b-industrial'); ?></span>
                    <strong><?php echo esc_html($wp_cache_enabled ? __('Enabled', 'b2b-industrial') : ($wp_config_writable ? __('Ready to Enable', 'b2b-industrial') : __('Config Not Writable', 'b2b-industrial'))); ?></strong>
                </div>
                <div class="li-seo-status-card">
                    <span><?php esc_html_e('Cache Directory', 'b2b-industrial'); ?></span>
                    <strong><?php echo esc_html(is_writable($cache_dir) || wp_mkdir_p($cache_dir) ? __('Writable', 'b2b-industrial') : __('Not Writable', 'b2b-industrial')); ?></strong>
                </div>
            </div>
        </section>
    </div>
    <?php
}

function li_seo_render_text_row(string $key, string $label, array $settings, string $placeholder = ''): void
{
    ?>
    <tr>
        <th scope="row"><label for="li_seo_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
        <td><input class="regular-text" id="li_seo_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(LI_SEO_SETTINGS_OPTION); ?>[<?php echo esc_attr($key); ?>]" type="text" value="<?php echo esc_attr((string) ($settings[$key] ?? '')); ?>" placeholder="<?php echo esc_attr($placeholder); ?>"></td>
    </tr>
    <?php
}

function li_seo_render_number_row(string $key, string $label, array $settings, int $min, int $max): void
{
    ?>
    <tr>
        <th scope="row"><label for="li_seo_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
        <td><input class="small-text" id="li_seo_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(LI_SEO_SETTINGS_OPTION); ?>[<?php echo esc_attr($key); ?>]" type="number" min="<?php echo esc_attr((string) $min); ?>" max="<?php echo esc_attr((string) $max); ?>" value="<?php echo esc_attr((string) ($settings[$key] ?? '')); ?>"></td>
    </tr>
    <?php
}

function li_seo_render_textarea_row(string $key, string $label, array $settings): void
{
    ?>
    <tr>
        <th scope="row"><label for="li_seo_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
        <td><textarea class="large-text" id="li_seo_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(LI_SEO_SETTINGS_OPTION); ?>[<?php echo esc_attr($key); ?>]" rows="3"><?php echo esc_textarea((string) ($settings[$key] ?? '')); ?></textarea></td>
    </tr>
    <?php
}

function li_seo_render_checkbox_row(string $key, string $label, array $settings): void
{
    ?>
    <tr>
        <th scope="row"><?php echo esc_html($label); ?></th>
        <td><label><input type="checkbox" name="<?php echo esc_attr(LI_SEO_SETTINGS_OPTION); ?>[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($settings[$key])); ?>> <?php esc_html_e('Enabled', 'b2b-industrial'); ?></label></td>
    </tr>
    <?php
}

function li_seo_render_checklist_row(string $key, string $label, array $settings, array $items): void
{
    $selected = array_map('sanitize_key', (array) ($settings[$key] ?? []));
    ?>
    <tr>
        <th scope="row"><?php echo esc_html($label); ?></th>
        <td class="li-seo-checklist">
            <?php foreach ($items as $item) : ?>
                <label><input type="checkbox" name="<?php echo esc_attr(LI_SEO_SETTINGS_OPTION); ?>[<?php echo esc_attr($key); ?>][]" value="<?php echo esc_attr($item); ?>" <?php checked(in_array($item, $selected, true)); ?>> <?php echo esc_html($item); ?></label>
            <?php endforeach; ?>
        </td>
    </tr>
    <?php
}

function li_seo_render_media_row(string $key, string $label, array $settings): void
{
    $image_id = absint($settings[$key] ?? 0);
    $label_text = $image_id ? get_the_title($image_id) : __('No image selected', 'b2b-industrial');
    ?>
    <tr>
        <th scope="row"><?php echo esc_html($label); ?></th>
        <td data-li-media-picker>
            <input type="hidden" name="<?php echo esc_attr(LI_SEO_SETTINGS_OPTION); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) $image_id); ?>" data-li-media-id>
            <span data-li-media-label><?php echo esc_html($label_text); ?></span><br>
            <button type="button" class="button" data-li-media-select data-li-media-title="<?php esc_attr_e('Select Image', 'b2b-industrial'); ?>" data-li-media-button="<?php esc_attr_e('Use This Image', 'b2b-industrial'); ?>" data-li-media-type="image"><?php esc_html_e('Select Image', 'b2b-industrial'); ?></button>
            <button type="button" class="button" data-li-media-remove <?php echo $image_id ? '' : 'hidden'; ?>><?php esc_html_e('Remove', 'b2b-industrial'); ?></button>
        </td>
    </tr>
    <?php
}
