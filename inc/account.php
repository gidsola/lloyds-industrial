<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function li_get_account_login_url(string $redirect_to = ''): string
{
    $url = li_get_account_url();

    if ($redirect_to !== '') {
        $url = add_query_arg('redirect_to', $redirect_to, $url);
    }

    return $url;
}

function li_get_account_redirect_url(): string
{
    $redirect_to = isset($_GET['redirect_to'])
        ? esc_url_raw(wp_unslash((string) $_GET['redirect_to']))
        : '';

    return $redirect_to !== '' ? $redirect_to : li_get_account_url();
}

function li_render_customer_account_shortcode(): string
{
    $sds_section = li_render_account_sds_section();

    if (class_exists('WooCommerce') && shortcode_exists('woocommerce_my_account')) {
        return '<div class="li-account-flow li-woo-account">' . do_shortcode('[woocommerce_my_account]') . $sds_section . '</div>';
    }

    return li_render_account_login_panel() . $sds_section;
}

function li_render_account_login_panel(): string
{
    $redirect_to = li_get_account_redirect_url();

    ob_start();
    ?>
    <div class="li-account-flow">
        <?php if (is_user_logged_in()) : ?>
            <?php $user = wp_get_current_user(); ?>
            <div class="li-account-flow__status li-account-flow__status--signed-in">
                <p class="li-eyebrow"><?php esc_html_e('Signed In', 'b2b-industrial'); ?></p>
                <h2><?php echo esc_html(sprintf(__('Welcome, %s', 'b2b-industrial'), $user->display_name ?: $user->user_login)); ?></h2>
                <p><?php esc_html_e('Use this account for customer resources and eligible SDS downloads. Once WooCommerce is installed, order history and account details will appear here.', 'b2b-industrial'); ?></p>
                <div class="li-account-flow__actions">
                    <a class="li-button-primary" href="<?php echo esc_url(home_url('/documentation/sds/')); ?>">
                        <?php esc_html_e('Review SDS Access', 'b2b-industrial'); ?>
                    </a>
                    <a class="li-button-secondary" href="<?php echo esc_url(wp_logout_url(li_get_account_url())); ?>">
                        <?php esc_html_e('Sign Out', 'b2b-industrial'); ?>
                    </a>
                </div>
            </div>
        <?php else : ?>
            <div class="li-account-flow__grid">
                <div class="li-account-flow__copy">
                    <p class="li-eyebrow"><?php esc_html_e('Customer Login', 'b2b-industrial'); ?></p>
                    <h2><?php esc_html_e('Sign In With Your Customer Account', 'b2b-industrial'); ?></h2>
                    <p><?php esc_html_e('Use the account tied to your B2B product orders. SDS access is matched against purchase history for the related product.', 'b2b-industrial'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Account login is the front door for customer access.', 'b2b-industrial'); ?></li>
                        <li><?php esc_html_e('SDS downloads remain product-specific and purchase-gated.', 'b2b-industrial'); ?></li>
                        <li><?php esc_html_e('If your order history is missing, contact support to help match the account.', 'b2b-industrial'); ?></li>
                    </ul>
                </div>
                <div class="li-account-flow__form">
                    <?php
                    wp_login_form([
                        'echo'           => true,
                        'redirect'       => $redirect_to,
                        'label_username' => __('Email or Username', 'b2b-industrial'),
                        'label_password' => __('Password', 'b2b-industrial'),
                        'label_log_in'   => __('Sign In', 'b2b-industrial'),
                    ]);
                    ?>
                    <p class="li-account-flow__links">
                        <a href="<?php echo esc_url(wp_lostpassword_url(li_get_account_url())); ?>">
                            <?php esc_html_e('Forgot password?', 'b2b-industrial'); ?>
                        </a>
                        <?php if (get_option('users_can_register')) : ?>
                            <a href="<?php echo esc_url(wp_registration_url()); ?>">
                                <?php esc_html_e('Create account', 'b2b-industrial'); ?>
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php

    return (string) ob_get_clean();
}

function li_render_sds_access_panel_shortcode(): string
{
    $account_url = li_get_account_login_url(home_url('/documentation/sds/'));
    $contact_url = home_url('/contact/');
    $products_url = home_url('/products/');

    ob_start();
    ?>
    <section class="li-sds-flow" aria-labelledby="li-sds-flow-title">
        <div class="li-sds-flow__header">
            <p class="li-eyebrow"><?php esc_html_e('How SDS Access Works', 'b2b-industrial'); ?></p>
            <h2 id="li-sds-flow-title"><?php esc_html_e('SDS Documents Are Private To Eligible Customers', 'b2b-industrial'); ?></h2>
            <p><?php esc_html_e('Safety Data Sheets are not a public library. Sign in with the account connected to your product purchase, then open the related product page to download eligible SDS files.', 'b2b-industrial'); ?></p>
        </div>

        <div class="li-sds-flow__steps">
            <article>
                <span>1</span>
                <h3><?php esc_html_e('Sign in', 'b2b-industrial'); ?></h3>
                <p><?php esc_html_e('Use the same account associated with the product order.', 'b2b-industrial'); ?></p>
            </article>
            <article>
                <span>2</span>
                <h3><?php esc_html_e('Open the product', 'b2b-industrial'); ?></h3>
                <p><?php esc_html_e('SDS access is checked against the related product record.', 'b2b-industrial'); ?></p>
            </article>
            <article>
                <span>3</span>
                <h3><?php esc_html_e('Download if eligible', 'b2b-industrial'); ?></h3>
                <p><?php esc_html_e('Purchased-product matches can download through protected document streaming.', 'b2b-industrial'); ?></p>
            </article>
        </div>

        <div class="li-sds-flow__actions">
            <?php if (is_user_logged_in()) : ?>
                <a class="li-button-primary" href="<?php echo esc_url($products_url); ?>">
                    <?php esc_html_e('Browse Products', 'b2b-industrial'); ?>
                </a>
            <?php else : ?>
                <a class="li-button-primary" href="<?php echo esc_url($account_url); ?>">
                    <?php esc_html_e('Sign In For SDS Access', 'b2b-industrial'); ?>
                </a>
            <?php endif; ?>
            <a class="li-button-secondary" href="<?php echo esc_url($contact_url); ?>">
                <?php esc_html_e('Get Access Help', 'b2b-industrial'); ?>
            </a>
        </div>
    </section>
    <?php

    return (string) ob_get_clean();
}

function li_user_has_purchased_product(int $product_id, ?int $user_id = null): bool
{
    $user_id = $user_id ?: get_current_user_id();

    if (!$product_id || !$user_id || !function_exists('wc_customer_bought_product')) {
        return false;
    }

    $user = get_userdata($user_id);

    if (!$user instanceof WP_User) {
        return false;
    }

    return wc_customer_bought_product($user->user_email, $user_id, $product_id);
}

function li_get_user_purchased_product_ids(int $user_id): array
{
    if (!$user_id || !function_exists('wc_get_orders')) {
        return [];
    }

    $orders = wc_get_orders([
        'customer_id' => $user_id,
        'status'      => ['completed', 'processing', 'on-hold'],
        'limit'       => -1,
        'return'      => 'objects',
    ]);
    $product_ids = [];

    foreach ($orders as $order) {
        if (!is_object($order) || !method_exists($order, 'get_items')) {
            continue;
        }

        foreach ($order->get_items() as $item) {
            if (!is_object($item) || !method_exists($item, 'get_product_id')) {
                continue;
            }

            $product_id = (int) $item->get_product_id();

            if ($product_id) {
                $product_ids[] = $product_id;
            }
        }
    }

    return array_values(array_unique(array_map('absint', $product_ids)));
}

function li_render_account_sds_section(): string
{
    if (!is_user_logged_in()) {
        return '';
    }

    $product_ids = li_get_user_purchased_product_ids(get_current_user_id());
    $items = [];

    foreach ($product_ids as $product_id) {
        $document_id = function_exists('li_get_product_sds_document_id') ? li_get_product_sds_document_id($product_id) : 0;

        if (!$document_id || !function_exists('li_get_document_download_url')) {
            continue;
        }

        $download_url = li_get_document_download_url($document_id);

        if (!$download_url) {
            continue;
        }

        $items[] = [
            'product_id'   => $product_id,
            'product_name' => get_the_title($product_id),
            'product_url'  => get_permalink($product_id),
            'document_id'  => $document_id,
            'download_url' => $download_url,
        ];
    }

    ob_start();
    ?>
    <section class="li-account-sds">
        <div class="li-account-sds__header">
            <p class="li-eyebrow"><?php esc_html_e('SDS Library', 'b2b-industrial'); ?></p>
            <h2><?php esc_html_e('SDS For Purchased Products', 'b2b-industrial'); ?></h2>
            <p><?php esc_html_e('Safety Data Sheets appear here when your account has purchase history for the related product.', 'b2b-industrial'); ?></p>
        </div>

        <?php if (!$items) : ?>
            <div class="li-account-sds__empty">
                <p><?php esc_html_e('No eligible SDS downloads were found for this account yet.', 'b2b-industrial'); ?></p>
                <a class="li-button-secondary" href="<?php echo esc_url(home_url('/contact/')); ?>"><?php esc_html_e('Get SDS Access Help', 'b2b-industrial'); ?></a>
            </div>
        <?php else : ?>
            <div class="li-account-sds__list">
                <?php foreach ($items as $item) : ?>
                    <article class="li-account-sds__item">
                        <div>
                            <h3><?php echo esc_html($item['product_name']); ?></h3>
                            <p><?php echo esc_html(get_the_title($item['document_id'])); ?></p>
                        </div>
                        <div class="li-account-sds__actions">
                            <a class="li-button-secondary" href="<?php echo esc_url($item['product_url']); ?>"><?php esc_html_e('View Product', 'b2b-industrial'); ?></a>
                            <a class="li-button-primary" href="<?php echo esc_url($item['download_url']); ?>"><?php esc_html_e('Download SDS', 'b2b-industrial'); ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php

    return (string) ob_get_clean();
}

add_shortcode('li_customer_account', 'li_render_customer_account_shortcode');
add_shortcode('li_sds_access_panel', 'li_render_sds_access_panel_shortcode');
