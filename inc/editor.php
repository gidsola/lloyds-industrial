<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_filter('block_editor_settings_all', function (array $settings): array {
    $settings['supportsLayout'] = true;

    return $settings;
});