<?php
/**
 * Plugin Name: B2B PDF Flipbook
 * Description: Local PDF flipbook viewer for B2B catalogues and technical documents.
 * Version: 1.0.3
 * Author: B2B Laboratories
 * Text Domain: b2b-pdf-flipbook
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('B2B_FLIPBOOK_VERSION', '1.0.3');
define('B2B_FLIPBOOK_PATH', plugin_dir_path(__FILE__));
define('B2B_FLIPBOOK_URL', b2b_flipbook_asset_url());

add_action('init', 'b2b_flipbook_register_post_type');
add_action('admin_menu', 'b2b_flipbook_register_admin_menu', 20);
add_action('add_meta_boxes', 'b2b_flipbook_add_meta_boxes');
add_action('save_post_b2b_flipbook', 'b2b_flipbook_save_meta');
add_action('admin_enqueue_scripts', 'b2b_flipbook_admin_assets');
add_action('wp_enqueue_scripts', 'b2b_flipbook_frontend_assets');
add_action('wp_ajax_b2b_flipbook_manifest', 'b2b_flipbook_ajax_manifest');
add_action('wp_ajax_nopriv_b2b_flipbook_manifest', 'b2b_flipbook_ajax_manifest');
add_shortcode('b2b_pdf_flipbook', 'b2b_flipbook_shortcode');
add_shortcode('b2b_pdf_catalogue_library', 'b2b_flipbook_library_shortcode');

add_filter('use_block_editor_for_post_type', static function (bool $use_block_editor, string $post_type): bool {
    return $post_type === 'b2b_flipbook' ? false : $use_block_editor;
}, 10, 2);

add_filter('theme_b2b_flipbook_templates', static function (array $templates): array {
    return [];
});

register_activation_hook(__FILE__, static function (): void {
    b2b_flipbook_register_post_type();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, static function (): void {
    flush_rewrite_rules();
});

function b2b_flipbook_register_post_type(): void
{
    register_post_type('b2b_flipbook', [
        'labels' => [
            'name'               => __('PDF Flipbooks', 'b2b-pdf-flipbook'),
            'singular_name'      => __('PDF Flipbook', 'b2b-pdf-flipbook'),
            'add_new_item'       => __('Add New PDF Flipbook', 'b2b-pdf-flipbook'),
            'edit_item'          => __('Edit PDF Flipbook', 'b2b-pdf-flipbook'),
            'new_item'           => __('New PDF Flipbook', 'b2b-pdf-flipbook'),
            'view_item'          => __('View PDF Flipbook', 'b2b-pdf-flipbook'),
            'search_items'       => __('Search PDF Flipbooks', 'b2b-pdf-flipbook'),
            'not_found'          => __('No PDF flipbooks found.', 'b2b-pdf-flipbook'),
            'not_found_in_trash' => __('No PDF flipbooks found in Trash.', 'b2b-pdf-flipbook'),
        ],
        'public'       => true,
        'show_in_menu' => false,
        'show_in_rest' => false,
        'menu_icon'    => 'dashicons-book',
        'supports'     => ['title', 'excerpt', 'thumbnail'],
        'rewrite'      => [
            'slug' => 'flipbook',
        ],
    ]);

    register_post_meta('b2b_flipbook', '_b2b_flipbook_pdf_id', [
        'type'              => 'integer',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'absint',
        'auth_callback'     => static fn (): bool => current_user_can('edit_posts'),
    ]);

    register_post_meta('b2b_flipbook', '_b2b_flipbook_featured', [
        'type'              => 'boolean',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => static fn (mixed $value): bool => !empty($value),
        'auth_callback'     => static fn (): bool => current_user_can('edit_posts'),
    ]);
}

function b2b_flipbook_register_admin_menu(): void
{
    add_submenu_page(
        'b2b',
        __('PDF Flipbooks', 'b2b-pdf-flipbook'),
        __('PDF Flipbooks', 'b2b-pdf-flipbook'),
        'edit_posts',
        'edit.php?post_type=b2b_flipbook'
    );
}

function b2b_flipbook_asset_url(): string
{
    $theme_dir = wp_normalize_path(get_template_directory());
    $plugin_dir = wp_normalize_path(__DIR__);

    if (str_starts_with($plugin_dir, $theme_dir)) {
        $relative = ltrim(substr($plugin_dir, strlen($theme_dir)), '/');

        return trailingslashit(get_theme_file_uri($relative));
    }

    return plugin_dir_url(__FILE__);
}

function b2b_flipbook_add_meta_boxes(): void
{
    add_meta_box(
        'b2b_flipbook_pdf',
        __('Flipbook Builder', 'b2b-pdf-flipbook'),
        'b2b_flipbook_render_pdf_meta_box',
        'b2b_flipbook',
        'normal',
        'high'
    );

    add_meta_box(
        'b2b_flipbook_order',
        __('Catalogue Order', 'b2b-pdf-flipbook'),
        'b2b_flipbook_render_order_meta_box',
        'b2b_flipbook',
        'side',
        'default'
    );
}

add_filter('enter_title_here', static function (string $placeholder, WP_Post $post): string {
    if ($post->post_type !== 'b2b_flipbook') {
        return $placeholder;
    }

    return __('Catalogue or document title', 'b2b-pdf-flipbook');
}, 10, 2);

function b2b_flipbook_render_pdf_meta_box(WP_Post $post): void
{
    wp_nonce_field('b2b_flipbook_save_pdf', 'b2b_flipbook_pdf_nonce');

    $pdf_id = (int) get_post_meta($post->ID, '_b2b_flipbook_pdf_id', true);
    $featured = (bool) get_post_meta($post->ID, '_b2b_flipbook_featured', true);
    $pdf_title = $pdf_id ? get_the_title($pdf_id) : __('No PDF selected', 'b2b-pdf-flipbook');
    $pdf_url = $pdf_id ? wp_get_attachment_url($pdf_id) : '';
    $status = $pdf_id ? __('Ready', 'b2b-pdf-flipbook') : __('Needs PDF', 'b2b-pdf-flipbook');
    ?>
    <div class="li-editor-panel b2b-flipbook-builder">
        <div class="li-editor-panel__intro">
            <span class="dashicons dashicons-book"></span>
            <div>
                <h2><?php esc_html_e('Build A Local PDF Flipbook', 'b2b-pdf-flipbook'); ?></h2>
                <p><?php esc_html_e('Select the source PDF, decide whether it should be featured, then publish. B2B renders the pages locally for the public catalogue viewer.', 'b2b-pdf-flipbook'); ?></p>
            </div>
        </div>

        <div class="b2b-flipbook-builder__grid">
            <section class="b2b-flipbook-builder__main">
                <div class="li-editor-panel__selected-file">
                    <span data-b2b-flipbook-pdf-status><?php echo esc_html($status); ?></span>
                    <strong data-b2b-flipbook-pdf-label><?php echo esc_html($pdf_title); ?></strong>
                    <a
                        href="<?php echo esc_url((string) $pdf_url); ?>"
                        target="_blank"
                        rel="noopener"
                        data-b2b-flipbook-pdf-open
                        <?php echo $pdf_url ? '' : 'hidden'; ?>
                    >
                        <?php esc_html_e('Open PDF', 'b2b-pdf-flipbook'); ?>
                    </a>
                </div>

                <input type="hidden" name="b2b_flipbook_pdf_id" value="<?php echo esc_attr((string) $pdf_id); ?>" data-b2b-flipbook-pdf-id>

                <div class="li-editor-panel__actions">
                    <button type="button" class="button button-primary" data-b2b-flipbook-select-pdf>
                        <?php esc_html_e('Select / Upload PDF', 'b2b-pdf-flipbook'); ?>
                    </button>
                    <button type="button" class="button button-secondary" data-b2b-flipbook-remove-pdf <?php echo $pdf_id ? '' : 'hidden'; ?>>
                        <?php esc_html_e('Remove PDF', 'b2b-pdf-flipbook'); ?>
                    </button>
                </div>

                <label class="li-editor-panel__toggle">
                    <input type="checkbox" name="b2b_flipbook_featured" value="1" <?php checked($featured); ?>>
                    <span><?php esc_html_e('Feature this flipbook in catalogue lists.', 'b2b-pdf-flipbook'); ?></span>
                </label>
            </section>

            <aside class="b2b-flipbook-builder__side">
                <div class="li-editor-panel__notice">
                    <?php esc_html_e('Local rendering uses Poppler pdftoppm first, then Imagick with Ghostscript when available. No external flipbook service is used.', 'b2b-pdf-flipbook'); ?>
                </div>

                <div class="li-editor-panel__shortcodes">
                    <span><?php esc_html_e('Embed Shortcodes', 'b2b-pdf-flipbook'); ?></span>
                    <code>[b2b_pdf_flipbook id="<?php echo esc_attr((string) $post->ID); ?>"]</code>
                    <code>[b2b_pdf_catalogue_library]</code>
                </div>

                <ol class="b2b-flipbook-builder__steps">
                    <li><?php esc_html_e('Name the flipbook.', 'b2b-pdf-flipbook'); ?></li>
                    <li><?php esc_html_e('Select or upload the PDF.', 'b2b-pdf-flipbook'); ?></li>
                    <li><?php esc_html_e('Publish when it is ready for the catalogue.', 'b2b-pdf-flipbook'); ?></li>
                </ol>
            </aside>
        </div>
    </div>
    <?php
}

function b2b_flipbook_render_order_meta_box(WP_Post $post): void
{
    ?>
    <div class="b2b-flipbook-order-field">
        <label for="b2b_flipbook_menu_order"><?php esc_html_e('Display Order', 'b2b-pdf-flipbook'); ?></label>
        <input
            type="number"
            id="b2b_flipbook_menu_order"
            name="b2b_flipbook_menu_order"
            value="<?php echo esc_attr((string) $post->menu_order); ?>"
            step="1"
        >
        <p><?php esc_html_e('Lower numbers appear first in catalogue lists. Items with the same order fall back to newest first.', 'b2b-pdf-flipbook'); ?></p>
    </div>
    <?php
}

function b2b_flipbook_save_meta(int $post_id): void
{
    if (!isset($_POST['b2b_flipbook_pdf_nonce'])) {
        return;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['b2b_flipbook_pdf_nonce'])), 'b2b_flipbook_save_pdf')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $pdf_id = isset($_POST['b2b_flipbook_pdf_id']) ? absint($_POST['b2b_flipbook_pdf_id']) : 0;

    if ($pdf_id && get_post_mime_type($pdf_id) !== 'application/pdf') {
        $pdf_id = 0;
    }

    update_post_meta($post_id, '_b2b_flipbook_pdf_id', $pdf_id);
    update_post_meta($post_id, '_b2b_flipbook_featured', !empty($_POST['b2b_flipbook_featured']) ? '1' : '0');
    delete_post_meta($post_id, '_wp_page_template');

    if (isset($_POST['b2b_flipbook_menu_order'])) {
        $menu_order = (int) $_POST['b2b_flipbook_menu_order'];

        if ((int) get_post_field('menu_order', $post_id) !== $menu_order) {
            remove_action('save_post_b2b_flipbook', 'b2b_flipbook_save_meta');
            wp_update_post([
                'ID'         => $post_id,
                'menu_order' => $menu_order,
            ]);
            add_action('save_post_b2b_flipbook', 'b2b_flipbook_save_meta');
        }
    }
}

function b2b_flipbook_admin_assets(string $hook): void
{
    $screen = get_current_screen();

    if (!$screen || $screen->post_type !== 'b2b_flipbook') {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script(
        'b2b-flipbook-admin',
        B2B_FLIPBOOK_URL . 'assets/admin.js',
        ['media-editor', 'media-views'],
        B2B_FLIPBOOK_VERSION,
        true
    );
}

function b2b_flipbook_frontend_assets(): void
{
    wp_register_style(
        'b2b-flipbook',
        B2B_FLIPBOOK_URL . 'assets/flipbook.css',
        [],
        B2B_FLIPBOOK_VERSION
    );

    wp_register_script(
        'b2b-flipbook',
        B2B_FLIPBOOK_URL . 'assets/flipbook.js',
        [],
        B2B_FLIPBOOK_VERSION,
        true
    );
}

function b2b_flipbook_get_pdf_id(int $flipbook_id): int
{
    return (int) get_post_meta($flipbook_id, '_b2b_flipbook_pdf_id', true);
}

function b2b_flipbook_find(array $atts): ?WP_Post
{
    $id = absint($atts['id'] ?? 0);

    if ($id) {
        $post = get_post($id);
        return $post instanceof WP_Post && $post->post_type === 'b2b_flipbook' && $post->post_status === 'publish' ? $post : null;
    }

    $slug = sanitize_title((string) ($atts['slug'] ?? ''));

    if ($slug !== '') {
        $post = get_page_by_path($slug, OBJECT, 'b2b_flipbook');
        return $post instanceof WP_Post && $post->post_status === 'publish' ? $post : null;
    }

    $posts = get_posts([
        'post_type'      => 'b2b_flipbook',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_key'       => '_b2b_flipbook_featured',
        'meta_value'     => '1',
        'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
    ]);

    if (!$posts) {
        $posts = get_posts([
            'post_type'      => 'b2b_flipbook',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
        ]);
    }

    return !empty($posts[0]) && $posts[0] instanceof WP_Post ? $posts[0] : null;
}

function b2b_flipbook_shortcode(array $atts = []): string
{
    $atts = shortcode_atts([
        'id'         => 0,
        'slug'       => '',
        'show_title' => 'true',
    ], $atts, 'b2b_pdf_flipbook');

    $post = b2b_flipbook_find($atts);

    if (!$post) {
        return '<div class="b2b-flipbook-notice">' . esc_html__('No PDF flipbook is published yet.', 'b2b-pdf-flipbook') . '</div>';
    }

    $pdf_id = b2b_flipbook_get_pdf_id($post->ID);

    if (!$pdf_id) {
        return '<div class="b2b-flipbook-notice">' . esc_html__('This flipbook does not have a PDF assigned yet.', 'b2b-pdf-flipbook') . '</div>';
    }

    wp_enqueue_style('b2b-flipbook');
    wp_enqueue_script('b2b-flipbook');

    $nonce = wp_create_nonce('b2b_flipbook_manifest_' . $post->ID);
    $pdf_url = wp_get_attachment_url($pdf_id) ?: '';
    $show_title = filter_var($atts['show_title'], FILTER_VALIDATE_BOOLEAN);

    ob_start();
    ?>
    <section
        class="b2b-flipbook"
        data-flipbook-id="<?php echo esc_attr((string) $post->ID); ?>"
        data-flipbook-nonce="<?php echo esc_attr($nonce); ?>"
        data-flipbook-endpoint="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
        data-pdf-url="<?php echo esc_url($pdf_url); ?>"
    >
        <?php if ($show_title): ?>
            <header class="b2b-flipbook__header">
                <p class="b2b-flipbook__eyebrow"><?php esc_html_e('PDF Catalogue', 'b2b-pdf-flipbook'); ?></p>
                <h2><?php echo esc_html(get_the_title($post)); ?></h2>
                <?php if (has_excerpt($post)): ?>
                    <p><?php echo esc_html(get_the_excerpt($post)); ?></p>
                <?php endif; ?>
            </header>
        <?php endif; ?>

        <div class="b2b-flipbook__toolbar" aria-label="<?php esc_attr_e('PDF controls', 'b2b-pdf-flipbook'); ?>">
            <button type="button" data-flipbook-prev><?php esc_html_e('Previous', 'b2b-pdf-flipbook'); ?></button>
            <span data-flipbook-status><?php esc_html_e('Loading PDF...', 'b2b-pdf-flipbook'); ?></span>
            <button type="button" data-flipbook-next><?php esc_html_e('Next', 'b2b-pdf-flipbook'); ?></button>
            <button type="button" data-flipbook-fullscreen><?php esc_html_e('Fullscreen', 'b2b-pdf-flipbook'); ?></button>
            <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open PDF', 'b2b-pdf-flipbook'); ?></a>
        </div>

        <div class="b2b-flipbook__stage" data-flipbook-stage>
            <div class="b2b-flipbook__loading"><?php esc_html_e('Preparing local pages...', 'b2b-pdf-flipbook'); ?></div>
        </div>

        <div class="b2b-flipbook__thumbs" data-flipbook-thumbs></div>
    </section>
    <?php

    return (string) ob_get_clean();
}

function b2b_flipbook_library_shortcode(array $atts = []): string
{
    $atts = shortcode_atts([
        'limit' => 12,
    ], $atts, 'b2b_pdf_catalogue_library');

    $flipbooks = get_posts([
        'post_type'      => 'b2b_flipbook',
        'post_status'    => 'publish',
        'posts_per_page' => max(1, absint($atts['limit'])),
        'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
    ]);

    if (!$flipbooks) {
        return '<div class="b2b-flipbook-notice">' . esc_html__('No PDF catalogues are published yet.', 'b2b-pdf-flipbook') . '</div>';
    }

    wp_enqueue_style('b2b-flipbook');

    $output = '<div class="b2b-flipbook-library" role="list">';

    foreach ($flipbooks as $flipbook) {
        $pdf_id = b2b_flipbook_get_pdf_id((int) $flipbook->ID);
        $pdf_url = $pdf_id ? wp_get_attachment_url($pdf_id) : '';
        $permalink = get_permalink($flipbook) ?: '';
        $is_featured = (bool) get_post_meta((int) $flipbook->ID, '_b2b_flipbook_featured', true);

        $output .= '<article class="b2b-flipbook-library__item" role="listitem">';
        $output .= '<div class="b2b-flipbook-library__meta">';
        $output .= '<span>' . esc_html__('Catalogue', 'b2b-pdf-flipbook') . '</span>';

        if ($is_featured) {
            $output .= '<span>' . esc_html__('Featured', 'b2b-pdf-flipbook') . '</span>';
        }

        $output .= '</div>';
        $output .= '<h3>' . esc_html(get_the_title($flipbook)) . '</h3>';

        if (has_excerpt($flipbook)) {
            $output .= '<p class="b2b-flipbook-library__excerpt">' . esc_html(get_the_excerpt($flipbook)) . '</p>';
        }

        $output .= '<div class="b2b-flipbook-library__actions">';

        if ($permalink) {
            $output .= '<a class="b2b-flipbook-library__button b2b-flipbook-library__button--primary" href="' . esc_url($permalink) . '">' . esc_html__('View Catalogue', 'b2b-pdf-flipbook') . '</a>';
        }

        if ($pdf_url) {
            $output .= '<a class="b2b-flipbook-library__button b2b-flipbook-library__button--secondary" href="' . esc_url($pdf_url) . '" target="_blank" rel="noopener">' . esc_html__('Open PDF', 'b2b-pdf-flipbook') . '</a>';
        }

        $output .= '</div></article>';
    }

    $output .= '</div>';

    return $output;
}

function b2b_flipbook_ajax_manifest(): void
{
    $flipbook_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';

    if (!$flipbook_id || !wp_verify_nonce($nonce, 'b2b_flipbook_manifest_' . $flipbook_id)) {
        wp_send_json_error(['message' => __('Invalid flipbook request.', 'b2b-pdf-flipbook')], 403);
    }

    $post = get_post($flipbook_id);

    if (!$post instanceof WP_Post || $post->post_type !== 'b2b_flipbook' || $post->post_status !== 'publish') {
        wp_send_json_error(['message' => __('Flipbook not found.', 'b2b-pdf-flipbook')], 404);
    }

    $manifest = b2b_flipbook_get_manifest($flipbook_id);

    if (!$manifest['ok']) {
        wp_send_json_error($manifest, 200);
    }

    wp_send_json_success($manifest);
}

function b2b_flipbook_get_manifest(int $flipbook_id): array
{
    $pdf_id = b2b_flipbook_get_pdf_id($flipbook_id);
    $pdf_path = $pdf_id ? get_attached_file($pdf_id) : '';
    $pdf_url = $pdf_id ? wp_get_attachment_url($pdf_id) : '';

    if (!$pdf_id || !$pdf_path || !is_string($pdf_path) || !file_exists($pdf_path)) {
        return [
            'ok'      => false,
            'message' => __('The PDF file could not be found.', 'b2b-pdf-flipbook'),
            'pdfUrl'  => $pdf_url,
        ];
    }

    $cache = b2b_flipbook_cache_paths($pdf_id, $pdf_path);
    $manifest_path = $cache['path'] . '/manifest.json';

    if (file_exists($manifest_path)) {
        $manifest = json_decode((string) file_get_contents($manifest_path), true);
        if (is_array($manifest)) {
            return $manifest;
        }
    }

    $result = b2b_flipbook_render_pdf_pages_with_poppler($pdf_path, $cache['path'], $cache['url']);

    if (!$result['ok']) {
        $imagick_result = b2b_flipbook_render_pdf_pages($pdf_path, $cache['path'], $cache['url']);

        if ($imagick_result['ok']) {
            $result = $imagick_result;
        } else {
            $result['message'] = sprintf(
                /* translators: 1: Poppler render message, 2: Imagick render message */
                __('Local page rendering is not ready yet. Poppler said: %1$s Imagick said: %2$s', 'b2b-pdf-flipbook'),
                $result['message'],
                $imagick_result['message']
            );
        }
    }

    $manifest = [
        'ok'       => $result['ok'],
        'message'  => $result['message'],
        'title'    => get_the_title($flipbook_id),
        'pdfUrl'   => $pdf_url,
        'rendered' => $result['ok'],
        'pages'    => $result['pages'],
        'pageCount'=> count($result['pages']),
        'renderer' => $result['renderer'],
    ];

    if ($result['ok']) {
        wp_mkdir_p($cache['path']);
        file_put_contents($manifest_path, wp_json_encode($manifest));
    }

    return $manifest;
}

function b2b_flipbook_cache_paths(int $pdf_id, string $pdf_path): array
{
    $uploads = wp_upload_dir();
    $fingerprint = $pdf_id . '-' . md5((string) filemtime($pdf_path) . '|' . (string) filesize($pdf_path));
    $relative = 'b2b-flipbooks/' . $fingerprint;

    return [
        'path' => trailingslashit($uploads['basedir']) . $relative,
        'url'  => trailingslashit($uploads['baseurl']) . $relative,
    ];
}

function b2b_flipbook_collect_rendered_pages(string $cache_path, string $cache_url): array
{
    $files = glob(trailingslashit($cache_path) . 'page-*.jpg');

    if (!$files) {
        return [];
    }

    natsort($files);

    return array_map(
        static fn (string $file): string => trailingslashit($cache_url) . basename($file),
        array_values($files)
    );
}

function b2b_flipbook_function_available(string $function): bool
{
    if (!function_exists($function)) {
        return false;
    }

    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

    return !in_array($function, $disabled, true);
}

function b2b_flipbook_find_binary(array $candidates): string
{
    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
            return $candidate;
        }
    }

    if (!b2b_flipbook_function_available('exec')) {
        return '';
    }

    foreach ($candidates as $candidate) {
        if (
            !is_string($candidate)
            || $candidate === ''
            || str_contains($candidate, DIRECTORY_SEPARATOR)
            || !preg_match('/^[A-Za-z0-9._-]+$/', $candidate)
        ) {
            continue;
        }

        $command = PHP_OS_FAMILY === 'Windows'
            ? 'where ' . $candidate
            : 'command -v ' . escapeshellarg($candidate);
        $output = [];
        $exit_code = 1;
        @exec($command, $output, $exit_code);

        if ($exit_code === 0 && !empty($output[0])) {
            return trim((string) $output[0]);
        }
    }

    return '';
}

function b2b_flipbook_render_pdf_pages_with_poppler(string $pdf_path, string $cache_path, string $cache_url): array
{
    wp_mkdir_p($cache_path);

    $existing_pages = b2b_flipbook_collect_rendered_pages($cache_path, $cache_url);

    if ($existing_pages) {
        return [
            'ok'       => true,
            'message'  => '',
            'pages'    => $existing_pages,
            'renderer' => 'poppler-cache',
        ];
    }

    if (!b2b_flipbook_function_available('exec')) {
        return [
            'ok'       => false,
            'message'  => __('PHP exec() is disabled, so Poppler cannot be used.', 'b2b-pdf-flipbook'),
            'pages'    => [],
            'renderer' => 'poppler',
        ];
    }

    $binary = b2b_flipbook_find_binary((array) apply_filters('b2b_flipbook_pdftoppm_candidates', [
        'pdftoppm',
        'C:\\poppler\\Library\\bin\\pdftoppm.exe',
        'C:\\Program Files\\poppler\\Library\\bin\\pdftoppm.exe',
    ]));

    if ($binary === '') {
        return [
            'ok'       => false,
            'message'  => __('Poppler pdftoppm was not found on the server PATH.', 'b2b-pdf-flipbook'),
            'pages'    => [],
            'renderer' => 'poppler',
        ];
    }

    $prefix = trailingslashit($cache_path) . 'page';
    $command = escapeshellarg($binary)
        . ' -jpeg -r 150 -jpegopt quality=86 '
        . escapeshellarg($pdf_path)
        . ' '
        . escapeshellarg($prefix);
    $output = [];
    $exit_code = 1;
    @exec($command . ' 2>&1', $output, $exit_code);

    $pages = b2b_flipbook_collect_rendered_pages($cache_path, $cache_url);

    if ($exit_code !== 0 || !$pages) {
        return [
            'ok'       => false,
            'message'  => $output ? implode(' ', array_slice($output, 0, 3)) : __('Poppler did not produce page images.', 'b2b-pdf-flipbook'),
            'pages'    => [],
            'renderer' => 'poppler',
        ];
    }

    return [
        'ok'       => true,
        'message'  => '',
        'pages'    => $pages,
        'renderer' => 'poppler',
    ];
}

function b2b_flipbook_render_pdf_pages(string $pdf_path, string $cache_path, string $cache_url): array
{
    try {
        if (!class_exists('Imagick')) {
            return [
                'ok'       => false,
                'message'  => __('PHP Imagick is not installed.', 'b2b-pdf-flipbook'),
                'pages'    => [],
                'renderer' => 'imagick',
            ];
        }

        wp_mkdir_p($cache_path);

        $existing_pages = b2b_flipbook_collect_rendered_pages($cache_path, $cache_url);

        if ($existing_pages) {
            return [
                'ok'       => true,
                'message'  => '',
                'pages'    => $existing_pages,
                'renderer' => 'imagick-cache',
            ];
        }

        $probe = new Imagick();
        $probe->pingImage($pdf_path);
        $page_count = min((int) $probe->getNumberImages(), (int) apply_filters('b2b_flipbook_max_pages', 300));
        $probe->clear();
        $probe->destroy();

        if ($page_count < 1) {
            return [
                'ok'       => false,
                'message'  => __('No pages were detected in this PDF.', 'b2b-pdf-flipbook'),
                'pages'    => [],
                'renderer' => 'imagick',
            ];
        }

        $pages = [];

        for ($page = 0; $page < $page_count; $page++) {
            $filename = sprintf('page-%03d.jpg', $page + 1);
            $target = trailingslashit($cache_path) . $filename;

            if (!file_exists($target)) {
                $image = new Imagick();
                $image->setResolution(150, 150);
                $image->readImage($pdf_path . '[' . $page . ']');
                $image->setImageBackgroundColor('white');
                $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                $image->setImageFormat('jpeg');
                $image->setImageCompressionQuality(86);
                $image->stripImage();
                $image->writeImage($target);
                $image->clear();
                $image->destroy();
            }

            $pages[] = trailingslashit($cache_url) . $filename;
        }

        return [
            'ok'       => true,
            'message'  => '',
            'pages'    => $pages,
            'renderer' => 'imagick',
        ];
    } catch (Throwable $error) {
        return [
            'ok'       => false,
            'message'  => sprintf(
                /* translators: %s: rendering error message */
                __('PDF rendering failed locally: %s', 'b2b-pdf-flipbook'),
                $error->getMessage()
            ),
            'pages'    => [],
            'renderer' => 'imagick',
        ];
    }
}

add_filter('the_content', static function (string $content): string {
    if (!is_singular('b2b_flipbook') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    return do_shortcode('[b2b_pdf_flipbook id="' . get_the_ID() . '"]') . $content;
}, 8);

add_filter('render_block_core/post-content', static function (string $block_content, array $block): string {
    if (!is_singular('b2b_flipbook') || !is_main_query()) {
        return $block_content;
    }

    $post_id = get_the_ID();

    if (!$post_id) {
        return $block_content;
    }

    $viewer = do_shortcode('[b2b_pdf_flipbook id="' . $post_id . '"]');

    if (str_contains($block_content, 'class="b2b-flipbook"')) {
        return $block_content;
    }

    return $viewer . $block_content;
}, 8, 2);
