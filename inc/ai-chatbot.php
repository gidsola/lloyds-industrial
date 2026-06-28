<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const LI_CHATBOT_SETTINGS_OPTION = 'li_chatbot_settings';
const LI_CHATBOT_DB_VERSION = '1.0.0';
const LI_CHATBOT_DB_VERSION_OPTION = 'li_chatbot_db_version';

add_action('after_switch_theme', 'li_chatbot_install');
add_action('admin_init', 'li_chatbot_maybe_install');
add_action('admin_init', 'li_chatbot_handle_admin_actions');
add_action('admin_menu', 'li_chatbot_register_admin_menu', 20);
add_action('wp_enqueue_scripts', 'li_chatbot_enqueue_frontend_assets');
add_action('wp_footer', 'li_chatbot_render_widget_root');
add_action('wp_ajax_li_chatbot_message', 'li_chatbot_handle_message');
add_action('wp_ajax_nopriv_li_chatbot_message', 'li_chatbot_handle_message');
add_action('wp_ajax_li_chatbot_login', 'li_chatbot_handle_login');
add_action('wp_ajax_nopriv_li_chatbot_login', 'li_chatbot_handle_login');
add_action('wp_ajax_li_chatbot_track_action', 'li_chatbot_handle_track_action');
add_action('wp_ajax_nopriv_li_chatbot_track_action', 'li_chatbot_handle_track_action');

function li_chatbot_get_defaults(): array
{
    return [
        'enabled'                => false,
        'greeting'               => __('Hi, I can help with Lloyds products, documents, resellers, and SDS access.', 'lloyds-industrial'),
        'placeholder'            => __('Ask about a product, application, reseller, or SDS...', 'lloyds-industrial'),
        'widget_title'           => __('Lloyds Assistant', 'lloyds-industrial'),
        'widget_position'        => 'bottom-right',
        'accent_color'           => '#17443b',
        'button_label'           => __('Chat', 'lloyds-industrial'),
        'avatar_text'            => 'L',
        'endpoint_url'           => '',
        'endpoint_mode'          => 'custom',
        'model'                  => '',
        'auth_type'              => 'bearer',
        'auth_header'            => 'Authorization',
        'auth_key'               => '',
        'timeout'                => 20,
        'max_context_chunks'     => 6,
        'temperature'            => '0.3',
        'system_prompt'          => __('You are the Lloyds Laboratories website assistant. Answer only from supplied Lloyds context and available tool results. If context is insufficient, say so and offer the next useful Lloyds action.', 'lloyds-industrial'),
        'source_pages'           => true,
        'source_products'        => true,
        'source_documents'       => true,
        'source_resellers'       => true,
        'source_flipbooks'       => true,
        'source_media_metadata'  => false,
        'log_conversations'      => false,
    ];
}

function li_chatbot_get_settings(): array
{
    $settings = get_option(LI_CHATBOT_SETTINGS_OPTION, []);

    return wp_parse_args(is_array($settings) ? $settings : [], li_chatbot_get_defaults());
}

function li_chatbot_sanitize_settings(array $input): array
{
    $defaults = li_chatbot_get_defaults();
    $position = sanitize_key((string) ($input['widget_position'] ?? $defaults['widget_position']));
    $endpoint_mode = sanitize_key((string) ($input['endpoint_mode'] ?? $defaults['endpoint_mode']));
    $auth_type = sanitize_key((string) ($input['auth_type'] ?? $defaults['auth_type']));
    $timeout = absint($input['timeout'] ?? $defaults['timeout']);
    $max_context_chunks = absint($input['max_context_chunks'] ?? $defaults['max_context_chunks']);

    return [
        'enabled'                => !empty($input['enabled']),
        'greeting'               => sanitize_textarea_field((string) ($input['greeting'] ?? $defaults['greeting'])),
        'placeholder'            => sanitize_text_field((string) ($input['placeholder'] ?? $defaults['placeholder'])),
        'widget_title'           => sanitize_text_field((string) ($input['widget_title'] ?? $defaults['widget_title'])),
        'widget_position'        => in_array($position, ['bottom-right', 'bottom-left', 'top-right', 'top-left'], true) ? $position : $defaults['widget_position'],
        'accent_color'           => sanitize_hex_color((string) ($input['accent_color'] ?? '')) ?: $defaults['accent_color'],
        'button_label'           => sanitize_text_field((string) ($input['button_label'] ?? $defaults['button_label'])),
        'avatar_text'            => strtoupper(substr(sanitize_text_field((string) ($input['avatar_text'] ?? $defaults['avatar_text'])), 0, 2)) ?: $defaults['avatar_text'],
        'endpoint_url'           => esc_url_raw((string) ($input['endpoint_url'] ?? '')),
        'endpoint_mode'          => in_array($endpoint_mode, ['custom', 'openai'], true) ? $endpoint_mode : $defaults['endpoint_mode'],
        'model'                  => sanitize_text_field((string) ($input['model'] ?? '')),
        'auth_type'              => in_array($auth_type, ['none', 'bearer', 'header'], true) ? $auth_type : $defaults['auth_type'],
        'auth_header'            => sanitize_text_field((string) ($input['auth_header'] ?? $defaults['auth_header'])),
        'auth_key'               => sanitize_text_field((string) ($input['auth_key'] ?? '')),
        'timeout'                => min(60, max(5, $timeout ?: (int) $defaults['timeout'])),
        'max_context_chunks'     => min(12, max(1, $max_context_chunks ?: (int) $defaults['max_context_chunks'])),
        'temperature'            => sanitize_text_field((string) ($input['temperature'] ?? $defaults['temperature'])),
        'system_prompt'          => sanitize_textarea_field((string) ($input['system_prompt'] ?? $defaults['system_prompt'])),
        'source_pages'           => !empty($input['source_pages']),
        'source_products'        => !empty($input['source_products']),
        'source_documents'       => !empty($input['source_documents']),
        'source_resellers'       => !empty($input['source_resellers']),
        'source_flipbooks'       => !empty($input['source_flipbooks']),
        'source_media_metadata'  => !empty($input['source_media_metadata']),
        'log_conversations'      => !empty($input['log_conversations']),
    ];
}

function li_chatbot_context_table(): string
{
    global $wpdb;

    return $wpdb->prefix . 'li_chatbot_context';
}

function li_chatbot_log_table(): string
{
    global $wpdb;

    return $wpdb->prefix . 'li_chatbot_logs';
}

function li_chatbot_install(): void
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $context_table = li_chatbot_context_table();
    $log_table = li_chatbot_log_table();

    dbDelta("CREATE TABLE {$context_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        source_type varchar(40) NOT NULL,
        source_id varchar(120) NOT NULL,
        title text NOT NULL,
        content longtext NOT NULL,
        url text NOT NULL,
        access_level varchar(40) NOT NULL DEFAULT 'public',
        metadata longtext NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY source_lookup (source_type, source_id),
        KEY access_level (access_level),
        FULLTEXT KEY context_search (title, content)
    ) {$charset_collate};");

    dbDelta("CREATE TABLE {$log_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        session_id varchar(80) NOT NULL,
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        message longtext NOT NULL,
        response longtext NOT NULL,
        metadata longtext NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY session_id (session_id),
        KEY user_id (user_id)
    ) {$charset_collate};");

    update_option(LI_CHATBOT_DB_VERSION_OPTION, LI_CHATBOT_DB_VERSION);
}

function li_chatbot_maybe_install(): void
{
    if (get_option(LI_CHATBOT_DB_VERSION_OPTION) !== LI_CHATBOT_DB_VERSION) {
        li_chatbot_install();
    }

    register_setting('li_chatbot_settings', LI_CHATBOT_SETTINGS_OPTION, [
        'type'              => 'array',
        'sanitize_callback' => 'li_chatbot_sanitize_settings',
        'default'           => li_chatbot_get_defaults(),
    ]);
}

function li_chatbot_register_admin_menu(): void
{
    add_menu_page(
        __('Lloyds Intelligence', 'lloyds-industrial'),
        __('Intelligence', 'lloyds-industrial'),
        'manage_options',
        'lloyds-intelligence',
        'li_chatbot_render_overview_page',
        'dashicons-chart-line',
        59
    );

    add_submenu_page(
        'lloyds-intelligence',
        __('Lloyds AI Overview', 'lloyds-industrial'),
        __('AI Overview', 'lloyds-industrial'),
        'manage_options',
        'lloyds-intelligence',
        'li_chatbot_render_overview_page'
    );

    add_submenu_page(
        'lloyds-intelligence',
        __('Lloyds AI Chatbot', 'lloyds-industrial'),
        __('AI Chatbot', 'lloyds-industrial'),
        'manage_options',
        'lloyds-ai-chatbot',
        'li_chatbot_render_admin_page'
    );
}

function li_chatbot_handle_admin_actions(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['li_chatbot_rebuild_index'])) {
        check_admin_referer('li_chatbot_rebuild_index');
        $result = li_chatbot_rebuild_index();
        set_transient('li_chatbot_notice', ['type' => 'index', 'count' => $result['count']], MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=lloyds-ai-chatbot'));
        exit;
    }

    if (isset($_POST['li_chatbot_add_manual_context'])) {
        check_admin_referer('li_chatbot_add_manual_context');
        $title = sanitize_text_field(wp_unslash((string) ($_POST['li_chatbot_manual_title'] ?? '')));
        $content = sanitize_textarea_field(wp_unslash((string) ($_POST['li_chatbot_manual_content'] ?? '')));

        if (!empty($_FILES['li_chatbot_manual_file']['tmp_name'])) {
            $file = $_FILES['li_chatbot_manual_file'];
            $filename = sanitize_file_name((string) ($file['name'] ?? 'context.txt'));
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($extension, ['txt', 'md', 'csv', 'json'], true) && is_uploaded_file((string) $file['tmp_name'])) {
                $file_content = file_get_contents((string) $file['tmp_name']);

                if (is_string($file_content) && $file_content !== '') {
                    $title = $title ?: $filename;
                    $content .= "\n\n" . wp_strip_all_tags($file_content);
                }
            }
        }

        if ($title !== '' && trim($content) !== '') {
            li_chatbot_insert_context('manual', 'manual-' . md5($title . $content), $title, $content, '', 'public', [
                'manual' => true,
            ]);
            set_transient('li_chatbot_notice', ['type' => 'manual_added'], MINUTE_IN_SECONDS);
        }

        wp_safe_redirect(admin_url('admin.php?page=lloyds-ai-chatbot'));
        exit;
    }

    if (isset($_POST['li_chatbot_test_message'])) {
        check_admin_referer('li_chatbot_test_message');
        $settings = li_chatbot_get_settings();
        $message = sanitize_textarea_field(wp_unslash((string) ($_POST['li_chatbot_test_prompt'] ?? '')));

        if ($message !== '') {
            $context = li_chatbot_search_context($message, (int) $settings['max_context_chunks']);
            $tool_response = li_chatbot_maybe_handle_sds_intent($message, $context);
            $reply = $tool_response
                ? (string) $tool_response['message']
                : li_chatbot_generate_response($message, $context, [], $settings);
            set_transient('li_chatbot_test_result', [
                'message' => $message,
                'reply' => $reply,
                'context' => array_map(static fn (array $row): string => $row['source_type'] . ':' . $row['source_id'] . ' - ' . $row['title'], $context),
            ], MINUTE_IN_SECONDS);
        }

        wp_safe_redirect(admin_url('admin.php?page=lloyds-ai-chatbot'));
        exit;
    }
}

function li_chatbot_rebuild_index(): array
{
    global $wpdb;

    $table = li_chatbot_context_table();
    $settings = li_chatbot_get_settings();
    $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE source_type <> %s", 'manual'));

    $count = 0;

    if (!empty($settings['source_pages'])) {
        $count += li_chatbot_index_posts('page', 'page', 'public');
    }

    if (!empty($settings['source_products']) && post_type_exists('product')) {
        $count += li_chatbot_index_products();
    }

    if (!empty($settings['source_documents']) && post_type_exists('li_document')) {
        $count += li_chatbot_index_documents();
    }

    if (!empty($settings['source_resellers']) && post_type_exists('li_reseller')) {
        $count += li_chatbot_index_posts('li_reseller', 'reseller', 'public');
    }

    if (!empty($settings['source_flipbooks']) && post_type_exists('lloyds_flipbook')) {
        $count += li_chatbot_index_posts('lloyds_flipbook', 'flipbook', 'public');
    }

    if (!empty($settings['source_media_metadata'])) {
        $count += li_chatbot_index_media_metadata();
    }

    if (function_exists('li_analytics_record_event')) {
        li_analytics_record_event('chatbot_index_rebuilt', [
            'object_type' => 'chatbot',
            'object_name' => 'Knowledge Index',
            'quantity' => $count,
            'meta' => ['count' => $count],
        ]);
    }

    return ['count' => $count];
}

function li_chatbot_index_posts(string $post_type, string $source_type, string $access_level): int
{
    $query = new WP_Query([
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);
    $count = 0;

    foreach ($query->posts as $post_id) {
        $title = get_the_title($post_id);
        $content = li_chatbot_clean_text(get_post_field('post_excerpt', $post_id) . "\n\n" . get_post_field('post_content', $post_id));

        if ($title === '' && $content === '') {
            continue;
        }

        li_chatbot_insert_context($source_type, (string) $post_id, $title, $content, get_permalink($post_id) ?: '', $access_level, [
            'post_type' => $post_type,
        ]);
        $count++;
    }

    return $count;
}

function li_chatbot_index_products(): int
{
    $query = new WP_Query([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);
    $count = 0;

    foreach ($query->posts as $product_id) {
        $taxonomy_text = [];

        foreach (['product_cat', 'product_brand', 'li_industry', 'li_application'] as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            $terms = wp_get_post_terms($product_id, $taxonomy, ['fields' => 'names']);

            if (!is_wp_error($terms) && $terms) {
                $taxonomy_text[] = implode(', ', $terms);
            }
        }

        $sku = (string) get_post_meta($product_id, '_sku', true);
        $content = li_chatbot_clean_text(implode("\n", [
            $sku ? 'SKU: ' . $sku : '',
            get_post_field('post_excerpt', $product_id),
            get_post_field('post_content', $product_id),
            $taxonomy_text ? 'Categories and applications: ' . implode('; ', $taxonomy_text) : '',
        ]));

        li_chatbot_insert_context('product', (string) $product_id, get_the_title($product_id), $content, get_permalink($product_id) ?: '', 'public', [
            'post_type' => 'product',
            'sku'       => $sku,
        ]);
        $count++;
    }

    return $count;
}

function li_chatbot_index_documents(): int
{
    $query = new WP_Query([
        'post_type'      => 'li_document',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);
    $count = 0;

    foreach ($query->posts as $document_id) {
        $is_sds = function_exists('li_is_sds_document') && li_is_sds_document((int) $document_id);
        $related_product_id = (int) get_post_meta($document_id, '_li_related_product_id', true);
        $related_product = $related_product_id ? get_the_title($related_product_id) : '';
        $content = $is_sds
            ? sprintf(
                /* translators: %s is a product title. */
                __('Safety Data Sheet metadata for %s. The SDS file itself is protected and requires verified access.', 'lloyds-industrial'),
                $related_product ?: get_the_title($document_id)
            )
            : li_chatbot_clean_text(get_post_field('post_excerpt', $document_id) . "\n\n" . get_post_field('post_content', $document_id));

        li_chatbot_insert_context('document', (string) $document_id, get_the_title($document_id), $content, function_exists('li_get_document_download_url') ? (li_get_document_download_url((int) $document_id) ?: '') : '', $is_sds ? 'sds' : 'public', [
            'post_type'          => 'li_document',
            'is_sds'             => $is_sds,
            'related_product_id' => $related_product_id,
            'related_product'    => $related_product,
        ]);
        $count++;
    }

    return $count;
}

function li_chatbot_index_media_metadata(): int
{
    $query = new WP_Query([
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);
    $count = 0;

    foreach ($query->posts as $attachment_id) {
        $file_path = get_attached_file($attachment_id);

        if (is_string($file_path) && function_exists('li_document_path_is_in_protected_storage') && li_document_path_is_in_protected_storage($file_path)) {
            continue;
        }

        $title = get_the_title($attachment_id);
        $content = li_chatbot_clean_text(implode("\n", [
            $title,
            get_post_field('post_excerpt', $attachment_id),
            get_post_field('post_content', $attachment_id),
            get_post_mime_type($attachment_id) ?: '',
        ]));

        if ($content === '') {
            continue;
        }

        li_chatbot_insert_context('media', (string) $attachment_id, $title, $content, wp_get_attachment_url($attachment_id) ?: '', 'public', [
            'post_type' => 'attachment',
            'mime_type' => get_post_mime_type($attachment_id) ?: '',
        ]);
        $count++;
    }

    return $count;
}

function li_chatbot_clean_text(string $text): string
{
    $text = strip_shortcodes($text);
    $text = wp_strip_all_tags($text);
    $text = preg_replace('/\s+/', ' ', $text) ?: '';

    return trim(html_entity_decode($text, ENT_QUOTES));
}

function li_chatbot_insert_context(string $source_type, string $source_id, string $title, string $content, string $url, string $access_level, array $metadata = []): void
{
    global $wpdb;

    $table = li_chatbot_context_table();
    $content = li_chatbot_truncate(li_chatbot_clean_text($content), 8000);

    $wpdb->replace($table, [
        'source_type'  => sanitize_key($source_type),
        'source_id'    => sanitize_text_field($source_id),
        'title'        => sanitize_text_field($title),
        'content'      => $content,
        'url'          => esc_url_raw($url),
        'access_level' => sanitize_key($access_level),
        'metadata'     => wp_json_encode($metadata),
        'updated_at'   => current_time('mysql'),
    ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
}

function li_chatbot_search_context(string $message, int $limit = 6): array
{
    global $wpdb;

    $table = li_chatbot_context_table();
    $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT 500", ARRAY_A);
    $tokens = li_chatbot_tokenize($message);
    $scored = [];

    foreach ($rows as $row) {
        if (!li_chatbot_user_can_use_context($row)) {
            continue;
        }

        $haystack = strtolower((string) $row['title'] . ' ' . (string) $row['content']);
        $score = 0;

        foreach ($tokens as $token) {
            if (str_contains($haystack, $token)) {
                $score += 3;
            }

            if (str_contains(strtolower((string) $row['title']), $token)) {
                $score += 4;
            }
        }

        if ($score <= 0 && count($tokens) < 2) {
            $score = 1;
        }

        if ($score > 0) {
            $row['score'] = $score;
            $scored[] = $row;
        }
    }

    usort($scored, static fn (array $a, array $b): int => ($b['score'] <=> $a['score']));

    return array_slice($scored, 0, max(1, $limit));
}

function li_chatbot_user_can_use_context(array $row): bool
{
    $access = sanitize_key((string) ($row['access_level'] ?? 'public'));

    if ($access === 'public' || $access === 'sds') {
        return true;
    }

    return is_user_logged_in() && current_user_can('manage_options');
}

function li_chatbot_tokenize(string $message): array
{
    $message = strtolower(li_chatbot_clean_text($message));
    $parts = preg_split('/[^a-z0-9]+/', $message) ?: [];
    $stop = ['the', 'and', 'for', 'with', 'that', 'this', 'from', 'are', 'you', 'can', 'need', 'please', 'about'];

    return array_values(array_unique(array_filter($parts, static function (string $part) use ($stop): bool {
        return strlen($part) >= 3 && !in_array($part, $stop, true);
    })));
}

function li_chatbot_handle_message(): void
{
    check_ajax_referer('li_chatbot_message', 'nonce');

    $settings = li_chatbot_get_settings();
    $message = sanitize_textarea_field(wp_unslash((string) ($_POST['message'] ?? '')));
    $session_id = sanitize_text_field(wp_unslash((string) ($_POST['session_id'] ?? '')));
    $history = json_decode(wp_unslash((string) ($_POST['history'] ?? '[]')), true);
    $history = is_array($history) ? array_slice($history, -8) : [];

    if ($message === '') {
        wp_send_json_error(['message' => __('Please enter a message.', 'lloyds-industrial')], 400);
    }

    $context = li_chatbot_search_context($message, (int) $settings['max_context_chunks']);
    $actions = [];
    $tool_response = li_chatbot_maybe_handle_sds_intent($message, $context);

    if ($tool_response) {
        $reply = $tool_response['message'];
        $actions = $tool_response['actions'];
    } else {
        $reply = li_chatbot_generate_response($message, $context, $history, $settings);
        $actions = li_chatbot_context_actions($context);
    }

    if (!empty($settings['log_conversations'])) {
        li_chatbot_log_message($session_id, $message, $reply, [
            'actions' => $actions,
            'context' => array_map(static fn (array $row): string => $row['source_type'] . ':' . $row['source_id'], $context),
        ]);
    }

    li_chatbot_track_event('chatbot_message', [
        'session_id' => $session_id,
        'message' => $message,
        'context_count' => count($context),
        'actions_count' => count($actions),
        'intent' => $tool_response ? 'sds' : 'general',
        'source_types' => array_values(array_unique(array_map(static fn (array $row): string => (string) $row['source_type'], $context))),
        'sources' => array_map(static fn (array $row): string => $row['source_type'] . ':' . $row['source_id'], $context),
    ]);

    li_chatbot_track_event('chatbot_response', [
        'session_id' => $session_id,
        'response_length' => strlen($reply),
        'used_endpoint' => !empty($settings['endpoint_url']) && !$tool_response,
        'actions' => array_map(static fn (array $action): string => (string) ($action['type'] ?? 'link'), $actions),
    ]);

    wp_send_json_success([
        'message' => $reply,
        'actions' => $actions,
        'user'    => li_chatbot_current_user_payload(),
        'sources' => array_map(static fn (array $row): array => [
            'title' => (string) $row['title'],
            'type'  => (string) $row['source_type'],
            'url'   => (string) $row['url'],
        ], $context),
    ]);
}

function li_chatbot_generate_response(string $message, array $context, array $history, array $settings): string
{
    if (!empty($settings['endpoint_url'])) {
        $remote = li_chatbot_call_model_endpoint($message, $context, $history, $settings);

        if ($remote !== '') {
            return $remote;
        }
    }

    if (!$context) {
        return __('I could not find enough Lloyds site context to answer that confidently. Try asking about a product name, application, document, or reseller location.', 'lloyds-industrial');
    }

    $top = $context[0];
    $content = li_chatbot_truncate((string) $top['content'], 420);
    $title = (string) $top['title'];

    return sprintf(
        /* translators: 1: source title, 2: source summary. */
        __('Based on Lloyds site information, the closest match is %1$s. %2$s', 'lloyds-industrial'),
        $title,
        $content
    );
}

function li_chatbot_call_model_endpoint(string $message, array $context, array $history, array $settings): string
{
    $context_text = implode("\n\n", array_map(static function (array $row): string {
        return sprintf(
            "[%s:%s] %s\n%s",
            (string) $row['source_type'],
            (string) $row['source_id'],
            (string) $row['title'],
            li_chatbot_truncate((string) $row['content'], 1600)
        );
    }, $context));
    $headers = ['Content-Type' => 'application/json'];
    $auth_key = (string) ($settings['auth_key'] ?? '');

    if ($auth_key !== '' && $settings['auth_type'] === 'bearer') {
        $headers['Authorization'] = 'Bearer ' . $auth_key;
    } elseif ($auth_key !== '' && $settings['auth_type'] === 'header') {
        $headers[(string) ($settings['auth_header'] ?: 'Authorization')] = $auth_key;
    }

    if ($settings['endpoint_mode'] === 'openai') {
        $body = [
            'model' => (string) ($settings['model'] ?: 'gpt-4o-mini'),
            'temperature' => (float) $settings['temperature'],
            'messages' => [
                ['role' => 'system', 'content' => (string) $settings['system_prompt']],
                ['role' => 'system', 'content' => "Lloyds context:\n" . $context_text],
                ['role' => 'user', 'content' => $message],
            ],
        ];
    } else {
        $body = [
            'model' => (string) $settings['model'],
            'message' => $message,
            'system' => (string) $settings['system_prompt'],
            'context' => $context_text,
            'history' => $history,
            'user' => li_chatbot_current_user_payload(),
        ];
    }

    $response = wp_remote_post((string) $settings['endpoint_url'], [
        'headers' => $headers,
        'timeout' => (int) $settings['timeout'],
        'body'    => wp_json_encode($body),
    ]);

    if (is_wp_error($response)) {
        return '';
    }

    $payload = json_decode((string) wp_remote_retrieve_body($response), true);

    if (!is_array($payload)) {
        return '';
    }

    $content = $payload['choices'][0]['message']['content']
        ?? $payload['response']
        ?? $payload['message']
        ?? $payload['content']
        ?? '';

    return is_string($content) ? sanitize_textarea_field($content) : '';
}

function li_chatbot_truncate(string $text, int $length): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $length);
    }

    return substr($text, 0, $length);
}

function li_chatbot_maybe_handle_sds_intent(string $message, array $context): ?array
{
    if (!preg_match('/\b(sds|msds|safety data|safety-data)\b/i', $message)) {
        return null;
    }

    if (!is_user_logged_in()) {
        li_chatbot_track_event('chatbot_sds_login_prompt', [
            'message' => $message,
            'context_count' => count($context),
        ]);

        return [
            'message' => __('SDS downloads are protected. Sign in here and I will keep this chat open, then I can check which SDS files your account can access.', 'lloyds-industrial'),
            'actions' => [[
                'type' => 'login',
                'label' => __('Sign in to check SDS access', 'lloyds-industrial'),
            ]],
        ];
    }

    $docs = li_chatbot_find_relevant_sds_documents($message, $context);
    $downloads = [];

    foreach ($docs as $document_id) {
        if (function_exists('li_user_has_document_access_for_document') && li_user_has_document_access_for_document($document_id)) {
            $downloads[] = [
                'type' => 'download',
                'label' => get_the_title($document_id),
                'url' => function_exists('li_get_secure_document_url') ? li_get_secure_document_url($document_id) : '',
            ];
        }
    }

    if ($downloads) {
        li_chatbot_track_event('chatbot_sds_available', [
            'document_count' => count($downloads),
            'documents' => array_map(static fn (array $download): string => (string) $download['label'], $downloads),
        ]);

        return [
            'message' => __('I found SDS downloads your account can access. Select a document below to download it securely.', 'lloyds-industrial'),
            'actions' => $downloads,
        ];
    }

    li_chatbot_track_event('chatbot_sds_denied', [
        'candidate_count' => count($docs),
        'message' => $message,
    ]);

    return [
        'message' => __('I could not find an SDS download currently available to this account for that request. If this looks wrong, contact Lloyds support and we can help match the order history.', 'lloyds-industrial'),
        'actions' => [[
            'type' => 'link',
            'label' => __('Get SDS access help', 'lloyds-industrial'),
            'url' => home_url('/contact/?type=sds_access'),
        ]],
    ];
}

function li_chatbot_find_relevant_sds_documents(string $message, array $context): array
{
    $document_ids = [];

    foreach ($context as $row) {
        if (($row['source_type'] ?? '') === 'document' && ($row['access_level'] ?? '') === 'sds') {
            $document_ids[] = absint($row['source_id']);
        }

        if (($row['source_type'] ?? '') === 'product') {
            $product_id = absint($row['source_id']);

            if ($product_id) {
                $related = get_posts([
                    'post_type'      => 'li_document',
                    'post_status'    => 'publish',
                    'posts_per_page' => 10,
                    'fields'         => 'ids',
                    'meta_key'       => '_li_related_product_id',
                    'meta_value'     => $product_id,
                ]);

                foreach ($related as $document_id) {
                    if (function_exists('li_is_sds_document') && li_is_sds_document((int) $document_id)) {
                        $document_ids[] = (int) $document_id;
                    }
                }
            }
        }
    }

    if ($document_ids) {
        return array_values(array_unique(array_filter($document_ids)));
    }

    $query = new WP_Query([
        'post_type'      => 'li_document',
        'post_status'    => 'publish',
        'posts_per_page' => 25,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        's'              => $message,
    ]);

    return array_values(array_filter(array_map('absint', $query->posts), static function (int $document_id): bool {
        return function_exists('li_is_sds_document') && li_is_sds_document($document_id);
    }));
}

function li_chatbot_context_actions(array $context): array
{
    $actions = [];

    foreach (array_slice($context, 0, 3) as $row) {
        if (!empty($row['url']) && ($row['access_level'] ?? 'public') === 'public') {
            $actions[] = [
                'type' => 'link',
                'label' => sprintf(__('Open %s', 'lloyds-industrial'), (string) $row['title']),
                'url' => (string) $row['url'],
            ];
        }
    }

    return $actions;
}

function li_chatbot_handle_login(): void
{
    check_ajax_referer('li_chatbot_message', 'nonce');

    $username = sanitize_user(wp_unslash((string) ($_POST['username'] ?? '')));
    $password = (string) wp_unslash($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);

    if ($username === '' || $password === '') {
        wp_send_json_error(['message' => __('Enter your username/email and password.', 'lloyds-industrial')], 400);
    }

    $user = wp_signon([
        'user_login'    => $username,
        'user_password' => $password,
        'remember'      => $remember,
    ], is_ssl());

    if (is_wp_error($user)) {
        li_chatbot_track_event('chatbot_login_failed', [
            'username' => $username,
        ]);

        wp_send_json_error(['message' => __('The login details did not work. Please try again.', 'lloyds-industrial')], 403);
    }

    wp_set_current_user($user->ID);
    li_chatbot_track_event('chatbot_login_success', [
        'user_id' => $user->ID,
        'roles' => (array) $user->roles,
    ]);

    wp_send_json_success([
        'message' => __('You are signed in. I can now check protected Lloyds resources without closing this chat.', 'lloyds-industrial'),
        'user' => li_chatbot_current_user_payload(),
    ]);
}

function li_chatbot_handle_track_action(): void
{
    check_ajax_referer('li_chatbot_message', 'nonce');

    $event = sanitize_key((string) ($_POST['event'] ?? ''));
    $label = sanitize_text_field(wp_unslash((string) ($_POST['label'] ?? '')));
    $url = esc_url_raw(wp_unslash((string) ($_POST['url'] ?? '')));
    $session_id = sanitize_text_field(wp_unslash((string) ($_POST['session_id'] ?? '')));

    if (!in_array($event, ['open', 'action_click', 'download_click'], true)) {
        wp_send_json_error(['message' => __('Unknown chatbot event.', 'lloyds-industrial')], 400);
    }

    li_chatbot_track_event('chatbot_' . $event, [
        'session_id' => $session_id,
        'label' => $label,
        'url' => $url,
    ]);

    wp_send_json_success(['tracked' => true]);
}

function li_chatbot_track_client_event(string $event, array $data = []): void
{
    li_chatbot_track_event('chatbot_' . sanitize_key($event), $data);
}

function li_chatbot_track_event(string $event_type, array $meta = []): void
{
    if (!function_exists('li_analytics_record_event')) {
        return;
    }

    li_analytics_record_event($event_type, [
        'object_type' => 'chatbot',
        'object_name' => sanitize_text_field((string) ($meta['intent'] ?? 'AI Chatbot')),
        'quantity' => absint($meta['context_count'] ?? $meta['document_count'] ?? 0),
        'search_term' => sanitize_text_field((string) ($meta['message'] ?? '')),
        'meta' => $meta,
    ]);
}

function li_chatbot_current_user_payload(): array
{
    if (!is_user_logged_in()) {
        return [
            'loggedIn' => false,
            'name' => '',
            'roles' => [],
        ];
    }

    $user = wp_get_current_user();

    return [
        'loggedIn' => true,
        'name' => $user->display_name ?: $user->user_login,
        'roles' => (array) $user->roles,
    ];
}

function li_chatbot_log_message(string $session_id, string $message, string $response, array $metadata): void
{
    global $wpdb;

    $wpdb->insert(li_chatbot_log_table(), [
        'session_id' => sanitize_text_field($session_id),
        'user_id'    => get_current_user_id(),
        'message'    => $message,
        'response'   => $response,
        'metadata'   => wp_json_encode($metadata),
        'created_at' => current_time('mysql'),
    ], ['%s', '%d', '%s', '%s', '%s', '%s']);
}

function li_chatbot_enqueue_frontend_assets(): void
{
    $settings = li_chatbot_get_settings();

    if (empty($settings['enabled'])) {
        return;
    }

    wp_enqueue_style(
        'lloyds-chatbot',
        get_template_directory_uri() . '/assets/css/chatbot.css',
        [],
        filemtime(get_template_directory() . '/assets/css/chatbot.css')
    );

    wp_enqueue_script(
        'lloyds-chatbot',
        get_template_directory_uri() . '/assets/js/chatbot.js',
        [],
        filemtime(get_template_directory() . '/assets/js/chatbot.js'),
        true
    );

    wp_localize_script('lloyds-chatbot', 'lloydsChatbot', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('li_chatbot_message'),
        'settings' => [
            'greeting' => (string) $settings['greeting'],
            'placeholder' => (string) $settings['placeholder'],
            'title' => (string) $settings['widget_title'],
            'position' => (string) $settings['widget_position'],
            'accentColor' => (string) $settings['accent_color'],
            'buttonLabel' => (string) $settings['button_label'],
            'avatarText' => (string) $settings['avatar_text'],
        ],
        'user' => li_chatbot_current_user_payload(),
        'i18n' => [
            'send' => __('Send', 'lloyds-industrial'),
            'close' => __('Close chat', 'lloyds-industrial'),
            'open' => __('Open chat', 'lloyds-industrial'),
            'username' => __('Username or email', 'lloyds-industrial'),
            'password' => __('Password', 'lloyds-industrial'),
            'login' => __('Sign In', 'lloyds-industrial'),
            'thinking' => __('Checking Lloyds context...', 'lloyds-industrial'),
            'error' => __('I could not reach the assistant right now.', 'lloyds-industrial'),
        ],
    ]);
}

function li_chatbot_render_widget_root(): void
{
    $settings = li_chatbot_get_settings();

    if (empty($settings['enabled'])) {
        return;
    }

    echo '<div id="lloyds-chatbot-root" class="lloyds-chatbot-root" data-position="' . esc_attr((string) $settings['widget_position']) . '"></div>';
}

function li_chatbot_render_overview_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    li_chatbot_maybe_install();

    $settings = li_chatbot_get_settings();
    $knowledge = li_chatbot_get_knowledge_overview();
    $range = function_exists('li_analytics_get_date_range') ? li_analytics_get_date_range() : [
        'preset' => '30',
        'start' => gmdate('Y-m-d 00:00:00', strtotime('-30 days')),
        'end' => gmdate('Y-m-d 23:59:59'),
        'start_date' => gmdate('Y-m-d', strtotime('-30 days')),
        'end_date' => gmdate('Y-m-d'),
    ];
    $usage = li_chatbot_get_usage_overview($range);
    ?>
    <div class="wrap li-settings-page li-chatbot-overview">
        <div class="li-settings-hero">
            <div>
                <p class="li-settings-kicker"><?php esc_html_e('AI Intelligence', 'lloyds-industrial'); ?></p>
                <h1><?php esc_html_e('AI Knowledge Overview', 'lloyds-industrial'); ?></h1>
                <p><?php esc_html_e('Audit everything the assistant can retrieve, how fresh the knowledge base is, which sources are active, and how visitors are using the chatbot.', 'lloyds-industrial'); ?></p>
            </div>
            <div class="li-settings-summary">
                <div><span><?php esc_html_e('Widget', 'lloyds-industrial'); ?></span><strong><?php echo esc_html(!empty($settings['enabled']) ? __('Enabled', 'lloyds-industrial') : __('Disabled', 'lloyds-industrial')); ?></strong></div>
                <div><span><?php esc_html_e('Context Items', 'lloyds-industrial'); ?></span><strong><?php echo esc_html(number_format_i18n((int) $knowledge['total'])); ?></strong></div>
                <div><span><?php esc_html_e('Messages', 'lloyds-industrial'); ?></span><strong><?php echo esc_html(number_format_i18n((int) $usage['messages'])); ?></strong></div>
            </div>
        </div>

        <form class="li-analytics-filter" method="get">
            <input type="hidden" name="page" value="lloyds-intelligence">
            <label>
                <span><?php esc_html_e('Usage range', 'lloyds-industrial'); ?></span>
                <select name="range">
                    <option value="7" <?php selected($range['preset'], '7'); ?>><?php esc_html_e('Last 7 days', 'lloyds-industrial'); ?></option>
                    <option value="30" <?php selected($range['preset'], '30'); ?>><?php esc_html_e('Last 30 days', 'lloyds-industrial'); ?></option>
                    <option value="90" <?php selected($range['preset'], '90'); ?>><?php esc_html_e('Last 90 days', 'lloyds-industrial'); ?></option>
                    <option value="365" <?php selected($range['preset'], '365'); ?>><?php esc_html_e('Last 12 months', 'lloyds-industrial'); ?></option>
                    <option value="custom" <?php selected($range['preset'], 'custom'); ?>><?php esc_html_e('Custom', 'lloyds-industrial'); ?></option>
                </select>
            </label>
            <label>
                <span><?php esc_html_e('Start', 'lloyds-industrial'); ?></span>
                <input type="date" name="start" value="<?php echo esc_attr($range['start_date']); ?>">
            </label>
            <label>
                <span><?php esc_html_e('End', 'lloyds-industrial'); ?></span>
                <input type="date" name="end" value="<?php echo esc_attr($range['end_date']); ?>">
            </label>
            <button class="button button-primary" type="submit"><?php esc_html_e('Apply', 'lloyds-industrial'); ?></button>
        </form>

        <div class="li-analytics-kpi-grid">
            <?php
            li_render_analytics_kpi(__('Knowledge Sources', 'lloyds-industrial'), number_format_i18n(count($knowledge['by_source'])), __('Distinct source types currently indexed.', 'lloyds-industrial'));
            li_render_analytics_kpi(__('Public Context', 'lloyds-industrial'), number_format_i18n((int) ($knowledge['by_access']['public'] ?? 0)), __('Immediately available to anonymous chatbot sessions.', 'lloyds-industrial'));
            li_render_analytics_kpi(__('SDS Metadata', 'lloyds-industrial'), number_format_i18n((int) ($knowledge['by_access']['sds'] ?? 0)), __('Protected files are excluded; safe SDS metadata is indexed.', 'lloyds-industrial'));
            li_render_analytics_kpi(__('Manual Context', 'lloyds-industrial'), number_format_i18n((int) ($knowledge['by_source']['manual'] ?? 0)), __('Admin-added knowledge entries.', 'lloyds-industrial'));
            li_render_analytics_kpi(__('Chat Sessions', 'lloyds-industrial'), number_format_i18n((int) $usage['sessions']), __('Distinct chatbot sessions in range.', 'lloyds-industrial'));
            li_render_analytics_kpi(__('SDS Requests', 'lloyds-industrial'), number_format_i18n((int) $usage['sds_requests']), __('SDS-related chatbot events.', 'lloyds-industrial'));
            li_render_analytics_kpi(__('Login Prompts', 'lloyds-industrial'), number_format_i18n((int) $usage['login_prompts']), __('In-chat sign-in prompts shown.', 'lloyds-industrial'));
            li_render_analytics_kpi(__('Action Clicks', 'lloyds-industrial'), number_format_i18n((int) $usage['action_clicks']), __('Links and secure downloads clicked inside chat.', 'lloyds-industrial'));
            li_render_analytics_kpi(__('Avg Context', 'lloyds-industrial'), number_format_i18n((float) $usage['avg_context'], 1), __('Average retrieved chunks per message.', 'lloyds-industrial'));
            ?>
        </div>

        <div class="li-ai-overview-grid">
            <section class="li-analytics-panel">
                <h2><?php esc_html_e('Knowledge by Source', 'lloyds-industrial'); ?></h2>
                <?php li_chatbot_render_bar_chart($knowledge['by_source']); ?>
            </section>
            <section class="li-analytics-panel">
                <h2><?php esc_html_e('Access Levels', 'lloyds-industrial'); ?></h2>
                <?php li_chatbot_render_bar_chart($knowledge['by_access']); ?>
            </section>
            <section class="li-analytics-panel">
                <h2><?php esc_html_e('Chatbot Event Mix', 'lloyds-industrial'); ?></h2>
                <?php li_chatbot_render_bar_chart($usage['event_mix']); ?>
            </section>
            <section class="li-analytics-panel">
                <h2><?php esc_html_e('Source Types Used in Chat', 'lloyds-industrial'); ?></h2>
                <?php li_chatbot_render_bar_chart($usage['source_usage']); ?>
            </section>
        </div>

        <section class="li-analytics-panel li-analytics-panel--wide">
            <div class="li-settings-section-heading">
                <p class="li-settings-kicker"><?php esc_html_e('Trend', 'lloyds-industrial'); ?></p>
                <h2><?php esc_html_e('Daily Chatbot Messages', 'lloyds-industrial'); ?></h2>
                <p><?php esc_html_e('Message volume and sessions by day for the selected range.', 'lloyds-industrial'); ?></p>
            </div>
            <?php li_chatbot_render_daily_usage_table($usage['daily']); ?>
        </section>

        <div class="li-ai-overview-grid">
            <section class="li-analytics-panel">
                <h2><?php esc_html_e('Largest Context Entries', 'lloyds-industrial'); ?></h2>
                <?php li_chatbot_render_context_table($knowledge['largest'], __('Characters', 'lloyds-industrial')); ?>
            </section>
            <section class="li-analytics-panel">
                <h2><?php esc_html_e('Recently Updated Context', 'lloyds-industrial'); ?></h2>
                <?php li_chatbot_render_context_table($knowledge['recent'], __('Updated', 'lloyds-industrial')); ?>
            </section>
            <section class="li-analytics-panel">
                <h2><?php esc_html_e('Top User Prompts', 'lloyds-industrial'); ?></h2>
                <?php li_chatbot_render_usage_table($usage['top_prompts'], __('Prompt', 'lloyds-industrial')); ?>
            </section>
            <section class="li-analytics-panel">
                <h2><?php esc_html_e('Recent Conversations', 'lloyds-industrial'); ?></h2>
                <?php li_chatbot_render_log_table($usage['recent_logs']); ?>
            </section>
        </div>
    </div>
    <?php
}

function li_chatbot_get_knowledge_overview(): array
{
    global $wpdb;

    $table = li_chatbot_context_table();
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    $by_source = li_chatbot_pairs_to_map($wpdb->get_results("SELECT source_type AS label, COUNT(*) AS total FROM {$table} GROUP BY source_type ORDER BY total DESC", ARRAY_A));
    $by_access = li_chatbot_pairs_to_map($wpdb->get_results("SELECT access_level AS label, COUNT(*) AS total FROM {$table} GROUP BY access_level ORDER BY total DESC", ARRAY_A));
    $largest = $wpdb->get_results("SELECT title, source_type, access_level, CHAR_LENGTH(content) AS metric, updated_at FROM {$table} ORDER BY CHAR_LENGTH(content) DESC LIMIT 10", ARRAY_A);
    $recent = $wpdb->get_results("SELECT title, source_type, access_level, updated_at AS metric, updated_at FROM {$table} ORDER BY updated_at DESC LIMIT 10", ARRAY_A);

    return [
        'total' => $total,
        'by_source' => $by_source,
        'by_access' => $by_access,
        'largest' => is_array($largest) ? $largest : [],
        'recent' => is_array($recent) ? $recent : [],
    ];
}

function li_chatbot_get_usage_overview(array $range): array
{
    global $wpdb;

    $table = function_exists('li_analytics_table_name') ? li_analytics_table_name() : '';

    if ($table === '') {
        return li_chatbot_empty_usage_overview();
    }

    $start = (string) $range['start'];
    $end = (string) $range['end'];
    $chat_events = [
        'chatbot_message',
        'chatbot_response',
        'chatbot_sds_login_prompt',
        'chatbot_sds_available',
        'chatbot_sds_denied',
        'chatbot_login_success',
        'chatbot_login_failed',
        'chatbot_open',
        'chatbot_action_click',
        'chatbot_download_click',
        'chatbot_index_rebuilt',
    ];
    $placeholders = implode(', ', array_fill(0, count($chat_events), '%s'));
    $base_args = array_merge([$start, $end], $chat_events);
    $where = "event_date BETWEEN %s AND %s AND event_type IN ({$placeholders})";
    $messages = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE event_date BETWEEN %s AND %s AND event_type = %s", $start, $end, 'chatbot_message'));
    $sessions = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT session_id) FROM {$table} WHERE event_date BETWEEN %s AND %s AND event_type = %s AND session_id <> ''", $start, $end, 'chatbot_message'));
    $sds_requests = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE event_date BETWEEN %s AND %s AND event_type IN (%s, %s, %s)", $start, $end, 'chatbot_sds_login_prompt', 'chatbot_sds_available', 'chatbot_sds_denied'));
    $login_prompts = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE event_date BETWEEN %s AND %s AND event_type = %s", $start, $end, 'chatbot_sds_login_prompt'));
    $action_clicks = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE event_date BETWEEN %s AND %s AND event_type IN (%s, %s)", $start, $end, 'chatbot_action_click', 'chatbot_download_click'));
    $event_mix = li_chatbot_pairs_to_map($wpdb->get_results($wpdb->prepare("SELECT event_type AS label, COUNT(*) AS total FROM {$table} WHERE {$where} GROUP BY event_type ORDER BY total DESC", $base_args), ARRAY_A));
    $daily = $wpdb->get_results($wpdb->prepare("SELECT DATE(event_date) AS day, COUNT(*) AS messages, COUNT(DISTINCT session_id) AS sessions FROM {$table} WHERE event_date BETWEEN %s AND %s AND event_type = %s GROUP BY DATE(event_date) ORDER BY day ASC", $start, $end, 'chatbot_message'), ARRAY_A);
    $top_prompts = $wpdb->get_results($wpdb->prepare("SELECT search_term AS label, COUNT(*) AS total, COUNT(DISTINCT visitor_id) AS visitors FROM {$table} WHERE event_date BETWEEN %s AND %s AND event_type = %s AND search_term <> '' GROUP BY search_term ORDER BY total DESC LIMIT 10", $start, $end, 'chatbot_message'), ARRAY_A);
    $source_usage = li_chatbot_get_source_usage_from_events($start, $end);
    $recent_logs = li_chatbot_get_recent_chat_logs();
    $avg_context = li_chatbot_get_average_context_count($start, $end);

    return [
        'messages' => $messages,
        'sessions' => $sessions,
        'sds_requests' => $sds_requests,
        'login_prompts' => $login_prompts,
        'action_clicks' => $action_clicks,
        'avg_context' => $avg_context,
        'event_mix' => $event_mix,
        'daily' => is_array($daily) ? $daily : [],
        'top_prompts' => is_array($top_prompts) ? $top_prompts : [],
        'source_usage' => $source_usage,
        'recent_logs' => $recent_logs,
    ];
}

function li_chatbot_empty_usage_overview(): array
{
    return [
        'messages' => 0,
        'sessions' => 0,
        'sds_requests' => 0,
        'login_prompts' => 0,
        'action_clicks' => 0,
        'avg_context' => 0,
        'event_mix' => [],
        'daily' => [],
        'top_prompts' => [],
        'source_usage' => [],
        'recent_logs' => [],
    ];
}

function li_chatbot_pairs_to_map(?array $rows): array
{
    $map = [];

    foreach ($rows ?: [] as $row) {
        $label = (string) ($row['label'] ?? '');

        if ($label === '') {
            $label = __('Unlabelled', 'lloyds-industrial');
        }

        $map[$label] = (int) ($row['total'] ?? 0);
    }

    return $map;
}

function li_chatbot_get_source_usage_from_events(string $start, string $end): array
{
    global $wpdb;

    if (!function_exists('li_analytics_table_name')) {
        return [];
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT meta FROM ' . li_analytics_table_name() . ' WHERE event_date BETWEEN %s AND %s AND event_type = %s AND meta IS NOT NULL ORDER BY event_date DESC LIMIT 500',
        $start,
        $end,
        'chatbot_message'
    ), ARRAY_A);
    $usage = [];

    foreach ($rows ?: [] as $row) {
        $meta = json_decode((string) ($row['meta'] ?? ''), true);

        if (!is_array($meta) || empty($meta['source_types']) || !is_array($meta['source_types'])) {
            continue;
        }

        foreach ($meta['source_types'] as $source_type) {
            $source_type = sanitize_key((string) $source_type);
            $usage[$source_type] = ($usage[$source_type] ?? 0) + 1;
        }
    }

    arsort($usage);

    return $usage;
}

function li_chatbot_get_average_context_count(string $start, string $end): float
{
    global $wpdb;

    if (!function_exists('li_analytics_table_name')) {
        return 0.0;
    }

    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT meta FROM ' . li_analytics_table_name() . ' WHERE event_date BETWEEN %s AND %s AND event_type = %s AND meta IS NOT NULL ORDER BY event_date DESC LIMIT 500',
        $start,
        $end,
        'chatbot_message'
    ), ARRAY_A);
    $total = 0;
    $count = 0;

    foreach ($rows ?: [] as $row) {
        $meta = json_decode((string) ($row['meta'] ?? ''), true);

        if (is_array($meta) && isset($meta['context_count'])) {
            $total += absint($meta['context_count']);
            $count++;
        }
    }

    return $count ? $total / $count : 0.0;
}

function li_chatbot_get_recent_chat_logs(): array
{
    global $wpdb;

    $table = li_chatbot_log_table();

    return $wpdb->get_results("SELECT session_id, user_id, message, response, created_at FROM {$table} ORDER BY created_at DESC LIMIT 8", ARRAY_A) ?: [];
}

function li_chatbot_render_bar_chart(array $items): void
{
    $max = max(array_values($items ?: [0]));
    ?>
    <div class="li-ai-bars">
        <?php if (!$items) : ?>
            <p><?php esc_html_e('No data yet.', 'lloyds-industrial'); ?></p>
        <?php endif; ?>
        <?php foreach ($items as $label => $value) : ?>
            <?php $width = $max > 0 ? max(4, ((int) $value / $max) * 100) : 0; ?>
            <div class="li-ai-bar">
                <div class="li-ai-bar__label">
                    <span><?php echo esc_html((string) $label); ?></span>
                    <strong><?php echo esc_html(number_format_i18n((int) $value)); ?></strong>
                </div>
                <div class="li-ai-bar__track"><span style="width: <?php echo esc_attr((string) $width); ?>%"></span></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

function li_chatbot_render_daily_usage_table(array $rows): void
{
    ?>
    <table class="widefat striped li-analytics-table">
        <thead><tr><th><?php esc_html_e('Day', 'lloyds-industrial'); ?></th><th><?php esc_html_e('Messages', 'lloyds-industrial'); ?></th><th><?php esc_html_e('Sessions', 'lloyds-industrial'); ?></th></tr></thead>
        <tbody>
            <?php if (!$rows) : ?>
                <tr><td colspan="3"><?php esc_html_e('No chatbot usage in this range yet.', 'lloyds-industrial'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row) : ?>
                <tr>
                    <td><?php echo esc_html((string) $row['day']); ?></td>
                    <td><?php echo esc_html(number_format_i18n((int) $row['messages'])); ?></td>
                    <td><?php echo esc_html(number_format_i18n((int) $row['sessions'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function li_chatbot_render_context_table(array $rows, string $metric_label): void
{
    ?>
    <table class="widefat striped li-analytics-table">
        <thead><tr><th><?php esc_html_e('Context', 'lloyds-industrial'); ?></th><th><?php esc_html_e('Source', 'lloyds-industrial'); ?></th><th><?php echo esc_html($metric_label); ?></th></tr></thead>
        <tbody>
            <?php if (!$rows) : ?>
                <tr><td colspan="3"><?php esc_html_e('No context has been indexed yet.', 'lloyds-industrial'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row) : ?>
                <tr>
                    <td><?php echo esc_html((string) $row['title']); ?><br><span class="description"><?php echo esc_html((string) $row['access_level']); ?></span></td>
                    <td><?php echo esc_html((string) $row['source_type']); ?></td>
                    <td><?php echo esc_html(is_numeric($row['metric']) ? number_format_i18n((int) $row['metric']) : (string) $row['metric']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function li_chatbot_render_usage_table(array $rows, string $label_heading): void
{
    ?>
    <table class="widefat striped li-analytics-table">
        <thead><tr><th><?php echo esc_html($label_heading); ?></th><th><?php esc_html_e('Uses', 'lloyds-industrial'); ?></th><th><?php esc_html_e('Visitors', 'lloyds-industrial'); ?></th></tr></thead>
        <tbody>
            <?php if (!$rows) : ?>
                <tr><td colspan="3"><?php esc_html_e('No usage yet.', 'lloyds-industrial'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row) : ?>
                <tr>
                    <td><?php echo esc_html((string) $row['label']); ?></td>
                    <td><?php echo esc_html(number_format_i18n((int) $row['total'])); ?></td>
                    <td><?php echo esc_html(number_format_i18n((int) $row['visitors'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function li_chatbot_render_log_table(array $rows): void
{
    ?>
    <table class="widefat striped li-analytics-table">
        <thead><tr><th><?php esc_html_e('When', 'lloyds-industrial'); ?></th><th><?php esc_html_e('Prompt', 'lloyds-industrial'); ?></th><th><?php esc_html_e('Reply', 'lloyds-industrial'); ?></th></tr></thead>
        <tbody>
            <?php if (!$rows) : ?>
                <tr><td colspan="3"><?php esc_html_e('Conversation logging is off or no logs exist yet.', 'lloyds-industrial'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row) : ?>
                <tr>
                    <td><?php echo esc_html((string) $row['created_at']); ?></td>
                    <td><?php echo esc_html(li_chatbot_truncate((string) $row['message'], 120)); ?></td>
                    <td><?php echo esc_html(li_chatbot_truncate((string) $row['response'], 160)); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function li_chatbot_render_admin_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;

    $settings = li_chatbot_get_settings();
    $context_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . li_chatbot_context_table());
    $manual_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . li_chatbot_context_table() . " WHERE source_type = %s", 'manual'));
    $notice = get_transient('li_chatbot_notice');
    $test_result = get_transient('li_chatbot_test_result');
    delete_transient('li_chatbot_notice');
    delete_transient('li_chatbot_test_result');
    ?>
    <div class="wrap li-settings-page li-chatbot-admin">
        <div class="li-settings-hero">
            <div>
                <p class="li-settings-kicker"><?php esc_html_e('AI Support', 'lloyds-industrial'); ?></p>
                <h1><?php esc_html_e('Lloyds AI Chatbot', 'lloyds-industrial'); ?></h1>
                <p><?php esc_html_e('Control the on-site assistant, model endpoint, retrieval context, SDS-safe access behavior, and visual chat widget from one Lloyds-owned admin surface.', 'lloyds-industrial'); ?></p>
            </div>
            <div class="li-settings-summary">
                <div><span><?php esc_html_e('Status', 'lloyds-industrial'); ?></span><strong><?php echo esc_html(!empty($settings['enabled']) ? __('Enabled', 'lloyds-industrial') : __('Disabled', 'lloyds-industrial')); ?></strong></div>
                <div><span><?php esc_html_e('Context Chunks', 'lloyds-industrial'); ?></span><strong><?php echo esc_html(number_format_i18n($context_count)); ?></strong></div>
                <div><span><?php esc_html_e('Manual Entries', 'lloyds-industrial'); ?></span><strong><?php echo esc_html(number_format_i18n($manual_count)); ?></strong></div>
            </div>
        </div>

        <?php if (is_array($notice)) : ?>
            <div class="li-admin-notice li-admin-notice--success" data-li-auto-dismiss-notice>
                <p>
                    <?php
                    echo esc_html($notice['type'] === 'index'
                        ? sprintf(__('Knowledge index rebuilt with %d generated entries.', 'lloyds-industrial'), absint($notice['count'] ?? 0))
                        : __('Manual chatbot context added.', 'lloyds-industrial'));
                    ?>
                </p>
                <button type="button" class="li-admin-notice__dismiss" data-li-dismiss-notice aria-label="<?php esc_attr_e('Dismiss notice', 'lloyds-industrial'); ?>">&times;</button>
            </div>
        <?php endif; ?>

        <nav class="li-admin-tabs" data-li-admin-tabs=".li-chatbot-admin" data-li-tabs-key="li-chatbot-admin-tab" aria-label="<?php esc_attr_e('Chatbot settings sections', 'lloyds-industrial'); ?>">
            <button class="li-admin-tab" type="button" data-li-tab-target="chatbot-general"><?php esc_html_e('General', 'lloyds-industrial'); ?></button>
            <button class="li-admin-tab" type="button" data-li-tab-target="chatbot-endpoint"><?php esc_html_e('Model Endpoint', 'lloyds-industrial'); ?></button>
            <button class="li-admin-tab" type="button" data-li-tab-target="chatbot-context"><?php esc_html_e('Context Sources', 'lloyds-industrial'); ?></button>
            <button class="li-admin-tab" type="button" data-li-tab-target="chatbot-appearance"><?php esc_html_e('Appearance', 'lloyds-industrial'); ?></button>
            <button class="li-admin-tab" type="button" data-li-tab-target="chatbot-index"><?php esc_html_e('Knowledge Index', 'lloyds-industrial'); ?></button>
            <button class="li-admin-tab" type="button" data-li-tab-target="chatbot-manual"><?php esc_html_e('Manual Context', 'lloyds-industrial'); ?></button>
            <button class="li-admin-tab" type="button" data-li-tab-target="chatbot-testing"><?php esc_html_e('Testing', 'lloyds-industrial'); ?></button>
        </nav>

        <form class="li-settings-form li-admin-tab-panels" method="post" action="options.php">
            <?php settings_fields('li_chatbot_settings'); ?>

            <section class="li-admin-panel li-admin-tab-panel" data-li-tab-panel="chatbot-general">
                <h2><?php esc_html_e('General', 'lloyds-industrial'); ?></h2>
                <?php li_chatbot_render_checkbox('enabled', __('Enable chatbot on the public site', 'lloyds-industrial'), $settings); ?>
                <?php li_chatbot_render_text('widget_title', __('Widget title', 'lloyds-industrial'), $settings); ?>
                <?php li_chatbot_render_textarea('greeting', __('Greeting', 'lloyds-industrial'), $settings, 3); ?>
                <?php li_chatbot_render_text('placeholder', __('Input placeholder', 'lloyds-industrial'), $settings); ?>
                <?php submit_button(__('Save Chatbot Settings', 'lloyds-industrial'), 'primary', 'submit', false); ?>
            </section>

            <section class="li-admin-panel li-admin-tab-panel" data-li-tab-panel="chatbot-endpoint">
                <h2><?php esc_html_e('Model Endpoint', 'lloyds-industrial'); ?></h2>
                <?php li_chatbot_render_text('endpoint_url', __('Endpoint URL', 'lloyds-industrial'), $settings, 'https://example.com/chat'); ?>
                <?php li_chatbot_render_select('endpoint_mode', __('Endpoint format', 'lloyds-industrial'), $settings, ['custom' => __('Custom Lloyds payload', 'lloyds-industrial'), 'openai' => __('OpenAI-compatible chat completions', 'lloyds-industrial')]); ?>
                <?php li_chatbot_render_text('model', __('Model name', 'lloyds-industrial'), $settings); ?>
                <?php li_chatbot_render_select('auth_type', __('Authentication', 'lloyds-industrial'), $settings, ['bearer' => __('Bearer token', 'lloyds-industrial'), 'header' => __('Custom header', 'lloyds-industrial'), 'none' => __('None', 'lloyds-industrial')]); ?>
                <?php li_chatbot_render_text('auth_header', __('Auth header', 'lloyds-industrial'), $settings); ?>
                <?php li_chatbot_render_password('auth_key', __('Auth key', 'lloyds-industrial'), $settings); ?>
                <?php li_chatbot_render_number('timeout', __('Timeout seconds', 'lloyds-industrial'), $settings, 5, 60); ?>
                <?php li_chatbot_render_number('max_context_chunks', __('Max context chunks', 'lloyds-industrial'), $settings, 1, 12); ?>
                <?php li_chatbot_render_textarea('system_prompt', __('System/tone prompt', 'lloyds-industrial'), $settings, 5); ?>
                <?php submit_button(__('Save Chatbot Settings', 'lloyds-industrial'), 'primary', 'submit', false); ?>
            </section>

            <section class="li-admin-panel li-admin-tab-panel" data-li-tab-panel="chatbot-context">
                <h2><?php esc_html_e('Context Sources', 'lloyds-industrial'); ?></h2>
                <?php foreach (['source_pages' => __('Pages', 'lloyds-industrial'), 'source_products' => __('Products', 'lloyds-industrial'), 'source_documents' => __('Documents and SDS metadata', 'lloyds-industrial'), 'source_resellers' => __('Resellers', 'lloyds-industrial'), 'source_flipbooks' => __('PDF Flipbooks', 'lloyds-industrial'), 'source_media_metadata' => __('Media metadata', 'lloyds-industrial')] as $key => $label) : ?>
                    <?php li_chatbot_render_checkbox($key, $label, $settings); ?>
                <?php endforeach; ?>
                <?php li_chatbot_render_checkbox('log_conversations', __('Log conversations for admin review', 'lloyds-industrial'), $settings); ?>
                <?php submit_button(__('Save Chatbot Settings', 'lloyds-industrial'), 'primary', 'submit', false); ?>
            </section>

            <section class="li-admin-panel li-admin-tab-panel" data-li-tab-panel="chatbot-appearance">
                <h2><?php esc_html_e('Widget Appearance', 'lloyds-industrial'); ?></h2>
                <?php li_chatbot_render_select('widget_position', __('Icon position', 'lloyds-industrial'), $settings, ['bottom-right' => __('Bottom right', 'lloyds-industrial'), 'bottom-left' => __('Bottom left', 'lloyds-industrial'), 'top-right' => __('Top right', 'lloyds-industrial'), 'top-left' => __('Top left', 'lloyds-industrial')]); ?>
                <?php li_chatbot_render_text('button_label', __('Button label', 'lloyds-industrial'), $settings); ?>
                <?php li_chatbot_render_text('avatar_text', __('Avatar text', 'lloyds-industrial'), $settings); ?>
                <?php li_chatbot_render_text('accent_color', __('Accent color', 'lloyds-industrial'), $settings, '#17443b'); ?>
                <?php submit_button(__('Save Chatbot Settings', 'lloyds-industrial'), 'primary', 'submit', false); ?>
            </section>
        </form>

        <div class="li-settings-maintenance li-chatbot-tools li-admin-tab-panels">
            <section class="li-admin-panel li-admin-tab-panel" data-li-tab-panel="chatbot-index">
                <h2><?php esc_html_e('Knowledge Index', 'lloyds-industrial'); ?></h2>
                <p><?php esc_html_e('Rebuild the local retrieval index from the selected source toggles. Protected SDS files are not indexed as readable text; only safe metadata is stored.', 'lloyds-industrial'); ?></p>
                <form method="post">
                    <?php wp_nonce_field('li_chatbot_rebuild_index'); ?>
                    <button class="button button-primary" type="submit" name="li_chatbot_rebuild_index" value="1"><?php esc_html_e('Rebuild Index', 'lloyds-industrial'); ?></button>
                </form>
            </section>

            <section class="li-admin-panel li-admin-tab-panel" data-li-tab-panel="chatbot-manual">
                <h2><?php esc_html_e('Manual Context', 'lloyds-industrial'); ?></h2>
                <p><?php esc_html_e('Add controlled knowledge that does not live in normal WordPress content. Text, Markdown, CSV, and JSON uploads are accepted.', 'lloyds-industrial'); ?></p>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('li_chatbot_add_manual_context'); ?>
                    <label>
                        <span><?php esc_html_e('Title', 'lloyds-industrial'); ?></span>
                        <input class="regular-text" type="text" name="li_chatbot_manual_title">
                    </label>
                    <label>
                        <span><?php esc_html_e('Context text', 'lloyds-industrial'); ?></span>
                        <textarea class="large-text" name="li_chatbot_manual_content" rows="6"></textarea>
                    </label>
                    <label>
                        <span><?php esc_html_e('Upload context file', 'lloyds-industrial'); ?></span>
                        <input type="file" name="li_chatbot_manual_file" accept=".txt,.md,.csv,.json">
                    </label>
                    <button class="button button-secondary" type="submit" name="li_chatbot_add_manual_context" value="1"><?php esc_html_e('Add Manual Context', 'lloyds-industrial'); ?></button>
                </form>
            </section>

            <section class="li-admin-panel li-chatbot-test-console li-admin-tab-panel" data-li-tab-panel="chatbot-testing">
                <h2><?php esc_html_e('Testing Console', 'lloyds-industrial'); ?></h2>
                <p><?php esc_html_e('Send a test prompt through the same retrieval and endpoint layer used by the public widget.', 'lloyds-industrial'); ?></p>
                <form method="post">
                    <?php wp_nonce_field('li_chatbot_test_message'); ?>
                    <label>
                        <span><?php esc_html_e('Test prompt', 'lloyds-industrial'); ?></span>
                        <textarea class="large-text" name="li_chatbot_test_prompt" rows="4"><?php echo esc_textarea(is_array($test_result) ? (string) ($test_result['message'] ?? '') : ''); ?></textarea>
                    </label>
                    <button class="button button-secondary" type="submit" name="li_chatbot_test_message" value="1"><?php esc_html_e('Run Test Prompt', 'lloyds-industrial'); ?></button>
                </form>
                <?php if (is_array($test_result)) : ?>
                    <div class="li-chatbot-test-result">
                        <strong><?php esc_html_e('Assistant reply', 'lloyds-industrial'); ?></strong>
                        <p><?php echo esc_html((string) ($test_result['reply'] ?? '')); ?></p>
                        <strong><?php esc_html_e('Retrieved context', 'lloyds-industrial'); ?></strong>
                        <ul>
                            <?php foreach ((array) ($test_result['context'] ?? []) as $source) : ?>
                                <li><?php echo esc_html((string) $source); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
    <?php
}

function li_chatbot_setting_name(string $key): string
{
    return LI_CHATBOT_SETTINGS_OPTION . '[' . $key . ']';
}

function li_chatbot_render_text(string $key, string $label, array $settings, string $placeholder = ''): void
{
    ?>
    <label class="li-chatbot-field">
        <span><?php echo esc_html($label); ?></span>
        <input class="regular-text" type="text" name="<?php echo esc_attr(li_chatbot_setting_name($key)); ?>" value="<?php echo esc_attr((string) ($settings[$key] ?? '')); ?>" placeholder="<?php echo esc_attr($placeholder); ?>">
    </label>
    <?php
}

function li_chatbot_render_password(string $key, string $label, array $settings): void
{
    ?>
    <label class="li-chatbot-field">
        <span><?php echo esc_html($label); ?></span>
        <input class="regular-text" type="password" name="<?php echo esc_attr(li_chatbot_setting_name($key)); ?>" value="<?php echo esc_attr((string) ($settings[$key] ?? '')); ?>" autocomplete="new-password">
    </label>
    <?php
}

function li_chatbot_render_number(string $key, string $label, array $settings, int $min, int $max): void
{
    ?>
    <label class="li-chatbot-field">
        <span><?php echo esc_html($label); ?></span>
        <input class="small-text" type="number" min="<?php echo esc_attr((string) $min); ?>" max="<?php echo esc_attr((string) $max); ?>" name="<?php echo esc_attr(li_chatbot_setting_name($key)); ?>" value="<?php echo esc_attr((string) ($settings[$key] ?? '')); ?>">
    </label>
    <?php
}

function li_chatbot_render_textarea(string $key, string $label, array $settings, int $rows): void
{
    ?>
    <label class="li-chatbot-field">
        <span><?php echo esc_html($label); ?></span>
        <textarea class="large-text" rows="<?php echo esc_attr((string) $rows); ?>" name="<?php echo esc_attr(li_chatbot_setting_name($key)); ?>"><?php echo esc_textarea((string) ($settings[$key] ?? '')); ?></textarea>
    </label>
    <?php
}

function li_chatbot_render_select(string $key, string $label, array $settings, array $options): void
{
    ?>
    <label class="li-chatbot-field">
        <span><?php echo esc_html($label); ?></span>
        <select name="<?php echo esc_attr(li_chatbot_setting_name($key)); ?>">
            <?php foreach ($options as $value => $option_label) : ?>
                <option value="<?php echo esc_attr((string) $value); ?>" <?php selected((string) ($settings[$key] ?? ''), (string) $value); ?>><?php echo esc_html($option_label); ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php
}

function li_chatbot_render_checkbox(string $key, string $label, array $settings): void
{
    ?>
    <label class="li-chatbot-check">
        <input type="checkbox" name="<?php echo esc_attr(li_chatbot_setting_name($key)); ?>" value="1" <?php checked(!empty($settings[$key])); ?>>
        <span><?php echo esc_html($label); ?></span>
    </label>
    <?php
}
