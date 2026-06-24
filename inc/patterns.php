<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function (): void {
    register_block_pattern_category(
        'lloyds-industrial',
        [
            'label' => __('Lloyds Industrial', 'lloyds-industrial'),
        ]
    );
});