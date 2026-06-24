<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function (): void {
    register_block_style('core/button', [
        'name'  => 'industrial-outline',
        'label' => __('Industrial Outline', 'lloyds-industrial'),
    ]);

    register_block_style('core/group', [
        'name'  => 'industrial-card',
        'label' => __('Industrial Card', 'lloyds-industrial'),
    ]);

    register_block_style('core/image', [
        'name'  => 'rounded-shadow',
        'label' => __('Rounded Shadow', 'lloyds-industrial'),
    ]);
});