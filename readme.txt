=== WH4U Domains ===
Contributors: webhosting4u
Tags: domains, domain search, domain registration, reseller, tld
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Domain reseller plugin for searching, registering, and transferring domains via the DomainsReseller API.

== Description ==

WH4U Domains is a WordPress plugin that allows website owners and resellers to offer domain name services directly from their WordPress site. It integrates with the DomainsReseller API to provide real-time domain availability checking, registration, and transfer capabilities.

= Features =

* **Domain Search** -- Visitors can search for domain availability via a shortcode or Gutenberg block
* **Public Registration & Transfer** -- Anonymous visitors can submit domain registration and transfer requests that admins review and approve
* **Admin Dashboard** -- Full admin panel for managing orders, viewing API status, and monitoring the retry queue
* **Reseller Support** -- Per-user API credentials allow multi-reseller setups
* **Retry Queue** -- Failed API calls are automatically retried with exponential backoff
* **Shopping Cart Redirect** -- Optional redirect to WHMCS, Blesta, ClientExec, Upmind, or custom cart URL with the domain pre-filled when visitors click Register or Transfer
* **Internationalization** -- Fully translatable; Greek translation included

= How It Works =

1. Install and activate the plugin
2. Configure your DomainsReseller API credentials under Domains > Settings
3. Add the `[wh4u_domain_lookup]` shortcode or the "Domain Lookup" block to any page
4. Visitors search for domains -- available domains can be registered, and taken domains can be transferred through the frontend form (or, if configured, sent to your WHMCS/Blesta/ClientExec/Upmind cart with the domain in the URL)
5. Admins review and approve/reject public orders from the WordPress admin

== Third-Party Service ==

This plugin connects to the **DomainsReseller API** provided by WebHosting4U to perform all domain-related operations.

= What data is sent =

* **Domain lookups**: The domain name being searched is sent to check availability
* **Domain registration**: Registrant contact information (name, email, phone, address, company, country), the domain name, registration period, nameservers, and addon preferences are sent to process the registration
* **Domain transfer**: Domain name, registration period, and EPP code are sent
* **TLD and pricing queries**: Requests for available TLDs and their pricing are sent

= When data is sent =

* When a visitor performs a domain search on the frontend
* When an admin approves a public domain registration order
* When an admin submits a registration or transfer order from the admin panel
* When TLD lists or pricing are loaded (cached locally for 12 hours)

= Service details =

* **Service provider**: WebHosting4U
* **Service URL**: [https://webhosting4u.gr](https://webhosting4u.gr)
* **Terms of Service**: [https://webhosting4u.gr/terms-of-service.php](https://webhosting4u.gr/terms-of-service.php)
* **Privacy Policy**: [https://webhosting4u.gr/privacy-policy.php](https://webhosting4u.gr/privacy-policy.php)

= Cloudflare Turnstile (optional) =

When Turnstile bot protection is enabled in Settings, this plugin loads the Cloudflare Turnstile JavaScript widget on pages with the domain lookup form and sends the challenge response token to Cloudflare for server-side verification before processing public orders.

* **Service provider**: Cloudflare, Inc.
* **Service URL**: [https://www.cloudflare.com/products/turnstile/](https://www.cloudflare.com/products/turnstile/)
* **Terms of Service**: [https://www.cloudflare.com/terms/](https://www.cloudflare.com/terms/)
* **Privacy Policy**: [https://www.cloudflare.com/privacypolicy/](https://www.cloudflare.com/privacypolicy/)

== Installation ==

1. Upload the `wh4u-domains` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu in WordPress
3. Go to Domains > Settings > Credentials and enter your DomainsReseller API email and API key
4. Set your default nameservers under the same Credentials tab
5. Go to Domains > Settings > General to choose between Real-time or Notification-only registration mode
6. (Optional) Under General, set Shopping Cart Redirect to WHMCS, Blesta, ClientExec, Upmind, or Custom and enter your cart URL so visitors are sent to your cart with the domain pre-filled
7. Add `[wh4u_domain_lookup]` to any page, or use the "Domain Lookup" Gutenberg block

== Frequently Asked Questions ==

= Do I need a DomainsReseller account? =

Yes. You need valid API credentials from WebHosting4U to use this plugin. Without credentials, the domain search will not function.

= Can visitors register domains without logging in? =

Yes. The frontend form allows anonymous visitors to submit registration and transfer requests. These are stored as pending orders that an admin must approve before the domain is processed through the API.

= What happens if the API is temporarily unavailable? =

Orders that fail due to temporary API issues (timeouts, server errors) are automatically queued for retry with exponential backoff. The retry queue processes items via WP-Cron.

= Can I send customers to my WHMCS (or other) cart instead of the built-in form? =

Yes. Under Domains > Settings > General, use the Shopping Cart Redirect section. Choose WHMCS, Blesta, ClientExec, Upmind, or Custom URL template, and enter your cart base URL (or full URL templates with {domain}, {sld}, {tld}). When a visitor clicks Register or Transfer, they will be redirected to your cart with the domain pre-filled.

= Is the plugin translatable? =

Yes. The plugin is fully internationalized. A Greek translation is included. Additional translations can be added via .po/.mo files in the `languages/` directory.

== Changelog ==

= 1.4.0 =
* Added optional Cloudflare Turnstile bot protection for public registration and transfer forms
* Turnstile site key and secret key configurable under Domains > Settings > General
* Client-side Turnstile widget rendered explicitly with auto theme detection
* Server-side token verification on public order REST endpoints before order creation
* Turnstile is fully optional -- forms work without it when keys are not configured

= 1.3.0 =
* Enriched domain lookup frontend with skeleton shimmer loading cards during API calls
* Added popular TLD chips below the search bar (configurable in Appearance > Search Bar)
* Primary result card now highlighted with accent styling and "Best match" badge
* Pricing display on available domain results when show-pricing is enabled
* Dark mode support via prefers-color-scheme media query (respects user color overrides)
* Animated SVG status icons (checkmark/X) on result cards with entrance animation
* Copy-to-clipboard button on each result card with tooltip feedback
* Animated placeholder text cycling through example domains in the search input
* Automatic theme design adoption: plugin detects active theme colors, fonts, spacing, and border radius from theme.json
* Theme compatibility banner in Appearance settings showing detected design tokens
* Fixed appearance settings not passing to frontend when using global defaults
* Improved i18n: removed hardcoded English fallback strings in JavaScript

= 1.2.0 =
* Added shopping cart redirect: optional redirect to WHMCS, Blesta, ClientExec, Upmind, or custom URL when visitors click Register or Transfer
* Domain (or sld/tld) is passed in the cart URL so the billing cart opens with the chosen domain
* Configure under Domains > Settings > General (Shopping Cart Redirect section)

= 1.1.0 =
* Added public domain registration and transfer via frontend form
* Added admin approve/reject workflow for public orders
* Added automatic API processing on order approval
* Added retry queue for failed API calls
* Added Greek translation

= 1.0.0 =
* Initial release
* Domain search, registration, and transfer
* Admin dashboard with API status and credits
* Per-user reseller credentials with encrypted storage
* TLD pricing display
* Rate limiting and logging
