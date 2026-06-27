<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function (): void {
    add_menu_page(
        __('Lloyds Industrial Settings', 'lloyds-industrial'),
        __('Lloyds', 'lloyds-industrial'),
        'manage_options',
        'lloyds',
        'li_render_settings_page',
        'dashicons-shield-alt',
        58
    );

    add_submenu_page(
        'lloyds',
        __('Lloyds Industrial Settings', 'lloyds-industrial'),
        __('Settings', 'lloyds-industrial'),
        'manage_options',
        'lloyds',
        'li_render_settings_page'
    );

    add_submenu_page(
        'lloyds',
        __('Lloyds Mega Menu', 'lloyds-industrial'),
        __('Mega Menu', 'lloyds-industrial'),
        'manage_options',
        'lloyds-mega-menu',
        'li_render_mega_menu_page'
    );
}, 1);

add_action('admin_init', function (): void {
    register_setting('li_theme_settings', 'li_theme_settings', [
        'type'              => 'array',
        'sanitize_callback' => 'li_sanitize_theme_settings',
        'default'           => [],
    ]);

    add_settings_section(
        'li_site_behavior_section',
        __('Site Behavior', 'lloyds-industrial'),
        'li_render_site_behavior_section',
        'lloyds'
    );

    add_settings_section(
        'li_brand_section',
        __('Brand Configuration', 'lloyds-industrial'),
        '__return_null',
        'lloyds'
    );

    add_settings_field(
        'li_partner_mode',
        __('Partner Site Mode', 'lloyds-industrial'),
        'li_render_partner_mode_field',
        'lloyds',
        'li_site_behavior_section'
    );

    add_settings_field(
        'li_document_access_mode',
        __('Document Access', 'lloyds-industrial'),
        'li_render_document_access_mode_field',
        'lloyds',
        'li_site_behavior_section'
    );

    add_settings_field(
        'li_brand_palette',
        __('Brand Colors', 'lloyds-industrial'),
        'li_render_brand_palette_field',
        'lloyds',
        'li_brand_section'
    );

    add_settings_section(
        'li_layout_display_section',
        __('Layout and Display', 'lloyds-industrial'),
        'li_render_layout_display_section',
        'lloyds'
    );

    add_settings_field(
        'li_header_display',
        __('Header Display', 'lloyds-industrial'),
        'li_render_header_display_field',
        'lloyds',
        'li_layout_display_section'
    );

    add_settings_field(
        'li_site_width',
        __('Site Width', 'lloyds-industrial'),
        'li_render_site_width_field',
        'lloyds',
        'li_layout_display_section'
    );

    add_settings_field(
        'li_footer_density',
        __('Footer Density', 'lloyds-industrial'),
        'li_render_footer_density_field',
        'lloyds',
        'li_layout_display_section'
    );

    add_settings_section(
        'li_global_layout_section',
        __('Global Header and Footer', 'lloyds-industrial'),
        'li_render_global_layout_section',
        'lloyds'
    );

    add_settings_field(
        'li_announcement',
        __('Announcement Bar', 'lloyds-industrial'),
        'li_render_announcement_field',
        'lloyds',
        'li_global_layout_section'
    );

    add_settings_field(
        'li_header_actions',
        __('Header Actions', 'lloyds-industrial'),
        'li_render_header_actions_field',
        'lloyds',
        'li_global_layout_section'
    );

    add_settings_field(
        'li_footer_content',
        __('Footer Content', 'lloyds-industrial'),
        'li_render_footer_content_field',
        'lloyds',
        'li_global_layout_section'
    );

    // Mega Menu Section
    add_settings_section(
        'li_mega_menu_section',
        __('Mega Menu Builder', 'lloyds-industrial'),
        'li_render_mega_menu_section',
        'lloyds-mega-menu'
    );

    add_settings_field(
        'li_mega_menu_enabled',
        __('Enable Mega Menu', 'lloyds-industrial'),
        'li_render_mega_menu_enabled_field',
        'lloyds-mega-menu',
        'li_mega_menu_section'
    );

    add_settings_field(
        'li_mega_menu_data',
        __('Menu Configuration', 'lloyds-industrial'),
        'li_render_mega_menu_data_field',
        'lloyds-mega-menu',
        'li_mega_menu_section'
    );
});

add_action('admin_init', function (): void {
    if (!current_user_can('manage_options') || !isset($_GET['li_reset_brand_settings'])) {
        return;
    }

    check_admin_referer('li_reset_brand_settings');

    delete_option('li_brand_settings');
    set_transient('li_brand_reset_notice', 'reset', MINUTE_IN_SECONDS);

    wp_safe_redirect(admin_url('admin.php?page=lloyds'));
    exit;
});

add_action('admin_init', function (): void {
    if (!current_user_can('manage_options') || !isset($_GET['li_reset_layout_settings'])) {
        return;
    }

    check_admin_referer('li_reset_layout_settings');

    $settings = get_option('li_theme_settings', []);
    $settings = is_array($settings) ? $settings : [];

    foreach (array_keys(li_get_global_layout_defaults()) as $key) {
        unset($settings[$key]);
    }

    update_option('li_theme_settings', $settings);
    set_transient('li_theme_settings_notice', 'layout_reset', MINUTE_IN_SECONDS);

    wp_safe_redirect(admin_url('admin.php?page=lloyds'));
    exit;
});

add_action('admin_init', function (): void {
    if (!current_user_can('manage_options') || !isset($_GET['li_theme_maintenance_action'])) {
        return;
    }

    $action = sanitize_key((string) $_GET['li_theme_maintenance_action']);
    check_admin_referer('li_theme_maintenance_' . $action);

    if ($action === 'refresh_catalogue') {
        li_bootstrap_refresh_catalogue_page_content();
        li_bootstrap_ensure_catalogue_flipbook_content();
        set_transient('li_theme_settings_notice', 'catalogue_refreshed', MINUTE_IN_SECONDS);
    } elseif ($action === 'rebuild_navigation') {
        $pages = li_bootstrap_create_pages(li_get_starter_media(), false);
        li_bootstrap_create_navigation($pages);
        set_transient('li_theme_settings_notice', 'navigation_rebuilt', MINUTE_IN_SECONDS);
    } elseif ($action === 'ensure_terms') {
        li_bootstrap_create_product_terms();
        set_transient('li_theme_settings_notice', 'terms_ensured', MINUTE_IN_SECONDS);
    }

    wp_safe_redirect(admin_url('admin.php?page=lloyds'));
    exit;
});

add_action('admin_init', function (): void {
    if (!current_user_can('manage_options') || !isset($_GET['li_migrate_sds_documents'])) {
        return;
    }

    check_admin_referer('li_migrate_sds_documents');

    $result = li_migrate_sds_documents_to_protected_storage();

    set_transient('li_sds_migration_result', $result, MINUTE_IN_SECONDS);

    wp_safe_redirect(admin_url('admin.php?page=lloyds'));
    exit;
});

add_action('admin_notices', function (): void {
    $theme_settings_notice = get_transient('li_theme_settings_notice');

    if (is_string($theme_settings_notice)) {
        delete_transient('li_theme_settings_notice');

        $messages = [
            'layout_reset'        => __('Theme layout settings reset to defaults.', 'lloyds-industrial'),
            'catalogue_refreshed' => __('Catalogue page content refreshed.', 'lloyds-industrial'),
            'navigation_rebuilt'  => __('Primary navigation rebuilt from starter pages.', 'lloyds-industrial'),
            'terms_ensured'       => __('Product and industry terms checked.', 'lloyds-industrial'),
        ];

        if (isset($messages[$theme_settings_notice])) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html($messages[$theme_settings_notice])
            );
        }
    }

    $brand_reset_notice = get_transient('li_brand_reset_notice');

    if ($brand_reset_notice === 'reset') {
        delete_transient('li_brand_reset_notice');

        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html__('Brand colors reset to the theme defaults.', 'lloyds-industrial')
        );
    }

    $reseed_notice = get_transient('li_reseed_site_notice');

    if ($reseed_notice === 'missing_nonce') {
        delete_transient('li_reseed_site_notice');

        printf(
            '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
            esc_html__('Use the reseed button from this settings page. Direct reseed links require a security nonce.', 'lloyds-industrial')
        );
    }

    $result = get_transient('li_sds_migration_result');

    if (!is_array($result)) {
        return;
    }

    delete_transient('li_sds_migration_result');

    printf(
        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
        esc_html(sprintf(
            __('SDS protection check complete. Secured or organized: %1$d. Skipped: %2$d. Failed: %3$d.', 'lloyds-industrial'),
            (int) ($result['migrated'] ?? 0),
            (int) ($result['skipped'] ?? 0),
            (int) ($result['failed'] ?? 0)
        ))
    );
});

function li_sanitize_theme_settings(array $settings): array
{
    $existing = li_get_global_layout_settings();
    $defaults = li_get_global_layout_defaults();
    $stored = get_option('li_theme_settings', []);
    $context = sanitize_key((string) ($settings['_settings_context'] ?? 'global'));

    if ($context === 'mega_menu') {
        $stored = is_array($stored) ? $stored : [];
        $stored['mega_menu_enabled'] = !empty($settings['mega_menu_enabled']);
        $stored['mega_menu_data'] = li_sanitize_mega_menu_data($settings['mega_menu_data'] ?? ($stored['mega_menu_data'] ?? ''));

        return $stored;
    }

    $documents_mode = sanitize_key((string) ($settings['documents_mode'] ?? $existing['documents_mode']));
    if (!in_array($documents_mode, ['controlled', 'private'], true)) {
        $documents_mode = $defaults['documents_mode'];
    }

    $footer_density = sanitize_key((string) ($settings['footer_density'] ?? $existing['footer_density']));
    if (!in_array($footer_density, ['comfortable', 'compact'], true)) {
        $footer_density = $defaults['footer_density'];
    }

    $site_width = absint($settings['site_width'] ?? $existing['site_width'] ?? $defaults['site_width']);
    $site_width = min(1800, max(1080, $site_width ?: (int) $defaults['site_width']));

    $sanitized = [
        'partner_mode'              => !empty($settings['partner_mode']),
        'quote_mode'                => !empty($settings['quote_mode']),
        'documents_mode'            => $documents_mode,
        'sticky_header'              => !empty($settings['sticky_header']),
        'compact_header'             => !empty($settings['compact_header']),
        'show_site_title'            => !empty($settings['show_site_title']),
        'show_header_actions_mobile' => !empty($settings['show_header_actions_mobile']),
        'site_width'                 => $site_width,
        'footer_density'             => $footer_density,
        'announcement_enabled'       => !empty($settings['announcement_enabled']),
        'announcement_text'          => sanitize_text_field($settings['announcement_text'] ?? $existing['announcement_text']),
        'announcement_link_label'    => sanitize_text_field($settings['announcement_link_label'] ?? $existing['announcement_link_label']),
        'announcement_link_url'      => esc_url_raw($settings['announcement_link_url'] ?? $existing['announcement_link_url']),
        'header_primary_label'       => sanitize_text_field($settings['header_primary_label'] ?? $existing['header_primary_label']),
        'header_primary_url'         => esc_url_raw($settings['header_primary_url'] ?? $existing['header_primary_url']),
        'header_secondary_label'     => sanitize_text_field($settings['header_secondary_label'] ?? $existing['header_secondary_label']),
        'header_secondary_url'       => esc_url_raw($settings['header_secondary_url'] ?? $existing['header_secondary_url']),
        'show_account_link'          => !empty($settings['show_account_link']),
        'show_cart_link'             => !empty($settings['show_cart_link']),
        'footer_tagline'             => sanitize_text_field($settings['footer_tagline'] ?? $existing['footer_tagline']),
        'footer_legal_text'          => wp_kses_post($settings['footer_legal_text'] ?? $existing['footer_legal_text']),
        'footer_note_enabled'        => !empty($settings['footer_note_enabled']),
        'footer_note_text'           => sanitize_text_field($settings['footer_note_text'] ?? $existing['footer_note_text']),
        'mega_menu_enabled'          => isset($settings['mega_menu_enabled'])
            ? !empty($settings['mega_menu_enabled'])
            : (!isset($stored['mega_menu_enabled']) || !empty($stored['mega_menu_enabled'])),
        'mega_menu_data'             => li_sanitize_mega_menu_data($settings['mega_menu_data'] ?? $existing['mega_menu_data'] ?? ''),
    ];

    foreach (li_get_footer_column_indexes() as $index) {
        $sanitized['footer_column_' . $index . '_enabled'] = !empty($settings['footer_column_' . $index . '_enabled']);
        $sanitized['footer_column_' . $index . '_heading'] = sanitize_text_field($settings['footer_column_' . $index . '_heading'] ?? $existing['footer_column_' . $index . '_heading']);
        $sanitized['footer_column_' . $index . '_links'] = li_sanitize_footer_links((string) ($settings['footer_column_' . $index . '_links'] ?? $existing['footer_column_' . $index . '_links']));
    }

    return $sanitized;
}

function li_sanitize_footer_links(string $links): string
{
    $clean = [];

    foreach (li_parse_footer_links($links) as $link) {
        $label = sanitize_text_field($link['label']);
        $url = esc_url_raw($link['url']);

        if ($label === '' || $url === '') {
            continue;
        }

        $clean[] = $label . '|' . $url;
    }

    return implode("\n", $clean);
}

function li_render_site_behavior_section(): void
{
    ?>
    <p><?php esc_html_e('Control storefront posture, customer access, and document availability from one place.', 'lloyds-industrial'); ?></p>
    <?php
}

function li_render_partner_mode_field(): void
{
    $settings = get_option('li_theme_settings', []);
    $checked = !empty($settings['partner_mode']);
    ?>
    <label>
        <input
            type="checkbox"
            name="li_theme_settings[partner_mode]"
            value="1"
            <?php checked($checked); ?>
        >
        <?php esc_html_e('Enable partner-domain behavior for this site.', 'lloyds-industrial'); ?>
    </label>
    <?php
}

function li_render_document_access_mode_field(): void
{
    $mode = li_get_document_access_mode();
    ?>
    <fieldset>
        <label>
            <input
                type="radio"
                name="li_theme_settings[documents_mode]"
                value="controlled"
                <?php checked($mode, 'controlled'); ?>
            >
            <?php esc_html_e('Controlled: public technical documents remain available; SDS documents require login and related product purchase.', 'lloyds-industrial'); ?>
        </label>
        <br>
        <label>
            <input
                type="radio"
                name="li_theme_settings[documents_mode]"
                value="private"
                <?php checked($mode, 'private'); ?>
            >
            <?php esc_html_e('Private: hide customer-facing document listings and downloads from non-staff users.', 'lloyds-industrial'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('SDS files are never public in controlled mode; they are streamed only after the purchase-history check passes.', 'lloyds-industrial'); ?>
        </p>
    </fieldset>
    <?php
}

function li_render_layout_display_section(): void
{
    ?>
    <p><?php esc_html_e('Tune global layout behavior without editing template files.', 'lloyds-industrial'); ?></p>
    <?php
}

function li_render_header_display_field(): void
{
    $settings = li_get_global_layout_settings();
    ?>
    <fieldset>
        <p>
            <label>
                <input
                    type="checkbox"
                    name="li_theme_settings[sticky_header]"
                    value="1"
                    <?php checked(li_theme_setting_checkbox_value($settings, 'sticky_header')); ?>
                >
                <?php esc_html_e('Keep the header sticky on desktop.', 'lloyds-industrial'); ?>
            </label>
        </p>
        <p>
            <label>
                <input
                    type="checkbox"
                    name="li_theme_settings[compact_header]"
                    value="1"
                    <?php checked(!empty($settings['compact_header'])); ?>
                >
                <?php esc_html_e('Use a compact header height.', 'lloyds-industrial'); ?>
            </label>
        </p>
        <p>
            <label>
                <input
                    type="checkbox"
                    name="li_theme_settings[show_site_title]"
                    value="1"
                    <?php checked(li_theme_setting_checkbox_value($settings, 'show_site_title')); ?>
                >
                <?php esc_html_e('Show the site title beside the logo.', 'lloyds-industrial'); ?>
            </label>
        </p>
        <p>
            <label>
                <input
                    type="checkbox"
                    name="li_theme_settings[show_header_actions_mobile]"
                    value="1"
                    <?php checked(!empty($settings['show_header_actions_mobile'])); ?>
                >
                <?php esc_html_e('Show header action links on mobile.', 'lloyds-industrial'); ?>
            </label>
        </p>
    </fieldset>
    <?php
}

function li_render_site_width_field(): void
{
    $settings = li_get_global_layout_settings();
    $site_width = absint($settings['site_width'] ?? 1400) ?: 1400;
    ?>
    <label for="li_site_width">
        <input
            class="small-text"
            id="li_site_width"
            type="number"
            name="li_theme_settings[site_width]"
            value="<?php echo esc_attr((string) $site_width); ?>"
            min="1080"
            max="1800"
            step="20"
        >
        <?php esc_html_e('px wide content container', 'lloyds-industrial'); ?>
    </label>
    <p class="description"><?php esc_html_e('Controls the wide layout variable used by the header, footer, and wide content sections.', 'lloyds-industrial'); ?></p>
    <?php
}

function li_render_footer_density_field(): void
{
    $settings = li_get_global_layout_settings();
    $density = (string) ($settings['footer_density'] ?? 'comfortable');
    ?>
    <fieldset>
        <label>
            <input
                type="radio"
                name="li_theme_settings[footer_density]"
                value="comfortable"
                <?php checked($density, 'comfortable'); ?>
            >
            <?php esc_html_e('Comfortable', 'lloyds-industrial'); ?>
        </label>
        <br>
        <label>
            <input
                type="radio"
                name="li_theme_settings[footer_density]"
                value="compact"
                <?php checked($density, 'compact'); ?>
            >
            <?php esc_html_e('Compact', 'lloyds-industrial'); ?>
        </label>
    </fieldset>
    <?php
}

function li_render_global_layout_section(): void
{
    ?>
    <p><?php esc_html_e('Control common header and footer copy without editing the global template parts.', 'lloyds-industrial'); ?></p>
    <?php
}

function li_render_brand_palette_field(): void
{
    $settings = li_get_brand_settings();
    $defaults = li_get_brand_defaults();
    $labels = li_get_brand_color_labels();
    $customize_url = add_query_arg(
        [
            'autofocus[section]' => 'li_brand_settings',
        ],
        admin_url('customize.php')
    );
    $reset_url = wp_nonce_url(
        admin_url('admin.php?page=lloyds&li_reset_brand_settings=1'),
        'li_reset_brand_settings'
    );
    ?>
    <div class="li-brand-palette-admin">
        <p class="description">
            <?php esc_html_e('Current brand colors are managed in the Customizer. Resetting removes saved color overrides and uses the theme defaults.', 'lloyds-industrial'); ?>
        </p>

        <div class="li-brand-palette-grid">
            <?php foreach ($labels as $key => $label) : ?>
                <?php
                $color = sanitize_hex_color($settings[$key] ?? '') ?: ($defaults[$key] ?? '#000000');
                $default_color = sanitize_hex_color($defaults[$key] ?? '') ?: '#000000';
                $is_default = strtoupper($color) === strtoupper($default_color);
                ?>
                <div class="li-brand-swatch-card">
                    <span
                        class="li-brand-swatch"
                        aria-hidden="true"
                        style="background:<?php echo esc_attr($color); ?>;"
                    ></span>
                    <span>
                        <strong><?php echo esc_html($label); ?></strong>
                        <code><?php echo esc_html(strtoupper($color)); ?></code>
                        <?php if (!$is_default) : ?>
                            <span class="li-brand-custom-badge"><?php esc_html_e('Custom', 'lloyds-industrial'); ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <p>
            <a class="button button-secondary" href="<?php echo esc_url($customize_url); ?>">
                <?php esc_html_e('Edit Brand Colors', 'lloyds-industrial'); ?>
            </a>
            <a
                class="button button-link-delete"
                href="<?php echo esc_url($reset_url); ?>"
                onclick="return confirm('<?php echo esc_js(__('Reset brand colors to the theme defaults?', 'lloyds-industrial')); ?>');"
            >
                <?php esc_html_e('Reset Colors to Defaults', 'lloyds-industrial'); ?>
            </a>
        </p>
    </div>
    <?php
}

function li_render_announcement_field(): void
{
    $settings = li_get_global_layout_settings();
    ?>
    <fieldset>
        <label>
            <input
                type="checkbox"
                name="li_theme_settings[announcement_enabled]"
                value="1"
                <?php checked(li_theme_setting_checkbox_value($settings, 'announcement_enabled')); ?>
            >
            <?php esc_html_e('Show the announcement bar.', 'lloyds-industrial'); ?>
        </label>

        <p>
            <label for="li_announcement_text"><?php esc_html_e('Message', 'lloyds-industrial'); ?></label><br>
            <input
                class="regular-text"
                id="li_announcement_text"
                type="text"
                name="li_theme_settings[announcement_text]"
                value="<?php echo esc_attr((string) $settings['announcement_text']); ?>"
            >
        </p>

        <p>
            <label for="li_announcement_link_label"><?php esc_html_e('Link Label', 'lloyds-industrial'); ?></label><br>
            <input
                class="regular-text"
                id="li_announcement_link_label"
                type="text"
                name="li_theme_settings[announcement_link_label]"
                value="<?php echo esc_attr((string) $settings['announcement_link_label']); ?>"
            >
        </p>

        <p>
            <label for="li_announcement_link_url"><?php esc_html_e('Link URL', 'lloyds-industrial'); ?></label><br>
            <input
                class="regular-text code"
                id="li_announcement_link_url"
                type="text"
                name="li_theme_settings[announcement_link_url]"
                value="<?php echo esc_attr((string) $settings['announcement_link_url']); ?>"
            >
        </p>
    </fieldset>
    <?php
}

function li_render_header_actions_field(): void
{
    $settings = li_get_global_layout_settings();
    ?>
    <fieldset>
        <p>
            <label for="li_header_primary_label"><?php esc_html_e('Primary Link Label', 'lloyds-industrial'); ?></label><br>
            <input
                class="regular-text"
                id="li_header_primary_label"
                type="text"
                name="li_theme_settings[header_primary_label]"
                value="<?php echo esc_attr((string) $settings['header_primary_label']); ?>"
            >
        </p>

        <p>
            <label for="li_header_primary_url"><?php esc_html_e('Primary Link URL', 'lloyds-industrial'); ?></label><br>
            <input
                class="regular-text code"
                id="li_header_primary_url"
                type="text"
                name="li_theme_settings[header_primary_url]"
                value="<?php echo esc_attr((string) $settings['header_primary_url']); ?>"
            >
        </p>

        <p>
            <label for="li_header_secondary_label"><?php esc_html_e('Button Label', 'lloyds-industrial'); ?></label><br>
            <input
                class="regular-text"
                id="li_header_secondary_label"
                type="text"
                name="li_theme_settings[header_secondary_label]"
                value="<?php echo esc_attr((string) $settings['header_secondary_label']); ?>"
            >
        </p>

        <p>
            <label for="li_header_secondary_url"><?php esc_html_e('Button URL', 'lloyds-industrial'); ?></label><br>
            <input
                class="regular-text code"
                id="li_header_secondary_url"
                type="text"
                name="li_theme_settings[header_secondary_url]"
                value="<?php echo esc_attr((string) $settings['header_secondary_url']); ?>"
            >
        </p>

        <p>
            <label>
                <input
                    type="checkbox"
                    name="li_theme_settings[show_account_link]"
                    value="1"
                    <?php checked(li_theme_setting_checkbox_value($settings, 'show_account_link')); ?>
                >
                <?php esc_html_e('Show account link.', 'lloyds-industrial'); ?>
            </label>
        </p>

        <p>
            <label>
                <input
                    type="checkbox"
                    name="li_theme_settings[show_cart_link]"
                    value="1"
                    <?php checked(li_theme_setting_checkbox_value($settings, 'show_cart_link')); ?>
                >
                <?php esc_html_e('Show cart link when WooCommerce is active.', 'lloyds-industrial'); ?>
            </label>
        </p>
    </fieldset>
    <?php
}

function li_render_footer_content_field(): void
{
    $settings = li_get_global_layout_settings();
    ?>
    <fieldset>
        <p>
            <label for="li_footer_tagline"><?php esc_html_e('Tagline', 'lloyds-industrial'); ?></label><br>
            <textarea
                class="large-text"
                id="li_footer_tagline"
                name="li_theme_settings[footer_tagline]"
                rows="3"
            ><?php echo esc_textarea((string) $settings['footer_tagline']); ?></textarea>
        </p>

        <p>
            <label for="li_footer_legal_text"><?php esc_html_e('Legal Text', 'lloyds-industrial'); ?></label><br>
            <input
                class="large-text"
                id="li_footer_legal_text"
                type="text"
                name="li_theme_settings[footer_legal_text]"
                value="<?php echo esc_attr((string) $settings['footer_legal_text']); ?>"
            >
            <span class="description"><?php esc_html_e('Available tokens: {year}, {site}.', 'lloyds-industrial'); ?></span>
        </p>

        <p>
            <label>
                <input
                    type="checkbox"
                    name="li_theme_settings[footer_note_enabled]"
                    value="1"
                    <?php checked(li_theme_setting_checkbox_value($settings, 'footer_note_enabled')); ?>
                >
                <?php esc_html_e('Show footer note.', 'lloyds-industrial'); ?>
            </label>
        </p>

        <p>
            <label for="li_footer_note_text"><?php esc_html_e('Footer Note', 'lloyds-industrial'); ?></label><br>
            <input
                class="large-text"
                id="li_footer_note_text"
                type="text"
                name="li_theme_settings[footer_note_text]"
                value="<?php echo esc_attr((string) $settings['footer_note_text']); ?>"
            >
            <span class="description"><?php esc_html_e('Available tokens: {year}, {site}.', 'lloyds-industrial'); ?></span>
        </p>

        <hr>

        <?php foreach (li_get_footer_column_indexes() as $index) : ?>
            <div class="li-admin-footer-column">
                <h4>
                    <?php
                    echo esc_html(sprintf(
                        /* translators: %d: footer column number */
                        __('Footer Column %d', 'lloyds-industrial'),
                        $index
                    ));
                    ?>
                </h4>

                <p>
                    <label>
                        <input
                            type="checkbox"
                            name="li_theme_settings[footer_column_<?php echo esc_attr((string) $index); ?>_enabled]"
                            value="1"
                            <?php checked(li_theme_setting_checkbox_value($settings, 'footer_column_' . $index . '_enabled')); ?>
                        >
                        <?php esc_html_e('Show this column.', 'lloyds-industrial'); ?>
                    </label>
                </p>

                <p>
                    <label for="li_footer_column_<?php echo esc_attr((string) $index); ?>_heading"><?php esc_html_e('Heading', 'lloyds-industrial'); ?></label><br>
                    <input
                        class="regular-text"
                        id="li_footer_column_<?php echo esc_attr((string) $index); ?>_heading"
                        type="text"
                        name="li_theme_settings[footer_column_<?php echo esc_attr((string) $index); ?>_heading]"
                        value="<?php echo esc_attr((string) $settings['footer_column_' . $index . '_heading']); ?>"
                    >
                </p>

                <p>
                    <label for="li_footer_column_<?php echo esc_attr((string) $index); ?>_links"><?php esc_html_e('Links', 'lloyds-industrial'); ?></label><br>
                    <textarea
                        class="large-text code"
                        id="li_footer_column_<?php echo esc_attr((string) $index); ?>_links"
                        name="li_theme_settings[footer_column_<?php echo esc_attr((string) $index); ?>_links]"
                        rows="5"
                    ><?php echo esc_textarea((string) $settings['footer_column_' . $index . '_links']); ?></textarea>
                    <span class="description"><?php esc_html_e('One link per line using Label|URL.', 'lloyds-industrial'); ?></span>
                </p>
            </div>
        <?php endforeach; ?>
    </fieldset>
    <?php
}

function li_render_mega_menu_section(): void
{
    ?>
    <p><?php esc_html_e('Configure the custom mega menu for your site. The mega menu supports rich content including icons, badges, multi-column layouts, and featured promo panels.', 'lloyds-industrial'); ?></p>
    <?php
}

function li_render_mega_menu_enabled_field(): void
{
    $settings = get_option('li_theme_settings', []);
    $checked = !isset($settings['mega_menu_enabled']) || !empty($settings['mega_menu_enabled']);
    ?>
    <label>
        <input
            type="checkbox"
            name="li_theme_settings[mega_menu_enabled]"
            value="1"
            <?php checked($checked); ?>
        >
        <?php esc_html_e('Enable the custom mega menu (replaces the default navigation).', 'lloyds-industrial'); ?>
    </label>
    <?php
}

function li_render_mega_menu_data_field(): void
{
    $settings = get_option('li_theme_settings', []);
    $mega_menu_data = $settings['mega_menu_data'] ?? '';
    $menu_items = [];
    
    // If we have stored JSON data, decode it
    if ($mega_menu_data !== '') {
        $decoded = json_decode($mega_menu_data, true);
        if (is_array($decoded)) {
            $menu_items = $decoded;
        }
    }
    
    // If no data, use defaults
    if (empty($menu_items)) {
        $menu_items = li_get_default_mega_menu();
    }
    
    // Get available pages for dropdown selection
    $pages = get_pages();
    $page_options = ['' => __('-- Select Page --', 'lloyds-industrial')];
    foreach ($pages as $page) {
        $page_options[$page->ID] = $page->post_title;
    }
    
    // Get available product categories for dropdown selection
    $product_categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ]);
    $category_options = ['' => __('-- Select Category --', 'lloyds-industrial')];
    if (!is_wp_error($product_categories)) {
        foreach ($product_categories as $category) {
            if (!$category instanceof WP_Term) {
                continue;
            }

            $category_options[$category->term_id] = $category->name;
        }
    }
    
    wp_enqueue_media();

    // Enqueue the admin script (for media picker functionality)
    wp_enqueue_script(
        'lloyds-admin',
        get_template_directory_uri() . '/assets/js/admin.js',
        ['media-editor', 'media-views'],
        wp_get_theme()->get('Version'),
        true
    );
    
    // Enqueue the mega menu admin script
    wp_enqueue_script(
        'li-mega-menu-admin',
        get_template_directory_uri() . '/assets/js/mega-menu-admin.js',
        ['jquery', 'jquery-ui-sortable', 'wp-color-picker'],
        wp_get_theme()->get('Version'),
        true
    );
    
    wp_localize_script('li-mega-menu-admin', 'liMegaMenu', [
        'defaultMenu' => li_get_default_mega_menu(),
        'defaultItem' => [
            'label' => '',
            'url' => '',
            'icon' => '',
            'image' => '',
            'description' => '',
            'bg_color' => '',
            'text_color' => '',
            'hover_color' => '',
            'badge' => '',
            'badge_color' => '#0066cc',
            'badge_text_color' => '#ffffff',
            'column' => 1,
            'featured' => [
                'enabled' => false,
                'image' => '',
                'title' => '',
                'text' => '',
                'url' => '',
                'button_label' => '',
            ],
            'enabled' => true,
            'new_tab' => false,
            'mobile_order' => 0,
            'mobile_visible' => true,
            'children' => [],
        ],
        'confirmations' => [
            'reset' => __('Are you sure you want to reset the mega menu to defaults? This cannot be undone.', 'lloyds-industrial'),
            'removeItem' => __('Are you sure you want to remove this menu item?', 'lloyds-industrial'),
            'removeChild' => __('Are you sure you want to remove this child item?', 'lloyds-industrial'),
        ],
        'mediaTitle' => __('Select Image', 'lloyds-industrial'),
        'mediaButton' => __('Use This Image', 'lloyds-industrial'),
        'labels' => [
            'showSettings' => __('Show item settings', 'lloyds-industrial'),
            'hideSettings' => __('Hide item settings', 'lloyds-industrial'),
        ],
    ]);
    
    // Enqueue styles for the admin interface
    wp_enqueue_style(
        'li-mega-menu-admin',
        get_template_directory_uri() . '/assets/css/mega-menu-admin.css',
        ['wp-color-picker'],
        wp_get_theme()->get('Version')
    );
    
    ?>
    <div class="li-mega-menu-admin-container">
        <div class="li-mega-menu-admin-header">
            <div>
                <p class="li-mega-menu-kicker"><?php esc_html_e('Builder', 'lloyds-industrial'); ?></p>
                <h2><?php esc_html_e('Menu Structure', 'lloyds-industrial'); ?></h2>
                <p><?php esc_html_e('Drag items to reorder them, then expand a row to edit links, badges, icons, colors, and mobile behavior.', 'lloyds-industrial'); ?></p>
            </div>
            <div class="li-mega-menu-admin-actions">
                <button type="button" class="button button-secondary li-mega-menu-add-item">
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                    <?php esc_html_e('Add Top Level Item', 'lloyds-industrial'); ?>
                </button>
                <button type="button" class="button button-secondary li-mega-menu-reset">
                    <span class="dashicons dashicons-image-rotate" aria-hidden="true"></span>
                    <?php esc_html_e('Reset to Defaults', 'lloyds-industrial'); ?>
                </button>
            </div>
        </div>
        
        <div class="li-mega-menu-admin-help">
            <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
            <p><?php esc_html_e('Top-level items become menu columns or dropdown triggers. Child items appear inside the opened panel.', 'lloyds-industrial'); ?></p>
        </div>
        
        <!-- Hidden textarea to store the JSON data (for form submission) -->
        <textarea 
            id="li_mega_menu_data_json" 
            name="li_theme_settings[mega_menu_data]" 
            class="hidden"
            data-lpignore="true"
        ><?php echo esc_textarea(json_encode($menu_items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></textarea>
        
        <!-- Main menu items container -->
        <div class="li-mega-menu-items" id="li_mega_menu_items">
            <?php 
            // Render existing menu items
            $item_index = 0;
            foreach ($menu_items as $item) {
                li_render_mega_menu_item_form($item, $item_index, $page_options, $category_options);
                $item_index++;
            }
            ?>
        </div>
        
        <!-- Template for new items (hidden) -->
        <script type="text/html" id="li-mega-menu-item-template">
            <?php 
            // Render a template item with placeholder values
            $template_item = [
                'label' => '',
                'url' => '',
                'icon' => '',
                'image' => '',
                'description' => '',
                'bg_color' => '',
                'text_color' => '',
                'hover_color' => '',
                'badge' => '',
                'badge_color' => '#0066cc',
                'badge_text_color' => '#ffffff',
                'column' => 1,
                'featured' => [
                    'enabled' => false,
                    'image' => '',
                    'title' => '',
                    'text' => '',
                    'url' => '',
                    'button_label' => '',
                ],
                'enabled' => true,
                'new_tab' => false,
                'mobile_order' => 0,
                'mobile_visible' => true,
                'children' => [],
            ];
            li_render_mega_menu_item_form($template_item, '__INDEX__', $page_options, $category_options, true);
            ?>
        </script>
        
        <script type="text/html" id="li-mega-menu-child-template">
            <?php 
            // Template for child items
            $child_template = [
                'label' => '',
                'url' => '',
                'icon' => '',
                'image' => '',
                'description' => '',
                'bg_color' => '',
                'text_color' => '',
                'hover_color' => '',
                'badge' => '',
                'badge_color' => '#0066cc',
                'badge_text_color' => '#ffffff',
                'column' => 1,
                'featured' => [],
                'enabled' => true,
                'new_tab' => false,
                'mobile_order' => 0,
                'mobile_visible' => true,
                'children' => [],
            ];
            li_render_mega_menu_item_form($child_template, '__PARENT_INDEX__', $page_options, $category_options, true, true);
            ?>
        </script>
    </div>
    <?php
}

/**
 * Render a single menu item form for the admin interface
 */
function li_render_mega_menu_item_form(array $item, int|string $index, array $page_options, array $category_options, bool $is_template = false, bool $is_child = false): void
{
    // Ensure we have all required keys with defaults
    $defaults = [
        'label' => '',
        'url' => '',
        'icon' => '',
        'image' => '',
        'description' => '',
        'bg_color' => '',
        'text_color' => '',
        'hover_color' => '',
        'badge' => '',
        'badge_color' => '#0066cc',
        'badge_text_color' => '#ffffff',
        'column' => 1,
        'featured' => [
            'enabled' => false,
            'image' => '',
            'title' => '',
            'text' => '',
            'url' => '',
            'button_label' => '',
        ],
        'enabled' => true,
        'new_tab' => false,
        'mobile_order' => 0,
        'mobile_visible' => true,
        'children' => [],
    ];
    
    $item = wp_parse_args($item, $defaults);
    $item_class = 'li-mega-menu-item-form';
    if ($is_child) {
        $item_class .= ' li-mega-menu-item-form--child';
    }
    
    // For template items, we'll use data attributes for JavaScript collection
    $name_prefix = "";
    
    // Build the featured panel data
    $featured = wp_parse_args($item['featured'], [
        'enabled' => false,
        'image' => '',
        'title' => '',
        'text' => '',
        'url' => '',
        'button_label' => '',
    ]);
    
    ?>
    <div class="<?php echo esc_attr($item_class); ?>" data-index="<?php echo esc_attr((string) $index); ?>">
        <div class="li-mega-menu-item-header">
            <div class="li-mega-menu-item-handle">
                <span class="dashicons dashicons-menu"></span>
                <span class="li-mega-menu-item-title">
                    <?php echo esc_html($item['label'] ?: __('New Menu Item', 'lloyds-industrial')); ?>
                </span>
            </div>
            <div class="li-mega-menu-item-actions">
                <button
                    type="button"
                    class="li-mega-menu-action li-mega-menu-action--toggle li-mega-menu-item-toggle"
                    aria-label="<?php esc_attr_e('Show item settings', 'lloyds-industrial'); ?>"
                    title="<?php esc_attr_e('Show item settings', 'lloyds-industrial'); ?>"
                >
                    <span class="dashicons dashicons-arrow-down"></span>
                </button>
                <?php if (!$is_child): ?>
                    <button
                        type="button"
                        class="li-mega-menu-action li-mega-menu-action--add li-mega-menu-add-child"
                        aria-label="<?php esc_attr_e('Add child item', 'lloyds-industrial'); ?>"
                        title="<?php esc_attr_e('Add child item', 'lloyds-industrial'); ?>"
                    >
                        <span class="dashicons dashicons-plus"></span>
                        <span><?php esc_html_e('Child', 'lloyds-industrial'); ?></span>
                    </button>
                <?php endif; ?>
                <button
                    type="button"
                    class="li-mega-menu-action li-mega-menu-action--remove li-mega-menu-item-remove"
                    aria-label="<?php esc_attr_e('Remove item', 'lloyds-industrial'); ?>"
                    title="<?php esc_attr_e('Remove item', 'lloyds-industrial'); ?>"
                >
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        </div>
        
        <div class="li-mega-menu-item-body" style="display: none;">
            <div class="li-mega-menu-item-fields">
                <!-- Basic Settings -->
                <div class="li-mega-menu-field-group">
                    <h5><?php esc_html_e('Basic Settings', 'lloyds-industrial'); ?></h5>
                    
                    <p class="li-mega-menu-field">
                        <label for="li_mega_menu_label_<?php echo esc_attr((string) $index); ?>">
                            <strong><?php esc_html_e('Label', 'lloyds-industrial'); ?></strong>
                        </label>
                        <input 
                            type="text" 
                            id="li_mega_menu_label_<?php echo esc_attr((string) $index); ?>"
                            data-field="label"
                            value="<?php echo esc_attr($item['label']); ?>"
                            class="widefat"
                            placeholder="<?php esc_attr_e('Enter menu item label', 'lloyds-industrial'); ?>"
                        >
                    </p>
                    
                    <p class="li-mega-menu-field">
                        <label for="li_mega_menu_url_<?php echo esc_attr((string) $index); ?>">
                            <strong><?php esc_html_e('URL', 'lloyds-industrial'); ?></strong>
                        </label>
                        <input
                            type="text"
                            inputmode="url"
                            id="li_mega_menu_url_<?php echo esc_attr((string) $index); ?>"
                            data-field="url"
                            value="<?php echo esc_attr($item['url']); ?>"
                            class="widefat"
                            placeholder="<?php esc_attr_e('https://example.com or /page-slug', 'lloyds-industrial'); ?>"
                        >
                        <span class="description">
                            <?php esc_html_e('Enter URL or select from page dropdown', 'lloyds-industrial'); ?>
                        </span>
                    </p>
                    
                    <p class="li-mega-menu-field">
                        <label for="li_mega_menu_icon_<?php echo esc_attr((string) $index); ?>">
                            <?php esc_html_e('Icon (Emoji)', 'lloyds-industrial'); ?>
                        </label>
                        <input 
                            type="text" 
                            id="li_mega_menu_icon_<?php echo esc_attr((string) $index); ?>"
                            data-field="icon"
                            value="<?php echo esc_attr($item['icon']); ?>"
                            class="regular-text"
                            placeholder="<?php esc_attr_e('🛢️', 'lloyds-industrial'); ?>"
                            maxlength="2"
                        >
                    </p>
                    
                    <p class="li-mega-menu-field">
                        <label for="li_mega_menu_description_<?php echo esc_attr((string) $index); ?>">
                            <?php esc_html_e('Description', 'lloyds-industrial'); ?>
                        </label>
                        <textarea 
                            id="li_mega_menu_description_<?php echo esc_attr((string) $index); ?>"
                            data-field="description"
                            class="widefat"
                            rows="2"
                            placeholder="<?php esc_attr_e('Optional subtext for this menu item', 'lloyds-industrial'); ?>"
                        ><?php echo esc_textarea($item['description']); ?></textarea>
                    </p>
                </div>
                
                <!-- Appearance Settings -->
                <div class="li-mega-menu-field-group">
                    <h5><?php esc_html_e('Appearance', 'lloyds-industrial'); ?></h5>
                    
                    <div class="li-mega-menu-field-row">
                        <p class="li-mega-menu-field li-mega-menu-field--half">
                            <label for="li_mega_menu_bg_color_<?php echo esc_attr((string) $index); ?>">
                                <?php esc_html_e('Background Color', 'lloyds-industrial'); ?>
                            </label>
                            <input 
                                type="text" 
                                id="li_mega_menu_bg_color_<?php echo esc_attr((string) $index); ?>"
                                data-field="bg_color"
                                value="<?php echo esc_attr($item['bg_color']); ?>"
                                class="regular-text li-color-picker"
                                placeholder="<?php esc_attr_e('#rrggbb or color name', 'lloyds-industrial'); ?>"
                                data-default-color=""
                            >
                        </p>
                        
                        <p class="li-mega-menu-field li-mega-menu-field--half">
                            <label for="li_mega_menu_text_color_<?php echo esc_attr((string) $index); ?>">
                                <?php esc_html_e('Text Color', 'lloyds-industrial'); ?>
                            </label>
                            <input 
                                type="text" 
                                id="li_mega_menu_text_color_<?php echo esc_attr((string) $index); ?>"
                                data-field="text_color"
                                value="<?php echo esc_attr($item['text_color']); ?>"
                                class="regular-text li-color-picker"
                                placeholder="<?php esc_attr_e('#rrggbb', 'lloyds-industrial'); ?>"
                                data-default-color=""
                            >
                        </p>
                    </div>
                    
                    <p class="li-mega-menu-field">
                        <label for="li_mega_menu_hover_color_<?php echo esc_attr((string) $index); ?>">
                            <?php esc_html_e('Hover Color', 'lloyds-industrial'); ?>
                        </label>
                        <input 
                            type="text" 
                            id="li_mega_menu_hover_color_<?php echo esc_attr((string) $index); ?>"
                            data-field="hover_color"
                            value="<?php echo esc_attr($item['hover_color']); ?>"
                            class="regular-text li-color-picker"
                            placeholder="<?php esc_attr_e('#rrggbb', 'lloyds-industrial'); ?>"
                            data-default-color=""
                        >
                    </p>
                </div>
                
                <!-- Badge Settings -->
                <div class="li-mega-menu-field-group">
                    <h5><?php esc_html_e('Badge', 'lloyds-industrial'); ?></h5>
                    
                    <div class="li-mega-menu-field-row">
                        <p class="li-mega-menu-field li-mega-menu-field--half">
                            <label for="li_mega_menu_badge_<?php echo esc_attr((string) $index); ?>">
                                <?php esc_html_e('Badge Text', 'lloyds-industrial'); ?>
                            </label>
                            <input 
                                type="text" 
                                id="li_mega_menu_badge_<?php echo esc_attr((string) $index); ?>"
                                name="<?php echo esc_attr($name_prefix); ?>[badge]"
                                value="<?php echo esc_attr($item['badge']); ?>"
                                class="regular-text"
                                placeholder="<?php esc_attr_e('New, Hot, etc.', 'lloyds-industrial'); ?>"
                            >
                        </p>
                        
                        <p class="li-mega-menu-field li-mega-menu-field--half">
                            <label for="li_mega_menu_badge_color_<?php echo esc_attr((string) $index); ?>">
                                <?php esc_html_e('Badge Background', 'lloyds-industrial'); ?>
                            </label>
                            <input 
                                type="text" 
                                id="li_mega_menu_badge_color_<?php echo esc_attr((string) $index); ?>"
                                name="<?php echo esc_attr($name_prefix); ?>[badge_color]"
                                value="<?php echo esc_attr($item['badge_color']); ?>"
                                class="regular-text li-color-picker"
                                placeholder="<?php esc_attr_e('#rrggbb', 'lloyds-industrial'); ?>"
                                data-default-color="#0066cc"
                            >
                        </p>
                    </div>
                    
                    <p class="li-mega-menu-field">
                        <label for="li_mega_menu_badge_text_color_<?php echo esc_attr((string) $index); ?>">
                            <?php esc_html_e('Badge Text Color', 'lloyds-industrial'); ?>
                        </label>
                        <input 
                            type="text" 
                            id="li_mega_menu_badge_text_color_<?php echo esc_attr((string) $index); ?>"
                            name="<?php echo esc_attr($name_prefix); ?>[badge_text_color]"
                            value="<?php echo esc_attr($item['badge_text_color']); ?>"
                            class="regular-text li-color-picker"
                            placeholder="<?php esc_attr_e('#ffffff', 'lloyds-industrial'); ?>"
                            data-default-color="#ffffff"
                        >
                    </p>
                </div>
                
                <!-- Layout & Behavior -->
                <div class="li-mega-menu-field-group">
                    <h5><?php esc_html_e('Layout & Behavior', 'lloyds-industrial'); ?></h5>
                    
                    <div class="li-mega-menu-field-row">
                        <p class="li-mega-menu-field li-mega-menu-field--half">
                            <label>
                                <input 
                                    type="checkbox" 
                                    name="<?php echo esc_attr($name_prefix); ?>[enabled]"
                                    value="1"
                                    <?php checked($item['enabled']); ?>
                                >
                                <?php esc_html_e('Enabled', 'lloyds-industrial'); ?>
                            </label>
                        </p>
                        
                        <p class="li-mega-menu-field li-mega-menu-field--half">
                            <label>
                                <input 
                                    type="checkbox" 
                                    name="<?php echo esc_attr($name_prefix); ?>[new_tab]"
                                    value="1"
                                    <?php checked($item['new_tab']); ?>
                                >
                                <?php esc_html_e('Open in New Tab', 'lloyds-industrial'); ?>
                            </label>
                        </p>
                    </div>
                    
                    <div class="li-mega-menu-field-row">
                        <p class="li-mega-menu-field li-mega-menu-field--half">
                            <label>
                                <input 
                                    type="checkbox" 
                                    name="<?php echo esc_attr($name_prefix); ?>[mobile_visible]"
                                    value="1"
                                    <?php checked($item['mobile_visible']); ?>
                                >
                                <?php esc_html_e('Visible on Mobile', 'lloyds-industrial'); ?>
                            </label>
                        </p>
                        
                        <p class="li-mega-menu-field li-mega-menu-field--half">
                            <label for="li_mega_menu_mobile_order_<?php echo esc_attr((string) $index); ?>">
                                <?php esc_html_e('Mobile Order', 'lloyds-industrial'); ?>
                            </label>
                            <input 
                                type="number" 
                                id="li_mega_menu_mobile_order_<?php echo esc_attr((string) $index); ?>"
                                name="<?php echo esc_attr($name_prefix); ?>[mobile_order]"
                                value="<?php echo esc_attr((string) $item['mobile_order']); ?>"
                                class="small-text"
                                min="0"
                            >
                        </p>
                    </div>
                    
                    <?php if (!$is_child): ?>
                        <p class="li-mega-menu-field">
                            <label for="li_mega_menu_column_<?php echo esc_attr((string) $index); ?>">
                                <?php esc_html_e('Default Column for Children', 'lloyds-industrial'); ?>
                            </label>
                            <select 
                                id="li_mega_menu_column_<?php echo esc_attr((string) $index); ?>"
                                name="<?php echo esc_attr($name_prefix); ?>[column]"
                                class="regular-text"
                            >
                                <?php for ($col = 1; $col <= 4; $col++): ?>
                                    <option 
                                        value="<?php echo esc_attr((string) $col); ?>" 
                                        <?php selected($item['column'], $col); ?>
                                    >
                                        <?php echo esc_html(sprintf(__('Column %d', 'lloyds-industrial'), $col)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </p>
                    <?php else: ?>
                        <p class="li-mega-menu-field">
                            <label for="li_mega_menu_column_<?php echo esc_attr((string) $index); ?>">
                                <?php esc_html_e('Column Position', 'lloyds-industrial'); ?>
                            </label>
                            <select 
                                id="li_mega_menu_column_<?php echo esc_attr((string) $index); ?>"
                                name="<?php echo esc_attr($name_prefix); ?>[column]"
                                class="regular-text"
                            >
                                <?php for ($col = 1; $col <= 4; $col++): ?>
                                    <option 
                                        value="<?php echo esc_attr((string) $col); ?>" 
                                        <?php selected($item['column'], $col); ?>
                                    >
                                        <?php echo esc_html(sprintf(__('Column %d', 'lloyds-industrial'), $col)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </p>
                    <?php endif; ?>
                </div>
                
                <!-- Featured Panel Settings (for top-level items) -->
                <?php if (!$is_child): ?>
                    <div class="li-mega-menu-field-group">
                        <h5><?php esc_html_e('Featured Panel', 'lloyds-industrial'); ?></h5>
                        
                        <p class="li-mega-menu-field">
                            <label>
                                <input 
                                    type="checkbox" 
                                    name="<?php echo esc_attr($name_prefix); ?>[featured][enabled]"
                                    value="1"
                                    class="li-featured-enabled-toggle"
                                    data-field="featured_enabled"
                                    <?php checked($featured['enabled']); ?>
                                    data-target="li-featured-panel-<?php echo esc_attr((string) $index); ?>"
                                >
                                <?php esc_html_e('Enable Featured Panel', 'lloyds-industrial'); ?>
                            </label>
                        </p>
                        
                        <div class="li-featured-panel-settings" id="li-featured-panel-<?php echo esc_attr((string) $index); ?>" style="<?php echo $featured['enabled'] ? '' : 'display:none;'; ?>">
                            <p class="li-mega-menu-field">
                                <label for="li_mega_menu_featured_title_<?php echo esc_attr((string) $index); ?>">
                                    <?php esc_html_e('Title', 'lloyds-industrial'); ?>
                                </label>
                                <input 
                                    type="text" 
                                    id="li_mega_menu_featured_title_<?php echo esc_attr((string) $index); ?>"
                                    name="<?php echo esc_attr($name_prefix); ?>[featured][title]"
                                    data-field="featured_title"
                                    value="<?php echo esc_attr($featured['title']); ?>"
                                    class="widefat"
                                    placeholder="<?php esc_attr_e('Featured panel title', 'lloyds-industrial'); ?>"
                                >
                            </p>
                            
                            <p class="li-mega-menu-field">
                                <label for="li_mega_menu_featured_text_<?php echo esc_attr((string) $index); ?>">
                                    <?php esc_html_e('Text', 'lloyds-industrial'); ?>
                                </label>
                                <textarea 
                                    id="li_mega_menu_featured_text_<?php echo esc_attr((string) $index); ?>"
                                    name="<?php echo esc_attr($name_prefix); ?>[featured][text]"
                                    data-field="featured_text"
                                    class="widefat"
                                    rows="3"
                                    placeholder="<?php esc_attr_e('Featured panel description', 'lloyds-industrial'); ?>"
                                ><?php echo esc_textarea($featured['text']); ?></textarea>
                            </p>
                            
                            <p class="li-mega-menu-field">
                                <label for="li_mega_menu_featured_url_<?php echo esc_attr((string) $index); ?>">
                                    <?php esc_html_e('URL', 'lloyds-industrial'); ?>
                                </label>
                                <input
                                    type="text"
                                    inputmode="url"
                                    id="li_mega_menu_featured_url_<?php echo esc_attr((string) $index); ?>"
                                    name="<?php echo esc_attr($name_prefix); ?>[featured][url]"
                                    data-field="featured_url"
                                    value="<?php echo esc_attr($featured['url']); ?>"
                                    class="widefat"
                                    placeholder="<?php esc_attr_e('https://example.com or /page-slug', 'lloyds-industrial'); ?>"
                                >
                            </p>
                            
                            <p class="li-mega-menu-field">
                                <label for="li_mega_menu_featured_button_<?php echo esc_attr((string) $index); ?>">
                                    <?php esc_html_e('Button Label', 'lloyds-industrial'); ?>
                                </label>
                                <input 
                                    type="text" 
                                    id="li_mega_menu_featured_button_<?php echo esc_attr((string) $index); ?>"
                                    name="<?php echo esc_attr($name_prefix); ?>[featured][button_label]"
                                    data-field="featured_button_label"
                                    value="<?php echo esc_attr($featured['button_label']); ?>"
                                    class="regular-text"
                                    placeholder="<?php esc_attr_e('Learn More', 'lloyds-industrial'); ?>"
                                >
                            </p>
                            
                            <p class="li-mega-menu-field">
                                <label for="li_mega_menu_featured_image_<?php echo esc_attr((string) $index); ?>">
                                    <?php esc_html_e('Image', 'lloyds-industrial'); ?>
                                </label>
                                <span data-li-media-picker>
                                    <input
                                        type="hidden"
                                        id="li_mega_menu_featured_image_<?php echo esc_attr((string) $index); ?>"
                                        name="<?php echo esc_attr($name_prefix); ?>[featured][image]"
                                        value="<?php echo esc_attr((string) $featured['image']); ?>"
                                        data-field="featured_image"
                                        data-li-media-id
                                    >
                                    <span data-li-media-label>
                                        <?php 
                                        if ($featured['image'] && is_numeric($featured['image'])) {
                                            $image_url = wp_get_attachment_url((int) $featured['image']);
                                            echo $image_url ? esc_html(basename($image_url)) : esc_html__('No image selected', 'lloyds-industrial');
                                        } else {
                                            esc_html_e('No image selected', 'lloyds-industrial');
                                        }
                                        ?>
                                    </span>
                                    <br>
                                    <button
                                        type="button"
                                        class="button"
                                        data-li-media-select
                                        data-li-media-title="<?php esc_attr_e('Select Featured Image', 'lloyds-industrial'); ?>"
                                        data-li-media-button="<?php esc_attr_e('Use This Image', 'lloyds-industrial'); ?>"
                                        data-li-media-type="image"
                                    >
                                        <?php esc_html_e('Select Image', 'lloyds-industrial'); ?>
                                    </button>
                                    <button type="button" class="button" data-li-media-remove <?php echo $featured['image'] ? '' : 'hidden'; ?>>
                                        <?php esc_html_e('Remove', 'lloyds-industrial'); ?>
                                    </button>
                                </span>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Child Items (for top-level items) -->
                <?php if (!$is_child && !empty($item['children'])): ?>
                    <div class="li-mega-menu-field-group">
                        <h5><?php esc_html_e('Child Items', 'lloyds-industrial'); ?></h5>
                        <div class="li-mega-menu-children" data-parent-index="<?php echo esc_attr((string) $index); ?>">
                            <?php 
                            $child_index = 0;
                            foreach ($item['children'] as $child) {
                                li_render_mega_menu_item_form($child, $child_index, $page_options, $category_options, false, true);
                                $child_index++;
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

function li_render_settings_page(): void
{
    $layout_settings = li_get_global_layout_settings();
    $document_mode = li_get_document_access_mode();
    $site_width = absint($layout_settings['site_width'] ?? 1400) ?: 1400;
    $header_style = !empty($layout_settings['compact_header'])
        ? __('Compact', 'lloyds-industrial')
        : __('Comfortable', 'lloyds-industrial');
    $document_label = $document_mode === 'private'
        ? __('Private', 'lloyds-industrial')
        : __('Controlled', 'lloyds-industrial');
    $reset_layout_url = wp_nonce_url(
        admin_url('admin.php?page=lloyds&li_reset_layout_settings=1'),
        'li_reset_layout_settings'
    );
    $refresh_catalogue_url = wp_nonce_url(
        admin_url('admin.php?page=lloyds&li_theme_maintenance_action=refresh_catalogue'),
        'li_theme_maintenance_refresh_catalogue'
    );
    $rebuild_navigation_url = wp_nonce_url(
        admin_url('admin.php?page=lloyds&li_theme_maintenance_action=rebuild_navigation'),
        'li_theme_maintenance_rebuild_navigation'
    );
    $ensure_terms_url = wp_nonce_url(
        admin_url('admin.php?page=lloyds&li_theme_maintenance_action=ensure_terms'),
        'li_theme_maintenance_ensure_terms'
    );
    ?>
    <div class="wrap li-settings-page">
        <div class="li-settings-hero">
            <div>
                <p class="li-settings-kicker"><?php esc_html_e('Theme Control Center', 'lloyds-industrial'); ?></p>
                <h1><?php esc_html_e('Lloyds Industrial Settings', 'lloyds-industrial'); ?></h1>
                <p><?php esc_html_e('Manage brand defaults, customer access, header behavior, footer content, and starter-site maintenance from one focused screen.', 'lloyds-industrial'); ?></p>
            </div>
            <div class="li-settings-summary" aria-label="<?php esc_attr_e('Current theme settings summary', 'lloyds-industrial'); ?>">
                <div>
                    <span><?php esc_html_e('Documents', 'lloyds-industrial'); ?></span>
                    <strong><?php echo esc_html($document_label); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Header', 'lloyds-industrial'); ?></span>
                    <strong><?php echo esc_html($header_style); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Width', 'lloyds-industrial'); ?></span>
                    <strong><?php echo esc_html((string) $site_width); ?>px</strong>
                </div>
            </div>
        </div>

        <form class="li-settings-form" method="post" action="options.php">
            <input type="hidden" name="li_theme_settings[_settings_context]" value="global">
            <?php
            settings_fields('li_theme_settings');
            do_settings_sections('lloyds');
            submit_button(__('Save Theme Settings', 'lloyds-industrial'));
            ?>
        </form>

        <section class="li-settings-maintenance" aria-labelledby="li-settings-maintenance-title">
            <div class="li-settings-section-heading">
                <p class="li-settings-kicker"><?php esc_html_e('Operations', 'lloyds-industrial'); ?></p>
                <h2 id="li-settings-maintenance-title"><?php esc_html_e('Theme Maintenance', 'lloyds-industrial'); ?></h2>
                <p><?php esc_html_e('Run focused maintenance actions without doing a full reseed.', 'lloyds-industrial'); ?></p>
            </div>

            <div class="li-maintenance-grid">
                <div class="li-maintenance-card">
                    <span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
                    <h3><?php esc_html_e('Reset layout settings', 'lloyds-industrial'); ?></h3>
                    <p><?php esc_html_e('Restore site behavior, header, footer, and layout settings to defaults while preserving mega menu data.', 'lloyds-industrial'); ?></p>
                    <a class="button" href="<?php echo esc_url($reset_layout_url); ?>" onclick="return confirm('<?php echo esc_js(__('Reset theme layout settings to defaults?', 'lloyds-industrial')); ?>');">
                        <?php esc_html_e('Reset Layout', 'lloyds-industrial'); ?>
                    </a>
                </div>

                <div class="li-maintenance-card">
                    <span class="dashicons dashicons-book" aria-hidden="true"></span>
                    <h3><?php esc_html_e('Refresh catalogue page', 'lloyds-industrial'); ?></h3>
                    <p><?php esc_html_e('Replace the Catalogue page body with the current starter catalogue content and library shortcode.', 'lloyds-industrial'); ?></p>
                    <a class="button" href="<?php echo esc_url($refresh_catalogue_url); ?>" onclick="return confirm('<?php echo esc_js(__('Refresh the Catalogue page content?', 'lloyds-industrial')); ?>');">
                        <?php esc_html_e('Refresh Catalogue', 'lloyds-industrial'); ?>
                    </a>
                </div>

                <div class="li-maintenance-card">
                    <span class="dashicons dashicons-menu-alt3" aria-hidden="true"></span>
                    <h3><?php esc_html_e('Rebuild navigation', 'lloyds-industrial'); ?></h3>
                    <p><?php esc_html_e('Recreate the block navigation from the starter page structure.', 'lloyds-industrial'); ?></p>
                    <a class="button" href="<?php echo esc_url($rebuild_navigation_url); ?>">
                        <?php esc_html_e('Rebuild Navigation', 'lloyds-industrial'); ?>
                    </a>
                </div>

                <div class="li-maintenance-card">
                    <span class="dashicons dashicons-category" aria-hidden="true"></span>
                    <h3><?php esc_html_e('Ensure product terms', 'lloyds-industrial'); ?></h3>
                    <p><?php esc_html_e('Create any missing product categories and industry terms used by starter content.', 'lloyds-industrial'); ?></p>
                    <a class="button" href="<?php echo esc_url($ensure_terms_url); ?>">
                        <?php esc_html_e('Ensure Terms', 'lloyds-industrial'); ?>
                    </a>
                </div>

                <div class="li-maintenance-card li-maintenance-card--secure">
                    <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                    <h3><?php esc_html_e('Protected SDS storage', 'lloyds-industrial'); ?></h3>
                    <p><?php esc_html_e('Move and organize existing SDS attachment files inside protected storage so they are served only after purchase-history access checks.', 'lloyds-industrial'); ?></p>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=lloyds&li_migrate_sds_documents=1'), 'li_migrate_sds_documents')); ?>">
                        <?php esc_html_e('Protect & Organize SDS Files', 'lloyds-industrial'); ?>
                    </a>
                </div>

                <div class="li-maintenance-card li-maintenance-card--caution">
                    <span class="dashicons dashicons-update" aria-hidden="true"></span>
                    <h3><?php esc_html_e('Full starter reseed', 'lloyds-industrial'); ?></h3>
                    <p><?php esc_html_e('Recreate starter pages, navigation, WooCommerce page assignments, product terms, and catalogue page content.', 'lloyds-industrial'); ?></p>
                    <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('index.php?li_reseed_site=1'), 'li_reseed_site')); ?>" onclick="return confirm('<?php echo esc_js(__('Run a full starter content reseed?', 'lloyds-industrial')); ?>');">
                        <?php esc_html_e('Reseed Site Content', 'lloyds-industrial'); ?>
                    </a>
                </div>
            </div>
        </section>
    </div>
    <?php
}

function li_render_mega_menu_page(): void
{
    $settings = li_get_theme_settings();
    $menu_items = [];
    $top_level_count = 0;
    $child_count = 0;
    $featured_count = 0;

    if (!empty($settings['mega_menu_data'])) {
        $decoded = json_decode((string) $settings['mega_menu_data'], true);

        if (is_array($decoded)) {
            $menu_items = $decoded;
        }
    }

    if (!$menu_items) {
        $menu_items = li_get_default_mega_menu();
    }

    foreach ($menu_items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $top_level_count++;
        $children = $item['children'] ?? [];

        if (is_array($children)) {
            $child_count += count($children);
        }

        if (!empty($item['featured']['enabled'])) {
            $featured_count++;
        }
    }

    $enabled = !isset($settings['mega_menu_enabled']) || !empty($settings['mega_menu_enabled']);
    ?>
    <div class="wrap li-mega-menu-page">
        <div class="li-mega-menu-page-hero">
            <div>
                <p class="li-mega-menu-kicker"><?php esc_html_e('Navigation Builder', 'lloyds-industrial'); ?></p>
                <h1><?php esc_html_e('Lloyds Mega Menu', 'lloyds-industrial'); ?></h1>
                <p><?php esc_html_e('Build the customer-facing navigation with product paths, document links, badges, icons, and featured panels.', 'lloyds-industrial'); ?></p>
            </div>
            <div class="li-mega-menu-summary" aria-label="<?php esc_attr_e('Mega menu summary', 'lloyds-industrial'); ?>">
                <div>
                    <span><?php esc_html_e('Status', 'lloyds-industrial'); ?></span>
                    <strong><?php echo esc_html($enabled ? __('Enabled', 'lloyds-industrial') : __('Disabled', 'lloyds-industrial')); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Top Level', 'lloyds-industrial'); ?></span>
                    <strong><?php echo esc_html((string) $top_level_count); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Children', 'lloyds-industrial'); ?></span>
                    <strong><?php echo esc_html((string) $child_count); ?></strong>
                </div>
                <div>
                    <span><?php esc_html_e('Featured', 'lloyds-industrial'); ?></span>
                    <strong><?php echo esc_html((string) $featured_count); ?></strong>
                </div>
            </div>
        </div>

        <form class="li-mega-menu-page-form" method="post" action="options.php">
            <input type="hidden" name="li_theme_settings[_settings_context]" value="mega_menu">
            <?php
            settings_fields('li_theme_settings');
            do_settings_sections('lloyds-mega-menu');
            submit_button(__('Save Mega Menu', 'lloyds-industrial'));
            ?>
        </form>
    </div>
    <?php
}
