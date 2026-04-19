=== WH4U Domains ===
Contributors: webhosting4ugr
Tags: domains, domain search, domain registration, reseller, tld
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.5.5
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
* **TLD queries**: Requests for available TLDs are sent

= When data is sent =

* When a visitor performs a domain search on the frontend
* When an admin approves a public domain registration order
* When an admin submits a registration or transfer order from the admin panel
* When TLD lists are loaded (cached locally for 12 hours)

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

= Data stored locally =

This plugin stores the following data on your WordPress installation:

* **Order records**: domain name, registration period, status, and encrypted registrant contact details (encrypted with AES-256-CBC + HMAC at rest)
* **Public order submissions**: stored as a custom post type with encrypted contact data until an administrator approves or rejects them
* **API logs** and **notification records**: request/response entries and email/webhook dispatch history with secrets redacted. A daily WP-Cron task prunes rows older than 30 days; the retention period is filterable via the `wh4u_log_retention_days` filter.
* **Retry queue**: failed API calls scheduled for exponential-backoff retry via WP-Cron
* **Rate-limit counters**: short-lived transients keyed by user ID or a salted SHA-256 hash of the visitor IP; used only for abuse protection and expire within minutes
* **Reseller settings**: per-user API credentials, with the API key and optional webhook secret encrypted at rest

No data is sent to any third party other than the services listed above.

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

== Screenshots ==

1. Admin dashboard with quick domain search and shortcuts to register, transfer, and renew actions.
2. Settings screen: API credentials, registration mode, and shopping cart redirect configuration.
3. Admin Register Domain form with domain details, nameservers, and registrant contact fields.

== Changelog ==

= 1.5.5 =
* Removed: TLD Pricing admin page and all pricing-related code paths. The upstream DomainsReseller API `/tlds/pricing` endpoint returns `registrationPrice: null` for TLDs whose registry enforces multi-year registration minimums (notably `.gr`, which requires a 2-year minimum), so a pricing table rendered from this endpoint cannot reliably display prices for every reseller-enabled TLD. Rather than half-showing data, the "View Pricing" tile on the dashboard now links out to the authoritative WHMCS pricing page at `https://webhosting4u.gr/customers/index.php?m=DomainsReseller&mg-page=Prices`
* Removed: `admin/class-wh4u-admin-pricing.php`, `rest-api/class-wh4u-rest-pricing.php`, the `/wh4u/v1/tlds`, `/wh4u/v1/tlds/pricing`, `/wh4u/v1/tlds/pricing/cache`, and `/wh4u/v1/pricing/(register|transfer)` REST endpoints, the `wh4u_tld_pricing_v2` transient, the `loadPricing()` admin JS routine, the frontend `prefetchPricing()` / `getPriceForTld()` code path, the `show_pricing` appearance setting, the `showPricing` block attribute, the `data-show-pricing` shortcode data attribute, and the `.wh4u-domains__result-price` CSS rules
* Changed: dashboard "View Pricing" quick link is now an external link (opens the WHMCS pricing page in a new tab with `rel="noopener noreferrer"`)

= 1.5.4 =
* Fix: TLD pricing page left the register column blank for ccTLDs whose upstream response emits an empty `registrationPrice` field because they have no 1-year tier (notably `.gr`, whose registry enforces a 2-year minimum). `extract_price()` in the REST pricing controller now skips empty scalars/arrays instead of accepting the first matching key, recurses into nested period-keyed arrays picking the lowest numeric period (so `{"2":"24.00","4":"48.00"}` surfaces the 2-year price), and as a last resort scans period-suffixed variants like `register2` or `registrationPrice_2y`
* Added: "Refresh Cache" button on the TLD Pricing admin page, backed by a new `DELETE /wh4u/v1/tlds/pricing/cache` endpoint (gated by the `wh4u_manage_domains` capability) that clears both the `wh4u_tld_pricing_v2` and `wh4u_tlds_cache` transients; needed because the previous normalization was cached for 12 hours and existing sites would otherwise keep serving stale empty prices after upgrade

= 1.5.3 =
* i18n: reinstated `load_plugin_textdomain()` on the `init` hook so self-hosted installs load translations correctly under WordPress 6.7+ (where gettext calls before `init` no longer resolve the user locale)
* i18n: replaced string concatenation patterns (`__( 'API call failed: ' ) . $msg`, `$label . ' ' . __( 'Contact' )`, `$period . ' ' . __( 'year(s)' )`) with `sprintf`/`_n` against `%s`/`%d` placeholders in admin-dashboard, admin-domains, admin-history, and notifications; word order can now be reordered per locale without code changes
* i18n: replaced the hardcoded English `' yr'` unit in the order history period column with a proper `_n( '%d year', '%d years' )` plural form
* i18n: added translator comments (`/* translators: ... */`) on every new `sprintf`/`_n` call per the WordPress handbook
* i18n: regenerated `wh4u-domains.pot` (412 strings) from the updated source, merged into `wh4u-domains-el.po`, and translated the remaining 77 previously-untranslated Greek strings (Frontend Appearance panel, Shopping Cart Redirect, Turnstile, Reverse Proxy / Trusted IPs, Public Orders post type + status plurals, block editor title/description/keywords, encryption key notices)
* i18n: rebuilt `wh4u-domains-el.mo` and the JSON translation file consumed by `wp_set_script_translations()`; removed two orphan JSON files whose `source` field held malformed artifacts (`(JS`, `i18n)`) from a prior tooling run

= 1.5.2 =
* Security: encryption key management rewrite -- removed the auto-generate-to-wp_options fallback so decryption keys are never created in the database; added a one-shot idempotent migration (guarded by a 5-minute transient lock) that re-encrypts reseller settings, order contacts, site settings, and public-order PII postmeta under the preferred WH4U_ENCRYPTION_KEY / AUTH_KEY-derived key and only deletes the legacy wp_options key after every row migrates successfully
* Security: webhook delivery now passes `redirection => 0` to `wp_remote_post()`, closing a redirect-based SSRF bypass where a crafted 3xx response could divert a webhook away from the validated/pinned host
* Security: `save_reseller_settings()` in the admin reseller screen now re-verifies the `wh4u_reseller_settings_nonce` inside the save handler (defense-in-depth alongside the existing `check_admin_referer()` wrapper)
* Security: defense-in-depth `(int)` cast on `$item->order_id` in the retry queue before it reaches `$wpdb->prepare()` with a `%d` placeholder, eliminating any theoretical type-confusion path regardless of upstream callers
* Security: removed the unused `WH4U_Logger::get_logs()` method so the admin-only log reader no longer exists as latent attack surface (no callers in the plugin)

= 1.5.1 =
* i18n: compiled Greek .mo binary from the existing .po catalogue so WordPress gettext loads translations at runtime without relying on a language pack
* i18n: all public-facing form labels (First Name, Last Name, Email, Phone, Address, City, State/Province, Country Code, Zip/Postal Code, Registration Period, Transfer Period, EPP/Auth Code, etc.) now render in Greek on el locale sites

= 1.5.0 =
* Security: registrant PII in pending public orders (name, email, phone, address, company, EPP code) is now encrypted at rest in post meta using the existing AES-256-CBC + HMAC-SHA256 envelope; legacy plaintext rows keep reading via a transparent fallback
* Security: validated the domain regex on public order submission to accept punycode (ACE-encoded) TLDs such as xn--fiqs8s while continuing to reject malformed inputs
* Housekeeping: added a daily WP-Cron task that prunes `wh4u_api_logs` and `wh4u_notifications` rows older than 30 days; retention is filterable via the `wh4u_log_retention_days` filter
* Housekeeping: removed the orphan `wh4u_public_orders` database table from activation (public orders have always been stored as a custom post type with post meta); the DROP statement in uninstall.php remains for legacy installs
* i18n: regenerated the POT template against the current source and generated JSON translation files (`wp_set_script_translations`) so Greek (and any future locale) covers the block editor InspectorControls panel labels
* i18n: bumped Project-Id-Version in the POT and Greek .po header from 1.0.0 to the current release

= 1.4.2 =
* Security: Turnstile secret key is now encrypted at rest (AES-256-CBC + HMAC-SHA256), matching the treatment of reseller API keys
* Security: encryption key derivation prefers the WH4U_ENCRYPTION_KEY constant, then AUTH_KEY/SECURE_AUTH_KEY salts; auto-generated DB-stored keys are now only kept for legacy installs
* Security: anonymous domain lookup and public pricing queries now require an administrator to explicitly designate a public-lookup reseller (Domains > Settings > General); the previous "first reseller in DB" fallback is removed
* Security: webhook delivery validates the resolved host IP once and pins it via CURLOPT_RESOLVE for the outgoing request, closing the DNS-rebinding window; DNS failures now fail-closed instead of allowing the request
* Security: rate limiter now supports opt-in trusted-proxy configuration (Cloudflare, nginx, X-Forwarded-For leftmost, True-Client-IP) with a declared list of trusted proxy IPs; client-supplied headers are ignored unless both settings are configured
* Privacy: added an explicit "Data stored locally" section to the readme describing order records, public order submissions, API logs, retry queue, rate-limit counters, and encrypted reseller settings

= 1.4.1 =
* Fixed plugin directory name to match text domain and slug (wh4u-domains)
* Removed load_plugin_textdomain call (handled automatically by WordPress.org since WP 4.6)
* Removed Domain Path header (unnecessary for WordPress.org-hosted plugins)
* Moved inline CSS, JS, and link tags in appearance preview to use wp_enqueue_style, wp_enqueue_script, and wp_add_inline_style
* Extracted preview JavaScript into dedicated admin/js/wh4u-preview.js file
* Improved sanitize_settings: added is_array check, applied wp_unslash to full input array
* Added defensive esc_attr escaping on all ternary-output patterns
* Added export-ignore rules to .gitattributes for .gitignore, .gitattributes, and .github
* Fixed Contributors field in readme.txt to match WordPress.org username

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
