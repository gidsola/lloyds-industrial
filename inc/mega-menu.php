<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mega Menu Data Model
 * 
 * Each menu item can have:
 * - label: string - Display text
 * - url: string - Link URL
 * - icon: string - Optional emoji or icon class
 * - image: string - Optional image URL (attachment ID or URL)
 * - description: string - Optional subtext
 * - bg_color: string - Background color (hex or named)
 * - text_color: string - Text color
 * - hover_color: string - Hover text color
 * - badge: string - Optional badge text (e.g., "New", "SDS")
 * - badge_color: string - Badge background color
 * - badge_text_color: string - Badge text color
 * - column: int - Column grouping (0 = top level)
 * - featured: mixed - Featured promo panel content (image, title, text, url)
 * - enabled: bool - Whether item is active
 * - new_tab: bool - Open in new tab
 * - mobile_order: int - Mobile display order
 * - mobile_visible: bool - Show on mobile
 * - children: array - Child menu items
 */

/**
 * Get default mega menu structure
 */
function li_get_default_mega_menu(): array
{
    return [
        [
            'label' => __('Products', 'lloyds-industrial'),
            'url' => '/products',
            'icon' => '',
            'image' => '',
            'description' => __('Browse our complete product catalog', 'lloyds-industrial'),
            'bg_color' => '',
            'text_color' => '',
            'hover_color' => '',
            'badge' => '',
            'badge_color' => '#0066cc',
            'badge_text_color' => '#ffffff',
            'column' => 0,
            'featured' => [
                'enabled' => false,
                'image' => '',
                'title' => '',
                'text' => '',
                'url' => '',
                'button_label' => '',
            ],
            'enabled' => true,
            'new_tab' => false,
            'mobile_order' => 1,
            'mobile_visible' => true,
            'children' => [
                [
                    'label' => __('Lubricants', 'lloyds-industrial'),
                    'url' => '/product-category/lubricants',
                    'icon' => '🛢️',
                    'image' => '',
                    'description' => __('High-performance lubricants for industrial applications', 'lloyds-industrial'),
                    'bg_color' => '',
                    'text_color' => '',
                    'hover_color' => '',
                    'badge' => __('New', 'lloyds-industrial'),
                    'badge_color' => '#28a745',
                    'badge_text_color' => '#ffffff',
                    'column' => 1,
                    'featured' => [],
                    'enabled' => true,
                    'new_tab' => false,
                    'mobile_order' => 1,
                    'mobile_visible' => true,
                    'children' => [],
                ],
                [
                    'label' => __('Degreasers', 'lloyds-industrial'),
                    'url' => '/product-category/degreasers',
                    'icon' => '🧼',
                    'image' => '',
                    'description' => __('Industrial strength degreasing solutions', 'lloyds-industrial'),
                    'bg_color' => '',
                    'text_color' => '',
                    'hover_color' => '',
                    'badge' => '',
                    'badge_color' => '#0066cc',
                    'badge_text_color' => '#ffffff',
                    'column' => 1,
                    'featured' => [],
                    'enabled' => true,
                    'new_tab' => false,
                    'mobile_order' => 2,
                    'mobile_visible' => true,
                    'children' => [],
                ],
                [
                    'label' => __('Corrosion Protection', 'lloyds-industrial'),
                    'url' => '/product-category/corrosion-protection',
                    'icon' => '🛡️',
                    'image' => '',
                    'description' => __('Protect your equipment from corrosion and rust', 'lloyds-industrial'),
                    'bg_color' => '',
                    'text_color' => '',
                    'hover_color' => '',
                    'badge' => '',
                    'badge_color' => '#0066cc',
                    'badge_text_color' => '#ffffff',
                    'column' => 1,
                    'featured' => [],
                    'enabled' => true,
                    'new_tab' => false,
                    'mobile_order' => 3,
                    'mobile_visible' => true,
                    'children' => [],
                ],
                [
                    'label' => __('Featured Product', 'lloyds-industrial'),
                    'url' => '',
                    'icon' => '',
                    'image' => '',
                    'description' => '',
                    'bg_color' => '',
                    'text_color' => '',
                    'hover_color' => '',
                    'badge' => '',
                    'badge_color' => '#0066cc',
                    'badge_text_color' => '#ffffff',
                    'column' => 2,
                    'featured' => [
                        'enabled' => true,
                        'image' => '',
                        'title' => __('Premium Industrial Lubricant', 'lloyds-industrial'),
                        'text' => __('Our best-selling lubricant for heavy-duty applications. Long-lasting protection.', 'lloyds-industrial'),
                        'url' => '/products/premium-lubricant',
                        'button_label' => __('View Product', 'lloyds-industrial'),
                    ],
                    'enabled' => true,
                    'new_tab' => false,
                    'mobile_order' => 4,
                    'mobile_visible' => true,
                    'children' => [],
                ],
            ],
        ],
        [
            'label' => __('Industries', 'lloyds-industrial'),
            'url' => '/industries',
            'icon' => '',
            'image' => '',
            'description' => __('Industries we serve', 'lloyds-industrial'),
            'bg_color' => '',
            'text_color' => '',
            'hover_color' => '',
            'badge' => '',
            'badge_color' => '#0066cc',
            'badge_text_color' => '#ffffff',
            'column' => 0,
            'featured' => [
                'enabled' => false,
                'image' => '',
                'title' => '',
                'text' => '',
                'url' => '',
                'button_label' => '',
            ],
            'enabled' => true,
            'new_tab' => false,
            'mobile_order' => 2,
            'mobile_visible' => true,
            'children' => [
                [
                    'label' => __('Utilities & Energy', 'lloyds-industrial'),
                    'url' => '/industries/utilities-energy',
                    'icon' => '⚡',
                    'image' => '',
                    'description' => '',
                    'bg_color' => '',
                    'text_color' => '',
                    'hover_color' => '',
                    'badge' => '',
                    'badge_color' => '#0066cc',
                    'badge_text_color' => '#ffffff',
                    'column' => 1,
                    'featured' => [],
                    'enabled' => true,
                    'new_tab' => false,
                    'mobile_order' => 1,
                    'mobile_visible' => true,
                    'children' => [],
                ],
                [
                    'label' => __('Manufacturing', 'lloyds-industrial'),
                    'url' => '/industries/manufacturing',
                    'icon' => '🏭',
                    'image' => '',
                    'description' => '',
                    'bg_color' => '',
                    'text_color' => '',
                    'hover_color' => '',
                    'badge' => '',
                    'badge_color' => '#0066cc',
                    'badge_text_color' => '#ffffff',
                    'column' => 1,
                    'featured' => [],
                    'enabled' => true,
                    'new_tab' => false,
                    'mobile_order' => 2,
                    'mobile_visible' => true,
                    'children' => [],
                ],
                [
                    'label' => __('Transportation', 'lloyds-industrial'),
                    'url' => '/industries/transportation',
                    'icon' => '🚛',
                    'image' => '',
                    'description' => '',
                    'bg_color' => '',
                    'text_color' => '',
                    'hover_color' => '',
                    'badge' => '',
                    'badge_color' => '#0066cc',
                    'badge_text_color' => '#ffffff',
                    'column' => 2,
                    'featured' => [],
                    'enabled' => true,
                    'new_tab' => false,
                    'mobile_order' => 3,
                    'mobile_visible' => true,
                    'children' => [],
                ],
            ],
        ],
        [
            'label' => __('Documentation', 'lloyds-industrial'),
            'url' => '/documentation',
            'icon' => '',
            'image' => '',
            'description' => __('Technical documents and safety information', 'lloyds-industrial'),
            'bg_color' => '',
            'text_color' => '',
            'hover_color' => '',
            'badge' => '',
            'badge_color' => '#0066cc',
            'badge_text_color' => '#ffffff',
            'column' => 0,
            'featured' => [
                'enabled' => false,
                'image' => '',
                'title' => '',
                'text' => '',
                'url' => '',
                'button_label' => '',
            ],
            'enabled' => true,
            'new_tab' => false,
            'mobile_order' => 3,
            'mobile_visible' => true,
            'children' => [
                [
                    'label' => __('Technical Documents', 'lloyds-industrial'),
                    'url' => '/documentation/technical',
                    'icon' => '📄',
                    'image' => '',
                    'description' => '',
                    'bg_color' => '',
                    'text_color' => '',
                    'hover_color' => '',
                    'badge' => '',
                    'badge_color' => '#0066cc',
                    'badge_text_color' => '#ffffff',
                    'column' => 1,
                    'featured' => [],
                    'enabled' => true,
                    'new_tab' => false,
                    'mobile_order' => 1,
                    'mobile_visible' => true,
                    'children' => [],
                ],
                [
                    'label' => __('SDS Access', 'lloyds-industrial'),
                    'url' => '/documentation/sds',
                    'icon' => '🔬',
                    'image' => '',
                    'description' => '',
                    'bg_color' => '',
                    'text_color' => '',
                    'hover_color' => '',
                    'badge' => __('SDS', 'lloyds-industrial'),
                    'badge_color' => '#dc3545',
                    'badge_text_color' => '#ffffff',
                    'column' => 1,
                    'featured' => [],
                    'enabled' => true,
                    'new_tab' => false,
                    'mobile_order' => 2,
                    'mobile_visible' => true,
                    'children' => [],
                ],
                [
                    'label' => __('Account Login', 'lloyds-industrial'),
                    'url' => '/account',
                    'icon' => '🔑',
                    'image' => '',
                    'description' => '',
                    'bg_color' => '',
                    'text_color' => '',
                    'hover_color' => '',
                    'badge' => '',
                    'badge_color' => '#0066cc',
                    'badge_text_color' => '#ffffff',
                    'column' => 2,
                    'featured' => [],
                    'enabled' => true,
                    'new_tab' => false,
                    'mobile_order' => 3,
                    'mobile_visible' => true,
                    'children' => [],
                ],
                [
                    'label' => __('Support CTA', 'lloyds-industrial'),
                    'url' => '/contact',
                    'icon' => '',
                    'image' => '',
                    'description' => '',
                    'bg_color' => '#0066cc',
                    'text_color' => '#ffffff',
                    'hover_color' => '#0055aa',
                    'badge' => '',
                    'badge_color' => '#0066cc',
                    'badge_text_color' => '#ffffff',
                    'column' => 2,
                    'featured' => [],
                    'enabled' => true,
                    'new_tab' => false,
                    'mobile_order' => 4,
                    'mobile_visible' => true,
                    'children' => [],
                ],
            ],
        ],
        [
            'label' => __('Company', 'lloyds-industrial'),
            'url' => '/about',
            'icon' => '',
            'image' => '',
            'description' => '',
            'bg_color' => '',
            'text_color' => '',
            'hover_color' => '',
            'badge' => '',
            'badge_color' => '#0066cc',
            'badge_text_color' => '#ffffff',
            'column' => 0,
            'featured' => [
                'enabled' => false,
                'image' => '',
                'title' => '',
                'text' => '',
                'url' => '',
                'button_label' => '',
            ],
            'enabled' => true,
            'new_tab' => false,
            'mobile_order' => 4,
            'mobile_visible' => true,
            'children' => [
                [
                    'label' => __('About Us', 'lloyds-industrial'),
                    'url' => '/about',
                    'icon' => '',
                    'image' => '',
                    'description' => '',
                    'bg_color' => '',
                    'text_color' => '',
                    'hover_color' => '',
                    'badge' => '',
                    'badge_color' => '#0066cc',
                    'badge_text_color' => '#ffffff',
                    'column' => 1,
                    'featured' => [],
                    'enabled' => true,
                    'new_tab' => false,
                    'mobile_order' => 1,
                    'mobile_visible' => true,
                    'children' => [],
                ],
                [
                    'label' => __('Partner Brands', 'lloyds-industrial'),
                    'url' => '/partners',
                    'icon' => '',
                    'image' => '',
                    'description' => '',
                    'bg_color' => '',
                    'text_color' => '',
                    'hover_color' => '',
                    'badge' => '',
                    'badge_color' => '#0066cc',
                    'badge_text_color' => '#ffffff',
                    'column' => 1,
                    'featured' => [],
                    'enabled' => true,
                    'new_tab' => false,
                    'mobile_order' => 2,
                    'mobile_visible' => true,
                    'children' => [],
                ],
                [
                    'label' => __('Contact', 'lloyds-industrial'),
                    'url' => '/contact',
                    'icon' => '',
                    'image' => '',
                    'description' => '',
                    'bg_color' => '',
                    'text_color' => '',
                    'hover_color' => '',
                    'badge' => '',
                    'badge_color' => '#0066cc',
                    'badge_text_color' => '#ffffff',
                    'column' => 2,
                    'featured' => [],
                    'enabled' => true,
                    'new_tab' => false,
                    'mobile_order' => 3,
                    'mobile_visible' => true,
                    'children' => [],
                ],
            ],
        ],
    ];
}

/**
 * Get mega menu settings from options
 */
function li_get_mega_menu_settings(): array
{
    $settings = li_get_theme_settings();
    
    // Check if we have serialized mega menu data
    if (!empty($settings['mega_menu_data'])) {
        $decoded = json_decode($settings['mega_menu_data'], true);
        if (is_array($decoded)) {
            return li_sanitize_mega_menu_items($decoded);
        }
    }
    
    // Return defaults
    return li_get_default_mega_menu();
}

/**
 * Sanitize mega menu data
 */
function li_sanitize_mega_menu_data(string $data): string
{
    $decoded = json_decode($data, true);
    
    if (!is_array($decoded)) {
        return json_encode(li_get_default_mega_menu());
    }
    
    // Recursively sanitize menu items
    $sanitized = li_sanitize_mega_menu_items($decoded);
    
    return json_encode($sanitized);
}

/**
 * Recursively sanitize menu item array
 */
function li_sanitize_mega_menu_items(array $items): array
{
    $sanitized = [];
    
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        
        $sanitized_item = [
            'label' => sanitize_text_field($item['label'] ?? ''),
            'url' => esc_url_raw($item['url'] ?? ''),
            'icon' => sanitize_text_field($item['icon'] ?? ''),
            'image' => is_numeric($item['image'] ?? '') ? (int) $item['image'] : esc_url_raw($item['image'] ?? ''),
            'description' => sanitize_text_field($item['description'] ?? ''),
            'bg_color' => sanitize_text_field($item['bg_color'] ?? ''),
            'text_color' => sanitize_text_field($item['text_color'] ?? ''),
            'hover_color' => sanitize_text_field($item['hover_color'] ?? ''),
            'badge' => sanitize_text_field($item['badge'] ?? ''),
            'badge_color' => sanitize_text_field($item['badge_color'] ?? '#0066cc'),
            'badge_text_color' => sanitize_text_field($item['badge_text_color'] ?? '#ffffff'),
            'column' => isset($item['column']) ? (int) $item['column'] : 0,
            'featured' => li_sanitize_featured_panel($item['featured'] ?? []),
            'enabled' => !empty($item['enabled']),
            'new_tab' => !empty($item['new_tab']),
            'mobile_order' => isset($item['mobile_order']) ? (int) $item['mobile_order'] : 0,
            'mobile_visible' => !isset($item['mobile_visible']) || !empty($item['mobile_visible']),
            'children' => li_sanitize_mega_menu_items($item['children'] ?? []),
        ];
        
        // Only filter out null values, keep empty strings and arrays for required keys
        $sanitized[] = array_filter($sanitized_item, function($value) {
            return $value !== null;
        });
    }
    
    return $sanitized;
}

/**
 * Sanitize featured panel data
 */
function li_sanitize_featured_panel(array $featured): array
{
    return [
        'enabled' => !empty($featured['enabled']),
        'image' => is_numeric($featured['image'] ?? '') ? (int) $featured['image'] : esc_url_raw($featured['image'] ?? ''),
        'title' => sanitize_text_field($featured['title'] ?? ''),
        'text' => sanitize_textarea_field($featured['text'] ?? ''),
        'url' => esc_url_raw($featured['url'] ?? ''),
        'button_label' => sanitize_text_field($featured['button_label'] ?? ''),
    ];
}

/**
 * Check if mega menu is enabled
 */
function li_is_mega_menu_enabled(): bool
{
    $settings = li_get_theme_settings();
    return !isset($settings['mega_menu_enabled']) || !empty($settings['mega_menu_enabled']);
}

/**
 * Render a single menu item
 */
function li_render_mega_menu_item(array $item, bool $is_top_level = false): string
{
    // Skip disabled items
    if (empty($item['enabled'])) {
        return '';
    }
    
    $label = esc_html($item['label'] ?? '');
    $url = esc_url($item['url'] ?? '');
    $has_children = !empty($item['children'] ?? []);
    $is_dropdown = $has_children || !empty($item['featured']['enabled']);
    
    $link_attrs = [
        'href' => $url,
        'class' => 'li-mega-menu__link',
    ];
    
    if (!empty($item['new_tab'])) {
        $link_attrs['target'] = '_blank';
        $link_attrs['rel'] = 'noopener noreferrer';
    }
    
    if ($is_dropdown) {
        $link_attrs['class'] .= ' li-mega-menu__link--has-dropdown';
        $link_attrs['aria-haspopup'] = 'true';
        $link_attrs['aria-expanded'] = 'false';
    }
    
    // Style attributes
    $style = '';
    if (!empty($item['bg_color']) && !$is_top_level) {
        $style .= 'background-color:' . esc_attr($item['bg_color']) . ';';
    }
    if (!empty($item['text_color']) && !$is_top_level) {
        $style .= 'color:' . esc_attr($item['text_color']) . ';';
    }
    
    $attr_string = '';
    foreach ($link_attrs as $attr => $value) {
        $attr_string .= ' ' . esc_attr($attr) . '="' . esc_attr($value) . '"';
    }
    
    $icon_html = '';
    if (!empty($item['icon'])) {
        $icon_html = '<span class="li-mega-menu__icon">' . esc_html($item['icon']) . '</span>';
    }
    
    $badge_html = '';
    if (!empty($item['badge'])) {
        $badge_style = '';
        if (!empty($item['badge_color'])) {
            $badge_style .= 'background-color:' . esc_attr($item['badge_color']) . ';';
        }
        if (!empty($item['badge_text_color'])) {
            $badge_style .= 'color:' . esc_attr($item['badge_text_color']) . ';';
        }
        $badge_html = '<span class="li-mega-menu__badge" style="' . esc_attr($badge_style) . '">' . esc_html($item['badge']) . '</span>';
    }
    
    $description_html = '';
    if (!empty($item['description']) && !$is_top_level) {
        $description_html = '<span class="li-mega-menu__description">' . esc_html($item['description']) . '</span>';
    }
    
    // Image for menu item (if set)
    $image_html = '';
    if (!empty($item['image'])) {
        $image_url = '';
        if (is_numeric($item['image'])) {
            $image_url = wp_get_attachment_url((int) $item['image']);
        } elseif (filter_var($item['image'], FILTER_VALIDATE_URL)) {
            $image_url = $item['image'];
        }
        
        if ($image_url) {
            $image_html = '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($label) . '" class="li-mega-menu__image" loading="lazy">';
        }
    }
    
    // Build the link content
    $link_content = $icon_html . $image_html . $badge_html . '<span class="li-mega-menu__label">' . $label . '</span>' . $description_html;
    
    // For top-level items, we need a wrapper
    $item_class = 'li-mega-menu__item';
    if ($is_top_level) {
        $item_class .= ' li-mega-menu__item--top-level';
    }
    if ($has_children) {
        $item_class .= ' li-mega-menu__item--has-children';
    }
    if (!empty($item['featured']['enabled'])) {
        $item_class .= ' li-mega-menu__item--has-featured';
    }
    
    $item_style = '';
    if ($style) {
        $item_style = ' style="' . esc_attr($style) . '"';
    }
    
    $html = '<li class="' . esc_attr($item_class) . '"' . $item_style . '>';
    $html .= '<a' . $attr_string . '>' . $link_content . '</a>';
    
    // Render dropdown if needed
    if ($is_dropdown) {
        $html .= li_render_mega_menu_dropdown($item);
    }
    
    $html .= '</li>';
    
    return $html;
}

/**
 * Render dropdown panel for a menu item
 */
function li_render_mega_menu_dropdown(array $item): string
{
    $has_children = !empty($item['children']);
    $has_featured = !empty($item['featured']['enabled']);
    
    if (!$has_children && !$has_featured) {
        return '';
    }
    
    $dropdown_class = 'li-mega-menu__dropdown';
    
    // Count columns needed
    $columns = 1;
    if ($has_children && !empty($item['children'])) {
        $child_columns = array_reduce($item['children'], function($carry, $child) {
            return max($carry, (int) ($child['column'] ?? 1));
        }, 1);
        $columns = max($columns, $child_columns);
    }
    if ($has_featured) {
        $columns++;
    }
    
    $dropdown_class .= ' li-mega-menu__dropdown--cols-' . esc_attr((string) $columns);
    
    $html = '<div class="' . esc_attr($dropdown_class) . '">';
    
    // Group children by column
    $children_by_column = [];
    if (!empty($item['children']) && is_array($item['children'])) {
        foreach ($item['children'] as $child) {
        if (empty($child['enabled'])) {
            continue;
        }
        $col = (int) ($child['column'] ?? 1);
        $children_by_column[$col][] = $child;
        }
    }
    
    // Render featured panel first (if enabled)
    if ($has_featured) {
        $html .= li_render_featured_panel($item['featured']);
    }
    
    // Render children columns
    if (!empty($children_by_column)) {
        $max_column = max(array_keys($children_by_column));
        for ($i = 1; $i <= $max_column; $i++) {
            if (empty($children_by_column[$i])) {
                continue;
            }
            
            $html .= '<div class="li-mega-menu__column">';
            $html .= '<ul class="li-mega-menu__sublist">';
            
            foreach ($children_by_column[$i] as $child) {
                $html .= li_render_mega_menu_item($child, false);
            }
            
            $html .= '</ul>';
            $html .= '</div>';
        }
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render featured promo panel
 */
function li_render_featured_panel(array $featured): string
{
    if (empty($featured['enabled'])) {
        return '';
    }
    
    $image_html = '';
    if (!empty($featured['image'])) {
        $image_url = '';
        if (is_numeric($featured['image'])) {
            $image_url = wp_get_attachment_url((int) $featured['image']);
        } elseif (filter_var($featured['image'], FILTER_VALIDATE_URL)) {
            $image_url = $featured['image'];
        }
        
        if ($image_url) {
            $image_html = '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($featured['title'] ?? '') . '" class="li-featured-panel__image" loading="lazy">';
        }
    }
    
    $title_html = '';
    if (!empty($featured['title'])) {
        $title_html = '<h4 class="li-featured-panel__title">' . esc_html($featured['title']) . '</h4>';
    }
    
    $text_html = '';
    if (!empty($featured['text'])) {
        $text_html = '<p class="li-featured-panel__text">' . esc_html($featured['text']) . '</p>';
    }
    
    $button_html = '';
    if (!empty($featured['url']) && !empty($featured['button_label'])) {
        $button_html = '<a href="' . esc_url($featured['url']) . '" class="li-featured-panel__button li-button">' . esc_html($featured['button_label']) . '</a>';
    }
    
    $html = '<div class="li-mega-menu__featured li-featured-panel">';
    $html .= $image_html;
    $html .= '<div class="li-featured-panel__content">';
    $html .= $title_html;
    $html .= $text_html;
    $html .= $button_html;
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Render the complete mega menu
 */
function li_render_mega_menu(): string
{
    if (!li_is_mega_menu_enabled()) {
        return '';
    }
    
    $menu_data = li_get_mega_menu_settings();
    
    if (empty($menu_data)) {
        return '';
    }
    
    $html = '<nav class="li-mega-menu" aria-label="' . esc_attr__('Main navigation', 'lloyds-industrial') . '">';
    $html .= '<ul class="li-mega-menu__list">';
    
    foreach ($menu_data as $item) {
        $html .= li_render_mega_menu_item($item, true);
    }
    
    $html .= '</ul>';
    $html .= '</nav>';
    
    return $html;
}

/**
 * Register the mega menu shortcode
 */
add_shortcode('li_mega_menu', 'li_render_mega_menu');

/**
 * Enqueue mega menu styles
 */
add_action('wp_enqueue_scripts', function (): void {
    $theme_version = wp_get_theme()->get('Version');
    
    wp_enqueue_style(
        'li-mega-menu',
        get_template_directory_uri() . '/assets/css/mega-menu.css',
        [],
        $theme_version
    );
    
    wp_enqueue_script(
        'li-mega-menu',
        get_template_directory_uri() . '/assets/js/mega-menu.js',
        ['jquery'],
        $theme_version,
        true
    );
});
