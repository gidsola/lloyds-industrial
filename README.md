# B2B Industrial WordPress Theme

An enterprise WordPress block theme for B2B organizations. The project combines a Full Site Editing theme with WooCommerce catalogue and account flows, product discovery, customer-gated documentation, reseller management, contact and mailing-list tools, SEO controls, analytics, an AI chatbot, and a local PDF flipbook plugin.

This repository is a deployable WordPress theme rather than a standalone application. WordPress and its database remain the runtime; the theme owns most site-specific business logic and presentation.

## Requirements

- WordPress 6.8 or newer
- PHP 8.2 or newer
- A writable WordPress uploads directory
- WooCommerce for ecommerce, customer purchase checks, account pages, and SDS purchase-gated access
- A web server capable of serving WordPress permalinks
- Optional server tools for the flipbook renderer: Poppler `pdftoppm` is preferred; Imagick with Ghostscript is the fallback
- Optional SMTP provider credentials for reliable outbound mail


## What It Provides

### Public site and catalogue

- Full Site Editing templates, template parts, patterns, and `theme.json` design settings
- Product archive, product detail, taxonomy archive, search, filtering, category cards, and product carousel experiences
- Product taxonomies for brands, industries, applications, and WooCommerce categories
- Industry and application filtering through query-string links and AJAX product search
- Custom product fields for a public summary and certifications
- Product-associated document listings
- Configurable announcement bar, header actions, footer content, branding, navigation, and mega menus

### WooCommerce and B2B workflows

- WooCommerce support for product galleries, zoom, lightbox, and slider behavior
- Custom cart, checkout, account, and notice shortcodes used by the starter pages
- WooCommerce styles are disabled so the theme can provide its own presentation
- Optional Quote Mode hides normal prices, disables purchasing, and changes add-to-cart labels to `Request Quote`
- Quote price visibility is limited to distributors and privileged store administrators
- The account view falls back to a native WordPress login panel when WooCommerce is unavailable
- Product purchase history determines eligibility for SDS documents

### Protected documentation

The `li_document` post type stores technical documents and related metadata:

- Document file attachment
- Related WooCommerce product
- Access level (`public`, `internal`, or SDS-specific purchase access)
- Optional protected document path
- Hierarchical `li_document_type` taxonomy

SDS documents are identified by document type and require a logged-in customer whose WooCommerce order contains the related product. Administrators and users with the document-management capability bypass the normal access check. Protected downloads are streamed through WordPress rather than exposed as ordinary public attachment links.

The protected directory is created below `wp-content/uploads/li-protected-documents/` and receives `index.php`, `.htaccess`, and `web.config` guards where the filesystem permits it. Verify web-server configuration during deployment; application-level checks should not be the only protection for sensitive files.

### Embedded PDF flipbooks

- Registers the `flipbook` post type
- Selects a PDF from the Media Library and optionally marks it as featured
- Renders catalogue pages locally from the source PDF
- Provides an AJAX manifest endpoint for page metadata
- Provides individual viewer and catalogue-library shortcodes
- Does not depend on an external flipbook service

## Project Layout

```text
functions.php                         Theme bootstrap and include order
style.css                             Theme metadata and WordPress requirements
theme.json                            Global styles, block settings, templates
inc/setup.php                         Theme supports and foundational setup
inc/assets.php                        Frontend/admin CSS, JS, and localized config
inc/site-bootstrap.php                Starter pages, menus, WooCommerce setup, migrations
inc/woocommerce.php                   WooCommerce support and cart/checkout shortcodes
inc/products.php                      Product taxonomies, filters, search, cards
inc/product-*.php                     Product fields, carousels, and documents
inc/document*.php                     Document post type, fields, access, downloads
inc/account.php                       Login, account, and purchased-product views
inc/customer-roles.php                Verified customer and distributor roles
inc/quote-mode.php                    B2B quote-mode behavior
inc/resellers.php                     Reseller records and finder
inc/contact-forms.php                 Contact, quote, and application submissions
inc/mailing-list.php                  Subscribers, campaigns, queues, and SMTP
inc/analytics.php                     Commerce and site event tracking
inc/seo.php                           SEO metadata, schema, sitemap/cache tools
inc/ai-chatbot.php                    Chatbot UI, AJAX handlers, and admin settings
inc/api.php                           Public product and document REST endpoints
inc/admin*.php                        Settings and admin enhancements
inc/mega-menu.php                     Mega-menu data, editor, and frontend output
parts/                                Header, footer, and announcement template parts
patterns/                             Reusable PHP-rendered block patterns
templates/                            Block templates and WooCommerce templates
starter-content/                      HTML content used by site bootstrapping
assets/css/                           Frontend, editor, admin, chatbot, and menu styles
assets/js/                            Frontend and admin behavior
assets/images/, assets/video/         Theme media
plugins/pdf-flipbook/                 Bundled local PDF viewer plugin
```

The include order and optional plugin loading are defined in [functions.php](functions.php). Most feature files are split by domain so the theme can be maintained without putting all behavior in one file.

## Installation

1. Install WordPress 6.8+ with PHP 8.2+.
2. Install and activate WooCommerce before enabling customer purchase-dependent features.
3. Deploy this directory as `wp-content/themes/b2b-industrial/`.
4. Activate under **Appearance > Themes**.
5. Review settings and configure branding, layout, behavior, contact, mailing, SEO, analytics, chatbot, and menu options.
6. Confirm generated pages under **Settings > Reading**, **Settings > Permalinks**, and WooCommerce page settings.
7. Upload products, product terms, documents, PDFs, and media.
8. Configure outgoing mail or SMTP before enabling contact forms, account notifications, or campaigns.
9. Flush permalinks after deployment or whenever a post type or rewrite configuration changes.

### First activation behavior

On first activation, [inc/site-bootstrap.php](inc/site-bootstrap.php) creates starter pages, sets reading options, creates navigation, assigns WooCommerce pages, seeds product terms, stores a bootstrap flag, and flushes rewrite rules. The current content migration version is `11`.

The bootstrap avoids recreating content after the initial run. Existing starter pages can be refreshed from the admin tools, but reseeding or migrations can update page content and should be tested on staging first.

The expected starter page families include Home, Products, Industries, About, Partners, Contact, Account, Catalogue, Cart, Checkout, Find a Reseller, Reseller Application, industry pages, and documentation pages for technical documents, SDS, data sheets, and certifications.

## Admin Workflows

### Products

Create products through WooCommerce. Assign product categories, brands, industries, and applications. Add the public summary, certifications, featured media, and related documents as needed. Product archive behavior is implemented by [inc/products.php](inc/products.php); product-specific fields and document presentation live in [inc/product-fields.php](inc/product-fields.php) and [inc/product-documents.php](inc/product-documents.php).

### Documents and SDS files

1. Create a Document under the Documents admin area.
2. Select a document type.
3. Attach the file and select the related product.
4. Choose the access level.
5. For SDS files, use an SDS or safety-data-sheet document type.
6. Test both an eligible customer account and an ineligible or logged-out visitor.

Do not assume hiding a link protects a file. Use the document download functions and confirm that direct attachment URLs do not expose protected material.

### Accounts and roles

The theme creates these roles on `init` when absent:

| Role | Capabilities | Purpose |
| --- | --- | --- |
| `li_verified_customer` | `read`, `read_li_documents` | Customer-facing access role |
| `li_distributor` | `read`, `read_li_documents` | Distributor account; quote-mode-aware |

Document administrators use the `manage_li_documents` capability, normally alongside an administrator or explicitly configured role. WooCommerce customer purchase checks still require WooCommerce to be active.

### Mail and campaigns

The mailing-list module registers subscribers, campaigns, and subscriber tags. It supports signup AJAX handling, campaign queues, exports, test sends, and optional SMTP through `phpmailer_init`. Keep SMTP credentials out of version control and configure them through the admin UI or an appropriate secrets-management process.

### Chatbot and external services

The chatbot uses AJAX actions for messages, history, login, action tracking, and administration. Its endpoint and related settings are configured in the admin panel. Review the configured provider endpoint, privacy implications, retention behavior, and rate limits before enabling it publicly.

## REST API

The theme registers public read-only routes under `/wp-json/b2b/v1` in [inc/api.php](inc/api.php):

| Route | Description |
| --- | --- |
| `GET /products` | Returns up to 50 published products with title, URL, summary, categories, industries, applications, certifications, and a document endpoint reference |
| `GET /products/{id}/documents` | Returns up to 50 related documents visible to the current visitor, including access level, SDS status, availability, and a protected download URL when authorized |

The route permission callbacks are public, but document results are filtered by the same access rules used by the site. Treat product summaries and taxonomy data as public API output. Add authentication, pagination, caching, or stricter permissions before using these routes for a high-volume or sensitive integration.

## Shortcodes

Common shortcodes registered by the theme include:

| Shortcode | Purpose |
| --- | --- |
| `[li_customer_account]` | WooCommerce account or fallback login flow |
| `[li_woocommerce_notices]` | WooCommerce notices |
| `[li_woocommerce_cart]` | WooCommerce cart |
| `[li_woocommerce_checkout]` | WooCommerce checkout |
| `[li_product_search]` | Product search form |
| `[li_product_filters]` | Product category, industry, and application filters |
| `[li_product_category_cards]` | Product category card listing |
| `[li_product_carousel]` | Product carousel |
| `[li_product_documents]` | Documents associated with a product |
| `[li_contact_form]`, `[contact_form]`, `[quote_request_form]` | Contact and quote submission forms |
| `[li_reseller_finder]` | Reseller finder |
| `[li_mailing_list_signup]`, `[li_newsletter_signup]`, `[mailing_list_signup]` | Mailing-list signup form |
| `[pdf_flipbook id="123"]` | Individual local PDF flipbook |
| `[pdf_catalogue_library]` | Featured/catalogue flipbook library |

Header, footer, announcement, mega-menu, and several content modules also expose theme-specific shortcodes. Search the owning file before adding a new shortcode with a similar name.

## Frontend Assets

Frontend assets are loaded from [inc/assets.php](inc/assets.php). The main behavior is in [assets/js/theme.js](assets/js/theme.js), with separate modules for the chatbot, mega menu, and admin/editor interfaces. Styles live in [assets/css/main.css](assets/css/main.css), with dedicated editor, chatbot, and mega-menu styles.

The frontend uses WordPress AJAX URLs and nonces localized into JavaScript. Preserve those localized configuration objects when changing script handles or loading conditions.

## Development

There is currently no `composer.json`, `package.json`, build script, test suite, or lint configuration in the repository. Checked-in CSS and JavaScript are served directly by WordPress.

Recommended local workflow:

1. Use a disposable local WordPress installation with a real database.
2. Activate the theme and WooCommerce.
3. Exercise first activation and migrations on a database snapshot.
4. Test anonymous, logged-in, distributor, administrator, eligible purchaser, and ineligible purchaser flows.
5. Test permalinks, REST output, AJAX nonces, file downloads, mail delivery, and the flipbook renderer.
6. Inspect PHP logs and browser console/network output while testing.
7. Run PHP syntax checks on changed files, for example `php -l path/to/changed-file.php`, when PHP is available locally.

Keep site content, uploads, credentials, and database exports outside this repository.

## Deployment Checklist

- Confirm PHP and WordPress versions meet the theme header requirements.
- Activate WooCommerce before relying on order history or SDS purchase checks.
- Back up the database before activation, reseeding, or content migrations.
- Verify uploads and `wp-content/uploads/li-protected-documents/` permissions.
- Confirm web-server deny rules for protected document storage.
- Configure SMTP and test contact, account, and campaign mail.
- Review chatbot provider settings and privacy requirements.
- Test Quote Mode and its effect on product purchasing before enabling it in production.
- Flush permalinks after activation and rewrite-related changes.
- Verify REST and AJAX endpoints with both authorized and unauthorized users.
- Test the local PDF renderer with representative PDFs and confirm generated page assets are readable.
- Clear page, object, CDN, and WooCommerce caches after deployment where applicable.

## Security Notes

- Forms, admin actions, AJAX actions, and document operations use WordPress nonces and capability checks in their owning modules.
- Public REST routes expose product catalogue data by design.
- Document listing and download authorization are separate concerns; test both.
- Protected files must not be placed in a directly browsable public location without server-level protection.
- Keep API keys, SMTP passwords, provider URLs, and chatbot credentials out of source control.
- Treat HTML, PDF, media, and uploaded metadata as untrusted input and preserve existing sanitization and escaping patterns.
- Review external chatbot and analytics behavior against the site's privacy and consent requirements.

## Troubleshooting

### Starter pages did not appear

Check the `li_site_bootstrapped` and `li_site_content_version` options, review admin notices, and confirm the activating user can manage options. Run built-in reseed or migration actions only after taking a database backup.

### Cart or checkout is empty or unavailable

Confirm WooCommerce is active and that the generated Cart and Checkout pages are assigned in WooCommerce settings. The theme's shortcode wrappers intentionally display a fallback state when WooCommerce is unavailable.

### SDS access is denied unexpectedly

Confirm the user is logged in with the account email used for the order, the document is related to the correct product, the order is in a recognized status, and the document type is recognized as SDS. Also verify that the protected uploads directory is writable.

### Flipbook pages do not render

Check that `pdftoppm` is available to the PHP/web-server process. If it is not, verify Imagick and Ghostscript support. Check PHP execution limits, uploads permissions, and the browser network request for the flipbook manifest AJAX action.

### Mail is not delivered

Check WordPress mail logs and the server mail transport first. If SMTP is enabled, verify host, port, encryption, authentication, sender settings, and outbound firewall rules.

## License

The theme metadata declares `GPL-2.0-or-later`. Confirm the licensing terms of separately supplied media, external services, or future dependencies before redistribution.