<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function li_get_contact_form_types(): array
{
    return [
        'product_support' => __('Product Support', 'lloyds-industrial'),
        'sds_access'      => __('SDS Access Help', 'lloyds-industrial'),
        'quote'           => __('Quote Request', 'lloyds-industrial'),
        'orders'          => __('Order Desk', 'lloyds-industrial'),
        'distributor'     => __('Distributor Inquiry', 'lloyds-industrial'),
        'accounting'      => __('Accounting', 'lloyds-industrial'),
        'general'         => __('General Contact', 'lloyds-industrial'),
    ];
}

function li_get_contact_form_settings(): array
{
    $defaults = [
        'default_recipient'     => get_option('admin_email'),
        'product_support_email' => 'Marketing@lloydslaboratories.com',
        'sds_access_email'      => 'Marketing@lloydslaboratories.com',
        'quote_email'           => 'Orderdesk@lloydslaboratories.com',
        'orders_email'          => 'Orderdesk@lloydslaboratories.com',
        'distributor_email'     => 'Marketing@lloydslaboratories.com',
        'accounting_email'      => 'Payables@lloydslaboratories.com',
        'general_email'         => get_option('admin_email'),
        'confirmation_subject'  => __('We received your Lloyds inquiry', 'lloyds-industrial'),
        'confirmation_message'  => __('Thank you for contacting Lloyds. Your request has been received and will be routed to the appropriate team.', 'lloyds-industrial'),
    ];

    $settings = get_option('li_contact_form_settings', []);

    return wp_parse_args(is_array($settings) ? $settings : [], $defaults);
}

function li_get_contact_form_recipient(string $type): string
{
    $settings = li_get_contact_form_settings();
    $key = sanitize_key($type) . '_email';
    $email = sanitize_email((string) ($settings[$key] ?? ''));

    return $email ?: sanitize_email((string) $settings['default_recipient']);
}

add_action('init', function (): void {
    register_post_type('li_contact_msg', [
        'labels' => [
            'name'          => __('Contact Submissions', 'lloyds-industrial'),
            'singular_name' => __('Contact Submission', 'lloyds-industrial'),
            'edit_item'     => __('View Contact Submission', 'lloyds-industrial'),
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => 'lloyds-contact-forms',
        'show_in_rest'        => false,
        'menu_icon'           => 'dashicons-email-alt2',
        'supports'            => ['title', 'editor'],
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'exclude_from_search' => true,
    ]);
});

add_action('admin_menu', function (): void {
    add_menu_page(
        __('Lloyds Contact Forms', 'lloyds-industrial'),
        __('Contact Forms', 'lloyds-industrial'),
        'manage_options',
        'lloyds-contact-forms',
        'li_render_contact_forms_settings_page',
        'dashicons-email-alt2',
        62
    );

    add_submenu_page(
        'lloyds-contact-forms',
        __('Contact Form Settings', 'lloyds-industrial'),
        __('Settings', 'lloyds-industrial'),
        'manage_options',
        'lloyds-contact-forms',
        'li_render_contact_forms_settings_page'
    );
});

add_action('admin_init', function (): void {
    if (
        is_admin()
        && isset($_GET['post_type'])
        && sanitize_key((string) $_GET['post_type']) === 'li_contact_submission'
    ) {
        wp_safe_redirect(admin_url('edit.php?post_type=li_contact_msg'));
        exit;
    }

    register_setting('li_contact_form_settings', 'li_contact_form_settings', [
        'type'              => 'array',
        'sanitize_callback' => 'li_sanitize_contact_form_settings',
        'default'           => [],
    ]);
});

function li_sanitize_contact_form_settings(array $settings): array
{
    $sanitized = [];
    $email_keys = [
        'default_recipient',
        'product_support_email',
        'sds_access_email',
        'quote_email',
        'orders_email',
        'distributor_email',
        'accounting_email',
        'general_email',
    ];

    foreach ($email_keys as $key) {
        $sanitized[$key] = sanitize_email((string) ($settings[$key] ?? ''));
    }

    $sanitized['confirmation_subject'] = sanitize_text_field($settings['confirmation_subject'] ?? '');
    $sanitized['confirmation_message'] = sanitize_textarea_field($settings['confirmation_message'] ?? '');

    return $sanitized;
}

function li_render_contact_forms_settings_page(): void
{
    $settings = li_get_contact_form_settings();
    $submissions_url = admin_url('edit.php?post_type=li_contact_msg');
    ?>
    <div class="wrap li-settings-page">
        <div class="li-settings-hero">
            <div>
                <p class="li-settings-kicker"><?php esc_html_e('Forms', 'lloyds-industrial'); ?></p>
                <h1><?php esc_html_e('Lloyds Contact Forms', 'lloyds-industrial'); ?></h1>
                <p><?php esc_html_e('Route quote requests, SDS access help, product support, orders, accounting, and general inquiries from one native theme form.', 'lloyds-industrial'); ?></p>
            </div>
            <div class="li-settings-summary">
                <div>
                    <span><?php esc_html_e('Shortcode', 'lloyds-industrial'); ?></span>
                    <strong>[li_contact_form]</strong>
                </div>
                <div>
                    <span><?php esc_html_e('Storage', 'lloyds-industrial'); ?></span>
                    <strong><?php esc_html_e('Submissions', 'lloyds-industrial'); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Routing', 'lloyds-industrial'); ?></span>
                    <strong><?php echo esc_html(count(li_get_contact_form_types())); ?></strong>
                </div>
            </div>
        </div>

        <p>
            <a class="button button-secondary" href="<?php echo esc_url($submissions_url); ?>">
                <?php esc_html_e('View Submissions', 'lloyds-industrial'); ?>
            </a>
        </p>

        <form class="li-settings-form" method="post" action="options.php">
            <?php settings_fields('li_contact_form_settings'); ?>

            <h2><?php esc_html_e('Routing Recipients', 'lloyds-industrial'); ?></h2>
            <p><?php esc_html_e('Each inquiry type can route to a different inbox. Empty fields fall back to the default recipient.', 'lloyds-industrial'); ?></p>
            <table class="form-table" role="presentation">
                <tbody>
                    <?php
                    $email_fields = [
                        'default_recipient'     => __('Default Recipient', 'lloyds-industrial'),
                        'product_support_email' => __('Product Support', 'lloyds-industrial'),
                        'sds_access_email'      => __('SDS Access Help', 'lloyds-industrial'),
                        'quote_email'           => __('Quote Requests', 'lloyds-industrial'),
                        'orders_email'          => __('Order Desk', 'lloyds-industrial'),
                        'distributor_email'     => __('Distributor Inquiries', 'lloyds-industrial'),
                        'accounting_email'      => __('Accounting', 'lloyds-industrial'),
                        'general_email'         => __('General Contact', 'lloyds-industrial'),
                    ];
                    ?>
                    <?php foreach ($email_fields as $key => $label) : ?>
                        <tr>
                            <th scope="row"><label for="li_contact_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                            <td>
                                <input
                                    class="regular-text"
                                    id="li_contact_<?php echo esc_attr($key); ?>"
                                    name="li_contact_form_settings[<?php echo esc_attr($key); ?>]"
                                    type="email"
                                    value="<?php echo esc_attr((string) $settings[$key]); ?>"
                                >
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2><?php esc_html_e('Confirmation Email', 'lloyds-industrial'); ?></h2>
            <p><?php esc_html_e('Sent to the visitor after a successful submission when they provide an email address.', 'lloyds-industrial'); ?></p>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="li_contact_confirmation_subject"><?php esc_html_e('Subject', 'lloyds-industrial'); ?></label></th>
                        <td>
                            <input
                                class="regular-text"
                                id="li_contact_confirmation_subject"
                                name="li_contact_form_settings[confirmation_subject]"
                                type="text"
                                value="<?php echo esc_attr((string) $settings['confirmation_subject']); ?>"
                            >
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="li_contact_confirmation_message"><?php esc_html_e('Message', 'lloyds-industrial'); ?></label></th>
                        <td>
                            <textarea
                                class="large-text"
                                id="li_contact_confirmation_message"
                                name="li_contact_form_settings[confirmation_message]"
                                rows="5"
                            ><?php echo esc_textarea((string) $settings['confirmation_message']); ?></textarea>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button(__('Save Contact Form Settings', 'lloyds-industrial')); ?>
        </form>
    </div>
    <?php
}

function li_render_contact_form_shortcode(mixed $atts = []): string
{
    $atts = is_array($atts) ? $atts : [];

    $atts = shortcode_atts([
        'type'  => '',
        'title' => '',
    ], $atts, 'li_contact_form');

    $types = li_get_contact_form_types();
    $selected_type = sanitize_key((string) $atts['type']);

    if (!isset($types[$selected_type])) {
        $selected_type = '';
    }

    $status = isset($_GET['li_contact_status']) ? sanitize_key((string) $_GET['li_contact_status']) : '';
    $title = trim((string) $atts['title']);

    ob_start();
    ?>
    <form class="li-contact-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php if ($title !== '') : ?>
            <h3><?php echo esc_html($title); ?></h3>
        <?php endif; ?>

        <?php if ($status === 'sent') : ?>
            <div class="li-contact-form__notice li-contact-form__notice--success">
                <?php esc_html_e('Thank you. Your request has been sent to the Lloyds team.', 'lloyds-industrial'); ?>
            </div>
        <?php elseif ($status === 'error') : ?>
            <div class="li-contact-form__notice li-contact-form__notice--error">
                <?php esc_html_e('Please complete the required fields and try again.', 'lloyds-industrial'); ?>
            </div>
        <?php endif; ?>

        <input type="hidden" name="action" value="li_submit_contact_form">
        <input type="hidden" name="source_url" value="<?php echo esc_url(get_permalink() ?: home_url('/')); ?>">
        <?php wp_nonce_field('li_submit_contact_form', 'li_contact_nonce'); ?>

        <p class="li-contact-form__honeypot" aria-hidden="true">
            <label for="li_contact_company_url"><?php esc_html_e('Company URL', 'lloyds-industrial'); ?></label>
            <input id="li_contact_company_url" name="company_url" type="text" tabindex="-1" autocomplete="off">
        </p>

        <div class="li-contact-form__grid">
            <p>
                <label for="li_contact_type"><?php esc_html_e('Request Type', 'lloyds-industrial'); ?> <span>*</span></label>
                <select id="li_contact_type" name="inquiry_type" required>
                    <option value=""><?php esc_html_e('Select a request type', 'lloyds-industrial'); ?></option>
                    <?php foreach ($types as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($selected_type, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="li_contact_name"><?php esc_html_e('Name', 'lloyds-industrial'); ?> <span>*</span></label>
                <input id="li_contact_name" name="name" type="text" autocomplete="name" required>
            </p>
            <p>
                <label for="li_contact_company"><?php esc_html_e('Company', 'lloyds-industrial'); ?></label>
                <input id="li_contact_company" name="company" type="text" autocomplete="organization">
            </p>
            <p>
                <label for="li_contact_email"><?php esc_html_e('Email', 'lloyds-industrial'); ?> <span>*</span></label>
                <input id="li_contact_email" name="email" type="email" autocomplete="email" required>
            </p>
            <p>
                <label for="li_contact_phone"><?php esc_html_e('Phone', 'lloyds-industrial'); ?></label>
                <input id="li_contact_phone" name="phone" type="tel" autocomplete="tel">
            </p>
            <p>
                <label for="li_contact_product"><?php esc_html_e('Product / Subject', 'lloyds-industrial'); ?></label>
                <input id="li_contact_product" name="product" type="text">
            </p>
            <p class="li-contact-form__compact-field">
                <label for="li_contact_quantity"><?php esc_html_e('Quantity / Volume', 'lloyds-industrial'); ?></label>
                <input id="li_contact_quantity" name="quantity" type="text">
            </p>
            <p class="li-contact-form__compact-field">
                <label for="li_contact_order_number"><?php esc_html_e('Order Number', 'lloyds-industrial'); ?></label>
                <input id="li_contact_order_number" name="order_number" type="text">
            </p>
        </div>

        <p>
            <label for="li_contact_message"><?php esc_html_e('Message', 'lloyds-industrial'); ?> <span>*</span></label>
            <textarea id="li_contact_message" name="message" rows="4" required></textarea>
        </p>

        <p class="li-contact-form__consent">
            <label>
                <input name="consent" type="checkbox" value="1" required>
                <?php esc_html_e('I agree to be contacted by Lloyds about this request.', 'lloyds-industrial'); ?>
            </label>
        </p>

        <button class="li-button-primary" type="submit"><?php esc_html_e('Send Request', 'lloyds-industrial'); ?></button>
    </form>
    <?php

    return (string) ob_get_clean();
}

function li_handle_contact_form_submission(): void
{
    $source_url = isset($_POST['source_url']) ? esc_url_raw(wp_unslash((string) $_POST['source_url'])) : home_url('/contact/');
    $redirect_error = add_query_arg('li_contact_status', 'error', $source_url);
    $redirect_success = add_query_arg('li_contact_status', 'sent', $source_url);

    if (!isset($_POST['li_contact_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['li_contact_nonce'])), 'li_submit_contact_form')) {
        wp_safe_redirect($redirect_error);
        exit;
    }

    if (!empty($_POST['company_url'])) {
        wp_safe_redirect($redirect_success);
        exit;
    }

    $types = li_get_contact_form_types();
    $type = sanitize_key((string) ($_POST['inquiry_type'] ?? ''));
    $name = sanitize_text_field(wp_unslash((string) ($_POST['name'] ?? '')));
    $company = sanitize_text_field(wp_unslash((string) ($_POST['company'] ?? '')));
    $email = sanitize_email(wp_unslash((string) ($_POST['email'] ?? '')));
    $phone = sanitize_text_field(wp_unslash((string) ($_POST['phone'] ?? '')));
    $product = sanitize_text_field(wp_unslash((string) ($_POST['product'] ?? '')));
    $quantity = sanitize_text_field(wp_unslash((string) ($_POST['quantity'] ?? '')));
    $order_number = sanitize_text_field(wp_unslash((string) ($_POST['order_number'] ?? '')));
    $message = sanitize_textarea_field(wp_unslash((string) ($_POST['message'] ?? '')));
    $consent = !empty($_POST['consent']);

    if (!isset($types[$type]) || $name === '' || $email === '' || $message === '' || !$consent) {
        wp_safe_redirect($redirect_error);
        exit;
    }

    $submission_id = wp_insert_post([
        'post_type'    => 'li_contact_msg',
        'post_status'  => 'publish',
        'post_title'   => sprintf('%s - %s', $types[$type], $name),
        'post_content' => $message,
    ], true);

    if (!is_wp_error($submission_id) && $submission_id) {
        $meta = compact('type', 'name', 'company', 'email', 'phone', 'product', 'quantity', 'order_number', 'source_url');
        $meta['status'] = 'new';

        foreach ($meta as $key => $value) {
            update_post_meta((int) $submission_id, '_li_contact_' . $key, $value);
        }
    }

    li_send_contact_form_notifications($type, [
        'name'         => $name,
        'company'      => $company,
        'email'        => $email,
        'phone'        => $phone,
        'product'      => $product,
        'quantity'     => $quantity,
        'order_number' => $order_number,
        'message'      => $message,
        'source_url'   => $source_url,
    ]);

    wp_safe_redirect($redirect_success);
    exit;
}

function li_send_contact_form_notifications(string $type, array $data): void
{
    $types = li_get_contact_form_types();
    $settings = li_get_contact_form_settings();
    $recipient = li_get_contact_form_recipient($type);
    $subject = sprintf('[%s] %s', get_bloginfo('name'), $types[$type] ?? __('Contact Request', 'lloyds-industrial'));
    $lines = [
        'Type: ' . ($types[$type] ?? $type),
        'Name: ' . $data['name'],
        'Company: ' . $data['company'],
        'Email: ' . $data['email'],
        'Phone: ' . $data['phone'],
        'Product / Subject: ' . $data['product'],
        'Quantity / Volume: ' . $data['quantity'],
        'Order Number: ' . $data['order_number'],
        'Source: ' . $data['source_url'],
        '',
        'Message:',
        $data['message'],
    ];
    $headers = [];

    if (!empty($data['email'])) {
        $headers[] = 'Reply-To: ' . $data['name'] . ' <' . $data['email'] . '>';
    }

    wp_mail($recipient, $subject, implode("\n", $lines), $headers);

    if (!empty($data['email']) && !empty($settings['confirmation_subject'])) {
        wp_mail(
            $data['email'],
            (string) $settings['confirmation_subject'],
            (string) $settings['confirmation_message']
        );
    }
}

add_action('admin_post_li_submit_contact_form', 'li_handle_contact_form_submission');
add_action('admin_post_nopriv_li_submit_contact_form', 'li_handle_contact_form_submission');
add_shortcode('li_contact_form', 'li_render_contact_form_shortcode');
add_shortcode('contact_form', 'li_render_contact_form_shortcode');
add_shortcode('quote_request_form', function (): string {
    return li_render_contact_form_shortcode(['type' => 'quote', 'title' => __('Quote Request', 'lloyds-industrial')]);
});

add_filter('manage_li_contact_msg_posts_columns', function (array $columns): array {
    return [
        'cb'              => $columns['cb'] ?? '',
        'title'           => __('Submission', 'lloyds-industrial'),
        'li_contact_type' => __('Type', 'lloyds-industrial'),
        'li_contact_status' => __('Status', 'lloyds-industrial'),
        'li_contact_from' => __('From', 'lloyds-industrial'),
        'li_contact_meta' => __('Product / Order', 'lloyds-industrial'),
        'date'            => $columns['date'] ?? __('Date', 'lloyds-industrial'),
    ];
});

add_action('manage_li_contact_msg_posts_custom_column', function (string $column, int $post_id): void {
    $types = li_get_contact_form_types();

    if ($column === 'li_contact_type') {
        $type = (string) get_post_meta($post_id, '_li_contact_type', true);
        echo esc_html($types[$type] ?? $type);
    }

    if ($column === 'li_contact_status') {
        $status = (string) get_post_meta($post_id, '_li_contact_status', true);
        echo esc_html(ucfirst($status ?: 'new'));
    }

    if ($column === 'li_contact_from') {
        $name = (string) get_post_meta($post_id, '_li_contact_name', true);
        $company = (string) get_post_meta($post_id, '_li_contact_company', true);
        $email = (string) get_post_meta($post_id, '_li_contact_email', true);

        echo esc_html($name);

        if ($company !== '') {
            echo '<br><span class="description">' . esc_html($company) . '</span>';
        }

        if ($email !== '') {
            echo '<br><a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
        }
    }

    if ($column === 'li_contact_meta') {
        $product = (string) get_post_meta($post_id, '_li_contact_product', true);
        $quantity = (string) get_post_meta($post_id, '_li_contact_quantity', true);
        $order_number = (string) get_post_meta($post_id, '_li_contact_order_number', true);

        if ($product !== '') {
            echo esc_html($product);
        }

        if ($quantity !== '') {
            echo '<br><span class="description">' . esc_html(sprintf(__('Qty: %s', 'lloyds-industrial'), $quantity)) . '</span>';
        }

        if ($order_number !== '') {
            echo '<br><span class="description">' . esc_html(sprintf(__('Order: %s', 'lloyds-industrial'), $order_number)) . '</span>';
        }
    }
}, 10, 2);

add_action('add_meta_boxes', function (): void {
    add_meta_box(
        'li_contact_submission_details',
        __('Submission Details', 'lloyds-industrial'),
        'li_render_contact_submission_details_metabox',
        'li_contact_msg',
        'normal',
        'high'
    );
});

function li_render_contact_submission_details_metabox(WP_Post $post): void
{
    wp_nonce_field('li_save_contact_submission_details', 'li_contact_submission_nonce');

    $fields = [
        'type'         => __('Type', 'lloyds-industrial'),
        'name'         => __('Name', 'lloyds-industrial'),
        'company'      => __('Company', 'lloyds-industrial'),
        'email'        => __('Email', 'lloyds-industrial'),
        'phone'        => __('Phone', 'lloyds-industrial'),
        'product'      => __('Product / Subject', 'lloyds-industrial'),
        'quantity'     => __('Quantity / Volume', 'lloyds-industrial'),
        'order_number' => __('Order Number', 'lloyds-industrial'),
        'source_url'   => __('Source URL', 'lloyds-industrial'),
    ];
    $types = li_get_contact_form_types();
    $status = (string) get_post_meta($post->ID, '_li_contact_status', true);
    $status = in_array($status, ['new', 'in_progress', 'closed'], true) ? $status : 'new';
    ?>
    <p>
        <label for="li_contact_status"><strong><?php esc_html_e('Status', 'lloyds-industrial'); ?></strong></label>
        <select id="li_contact_status" name="li_contact_status">
            <option value="new" <?php selected($status, 'new'); ?>><?php esc_html_e('New', 'lloyds-industrial'); ?></option>
            <option value="in_progress" <?php selected($status, 'in_progress'); ?>><?php esc_html_e('In Progress', 'lloyds-industrial'); ?></option>
            <option value="closed" <?php selected($status, 'closed'); ?>><?php esc_html_e('Closed', 'lloyds-industrial'); ?></option>
        </select>
    </p>
    <table class="widefat striped">
        <tbody>
            <?php foreach ($fields as $key => $label) : ?>
                <?php
                $value = (string) get_post_meta($post->ID, '_li_contact_' . $key, true);

                if ($key === 'type') {
                    $value = $types[$value] ?? $value;
                }
                ?>
                <tr>
                    <th scope="row" style="width:180px;"><?php echo esc_html($label); ?></th>
                    <td>
                        <?php if ($key === 'email' && $value !== '') : ?>
                            <a href="mailto:<?php echo esc_attr($value); ?>"><?php echo esc_html($value); ?></a>
                        <?php elseif ($key === 'source_url' && $value !== '') : ?>
                            <a href="<?php echo esc_url($value); ?>"><?php echo esc_html($value); ?></a>
                        <?php else : ?>
                            <?php echo esc_html($value ?: '-'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

add_action('save_post_li_contact_msg', function (int $post_id): void {
    if (!isset($_POST['li_contact_submission_nonce'])) {
        return;
    }

    if (!wp_verify_nonce(
        sanitize_text_field(wp_unslash($_POST['li_contact_submission_nonce'])),
        'li_save_contact_submission_details'
    )) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $status = isset($_POST['li_contact_status'])
        ? sanitize_key(wp_unslash((string) $_POST['li_contact_status']))
        : 'new';

    if (!in_array($status, ['new', 'in_progress', 'closed'], true)) {
        $status = 'new';
    }

    update_post_meta($post_id, '_li_contact_status', $status);
});
