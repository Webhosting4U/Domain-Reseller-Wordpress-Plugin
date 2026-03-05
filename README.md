# WH4U Domains <img src="https://webhosting4u.gr/assets/img/wh4u-LogoDark.webp" alt="WebHosting4U" width="120" height="120" />

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-6.2%2B-21759B?logo=wordpress)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php)](https://www.php.net/)
[![Version](https://img.shields.io/badge/version-1.2.0-blue)](https://github.com/Webhosting4U/Domain-Reseller-Wordpress-Plugin/releases)

### Summary

WordPress plugin for domain resellers: search, register, and transfer domains via the DomainsReseller API. Optional redirect to WHMCS, Blesta, ClientExec, or Upmind with the domain pre-filled.

### Description

**WH4U Domains** lets you offer domain registration and transfer from your WordPress site. Visitors search for a domain; available domains can be registered and taken domains can be transferred. Orders are managed in the WordPress admin (approve/reject). The plugin integrates with the **DomainsReseller API** (WebHosting4U). You can use the built-in form or send customers to your billing cart (WHMCS, Blesta, ClientExec, Upmind, or a custom URL) with the chosen domain passed in the link. Supports per-user reseller credentials, retry queue for failed API calls, and translations (Greek included).

---

## Table of contents

- [What this plugin does](#what-this-plugin-does)
- [Features](#features)
- [What you need before you start](#what-you-need-before-you-start)
- [Installation (step by step)](#installation-step-by-step)
- [Configuration (step by step)](#configuration-step-by-step)
- [Putting the domain search on your site](#putting-the-domain-search-on-your-site)
- [Managing orders as an administrator](#managing-orders-as-an-administrator)
- [Third-party service and your data](#third-party-service-and-your-data)
- [Frequently asked questions](#frequently-asked-questions)
- [Changelog](#changelog)
- [License](#license)

---

## What this plugin does

- **Domain search:** Visitors type a domain name on your site and see whether it is available or already taken.
- **Registration requests:** If a domain is available, visitors can submit a request to register it. You (the admin) review and approve before the registration is sent to the API.
- **Transfer requests:** If a domain is taken, visitors can submit a transfer request. Again, you approve before anything is processed.
- **Admin tools:** You get a dashboard to manage orders, check API status, see pricing, and handle failed requests that are retried automatically.

All domain operations go through the **DomainsReseller API** provided by WebHosting4U. You need an account and API credentials to use the plugin.

---

## Features

| Feature | Description |
|--------|-------------|
| **Domain search** | Visitors search via a shortcode or a block in the block editor. |
| **Public registration & transfer** | Guests can submit requests without logging in; you approve them in the admin. |
| **Admin dashboard** | One place to manage orders, API status, credits, and the retry queue. |
| **Reseller support** | Different WordPress users can use different API credentials (multi-reseller). |
| **Retry queue** | Failed API calls are retried automatically with exponential backoff. |
| **Shopping cart redirect** | Optional: send customers to WHMCS, Blesta, ClientExec, Upmind, or a custom cart URL with the domain pre-filled when they click Register or Transfer. |
| **Translations** | Plugin is translation-ready; Greek is included. |

---

## What you need before you start

1. **A WordPress site** where you can install and activate plugins.
2. **DomainsReseller API credentials** from WebHosting4U (API email and API key). Without these, domain search and orders will not work.
3. **WordPress 6.2 or newer** and **PHP 7.4 or newer** on your server.

---

## Installation (step by step)

Follow these steps even if you have never installed a WordPress plugin before.

### 1. Get the plugin files

- Download or clone this repository.
- The main folder should be named something like `Domain-Reseller-Wordpress-Plugin` or `wh4u-domains`. The plugin code must live inside a folder (e.g. `wh4u-domains`) that you will put inside WordPress’s plugins directory.

### 2. Upload the plugin folder to WordPress

- On your server, open the folder: **`wp-content/plugins/`** (this is the “plugins” directory of your WordPress installation).
- Copy the **entire plugin folder** (the one that contains `wh4u-domains.php`) into `wp-content/plugins/`.
- Example: after copying, you should have a path like `wp-content/plugins/wh4u-domains/wh4u-domains.php`.

**If you use an FTP or file manager:** upload the plugin folder so it appears inside `wp-content/plugins/`.

### 3. Activate the plugin

- Log in to your WordPress site as an administrator.
- In the left-hand menu, click **Plugins**.
- Find **WH4U Domains** in the list.
- Click **Activate** under the plugin name.

When the plugin is active, you will see a new item in the left menu: **Domains**. That means the plugin is installed and running.

---

## Configuration (step by step)

After activation, you must enter your API details and a few settings. Do this once before asking visitors to use the domain search.

### 1. Open the Domains settings

- In the WordPress left menu, click **Domains**.
- Click **Settings** (under Domains).

You will see tabs at the top: **General**, **Credentials**, and **Appearance**.

### 2. General tab (administrators only)

- Click the **General** tab.
- **API Base URL:** Usually you can leave the default URL as it is, unless you were given a different one.
- **Registration mode:**
  - **Real-time:** When you approve an order, the plugin tries to register or transfer the domain with the API immediately.
  - **Notification only:** The plugin does not call the API on approval; use this if you prefer to process orders manually elsewhere.
- Click **Save Changes** at the bottom.

### 3. Credentials tab (required)

- Click the **Credentials** tab.
- Enter the **API Email** and **API Key** that you received from WebHosting4U (DomainsReseller). These are required for domain search and orders to work.
- Enter your **default nameservers** (e.g. `ns1.yourhost.com` and `ns2.yourhost.com`). These are used when registering new domains.
- Click **Save Credentials**.

Without valid credentials and nameservers, the domain search will not work and orders cannot be processed.

### 4. Shopping cart redirect (optional)

If you use **WHMCS**, **Blesta**, **ClientExec**, **Upmind**, or another billing cart, you can send customers there with the domain pre-filled when they click **Register** or **Transfer** on the domain search results. Otherwise they use the built-in form on your WordPress site.

- In the **General** tab, scroll to **Shopping Cart Redirect**.
- **Cart Type:** Choose your cart or **None** to keep the built-in form only.

**Preset carts (WHMCS, Blesta, ClientExec, Upmind):**

- Set **Cart Type** to your cart.
- Enter **Cart Base URL** with no trailing slash (e.g. `https://billing.example.com` or `https://yourdomain.com/whmcs`).
- The plugin builds the correct link for each cart:
  - **WHMCS:** `cart.php?a=add&domain=register` or `domain=transfer` with `sld` and `tld`.
  - **Blesta:** `order/main/index/` with `domain` in the query.
  - **ClientExec:** `order.php?step=1&productGroup=2` with `domain`.
  - **Upmind:** base URL with `domain` and `action`.

**Custom cart (other systems):**

- Set **Cart Type** to **Custom URL template**.
- **Register URL Template:** full URL with placeholders, e.g.  
  `https://billing.example.com/cart.php?a=add&domain=register&sld={sld}&tld={tld}`
- **Transfer URL Template:** full URL with placeholders, e.g.  
  `https://billing.example.com/cart.php?a=add&domain=transfer&sld={sld}&tld={tld}`
- Placeholders: `{domain}` (full domain), `{sld}` (name part), `{tld}` (extension). Use only HTTPS URLs.

### 5. Appearance tab (optional)

- Click the **Appearance** tab if you want to change how the domain search form looks (e.g. colors, layout). You can leave defaults if you prefer.

---

## Putting the domain search on your site

You can show the domain search form on any page in two ways.

### Option A: Using the block editor (recommended if you use blocks)

1. Edit or create a page: **Pages → Add New** (or edit an existing page).
2. Click the **+** button to add a block.
3. Search for **“Domain Lookup”** or **“WH4U”**.
4. Insert the **Domain Lookup** block.
5. Publish or update the page.

Visitors will see the search box and results on that page.

### Option B: Using the shortcode (classic editor or any text)

1. Edit the page or post where you want the domain search.
2. Type exactly this in the content:  
   **`[wh4u_domain_lookup]`**
3. Save or update the page.

When the page is viewed, the shortcode is replaced by the domain search form. You can use this shortcode on any page or post.

---

## Managing orders as an administrator

- In the left menu, go to **Domains**.
- Use **Dashboard** for an overview, **Orders** (or **Public Orders**) to see requests from visitors.
- For each public order you can **Approve** or **Reject**. Approved orders are processed according to the **Registration mode** you set under Domains → Settings → General.
- **Domains**, **Pricing**, **Credits**, **History**, and **Queue** give you more tools to manage domains and failed retries.

---

## Third-party service and your data

This plugin talks to the **DomainsReseller API** run by WebHosting4U. When you or your visitors use the plugin, some data is sent to that service.

| When | What is sent |
|------|-----------------------------|
| Domain search | The domain name being checked. |
| Registration | Registrant contact (name, email, phone, address, company, country), domain name, registration period, nameservers, add-ons. |
| Transfer | Domain name, registration period, EPP code. |
| TLD/pricing | Requests for available TLDs and prices (results are cached for 12 hours). |

**Service details:**

- **Provider:** [WebHosting4U](https://webhosting4u.gr)
- **Terms of Service:** [webhosting4u.gr/terms-of-service.php](https://webhosting4u.gr/terms-of-service.php)
- **Privacy Policy:** [webhosting4u.gr/privacy-policy.php](https://webhosting4u.gr/privacy-policy.php)

---

## Frequently asked questions

### Do I need a DomainsReseller account?

Yes. You need API credentials (email and key) from WebHosting4U. Without them, the domain search and orders will not work.

### Can visitors register domains without logging in?

Yes. They can submit registration and transfer requests without an account. Those appear as pending orders. You must approve each order in the admin before it is sent to the API.

### What if the API is temporarily down?

Orders that fail because of timeouts or server errors are put in a retry queue. The plugin retries them automatically (using WordPress’s built-in cron) with increasing delays. You can monitor the queue under Domains in the admin.

### Can I translate the plugin?

Yes. The plugin is translation-ready. Greek is included. You can add more languages with `.po`/`.mo` files in the `languages/` folder.

---

## Changelog

### 1.2.0

- **Shopping cart redirect:** Optional redirect to WHMCS, Blesta, ClientExec, Upmind, or a custom URL when visitors click Register or Transfer. Domain (or sld/tld) is passed in the URL so the cart opens with the chosen domain. Configure under Domains > Settings > General (Shopping Cart Redirect).

### 1.1.0

- Public domain registration and transfer from the frontend form.
- Admin approve/reject workflow for public orders.
- Automatic API processing when an order is approved.
- Retry queue for failed API calls.
- Greek translation.

### 1.0.0

- Initial release.
- Domain search, registration, and transfer.
- Admin dashboard with API status and credits.
- Per-user reseller credentials with encrypted storage.
- TLD pricing display.
- Rate limiting and logging.

---

## License

This project is licensed under the **GPL v2 or later**. See [LICENSE](LICENSE) and [gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html) for details.
