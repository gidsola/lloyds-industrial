<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function (): void {
    if (!get_role('li_verified_customer')) {
        add_role(
            'li_verified_customer',
            __('Verified Customer', 'b2b-industrial'),
            [
                'read'              => true,
                'read_li_documents' => true,
            ]
        );
    }

    if (!get_role('li_distributor')) {
        add_role(
            'li_distributor',
            __('Distributor', 'b2b-industrial'),
            [
                'read'                => true,
                'read_li_documents'   => true,
                'manage_li_documents' => false,
            ]
        );
    }
});