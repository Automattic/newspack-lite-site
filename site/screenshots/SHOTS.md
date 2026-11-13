# Screenshot list

Every screenshot in `site/public/screenshots/`, what it shows, and the command that retakes it. See [README.md](README.md) for setting up the screenshot site.

Run the commands from `site/`, after setting the site address once:

```bash
export SHOT_SITE=https://screenshots.local
```

Each command opens Chrome with the page ready. Press Enter to capture, or add `--now` to capture without opening a window.

## Lite Site as it is right after activation

Seed with `fresh` first: Lite Site is turned off and there are no RSS feeds.

```bash
wp eval-file wp-content/plugins/newspack-lite-site/site/screenshots/seed.php fresh
```

- `getting-started/install-the-plugin-1.webp`: Plugins > Add Plugin with Upload Plugin open; the upload form is spotlighted.<br>`npm run shot -- getting-started/install-the-plugin-1 --url 'wp-admin/plugin-install.php?tab=upload' --spotlight '.upload-plugin .wp-upload-form'`
- `getting-started/install-the-plugin-2.webp`: Plugins screen with Newspack Lite Site active; its row is spotlighted.<br>`npm run shot -- getting-started/install-the-plugin-2 --url 'wp-admin/plugins.php?plugin_status=active' --spotlight 'tr[data-slug="newspack-lite-site"]'`
- `getting-started/install-the-plugin-3.webp`: The Lite Site menu in the admin sidebar, with RSS and Settings, spotlighted.<br>`npm run shot -- getting-started/install-the-plugin-3 --url 'wp-admin/admin.php?page=newspack-lite-site' --spotlight '#toplevel_page_newspack-lite-site'`
- `getting-started/turn-on-lite-site-1.webp`: Lite Site > Settings > General, with Enable Lite Site checked and spotlighted.<br>`npm run shot -- getting-started/turn-on-lite-site-1 --url 'wp-admin/admin.php?page=newspack-lite-site' --click 'internal:label="Enable Lite Site"s' --spotlight '.components-checkbox-control:has-text("Enable Lite Site")'`
- `getting-started/turn-on-lite-site-2.webp`: The same screen, with the Save button spotlighted.<br>`npm run shot -- getting-started/turn-on-lite-site-2 --url 'wp-admin/admin.php?page=newspack-lite-site' --click 'internal:label="Enable Lite Site"s' --spotlight '.newspack-page__header-actions button:has-text("Save")'`
- `getting-started/add-an-rss-feed-1.webp`: Lite Site > RSS before any feed is added: the No RSS feeds yet empty state, with Add RSS Feed spotlighted.<br>`npm run shot -- getting-started/add-an-rss-feed-1 --url 'wp-admin/admin.php?page=newspack-lite-site-rss' --spotlight 'button:has-text("Add RSS Feed")'`
- `getting-started/add-an-rss-feed-2.webp`: The Add RSS Feed drawer filled in with the county alerts feed, Every 10 Minutes and Dana Okafor; the Add RSS Feed button is spotlighted.<br>`npm run shot -- getting-started/add-an-rss-feed-2 --url 'wp-admin/admin.php?page=newspack-lite-site-rss' --click 'button:has-text("Add RSS Feed")' --fill 'internal:label="RSS Feed URL"s' --value https://hallowaycounty.example/alerts/feed/ --fill 'internal:label="Frequency"s' --value 'Every 10 Minutes' --fill 'internal:label="Author"s' --value 'Dana Okafor' --click 'text=Imported entries are published under this author.' --spotlight '.newspack-drawer button:has-text("Add RSS Feed")'`

## The full demo

Seed without `fresh` first: Lite Site is turned on, with RSS feeds and entries.

```bash
wp eval-file wp-content/plugins/newspack-lite-site/site/screenshots/seed.php
```

- `getting-started/turn-on-lite-site-3.webp`: Lite Site > Settings > General after saving, with the View Lite Site button spotlighted.<br>`npm run shot -- getting-started/turn-on-lite-site-3 --url 'wp-admin/admin.php?page=newspack-lite-site' --spotlight '.newspack-lite-path-field__link a'`
- `getting-started/turn-on-lite-site-4.webp`: The Lite Site homepage at /lite/, as a signed-out reader sees it.<br>`npm run shot -- getting-started/turn-on-lite-site-4 --url lite/ --logged-out`
- `getting-started/add-an-rss-feed-3.webp`: Lite Site > RSS > Feeds with the four demo feeds; the county alerts feed is spotlighted.<br>`npm run shot -- getting-started/add-an-rss-feed-3 --url 'wp-admin/admin.php?page=newspack-lite-site-rss#/feeds' --spotlight 'tr:has-text("hallowaycounty.example")'`
- `getting-started/add-an-rss-feed-4.webp`: Lite Site > RSS > Entries, listing the entries the feeds imported.<br>`npm run shot -- getting-started/add-an-rss-feed-4 --url 'wp-admin/admin.php?page=newspack-lite-site-rss#/entries'`
- `guides/settings-1.webp`: Settings > General: the Lite Site Path field spotlighted.<br>`npm run shot -- guides/settings-1 --url 'wp-admin/admin.php?page=newspack-lite-site' --spotlight 'div:has(> .newspack-lite-path-field__input)'`
- `guides/settings-2.webp`: Settings > General: the Posts per Page field spotlighted.<br>`npm run shot -- guides/settings-2 --url 'wp-admin/admin.php?page=newspack-lite-site' --spotlight '.dataforms-layouts-regular__field:has-text("Posts per Page")'`
- `guides/settings-3.webp`: Settings > General: the Content filters, with the Categories list open and spotlighted.<br>`npm run shot -- guides/settings-3 --url 'wp-admin/admin.php?page=newspack-lite-site' --scroll 'h2:has-text("Content")' --click 'internal:label="Categories"s' --spotlight '.components-form-token-field:has(input:focus)'`
- `guides/settings-4.webp`: Settings > General: Links and Footer, with the footer HTML spotlighted.<br>`npm run shot -- guides/settings-4 --url 'wp-admin/admin.php?page=newspack-lite-site' --scroll 'h2:has-text("Links and Footer")' --spotlight '.dataforms-layouts-regular__field:has-text("Footer HTML")'`
- `guides/settings-5.webp`: Settings > General: Analytics, with the GA4 Measurement ID field spotlighted.<br>`npm run shot -- guides/settings-5 --url 'wp-admin/admin.php?page=newspack-lite-site' --scroll 'h2:has-text("Analytics")' --spotlight '.dataforms-layouts-regular__field:has-text("GA4 Measurement ID")'`
- `guides/appearance-1.webp`: Settings > Appearance: Colors, with the Primary Color picker spotlighted.<br>`npm run shot -- guides/appearance-1 --url 'wp-admin/admin.php?page=newspack-lite-site#/appearance' --spotlight '.dataforms-layouts-regular__field:has-text("Primary Color")'`
- `guides/appearance-2.webp`: Settings > Appearance: Typography, with the font embed URL and Body Font fields spotlighted.<br>`npm run shot -- guides/appearance-2 --url 'wp-admin/admin.php?page=newspack-lite-site#/appearance' --spotlight '.newspack-grid:has(h2:has-text("Typography")) > div:nth-child(2)'`
- `guides/appearance-3.webp`: Settings > Appearance: Advanced, with the Custom CSS field spotlighted.<br>`npm run shot -- guides/appearance-3 --url 'wp-admin/admin.php?page=newspack-lite-site#/appearance' --scroll 'h2:has-text("Advanced")' --spotlight '.dataforms-layouts-regular__field:has-text("Custom CSS")'`
- `guides/rss-feeds-1.webp`: RSS > Feeds: the Error status of the Kettle Watershed Institute feed spotlighted.<br>`npm run shot -- guides/rss-feeds-1 --url 'wp-admin/admin.php?page=newspack-lite-site-rss#/feeds' --spotlight 'tr:has-text("kettlewatershed.example") .newspack-status-badge'`
- `guides/rss-feeds-2.webp`: RSS > Feeds: the tooltip on the Error status, with the error message.<br>`npm run shot -- guides/rss-feeds-2 --url 'wp-admin/admin.php?page=newspack-lite-site-rss#/feeds' --hover 'tr:has-text("kettlewatershed.example") .newspack-status-badge > *'`
- `guides/rss-feeds-3.webp`: The Edit RSS Feed drawer for the failing feed, with the error and Retry Now spotlighted.<br>`npm run shot -- guides/rss-feeds-3 --url 'wp-admin/admin.php?page=newspack-lite-site-rss#/feeds' --click 'tr:has-text("kettlewatershed.example") button:has-text("Edit")' --spotlight '.newspack-drawer .components-notice'`
- `guides/rss-feeds-4.webp`: The Edit RSS Feed drawer for the county alerts feed, with Status spotlighted.<br>`npm run shot -- guides/rss-feeds-4 --url 'wp-admin/admin.php?page=newspack-lite-site-rss#/feeds' --click 'tr:has-text("hallowaycounty.example") button:has-text("Edit")' --spotlight '.newspack-drawer .dataforms-layouts-regular__field:has(.components-toggle-group-control)'`
- `guides/rss-feeds-5.webp`: The Delete RSS Feed confirmation for the Riverbend Transit feed.<br>`npm run shot -- guides/rss-feeds-5 --url 'wp-admin/admin.php?page=newspack-lite-site-rss#/feeds' --click 'tr:has-text("riverbendtransit.example") button:has-text("Delete")'`
- `guides/rss-entries-1.webp`: RSS > Entries: the newest entry spotlighted, with its feed under the title.<br>`npm run shot -- guides/rss-entries-1 --url 'wp-admin/admin.php?page=newspack-lite-site-rss#/entries' --spotlight 'tr:has-text("Kettle River flood warning remains in effect")'`
- `guides/rss-entries-2.webp`: RSS > Entries: the actions menu of an entry open, with View on Lite Site and View Original.<br>`npm run shot -- guides/rss-entries-2 --url 'wp-admin/admin.php?page=newspack-lite-site-rss#/entries' --click 'tr:has-text("Kettle River flood warning remains in effect") button[aria-label="Actions"]' --spotlight '[role="menu"]:has-text("View Original")'`
- `guides/rss-entries-3.webp`: An imported RSS entry on the Lite Site, as a signed-out reader sees it.<br>`npm run shot -- guides/rss-entries-3 --url lite/rss-entry/kettle-river-flood-warning-remains-in-effect/ --logged-out`
- `guides/lite-homepage-1.webp`: The Lite Site homepage with the sticky post, in bold at the top, spotlighted.<br>`npm run shot -- guides/lite-homepage-1 --url lite/ --logged-out --spotlight '.post-list li:first-child'`
- `guides/lite-homepage-2.webp`: The bottom of the Lite Site homepage: the page links spotlighted, above the footer.<br>`npm run shot -- guides/lite-homepage-2 --url lite/ --logged-out --scroll .site-footer --spotlight nav.pagination`
- `guides/lite-homepage-3.webp`: The Lite Site homepage with the View full site link spotlighted.<br>`npm run shot -- guides/lite-homepage-3 --url lite/ --logged-out --spotlight 'header.back a'`
- `guides/lite-articles-1.webp`: A Lite Site article: the links back to the Lite Site homepage and to the full version spotlighted.<br>`npm run shot -- guides/lite-articles-1 --url lite/ --logged-out --click 'text=Kettle River flood:' --spotlight header.back`
- `guides/lite-articles-2.webp`: A Lite Site article: the image placeholder, with its alt text, caption, photo credit and Load image button, spotlighted.<br>`npm run shot -- guides/lite-articles-2 --url lite/ --logged-out --click 'text=Kettle River flood:' --scroll .lite-image-placeholder --spotlight .lite-image-placeholder`
- `guides/lite-articles-3.webp`: The same article after Load image: the photo of flooded fields by the greenway, with its caption and credit, spotlighted.<br>`npm run shot -- guides/lite-articles-3 --url lite/ --logged-out --click 'text=Kettle River flood:' --click .lite-image-load-btn --scroll '.content figure' --spotlight '.content figure'`
- `guides/lite-articles-4.webp`: A Lite Site article: an external link, marked with an arrow, spotlighted.<br>`npm run shot -- guides/lite-articles-4 --url lite/ --logged-out --click 'text=Kettle River flood:' --scroll a.lite-site-external --spotlight a.lite-site-external`
- `guides/lite-articles-5.webp`: The bottom of a Lite Site article: the footer spotlighted.<br>`npm run shot -- guides/lite-articles-5 --url lite/ --logged-out --click 'text=Kettle River flood:' --scroll .site-footer --spotlight .site-footer`
- `guides/lite-articles-6.webp`: The full version of the same article on the main site, in the Newspack theme, which the Lite Site links to: its standfirst, Marcus Reilly's monogram byline and the top of the featured photo.<br>`npm run shot -- guides/lite-articles-6 --url lite/ --logged-out --click 'text=Kettle River flood:' --click 'text=View full version'`
- `guides/lite-articles-7.webp`: The View Lite Site link in the admin bar, spotlighted on Lite Site > Settings.<br>`npm run shot -- guides/lite-articles-7 --url 'wp-admin/admin.php?page=newspack-lite-site' --spotlight '#wp-admin-bar-view-lite-site'`

## A feed that recovers

On the full demo, let Retry Now work for the failing feed first. Rerun the seed afterward to bring its error back.

```bash
wp option update newspack_ls_screenshots_retry_works 1
```

- `guides/rss-feeds-6.webp`: Retry Now on the failing feed after it recovers: the drawer has closed its error and the Up to date confirmation shows.<br>`npm run shot -- guides/rss-feeds-6 --url 'wp-admin/admin.php?page=newspack-lite-site-rss#/feeds' --click 'tr:has-text("kettlewatershed.example") button:has-text("Edit")' --click 'button:has-text("Retry Now")' --spotlight .newspack-lite-snackbar-container`
