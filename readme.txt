=== Newspack Lite Site ===
Contributors: automattic
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: trunk
License: GPL-3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Tags: lite, text-only, low bandwidth, emergency, newspack

A text-only version of your site that loads fast on slow connections, during traffic spikes and in emergencies.

== Description ==

Lite Site creates a stripped-down, text-only version of your site. A typical article drops from about 3 MB to under 50 KB, so your reporting stays readable when bandwidth fails: during disasters, network outages, breaking-news traffic spikes, or on satellite, dial-up and metered connections.

Lite Site pages are plain HTML with inline styles. There is no theme, no block runtime and no tracking script, and images load only when a reader taps to see them.

Readers reach the Lite Site at a path on your domain, such as `/lite/` or `/text/`.

= Features =

* Serves your posts and pages, and a newest-first archive, as text-only pages.
* Keeps headings, paragraphs, lists, blockquotes, links and basic formatting, and removes everything else.
* Turns each image into a placeholder with its alt text and caption, which loads the image when tapped.
* Links each Lite Site article to the full version, and keeps Lite Site pages out of search results.
* Shows bylines and publication dates, including co-authors.
* Lets you choose which categories and tags appear, how many posts each page lists and which path the Lite Site uses.
* Matches your brand with a primary color, a font, footer text and custom CSS.
* Imports articles from RSS feeds, so a backup WordPress site can publish coverage from another CMS.
* Shows an offline page to readers whose browser has no connection, on sites using the PWA plugin.

= About Newspack =

Lite Site is part of Newspack, a suite of tools that helps news organizations publish and grow on WordPress. It is available to all WordPress users through the [Emergency Mode for News](https://emergencymode.news/) initiative, funded by [Press Forward](https://www.pressforward.news/). Learn more at [newspack.com](https://newspack.com/).

== Installation ==

1. Upload and activate the plugin.
2. Go to Lite Site > Settings.
3. Check Enable Lite Site and click Save.
4. Visit `/lite/` on your site.
5. Link to the Lite Site from your site's header or footer, so readers can find it before they need it.

== Frequently Asked Questions ==

= Will this affect my SEO? =

No. Each Lite Site page points search engines to the full version as the original, and asks them not to index the Lite Site page.

= Can I use a different path than /lite/? =

Yes. Change the Lite Site path in Lite Site > Settings, for example to `/text/` or `/fast/`.

= Which content types appear on the Lite Site? =

Posts, pages and imported RSS entries. Developers can add other post types with the `newspack_lite_site_supported_post_types` filter.

= Can I use this with a caching plugin? =

Yes. Lite Site caches each article for 15 minutes and clears it when the article changes. A page caching plugin adds a second layer.

= What happens if I deactivate the plugin? =

Lite Site addresses stop working. The rest of your site is not affected.

== External services ==

Lite Site connects to these services only when you set them up:

* **Google Analytics.** When you add a GA4 Measurement ID in Lite Site > Settings, Lite Site pages load Google's analytics script, which sends page views to Google. Google's [terms of service](https://marketingplatform.google.com/about/analytics/terms/us/) and [privacy policy](https://policies.google.com/privacy) apply.
* **Font provider.** When you add a font import URL, Lite Site pages load that stylesheet from the provider you chose, such as Google Fonts or Adobe Fonts, and that provider's terms apply.
* **RSS feeds.** When you add a feed under Lite Site > RSS, your site fetches that feed and its images on the schedule you set.

== Changelog ==

See https://github.com/Automattic/newspack-lite-site/releases
