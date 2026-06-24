<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function li_get_brand_setting(string $key, mixed $default = null): mixed
{
    $settings = get_option('li_brand_settings', []);

    if (!is_array($settings)) {
        return $default;
    }

    return $settings[$key] ?? $default;
}

add_action('customize_register', function (WP_Customize_Manager $wp_customize): void {
    $wp_customize->add_section('li_brand_settings', [
        'title'    => __('Brand Settings', 'lloyds-industrial'),
        'priority' => 30,
    ]);

    $colors = [
        'primary_color'   => ['Primary Color', '#173449'],
        'secondary_color' => ['Secondary Color', '#234A64'],
        'accent_color'    => ['Accent Color', '#D8A03F'],
        'surface_color'   => ['Surface Color', '#F7F9FB'],
        'text_color'      => ['Text Color', '#1C252D'],
        'header_bg'       => ['Header Background', '#FFFFFF'],
        'footer_bg'       => ['Footer Background', '#173449'],
        'hero_overlay'    => ['Hero Overlay Color', '#08141E'],
    ];

    foreach ($colors as $key => [$label, $default]) {
        $wp_customize->add_setting("li_brand_settings[$key]", [
            'default'           => $default,
            'sanitize_callback' => 'sanitize_hex_color',
            'type'              => 'option',
            'transport'         => 'refresh',
        ]);

        $wp_customize->add_control(new WP_Customize_Color_Control(
            $wp_customize,
            "li_brand_$key",
            [
                'label'    => __($label, 'lloyds-industrial'),
                'section'  => 'li_brand_settings',
                'settings' => "li_brand_settings[$key]",
            ]
        ));
    }
});