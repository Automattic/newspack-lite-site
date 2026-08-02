# Lite Sites, from [Newspack](https://newspack.com/) and [Emergency Mode for News](https://emergencymode.news/)

| Detail | Value |
| --- | --- |
| **Stable tag** | [(see package.json)](package.json#L3) |
| **Requires at least** | 6.4 |
| **Tested up to** | 7.0 |
| **Requires PHP** | 7.4 |
| **License** | [GPLv3 or later](LICENSE.md) |
| **Tags** | 🚨, 📻, 📰, ⚡️, 📶, 📡, 🛜, text-only, lite-site, wordpress, newspack, bandwidth, accessibility, emergency, offline, rss, performance |
| **Contributors** | @automattic, @rtcamp &amp; @R1shabh-Gupta, @miguelpeixe, @scottklein, @tiffehr |

## Description

Lite Sites is a WordPress plugin developed by Newspack and released to all WordPress users through the Emergency Mode for News initiative. It creates a stripped-down, **text-only version of your website** that loads rapidly even when bandwidth is severely compromised—making critical information accessible during network failures, disasters and other emergencies.

Think of it as the digital equivalent of keeping a transistor radio for emergencies: 📻 **it works when everything else fails**.

Its alternate site ships as basic HTML document with inline CSS — no theme, no Blocks runtime, no tracking scripts and no images _unless_ the reader taps to load one.

With some strategy tweaks, it can also help address:

* breaking-news traffic spikes that overwhelm hosting
* satellite or dial-up connections
* readers on a metered plan
* low-vision-optimized renderings and other accessibilty improvements

Lite Sites publishes via a domain path like `/lite/<normal url>`, `/text/<normal url>` or other domain variations you configure. On WordPress the domain path you choose will be added automatically. However, you could mask that pattern via any subdomain or other pattern you prefer via your hosting provider's records.

## Reports or Contributions

### Reporting Security Issues

To disclose a security issue to our team, [please submit a report via HackerOne here](https://hackerone.com/automattic/).

### Contributing to Newspack

If you have a patch or have stumbled upon an issue with the Newspack plugin/theme, you can contribute this back to the code. [Please read our contributor guidelines for more information on how you can do this](https://github.com/Automattic/newspack-plugin/blob/trunk/.github/CONTRIBUTING.md).

## Background

Lite Sites began as a [Newspack-hosted feature](https://help.newspack.com/lite-site/) and available to Newspack Publishers who needed text-only options during disasters.

This repository is the **rebuilt, public version**. It was upgraded and opened to all WordPress users through the [**Emergency Mode for News**](https://emergencymode.news/) grant, made possible by an infrastructure grant from [Press Forward](https://www.pressforward.news/infrastructure25/), a national initiative to reimagine local news.

The [Emergency Mode for News](https://emergencymode.news/) program provides free resources, training and open-source tools including this Lite Site plugin and a [modern liveblogging plugin](https://github.com/Automattic/newspack-rolling-coverage). Project goals, technical upgrades and the rationale for taking it public are described at [emergencymode.news](https://emergencymode.news/tools/#lite-sites).

**What changed in the public version:**

* Packaged as a standalone WordPress plugin
* A new **RSS Feed Import** system, so a Lite Site can aggregate and republish coverage from partner newsrooms during an emergency
* A React-based admin (built on `@wordpress/dataviews`) replacing the original settings screen
* Appearance controls (brand color, fonts, footer, custom CSS) so a Lite Site still reads as yours
* An **offline page** for sites running a service worker via the PWA plugin

## Features

### Text-only rendering

* Serves every eligible Post and Page, and your reverse-cron full archive as a text-only site alternative
* Simplifies standard WordPress content via a strict allowlist of elements — headings, paragraphs, lists, blockquotes, links and basic emphasis
* Strips `<script>` tags and HTML comments entirely
* Converts every `<figure>` into a **tap-to-load placeholder** showing the alt text and caption — images or interactivity downloads only on dircet reader action
* Each lite page links back to the full-featured version and declares it as `rel="canonical"`, with `noindex, follow` so the lite version never competes with the original in search
* Rendered single pages are cached in a transient for 15 minutes and invalidated when the published Post or Page is saved

### Content Controls

Choose the categories and tags that appear on the Lite Site, with optional automatic inclusion of subcategories for complex taxnonomies

* Include/exclude specific categories and tags
* Configurable pagination, defaulting to your WordPress **Settings → Reading** value
* Configurable URL base, so `/lite` can become `/text`, `/fast` or anything else
* Control what appears on the Lite Site — filtered posts are hidden from both the archive and individual URLs

### RSS Feed Import

If you normally do not publish on WordPress and a parallel CMS to your own is a challenge, you can provide your own current RSS feed to the Lite Sites pulgin. If you do, WordPress will import the specified content and create WordPress parallel Posts, which can then power Lite Sites. You can continue to use your own CMS and know it is dual-publishing to a simple WordPress backup mechanism. 

#### Details

* Add any number of RSS feeds, each with its own import interval (every 5 minutes through weekly) and assigned WordPress author
* Pause, resume and delete feeds from the admin — each feed shows its last run, last result and next scheduled run
* Imported items become `nls_rss_entry` posts, de-duplicated by GUID so re-runs never create duplicates
* Featured images are sideloaded from `media:thumbnail` or image enclosures
* Hardened against SSRF: every outbound URL — feed and image alike — is validated against private, reserved, and link-local address ranges, with redirects capped at 2 and responses capped at 3 MB. Imports are limited to 50 new items per run and guarded by a per-feed lock

Feed imports run on WP-Cron. If your site defines `DISABLE_WP_CRON`, the admin will tell you, and you'll need a system cron calling `wp-cron.php`.

### Appearance

Brand color (or automatic detection from your theme), a font import URL (Google Fonts and similar), body font family, footer HTML, custom CSS, and a toggle for opening external links in a new tab. An optional GA4 Measurement ID is the only third-party script the plugin will ever add, and only if you supply one.

### Liveblogs and offline

* Posts flagged as liveblogs render their entries in reverse-chronological order with a **Live** or **Archived** status badge
* If the [PWA plugin](https://wordpress.org/plugins/pwa/) is active, the plugin supplies a matching text-only offline page through the `offline_template` filter

---

## Installation

**Requirements:** WordPress 6.2 or later, PHP 7.4 or later (developed and tested against PHP 8.3). No other plugins or themes are required.

### Install from a ZIP — **not yet available**

> **Not yet supported.** Ready-to-upload release ZIPs are planned for this repository but are not published yet. The steps below are drafted for when they are
>
> 1. Download the latest `newspack-lite-site.zip` from the [Releases](https://github.com/Automattic/newspack-lite-site/releases) page
> 1. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the ZIP, and click **Install Now**
> 1. Click **Activate**

Until then, use the source install below.

### Install from source

```bash
git clone https://github.com/Automattic/newspack-lite-site.git
cd newspack-lite-site
composer install --no-dev   # required: the plugin loads vendor/autoload.php
npm ci && npm run build     # required: builds the admin UI into dist/
```

Copy or symlink the resulting directory into `wp-content/plugins/newspack-lite-site`, then activate it from **Plugins**.

To produce an installable ZIP yourself:

```bash
npm run release:archive     # writes release/newspack-lite-site.zip
```

### Set it up

1. Go to **Lite Site → Settings** in the WordPress admin
1. Turn on **Enable Lite Site** and save
   * (Saving flushes rewrite rules automatically; if `/lite` 404s, re-save your permalinks under **Settings → Permalinks**)
1. Optionally set the URL base, category and tag filters, posts per page, and appearance options
1. Visit `https://yoursite.com/lite` — or use the **View Lite Site** link in the admin toolbar
1. Optionally, add feeds under **Lite Site → RSS Feed Import**

Link to `/lite` from your main site's header or footer so readers can find it before they need it.

---

## For developers

### Extension points

| Hook | Type | Purpose |
| --- | --- | --- |
| `newspack_lite_site_supported_post_types` | filter | Post types eligible for lite rendering. Defaults to `post`, `page`, `nls_rss_entry`. |
| `newspack_lite_site_post_content` | filter | The lite content pipeline, mirroring `the_content` without third-party callbacks. |
| `newspack_lite_site_single_after_footer` | action | Fires after the footer on a lite single page. Receives the `WP_Post`. |
| `newspack_lite_site_offline_after_footer` | action | Fires after the footer on the offline page. |

Settings live in the `newspack_lite_site_settings` option and are exposed through `/wp/v2/settings`. Feeds are managed through `newspack-lite-site/v1/rss-feeds`.

### Local development

```bash
npm run watch       # rebuild the admin UI on change
npm run lint        # JS + SCSS
npm run lint:php    # PHPCS (WordPress + VIP standards)
npm run test:php    # PHPUnit — see bin/install-wp-tests.sh for the test DB setup
```

---

## License

Built by [Automattic](https://automattic.com/) for [Newspack](https://newspack.com/), with support from the [Emergency Mode for News](https://emergencymode.news/) grant and [Press Forward](https://www.pressforward.news/infrastructure25/). This work is licensed under [GNU General Public License v3 (or later)](LICENSE.md).
