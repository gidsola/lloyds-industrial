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
    if (class_exists('WooCommerce') && shortcode_exists('woocommerce_my_account')) {
        return '<div class="li-account-flow li-woo-account">' . do_shortcode('[woocommerce_my_account]') . '</div>';
    }

    return li_render_account_login_panel();
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
                <p class="li-eyebrow"><?php esc_html_e('Signed In', 'lloyds-industrial'); ?></p>
                <h2><?php echo esc_html(sprintf(__('Welcome, %s', 'lloyds-industrial'), $user->display_name ?: $user->user_login)); ?></h2>
                <p><?php esc_html_e('Use this account for customer resources and eligible SDS downloads. Once WooCommerce is installed, order history and account details will appear here.', 'lloyds-industrial'); ?></p>
                <div class="li-account-flow__actions">
                    <a class="li-button-primary" href="<?php echo esc_url(home_url('/documentation/sds/')); ?>">
                        <?php esc_html_e('Review SDS Access', 'lloyds-industrial'); ?>
                    </a>
                    <a class="li-button-secondary" href="<?php echo esc_url(wp_logout_url(li_get_account_url())); ?>">
                        <?php esc_html_e('Sign Out', 'lloyds-industrial'); ?>
                    </a>
                </div>
            </div>
        <?php else : ?>
            <div class="li-account-flow__grid">
                <div class="li-account-flow__copy">
                    <p class="li-eyebrow"><?php esc_html_e('Customer Login', 'lloyds-industrial'); ?></p>
                    <h2><?php esc_html_e('Sign In With Your Customer Account', 'lloyds-industrial'); ?></h2>
                    <p><?php esc_html_e('Use the account tied to your Lloyds product orders. SDS access is matched against purchase history for the related product.', 'lloyds-industrial'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Account login is the front door for customer access.', 'lloyds-industrial'); ?></li>
                        <li><?php esc_html_e('SDS downloads remain product-specific and purchase-gated.', 'lloyds-industrial'); ?></li>
                        <li><?php esc_html_e('If your order history is missing, contact support to help match the account.', 'lloyds-industrial'); ?></li>
                    </ul>
                </div>
                <div class="li-account-flow__form">
                    <?php
                    wp_login_form([
                        'echo'           => true,
                        'redirect'       => $redirect_to,
                        'label_username' => __('Email or Username', 'lloyds-industrial'),
                        'label_password' => __('Password', 'lloyds-industrial'),
                        'label_log_in'   => __('Sign In', 'lloyds-industrial'),
                    ]);
                    ?>
                    <p class="li-account-flow__links">
                        <a href="<?php echo esc_url(wp_lostpassword_url(li_get_account_url())); ?>">
                            <?php esc_html_e('Forgot password?', 'lloyds-industrial'); ?>
                        </a>
                        <?php if (get_option('users_can_register')) : ?>
                            <a href="<?php echo esc_url(wp_registration_url()); ?>">
                                <?php esc_html_e('Create account', 'lloyds-industrial'); ?>
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
            <p class="li-eyebrow"><?php esc_html_e('How SDS Access Works', 'lloyds-industrial'); ?></p>
            <h2 id="li-sds-flow-title"><?php esc_html_e('SDS Documents Are Private To Eligible Customers', 'lloyds-industrial'); ?></h2>
            <p><?php esc_html_e('Safety Data Sheets are not a public library. Sign in with the account connected to your product purchase, then open the related product page to download eligible SDS files.', 'lloyds-industrial'); ?></p>
        </div>

        <div class="li-sds-flow__steps">
            <article>
                <span>1</span>
                <h3><?php esc_html_e('Sign in', 'lloyds-industrial'); ?></h3>
                <p><?php esc_html_e('Use the same account associated with the product order.', 'lloyds-industrial'); ?></p>
            </article>
            <article>
                <span>2</span>
                <h3><?php esc_html_e('Open the product', 'lloyds-industrial'); ?></h3>
                <p><?php esc_html_e('SDS access is checked against the related product record.', 'lloyds-industrial'); ?></p>
            </article>
            <article>
                <span>3</span>
                <h3><?php esc_html_e('Download if eligible', 'lloyds-industrial'); ?></h3>
                <p><?php esc_html_e('Purchased-product matches can download through protected document streaming.', 'lloyds-industrial'); ?></p>
            </article>
        </div>

        <div class="li-sds-flow__actions">
            <?php if (is_user_logged_in()) : ?>
                <a class="li-button-primary" href="<?php echo esc_url($products_url); ?>">
                    <?php esc_html_e('Browse Products', 'lloyds-industrial'); ?>
                </a>
            <?php else : ?>
                <a class="li-button-primary" href="<?php echo esc_url($account_url); ?>">
                    <?php esc_html_e('Sign In For SDS Access', 'lloyds-industrial'); ?>
                </a>
            <?php endif; ?>
            <a class="li-button-secondary" href="<?php echo esc_url($contact_url); ?>">
                <?php esc_html_e('Get Access Help', 'lloyds-industrial'); ?>
            </a>
        </div>
    </section>
    <?php

    return (string) ob_get_clean();
}

add_shortcode('li_customer_account', 'li_render_customer_account_shortcode');
add_shortcode('li_sds_access_panel', 'li_render_sds_access_panel_shortcode');
