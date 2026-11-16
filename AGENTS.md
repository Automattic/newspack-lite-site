# AI agent instructions

Guidance for AI coding agents working in this repository. `CLAUDE.md` points here.

## Overview

Lite Site is a WordPress plugin that serves a stripped-down, text-only version of a site (at `/lite/` by default) for slow connections, traffic spikes and emergencies. It can also import RSS feeds so a Lite Site can republish partner coverage.

- `includes/`: PHP classes (Composer classmap).
- `src/`: the React admin screens, built into `dist/` (never committed).
- `templates/`: the text-only page templates.
- `tests/`: PHPUnit tests.
- `site/`: the publisher documentation site. See "Docs site" below.

## Build, lint and test

The plugin uses npm and Composer.

```bash
npm ci && composer install   # Install dependencies
npm run build                # Build dist/
npm run watch                # Rebuild on change
```

- Composer autoloads `includes/` with a classmap. After adding a PHP class file, run `composer dump-autoload`.

Lint before pushing:

```bash
npm run lint:js      # ESLint
npm run lint:scss    # Stylelint
npm run lint:php     # PHPCS with this repo's phpcs.xml
npm run fix:js       # Auto-fix JS (also format:scss, fix:php)
```

Tests:

- **PHP:** `npm run test:php` runs PHPUnit from `tests/`. It needs a WordPress test install, which `bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]` sets up.
- **JS:** `npm test` only prints a notice, since the plugin has no JS unit tests.

Commits follow [Conventional Commits](https://www.conventionalcommits.org/) (`npm run cm` opens an interactive prompt). Releases run through `semantic-release` from the `release` branch. Never edit `CHANGELOG.md` or the translation files in `languages/`: CI generates them.

## Writing

A change that alters what publishers see updates the matching page in `site/content/docs/` in the same pull request, and retakes the screenshots that show the changed screen. A doc left behind describes behavior that no longer exists.

- Describe the end state, not the history of the change. Rewrite or remove sentences the change makes untrue instead of appending to them.
- Quote UI labels exactly as they appear on screen.
- The name is "Lite Site", never "Lite Sites".

## Docs site

`site/` is the publisher documentation site: an [Astro](https://astro.build/) and [Fumadocs](https://fumadocs.dev/) static site with its own npm project, lockfile and `.nvmrc` (Node 24). The plugin's `npm ci`, lint and build never touch it, and it never ships in the plugin zip (`/site` in `.distignore`). The plugin's PHPCS does check the PHP in `site/screenshots/`.

```bash
cd site
npm ci
npm run dev           # Local site at http://localhost:4321
npm run build         # Static build in site/dist/
npm test              # Content and color contrast checks
npm run check:build   # Checks site/dist/ after a build
npm run shot -- <section>/<name>-<n> --site <url>   # Retakes a screenshot
```

- The site must build from a fresh clone with only `site/` dependencies installed, as Spacefast builds it, so `site/astro.config.mjs` pins Vite to `site/tsconfig.json`; otherwise Vite falls back to the plugin's root `tsconfig.json`, whose `extends` needs the plugin's `node_modules`.
- Pages are MDX files in `site/content/docs/`; each folder's `meta.json` sets the sidebar order. The homepage is `site/src/components/home.tsx`, and the About page is `site/src/components/about.tsx`.
- **Site chrome** (header, mobile menu, search, docs drawer, page chrome and footer) comes from the [newspack-ui site kit](https://newspack-ui-theme.space.fast/docs/build-a-site/), installed with `npx shadcn@latest add` into `site/src/components/site/`, `site/src/components/docs/`, `site/src/components/ui/`, `site/src/lib/` (`theme.ts`, `theme-script.ts`, `newspack-ui.ts` and `newspack-ui-icons.tsx`) and `site/src/styles/` (`newspack-ui-site.css` and `newspack-ui-fumadocs.css`, imported by `global.css`). Don't edit those files, since a later install overwrites them; report what the kit lacks upstream instead. The install also rewrites `global.css` (indentation and quote style) and replaces `site/src/lib/site.ts` with the kit's template, so put both back before committing. `site.ts` holds the site's settings: the name, nav and footer links, and `SEARCH_INDEX` (`/api/search`, which `check-build` doesn't catch if it's wrong). Its own pieces (wordmark, footer credit line, and the islands that hand the wordmark to the kit's `AstroDocs` and `AstroDocsSiteHeader`) live in `site/src/components/brand.tsx`, `docs.tsx` and `site-layout.tsx`.
- **Writing rules**, enforced by `npm test` where they can be:
  - Publishers first: plain words, short steps, one action per step. US English, sentence-case headings, no em dashes.
  - Technical words (hook and filter names, options, cron) belong only in `site/content/docs/developers/`, which explains what each thing is for before how to use it. The plain word "filter" for filtering by category and tag is fine on publisher pages.
  - Quote UI labels exactly as they appear on screen, checked against the plugin source.
  - Every step that changes what's on screen gets a `<Screenshot label="..." />` naming the screen and what's highlighted.
- **Reading text:** newspack-ui's scale is for reader UI and short headings; the site sets long text itself. Leads (`type-lead`, `.docs-lead`) use the larger lead size at the body line height; docs body text (`.docs-prose`) and About body text (`type-prose`) use the body size at the looser `--ls-line-height-prose`. Don't change newspack-ui's own tokens for this.
- **Screenshots** live in `site/public/screenshots/<section>/<page>-<n>.webp` and all show the same demo site, "The Riverbend Gazette".
  - A change to a screen publishers see retakes the screenshots that show it, with the seed and capture command in `site/screenshots/`. [`site/screenshots/README.md`](site/screenshots/README.md) has the steps and [`site/screenshots/SHOTS.md`](site/screenshots/SHOTS.md) lists the command for each image.
  - House settings, for checking a pull request's images: 3024×1964 WebP, light mode, the classic Newspack theme with the seeded demo content, signed in as the seeded "James Smith" administrator.
  - The seed runs only against a local or development single site set aside for screenshots, unless run with `force`, and puts back what it changed when run with `remove`. The capture command keeps its sign-in outside the checkout, in `~/.cache/newspack-ls-shot/`.
- **Hosting:** Spacefast, in the Newspack team, at `https://lite-site.space.fast/`. Spacefast builds the site from this repository (root directory `site`) whenever the deploy branch changes. The deploy branch is `trunk`, so the live site can run ahead of the latest plugin release. Write docs for the behavior publishers will have once the change ships, and say in the pull request when a page describes unreleased behavior.
- **Updating the live site:** push to (or merge into) the deploy branch. Each build shows on the Space's Builds page in Spacefast and as the `Spacefast Builds` check on GitHub.
- **When a build doesn't start** (it can sit queued), sign in to Spacefast as a member of the Newspack team and sync the repository, then retry the latest build:

  ```bash
  npx -y spacefast@latest git sync --space lite-site --team newspack
  npx -y spacefast@latest redeploy --space lite-site --team newspack
  ```

  Don't publish `site/dist` by hand with `spacefast publish`: it uploads only part of the build, and the live site loses most of its pages.

- **Access:** members of the Newspack team on Spacefast, plus a password for invited publishers. The password is managed in the Space's Access settings and never goes in this repository.
- Spacefast's local `.spacefast/` folder is never committed (`site/.gitignore`).
