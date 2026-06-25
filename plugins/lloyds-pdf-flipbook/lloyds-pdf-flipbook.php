<?php
/**
 * Plugin Name: Lloyds PDF Flipbook
 * Description: Local PDF flipbook viewer for Lloyds catalogues and technical documents.
 * Version: 1.0.3
 * Author: Lloyds Laboratories
 * Text Domain: lloyds-pdf-flipbook
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('LLOYDS_FLIPBOOK_VERSION', '1.0.3');
define('LLOYDS_FLIPBOOK_PATH', plugin_dir_path(__FILE__));
define('LLOYDS_FLIPBOOK_URL', lloyds_flipbook_asset_url());

add_action('init', 'lloyds_flipbook_register_post_type');
add_action('add_meta_boxes', 'lloyds_flipbook_add_meta_boxes');
add_action('save_post_lloyds_flipbook', 'lloyds_flipbook_save_meta');
add_action('admin_enqueue_scripts', 'lloyds_flipbook_admin_assets');
add_action('wp_enqueue_scripts', 'lloyds_flipbook_frontend_assets');
add_action('wp_ajax_lloyds_flipbook_manifest', 'lloyds_flipbook_ajax_manifest');
add_action('wp_ajax_nopriv_lloyds_flipbook_manifest', 'lloyds_flipbook_ajax_manifest');
add_shortcode('lloyds_pdf_flipbook', 'lloyds_flipbook_shortcode');
add_shortcode('lloyds_pdf_catalogue_library', 'lloyds_flipbook_library_shortcode');

register_activation_hook(__FILE__, static function (): void {
    lloyds_flipbook_register_post_type();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, static function (): void {
    flush_rewrite_rules();
});

function lloyds_flipbook_register_post_type(): void
{
    register_post_type('lloyds_flipbook', [
        'labels' => [
            'name'               => __('PDF Flipbooks', 'lloyds-pdf-flipbook'),
            'singular_name'      => __('PDF Flipbook', 'lloyds-pdf-flipbook'),
            'add_new_item'       => __('Add New PDF Flipbook', 'lloyds-pdf-flipbook'),
            'edit_item'          => __('Edit PDF Flipbook', 'lloyds-pdf-flipbook'),
            'new_item'           => __('New PDF Flipbook', 'lloyds-pdf-flipbook'),
            'view_item'          => __('View PDF Flipbook', 'lloyds-pdf-flipbook'),
            'search_items'       => __('Search PDF Flipbooks', 'lloyds-pdf-flipbook'),
            'not_found'          => __('No PDF flipbooks found.', 'lloyds-pdf-flipbook'),
            'not_found_in_trash' => __('No PDF flipbooks found in Trash.', 'lloyds-pdf-flipbook'),
        ],
        'public'       => true,
        'show_in_rest' => true,
        'menu_icon'    => 'dashicons-book',
        'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'page-attributes'],
        'rewrite'      => [
            'slug' => 'flipbook',
        ],
    ]);

    register_post_meta('lloyds_flipbook', '_lloyds_flipbook_pdf_id', [
        'type'              => 'integer',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'absint',
        'auth_callback'     => static fn (): bool => current_user_can('edit_posts'),
    ]);

    register_post_meta('lloyds_flipbook', '_lloyds_flipbook_featured', [
        'type'              => 'boolean',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => static fn (mixed $value): bool => !empty($value),
        'auth_callback'     => static fn (): bool => current_user_can('edit_posts'),
    ]);
}

function lloyds_flipbook_asset_url(): string
{
    $theme_dir = wp_normalize_path(get_template_directory());
    $plugin_dir = wp_normalize_path(__DIR__);

    if (str_starts_with($plugin_dir, $theme_dir)) {
        $relative = ltrim(substr($plugin_dir, strlen($theme_dir)), '/');

        return trailingslashit(get_theme_file_uri($relative));
    }

    return plugin_dir_url(__FILE__);
}

function lloyds_flipbook_add_meta_boxes(): void
{
    add_meta_box(
        'lloyds_flipbook_pdf',
        __('PDF File', 'lloyds-pdf-flipbook'),
        'lloyds_flipbook_render_pdf_meta_box',
        'lloyds_flipbook',
        'side',
        'high'
    );
}

function lloyds_flipbook_render_pdf_meta_box(WP_Post $post): void
{
    wp_nonce_field('lloyds_flipbook_save_pdf', 'lloyds_flipbook_pdf_nonce');

    $pdf_id = (int) get_post_meta($post->ID, '_lloyds_flipbook_pdf_id', true);
    $featured = (bool) get_post_meta($post->ID, '_lloyds_flipbook_featured', true);
    $pdf_title = $pdf_id ? get_the_title($pdf_id) : __('No PDF selected', 'lloyds-pdf-flipbook');
    ?>
    <p>
        <strong><?php esc_html_e('Selected PDF', 'lloyds-pdf-flipbook'); ?></strong><br>
        <span data-lloyds-flipbook-pdf-label><?php echo esc_html($pdf_title); ?></span>
    </p>
    <input type="hidden" name="lloyds_flipbook_pdf_id" value="<?php echo esc_attr((string) $pdf_id); ?>" data-lloyds-flipbook-pdf-id>
    <p>
        <button type="button" class="button" data-lloyds-flipbook-select-pdf>
            <?php esc_html_e('Select PDF', 'lloyds-pdf-flipbook'); ?>
        </button>
        <button type="button" class="button" data-lloyds-flipbook-remove-pdf <?php echo $pdf_id ? '' : 'hidden'; ?>>
            <?php esc_html_e('Remove', 'lloyds-pdf-flipbook'); ?>
        </button>
    </p>
    <p>
        <label>
            <input type="checkbox" name="lloyds_flipbook_featured" value="1" <?php checked($featured); ?>>
            <?php esc_html_e('Feature this flipbook in catalogue lists.', 'lloyds-pdf-flipbook'); ?>
        </label>
    </p>
    <p class="description">
        <?php esc_html_e('The PDF is rendered locally into page images when Poppler pdftoppm or Imagick with Ghostscript is available. No external flipbook service is used.', 'lloyds-pdf-flipbook'); ?>
    </p>
    <?php
}

function lloyds_flipbook_save_meta(int $post_id): void
{
    if (!isset($_POST['lloyds_flipbook_pdf_nonce'])) {
        return;
    }

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lloyds_flipbook_pdf_nonce'])), 'lloyds_flipbook_save_pdf')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $pdf_id = isset($_POST['lloyds_flipbook_pdf_id']) ? absint($_POST['lloyds_flipbook_pdf_id']) : 0;

    if ($pdf_id && get_post_mime_type($pdf_id) !== 'application/pdf') {
        $pdf_id = 0;
    }

    update_post_meta($post_id, '_lloyds_flipbook_pdf_id', $pdf_id);
    update_post_meta($post_id, '_lloyds_flipbook_featured', !empty($_POST['lloyds_flipbook_featured']) ? '1' : '0');
}

function lloyds_flipbook_admin_assets(string $hook): void
{
    $screen = get_current_screen();

    if (!$screen || $screen->post_type !== 'lloyds_flipbook') {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script(
        'lloyds-flipbook-admin',
        LLOYDS_FLIPBOOK_URL . 'assets/admin.js',
        ['media-editor', 'media-views'],
        LLOYDS_FLIPBOOK_VERSION,
        true
    );
}

function lloyds_flipbook_frontend_assets(): void
{
    wp_register_style(
        'lloyds-flipbook',
        LLOYDS_FLIPBOOK_URL . 'assets/flipbook.css',
        [],
        LLOYDS_FLIPBOOK_VERSION
    );

    wp_register_script(
        'lloyds-flipbook',
        LLOYDS_FLIPBOOK_URL . 'assets/flipbook.js',
        [],
        LLOYDS_FLIPBOOK_VERSION,
        true
    );
}

function lloyds_flipbook_get_pdf_id(int $flipbook_id): int
{
    return (int) get_post_meta($flipbook_id, '_lloyds_flipbook_pdf_id', true);
}

function lloyds_flipbook_find(array $atts): ?WP_Post
{
    $id = absint($atts['id'] ?? 0);

    if ($id) {
        $post = get_post($id);
        return $post instanceof WP_Post && $post->post_type === 'lloyds_flipbook' && $post->post_status === 'publish' ? $post : null;
    }

    $slug = sanitize_title((string) ($atts['slug'] ?? ''));

    if ($slug !== '') {
        $post = get_page_by_path($slug, OBJECT, 'lloyds_flipbook');
        return $post instanceof WP_Post && $post->post_status === 'publish' ? $post : null;
    }

    $posts = get_posts([
        'post_type'      => 'lloyds_flipbook',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_key'       => '_lloyds_flipbook_featured',
        'meta_value'     => '1',
        'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
    ]);

    if (!$posts) {
        $posts = get_posts([
            'post_type'      => 'lloyds_flipbook',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
        ]);
    }

    return !empty($posts[0]) && $posts[0] instanceof WP_Post ? $posts[0] : null;
}

function lloyds_flipbook_shortcode(array $atts = []): string
{
    $atts = shortcode_atts([
        'id'         => 0,
        'slug'       => '',
        'show_title' => 'true',
    ], $atts, 'lloyds_pdf_flipbook');

    $post = lloyds_flipbook_find($atts);

    if (!$post) {
        return '<div class="lloyds-flipbook-notice">' . esc_html__('No PDF flipbook is published yet.', 'lloyds-pdf-flipbook') . '</div>';
    }

    $pdf_id = lloyds_flipbook_get_pdf_id($post->ID);

    if (!$pdf_id) {
        return '<div class="lloyds-flipbook-notice">' . esc_html__('This flipbook does not have a PDF assigned yet.', 'lloyds-pdf-flipbook') . '</div>';
    }

    wp_enqueue_style('lloyds-flipbook');
    wp_enqueue_script('lloyds-flipbook');

    $nonce = wp_create_nonce('lloyds_flipbook_manifest_' . $post->ID);
    $pdf_url = wp_get_attachment_url($pdf_id) ?: '';
    $show_title = filter_var($atts['show_title'], FILTER_VALIDATE_BOOLEAN);

    ob_start();
    ?>
    <section
        class="lloyds-flipbook"
        data-flipbook-id="<?php echo esc_attr((string) $post->ID); ?>"
        data-flipbook-nonce="<?php echo esc_attr($nonce); ?>"
        data-flipbook-endpoint="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
        data-pdf-url="<?php echo esc_url($pdf_url); ?>"
    >
        <?php if ($show_title): ?>
            <header class="lloyds-flipbook__header">
                <p class="lloyds-flipbook__eyebrow"><?php esc_html_e('PDF Catalogue', 'lloyds-pdf-flipbook'); ?></p>
                <h2><?php echo esc_html(get_the_title($post)); ?></h2>
                <?php if (has_excerpt($post)): ?>
                    <p><?php echo esc_html(get_the_excerpt($post)); ?></p>
                <?php endif; ?>
            </header>
        <?php endif; ?>

        <div class="lloyds-flipbook__toolbar" aria-label="<?php esc_attr_e('PDF controls', 'lloyds-pdf-flipbook'); ?>">
            <button type="button" data-flipbook-prev><?php esc_html_e('Previous', 'lloyds-pdf-flipbook'); ?></button>
            <span data-flipbook-status><?php esc_html_e('Loading PDF...', 'lloyds-pdf-flipbook'); ?></span>
            <button type="button" data-flipbook-next><?php esc_html_e('Next', 'lloyds-pdf-flipbook'); ?></button>
            <button type="button" data-flipbook-fullscreen><?php esc_html_e('Fullscreen', 'lloyds-pdf-flipbook'); ?></button>
            <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" rel="noopener"><?php esc_html_e('Open PDF', 'lloyds-pdf-flipbook'); ?></a>
        </div>

        <div class="lloyds-flipbook__stage" data-flipbook-stage>
            <div class="lloyds-flipbook__loading"><?php esc_html_e('Preparing local pages...', 'lloyds-pdf-flipbook'); ?></div>
        </div>

        <div class="lloyds-flipbook__thumbs" data-flipbook-thumbs></div>
    </section>
    <?php

    return (string) ob_get_clean();
}

function lloyds_flipbook_library_shortcode(array $atts = []): string
{
    $atts = shortcode_atts([
        'limit' => 12,
    ], $atts, 'lloyds_pdf_catalogue_library');

    $flipbooks = get_posts([
        'post_type'      => 'lloyds_flipbook',
        'post_status'    => 'publish',
        'posts_per_page' => max(1, absint($atts['limit'])),
        'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
    ]);

    if (!$flipbooks) {
        return '<div class="lloyds-flipbook-notice">' . esc_html__('No PDF catalogues are published yet.', 'lloyds-pdf-flipbook') . '</div>';
    }

    wp_enqueue_style('lloyds-flipbook');

    $output = '<div class="lloyds-flipbook-library" role="list">';

    foreach ($flipbooks as $flipbook) {
        $pdf_id = lloyds_flipbook_get_pdf_id((int) $flipbook->ID);
        $pdf_url = $pdf_id ? wp_get_attachment_url($pdf_id) : '';
        $permalink = get_permalink($flipbook) ?: '';
        $is_featured = (bool) get_post_meta((int) $flipbook->ID, '_lloyds_flipbook_featured', true);

        $output .= '<article class="lloyds-flipbook-library__item" role="listitem">';
        $output .= '<div class="lloyds-flipbook-library__meta">';
        $output .= '<span>' . esc_html__('Catalogue', 'lloyds-pdf-flipbook') . '</span>';

        if ($is_featured) {
            $output .= '<span>' . esc_html__('Featured', 'lloyds-pdf-flipbook') . '</span>';
        }

        $output .= '</div>';
        $output .= '<h3>' . esc_html(get_the_title($flipbook)) . '</h3>';

        if (has_excerpt($flipbook)) {
            $output .= '<p class="lloyds-flipbook-library__excerpt">' . esc_html(get_the_excerpt($flipbook)) . '</p>';
        }

        $output .= '<div class="lloyds-flipbook-library__actions">';

        if ($permalink) {
            $output .= '<a class="lloyds-flipbook-library__button lloyds-flipbook-library__button--primary" href="' . esc_url($permalink) . '">' . esc_html__('View Catalogue', 'lloyds-pdf-flipbook') . '</a>';
        }

        if ($pdf_url) {
            $output .= '<a class="lloyds-flipbook-library__button lloyds-flipbook-library__button--secondary" href="' . esc_url($pdf_url) . '" target="_blank" rel="noopener">' . esc_html__('Open PDF', 'lloyds-pdf-flipbook') . '</a>';
        }

        $output .= '</div></article>';
    }

    $output .= '</div>';

    return $output;
}

function lloyds_flipbook_ajax_manifest(): void
{
    $flipbook_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';

    if (!$flipbook_id || !wp_verify_nonce($nonce, 'lloyds_flipbook_manifest_' . $flipbook_id)) {
        wp_send_json_error(['message' => __('Invalid flipbook request.', 'lloyds-pdf-flipbook')], 403);
    }

    $post = get_post($flipbook_id);

    if (!$post instanceof WP_Post || $post->post_type !== 'lloyds_flipbook' || $post->post_status !== 'publish') {
        wp_send_json_error(['message' => __('Flipbook not found.', 'lloyds-pdf-flipbook')], 404);
    }

    $manifest = lloyds_flipbook_get_manifest($flipbook_id);

    if (!$manifest['ok']) {
        wp_send_json_error($manifest, 200);
    }

    wp_send_json_success($manifest);
}

function lloyds_flipbook_get_manifest(int $flipbook_id): array
{
    $pdf_id = lloyds_flipbook_get_pdf_id($flipbook_id);
    $pdf_path = $pdf_id ? get_attached_file($pdf_id) : '';
    $pdf_url = $pdf_id ? wp_get_attachment_url($pdf_id) : '';

    if (!$pdf_id || !$pdf_path || !is_string($pdf_path) || !file_exists($pdf_path)) {
        return [
            'ok'      => false,
            'message' => __('The PDF file could not be found.', 'lloyds-pdf-flipbook'),
            'pdfUrl'  => $pdf_url,
        ];
    }

    $cache = lloyds_flipbook_cache_paths($pdf_id, $pdf_path);
    $manifest_path = $cache['path'] . '/manifest.json';

    if (file_exists($manifest_path)) {
        $manifest = json_decode((string) file_get_contents($manifest_path), true);
        if (is_array($manifest)) {
            return $manifest;
        }
    }

    $result = lloyds_flipbook_render_pdf_pages_with_poppler($pdf_path, $cache['path'], $cache['url']);

    if (!$result['ok']) {
        $imagick_result = lloyds_flipbook_render_pdf_pages($pdf_path, $cache['path'], $cache['url']);

        if ($imagick_result['ok']) {
            $result = $imagick_result;
        } else {
            $result['message'] = sprintf(
                /* translators: 1: Poppler render message, 2: Imagick render message */
                __('Local page rendering is not ready yet. Poppler said: %1$s Imagick said: %2$s', 'lloyds-pdf-flipbook'),
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

function lloyds_flipbook_cache_paths(int $pdf_id, string $pdf_path): array
{
    $uploads = wp_upload_dir();
    $fingerprint = $pdf_id . '-' . md5((string) filemtime($pdf_path) . '|' . (string) filesize($pdf_path));
    $relative = 'lloyds-flipbooks/' . $fingerprint;

    return [
        'path' => trailingslashit($uploads['basedir']) . $relative,
        'url'  => trailingslashit($uploads['baseurl']) . $relative,
    ];
}

function lloyds_flipbook_collect_rendered_pages(string $cache_path, string $cache_url): array
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

function lloyds_flipbook_function_available(string $function): bool
{
    if (!function_exists($function)) {
        return false;
    }

    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

    return !in_array($function, $disabled, true);
}

function lloyds_flipbook_find_binary(array $candidates): string
{
    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
            return $candidate;
        }
    }

    if (!lloyds_flipbook_function_available('exec')) {
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

function lloyds_flipbook_render_pdf_pages_with_poppler(string $pdf_path, string $cache_path, string $cache_url): array
{
    wp_mkdir_p($cache_path);

    $existing_pages = lloyds_flipbook_collect_rendered_pages($cache_path, $cache_url);

    if ($existing_pages) {
        return [
            'ok'       => true,
            'message'  => '',
            'pages'    => $existing_pages,
            'renderer' => 'poppler-cache',
        ];
    }

    if (!lloyds_flipbook_function_available('exec')) {
        return [
            'ok'       => false,
            'message'  => __('PHP exec() is disabled, so Poppler cannot be used.', 'lloyds-pdf-flipbook'),
            'pages'    => [],
            'renderer' => 'poppler',
        ];
    }

    $binary = lloyds_flipbook_find_binary((array) apply_filters('lloyds_flipbook_pdftoppm_candidates', [
        'pdftoppm',
        'C:\\poppler\\Library\\bin\\pdftoppm.exe',
        'C:\\Program Files\\poppler\\Library\\bin\\pdftoppm.exe',
    ]));

    if ($binary === '') {
        return [
            'ok'       => false,
            'message'  => __('Poppler pdftoppm was not found on the server PATH.', 'lloyds-pdf-flipbook'),
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

    $pages = lloyds_flipbook_collect_rendered_pages($cache_path, $cache_url);

    if ($exit_code !== 0 || !$pages) {
        return [
            'ok'       => false,
            'message'  => $output ? implode(' ', array_slice($output, 0, 3)) : __('Poppler did not produce page images.', 'lloyds-pdf-flipbook'),
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

function lloyds_flipbook_render_pdf_pages(string $pdf_path, string $cache_path, string $cache_url): array
{
    try {
        if (!class_exists('Imagick')) {
            return [
                'ok'       => false,
                'message'  => __('PHP Imagick is not installed.', 'lloyds-pdf-flipbook'),
                'pages'    => [],
                'renderer' => 'imagick',
            ];
        }

        wp_mkdir_p($cache_path);

        $existing_pages = lloyds_flipbook_collect_rendered_pages($cache_path, $cache_url);

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
        $page_count = min((int) $probe->getNumberImages(), (int) apply_filters('lloyds_flipbook_max_pages', 300));
        $probe->clear();
        $probe->destroy();

        if ($page_count < 1) {
            return [
                'ok'       => false,
                'message'  => __('No pages were detected in this PDF.', 'lloyds-pdf-flipbook'),
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
                __('PDF rendering failed locally: %s', 'lloyds-pdf-flipbook'),
                $error->getMessage()
            ),
            'pages'    => [],
            'renderer' => 'imagick',
        ];
    }
}

add_filter('the_content', static function (string $content): string {
    if (!is_singular('lloyds_flipbook') || !in_the_loop() || !is_main_query()) {
        return $content;
    }

    return do_shortcode('[lloyds_pdf_flipbook id="' . get_the_ID() . '"]') . $content;
}, 8);

add_filter('render_block_core/post-content', static function (string $block_content, array $block): string {
    if (!is_singular('lloyds_flipbook') || !is_main_query()) {
        return $block_content;
    }

    $post_id = get_the_ID();

    if (!$post_id) {
        return $block_content;
    }

    $viewer = do_shortcode('[lloyds_pdf_flipbook id="' . $post_id . '"]');

    if (str_contains($block_content, 'class="lloyds-flipbook"')) {
        return $block_content;
    }

    return $viewer . $block_content;
}, 8, 2);
