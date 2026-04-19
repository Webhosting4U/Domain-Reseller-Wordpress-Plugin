# WH4U Domains

**WH4U Domains** is a WordPress plugin that turns your site into a front door for domain search, registration, and transfers. It talks to the [DomainsReseller API](https://webhosting4u.gr/) from [WebHosting4U](https://webhosting4u.gr/) so visitors get live availability and a workflow you control in the admin.

[![Stable tag](https://img.shields.io/badge/stable-1.5.5-blue)](https://github.com/Webhosting4U/Domain-Reseller-Wordpress-Plugin)
[![WordPress](https://img.shields.io/badge/WordPress-6.2%2B-21759b?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--3.0-green)](LICENSE)

---

## Why use it

- **Search** — Real-time domain availability from the DomainsReseller API.
- **Sell or hand off** — Built-in public registration and transfer requests, or redirect to WHMCS, Blesta, ClientExec, Upmind, or a custom cart URL with the domain prefilled.
- **Operate safely** — Optional [Cloudflare Turnstile](https://www.cloudflare.com/products/turnstile/) on public forms, rate limiting, logging, and a **retry queue** with exponential backoff when the API is temporarily unhappy.
- **Run as a reseller** — Encrypted per-user API credentials for multi-reseller setups.
- **Fit your theme** — Shortcode and Gutenberg block, appearance options, and theme token detection via `theme.json` where supported.

---

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 6.2 or newer |
| PHP | 7.4 or newer |
| DomainsReseller API | Valid email + API key from WebHosting4U |

---

## Quick start

1. Copy the plugin folder into `wp-content/plugins/` (the folder name should be `wh4u-domains` as shipped).
2. Activate **WH4U Domains** under **Plugins**.
3. Open **Domains → Settings → Credentials** and enter your DomainsReseller API email, API key, and default nameservers.
4. Under **Domains → Settings → General**, pick **Real-time** or **Notification-only** registration behaviour and configure **Shopping Cart Redirect** if you use an external billing system.
5. Place the lookup on a page:
   - **Shortcode:** `[wh4u_domain_lookup]`
   - **Block:** add the **Domain Lookup** block in the editor.

Visitors can search immediately. Public registration and transfer requests land as **pending** until an administrator approves or rejects them.

---

## What gets embedded

| Method | Usage |
|--------|--------|
| Shortcode | `[wh4u_domain_lookup]` |
| Block | *Domain Lookup* (Gutenberg) |

Appearance (search bar, chips, suggestions, and related options) is configurable under **Domains → Settings** where your version exposes those screens.

---

## Shopping cart redirect

If you already bill in WHMCS, Blesta, ClientExec, Upmind, or another system, you can send **Register** / **Transfer** clicks to your cart with placeholders such as `{domain}`, `{sld}`, and `{tld}`. Configure this under **Domains → Settings → General** → **Shopping Cart Redirect**.

---

## Privacy and external services

The plugin must reach **WebHosting4U’s DomainsReseller API** for lookups, orders, and TLD lists (TLD responses are cached locally for about **12 hours**). TLD-level pricing is browsed through your WHMCS storefront (the dashboard "View Pricing" tile links out to it) rather than rendered inside the plugin.

**Optional:** When Turnstile is enabled, the browser loads Cloudflare’s Turnstile script and the server verifies tokens before accepting public orders.

For official policies and company links:

- [WebHosting4U](https://webhosting4u.gr/)
- [Terms of Service](https://webhosting4u.gr/terms-of-service.php)
- [Privacy Policy](https://webhosting4u.gr/privacy-policy.php)

Turnstile (if used): [Cloudflare Turnstile](https://www.cloudflare.com/products/turnstile/) — [Terms](https://www.cloudflare.com/terms/) — [Privacy](https://www.cloudflare.com/privacypolicy/).

---

## Frequently asked questions

**Do I need a DomainsReseller account?**  
Yes. Without valid API credentials, domain search will not work.

**Can guests submit orders?**  
Yes. Anonymous visitors can submit registration and transfer requests; they stay pending until an admin approves them.

**What if the API is down briefly?**  
Failed calls can be queued and retried with exponential backoff via WP-Cron.

**Is it translatable?**  
Yes. Strings are internationalised; a Greek translation ships with the plugin. Add or override languages with `.po` / `.mo` files under `languages/`.

---

## Repository layout (high level)

| Path | Role |
|------|------|
| `wh4u-domains.php` | Bootstrap, version constants, hooks |
| `admin/` | Dashboard, settings, appearance, orders |
| `public/` | Front-end assets and public-facing logic |
| `rest-api/` | REST routes for domains, orders, credits, queue, public orders |
| `blocks/domain-lookup/` | Gutenberg block |
| `languages/` | Translation files |

---

## Changelog

Release notes for each version live in [`readme.txt`](readme.txt) (WordPress.org–style changelog). This file stays in sync with plugin releases.

---

## License

Distributed under the **GNU General Public License**; full text in [LICENSE](LICENSE).

---

## Credits

Developed by **[WebHosting4U](https://webhosting4u.gr/)**. Issues and contributions are welcome via [GitHub](https://github.com/Webhosting4U/Domain-Reseller-Wordpress-Plugin).
