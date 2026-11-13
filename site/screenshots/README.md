# Docs screenshots

The screenshots in `site/public/screenshots/` all show the same demo site, "The Riverbend Gazette", during the Kettle River flood, taken at the same size and settings. This folder lets anyone retake one so it matches the rest:

- `seed.php` turns a local WordPress site into that demo site, and resets it when you run it again.
- `shot.mjs` (run as `npm run shot`) takes one screenshot at the house settings, with an optional spotlight, and writes the WebP into `site/public/screenshots/`.
- [`SHOTS.md`](SHOTS.md) lists every screenshot, what it shows and the exact command that retakes it.

When you change a screen publishers see, retake only the screenshots that show it.

## Set up a screenshot site

Use a local WordPress site set aside for screenshots, not the site you develop on. Any kind of local site works (Docker, Local, Studio, wp-env) as long as you can run WP-CLI against it. It has to be a single site; the seed refuses multisite networks.

The site needs:

- The [Newspack plugin](https://github.com/Automattic/newspack-plugin/releases), active. The admin menu shows whatever plugins the site has active; the existing screenshots have Newspack, Newspack Blocks and Newspack Campaigns active, and nothing else besides Lite Site.
- The classic [Newspack theme](https://github.com/Automattic/newspack-theme/releases), installed. The seed activates it. The Riverbend Gazette runs on it, not on the Newspack Block Theme.
- Audience Management turned on in the Newspack plugin, for the Sign In button in the theme's header. The seed doesn't change it.
- A git checkout of Lite Site, active, with its assets built (`composer install` and `npm ci && npm run build` in the plugin folder). The plugin zip doesn't include this folder.
- Pretty permalinks. Lite Site doesn't work with Plain permalinks, and the seed refuses them.
- No posts, categories, RSS feeds, RSS entries or users of its own besides the administrator. Delete WordPress's "Hello world!" post and any sample posts, categories and users your setup tool created: the Lite Site homepage lists every published post and RSS entry, the Categories fields in the Lite Site settings list every category, and the Author menu for RSS feeds lists every account that can publish. The seed warns when it finds posts, categories or accounts the demo doesn't use, and refuses to run while the site has RSS feeds of its own.
- An administrator with a neutral name, such as "Admin". The account stays on the site, and appears in the Author menu for RSS feeds.
- An environment type of `local` or `development`. Set it in `wp-config.php` with `define( 'WP_ENVIRONMENT_TYPE', 'local' );`. WordPress assumes `production` when it isn't set, which is the case on many Docker images and Studio sites, and the seed refuses those unless you add `force` to the command.

On your computer you need Node 24 and [Google Chrome](https://www.google.com/chrome/).

## Seed the demo content

From the WordPress folder, point WP-CLI at `seed.php` in your checkout:

```bash
wp eval-file wp-content/plugins/newspack-lite-site/site/screenshots/seed.php
```

Before it changes anything, the seed checks that:

- The site is a single site with an environment type of `local` or `development` (or you added `force`).
- The Newspack plugin and Lite Site are active, and the active Lite Site is the checkout this `seed.php` belongs to.
- The Newspack theme is installed and can run on the site's versions of WordPress and PHP.
- The site uses pretty permalinks and has an administrator of its own, who can save unfiltered HTML.
- Nothing on the site is in the way: a post, page or user with one of the demo names or email addresses, a menu called Primary, Secondary or Social, or an RSS feed the seed didn't add.
- The uploads and mu-plugins folders are writable, and the files the seed copies are readable.

If anything fails, it stops and lists what it found.

The first run creates an administrator, James Smith (username `james`), and prints its password once, right after creating the account. Sign in as James Smith to take screenshots; the capture command refuses any other account. The seed also runs as James Smith, so the media it creates belong to that account; posts and RSS entries keep their demo authors. To set a new password:

```bash
wp user update james --user_pass=<new password>
```

The seed creates, and marks as its own:

- Seven authors from the Gazette's newsroom, with the Gazette's monogram avatars: Dana Okafor, Marcus Reilly, Ellis Thorne, Priya Verma, Nora Doyle, Simone Adeyemi and Hal Brennan. James Smith, the editor-in-chief, writes the Opinion piece.
- 15 posts, with their categories, tags and standfirsts. Four cover the flood itself, newest first: "Kettle River flood: what's closed and where to get help" (sticky, with a photo in the story for the tap-to-load image, an external link and a link to another post), "Mill Race Footbridge closes as the Kettle rises", "Kettle River crests at 21.3 feet and starts to fall" and "Friday night games moved as the Kettle River rises". The other 11 are Gazette stories about the river, flooding and the emergency services, from the last seven weeks. Dates are set relative to when you run the seed, so the order reads the same whenever you seed. A post's standfirst, the subtitle the Newspack theme shows under the headline, is its excerpt unless `content.json` gives it a `subtitle`. Seven posts have a featured image, as they would in the paper; the stories that no photo in `assets/media/` fits have none.
- Four RSS feeds from the county's partners, under Lite Site > RSS: Halloway County Emergency Management (every 10 minutes, Active), Riverbend Public Schools (hourly, Active), the Kettle Watershed Institute (every 30 minutes, Error: "Couldn't reach this RSS feed.") and Riverbend Transit (daily, Inactive). The feeds point at made-up `.example` addresses and nothing is fetched.
- Nine RSS entries those feeds imported, one of them a draft.
- Seven photos, with the captions and photographer credits the Newspack plugin shows under them, the site logo and the site icon, in the media library, and the categories and tags the posts use that the site doesn't have yet.
- Three pages for the theme's menus: Donate, Newsletters and About Us, each with the first paragraph of the Gazette's own page.
- The Gazette's menus: Primary (the six sections the posts use, in the Gazette's order), Secondary (Donate, Newsletters and About Us, in the green strip above the masthead) and Social (Facebook, Instagram, Bluesky and YouTube).
- Block widgets: Support local reporting, Latest stories and Get it by email in the sidebar, and the Gazette's tagline in the footer.
- A must-use plugin, `wp-content/mu-plugins/newspack-ls-screenshots.php`, copied from `fakes.php`. It answers the demo feeds' scheduled imports and Retry Now with the result `content.json` gives each feed, serves the fonts of Lite Site and the theme from this folder in place of the Google Fonts stylesheets, blocks every request WordPress's HTTP API makes to a host other than the site itself, blocks all email, and gives every account a fixed avatar. It does nothing on a site the seed hasn't seeded.

It also changes these settings, and keeps their old values to put back on removal: the site title and tagline, the active theme, the theme mods of the Newspack theme and of the theme it replaces, the Newspack theme's Additional CSS, the widgets, the front page (set to show the latest posts), the site logo, the site icon, the Lite Site settings and the list of RSS feeds. The Lite Site settings turn Lite Site on at `/lite/` with 10 posts a page, a footer with the Gazette's address, The Riverbend Gazette's green as the primary color, and Source Serif 4 from Google Fonts as the body font. Other plugins can react to a theme switch in ways removal can't undo.

The full site looks like The Riverbend Gazette's live site. Everything that makes it so is under `theme` in `content.json`:

- `mods`: the Gazette's theme mods, as the Customizer saves them: its green and blue, Inter for headings and Source Serif 4 for body text, the centered logo at the Gazette's size, no site title or tagline under the logo, the stacked footer and the small footer logo.
- `footer_logo`: the image in `assets/media/` the footer shows, the site icon.
- `custom_css`: the Gazette's Additional CSS, which paints the strip above the masthead in the primary color.
- `pages`, `menus` and `widgets`: the pages, menus and widgets listed above. Widget links to a page use `{{page:<slug>}}`.

A few things on the live site are left out, because they need services or pages the demo doesn't have: the donation form in the sidebar (it needs WooCommerce), the four columns of page links in the footer, the sharing buttons, and the Gazette's homepage layout (the demo's front page lists the latest posts). The theme shows a featured image at least 1200 pixels wide, like the flood photo, above the article and across the page, where the live site's narrower photos sit beside the sidebar. Lite Site pages don't use the theme, so only screenshots of the full site depend on any of this.

The seed finishes by flushing the site's object cache, so Lite Site pages and any page cache show the new content straight away.

Run the same command again at any time to reset the demo content. A rerun keeps James Smith and the authors, and recreates everything else. The next seed after a removal creates James Smith again and prints a new password.

For screenshots of Lite Site as it is right after activation, seed with `fresh`. It creates the same posts, authors and media, but leaves Lite Site turned off with its default settings, and adds no RSS feeds or entries:

```bash
wp eval-file wp-content/plugins/newspack-lite-site/site/screenshots/seed.php fresh
```

To remove everything the seed created and put the settings back:

```bash
wp eval-file wp-content/plugins/newspack-lite-site/site/screenshots/seed.php remove
```

Removal deletes only what the seed marked and the RSS feeds it added, and refuses to run on a site with no seed state unless you add `force`. It works with Lite Site deactivated, so run it before deleting the plugin: until then, the fakes plugin keeps blocking requests and email. Posts that someone else wrote as one of the demo accounts move to the site's first other administrator, and it never deletes the site's last administrator.

If a seed stops partway, run `remove`, fix what it reported, and seed again.

Scheduled imports and Retry Now give each feed the result the seed set, so the results on the Feeds screen stay the same however long the site sits before you take a screenshot. Each run records its own time, so Last Run and Next Run move on as WP-Cron runs the imports, as they would on a real site. Retry Now on the Kettle Watershed Institute feed fails again with the same error, like a feed that's still down. For a screenshot of a retry that works, so that Retry Now and the scheduled imports bring it back up to date, turn that on with `wp option update newspack_ls_screenshots_retry_works 1`, and turn it off again with `wp option delete newspack_ls_screenshots_retry_works`. Rerun the seed to bring the error back.

## Take a screenshot

From `site/`, install the dependencies once:

```bash
npm ci
```

Then name the image and give the site's address:

```bash
npm run shot -- guides/rss-feeds-1 --site https://screenshots.local --url "wp-admin/admin.php?page=newspack-lite-site-rss#/feeds"
```

1. Chrome opens at the page. The first time, the terminal asks for a username and password: use James Smith's, or set `WP_USER` and `WP_PASSWORD`. The browser loads only the site itself; requests to other hosts are blocked.
2. Get the page into the state the screenshot needs: open a drawer, hover over a status, scroll.
3. To spotlight one element, hold Alt (Option on a Mac) and click it. It gets a dashed outline, which doesn't show in the image. On Linux desktops that use Alt-click to move windows, use `--spotlight` instead.
4. Press Enter in the terminal.

The script saves `site/public/screenshots/guides/rss-feeds-1.webp` and prints the tag to paste into the page:

```mdx
<Screenshot label="..." src="/screenshots/guides/rss-feeds-1.webp" width={3024} height={1964} />
```

Write the label as alt text that says what the image shows and what is highlighted.

[`SHOTS.md`](SHOTS.md) has the command for every existing screenshot. Set `SHOT_SITE` to your site's address, and you can run them as they are.

### Examples

An admin screen, with the Error status of a feed spotlighted:

```bash
npm run shot -- guides/rss-feeds-1 --site https://screenshots.local \
  --url "wp-admin/admin.php?page=newspack-lite-site-rss#/feeds" \
  --spotlight 'tr:has-text("kettlewatershed.example") .newspack-status-badge'
```

A drawer with its fields filled in: the script opens Add RSS Feed, types the feed's address, picks a frequency and an author from their menus, then clicks a help text so no field shows a focus ring.

```bash
npm run shot -- getting-started/add-an-rss-feed-2 --site https://screenshots.local \
  --url "wp-admin/admin.php?page=newspack-lite-site-rss" \
  --click 'button:has-text("Add RSS Feed")' \
  --fill 'internal:label="RSS Feed URL"s' --value 'https://hallowaycounty.example/alerts/feed/' \
  --fill 'internal:label="Frequency"s' --value 'Every 10 Minutes' \
  --fill 'internal:label="Author"s' --value 'Dana Okafor' \
  --click 'text=Imported entries are published under this author.' \
  --spotlight '.newspack-drawer button:has-text("Add RSS Feed")'
```

A tooltip: the script rests the pointer on the Error status and captures with the tooltip open.

```bash
npm run shot -- guides/rss-feeds-2 --site https://screenshots.local \
  --url "wp-admin/admin.php?page=newspack-lite-site-rss#/feeds" \
  --hover 'tr:has-text("kettlewatershed.example") .newspack-status-badge > *'
```

A Lite Site article as a signed-out reader sees it, after tapping Load image: the script opens the Lite Site homepage, clicks the sticky story, loads the image and scrolls it into view.

```bash
npm run shot -- guides/lite-articles-3 --site https://screenshots.local \
  --url lite/ --logged-out --click 'text=Kettle River flood:' \
  --click .lite-image-load-btn --scroll '.content figure' --spotlight '.content figure'
```

A hover, and a spotlight around two elements at once (the box that contains both). This is a try-out, so `--out` writes it to a scratch file instead of a docs image:

```bash
npm run shot -- guides/scratch-1 --site https://screenshots.local \
  --url "wp-admin/admin.php?page=newspack-lite-site-rss#/feeds" --out /tmp/two-rows.webp \
  --hover 'tr:has-text("kettlewatershed.example") .newspack-status-badge > *' \
  --spotlight 'tr:has-text("kettlewatershed.example")' --spotlight 'tr:has-text("riverbendschools.example")'
```

Loading the image in a signed-out Lite Site article, waiting for it, scrolling it into view and then back up a little, again to a scratch file:

```bash
npm run shot -- guides/scratch-1 --site https://screenshots.local \
  --url lite/ --logged-out --click 'text=Kettle River flood:' --out /tmp/scroll-test.webp \
  --click .lite-image-load-btn --wait 500 --scroll '.content figure' --scroll=-200
```

### Options

| Option | What it does |
| :--- | :--- |
| `--site <url>` | The screenshot site's address, including any folder WordPress is installed in. `https://` is added when you leave it out. You can set `SHOT_SITE` instead. |
| `--url <path>` | The page to open, relative to the site's address. Defaults to `wp-admin/`. |
| `--logged-out` | Opens the page in a fresh browser with no one signed in, so there's no admin bar. Use it for Lite Site pages and the full site as readers see them. |
| `--spotlight <selector>` | Spotlights the first element that matches, instead of Alt-clicking. Repeat it to spotlight the one box that contains all the matches, such as a field and its button. |
| `--click <selector>` | Clicks an element before the capture, and waits for the page to settle. Repeat it for several clicks, in order. |
| `--fill <selector>` and `--value <text>` | Types the text into the first field that matches, such as a feed address in a drawer. When the field is a select menu, picks the choice with that label instead, such as a frequency or an author. Put each `--value` right after its `--fill`. |
| `--hover <selector>` | Rests the pointer on the first element that matches, to show a tooltip or a hover state, such as the message on an Error status. The pointer stays there for the capture. Repeat it for several hovers. |
| `--scroll <selector or px>` | With a selector, scrolls the first element that matches into the middle of the window, such as the image placeholder or the footer of a Lite Site page. With a number of pixels, scrolls down by that much, or up when it's negative. Write a negative number with an equals sign, as `--scroll=-2000`: a bare `--scroll -2000` fails to parse. It scrolls an open modal first, then the block editor's canvas, then the page. |
| `--press <key>` | Presses a key, such as `Escape`, `Enter` or `Control+a`, using Playwright's key names. Repeat it for several keys. |
| `--wait <ms>` | Waits that many milliseconds, for an animation or anything that takes longer to appear than the page takes to settle. Repeat it where you need it. |
| `--eval <js>` | Runs JavaScript in the page and in each frame inside it, such as the editor canvas, for a step no other option covers. Errors in the main page are printed. Repeat it for several scripts. |
| `--mouse <x,y>` | Leaves the pointer at this point in the window, in pixels from the top left of the 1512×982 viewport. By default it moves to the bottom right corner after a click or fill. |
| `--now` | Captures as soon as the page settles, without opening a window or waiting for Enter. Use it with the steps above and `--spotlight` for screens you can reach without help. Without a saved sign-in it needs `WP_USER` and `WP_PASSWORD`, since it can't ask. |
| `--out <file>` | Writes the image somewhere else, for trying things out. A relative path starts from the folder you ran `npm run` in. |

Clicks, fills, hovers, scrolls, key presses, waits and scripts all run in the order you give them, whichever options they use.

Selectors can be CSS, or Playwright's text selectors such as `button:has-text("Add RSS Feed")` and `text=Kettle River flood:`. To find a field by its label, use `internal:label="<label>"s`, where the `s` makes the match exact. They also find elements inside the block editor's canvas.

The script also captures without waiting when it isn't run from a terminal.

### Selector tips

- Posts' addresses include their date on sites with date-based permalinks, which changes with every seed, so open a Lite Site article from the Lite Site homepage with `--click` rather than by its address.
- To reach one feed or entry in a list, scope the selector to its row by something only that row shows, such as `tr:has-text("kettlewatershed.example")` for a feed's address or the entry's title, then add the control: `tr:has-text("...") button:has-text("Edit")`.
- Playwright's `:text-is()` doesn't match menu items whose text sits in a child element. Use `:has-text()`, or `:has(:text-is("..."))`.
- A bare `[role="menuitem"]` or `[role="menu"]` also matches hidden elements in the admin bar. Scope it, for example `[role="menu"]:has-text("View Original")`.

### Clean up after a session

Signing in through wp-admin makes WordPress's Quick Draft create an auto-draft post. Delete the strays after a session:

```bash
wp post delete $(wp post list --post_type=post --post_status=auto-draft --format=ids) --force
```

## House settings

Every screenshot uses these, and the script applies them for you:

- A 1512×982 viewport at twice the pixel density, saved as a 3024×1964 WebP at quality 85, without metadata.
- Light mode, the visible viewport only (never the full page), and no scrollbars.
- Signed in as James Smith, who has a flat blue avatar, for admin screens. Signed out for Lite Site pages and the full site as readers see them. Update notices, cookie banners, the Newspack debug notice and similar noise are hidden from the moment the page loads.
- The reader sign-up prompt is closed on front-end pages.
- The editor isn't in fullscreen mode and shows no block breadcrumbs, welcome guides or pattern chooser. Top toolbar is on, so a selected block's toolbar sits in the editor's header instead of over the page. These apply in the post, page, pattern and Site Editor alike, and the script saves them for the account you sign in with each time it runs.
- At most one spotlight: everything outside the element and an 8px margin is dimmed with 45% black.

Before you commit an image, look at it.

## Troubleshooting

- **The seed refuses to run:** read the list it prints. Nothing was changed.
- **Lite Site pages show content from before the seed:** the seed flushes the object cache, but a page cache outside WordPress, such as one in your local server, can still hold the old page. Clear it, or wait for it to expire.
- **The Lite Site address shows a page not found:** visit Settings > Permalinks once to rebuild the site's addresses, then try again.
- **Chrome doesn't start:** install Google Chrome, or set `CHROME_PATH` to a Chrome or Chromium binary.
- **Signed in as the wrong user:** the script stops without saving anything. Run it again and sign in as James Smith. A good sign-in is saved in `~/.cache/newspack-ls-shot/`, one file per site, readable only by you.
- **The site uses http or a self-signed certificate:** that's fine; the script accepts both.

## Credits

- **Photos** in `assets/media/` (all but the site logo and site icon). Their captions and credits are in `content.json`:
  - From [Pexels](https://www.pexels.com/), free to use under the [Pexels license](https://www.pexels.com/license/): `flood-street.jpg` by Sveta K, and these three, resized to 1600 pixels on the long edge:
    - `flooded-field.jpg`: "Flooded Field" by Toon de Vos, [Pexels](https://www.pexels.com/photo/flooded-field-6208447/).
    - `flooded-riverside-walkway.jpg`: "Flooded Riverbank Walkway" by Andrey Karpov, [Pexels](https://www.pexels.com/photo/flooded-riverbank-walkway-7821835/).
    - `road-works.jpg`: "Road Construction Worker with Jackhammer" by Thomas Fuhrmann, [Pexels](https://www.pexels.com/photo/road-construction-worker-with-jackhammer-33484883/).
  - Found through [Openverse](https://openverse.org/), dedicated to the public domain under [CC0 1.0](https://creativecommons.org/publicdomain/zero/1.0/) or marked with the [Public Domain Mark 1.0](https://creativecommons.org/publicdomain/mark/1.0/), so they need no credit, though the site credits each under its caption. Each is resized.
    - `kettle-river-sunset.jpg`: "autumn sunset on the river" by azxa661, Public Domain Mark, [Flickr](https://www.flickr.com/photos/197207264@N02/52581985117).
    - `reporters-notebook.jpg`: "Research" by neil conway, Public Domain Mark, [Flickr](https://www.flickr.com/photos/30934989@N06/5625707813).
    - `water-main-trench.jpg`: "Bøgeskov Høvej 25, vejarbejde - DSC_2223_Balancer" by Lav Ulv, CC0, [Flickr](https://www.flickr.com/photos/99460566@N06/35776145673).
- **The site logo and site icon**, `assets/media/riverbend-gazette-wordmark.png` and `assets/media/site-icon.png`, are The Riverbend Gazette's wordmark and icon, made for Newspack's demo sites. The icon is also the footer logo.
- **Source Serif 4**, the variable WOFF2 files in `assets/fonts/source-serif-4/`, is by Adobe, from [adobe-fonts/source-serif](https://github.com/adobe-fonts/source-serif) release 4.005, under the [SIL Open Font License 1.1](https://openfontlicense.org/) (`assets/fonts/source-serif-4/LICENSE.md`). Lite Site pages and the theme on the screenshot site load it from this folder in place of Google Fonts.
- **Inter**, the variable WOFF2 files in `assets/fonts/inter/`, is by The Inter Project Authors ([rsms/inter](https://github.com/rsms/inter)), the Latin files Google Fonts serves for version 20, under the [SIL Open Font License 1.1](https://openfontlicense.org/) (`assets/fonts/inter/LICENSE.md`). The theme on the screenshot site loads it from this folder in place of Google Fonts.
- **Avatars** in `assets/avatars/` are The Riverbend Gazette's monogram avatars, one per seeded author, made for Newspack's demo sites and resized to 192 pixels. `default.svg`, a flat blue square, is the avatar of James Smith and every other account.
- **The stories** come from The Riverbend Gazette, the invented newspaper of Newspack's demo sites.
