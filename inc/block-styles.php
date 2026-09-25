<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function (): void {
    register_block_style('core/button', [
        'name'  => 'industrial-outline',
        'label' => __('Industrial Outline', 'b2b-industrial'),
    ]);

    register_block_style('core/group', [
        'name'  => 'industrial-card',
        'label' => __('Industrial Card', 'b2b-industrial'),
    ]);

    register_block_style('core/image', [
        'name'  => 'rounded-shadow',
        'label' => __('Rounded Shadow', 'b2b-industrial'),
    ]);
});