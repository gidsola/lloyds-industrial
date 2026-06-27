<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function (): void {
    register_post_type('li_reseller', [
        'labels' => [
            'name'          => __('Resellers', 'lloyds-industrial'),
            'singular_name' => __('Reseller', 'lloyds-industrial'),
            'add_new_item'  => __('Add Reseller', 'lloyds-industrial'),
            'edit_item'     => __('Edit Reseller', 'lloyds-industrial'),
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => 'lloyds-resellers',
        'show_in_rest'        => true,
        'menu_icon'           => 'dashicons-store',
        'supports'            => ['title'],
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'exclude_from_search' => true,
    ]);
});

add_action('admin_menu', function (): void {
    add_menu_page(
        __('Lloyds Resellers', 'lloyds-industrial'),
        __('Resellers', 'lloyds-industrial'),
        'edit_posts',
        'lloyds-resellers',
        'li_render_reseller_admin_overview',
        'dashicons-store',
        63
    );

    add_submenu_page(
        'lloyds-resellers',
        __('All Resellers', 'lloyds-industrial'),
        __('All Resellers', 'lloyds-industrial'),
        'edit_posts',
        'edit.php?post_type=li_reseller'
    );
});

function li_render_reseller_admin_overview(): void
{
    $add_url = admin_url('post-new.php?post_type=li_reseller');
    $all_url = admin_url('edit.php?post_type=li_reseller');
    ?>
    <div class="wrap li-settings-page">
        <div class="li-settings-hero">
            <div>
                <p class="li-settings-kicker"><?php esc_html_e('Channel', 'lloyds-industrial'); ?></p>
                <h1><?php esc_html_e('Lloyds Resellers', 'lloyds-industrial'); ?></h1>
                <p><?php esc_html_e('Maintain public reseller directory listings and connect approved applications to distributor accounts.', 'lloyds-industrial'); ?></p>
            </div>
            <div class="li-settings-summary">
                <div>
                    <span><?php esc_html_e('Shortcode', 'lloyds-industrial'); ?></span>
                    <strong>[li_reseller_finder]</strong>
                </div>
                <div>
                    <span><?php esc_html_e('Public', 'lloyds-industrial'); ?></span>
                    <strong><?php esc_html_e('Published only', 'lloyds-industrial'); ?></strong>
                </div>
            </div>
        </div>
        <p>
            <a class="button button-primary" href="<?php echo esc_url($add_url); ?>"><?php esc_html_e('Add Reseller', 'lloyds-industrial'); ?></a>
            <a class="button button-secondary" href="<?php echo esc_url($all_url); ?>"><?php esc_html_e('View Resellers', 'lloyds-industrial'); ?></a>
        </p>
    </div>
    <?php
}

function li_get_reseller_field_labels(): array
{
    return [
        'public_name'     => __('Public Name', 'lloyds-industrial'),
        'contact_name'    => __('Primary Contact', 'lloyds-industrial'),
        'email'           => __('Email', 'lloyds-industrial'),
        'phone'           => __('Phone', 'lloyds-industrial'),
        'website'         => __('Website', 'lloyds-industrial'),
        'address'         => __('Address', 'lloyds-industrial'),
        'city'            => __('City', 'lloyds-industrial'),
        'province'        => __('Province / State', 'lloyds-industrial'),
        'postal_code'     => __('Postal / ZIP Code', 'lloyds-industrial'),
        'country'         => __('Country', 'lloyds-industrial'),
        'territory'       => __('Service Territory', 'lloyds-industrial'),
        'product_focus'   => __('Product Focus', 'lloyds-industrial'),
        'customer_types'  => __('Customer Base', 'lloyds-industrial'),
        'sales_channels'  => __('Sales Channels', 'lloyds-industrial'),
        'public_notes'    => __('Public Notes', 'lloyds-industrial'),
        'internal_notes'  => __('Internal Notes', 'lloyds-industrial'),
        'linked_user_id'  => __('Linked User ID', 'lloyds-industrial'),
        'application_id'  => __('Application ID', 'lloyds-industrial'),
    ];
}

add_action('add_meta_boxes', function (): void {
    add_meta_box(
        'li_reseller_details',
        __('Reseller Details', 'lloyds-industrial'),
        'li_render_reseller_details_metabox',
        'li_reseller',
        'normal',
        'high'
    );
});

add_filter('enter_title_here', function (string $placeholder, WP_Post $post): string {
    if ($post->post_type !== 'li_reseller') {
        return $placeholder;
    }

    return __('Internal reseller record name', 'lloyds-industrial');
}, 10, 2);

function li_render_reseller_details_metabox(WP_Post $post): void
{
    wp_nonce_field('li_save_reseller_details', 'li_reseller_nonce');
    $labels = li_get_reseller_field_labels();
    $sections = [
        'profile' => [
            'title'       => __('Public Profile', 'lloyds-industrial'),
            'description' => __('These fields appear in the public reseller finder and help customers choose the right channel partner.', 'lloyds-industrial'),
            'fields'      => ['public_name', 'website', 'public_notes'],
        ],
        'contact' => [
            'title'       => __('Contact Details', 'lloyds-industrial'),
            'description' => __('Primary business contact and location information for customer routing.', 'lloyds-industrial'),
            'fields'      => ['contact_name', 'email', 'phone', 'address', 'city', 'province', 'postal_code', 'country'],
        ],
        'coverage' => [
            'title'       => __('Coverage and Fit', 'lloyds-industrial'),
            'description' => __('Use these fields to match reseller listings with product, territory, and customer searches.', 'lloyds-industrial'),
            'fields'      => ['territory', 'product_focus', 'customer_types', 'sales_channels'],
        ],
        'operations' => [
            'title'       => __('Internal Operations', 'lloyds-industrial'),
            'description' => __('Private notes and system links are visible to administrators only.', 'lloyds-industrial'),
            'fields'      => ['internal_notes', 'linked_user_id', 'application_id'],
        ],
    ];
    ?>
    <div class="li-reseller-builder">
        <div class="li-reseller-builder__intro">
            <span class="dashicons dashicons-store"></span>
            <div>
                <h2><?php esc_html_e('Reseller Directory Listing', 'lloyds-industrial'); ?></h2>
                <p><?php esc_html_e('Build the listing customers see when they search for approved Lloyds reseller and distributor channels.', 'lloyds-industrial'); ?></p>
            </div>
        </div>

        <div class="li-reseller-builder__grid">
            <?php foreach ($sections as $section) : ?>
                <section class="li-reseller-builder__section">
                    <header>
                        <h3><?php echo esc_html($section['title']); ?></h3>
                        <p><?php echo esc_html($section['description']); ?></p>
                    </header>

                    <div class="li-reseller-builder__fields">
                        <?php foreach ($section['fields'] as $key) : ?>
                            <?php
                            $value = (string) get_post_meta($post->ID, '_li_reseller_' . $key, true);
                            $readonly = in_array($key, ['linked_user_id', 'application_id'], true);
                            $input_type = match ($key) {
                                'email' => 'email',
                                'website' => 'url',
                                'phone' => 'tel',
                                default => 'text',
                            };
                            ?>
                            <label class="li-reseller-builder__field" for="li_reseller_<?php echo esc_attr($key); ?>">
                                <span><?php echo esc_html($labels[$key] ?? $key); ?></span>
                                <?php if (in_array($key, ['public_notes', 'internal_notes'], true)) : ?>
                                    <textarea
                                        id="li_reseller_<?php echo esc_attr($key); ?>"
                                        name="li_reseller[<?php echo esc_attr($key); ?>]"
                                        rows="4"
                                        <?php echo $readonly ? 'readonly' : ''; ?>
                                    ><?php echo esc_textarea($value); ?></textarea>
                                <?php else : ?>
                                    <input
                                        id="li_reseller_<?php echo esc_attr($key); ?>"
                                        name="li_reseller[<?php echo esc_attr($key); ?>]"
                                        type="<?php echo esc_attr($input_type); ?>"
                                        value="<?php echo esc_attr($value); ?>"
                                        <?php echo $readonly ? 'readonly' : ''; ?>
                                    >
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

add_action('save_post_li_reseller', function (int $post_id): void {
    if (!isset($_POST['li_reseller_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['li_reseller_nonce'])), 'li_save_reseller_details')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $input = isset($_POST['li_reseller']) && is_array($_POST['li_reseller']) ? wp_unslash($_POST['li_reseller']) : [];

    foreach (li_get_reseller_field_labels() as $key => $label) {
        if (in_array($key, ['linked_user_id', 'application_id'], true)) {
            continue;
        }

        $value = (string) ($input[$key] ?? '');
        $value = match ($key) {
            'email' => sanitize_email($value),
            'website' => esc_url_raw($value),
            default => sanitize_text_field($value),
        };

        if (in_array($key, ['public_notes', 'internal_notes'], true)) {
            $value = sanitize_textarea_field((string) ($input[$key] ?? ''));
        }

        update_post_meta($post_id, '_li_reseller_' . $key, $value);
    }
});

function li_get_public_resellers(string $query = '', string $product_query = ''): array
{
    $posts = get_posts([
        'post_type'      => 'li_reseller',
        'post_status'    => 'publish',
        'posts_per_page' => 100,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    $query = strtolower(trim($query));
    $product_query = strtolower(trim($product_query));
    $resellers = [];

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $data = li_get_reseller_public_data($post);
        $haystack = strtolower(implode(' ', array_filter($data)));

        if ($query !== '' && !str_contains($haystack, $query)) {
            continue;
        }

        if ($product_query !== '' && !str_contains($haystack, $product_query)) {
            continue;
        }

        $resellers[] = $data;
    }

    return $resellers;
}

function li_get_reseller_public_data(WP_Post $post): array
{
    $fields = [];

    foreach (li_get_reseller_field_labels() as $key => $label) {
        $fields[$key] = (string) get_post_meta($post->ID, '_li_reseller_' . $key, true);
    }

    $fields['id'] = (string) $post->ID;
    $fields['title'] = get_the_title($post);
    $fields['public_name'] = $fields['public_name'] ?: $fields['title'];

    return $fields;
}

function li_render_reseller_finder_shortcode(): string
{
    $query = isset($_GET['reseller_location']) ? sanitize_text_field(wp_unslash((string) $_GET['reseller_location'])) : '';
    $product_query = isset($_GET['reseller_product']) ? sanitize_text_field(wp_unslash((string) $_GET['reseller_product'])) : '';
    $resellers = li_get_public_resellers($query, $product_query);

    ob_start();
    ?>
    <section class="li-reseller-finder" aria-labelledby="li-reseller-finder-title">
        <div class="li-reseller-finder__header">
            <p class="li-eyebrow"><?php esc_html_e('Reseller Directory', 'lloyds-industrial'); ?></p>
            <h2 id="li-reseller-finder-title"><?php esc_html_e('Find A Reseller Near You', 'lloyds-industrial'); ?></h2>
            <p><?php esc_html_e('Search by city, province/state, postal code, territory, product focus, or customer segment.', 'lloyds-industrial'); ?></p>
            <?php if ($product_query !== '') : ?>
                <p class="li-reseller-finder__context">
                    <?php echo esc_html(sprintf(__('Showing resellers that list product focus matching "%s".', 'lloyds-industrial'), $product_query)); ?>
                </p>
            <?php endif; ?>
        </div>

        <form class="li-reseller-finder__search" method="get">
            <label class="screen-reader-text" for="li_reseller_location"><?php esc_html_e('Location or territory', 'lloyds-industrial'); ?></label>
            <input id="li_reseller_location" name="reseller_location" type="search" value="<?php echo esc_attr($query); ?>" placeholder="<?php echo esc_attr__('City, province, postal code, territory...', 'lloyds-industrial'); ?>">
            <?php if ($product_query !== '') : ?>
                <input type="hidden" name="reseller_product" value="<?php echo esc_attr($product_query); ?>">
            <?php endif; ?>
            <button class="li-button-primary" type="submit"><?php esc_html_e('Search', 'lloyds-industrial'); ?></button>
            <?php if ($query !== '' || $product_query !== '') : ?>
                <a class="li-button-secondary" href="<?php echo esc_url(get_permalink() ?: home_url('/find-a-reseller/')); ?>"><?php esc_html_e('Clear', 'lloyds-industrial'); ?></a>
            <?php endif; ?>
        </form>

        <?php if (!$resellers) : ?>
            <div class="li-reseller-finder__empty">
                <h3><?php esc_html_e('No matching resellers found', 'lloyds-industrial'); ?></h3>
                <p><?php esc_html_e('Contact Lloyds and we will route your request to the right distributor or support team.', 'lloyds-industrial'); ?></p>
                <a class="li-button-primary" href="<?php echo esc_url(home_url('/contact/')); ?>"><?php esc_html_e('Contact Lloyds', 'lloyds-industrial'); ?></a>
            </div>
        <?php else : ?>
            <div class="li-reseller-grid">
                <?php foreach ($resellers as $reseller) : ?>
                    <article class="li-reseller-card">
                        <h3><?php echo esc_html($reseller['public_name']); ?></h3>
                        <?php if ($reseller['city'] !== '' || $reseller['province'] !== '' || $reseller['country'] !== '') : ?>
                            <p class="li-reseller-card__location">
                                <?php echo esc_html(implode(', ', array_filter([$reseller['city'], $reseller['province'], $reseller['country']]))); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($reseller['territory'] !== '') : ?>
                            <p><strong><?php esc_html_e('Territory:', 'lloyds-industrial'); ?></strong> <?php echo esc_html($reseller['territory']); ?></p>
                        <?php endif; ?>
                        <?php if ($reseller['product_focus'] !== '') : ?>
                            <p><strong><?php esc_html_e('Product Focus:', 'lloyds-industrial'); ?></strong> <?php echo esc_html($reseller['product_focus']); ?></p>
                        <?php endif; ?>
                        <?php if ($reseller['public_notes'] !== '') : ?>
                            <p><?php echo esc_html($reseller['public_notes']); ?></p>
                        <?php endif; ?>
                        <div class="li-reseller-card__actions">
                            <?php if ($reseller['phone'] !== '') : ?>
                                <a class="li-button-secondary" href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $reseller['phone'])); ?>"><?php echo esc_html($reseller['phone']); ?></a>
                            <?php endif; ?>
                            <?php if ($reseller['email'] !== '') : ?>
                                <a class="li-button-primary" href="mailto:<?php echo esc_attr($reseller['email']); ?>"><?php esc_html_e('Email', 'lloyds-industrial'); ?></a>
                            <?php endif; ?>
                            <?php if ($reseller['website'] !== '') : ?>
                                <a class="li-button-secondary" href="<?php echo esc_url($reseller['website']); ?>"><?php esc_html_e('Website', 'lloyds-industrial'); ?></a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php

    return (string) ob_get_clean();
}

add_shortcode('li_reseller_finder', 'li_render_reseller_finder_shortcode');

add_filter('manage_li_reseller_posts_columns', function (array $columns): array {
    return [
        'cb' => $columns['cb'] ?? '',
        'title' => __('Reseller', 'lloyds-industrial'),
        'li_reseller_location' => __('Location', 'lloyds-industrial'),
        'li_reseller_contact' => __('Contact', 'lloyds-industrial'),
        'li_reseller_focus' => __('Product Focus', 'lloyds-industrial'),
        'date' => $columns['date'] ?? __('Date', 'lloyds-industrial'),
    ];
});

add_action('manage_li_reseller_posts_custom_column', function (string $column, int $post_id): void {
    if ($column === 'li_reseller_location') {
        $location = array_filter([
            (string) get_post_meta($post_id, '_li_reseller_city', true),
            (string) get_post_meta($post_id, '_li_reseller_province', true),
            (string) get_post_meta($post_id, '_li_reseller_country', true),
        ]);
        $territory = (string) get_post_meta($post_id, '_li_reseller_territory', true);

        echo esc_html($location ? implode(', ', $location) : '-');

        if ($territory !== '') {
            echo '<br><span class="description">' . esc_html($territory) . '</span>';
        }
    }

    if ($column === 'li_reseller_contact') {
        $email = (string) get_post_meta($post_id, '_li_reseller_email', true);
        $phone = (string) get_post_meta($post_id, '_li_reseller_phone', true);

        if ($email !== '') {
            echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
        }

        if ($phone !== '') {
            echo '<br><span class="description">' . esc_html($phone) . '</span>';
        }

        if ($email === '' && $phone === '') {
            echo esc_html('-');
        }
    }

    if ($column === 'li_reseller_focus') {
        $focus = (string) get_post_meta($post_id, '_li_reseller_product_focus', true);
        echo esc_html($focus ?: '-');
    }
}, 10, 2);

function li_accept_reseller_application(int $application_id): array
{
    $post = get_post($application_id);

    if (!$post instanceof WP_Post || $post->post_type !== 'li_contact_msg') {
        return ['reseller_id' => 0, 'user_id' => 0];
    }

    $email = sanitize_email((string) get_post_meta($application_id, '_li_contact_email', true));
    $company = (string) get_post_meta($application_id, '_li_contact_company', true);
    $name = (string) get_post_meta($application_id, '_li_contact_name', true);
    $user_id = (int) get_post_meta($application_id, '_li_contact_created_user_id', true);

    if (!$user_id && $email !== '') {
        $existing = get_user_by('email', $email);

        if ($existing instanceof WP_User) {
            $user_id = (int) $existing->ID;
            $existing->add_role('li_distributor');
        } else {
            $email_parts = explode('@', $email);
            $username = sanitize_user(($email_parts[0] ?? '') ?: $email, true);
            $username = li_get_available_username($username ?: 'reseller');
            $password = wp_generate_password(24, true);
            $new_user_id = wp_create_user($username, $password, $email);

            if (!is_wp_error($new_user_id)) {
                $user_id = (int) $new_user_id;
                wp_update_user([
                    'ID'           => $user_id,
                    'display_name' => $name !== '' ? $name : $company,
                    'role'         => 'li_distributor',
                ]);
                update_user_meta($user_id, 'billing_company', $company);
                wp_new_user_notification($user_id, null, 'user');
            }
        }

        if ($user_id) {
            update_post_meta($application_id, '_li_contact_created_user_id', $user_id);
        }
    }

    $reseller_id = (int) get_post_meta($application_id, '_li_contact_created_reseller_id', true);

    if (!$reseller_id) {
        $reseller_id = wp_insert_post([
            'post_type'   => 'li_reseller',
            'post_status' => 'draft',
            'post_title'  => $company ?: sprintf(__('Reseller Application #%d', 'lloyds-industrial'), $application_id),
        ], true);

        if (!is_wp_error($reseller_id) && $reseller_id) {
            li_populate_reseller_from_application((int) $reseller_id, $application_id, $user_id);
            update_post_meta($application_id, '_li_contact_created_reseller_id', (int) $reseller_id);
        } else {
            $reseller_id = 0;
        }
    }

    return ['reseller_id' => (int) $reseller_id, 'user_id' => $user_id];
}

function li_populate_reseller_from_application(int $reseller_id, int $application_id, int $user_id = 0): void
{
    $map = [
        'public_name'    => '_li_contact_company',
        'contact_name'   => '_li_contact_name',
        'email'          => '_li_contact_email',
        'phone'          => '_li_contact_phone',
        'website'        => '_li_reseller_website',
        'address'        => '_li_reseller_address',
        'territory'      => '_li_reseller_territory',
        'product_focus'  => '_li_reseller_product_interests',
        'customer_types' => '_li_reseller_customer_types',
        'sales_channels' => '_li_reseller_sales_channels',
        'internal_notes' => '_li_contact_source_url',
    ];

    foreach ($map as $reseller_key => $source_meta_key) {
        update_post_meta($reseller_id, '_li_reseller_' . $reseller_key, (string) get_post_meta($application_id, $source_meta_key, true));
    }

    update_post_meta($reseller_id, '_li_reseller_linked_user_id', (string) $user_id);
    update_post_meta($reseller_id, '_li_reseller_application_id', (string) $application_id);
}

function li_get_available_username(string $base): string
{
    $base = sanitize_user($base, true) ?: 'reseller';
    $username = $base;
    $suffix = 1;

    while (username_exists($username)) {
        $suffix++;
        $username = $base . $suffix;
    }

    return $username;
}
