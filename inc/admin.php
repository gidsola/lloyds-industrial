<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function (): void {
    add_theme_page(
        __('Lloyds Industrial Settings', 'lloyds-industrial'),
        __('Industrial Settings', 'lloyds-industrial'),
        'manage_options',
        'lloyds-industrial-settings',
        'li_render_settings_page'
    );
});

add_action('admin_init', function (): void {
    register_setting('li_theme_settings', 'li_theme_settings', [
        'type'              => 'array',
        'sanitize_callback' => 'li_sanitize_theme_settings',
        'default'           => [],
    ]);

    add_settings_section(
        'li_brand_section',
        __('Brand Configuration', 'lloyds-industrial'),
        '__return_null',
        'lloyds-industrial-settings'
    );

    add_settings_field(
        'li_partner_mode',
        __('Partner Site Mode', 'lloyds-industrial'),
        'li_render_partner_mode_field',
        'lloyds-industrial-settings',
        'li_brand_section'
    );

    add_settings_section(
        'li_global_layout_section',
        __('Global Header and Footer', 'lloyds-industrial'),
        'li_render_global_layout_section',
        'lloyds-industrial-settings'
    );

    add_settings_field(
        'li_announcement',
        __('Announcement Bar', 'lloyds-industrial'),
        'li_render_announcement_field',
        'lloyds-industrial-settings',
        'li_global_layout_section'
    );

    add_settings_field(
        'li_header_actions',
        __('Header Actions', 'lloyds-industrial'),
        'li_render_header_actions_field',
        'lloyds-industrial-settings',
        'li_global_layout_section'
    );

    add_settings_field(
        'li_footer_content',
        __('Footer Content', 'lloyds-industrial'),
        'li_render_footer_content_field',
        'lloyds-industrial-settings',
        'li_global_layout_section'
    );

    // Mega Menu Section
    add_settings_section(
        'li_mega_menu_section',
        __('Mega Menu', 'lloyds-industrial'),
        'li_render_mega_menu_section',
        'lloyds-industrial-settings'
    );

    add_settings_field(
        'li_mega_menu_enabled',
        __('Enable Mega Menu', 'lloyds-industrial'),
        'li_render_mega_menu_enabled_field',
        'lloyds-industrial-settings',
        'li_mega_menu_section'
    );

    add_settings_field(
        'li_mega_menu_data',
        __('Menu Data (JSON)', 'lloyds-industrial'),
        'li_render_mega_menu_data_field',
        'lloyds-industrial-settings',
        'li_mega_menu_section'
    );
});

add_action('admin_init', function (): void {
    if (!current_user_can('manage_options') || !isset($_GET['li_migrate_sds_documents'])) {
        return;
    }

    check_admin_referer('li_migrate_sds_documents');

    $result = li_migrate_sds_documents_to_protected_storage();

    set_transient('li_sds_migration_result', $result, MINUTE_IN_SECONDS);

    wp_safe_redirect(admin_url('themes.php?page=lloyds-industrial-settings'));
    exit;
});

add_action('admin_notices', function (): void {
    $result = get_transient('li_sds_migration_result');

    if (!is_array($result)) {
        return;
    }

    delete_transient('li_sds_migration_result');

    printf(
        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
        esc_html(sprintf(
            __('SDS migration complete. Copied: %1$d. Skipped: %2$d. Failed: %3$d.', 'lloyds-industrial'),
            (int) ($result['migrated'] ?? 0),
            (int) ($result['skipped'] ?? 0),
            (int) ($result['failed'] ?? 0)
        ))
    );
});

function li_sanitize_theme_settings(array $settings): array
{
    $existing = li_get_global_layout_settings();

    $sanitized = [
        'partner_mode'              => !empty($settings['partner_mode']),
        'quote_mode'                => !empty($settings['quote_mode']),
        'documents_mode'            => 'controlled',
        'announcement_enabled'      => !empty($settings['announcement_enabled']),
        'announcement_text'         => sanitize_text_field($settings['announcement_text'] ?? $existing['announcement_text']),
        'announcement_link_label'   => sanitize_text_field($settings['announcement_link_label'] ?? $existing['announcement_link_label']),
        'announcement_link_url'     => esc_url_raw($settings['announcement_link_url'] ?? $existing['announcement_link_url']),
        'header_primary_label'      => sanitize_text_field($settings['header_primary_label'] ?? $existing['header_primary_label']),
        'header_primary_url'        => esc_url_raw($settings['header_primary_url'] ?? $existing['header_primary_url']),
        'header_secondary_label'    => sanitize_text_field($settings['header_secondary_label'] ?? $existing['header_secondary_label']),
        'header_secondary_url'      => esc_url_raw($settings['header_secondary_url'] ?? $existing['header_secondary_url']),
        'show_account_link'         => !empty($settings['show_account_link']),
        'show_cart_link'            => !empty($settings['show_cart_link']),
        'footer_tagline'            => sanitize_text_field($settings['footer_tagline'] ?? $existing['footer_tagline']),
        'footer_legal_text'         => wp_kses_post($settings['footer_legal_text'] ?? $existing['footer_legal_text']),
        'footer_note_enabled'       => !empty($settings['footer_note_enabled']),
        'footer_note_text'          => sanitize_text_field($settings['footer_note_text'] ?? $existing['footer_note_text']),
        'mega_menu_enabled'        => !isset($settings['mega_menu_enabled']) || !empty($settings['mega_menu_enabled']),
        'mega_menu_data'            => li_sanitize_mega_menu_data($settings['mega_menu_data'] ?? $existing['mega_menu_data'] ?? ''),
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

function li_render_global_layout_section(): void
{
    ?>
    <p><?php esc_html_e('Control common header and footer copy without editing the global template parts.', 'lloyds-industrial'); ?></p>
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
    
    // If empty, use default data for display
    if ($mega_menu_data === '') {
        $default_data = li_get_default_mega_menu();
        $mega_menu_data = json_encode($default_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    // Format the JSON nicely for display
    $decoded = json_decode($mega_menu_data, true);
    if (is_array($decoded)) {
        $mega_menu_data = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    $editor_id = 'li_mega_menu_data';
    
    // Enqueue code editor if available
    if (function_exists('wp_enqueue_code_editor')) {
        $settings = wp_enqueue_code_editor(['type' => 'application/json']);
        wp_add_inline_script(
            'code-editor',
            sprintf(
                'jQuery(function($) { try { wp.codeEditor.initialize(%s, %s); } catch (e) { console.log(e); } });',
                wp_json_encode($editor_id),
                wp_json_encode($settings)
            )
        );
    }
    ?>
    <div class="li-mega-menu-data-container">
        <textarea
            class="large-text code"
            id="<?php echo esc_attr($editor_id); ?>"
            name="li_theme_settings[mega_menu_data]"
            rows="20"
            cols="80"
            data-lpignore="true"
            autocomplete="off"
            spellcheck="false"
        ><?php echo esc_textarea($mega_menu_data); ?></textarea>
        <p class="description">
            <?php esc_html_e('Enter mega menu configuration as JSON. Use the default structure as a template. Each top-level array item is a menu section with children.', 'lloyds-industrial'); ?>
        </p>
        <p class="description">
            <button type="button" class="button button-secondary li-mega-menu-reset" id="li_reset_mega_menu">
                <?php esc_html_e('Reset to Defaults', 'lloyds-industrial'); ?>
            </button>
        </p>
        <script type="text/javascript">
        (function($) {
            $('#li_reset_mega_menu').on('click', function(e) {
                e.preventDefault();
                if (confirm('<?php echo esc_js(__("Are you sure you want to reset the mega menu to defaults? This cannot be undone.", "lloyds-industrial")); ?>')) {
                    var defaultData = <?php echo json_encode(li_get_default_mega_menu(), JSON_UNESCAPED_UNICODE); ?>;
                    $('#li_mega_menu_data').val(JSON.stringify(defaultData, null, 2));
                }
            });
        })(jQuery);
        </script>
    </div>
    <?php
}

function li_render_settings_page(): void
{
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Lloyds Industrial Settings', 'lloyds-industrial'); ?></h1>

        <form method="post" action="options.php">
            <?php
            settings_fields('li_theme_settings');
            do_settings_sections('lloyds-industrial-settings');
            submit_button();
            ?>
        </form>

        <hr>

        <h2><?php esc_html_e('Protected SDS Storage', 'lloyds-industrial'); ?></h2>
        <p>
            <?php esc_html_e('Copy existing SDS attachment files into protected storage. Original media files are left untouched.', 'lloyds-industrial'); ?>
        </p>
        <p>
            <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('themes.php?page=lloyds-industrial-settings&li_migrate_sds_documents=1'), 'li_migrate_sds_documents')); ?>">
                <?php esc_html_e('Migrate SDS Files', 'lloyds-industrial'); ?>
            </a>
        </p>
    </div>
    <?php
}
