<?php
/**
 * Title: Industrial Hero
 * Slug: b2b-industrial/hero
 * Categories: b2b-industrial
 */
?>

<!-- wp:cover {"url":"<?php echo esc_url(get_template_directory_uri()); ?>/assets/images/industrial-hero.webp","dimRatio":70,"overlayColor":"primary","minHeight":760,"minHeightUnit":"px","align":"full","className":"li-hero-cover"} -->
<div class="wp-block-cover alignfull li-hero-cover" style="min-height:760px">
    <span aria-hidden="true" class="wp-block-cover__background has-primary-background-color has-background-dim-70 has-background-dim"></span>
    <img class="wp-block-cover__image-background" alt="" src="<?php echo esc_url(get_template_directory_uri()); ?>/assets/images/industrial-hero.webp" data-object-fit="cover"/>

    <div class="wp-block-cover__inner-container">

        <!-- wp:group {"align":"wide","layout":{"type":"constrained","contentSize":"880px","justifyContent":"left"}} -->
        <div class="wp-block-group alignwide">

            <!-- wp:paragraph {"className":"li-eyebrow","textColor":"accent"} -->
            <p class="li-eyebrow has-accent-color has-body-text-color">Canadian Manufacturing Since 1919</p>
            <!-- /wp:paragraph -->

            <!-- wp:heading {"level":1,"textColor":"white"} -->
            <h1 class="wp-block-heading has-white-color has-body-text-color">Industrial Chemistry Engineered For Performance</h1>
            <!-- /wp:heading -->

            <!-- wp:paragraph {"textColor":"white","fontSize":"medium"} -->
            <p class="has-white-color has-body-text-color has-medium-font-size">Trusted by utilities, transportation, manufacturing, agriculture and infrastructure professionals across North America.</p>
            <!-- /wp:paragraph -->

            <!-- wp:buttons {"style":{"spacing":{"margin":{"top":"32px"}}}} -->
            <div class="wp-block-buttons" style="margin-top:32px">

                <!-- wp:button {"url":"/industries","backgroundColor":"accent","textColor":"white"} -->
                <div class="wp-block-button">
                    <a class="wp-block-button__link has-white-color has-accent-background-color has-body-text-color has-background wp-element-button" href="/industries">Explore Solutions</a>
                </div>
                <!-- /wp:button -->

                <!-- wp:button {"url":"/documentation","textColor":"white","className":"is-style-outline"} -->
                <div class="wp-block-button is-style-outline">
                    <a class="wp-block-button__link has-white-color has-body-text-color wp-element-button" href="/documentation">Technical Resources</a>
                </div>
                <!-- /wp:button -->

            </div>
            <!-- /wp:buttons -->

        </div>
        <!-- /wp:group -->

    </div>
</div>
<!-- /wp:cover -->