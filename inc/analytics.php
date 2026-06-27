<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const LI_ANALYTICS_DB_VERSION = '1.0.0';
const LI_ANALYTICS_VISITOR_COOKIE = 'li_analytics_vid';
const LI_ANALYTICS_SESSION_COOKIE = 'li_analytics_sid';

add_action('after_switch_theme', 'li_analytics_install');
add_action('admin_init', 'li_analytics_maybe_install');

add_action('admin_menu', function (): void {
    add_submenu_page(
        'lloyds',
        __('Lloyds Analytics', 'lloyds-industrial'),
        __('Analytics', 'lloyds-industrial'),
        'manage_options',
        'lloyds-analytics',
        'li_render_analytics_page'
    );
});

add_action('init', 'li_analytics_bootstrap_visitor', 1);
add_action('template_redirect', 'li_analytics_track_page_view', 99);
add_action('woocommerce_add_to_cart', 'li_analytics_track_add_to_cart', 10, 6);
add_action('woocommerce_checkout_order_processed', 'li_analytics_track_order_created', 10, 3);
add_action('woocommerce_payment_complete', 'li_analytics_track_order_paid');
add_action('woocommerce_order_status_completed', 'li_analytics_track_order_completed');

function li_analytics_table_name(): string
{
    global $wpdb;

    return $wpdb->prefix . 'li_analytics_events';
}

function li_analytics_install(): void
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table_name = li_analytics_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_date datetime NOT NULL,
        event_type varchar(40) NOT NULL,
        object_type varchar(40) NOT NULL DEFAULT '',
        object_id bigint(20) unsigned NOT NULL DEFAULT 0,
        object_name varchar(255) NOT NULL DEFAULT '',
        url text NULL,
        path varchar(255) NOT NULL DEFAULT '',
        referrer text NULL,
        referrer_host varchar(190) NOT NULL DEFAULT '',
        utm_source varchar(120) NOT NULL DEFAULT '',
        utm_medium varchar(120) NOT NULL DEFAULT '',
        utm_campaign varchar(190) NOT NULL DEFAULT '',
        search_term varchar(190) NOT NULL DEFAULT '',
        visitor_id varchar(64) NOT NULL DEFAULT '',
        session_id varchar(64) NOT NULL DEFAULT '',
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        ip_hash varchar(64) NOT NULL DEFAULT '',
        user_agent text NULL,
        browser varchar(80) NOT NULL DEFAULT '',
        os varchar(80) NOT NULL DEFAULT '',
        device varchar(40) NOT NULL DEFAULT '',
        is_bot tinyint(1) NOT NULL DEFAULT 0,
        value decimal(18,4) NOT NULL DEFAULT 0,
        quantity int unsigned NOT NULL DEFAULT 0,
        currency varchar(12) NOT NULL DEFAULT '',
        meta longtext NULL,
        PRIMARY KEY  (id),
        KEY event_date (event_date),
        KEY event_type_date (event_type,event_date),
        KEY object_lookup (object_type,object_id,event_date),
        KEY visitor_lookup (visitor_id,event_date),
        KEY session_lookup (session_id,event_date),
        KEY referrer_host (referrer_host),
        KEY is_bot (is_bot)
    ) {$charset_collate};";

    dbDelta($sql);
    update_option('li_analytics_db_version', LI_ANALYTICS_DB_VERSION);
}

function li_analytics_maybe_install(): void
{
    if (get_option('li_analytics_db_version') !== LI_ANALYTICS_DB_VERSION) {
        li_analytics_install();
    }
}

function li_analytics_bootstrap_visitor(): void
{
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || headers_sent()) {
        return;
    }

    $secure = is_ssl();
    $cookie_path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';
    $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';

    if (empty($_COOKIE[LI_ANALYTICS_VISITOR_COOKIE])) {
        $visitor_id = wp_generate_uuid4();
        setcookie(LI_ANALYTICS_VISITOR_COOKIE, $visitor_id, time() + YEAR_IN_SECONDS, $cookie_path, $cookie_domain, $secure, true);
        $_COOKIE[LI_ANALYTICS_VISITOR_COOKIE] = $visitor_id;
    }

    if (empty($_COOKIE[LI_ANALYTICS_SESSION_COOKIE])) {
        $session_id = wp_generate_uuid4();
        setcookie(LI_ANALYTICS_SESSION_COOKIE, $session_id, time() + (30 * MINUTE_IN_SECONDS), $cookie_path, $cookie_domain, $secure, true);
        $_COOKIE[LI_ANALYTICS_SESSION_COOKIE] = $session_id;
    } else {
        $session_id = sanitize_text_field(wp_unslash((string) $_COOKIE[LI_ANALYTICS_SESSION_COOKIE]));
        setcookie(LI_ANALYTICS_SESSION_COOKIE, $session_id, time() + (30 * MINUTE_IN_SECONDS), $cookie_path, $cookie_domain, $secure, true);
    }
}

function li_analytics_should_track_request(): bool
{
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || is_preview() || is_robots() || is_favicon()) {
        return false;
    }

    if (defined('REST_REQUEST') && REST_REQUEST) {
        return false;
    }

    if (is_user_logged_in() && current_user_can('manage_options')) {
        return false;
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    return in_array($method, ['GET', 'HEAD'], true);
}

function li_analytics_track_page_view(): void
{
    if (!li_analytics_should_track_request() || is_404()) {
        return;
    }

    $context = li_analytics_get_current_object_context();
    $event_type = $context['object_type'] === 'product' ? 'product_view' : 'page_view';

    li_analytics_record_event($event_type, $context);
}

function li_analytics_get_current_object_context(): array
{
    $object_type = 'page';
    $object_id = 0;
    $object_name = '';

    if (is_singular()) {
        $post_id = get_queried_object_id();
        $post_type = get_post_type($post_id);
        $object_type = $post_type ? $post_type : 'post';
        $object_id = (int) $post_id;
        $object_name = get_the_title($post_id);
    } elseif (is_tax() || is_category() || is_tag()) {
        $term = get_queried_object();

        if ($term instanceof WP_Term) {
            $object_type = 'taxonomy:' . $term->taxonomy;
            $object_id = (int) $term->term_id;
            $object_name = $term->name;
        }
    } elseif (is_search()) {
        $object_type = 'search';
        $object_name = get_search_query(false);
    } elseif (is_post_type_archive()) {
        $object_type = 'archive:' . (string) get_query_var('post_type');
        $object_name = post_type_archive_title('', false);
    } elseif (is_home() || is_front_page()) {
        $object_type = 'home';
        $object_name = get_bloginfo('name');
    }

    return [
        'object_type' => $object_type,
        'object_id'   => $object_id,
        'object_name' => $object_name,
        'search_term' => is_search() ? get_search_query(false) : '',
    ];
}

function li_analytics_record_event(string $event_type, array $context = []): void
{
    global $wpdb;

    li_analytics_maybe_install();

    $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_URI'])) : '/';
    $url = esc_url_raw((string) ($context['url'] ?? ''));

    if ($url === '') {
        $url = home_url($request_uri);
    }

    $path = (string) wp_parse_url($url, PHP_URL_PATH);
    $referrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash((string) $_SERVER['HTTP_REFERER'])) : '';
    $referrer_host = $referrer ? strtolower((string) wp_parse_url($referrer, PHP_URL_HOST)) : '';
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_USER_AGENT'])) : '';
    $device = li_analytics_detect_device($user_agent);
    $browser = li_analytics_detect_browser($user_agent);
    $os = li_analytics_detect_os($user_agent);
    $request = wp_unslash($_GET);
    $meta = $context['meta'] ?? [];

    if (!is_array($meta)) {
        $meta = [];
    }

    $wpdb->insert(
        li_analytics_table_name(),
        [
            'event_date'    => current_time('mysql'),
            'event_type'    => sanitize_key($event_type),
            'object_type'   => sanitize_key((string) ($context['object_type'] ?? '')),
            'object_id'     => absint($context['object_id'] ?? 0),
            'object_name'   => sanitize_text_field((string) ($context['object_name'] ?? '')),
            'url'           => $url,
            'path'          => sanitize_text_field((string) ($context['path'] ?? $path)),
            'referrer'      => $referrer,
            'referrer_host' => sanitize_text_field($referrer_host),
            'utm_source'    => sanitize_text_field((string) ($request['utm_source'] ?? '')),
            'utm_medium'    => sanitize_text_field((string) ($request['utm_medium'] ?? '')),
            'utm_campaign'  => sanitize_text_field((string) ($request['utm_campaign'] ?? '')),
            'search_term'   => sanitize_text_field((string) ($context['search_term'] ?? ($request['s'] ?? ''))),
            'visitor_id'    => li_analytics_get_cookie_value(LI_ANALYTICS_VISITOR_COOKIE),
            'session_id'    => li_analytics_get_cookie_value(LI_ANALYTICS_SESSION_COOKIE),
            'user_id'       => get_current_user_id(),
            'ip_hash'       => li_analytics_get_ip_hash(),
            'user_agent'    => $user_agent,
            'browser'       => $browser,
            'os'            => $os,
            'device'        => $device,
            'is_bot'        => li_analytics_is_bot($user_agent) ? 1 : 0,
            'value'         => (float) ($context['value'] ?? 0),
            'quantity'      => absint($context['quantity'] ?? 0),
            'currency'      => sanitize_text_field((string) ($context['currency'] ?? (function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : ''))),
            'meta'          => $meta ? wp_json_encode($meta) : null,
        ],
        [
            '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%d', '%s', '%s',
        ]
    );
}

function li_analytics_get_cookie_value(string $cookie): string
{
    if (empty($_COOKIE[$cookie])) {
        return '';
    }

    return substr(sanitize_text_field(wp_unslash((string) $_COOKIE[$cookie])), 0, 64);
}

function li_analytics_get_ip_hash(): string
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR'])) : '';

    if ($ip === '') {
        return '';
    }

    return hash_hmac('sha256', $ip, wp_salt('nonce'));
}

function li_analytics_detect_device(string $user_agent): string
{
    if (preg_match('/tablet|ipad|playbook|silk/i', $user_agent)) {
        return 'tablet';
    }

    if (preg_match('/mobile|iphone|ipod|android|blackberry|opera mini|iemobile/i', $user_agent)) {
        return 'mobile';
    }

    return 'desktop';
}

function li_analytics_detect_browser(string $user_agent): string
{
    $browsers = [
        'Edge'    => '/Edg\//i',
        'Chrome'  => '/Chrome|CriOS/i',
        'Safari'  => '/Safari/i',
        'Firefox' => '/Firefox|FxiOS/i',
        'Opera'   => '/OPR|Opera/i',
        'IE'      => '/MSIE|Trident/i',
    ];

    foreach ($browsers as $name => $pattern) {
        if (preg_match($pattern, $user_agent)) {
            return $name;
        }
    }

    return 'Other';
}

function li_analytics_detect_os(string $user_agent): string
{
    $systems = [
        'Windows' => '/Windows NT/i',
        'macOS'   => '/Mac OS X/i',
        'iOS'     => '/iPhone|iPad|iPod/i',
        'Android' => '/Android/i',
        'Linux'   => '/Linux/i',
    ];

    foreach ($systems as $name => $pattern) {
        if (preg_match($pattern, $user_agent)) {
            return $name;
        }
    }

    return 'Other';
}

function li_analytics_is_bot(string $user_agent): bool
{
    return $user_agent !== '' && (bool) preg_match('/bot|crawl|spider|slurp|mediapartners|facebookexternalhit|preview|monitoring/i', $user_agent);
}

function li_analytics_track_add_to_cart(string $cart_item_key, int $product_id, int $quantity, int $variation_id, array $variation, array $cart_item_data): void
{
    $product = function_exists('wc_get_product') ? wc_get_product($variation_id ?: $product_id) : null;

    li_analytics_record_event('add_to_cart', [
        'object_type' => 'product',
        'object_id'   => $variation_id ?: $product_id,
        'object_name' => $product ? $product->get_name() : get_the_title($product_id),
        'quantity'    => $quantity,
        'value'       => $product ? ((float) $product->get_price() * $quantity) : 0,
        'meta'        => [
            'cart_item_key' => $cart_item_key,
            'parent_product_id' => $product_id,
        ],
    ]);
}

function li_analytics_track_order_created(int $order_id, array $posted_data, WC_Order $order): void
{
    li_analytics_record_order_event('order_created', $order);
}

function li_analytics_track_order_paid(int $order_id): void
{
    $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;

    if ($order instanceof WC_Order) {
        li_analytics_record_order_event('order_paid', $order);
    }
}

function li_analytics_track_order_completed(int $order_id): void
{
    $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;

    if ($order instanceof WC_Order) {
        li_analytics_record_order_event('order_completed', $order);
    }
}

function li_analytics_record_order_event(string $event_type, WC_Order $order): void
{
    li_analytics_record_event($event_type, [
        'object_type' => 'shop_order',
        'object_id'   => $order->get_id(),
        'object_name' => '#' . $order->get_order_number(),
        'value'       => (float) $order->get_total(),
        'quantity'    => count($order->get_items()),
        'currency'    => $order->get_currency(),
        'meta'        => [
            'status' => $order->get_status(),
            'customer_id' => $order->get_customer_id(),
            'payment_method' => $order->get_payment_method(),
        ],
    ]);
}

function li_analytics_track_document_download(int $document_id): void
{
    li_analytics_record_event('document_download', [
        'object_type' => 'li_document',
        'object_id'   => $document_id,
        'object_name' => get_the_title($document_id),
        'meta'        => [
            'access_level' => (string) get_post_meta($document_id, '_li_access_level', true),
            'is_sds' => function_exists('li_is_sds_document') && li_is_sds_document($document_id),
            'related_product_id' => (int) get_post_meta($document_id, '_li_related_product_id', true),
        ],
    ]);
}

function li_analytics_track_contact_submission(int $submission_id, string $type, array $data): void
{
    $source_url = esc_url_raw((string) ($data['source_url'] ?? ''));

    li_analytics_record_event('contact_submission', [
        'object_type' => 'li_contact_msg',
        'object_id'   => $submission_id,
        'object_name' => sanitize_text_field((string) ($data['product'] ?? $type)),
        'url'         => $source_url,
        'meta'        => [
            'type' => $type,
            'company' => sanitize_text_field((string) ($data['company'] ?? '')),
            'source_url' => $source_url,
        ],
    ]);
}

function li_analytics_get_date_range(): array
{
    $preset = isset($_GET['range']) ? sanitize_key((string) $_GET['range']) : '30';
    $days = 30;

    if ($preset === '7') {
        $days = 7;
    } elseif ($preset === '90') {
        $days = 90;
    } elseif ($preset === '365') {
        $days = 365;
    }

    if ($preset === 'custom') {
        $start = isset($_GET['start']) ? sanitize_text_field((string) $_GET['start']) : '';
        $end = isset($_GET['end']) ? sanitize_text_field((string) $_GET['end']) : '';
    } else {
        $start = gmdate('Y-m-d', strtotime('-' . ($days - 1) . ' days', current_time('timestamp')));
        $end = gmdate('Y-m-d', current_time('timestamp'));
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        $start = gmdate('Y-m-d', strtotime('-29 days', current_time('timestamp')));
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        $end = gmdate('Y-m-d', current_time('timestamp'));
    }

    return [
        'start' => $start . ' 00:00:00',
        'end'   => $end . ' 23:59:59',
        'start_date' => $start,
        'end_date' => $end,
        'preset' => $preset,
    ];
}

function li_analytics_count_events(array $range, string $event_type = '', bool $exclude_bots = true): int
{
    global $wpdb;

    $where = 'event_date BETWEEN %s AND %s';
    $params = [$range['start'], $range['end']];

    if ($event_type !== '') {
        $where .= ' AND event_type = %s';
        $params[] = $event_type;
    }

    if ($exclude_bots) {
        $where .= ' AND is_bot = 0';
    }

    return (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM ' . li_analytics_table_name() . " WHERE {$where}",
        $params
    ));
}

function li_analytics_distinct_count(array $range, string $field, string $event_type = 'page_view'): int
{
    global $wpdb;

    $allowed = ['visitor_id', 'session_id'];

    if (!in_array($field, $allowed, true)) {
        return 0;
    }

    return (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(DISTINCT ' . $field . ') FROM ' . li_analytics_table_name() . ' WHERE event_date BETWEEN %s AND %s AND event_type IN (%s, %s) AND is_bot = 0 AND ' . $field . " <> ''",
        $range['start'],
        $range['end'],
        $event_type,
        'product_view'
    ));
}

function li_analytics_get_top_rows(array $range, string $group_field, string $event_type = '', int $limit = 10): array
{
    global $wpdb;

    $allowed = ['path', 'referrer_host', 'utm_source', 'device', 'browser', 'os', 'object_name', 'search_term', 'event_type'];

    if (!in_array($group_field, $allowed, true)) {
        return [];
    }

    $where = "event_date BETWEEN %s AND %s AND is_bot = 0 AND {$group_field} <> ''";
    $params = [$range['start'], $range['end']];

    if ($event_type !== '') {
        $where .= ' AND event_type = %s';
        $params[] = $event_type;
    }

    $params[] = $limit;

    return $wpdb->get_results($wpdb->prepare(
        'SELECT ' . $group_field . ' AS label, COUNT(*) AS total, COUNT(DISTINCT visitor_id) AS visitors FROM ' . li_analytics_table_name() . " WHERE {$where} GROUP BY {$group_field} ORDER BY total DESC LIMIT %d",
        $params
    ), ARRAY_A) ?: [];
}

function li_analytics_get_daily_rows(array $range): array
{
    global $wpdb;

    return $wpdb->get_results($wpdb->prepare(
        'SELECT DATE(event_date) AS day, COUNT(*) AS views, COUNT(DISTINCT visitor_id) AS visitors FROM ' . li_analytics_table_name() . " WHERE event_date BETWEEN %s AND %s AND event_type IN ('page_view', 'product_view') AND is_bot = 0 GROUP BY DATE(event_date) ORDER BY day ASC",
        $range['start'],
        $range['end']
    ), ARRAY_A) ?: [];
}

function li_analytics_get_sales_summary(array $range): array
{
    $summary = [
        'orders' => 0,
        'revenue' => 0.0,
        'average_order' => 0.0,
        'items' => 0,
    ];

    if (!function_exists('wc_get_orders')) {
        return $summary;
    }

    $orders = wc_get_orders([
        'limit'        => -1,
        'status'       => ['wc-processing', 'wc-completed', 'wc-on-hold'],
        'date_created' => $range['start_date'] . '...' . $range['end_date'],
        'return'       => 'objects',
    ]);

    foreach ($orders as $order) {
        if (!$order instanceof WC_Order) {
            continue;
        }

        $summary['orders']++;
        $summary['revenue'] += (float) $order->get_total();
        $summary['items'] += (int) $order->get_item_count();
    }

    if ($summary['orders'] > 0) {
        $summary['average_order'] = $summary['revenue'] / $summary['orders'];
    }

    return $summary;
}

function li_render_analytics_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    li_analytics_maybe_install();

    $range = li_analytics_get_date_range();
    $sales = li_analytics_get_sales_summary($range);
    $views = li_analytics_count_events($range, '', true);
    $page_views = li_analytics_count_events($range, 'page_view', true) + li_analytics_count_events($range, 'product_view', true);
    $visitors = li_analytics_distinct_count($range, 'visitor_id');
    $sessions = li_analytics_distinct_count($range, 'session_id');
    $contact_submissions = li_analytics_count_events($range, 'contact_submission', true);
    $document_downloads = li_analytics_count_events($range, 'document_download', true);
    $cart_adds = li_analytics_count_events($range, 'add_to_cart', true);
    $bot_events = li_analytics_count_events($range, '', false) - $views;
    $daily_rows = li_analytics_get_daily_rows($range);
    $top_pages = li_analytics_get_top_rows($range, 'path', '', 12);
    $top_referrers = li_analytics_get_top_rows($range, 'referrer_host', '', 8);
    $top_products = li_analytics_get_top_rows($range, 'object_name', 'product_view', 8);
    $top_documents = li_analytics_get_top_rows($range, 'object_name', 'document_download', 8);
    $top_searches = li_analytics_get_top_rows($range, 'search_term', '', 8);
    $devices = li_analytics_get_top_rows($range, 'device', '', 6);
    $browsers = li_analytics_get_top_rows($range, 'browser', '', 6);
    $event_mix = li_analytics_get_top_rows($range, 'event_type', '', 10);
    $currency = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
    ?>
    <div class="wrap li-settings-page li-analytics-page">
        <div class="li-settings-hero">
            <div>
                <p class="li-settings-kicker"><?php esc_html_e('Performance Intelligence', 'lloyds-industrial'); ?></p>
                <h1><?php esc_html_e('Lloyds Analytics', 'lloyds-industrial'); ?></h1>
                <p><?php esc_html_e('Track sales, site traffic, product discovery, document engagement, campaigns, devices, referrers, search behavior, and inquiry volume from one native dashboard.', 'lloyds-industrial'); ?></p>
            </div>
            <div class="li-settings-summary">
                <div>
                    <span><?php esc_html_e('Revenue', 'lloyds-industrial'); ?></span>
                    <strong><?php echo esc_html($currency . number_format_i18n((float) $sales['revenue'], 2)); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Visitors', 'lloyds-industrial'); ?></span>
                    <strong><?php echo esc_html(number_format_i18n($visitors)); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Page Views', 'lloyds-industrial'); ?></span>
                    <strong><?php echo esc_html(number_format_i18n($page_views)); ?></strong>
                </div>
            </div>
        </div>

        <form class="li-analytics-filter" method="get">
            <input type="hidden" name="page" value="lloyds-analytics">
            <label>
                <span><?php esc_html_e('Range', 'lloyds-industrial'); ?></span>
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
            li_render_analytics_kpi(__('Orders', 'lloyds-industrial'), number_format_i18n((int) $sales['orders']), __('WooCommerce processing, completed, and on-hold orders.', 'lloyds-industrial'));
            li_render_analytics_kpi(__('Average Order', 'lloyds-industrial'), $currency . number_format_i18n((float) $sales['average_order'], 2), __('Revenue divided by order count.', 'lloyds-industrial'));
            li_render_analytics_kpi(__('Sessions', 'lloyds-industrial'), number_format_i18n($sessions), __('Unique 30-minute visitor sessions.', 'lloyds-industrial'));
            li_render_analytics_kpi(__('Cart Adds', 'lloyds-industrial'), number_format_i18n($cart_adds), __('Products added to cart.', 'lloyds-industrial'));
            li_render_analytics_kpi(__('Documents', 'lloyds-industrial'), number_format_i18n($document_downloads), __('Secure and public document downloads.', 'lloyds-industrial'));
            li_render_analytics_kpi(__('Inquiries', 'lloyds-industrial'), number_format_i18n($contact_submissions), __('Native contact form submissions.', 'lloyds-industrial'));
            li_render_analytics_kpi(__('Items Sold', 'lloyds-industrial'), number_format_i18n((int) $sales['items']), __('Line item quantity from tracked orders.', 'lloyds-industrial'));
            li_render_analytics_kpi(__('Bot Events', 'lloyds-industrial'), number_format_i18n(max(0, $bot_events)), __('Recorded but excluded from primary metrics.', 'lloyds-industrial'));
            ?>
        </div>

        <section class="li-analytics-panel li-analytics-panel--wide">
            <div class="li-settings-section-heading">
                <p class="li-settings-kicker"><?php esc_html_e('Trend', 'lloyds-industrial'); ?></p>
                <h2><?php esc_html_e('Daily Traffic', 'lloyds-industrial'); ?></h2>
                <p><?php esc_html_e('Page and product views with unique visitors by day.', 'lloyds-industrial'); ?></p>
            </div>
            <?php li_render_analytics_daily_table($daily_rows); ?>
        </section>

        <div class="li-analytics-grid">
            <?php
            li_render_analytics_table(__('Top Pages', 'lloyds-industrial'), $top_pages, __('Path', 'lloyds-industrial'));
            li_render_analytics_table(__('Referrers', 'lloyds-industrial'), $top_referrers, __('Host', 'lloyds-industrial'));
            li_render_analytics_table(__('Product Views', 'lloyds-industrial'), $top_products, __('Product', 'lloyds-industrial'));
            li_render_analytics_table(__('Document Downloads', 'lloyds-industrial'), $top_documents, __('Document', 'lloyds-industrial'));
            li_render_analytics_table(__('Search Terms', 'lloyds-industrial'), $top_searches, __('Term', 'lloyds-industrial'));
            li_render_analytics_table(__('Event Mix', 'lloyds-industrial'), $event_mix, __('Event', 'lloyds-industrial'));
            li_render_analytics_table(__('Devices', 'lloyds-industrial'), $devices, __('Device', 'lloyds-industrial'));
            li_render_analytics_table(__('Browsers', 'lloyds-industrial'), $browsers, __('Browser', 'lloyds-industrial'));
            ?>
        </div>
    </div>
    <?php
}

function li_render_analytics_kpi(string $label, string $value, string $description): void
{
    ?>
    <div class="li-analytics-kpi">
        <span><?php echo esc_html($label); ?></span>
        <strong><?php echo esc_html($value); ?></strong>
        <p><?php echo esc_html($description); ?></p>
    </div>
    <?php
}

function li_render_analytics_daily_table(array $rows): void
{
    ?>
    <table class="widefat striped li-analytics-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Day', 'lloyds-industrial'); ?></th>
                <th><?php esc_html_e('Views', 'lloyds-industrial'); ?></th>
                <th><?php esc_html_e('Visitors', 'lloyds-industrial'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows) : ?>
                <tr><td colspan="3"><?php esc_html_e('No traffic has been recorded for this range yet.', 'lloyds-industrial'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row) : ?>
                <tr>
                    <td><?php echo esc_html((string) $row['day']); ?></td>
                    <td><?php echo esc_html(number_format_i18n((int) $row['views'])); ?></td>
                    <td><?php echo esc_html(number_format_i18n((int) $row['visitors'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function li_render_analytics_table(string $title, array $rows, string $label_heading): void
{
    ?>
    <section class="li-analytics-panel">
        <h2><?php echo esc_html($title); ?></h2>
        <table class="widefat striped li-analytics-table">
            <thead>
                <tr>
                    <th><?php echo esc_html($label_heading); ?></th>
                    <th><?php esc_html_e('Events', 'lloyds-industrial'); ?></th>
                    <th><?php esc_html_e('Visitors', 'lloyds-industrial'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows) : ?>
                    <tr><td colspan="3"><?php esc_html_e('No data yet.', 'lloyds-industrial'); ?></td></tr>
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
    </section>
    <?php
}
