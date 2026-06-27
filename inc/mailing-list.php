<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const LI_MAIL_SETTINGS_OPTION = 'li_mailing_list_settings';
const LI_MAIL_REWRITE_VERSION_OPTION = 'li_mail_rewrite_version';

add_action('init', 'li_mail_register_content_types');
add_action('init', 'li_mail_register_routes');
add_action('after_switch_theme', 'li_mail_flush_rewrite_rules');
add_action('admin_menu', 'li_mail_register_admin_menu', 20);
add_action('admin_init', 'li_mail_register_settings');
add_action('admin_init', 'li_mail_maybe_flush_rewrite_rules');
add_action('admin_init', 'li_mail_handle_admin_actions');
add_action('admin_post_li_mail_save_campaign_builder', 'li_mail_handle_campaign_builder_save');
add_action('admin_post_li_mail_subscribe', 'li_mail_handle_subscribe');
add_action('admin_post_nopriv_li_mail_subscribe', 'li_mail_handle_subscribe');
add_action('phpmailer_init', 'li_mail_configure_smtp');
add_filter('cron_schedules', 'li_mail_register_cron_schedules');
add_action('li_mail_process_campaign_queue', 'li_mail_process_campaign_queue');
add_action('template_redirect', 'li_mail_handle_public_actions');
add_action('add_meta_boxes', 'li_mail_add_meta_boxes');
add_action('save_post_li_mail_subscriber', 'li_mail_save_subscriber_meta');
add_action('save_post_li_mail_campaign', 'li_mail_save_campaign_meta');
add_shortcode('li_mailing_list_signup', 'li_mail_render_signup_shortcode');
add_shortcode('li_newsletter_signup', 'li_mail_render_signup_shortcode');
add_shortcode('mailing_list_signup', 'li_mail_render_signup_shortcode');

function li_mail_get_settings_defaults(): array
{
    return [
        'from_name'            => get_bloginfo('name'),
        'from_email'           => get_option('admin_email'),
        'reply_to_email'       => get_option('admin_email'),
        'default_tag'          => '',
        'double_optin'         => false,
        'welcome_enabled'      => true,
        'welcome_subject'      => __('Welcome to the Lloyds mailing list', 'lloyds-industrial'),
        'welcome_message'      => __('Thank you for subscribing. We will send product updates, sales notices, promotions, and operational announcements when they are useful.', 'lloyds-industrial'),
        'confirmation_subject' => __('Confirm your Lloyds mailing list subscription', 'lloyds-industrial'),
        'confirmation_message' => __('Please confirm your subscription by opening this link: {{confirm_url}}', 'lloyds-industrial'),
        'consent_text'         => __('I agree to receive email updates from Lloyds. I can unsubscribe at any time.', 'lloyds-industrial'),
        'privacy_url'          => home_url('/privacy-policy/'),
        'max_recipients_run'   => 250,
        'queue_batch_size'     => 25,
        'queue_interval'       => 300,
        'footer_text'          => __('You are receiving this message because you subscribed to Lloyds updates.', 'lloyds-industrial'),
        'smtp_enabled'         => false,
        'smtp_host'            => '',
        'smtp_port'            => 587,
        'smtp_encryption'      => 'tls',
        'smtp_auth'            => true,
        'smtp_username'        => '',
        'smtp_password'        => '',
    ];
}

function li_mail_get_settings(): array
{
    $settings = get_option(LI_MAIL_SETTINGS_OPTION, []);

    return wp_parse_args(is_array($settings) ? $settings : [], li_mail_get_settings_defaults());
}

function li_mail_register_content_types(): void
{
    register_post_type('li_mail_subscriber', [
        'labels' => [
            'name'          => __('Mail Subscribers', 'lloyds-industrial'),
            'singular_name' => __('Mail Subscriber', 'lloyds-industrial'),
            'edit_item'     => __('Edit Subscriber', 'lloyds-industrial'),
            'add_new_item'  => __('Add Subscriber', 'lloyds-industrial'),
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => false,
        'show_in_rest'        => false,
        'supports'            => ['title'],
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'exclude_from_search' => true,
    ]);

    register_post_type('li_mail_campaign', [
        'labels' => [
            'name'          => __('Mail Campaigns', 'lloyds-industrial'),
            'singular_name' => __('Mail Campaign', 'lloyds-industrial'),
            'edit_item'     => __('Edit Campaign', 'lloyds-industrial'),
            'add_new_item'  => __('Add Campaign', 'lloyds-industrial'),
        ],
        'public'              => false,
        'show_ui'             => false,
        'show_in_menu'        => false,
        'show_in_rest'        => false,
        'supports'            => ['title', 'editor'],
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'exclude_from_search' => true,
    ]);

    register_taxonomy('li_mail_tag', ['li_mail_subscriber'], [
        'labels' => [
            'name'          => __('Mailing Tags', 'lloyds-industrial'),
            'singular_name' => __('Mailing Tag', 'lloyds-industrial'),
        ],
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => false,
        'show_in_rest' => false,
        'hierarchical' => false,
    ]);
}

function li_mail_register_routes(): void
{
    add_rewrite_rule('^mailing-list/(confirm|unsubscribe)/([^/]+)/?', 'index.php?li_mail_action=$matches[1]&li_mail_token=$matches[2]', 'top');
    add_rewrite_tag('%li_mail_action%', '([^&]+)');
    add_rewrite_tag('%li_mail_token%', '([^&]+)');
}

function li_mail_flush_rewrite_rules(): void
{
    li_mail_register_routes();
    flush_rewrite_rules();
    update_option(LI_MAIL_REWRITE_VERSION_OPTION, '1');
}

function li_mail_maybe_flush_rewrite_rules(): void
{
    if (get_option(LI_MAIL_REWRITE_VERSION_OPTION) === '1') {
        return;
    }

    li_mail_flush_rewrite_rules();
}

function li_mail_register_admin_menu(): void
{
    add_submenu_page(
        'lloyds',
        __('Mail Subscribers', 'lloyds-industrial'),
        __('Mail Subscribers', 'lloyds-industrial'),
        'edit_posts',
        'edit.php?post_type=li_mail_subscriber'
    );

    add_submenu_page(
        'lloyds',
        __('Mailing Tags', 'lloyds-industrial'),
        __('Mailing Tags', 'lloyds-industrial'),
        'manage_categories',
        'edit-tags.php?taxonomy=li_mail_tag&post_type=li_mail_subscriber'
    );

    add_submenu_page(
        'lloyds',
        __('Lloyds Mailing List', 'lloyds-industrial'),
        __('Mailing List', 'lloyds-industrial'),
        'manage_options',
        'lloyds-mailing-list',
        'li_mail_render_settings_page'
    );

    add_submenu_page(
        'lloyds',
        __('Campaign Studio', 'lloyds-industrial'),
        __('Mail Campaigns', 'lloyds-industrial'),
        'manage_options',
        'lloyds-mail-campaigns',
        'li_mail_render_campaigns_page'
    );

    add_submenu_page(
        null,
        __('Campaign Builder', 'lloyds-industrial'),
        __('Campaign Builder', 'lloyds-industrial'),
        'manage_options',
        'lloyds-mail-campaign-builder',
        'li_mail_render_campaign_builder_page'
    );

}

function li_mail_register_settings(): void
{
    register_setting('li_mailing_list_settings', LI_MAIL_SETTINGS_OPTION, [
        'type'              => 'array',
        'sanitize_callback' => 'li_mail_sanitize_settings',
        'default'           => li_mail_get_settings_defaults(),
    ]);
}

function li_mail_sanitize_settings(array $input): array
{
    $defaults = li_mail_get_settings_defaults();
    $max = absint($input['max_recipients_run'] ?? $defaults['max_recipients_run']);
    $queue_batch_size = absint($input['queue_batch_size'] ?? $defaults['queue_batch_size']);
    $queue_interval = absint($input['queue_interval'] ?? $defaults['queue_interval']);
    $smtp_port = absint($input['smtp_port'] ?? $defaults['smtp_port']);
    $smtp_encryption = sanitize_key((string) ($input['smtp_encryption'] ?? $defaults['smtp_encryption']));

    if (!in_array($smtp_encryption, ['', 'ssl', 'tls'], true)) {
        $smtp_encryption = 'tls';
    }

    return [
        'from_name'            => sanitize_text_field((string) ($input['from_name'] ?? $defaults['from_name'])),
        'from_email'           => sanitize_email((string) ($input['from_email'] ?? $defaults['from_email'])),
        'reply_to_email'       => sanitize_email((string) ($input['reply_to_email'] ?? $defaults['reply_to_email'])),
        'default_tag'          => sanitize_title((string) ($input['default_tag'] ?? '')),
        'double_optin'         => !empty($input['double_optin']),
        'welcome_enabled'      => !empty($input['welcome_enabled']),
        'welcome_subject'      => sanitize_text_field((string) ($input['welcome_subject'] ?? $defaults['welcome_subject'])),
        'welcome_message'      => wp_kses_post((string) ($input['welcome_message'] ?? $defaults['welcome_message'])),
        'confirmation_subject' => sanitize_text_field((string) ($input['confirmation_subject'] ?? $defaults['confirmation_subject'])),
        'confirmation_message' => wp_kses_post((string) ($input['confirmation_message'] ?? $defaults['confirmation_message'])),
        'consent_text'         => sanitize_text_field((string) ($input['consent_text'] ?? $defaults['consent_text'])),
        'privacy_url'          => esc_url_raw((string) ($input['privacy_url'] ?? $defaults['privacy_url'])),
        'max_recipients_run'   => min(1000, max(10, $max ?: (int) $defaults['max_recipients_run'])),
        'queue_batch_size'     => min(250, max(1, $queue_batch_size ?: (int) $defaults['queue_batch_size'])),
        'queue_interval'       => min(HOUR_IN_SECONDS, max(60, $queue_interval ?: (int) $defaults['queue_interval'])),
        'footer_text'          => wp_kses_post((string) ($input['footer_text'] ?? $defaults['footer_text'])),
        'smtp_enabled'         => !empty($input['smtp_enabled']),
        'smtp_host'            => sanitize_text_field((string) ($input['smtp_host'] ?? '')),
        'smtp_port'            => min(65535, max(1, $smtp_port ?: (int) $defaults['smtp_port'])),
        'smtp_encryption'      => $smtp_encryption,
        'smtp_auth'            => !empty($input['smtp_auth']),
        'smtp_username'        => sanitize_text_field((string) ($input['smtp_username'] ?? '')),
        'smtp_password'        => (string) ($input['smtp_password'] ?? ''),
    ];
}

function li_mail_handle_admin_actions(): void
{
    if (!current_user_can('manage_options') || empty($_GET['li_mail_action'])) {
        return;
    }

    $action = sanitize_key((string) $_GET['li_mail_action']);
    check_admin_referer('li_mail_' . $action);

    if ($action === 'send_campaign') {
        $campaign_id = absint($_GET['campaign_id'] ?? 0);
        $queued = $campaign_id ? li_mail_queue_campaign($campaign_id) : 0;
        $result = $campaign_id ? li_mail_process_campaign_queue_for_campaign($campaign_id) : ['sent' => 0, 'failed' => 0, 'remaining' => 0];
        set_transient('li_mail_notice', [
            'type' => 'campaign_queued',
            'queued' => $queued,
            'sent' => (int) $result['sent'],
            'failed' => (int) $result['failed'],
            'remaining' => (int) $result['remaining'],
        ], MINUTE_IN_SECONDS);
    } elseif ($action === 'send_test_campaign') {
        $campaign_id = absint($_GET['campaign_id'] ?? 0);
        $sent = $campaign_id ? li_mail_send_campaign_test($campaign_id) : false;
        set_transient('li_mail_notice', ['type' => $sent ? 'test_sent' : 'test_failed'], MINUTE_IN_SECONDS);
    } elseif ($action === 'export_subscribers') {
        li_mail_export_subscribers_csv();
        exit;
    } elseif ($action === 'process_queue') {
        $campaign_id = absint($_GET['campaign_id'] ?? 0);
        $result = $campaign_id ? li_mail_process_campaign_queue_for_campaign($campaign_id) : ['sent' => 0, 'failed' => 0, 'remaining' => 0];
        set_transient('li_mail_notice', [
            'type' => 'queue_processed',
            'sent' => (int) $result['sent'],
            'failed' => (int) $result['failed'],
            'remaining' => (int) $result['remaining'],
        ], MINUTE_IN_SECONDS);
    }

    wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=lloyds-mailing-list'));
    exit;
}

add_action('admin_notices', function (): void {
    $notice = get_transient('li_mail_notice');

    if (!is_array($notice)) {
        return;
    }

    delete_transient('li_mail_notice');
    $type = (string) ($notice['type'] ?? '');

    if ($type === 'campaign_queued') {
        $message = sprintf(
            __('Campaign queued. Queued: %1$d. Sent now: %2$d. Failed now: %3$d. Remaining queued: %4$d.', 'lloyds-industrial'),
            (int) ($notice['queued'] ?? 0),
            (int) ($notice['sent'] ?? 0),
            (int) ($notice['failed'] ?? 0),
            (int) ($notice['remaining'] ?? 0)
        );
        $class = 'notice-success';
    } elseif ($type === 'queue_processed') {
        $message = sprintf(
            __('Queue processed. Sent: %1$d. Failed: %2$d. Remaining queued: %3$d.', 'lloyds-industrial'),
            (int) ($notice['sent'] ?? 0),
            (int) ($notice['failed'] ?? 0),
            (int) ($notice['remaining'] ?? 0)
        );
        $class = 'notice-success';
    } elseif ($type === 'test_sent') {
        $message = __('Test email sent to the site administrator.', 'lloyds-industrial');
        $class = 'notice-success';
    } else {
        $message = __('Mailing list action could not be completed.', 'lloyds-industrial');
        $class = 'notice-error';
    }

    printf('<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
});

function li_mail_find_subscriber_by_email(string $email): ?WP_Post
{
    $query = new WP_Query([
        'post_type'      => 'li_mail_subscriber',
        'post_status'    => ['publish', 'draft', 'private'],
        'posts_per_page' => 1,
        'meta_key'       => '_li_mail_email',
        'meta_value'     => sanitize_email($email),
        'no_found_rows'  => true,
    ]);

    return $query->posts[0] ?? null;
}

function li_mail_create_or_update_subscriber(array $data): int
{
    $email = sanitize_email((string) ($data['email'] ?? ''));

    if ($email === '') {
        return 0;
    }

    $existing = li_mail_find_subscriber_by_email($email);
    $name = sanitize_text_field((string) ($data['name'] ?? ''));
    $title = $name !== '' ? $name . ' <' . $email . '>' : $email;
    $status = sanitize_key((string) ($data['status'] ?? 'active'));
    $status = in_array($status, ['active', 'pending', 'unsubscribed', 'bounced'], true) ? $status : 'active';

    if ($existing instanceof WP_Post) {
        $subscriber_id = (int) $existing->ID;
        wp_update_post([
            'ID' => $subscriber_id,
            'post_title' => $title,
        ]);
    } else {
        $subscriber_id = (int) wp_insert_post([
            'post_type'   => 'li_mail_subscriber',
            'post_status' => 'publish',
            'post_title'  => $title,
        ]);
        update_post_meta($subscriber_id, '_li_mail_subscribed_at', current_time('mysql'));
    }

    if (!$subscriber_id) {
        return 0;
    }

    $meta = [
        '_li_mail_email' => $email,
        '_li_mail_name' => $name,
        '_li_mail_company' => sanitize_text_field((string) ($data['company'] ?? '')),
        '_li_mail_phone' => sanitize_text_field((string) ($data['phone'] ?? '')),
        '_li_mail_source' => sanitize_text_field((string) ($data['source'] ?? 'manual')),
        '_li_mail_status' => $status,
        '_li_mail_token' => (string) get_post_meta($subscriber_id, '_li_mail_token', true),
        '_li_mail_last_activity' => current_time('mysql'),
    ];

    if ($meta['_li_mail_token'] === '') {
        $meta['_li_mail_token'] = wp_generate_password(32, false, false);
    }

    foreach ($meta as $key => $value) {
        update_post_meta($subscriber_id, $key, $value);
    }

    $tags = array_filter(array_map('sanitize_title', (array) ($data['tags'] ?? [])));
    $settings = li_mail_get_settings();

    if (!$tags && !empty($settings['default_tag'])) {
        $tags[] = sanitize_title((string) $settings['default_tag']);
    }

    if ($tags) {
        wp_set_object_terms($subscriber_id, $tags, 'li_mail_tag', false);
    }

    return $subscriber_id;
}

function li_mail_render_signup_shortcode(mixed $atts = []): string
{
    $settings = li_mail_get_settings();
    $atts = shortcode_atts([
        'title' => __('Subscribe to our mailing list', 'lloyds-industrial'),
        'description' => __('Get Lloyds product updates, sales announcements, promotions, and practical industrial resources.', 'lloyds-industrial'),
        'tag' => '',
        'source' => '',
        'show_company' => 'true',
        'show_name' => 'true',
        'button' => __('Subscribe', 'lloyds-industrial'),
    ], is_array($atts) ? $atts : [], 'li_mailing_list_signup');
    $status = isset($_GET['li_mail_status']) ? sanitize_key((string) $_GET['li_mail_status']) : '';
    $source_url = get_permalink() ?: home_url('/');

    ob_start();
    ?>
    <form class="li-mail-signup li-contact-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php if ((string) $atts['title'] !== '') : ?>
            <h3><?php echo esc_html((string) $atts['title']); ?></h3>
        <?php endif; ?>
        <?php if ((string) $atts['description'] !== '') : ?>
            <p><?php echo esc_html((string) $atts['description']); ?></p>
        <?php endif; ?>
        <?php if ($status === 'subscribed') : ?>
            <div class="li-contact-form__notice li-contact-form__notice--success"><?php esc_html_e('Thank you. You are subscribed.', 'lloyds-industrial'); ?></div>
        <?php elseif ($status === 'pending') : ?>
            <div class="li-contact-form__notice li-contact-form__notice--success"><?php esc_html_e('Please check your email to confirm your subscription.', 'lloyds-industrial'); ?></div>
        <?php elseif ($status === 'error') : ?>
            <div class="li-contact-form__notice li-contact-form__notice--error"><?php esc_html_e('Please enter a valid email address and accept the mailing list consent.', 'lloyds-industrial'); ?></div>
        <?php endif; ?>

        <input type="hidden" name="action" value="li_mail_subscribe">
        <input type="hidden" name="source_url" value="<?php echo esc_url($source_url); ?>">
        <input type="hidden" name="source" value="<?php echo esc_attr((string) ($atts['source'] ?: 'page_signup')); ?>">
        <input type="hidden" name="tag" value="<?php echo esc_attr(sanitize_title((string) $atts['tag'])); ?>">
        <?php wp_nonce_field('li_mail_subscribe', 'li_mail_nonce'); ?>

        <p class="li-contact-form__honeypot" aria-hidden="true">
            <label for="li_mail_website"><?php esc_html_e('Website', 'lloyds-industrial'); ?></label>
            <input id="li_mail_website" name="website" type="text" tabindex="-1" autocomplete="off">
        </p>

        <div class="li-contact-form__grid">
            <?php if ($atts['show_name'] !== 'false') : ?>
                <p>
                    <label for="li_mail_name"><?php esc_html_e('Name', 'lloyds-industrial'); ?></label>
                    <input id="li_mail_name" name="name" type="text" autocomplete="name">
                </p>
            <?php endif; ?>
            <p>
                <label for="li_mail_email"><?php esc_html_e('Email', 'lloyds-industrial'); ?> <span>*</span></label>
                <input id="li_mail_email" name="email" type="email" autocomplete="email" required>
            </p>
            <?php if ($atts['show_company'] !== 'false') : ?>
                <p>
                    <label for="li_mail_company"><?php esc_html_e('Company', 'lloyds-industrial'); ?></label>
                    <input id="li_mail_company" name="company" type="text" autocomplete="organization">
                </p>
            <?php endif; ?>
        </div>

        <p class="li-contact-form__consent">
            <label>
                <input name="consent" type="checkbox" value="1" required>
                <?php echo esc_html((string) $settings['consent_text']); ?>
            </label>
        </p>

        <?php if (!empty($settings['privacy_url'])) : ?>
            <p class="description"><a href="<?php echo esc_url((string) $settings['privacy_url']); ?>"><?php esc_html_e('Privacy policy', 'lloyds-industrial'); ?></a></p>
        <?php endif; ?>

        <button class="li-button-primary" type="submit"><?php echo esc_html((string) $atts['button']); ?></button>
    </form>
    <?php

    return (string) ob_get_clean();
}

function li_mail_handle_subscribe(): void
{
    $source_url = isset($_POST['source_url']) ? esc_url_raw(wp_unslash((string) $_POST['source_url'])) : home_url('/');
    $redirect_error = add_query_arg('li_mail_status', 'error', $source_url);

    if (!isset($_POST['li_mail_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['li_mail_nonce'])), 'li_mail_subscribe')) {
        wp_safe_redirect($redirect_error);
        exit;
    }

    if (!empty($_POST['website'])) {
        wp_safe_redirect(add_query_arg('li_mail_status', 'subscribed', $source_url));
        exit;
    }

    $email = sanitize_email(wp_unslash((string) ($_POST['email'] ?? '')));
    $consent = !empty($_POST['consent']);

    if ($email === '' || !$consent) {
        wp_safe_redirect($redirect_error);
        exit;
    }

    $settings = li_mail_get_settings();
    $status = !empty($settings['double_optin']) ? 'pending' : 'active';
    $tag = sanitize_title((string) ($_POST['tag'] ?? ''));
    $subscriber_id = li_mail_create_or_update_subscriber([
        'email' => $email,
        'name' => sanitize_text_field(wp_unslash((string) ($_POST['name'] ?? ''))),
        'company' => sanitize_text_field(wp_unslash((string) ($_POST['company'] ?? ''))),
        'source' => sanitize_text_field(wp_unslash((string) ($_POST['source'] ?? 'page_signup'))),
        'status' => $status,
        'tags' => $tag ? [$tag] : [],
    ]);

    if (!$subscriber_id) {
        wp_safe_redirect($redirect_error);
        exit;
    }

    if ($status === 'pending') {
        li_mail_send_confirmation_email($subscriber_id);
        wp_safe_redirect(add_query_arg('li_mail_status', 'pending', $source_url));
        exit;
    }

    li_mail_send_welcome_email($subscriber_id);
    wp_safe_redirect(add_query_arg('li_mail_status', 'subscribed', $source_url));
    exit;
}

function li_mail_handle_public_actions(): void
{
    $action = sanitize_key((string) get_query_var('li_mail_action'));
    $token = sanitize_text_field((string) get_query_var('li_mail_token'));

    if ($action === '' || $token === '') {
        return;
    }

    $subscriber = li_mail_find_subscriber_by_token($token);

    if (!$subscriber instanceof WP_Post) {
        wp_die(esc_html__('This mailing list link is invalid or expired.', 'lloyds-industrial'), esc_html__('Mailing List', 'lloyds-industrial'), ['response' => 404]);
    }

    if ($action === 'confirm') {
        update_post_meta($subscriber->ID, '_li_mail_status', 'active');
        update_post_meta($subscriber->ID, '_li_mail_confirmed_at', current_time('mysql'));
        li_mail_send_welcome_email((int) $subscriber->ID);
        wp_die(esc_html__('Your subscription has been confirmed. Thank you.', 'lloyds-industrial'), esc_html__('Subscription Confirmed', 'lloyds-industrial'));
    }

    if ($action === 'unsubscribe') {
        update_post_meta($subscriber->ID, '_li_mail_status', 'unsubscribed');
        update_post_meta($subscriber->ID, '_li_mail_unsubscribed_at', current_time('mysql'));
        wp_die(esc_html__('You have been unsubscribed from Lloyds mailing list emails.', 'lloyds-industrial'), esc_html__('Unsubscribed', 'lloyds-industrial'));
    }
}

function li_mail_find_subscriber_by_token(string $token): ?WP_Post
{
    $query = new WP_Query([
        'post_type'      => 'li_mail_subscriber',
        'post_status'    => ['publish', 'draft', 'private'],
        'posts_per_page' => 1,
        'meta_key'       => '_li_mail_token',
        'meta_value'     => $token,
        'no_found_rows'  => true,
    ]);

    return $query->posts[0] ?? null;
}

function li_mail_get_subscriber_token(int $subscriber_id): string
{
    $token = (string) get_post_meta($subscriber_id, '_li_mail_token', true);

    if ($token === '') {
        $token = wp_generate_password(32, false, false);
        update_post_meta($subscriber_id, '_li_mail_token', $token);
    }

    return $token;
}

function li_mail_get_unsubscribe_url(int $subscriber_id): string
{
    return home_url('/mailing-list/unsubscribe/' . li_mail_get_subscriber_token($subscriber_id) . '/');
}

function li_mail_get_confirm_url(int $subscriber_id): string
{
    return home_url('/mailing-list/confirm/' . li_mail_get_subscriber_token($subscriber_id) . '/');
}

function li_mail_get_headers(): array
{
    $settings = li_mail_get_settings();
    $from_name = sanitize_text_field((string) $settings['from_name']);
    $from_email = sanitize_email((string) $settings['from_email']);
    $reply_to = sanitize_email((string) $settings['reply_to_email']);

    return array_filter([
        'Content-Type: text/html; charset=UTF-8',
        $from_email ? 'From: ' . $from_name . ' <' . $from_email . '>' : '',
        $reply_to ? 'Reply-To: ' . $reply_to : '',
    ]);
}

function li_mail_configure_smtp(PHPMailer\PHPMailer\PHPMailer $phpmailer): void
{
    $settings = li_mail_get_settings();

    if (empty($settings['smtp_enabled']) || empty($settings['smtp_host'])) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = (string) $settings['smtp_host'];
    $phpmailer->Port = (int) $settings['smtp_port'];
    $phpmailer->SMTPAuth = !empty($settings['smtp_auth']);

    if (!empty($settings['smtp_encryption'])) {
        $phpmailer->SMTPSecure = (string) $settings['smtp_encryption'];
    }

    if ($phpmailer->SMTPAuth) {
        $phpmailer->Username = (string) $settings['smtp_username'];
        $phpmailer->Password = (string) $settings['smtp_password'];
    }
}

function li_mail_send_confirmation_email(int $subscriber_id): void
{
    $settings = li_mail_get_settings();
    $email = (string) get_post_meta($subscriber_id, '_li_mail_email', true);

    if ($email === '') {
        return;
    }

    $body = strtr((string) $settings['confirmation_message'], [
        '{{confirm_url}}' => li_mail_get_confirm_url($subscriber_id),
        '{{site_name}}' => get_bloginfo('name'),
    ]);

    wp_mail($email, (string) $settings['confirmation_subject'], wpautop($body), li_mail_get_headers());
}

function li_mail_send_welcome_email(int $subscriber_id): void
{
    $settings = li_mail_get_settings();

    if (empty($settings['welcome_enabled'])) {
        return;
    }

    $email = (string) get_post_meta($subscriber_id, '_li_mail_email', true);

    if ($email === '') {
        return;
    }

    wp_mail($email, (string) $settings['welcome_subject'], wpautop((string) $settings['welcome_message']), li_mail_get_headers());
}

function li_mail_add_meta_boxes(): void
{
    add_meta_box('li_mail_subscriber_details', __('Subscriber Details', 'lloyds-industrial'), 'li_mail_render_subscriber_metabox', 'li_mail_subscriber', 'normal', 'high');
    add_meta_box('li_mail_campaign_details', __('Campaign Settings', 'lloyds-industrial'), 'li_mail_render_campaign_metabox', 'li_mail_campaign', 'side', 'high');
    add_meta_box('li_mail_campaign_send', __('Send Campaign', 'lloyds-industrial'), 'li_mail_render_campaign_send_metabox', 'li_mail_campaign', 'side', 'default');
}

function li_mail_render_subscriber_metabox(WP_Post $post): void
{
    wp_nonce_field('li_mail_save_subscriber', 'li_mail_subscriber_nonce');
    $fields = [
        'email' => __('Email', 'lloyds-industrial'),
        'name' => __('Name', 'lloyds-industrial'),
        'company' => __('Company', 'lloyds-industrial'),
        'phone' => __('Phone', 'lloyds-industrial'),
        'source' => __('Source', 'lloyds-industrial'),
    ];
    $status = (string) get_post_meta($post->ID, '_li_mail_status', true);
    $status = in_array($status, ['active', 'pending', 'unsubscribed', 'bounced'], true) ? $status : 'active';
    ?>
    <div class="li-editor-panel li-mail-subscriber-panel">
        <div class="li-editor-panel__intro">
            <span class="dashicons dashicons-groups"></span>
            <div>
                <h2><?php esc_html_e('Subscriber Profile', 'lloyds-industrial'); ?></h2>
                <p><?php esc_html_e('Maintain subscriber contact details and delivery status for Lloyds mailing list campaigns.', 'lloyds-industrial'); ?></p>
            </div>
        </div>

        <div class="li-editor-panel__grid">
            <?php foreach ($fields as $key => $label) : ?>
                <label class="li-editor-panel__field" for="li_mail_<?php echo esc_attr($key); ?>">
                    <span><?php echo esc_html($label); ?></span>
                    <input id="li_mail_<?php echo esc_attr($key); ?>" name="li_mail_<?php echo esc_attr($key); ?>" type="<?php echo $key === 'email' ? 'email' : 'text'; ?>" value="<?php echo esc_attr((string) get_post_meta($post->ID, '_li_mail_' . $key, true)); ?>">
                </label>
            <?php endforeach; ?>
            <label class="li-editor-panel__field" for="li_mail_status">
                <span><?php esc_html_e('Status', 'lloyds-industrial'); ?></span>
                <select id="li_mail_status" name="li_mail_status">
                    <?php foreach (['active', 'pending', 'unsubscribed', 'bounced'] as $option) : ?>
                        <option value="<?php echo esc_attr($option); ?>" <?php selected($status, $option); ?>><?php echo esc_html(ucfirst($option)); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </div>
    <?php
}

function li_mail_render_campaign_metabox(WP_Post $post): void
{
    wp_nonce_field('li_mail_save_campaign', 'li_mail_campaign_nonce');
    $type = (string) get_post_meta($post->ID, '_li_mail_campaign_type', true);
    $audience_tag = (string) get_post_meta($post->ID, '_li_mail_audience_tag', true);
    $subject = (string) get_post_meta($post->ID, '_li_mail_subject', true);
    $preheader = (string) get_post_meta($post->ID, '_li_mail_preheader', true);
    $tags = get_terms(['taxonomy' => 'li_mail_tag', 'hide_empty' => false]);
    ?>
    <div class="li-editor-panel li-editor-panel--compact">
        <label class="li-editor-panel__field" for="li_mail_subject">
            <span><?php esc_html_e('Email Subject', 'lloyds-industrial'); ?></span>
            <input id="li_mail_subject" name="li_mail_subject" type="text" value="<?php echo esc_attr($subject); ?>">
        </label>
        <label class="li-editor-panel__field" for="li_mail_preheader">
            <span><?php esc_html_e('Preheader', 'lloyds-industrial'); ?></span>
            <textarea id="li_mail_preheader" name="li_mail_preheader" rows="2"><?php echo esc_textarea($preheader); ?></textarea>
        </label>
        <label class="li-editor-panel__field" for="li_mail_campaign_type">
            <span><?php esc_html_e('Campaign Type', 'lloyds-industrial'); ?></span>
            <select id="li_mail_campaign_type" name="li_mail_campaign_type">
            <?php foreach (li_mail_get_campaign_types() as $key => $label) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($type ?: 'newsletter', $key); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
            </select>
        </label>
        <label class="li-editor-panel__field" for="li_mail_audience_tag">
            <span><?php esc_html_e('Audience Tag', 'lloyds-industrial'); ?></span>
            <select id="li_mail_audience_tag" name="li_mail_audience_tag">
            <option value=""><?php esc_html_e('All active subscribers', 'lloyds-industrial'); ?></option>
            <?php if (!is_wp_error($tags)) : ?>
                <?php foreach ($tags as $tag) : ?>
                    <option value="<?php echo esc_attr($tag->slug); ?>" <?php selected($audience_tag, $tag->slug); ?>><?php echo esc_html($tag->name); ?></option>
                <?php endforeach; ?>
            <?php endif; ?>
            </select>
        </label>
        <p class="li-editor-panel__notice"><?php esc_html_e('Placeholders: {{first_name}}, {{name}}, {{email}}, {{company}}, {{site_name}}, {{unsubscribe_url}}.', 'lloyds-industrial'); ?></p>
    </div>
    <?php
}

function li_mail_render_campaign_send_metabox(WP_Post $post): void
{
    $sent = (int) get_post_meta($post->ID, '_li_mail_sent_count', true);
    $failed = (int) get_post_meta($post->ID, '_li_mail_failed_count', true);
    $last_sent = (string) get_post_meta($post->ID, '_li_mail_last_sent_at', true);
    $queued = count(li_mail_get_campaign_queue((int) $post->ID));
    $send_url = wp_nonce_url(admin_url('admin.php?page=lloyds-mailing-list&li_mail_action=send_campaign&campaign_id=' . $post->ID), 'li_mail_send_campaign');
    $process_url = wp_nonce_url(admin_url('admin.php?page=lloyds-mailing-list&li_mail_action=process_queue&campaign_id=' . $post->ID), 'li_mail_process_queue');
    $test_url = wp_nonce_url(admin_url('admin.php?page=lloyds-mailing-list&li_mail_action=send_test_campaign&campaign_id=' . $post->ID), 'li_mail_send_test_campaign');
    ?>
    <div class="li-editor-panel li-editor-panel--compact">
        <dl class="li-editor-panel__stats">
            <div><dt><?php esc_html_e('Sent', 'lloyds-industrial'); ?></dt><dd><?php echo esc_html((string) $sent); ?></dd></div>
            <div><dt><?php esc_html_e('Failed', 'lloyds-industrial'); ?></dt><dd><?php echo esc_html((string) $failed); ?></dd></div>
            <div><dt><?php esc_html_e('Queued', 'lloyds-industrial'); ?></dt><dd><?php echo esc_html((string) $queued); ?></dd></div>
            <div><dt><?php esc_html_e('Last Sent', 'lloyds-industrial'); ?></dt><dd><?php echo esc_html($last_sent ?: '-'); ?></dd></div>
        </dl>
        <div class="li-editor-panel__actions">
            <a class="button button-secondary" href="<?php echo esc_url($test_url); ?>"><?php esc_html_e('Send Test', 'lloyds-industrial'); ?></a>
            <a class="button button-primary" href="<?php echo esc_url($send_url); ?>" onclick="return confirm('<?php echo esc_js(__('Queue this campaign for scheduled delivery?', 'lloyds-industrial')); ?>');"><?php esc_html_e('Queue Campaign', 'lloyds-industrial'); ?></a>
            <a class="button button-secondary" href="<?php echo esc_url($process_url); ?>"><?php esc_html_e('Process Queue Now', 'lloyds-industrial'); ?></a>
        </div>
    </div>
    <?php
}

function li_mail_get_campaign_types(): array
{
    return [
        'newsletter' => __('Newsletter', 'lloyds-industrial'),
        'sale' => __('Sale', 'lloyds-industrial'),
        'promotion' => __('Promotion', 'lloyds-industrial'),
        'product_update' => __('Product Update', 'lloyds-industrial'),
        'announcement' => __('Announcement', 'lloyds-industrial'),
        'event' => __('Event', 'lloyds-industrial'),
        'custom' => __('Custom', 'lloyds-industrial'),
    ];
}

function li_mail_save_subscriber_meta(int $post_id): void
{
    if (!isset($_POST['li_mail_subscriber_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['li_mail_subscriber_nonce'])), 'li_mail_save_subscriber') || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $email = isset($_POST['li_mail_email']) ? sanitize_email(wp_unslash((string) $_POST['li_mail_email'])) : '';
    $status = isset($_POST['li_mail_status']) ? sanitize_key(wp_unslash((string) $_POST['li_mail_status'])) : 'active';
    $status = in_array($status, ['active', 'pending', 'unsubscribed', 'bounced'], true) ? $status : 'active';

    foreach (['email', 'name', 'company', 'phone', 'source'] as $key) {
        $value = $key === 'email' ? $email : sanitize_text_field(wp_unslash((string) ($_POST['li_mail_' . $key] ?? '')));
        update_post_meta($post_id, '_li_mail_' . $key, $value);
    }

    update_post_meta($post_id, '_li_mail_status', $status);
    li_mail_get_subscriber_token($post_id);
}

function li_mail_save_campaign_meta(int $post_id): void
{
    if (!isset($_POST['li_mail_campaign_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['li_mail_campaign_nonce'])), 'li_mail_save_campaign') || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $type = isset($_POST['li_mail_campaign_type']) ? sanitize_key(wp_unslash((string) $_POST['li_mail_campaign_type'])) : 'newsletter';

    if (!isset(li_mail_get_campaign_types()[$type])) {
        $type = 'newsletter';
    }

    update_post_meta($post_id, '_li_mail_subject', sanitize_text_field(wp_unslash((string) ($_POST['li_mail_subject'] ?? ''))));
    update_post_meta($post_id, '_li_mail_preheader', sanitize_textarea_field(wp_unslash((string) ($_POST['li_mail_preheader'] ?? ''))));
    update_post_meta($post_id, '_li_mail_campaign_type', $type);
    update_post_meta($post_id, '_li_mail_audience_tag', sanitize_title(wp_unslash((string) ($_POST['li_mail_audience_tag'] ?? ''))));
}

function li_mail_register_cron_schedules(array $schedules): array
{
    $settings = li_mail_get_settings();
    $interval = (int) ($settings['queue_interval'] ?? 300);
    $interval = min(HOUR_IN_SECONDS, max(60, $interval));

    $schedules['li_mail_queue_interval'] = [
        'interval' => $interval,
        'display'  => sprintf(__('Every %d minutes for mailing queue', 'lloyds-industrial'), max(1, (int) round($interval / 60))),
    ];

    return $schedules;
}

function li_mail_schedule_queue_runner(): void
{
    if (!wp_next_scheduled('li_mail_process_campaign_queue')) {
        wp_schedule_event(time() + MINUTE_IN_SECONDS, 'li_mail_queue_interval', 'li_mail_process_campaign_queue');
    }
}

function li_mail_unschedule_queue_runner_if_idle(): void
{
    if (li_mail_get_active_queue_campaign_ids()) {
        return;
    }

    $timestamp = wp_next_scheduled('li_mail_process_campaign_queue');

    if ($timestamp) {
        wp_unschedule_event($timestamp, 'li_mail_process_campaign_queue');
    }
}

function li_mail_get_active_queue_campaign_ids(): array
{
    return array_map('absint', (new WP_Query([
        'post_type'      => 'li_mail_campaign',
        'post_status'    => 'publish',
        'posts_per_page' => 20,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'   => '_li_mail_queue_status',
                'value' => 'queued',
            ],
        ],
    ]))->posts);
}

function li_mail_get_campaign_queue(int $campaign_id): array
{
    $queue = get_post_meta($campaign_id, '_li_mail_queue_subscribers', true);

    return is_array($queue)
        ? array_values(array_filter(array_map('absint', $queue)))
        : [];
}

function li_mail_get_campaign_recipients(int $campaign_id, int $limit = 0): array
{
    $settings = li_mail_get_settings();
    $sent_ids = array_map('absint', (array) get_post_meta($campaign_id, '_li_mail_campaign_sent_subscribers', true));
    $audience_tag = (string) get_post_meta($campaign_id, '_li_mail_audience_tag', true);
    $args = [
        'post_type'      => 'li_mail_subscriber',
        'post_status'    => 'publish',
        'posts_per_page' => $limit > 0 ? $limit : -1,
        'post__not_in'   => $sent_ids,
        'meta_query'     => [
            [
                'key' => '_li_mail_status',
                'value' => 'active',
            ],
        ],
        'fields' => 'ids',
    ];

    if ($audience_tag !== '') {
        $args['tax_query'] = [
            [
                'taxonomy' => 'li_mail_tag',
                'field' => 'slug',
                'terms' => [$audience_tag],
            ],
        ];
    }

    return array_map('absint', (new WP_Query($args))->posts);
}

function li_mail_queue_campaign(int $campaign_id): int
{
    $existing_queue = li_mail_get_campaign_queue($campaign_id);
    $eligible = li_mail_get_campaign_recipients($campaign_id);
    $queue = array_values(array_unique(array_merge($existing_queue, $eligible)));

    update_post_meta($campaign_id, '_li_mail_queue_subscribers', $queue);
    update_post_meta($campaign_id, '_li_mail_queue_status', $queue ? 'queued' : 'complete');
    update_post_meta($campaign_id, '_li_mail_queue_started_at', current_time('mysql'));

    if ($queue) {
        li_mail_schedule_queue_runner();
    }

    return count($queue);
}

function li_mail_send_campaign_test(int $campaign_id): bool
{
    $settings = li_mail_get_settings();
    $admin_email = sanitize_email((string) get_option('admin_email'));

    if ($admin_email === '') {
        return false;
    }

    return li_mail_send_campaign_email($campaign_id, 0, $admin_email, [
        'name' => __('Test Recipient', 'lloyds-industrial'),
        'email' => $admin_email,
        'company' => get_bloginfo('name'),
        'unsubscribe_url' => home_url('/'),
    ]);
}

function li_mail_process_campaign_queue(): void
{
    foreach (li_mail_get_active_queue_campaign_ids() as $campaign_id) {
        li_mail_process_campaign_queue_for_campaign($campaign_id);
    }

    li_mail_unschedule_queue_runner_if_idle();
}

function li_mail_process_campaign_queue_for_campaign(int $campaign_id): array
{
    $settings = li_mail_get_settings();
    $queue = li_mail_get_campaign_queue($campaign_id);
    $batch_size = min(250, max(1, (int) ($settings['queue_batch_size'] ?? 25)));
    $recipient_ids = array_slice($queue, 0, $batch_size);
    $sent_ids = array_map('absint', (array) get_post_meta($campaign_id, '_li_mail_campaign_sent_subscribers', true));
    $sent = 0;
    $failed = 0;
    $processed_ids = [];

    foreach ($recipient_ids as $subscriber_id) {
        $processed_ids[] = $subscriber_id;
        $email = (string) get_post_meta($subscriber_id, '_li_mail_email', true);

        if ($email === '') {
            $failed++;
            continue;
        }

        $ok = li_mail_send_campaign_email($campaign_id, $subscriber_id, $email, [
            'name' => (string) get_post_meta($subscriber_id, '_li_mail_name', true),
            'email' => $email,
            'company' => (string) get_post_meta($subscriber_id, '_li_mail_company', true),
            'unsubscribe_url' => li_mail_get_unsubscribe_url($subscriber_id),
        ]);

        if ($ok) {
            $sent++;
            $sent_ids[] = $subscriber_id;
            update_post_meta($subscriber_id, '_li_mail_last_campaign_at', current_time('mysql'));
        } else {
            $failed++;
        }
    }

    $sent_ids = array_values(array_unique(array_map('absint', $sent_ids)));
    $remaining_queue = array_values(array_diff($queue, $processed_ids));
    update_post_meta($campaign_id, '_li_mail_campaign_sent_subscribers', $sent_ids);
    update_post_meta($campaign_id, '_li_mail_queue_subscribers', $remaining_queue);
    update_post_meta($campaign_id, '_li_mail_queue_status', $remaining_queue ? 'queued' : 'complete');
    update_post_meta($campaign_id, '_li_mail_sent_count', (int) get_post_meta($campaign_id, '_li_mail_sent_count', true) + $sent);
    update_post_meta($campaign_id, '_li_mail_failed_count', (int) get_post_meta($campaign_id, '_li_mail_failed_count', true) + $failed);
    update_post_meta($campaign_id, '_li_mail_last_sent_at', current_time('mysql'));

    if (!$remaining_queue) {
        update_post_meta($campaign_id, '_li_mail_queue_completed_at', current_time('mysql'));
    }

    return [
        'sent' => $sent,
        'failed' => $failed,
        'remaining' => count($remaining_queue),
    ];
}

function li_mail_send_campaign_email(int $campaign_id, int $subscriber_id, string $email, array $recipient): bool
{
    $subject = (string) get_post_meta($campaign_id, '_li_mail_subject', true);

    if ($subject === '') {
        $subject = get_the_title($campaign_id);
    }

    $content = apply_filters('the_content', get_post_field('post_content', $campaign_id));
    $preheader = (string) get_post_meta($campaign_id, '_li_mail_preheader', true);
    $settings = li_mail_get_settings();
    $name = trim((string) ($recipient['name'] ?? ''));
    $first_name = $name !== '' ? strtok($name, ' ') : '';
    $replacements = [
        '{{name}}' => $name,
        '{{first_name}}' => (string) $first_name,
        '{{email}}' => (string) ($recipient['email'] ?? $email),
        '{{company}}' => (string) ($recipient['company'] ?? ''),
        '{{site_name}}' => get_bloginfo('name'),
        '{{unsubscribe_url}}' => (string) ($recipient['unsubscribe_url'] ?? home_url('/')),
    ];
    $body = strtr($content, $replacements);
    $footer = strtr((string) $settings['footer_text'], $replacements);
    $html = '<div style="display:none;max-height:0;overflow:hidden;">' . esc_html($preheader) . '</div>';
    $html .= '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#182126;">' . $body . '</div>';
    $html .= '<hr><p style="font-size:12px;color:#66736f;">' . wp_kses_post($footer) . '<br><a href="' . esc_url((string) $replacements['{{unsubscribe_url}}']) . '">' . esc_html__('Unsubscribe', 'lloyds-industrial') . '</a></p>';

    return wp_mail($email, strtr($subject, $replacements), $html, li_mail_get_headers());
}

function li_mail_render_settings_page(): void
{
    $settings = li_mail_get_settings();
    $subscriber_count = li_mail_count_subscribers('active');
    $pending_count = li_mail_count_subscribers('pending');
    $campaign_count = wp_count_posts('li_mail_campaign');
    $campaigns_url = admin_url('admin.php?page=lloyds-mail-campaigns');
    $subscribers_url = admin_url('edit.php?post_type=li_mail_subscriber');
    $export_url = wp_nonce_url(admin_url('admin.php?page=lloyds-mailing-list&li_mail_action=export_subscribers'), 'li_mail_export_subscribers');
    ?>
    <div class="wrap li-settings-page li-mail-page">
        <div class="li-settings-hero">
            <div>
                <p class="li-settings-kicker"><?php esc_html_e('Audience and Campaigns', 'lloyds-industrial'); ?></p>
                <h1><?php esc_html_e('Lloyds Mailing List', 'lloyds-industrial'); ?></h1>
                <p><?php esc_html_e('Build subscriber lists, embed signup forms, manage consent, segment audiences, and send sales, promotional, product, and announcement campaigns.', 'lloyds-industrial'); ?></p>
            </div>
            <div class="li-settings-summary">
                <div><span><?php esc_html_e('Active', 'lloyds-industrial'); ?></span><strong><?php echo esc_html(number_format_i18n($subscriber_count)); ?></strong></div>
                <div><span><?php esc_html_e('Pending', 'lloyds-industrial'); ?></span><strong><?php echo esc_html(number_format_i18n($pending_count)); ?></strong></div>
                <div><span><?php esc_html_e('Campaigns', 'lloyds-industrial'); ?></span><strong><?php echo esc_html(number_format_i18n((int) ($campaign_count->publish ?? 0))); ?></strong></div>
            </div>
        </div>

        <p class="li-seo-actions">
            <a class="button button-secondary" href="<?php echo esc_url($subscribers_url); ?>"><?php esc_html_e('View Subscribers', 'lloyds-industrial'); ?></a>
            <a class="button button-secondary" href="<?php echo esc_url($campaigns_url); ?>"><?php esc_html_e('View Campaigns', 'lloyds-industrial'); ?></a>
            <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=lloyds-mail-campaign-builder')); ?>"><?php esc_html_e('Create Campaign', 'lloyds-industrial'); ?></a>
            <a class="button button-secondary" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Export Subscribers', 'lloyds-industrial'); ?></a>
        </p>

        <form class="li-settings-form" method="post" action="options.php">
            <?php settings_fields('li_mailing_list_settings'); ?>

            <h2><?php esc_html_e('Sender Identity', 'lloyds-industrial'); ?></h2>
            <p><?php esc_html_e('Configure the default sender used for confirmations, welcome messages, and campaigns.', 'lloyds-industrial'); ?></p>
            <table class="form-table" role="presentation"><tbody>
                <?php li_mail_render_text_row('from_name', __('From name', 'lloyds-industrial'), $settings); ?>
                <?php li_mail_render_email_row('from_email', __('From email', 'lloyds-industrial'), $settings); ?>
                <?php li_mail_render_email_row('reply_to_email', __('Reply-to email', 'lloyds-industrial'), $settings); ?>
                <?php li_mail_render_text_row('default_tag', __('Default subscriber tag', 'lloyds-industrial'), $settings); ?>
            </tbody></table>

            <h2><?php esc_html_e('Signup and Consent', 'lloyds-industrial'); ?></h2>
            <p><?php esc_html_e('Use the shortcode on any page, pattern, product, or reusable block: [li_mailing_list_signup]', 'lloyds-industrial'); ?></p>
            <table class="form-table" role="presentation"><tbody>
                <?php li_mail_render_checkbox_row('double_optin', __('Require email confirmation', 'lloyds-industrial'), $settings); ?>
                <?php li_mail_render_text_row('consent_text', __('Consent text', 'lloyds-industrial'), $settings); ?>
                <?php li_mail_render_text_row('privacy_url', __('Privacy policy URL', 'lloyds-industrial'), $settings); ?>
            </tbody></table>

            <h2><?php esc_html_e('Automated Emails', 'lloyds-industrial'); ?></h2>
            <p><?php esc_html_e('Customize confirmation and welcome messages. Confirmation messages can use {{confirm_url}}.', 'lloyds-industrial'); ?></p>
            <table class="form-table" role="presentation"><tbody>
                <?php li_mail_render_checkbox_row('welcome_enabled', __('Send welcome email', 'lloyds-industrial'), $settings); ?>
                <?php li_mail_render_text_row('welcome_subject', __('Welcome subject', 'lloyds-industrial'), $settings); ?>
                <?php li_mail_render_textarea_row('welcome_message', __('Welcome message', 'lloyds-industrial'), $settings); ?>
                <?php li_mail_render_text_row('confirmation_subject', __('Confirmation subject', 'lloyds-industrial'), $settings); ?>
                <?php li_mail_render_textarea_row('confirmation_message', __('Confirmation message', 'lloyds-industrial'), $settings); ?>
            </tbody></table>

            <h2><?php esc_html_e('Campaign Delivery', 'lloyds-industrial'); ?></h2>
            <p><?php esc_html_e('Queued delivery sends small batches through WP-Cron so mail providers and local servers are not hit with the entire list at once.', 'lloyds-industrial'); ?></p>
            <table class="form-table" role="presentation"><tbody>
                <?php li_mail_render_number_row('queue_batch_size', __('Emails per queue batch', 'lloyds-industrial'), $settings, 1, 250); ?>
                <?php li_mail_render_number_row('queue_interval', __('Queue interval seconds', 'lloyds-industrial'), $settings, 60, HOUR_IN_SECONDS); ?>
                <?php li_mail_render_textarea_row('footer_text', __('Campaign footer text', 'lloyds-industrial'), $settings); ?>
            </tbody></table>

            <h2><?php esc_html_e('Optional SMTP Provider', 'lloyds-industrial'); ?></h2>
            <p><?php esc_html_e('When enabled and configured, all WordPress mail will use this SMTP transport. Leave disabled to use the server default.', 'lloyds-industrial'); ?></p>
            <table class="form-table" role="presentation"><tbody>
                <?php li_mail_render_checkbox_row('smtp_enabled', __('Use SMTP provider', 'lloyds-industrial'), $settings); ?>
                <?php li_mail_render_text_row('smtp_host', __('SMTP host', 'lloyds-industrial'), $settings); ?>
                <?php li_mail_render_number_row('smtp_port', __('SMTP port', 'lloyds-industrial'), $settings, 1, 65535); ?>
                <?php li_mail_render_select_row('smtp_encryption', __('Encryption', 'lloyds-industrial'), $settings, ['tls' => 'TLS', 'ssl' => 'SSL', '' => __('None', 'lloyds-industrial')]); ?>
                <?php li_mail_render_checkbox_row('smtp_auth', __('SMTP authentication', 'lloyds-industrial'), $settings); ?>
                <?php li_mail_render_text_row('smtp_username', __('SMTP username', 'lloyds-industrial'), $settings); ?>
                <?php li_mail_render_password_row('smtp_password', __('SMTP password', 'lloyds-industrial'), $settings); ?>
            </tbody></table>

            <?php submit_button(__('Save Mailing List Settings', 'lloyds-industrial')); ?>
        </form>
    </div>
    <?php
}

function li_mail_count_subscribers(string $status): int
{
    return (int) (new WP_Query([
        'post_type' => 'li_mail_subscriber',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_key' => '_li_mail_status',
        'meta_value' => $status,
    ]))->found_posts;
}

function li_mail_export_subscribers_csv(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to export subscribers.', 'lloyds-industrial'), esc_html__('Mailing List', 'lloyds-industrial'), ['response' => 403]);
    }

    $query = new WP_Query([
        'post_type'      => 'li_mail_subscriber',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=lloyds-mail-subscribers-' . gmdate('Y-m-d') . '.csv');

    $out = fopen('php://output', 'w');

    if (!$out) {
        exit;
    }

    fputcsv($out, ['email', 'name', 'company', 'phone', 'status', 'source', 'tags', 'subscribed_at', 'last_campaign_at']);

    foreach ($query->posts as $post) {
        $subscriber_id = (int) $post->ID;
        $terms = get_the_terms($subscriber_id, 'li_mail_tag');
        $tags = is_array($terms) ? implode('|', wp_list_pluck($terms, 'name')) : '';
        fputcsv($out, [
            (string) get_post_meta($subscriber_id, '_li_mail_email', true),
            (string) get_post_meta($subscriber_id, '_li_mail_name', true),
            (string) get_post_meta($subscriber_id, '_li_mail_company', true),
            (string) get_post_meta($subscriber_id, '_li_mail_phone', true),
            (string) get_post_meta($subscriber_id, '_li_mail_status', true),
            (string) get_post_meta($subscriber_id, '_li_mail_source', true),
            $tags,
            (string) get_post_meta($subscriber_id, '_li_mail_subscribed_at', true),
            (string) get_post_meta($subscriber_id, '_li_mail_last_campaign_at', true),
        ]);
    }

    fclose($out);
    exit;
}

function li_mail_handle_campaign_builder_save(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to save campaigns.', 'lloyds-industrial'), esc_html__('Mailing List', 'lloyds-industrial'), ['response' => 403]);
    }

    check_admin_referer('li_mail_save_campaign_builder');

    $campaign_id = absint($_POST['campaign_id'] ?? 0);
    $title = sanitize_text_field(wp_unslash((string) ($_POST['campaign_title'] ?? '')));
    $content = wp_kses_post(wp_unslash((string) ($_POST['campaign_content'] ?? '')));

    if ($title === '') {
        $title = __('Untitled Campaign', 'lloyds-industrial');
    }

    $post_data = [
        'post_type' => 'li_mail_campaign',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_content' => $content,
    ];

    if ($campaign_id) {
        $post_data['ID'] = $campaign_id;
        $campaign_id = (int) wp_update_post($post_data);
    } else {
        $campaign_id = (int) wp_insert_post($post_data);
    }

    if (!$campaign_id) {
        wp_safe_redirect(admin_url('admin.php?page=lloyds-mail-campaign-builder&li_mail_saved=0'));
        exit;
    }

    $type = sanitize_key(wp_unslash((string) ($_POST['campaign_type'] ?? 'newsletter')));

    if (!isset(li_mail_get_campaign_types()[$type])) {
        $type = 'newsletter';
    }

    update_post_meta($campaign_id, '_li_mail_subject', sanitize_text_field(wp_unslash((string) ($_POST['campaign_subject'] ?? ''))));
    update_post_meta($campaign_id, '_li_mail_preheader', sanitize_textarea_field(wp_unslash((string) ($_POST['campaign_preheader'] ?? ''))));
    update_post_meta($campaign_id, '_li_mail_campaign_type', $type);
    update_post_meta($campaign_id, '_li_mail_audience_tag', sanitize_title(wp_unslash((string) ($_POST['campaign_audience_tag'] ?? ''))));

    wp_safe_redirect(admin_url('admin.php?page=lloyds-mail-campaign-builder&campaign_id=' . $campaign_id . '&li_mail_saved=1'));
    exit;
}

function li_mail_render_campaigns_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $campaigns = new WP_Query([
        'post_type' => 'li_mail_campaign',
        'post_status' => ['publish', 'draft'],
        'posts_per_page' => 50,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
    ?>
    <div class="wrap li-settings-page li-mail-studio">
        <div class="li-settings-hero">
            <div>
                <p class="li-settings-kicker"><?php esc_html_e('Campaign Studio', 'lloyds-industrial'); ?></p>
                <h1><?php esc_html_e('Mail Campaigns', 'lloyds-industrial'); ?></h1>
                <p><?php esc_html_e('Create, preview, test, and send campaign blasts without using the WordPress page editor.', 'lloyds-industrial'); ?></p>
            </div>
            <div class="li-settings-summary">
                <div><span><?php esc_html_e('Shortcode', 'lloyds-industrial'); ?></span><strong>[li_mailing_list_signup]</strong></div>
                <div><span><?php esc_html_e('Campaigns', 'lloyds-industrial'); ?></span><strong><?php echo esc_html(number_format_i18n((int) $campaigns->found_posts)); ?></strong></div>
                <div><span><?php esc_html_e('Transport', 'lloyds-industrial'); ?></span><strong><?php echo esc_html(!empty(li_mail_get_settings()['smtp_enabled']) ? __('SMTP', 'lloyds-industrial') : __('Default', 'lloyds-industrial')); ?></strong></div>
            </div>
        </div>

        <p class="li-seo-actions">
            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=lloyds-mail-campaign-builder')); ?>"><?php esc_html_e('New Campaign', 'lloyds-industrial'); ?></a>
            <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=lloyds-mailing-list')); ?>"><?php esc_html_e('Mail Settings', 'lloyds-industrial'); ?></a>
        </p>

        <div class="li-mail-campaign-list">
            <?php if (!$campaigns->posts) : ?>
                <div class="li-mail-empty-state">
                    <h2><?php esc_html_e('No campaigns yet', 'lloyds-industrial'); ?></h2>
                    <p><?php esc_html_e('Create the first campaign for sales, promotions, product updates, or management announcements.', 'lloyds-industrial'); ?></p>
                </div>
            <?php endif; ?>
            <?php foreach ($campaigns->posts as $campaign) : ?>
                <?php
                $campaign_id = (int) $campaign->ID;
                $type = (string) get_post_meta($campaign_id, '_li_mail_campaign_type', true);
                $types = li_mail_get_campaign_types();
                $sent = (int) get_post_meta($campaign_id, '_li_mail_sent_count', true);
                $failed = (int) get_post_meta($campaign_id, '_li_mail_failed_count', true);
                $queued = count(li_mail_get_campaign_queue($campaign_id));
                $last_sent = (string) get_post_meta($campaign_id, '_li_mail_last_sent_at', true);
                ?>
                <article class="li-mail-campaign-card">
                    <div>
                        <span><?php echo esc_html($types[$type] ?? __('Newsletter', 'lloyds-industrial')); ?></span>
                        <h2><?php echo esc_html(get_the_title($campaign_id)); ?></h2>
                        <p><?php echo esc_html((string) get_post_meta($campaign_id, '_li_mail_subject', true) ?: __('No subject set', 'lloyds-industrial')); ?></p>
                    </div>
                    <dl>
                        <div><dt><?php esc_html_e('Sent', 'lloyds-industrial'); ?></dt><dd><?php echo esc_html((string) $sent); ?></dd></div>
                        <div><dt><?php esc_html_e('Failed', 'lloyds-industrial'); ?></dt><dd><?php echo esc_html((string) $failed); ?></dd></div>
                        <div><dt><?php esc_html_e('Queued', 'lloyds-industrial'); ?></dt><dd><?php echo esc_html((string) $queued); ?></dd></div>
                    </dl>
                    <p>
                        <a class="button button-secondary" href="<?php echo esc_url(admin_url('admin.php?page=lloyds-mail-campaign-builder&campaign_id=' . $campaign_id)); ?>"><?php esc_html_e('Open Builder', 'lloyds-industrial'); ?></a>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

function li_mail_render_campaign_builder_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $campaign_id = absint($_GET['campaign_id'] ?? 0);
    $campaign = $campaign_id ? get_post($campaign_id) : null;
    $title = $campaign instanceof WP_Post ? $campaign->post_title : '';
    $content = $campaign instanceof WP_Post ? $campaign->post_content : '';
    $subject = $campaign_id ? (string) get_post_meta($campaign_id, '_li_mail_subject', true) : '';
    $preheader = $campaign_id ? (string) get_post_meta($campaign_id, '_li_mail_preheader', true) : '';
    $type = $campaign_id ? (string) get_post_meta($campaign_id, '_li_mail_campaign_type', true) : 'newsletter';
    $audience_tag = $campaign_id ? (string) get_post_meta($campaign_id, '_li_mail_audience_tag', true) : '';
    $tags = get_terms(['taxonomy' => 'li_mail_tag', 'hide_empty' => false]);
    $sent = $campaign_id ? (int) get_post_meta($campaign_id, '_li_mail_sent_count', true) : 0;
    $failed = $campaign_id ? (int) get_post_meta($campaign_id, '_li_mail_failed_count', true) : 0;
    $queued = $campaign_id ? count(li_mail_get_campaign_queue($campaign_id)) : 0;
    $send_url = $campaign_id ? wp_nonce_url(admin_url('admin.php?page=lloyds-mailing-list&li_mail_action=send_campaign&campaign_id=' . $campaign_id), 'li_mail_send_campaign') : '';
    $process_url = $campaign_id ? wp_nonce_url(admin_url('admin.php?page=lloyds-mailing-list&li_mail_action=process_queue&campaign_id=' . $campaign_id), 'li_mail_process_queue') : '';
    $test_url = $campaign_id ? wp_nonce_url(admin_url('admin.php?page=lloyds-mailing-list&li_mail_action=send_test_campaign&campaign_id=' . $campaign_id), 'li_mail_send_test_campaign') : '';
    ?>
    <div class="wrap li-settings-page li-mail-builder-page">
        <div class="li-settings-hero">
            <div>
                <p class="li-settings-kicker"><?php esc_html_e('Campaign Builder', 'lloyds-industrial'); ?></p>
                <h1><?php echo esc_html($campaign_id ? __('Edit Campaign', 'lloyds-industrial') : __('Create Campaign', 'lloyds-industrial')); ?></h1>
                <p><?php esc_html_e('Build a polished email blast with audience targeting, test sending, and delivery controls in one focused workspace.', 'lloyds-industrial'); ?></p>
            </div>
            <div class="li-settings-summary">
                <div><span><?php esc_html_e('Sent', 'lloyds-industrial'); ?></span><strong><?php echo esc_html((string) $sent); ?></strong></div>
                <div><span><?php esc_html_e('Failed', 'lloyds-industrial'); ?></span><strong><?php echo esc_html((string) $failed); ?></strong></div>
                <div><span><?php esc_html_e('Queued', 'lloyds-industrial'); ?></span><strong><?php echo esc_html((string) $queued); ?></strong></div>
            </div>
        </div>

        <?php if (isset($_GET['li_mail_saved'])) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Campaign saved.', 'lloyds-industrial'); ?></p></div>
        <?php endif; ?>

        <form class="li-mail-builder" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="li_mail_save_campaign_builder">
            <input type="hidden" name="campaign_id" value="<?php echo esc_attr((string) $campaign_id); ?>">
            <?php wp_nonce_field('li_mail_save_campaign_builder'); ?>

            <section class="li-mail-builder-main">
                <label>
                    <span><?php esc_html_e('Campaign Name', 'lloyds-industrial'); ?></span>
                    <input type="text" name="campaign_title" value="<?php echo esc_attr($title); ?>" placeholder="<?php esc_attr_e('Spring promotion, Q3 product update...', 'lloyds-industrial'); ?>">
                </label>
                <label>
                    <span><?php esc_html_e('Email Subject', 'lloyds-industrial'); ?></span>
                    <input type="text" name="campaign_subject" value="<?php echo esc_attr($subject); ?>" placeholder="<?php esc_attr_e('A clear, specific subject line', 'lloyds-industrial'); ?>">
                </label>
                <label>
                    <span><?php esc_html_e('Preheader', 'lloyds-industrial'); ?></span>
                    <textarea name="campaign_preheader" rows="2" placeholder="<?php esc_attr_e('Short inbox preview text', 'lloyds-industrial'); ?>"><?php echo esc_textarea($preheader); ?></textarea>
                </label>
                <label>
                    <span><?php esc_html_e('Message Body', 'lloyds-industrial'); ?></span>
                    <textarea class="li-mail-builder-body" name="campaign_content" rows="18" placeholder="<?php esc_attr_e('Write the campaign email here. Basic HTML is supported.', 'lloyds-industrial'); ?>"><?php echo esc_textarea($content); ?></textarea>
                </label>
            </section>

            <aside class="li-mail-builder-side">
                <section>
                    <h2><?php esc_html_e('Audience', 'lloyds-industrial'); ?></h2>
                    <label>
                        <span><?php esc_html_e('Campaign Type', 'lloyds-industrial'); ?></span>
                        <select name="campaign_type">
                            <?php foreach (li_mail_get_campaign_types() as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($type ?: 'newsletter', $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Audience Tag', 'lloyds-industrial'); ?></span>
                        <select name="campaign_audience_tag">
                            <option value=""><?php esc_html_e('All active subscribers', 'lloyds-industrial'); ?></option>
                            <?php if (!is_wp_error($tags)) : ?>
                                <?php foreach ($tags as $tag) : ?>
                                    <option value="<?php echo esc_attr($tag->slug); ?>" <?php selected($audience_tag, $tag->slug); ?>><?php echo esc_html($tag->name); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </label>
                </section>

                <section>
                    <h2><?php esc_html_e('Personalization', 'lloyds-industrial'); ?></h2>
                    <p><code>{{first_name}}</code> <code>{{name}}</code> <code>{{email}}</code> <code>{{company}}</code> <code>{{site_name}}</code> <code>{{unsubscribe_url}}</code></p>
                </section>

                <section>
                    <h2><?php esc_html_e('Actions', 'lloyds-industrial'); ?></h2>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Save Campaign', 'lloyds-industrial'); ?></button>
                    <?php if ($campaign_id) : ?>
                        <a class="button button-secondary" href="<?php echo esc_url($test_url); ?>"><?php esc_html_e('Send Test', 'lloyds-industrial'); ?></a>
                        <a class="button button-secondary" href="<?php echo esc_url($send_url); ?>" onclick="return confirm('<?php echo esc_js(__('Queue this campaign for scheduled delivery?', 'lloyds-industrial')); ?>');"><?php esc_html_e('Queue Campaign', 'lloyds-industrial'); ?></a>
                        <?php if ($queued > 0) : ?>
                            <a class="button button-secondary" href="<?php echo esc_url($process_url); ?>"><?php esc_html_e('Process Queue Now', 'lloyds-industrial'); ?></a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a class="button button-link" href="<?php echo esc_url(admin_url('admin.php?page=lloyds-mail-campaigns')); ?>"><?php esc_html_e('Back to Campaigns', 'lloyds-industrial'); ?></a>
                </section>
            </aside>
        </form>
    </div>
    <?php
}

function li_mail_render_text_row(string $key, string $label, array $settings): void
{
    ?>
    <tr><th scope="row"><label for="li_mail_setting_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><input class="regular-text" id="li_mail_setting_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(LI_MAIL_SETTINGS_OPTION); ?>[<?php echo esc_attr($key); ?>]" type="text" value="<?php echo esc_attr((string) ($settings[$key] ?? '')); ?>"></td></tr>
    <?php
}

function li_mail_render_email_row(string $key, string $label, array $settings): void
{
    ?>
    <tr><th scope="row"><label for="li_mail_setting_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><input class="regular-text" id="li_mail_setting_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(LI_MAIL_SETTINGS_OPTION); ?>[<?php echo esc_attr($key); ?>]" type="email" value="<?php echo esc_attr((string) ($settings[$key] ?? '')); ?>"></td></tr>
    <?php
}

function li_mail_render_number_row(string $key, string $label, array $settings, int $min, int $max): void
{
    ?>
    <tr><th scope="row"><label for="li_mail_setting_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><input class="small-text" id="li_mail_setting_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(LI_MAIL_SETTINGS_OPTION); ?>[<?php echo esc_attr($key); ?>]" type="number" min="<?php echo esc_attr((string) $min); ?>" max="<?php echo esc_attr((string) $max); ?>" value="<?php echo esc_attr((string) ($settings[$key] ?? '')); ?>"></td></tr>
    <?php
}

function li_mail_render_textarea_row(string $key, string $label, array $settings): void
{
    ?>
    <tr><th scope="row"><label for="li_mail_setting_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><textarea class="large-text" id="li_mail_setting_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(LI_MAIL_SETTINGS_OPTION); ?>[<?php echo esc_attr($key); ?>]" rows="4"><?php echo esc_textarea((string) ($settings[$key] ?? '')); ?></textarea></td></tr>
    <?php
}

function li_mail_render_checkbox_row(string $key, string $label, array $settings): void
{
    ?>
    <tr><th scope="row"><?php echo esc_html($label); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(LI_MAIL_SETTINGS_OPTION); ?>[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($settings[$key])); ?>> <?php esc_html_e('Enabled', 'lloyds-industrial'); ?></label></td></tr>
    <?php
}

function li_mail_render_password_row(string $key, string $label, array $settings): void
{
    ?>
    <tr><th scope="row"><label for="li_mail_setting_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><input class="regular-text" id="li_mail_setting_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(LI_MAIL_SETTINGS_OPTION); ?>[<?php echo esc_attr($key); ?>]" type="password" value="<?php echo esc_attr((string) ($settings[$key] ?? '')); ?>" autocomplete="new-password"></td></tr>
    <?php
}

function li_mail_render_select_row(string $key, string $label, array $settings, array $options): void
{
    ?>
    <tr>
        <th scope="row"><label for="li_mail_setting_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
        <td>
            <select id="li_mail_setting_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(LI_MAIL_SETTINGS_OPTION); ?>[<?php echo esc_attr($key); ?>]">
                <?php foreach ($options as $value => $option_label) : ?>
                    <option value="<?php echo esc_attr((string) $value); ?>" <?php selected((string) ($settings[$key] ?? ''), (string) $value); ?>><?php echo esc_html((string) $option_label); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <?php
}

add_filter('manage_li_mail_subscriber_posts_columns', function (array $columns): array {
    return [
        'cb' => $columns['cb'] ?? '',
        'title' => __('Subscriber', 'lloyds-industrial'),
        'li_mail_email' => __('Email', 'lloyds-industrial'),
        'li_mail_status' => __('Status', 'lloyds-industrial'),
        'li_mail_company' => __('Company', 'lloyds-industrial'),
        'li_mail_tags' => __('Tags', 'lloyds-industrial'),
        'date' => $columns['date'] ?? __('Date', 'lloyds-industrial'),
    ];
});

add_action('manage_li_mail_subscriber_posts_custom_column', function (string $column, int $post_id): void {
    if ($column === 'li_mail_email') {
        $email = (string) get_post_meta($post_id, '_li_mail_email', true);
        echo $email ? '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>' : '-';
    } elseif ($column === 'li_mail_status') {
        echo esc_html(ucfirst((string) get_post_meta($post_id, '_li_mail_status', true) ?: 'active'));
    } elseif ($column === 'li_mail_company') {
        echo esc_html((string) get_post_meta($post_id, '_li_mail_company', true) ?: '-');
    } elseif ($column === 'li_mail_tags') {
        $terms = get_the_terms($post_id, 'li_mail_tag');
        echo esc_html(is_array($terms) ? implode(', ', wp_list_pluck($terms, 'name')) : '-');
    }
}, 10, 2);

add_filter('manage_li_mail_campaign_posts_columns', function (array $columns): array {
    return [
        'cb' => $columns['cb'] ?? '',
        'title' => __('Campaign', 'lloyds-industrial'),
        'li_mail_subject' => __('Subject', 'lloyds-industrial'),
        'li_mail_type' => __('Type', 'lloyds-industrial'),
        'li_mail_sent' => __('Sent', 'lloyds-industrial'),
        'date' => $columns['date'] ?? __('Date', 'lloyds-industrial'),
    ];
});

add_action('manage_li_mail_campaign_posts_custom_column', function (string $column, int $post_id): void {
    if ($column === 'li_mail_subject') {
        echo esc_html((string) get_post_meta($post_id, '_li_mail_subject', true) ?: '-');
    } elseif ($column === 'li_mail_type') {
        $types = li_mail_get_campaign_types();
        $type = (string) get_post_meta($post_id, '_li_mail_campaign_type', true);
        echo esc_html($types[$type] ?? __('Newsletter', 'lloyds-industrial'));
    } elseif ($column === 'li_mail_sent') {
        echo esc_html((string) ((int) get_post_meta($post_id, '_li_mail_sent_count', true)));
    }
}, 10, 2);
