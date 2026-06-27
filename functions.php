<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once get_template_directory() . '/inc/setup.php';
require_once get_template_directory() . '/inc/brand.php';
require_once get_template_directory() . '/inc/layout.php';
require_once get_template_directory() . '/inc/account.php';
require_once get_template_directory() . '/inc/theme-json.php';
require_once get_template_directory() . '/inc/assets.php';

require_once get_template_directory() . '/inc/patterns.php';
require_once get_template_directory() . '/inc/block-styles.php';
require_once get_template_directory() . '/inc/editor.php';

require_once get_template_directory() . '/inc/woocommerce.php';
require_once get_template_directory() . '/inc/products.php';
require_once get_template_directory() . '/inc/product-carousel.php';
require_once get_template_directory() . '/inc/product-fields.php';
require_once get_template_directory() . '/inc/product-documents.php';
require_once get_template_directory() . '/inc/media-organization.php';

require_once get_template_directory() . '/inc/documents.php';
require_once get_template_directory() . '/inc/document-fields.php';
require_once get_template_directory() . '/inc/document-access.php';
require_once get_template_directory() . '/inc/document-downloads.php';
require_once get_template_directory() . '/inc/resellers.php';
require_once get_template_directory() . '/inc/contact-forms.php';
require_once get_template_directory() . '/inc/analytics.php';
require_once get_template_directory() . '/inc/seo.php';
require_once get_template_directory() . '/inc/mailing-list.php';

require_once get_template_directory() . '/inc/customer-roles.php';
require_once get_template_directory() . '/inc/multisite.php';

require_once get_template_directory() . '/inc/admin.php';
require_once get_template_directory() . '/inc/quote-mode.php';
require_once get_template_directory() . '/inc/admin-columns.php';

require_once get_template_directory() . '/inc/api.php';
require_once get_template_directory() . '/inc/site-bootstrap.php';
require_once get_template_directory() . '/inc/activation.php';

require_once get_template_directory() . '/inc/mega-menu.php';

$lloyds_flipbook_plugin = get_template_directory() . '/plugins/lloyds-pdf-flipbook/lloyds-pdf-flipbook.php';
if (file_exists($lloyds_flipbook_plugin) && !function_exists('lloyds_flipbook_register_post_type')) {
    require_once $lloyds_flipbook_plugin;
}
