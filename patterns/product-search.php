<?php
/**
 * Title: Product Search
 * Slug: lloyds-industrial/product-search
 * Categories: lloyds-industrial
 */
?>

<!-- wp:group {"align":"wide","className":"li-floating-search","style":{"spacing":{"margin":{"top":"-52px","bottom":"72px"}}}} -->
<div class="wp-block-group alignwide li-floating-search" style="margin-top:-52px;margin-bottom:72px">

    <!-- wp:html -->
    <form class="li-product-search-form" role="search" method="get" action="/">
        <label class="screen-reader-text" for="li-product-search">Search products</label>
        <input id="li-product-search" type="search" name="s" placeholder="Search products, applications, or industries..." value="">
        <input type="hidden" name="post_type" value="product">
        <button type="submit">Search</button>
    </form>
    <!-- /wp:html -->

</div>
<!-- /wp:group -->
