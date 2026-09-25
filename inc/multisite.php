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
        'title'    => __('Brand Settings', 'b2b-industrial'),
        'priority' => 30,
    ]);

    $defaults = li_get_brand_defaults();

    foreach (li_get_brand_color_labels() as $key => $label) {
        $wp_customize->add_setting("li_brand_settings[$key]", [
            'default'           => $defaults[$key] ?? '',
            'sanitize_callback' => 'sanitize_hex_color',
            'type'              => 'option',
            'transport'         => 'refresh',
        ]);

        $wp_customize->add_control(new WP_Customize_Color_Control(
            $wp_customize,
            "li_brand_$key",
            [
                'label'    => __($label, 'b2b-industrial'),
                'section'  => 'li_brand_settings',
                'settings' => "li_brand_settings[$key]",
            ]
        ));
    }
});
