<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function (): void {
    register_block_pattern_category(
        'b2b-industrial',
        [
            'label' => __('B2B Industrial', 'b2b-industrial'),
        ]
    );
});