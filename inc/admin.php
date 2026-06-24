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

    add_settings_field(
        'li_documents_mode',
        __('Documentation Access Mode', 'lloyds-industrial'),
        'li_render_documents_mode_field',
        'lloyds-industrial-settings',
        'li_brand_section'
    );

    add_settings_field(
        'li_quote_mode',
        __('Quote Mode', 'lloyds-industrial'),
        'li_render_quote_mode_field',
        'lloyds-industrial-settings',
        'li_brand_section'
    );
});

function li_sanitize_theme_settings(array $settings): array
{
    return [
        'partner_mode' => !empty($settings['partner_mode']),

        'quote_mode' => !empty($settings['quote_mode']),

        'documents_mode' => in_array(
            $settings['documents_mode'] ?? 'controlled',
            ['controlled', 'public', 'private'],
            true
        ) ? $settings['documents_mode'] : 'controlled',
    ];
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

function li_render_documents_mode_field(): void
{
    $settings = get_option('li_theme_settings', []);
    $mode = $settings['documents_mode'] ?? 'controlled';
    ?>
    <select name="li_theme_settings[documents_mode]">
        <option value="controlled" <?php selected($mode, 'controlled'); ?>>
            <?php esc_html_e('Controlled customer access', 'lloyds-industrial'); ?>
        </option>
        <option value="public" <?php selected($mode, 'public'); ?>>
            <?php esc_html_e('Public resources only', 'lloyds-industrial'); ?>
        </option>
        <option value="private" <?php selected($mode, 'private'); ?>>
            <?php esc_html_e('Private/internal only', 'lloyds-industrial'); ?>
        </option>
    </select>
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
    </div>
    <?php
}