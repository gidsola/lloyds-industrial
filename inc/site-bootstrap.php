<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('after_switch_theme', 'li_bootstrap_default_site');

add_action('admin_init', function (): void {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_GET['li_reseed_site'])) {
        return;
    }

    delete_option('li_site_bootstrapped');

    li_bootstrap_default_site();

    wp_safe_redirect(admin_url('edit.php?post_type=page'));
    exit;
});

function li_bootstrap_default_site(): void
{
    if (get_option('li_site_bootstrapped')) {
        return;
    }

    $media = li_get_starter_media();
    $pages = li_bootstrap_create_pages($media);

    li_bootstrap_set_reading_options($pages);
    li_bootstrap_create_navigation($pages);
    li_bootstrap_create_woocommerce_pages($pages);

    update_option('li_site_bootstrapped', time());

    flush_rewrite_rules();
}

function li_bootstrap_create_pages(array $media = []): array
{
    $page_definitions = [
        'home' => [
            'title'    => 'Home',
            'slug'     => 'home',
            'template' => '',
            'content'  => li_get_starter_content('home', $media),
        ],

        'products' => [
            'title'    => 'Products',
            'slug'     => 'products',
            'template' => 'page-products',
            'content'  => li_get_starter_content('products'),
        ],

        'industries' => [
            'title'    => 'Industries',
            'slug'     => 'industries',
            'template' => 'page-industries',
            'content'  => li_get_starter_content('industries'),
        ],

        'documentation' => [
            'title'    => 'Documentation',
            'slug'     => 'documentation',
            'template' => 'page-documentation',
            'content'  => li_get_starter_content('documentation')
        ],

        'partners' => [
            'title'    => 'Partners',
            'slug'     => 'partners',
            'template' => 'page-partners',
            'content'  => li_get_starter_content('partners')
        ],

        'about' => [
            'title'    => 'About',
            'slug'     => 'about',
            'template' => 'page-about',
            'content'  => li_get_starter_content('about'),
        ],

        'contact' => [
            'title'    => 'Contact',
            'slug'     => 'contact',
            'template' => 'page-contact',
            'content'  => li_get_starter_content('contact')
        ],

        'account' => [
            'title'    => 'Account',
            'slug'     => 'account',
            'template' => 'page-account',
            'content'  => li_get_starter_content('account')
        ],

        'blog' => [
            'title'    => 'Blog',
            'slug'     => 'blog',
            'template' => '',
            'content'  => '<!-- wp:paragraph --><p>Company updates, product guidance and industrial resources.</p><!-- /wp:paragraph -->',
        ],
    ];

    $created_pages = [];

    foreach ($page_definitions as $key => $page) {
        $existing = get_page_by_path($page['slug'], OBJECT, 'page');

        if ($existing instanceof WP_Post) {
            $page_id = $existing->ID;

            wp_update_post([
                'ID'           => $page_id,
                'post_title'   => $page['title'],
                'post_content' => $page['content'],
            ]);
        } else {
            $page_id = wp_insert_post([
                'post_title'   => $page['title'],
                'post_name'    => $page['slug'],
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_content' => $page['content'],
            ], true);
        }

        if (is_wp_error($page_id) || !$page_id) {
            continue;
        }

        if (!empty($page['template'])) {
            update_post_meta((int) $page_id, '_wp_page_template', $page['template']);
        } else {
            delete_post_meta((int) $page_id, '_wp_page_template');
        }

        $created_pages[$key] = (int) $page_id;
    }

    return $created_pages;
}

function li_bootstrap_set_reading_options(array $pages): void
{
    if (!empty($pages['home'])) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $pages['home']);
    }

    if (!empty($pages['blog'])) {
        update_option('page_for_posts', $pages['blog']);
    }
}

function li_bootstrap_create_navigation(array $pages): void
{
    $existing_navigation = get_posts([
        'post_type'      => 'wp_navigation',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'name'           => 'primary-navigation',
    ]);

    if ($existing_navigation) {
        wp_update_post([
            'ID'           => $existing_navigation[0]->ID,
            'post_content' => li_bootstrap_get_navigation_content($pages),
        ]);

        update_option('li_primary_navigation_id', (int) $existing_navigation[0]->ID);
        return;
    }

    $navigation_id = wp_insert_post([
        'post_title'   => 'Primary Navigation',
        'post_name'    => 'primary-navigation',
        'post_type'    => 'wp_navigation',
        'post_status'  => 'publish',
        'post_content' => li_bootstrap_get_navigation_content($pages),
    ], true);

    if (!is_wp_error($navigation_id) && $navigation_id) {
        update_option('li_primary_navigation_id', (int) $navigation_id);
    }
}

function li_bootstrap_get_navigation_content(array $pages): string
{
    $content = '';

    $content .= li_navigation_link_block('Products', $pages['products'] ?? 0, [
        li_navigation_link_block('Lubricants', $pages['lubricants'] ?? 0),
        li_navigation_link_block('Degreasers', $pages['degreasers'] ?? 0),
        li_navigation_link_block('Corrosion Protection', $pages['corrosion-protection'] ?? 0),
    ]);

    $content .= li_navigation_link_block('Industries', $pages['industries'] ?? 0, [
        li_navigation_link_block('Utilities & Energy', $pages['utilities-energy'] ?? 0),
        li_navigation_link_block('Transportation', $pages['transportation'] ?? 0),
        li_navigation_link_block('Manufacturing', $pages['manufacturing'] ?? 0),
        li_navigation_link_block('Agriculture', $pages['agriculture'] ?? 0),
    ]);

    $content .= li_navigation_link_block('Documentation', $pages['documentation'] ?? 0, [
        li_navigation_link_block('SDS Library', $pages['sds-library'] ?? 0),
        li_navigation_link_block('Technical Data Sheets', $pages['technical-data-sheets'] ?? 0),
        li_navigation_link_block('Certifications', $pages['certifications'] ?? 0),
        li_navigation_link_block('Customer Account', $pages['account'] ?? 0),
    ]);

    $content .= li_navigation_link_block('Partners', $pages['partners'] ?? 0);
    $content .= li_navigation_link_block('About', $pages['about'] ?? 0);
    $content .= li_navigation_link_block('Contact', $pages['contact'] ?? 0);

    return $content;
}

function li_navigation_link_block(string $label, int $page_id, array $children = []): string
{
    if (!$page_id) {
        return '';
    }

    $url = get_permalink($page_id);

    if (!$url) {
        return '';
    }

    $attrs = [
        'label' => $label,
        'type'  => 'page',
        'id'    => $page_id,
        'url'   => $url,
        'kind'  => 'post-type',
    ];

    if (!$children) {
        return sprintf(
            '<!-- wp:navigation-link %s /-->' . "\n",
            wp_json_encode($attrs, JSON_UNESCAPED_SLASHES)
        );
    }

    return sprintf(
        '<!-- wp:navigation-submenu %s -->' . "\n" .
        implode('', $children) .
        '<!-- /wp:navigation-submenu -->' . "\n",
        wp_json_encode($attrs, JSON_UNESCAPED_SLASHES)
    );
}

function li_get_starter_content(string $file, array $media = []): string
{
    $path = get_template_directory() . '/starter-content/' . $file . '.html';

    if (!file_exists($path)) {
        return '';
    }

    $content = file_get_contents($path);

    if (!is_string($content)) {
        return '';
    }

    foreach ($media as $key => $value) {
        $content = str_replace('{{' . $key . '}}', (string) $value, $content);
    }

    return $content;
}

function li_import_starter_media(string $relative_path, string $title): int
{
    $theme_path = get_template_directory() . '/' . ltrim($relative_path, '/');

    if (!file_exists($theme_path)) {
        return 0;
    }

    $existing = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => 1,
        'meta_key'       => '_li_starter_media_source',
        'meta_value'     => $relative_path,
        'fields'         => 'ids',
    ]);

    if (!empty($existing[0])) {
        return (int) $existing[0];
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $upload = wp_upload_bits(
        basename($theme_path),
        null,
        file_get_contents($theme_path)
    );

    if (!empty($upload['error'])) {
        return 0;
    }

    $filetype = wp_check_filetype($upload['file']);

    $attachment_id = wp_insert_attachment([
        'post_mime_type' => $filetype['type'] ?: 'image/webp',
        'post_title'     => sanitize_text_field($title),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ], $upload['file']);

    if (is_wp_error($attachment_id) || !$attachment_id) {
        return 0;
    }

    $metadata = wp_generate_attachment_metadata((int) $attachment_id, $upload['file']);
    wp_update_attachment_metadata((int) $attachment_id, $metadata);

    update_post_meta((int) $attachment_id, '_li_starter_media_source', $relative_path);

    return (int) $attachment_id;
}

function li_get_starter_media(): array
{
    $hero_id = li_import_starter_media(
        'assets/images/industrial-hero.webp',
        'Industrial Hero'
    );

    return [
        'hero_id'  => $hero_id,
        'hero_url' => $hero_id ? wp_get_attachment_url($hero_id) : '',
    ];
}

function li_bootstrap_create_woocommerce_pages(array $pages): void
{
    if (!class_exists('WooCommerce')) {
        return;
    }

    if (!empty($pages['products'])) {
        update_option('woocommerce_shop_page_id', $pages['products']);
    }

    if (!empty($pages['account'])) {
        update_option('woocommerce_myaccount_page_id', $pages['account']);
    }
}