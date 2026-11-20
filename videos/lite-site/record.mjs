// Records the Lite Site marketing video described in script.md, on the
// Gazette demo site, with the browser-record skill. script.md holds the
// story; this file holds how each scene is staged. When the UI changes,
// update this file and record again.
//
// The skill copies this folder (with assets/) into a recording session's
// scripts/ folder and runs it from there, so the imports below resolve to the
// skill's library and the recordings root's Playwright.
//
//   node scripts/record.mjs                       # the whole video
//   ONLY=latest,article node scripts/record.mjs   # just those scenes, no join
//
// The "enable" scene needs an admin sign-in. The skill's signInToDemoSite()
// opens a window for it before the first scene and keeps it open until the
// recording ends; nothing is saved. That scene turns Lite Site off first, so
// it can be turned on on camera, and withChanges() puts the settings back
// afterwards.
import { chromium } from 'playwright';
import { readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { interstitial, closingCard, headline, zoomTo } from '../../lib/narrate.mjs';
import { configureVoiceover, voiceover } from '../../lib/voiceover.mjs';
import { recordScene, joinScenes } from '../../lib/scenes.mjs';
import { color } from '../../lib/brand.mjs';
import { sessionDirs } from '../../lib/video-files.mjs';
import { getDemoSite } from '../../lib/demo-sites.mjs';
import { signInToDemoSite } from '../../lib/demo-login.mjs';
import { withChanges } from '../../lib/changes.mjs';

const dirs = sessionDirs(import.meta.url);
const SCENES_DIR = join(dirs.videos, 'scenes');
const OUT = join(dirs.videos, 'lite-site.mp4');
const PHOTO = join(dirs.assets, 'pexels-photo-8568720.jpg'); // see "Photos" in script.md
const SITE = getDemoSite('gazette').url.replace(/\/$/, '');
const LEDGER = join(dirs.session, 'changes.json');

// --- The voice -------------------------------------------------------------------
// The same lines as script.md, word for word.
const lines = {
  photo: 'When disaster strikes… networks get overloaded.',
  slow: 'And heavy news pages? They stop loading.',
  latest: 'Lite Site gives your newsroom a text-only edition, with the latest updates first.',
  article: 'Every story loads, even on the weakest connection.',
  enable: "Enable it in seconds. There's no new site to build!",
  close: 'Lite Site. From Newspack and Emergency Mode for News. Keep your readers informed… when it matters most.',
};
configureVoiceover({ provider: 'kokoro', kokoroVoice: 'af_heart', kokoroSpeed: 1.05 });

// --- Helpers -----------------------------------------------------------------------

// A slow mobile connection: 500ms delay, about 1 Mbps.
async function throttle(page) {
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Network.enable');
  await cdp.send('Network.emulateNetworkConditions', {
    offline: false, latency: 500, downloadThroughput: 120 * 1024, uploadThroughput: 40 * 1024,
  });
}

// The full site on a stalled connection. Each news image (40px or larger, so
// not the logo or icons) is covered by a grey box with a spinner, including
// those below the fold: the split headline shrinks the page to show all of it.
// Only the main photo is revealed later; the rest keep spinning, so the page
// never finishes. The boxes sit in <body> at the images' positions, so the
// split headline's shrink carries them along.
async function stallImages(page) {
  await page.evaluate((blue) => {
    const style = document.createElement('style');
    style.textContent = `
      @keyframes np-spin { to { transform: rotate(360deg); } }
      .np-ph { position: absolute; z-index: 5; pointer-events: none; background: #e8e8e8;
        clip-path: inset(0); transition: clip-path 1200ms linear; }
      .np-ph.np-arrived { clip-path: inset(100% 0 0 0); }
      .np-ph::after { content: ''; position: absolute; top: 50%; left: 50%;
        width: 32px; height: 32px; margin: -16px 0 0 -16px; border-radius: 50%;
        border: 3px solid rgba(0, 0, 0, .12); border-top-color: ${blue};
        animation: np-spin 900ms linear infinite; }
      .np-ph.np-arrived::after { display: none; }`;
    document.head.appendChild(style);
    const body = document.body.getBoundingClientRect();
    const images = [...document.images].filter((img) => {
      const r = img.getBoundingClientRect();
      return r.width >= 40 && r.height >= 40;
    });
    images.forEach((img, i) => {
      const r = img.getBoundingClientRect();
      const box = document.createElement('div');
      box.className = 'np-ph';
      // Round outward by a pixel on every side: image positions fall on
      // fractions of a pixel, and an exact-size box lets a sliver of the image
      // underneath show along one edge.
      const left = Math.floor(r.left - body.left) - 1;
      const top = Math.floor(r.top - body.top) - 1;
      Object.assign(box.style, {
        left: `${left}px`, top: `${top}px`,
        width: `${Math.ceil(r.right - body.left) + 1 - left}px`,
        height: `${Math.ceil(r.bottom - body.top) + 1 - top}px`,
      });
      // Hidden until its reveal, so nothing underneath can show through.
      img.style.opacity = '0';
      document.body.appendChild(box);
      if (i === 0) window.__npMain = { img, box };
    });
  }, color('primary-600'));
}

// Reveals the main photo when it really arrives (or now, if it already has).
// Waits in the page, so the photo's download never sets the scene's length.
const revealMainImage = (page) => page.evaluate(() => {
  const main = window.__npMain;
  if (!main) return;
  const reveal = () => {
    main.img.style.opacity = '1';
    main.box.classList.add('np-arrived');
  };
  if (main.img.complete && main.img.naturalWidth) reveal();
  else main.img.addEventListener('load', reveal, { once: true });
});

// The story the homepage scene zooms onto and the article scene opens: the
// pinned (sticky) story if there is one, otherwise the latest. Lite Site
// shows a pinned story's title in an <h3> and other titles in a <span>.
// Stories added from an RSS feed link to another site, so they are skipped.
async function findStory(page) {
  await page.goto(`${SITE}/lite/`, { waitUntil: 'load' });
  const story = await page.evaluate(() => {
    const local = 'a:not(.lite-site-external)';
    const link = document.querySelector(`.post-list li h3 ${local}`)
      ?? document.querySelector(`.post-list li ${local}`);
    if (!link) return null;
    const items = [...document.querySelectorAll('.post-list li')];
    return { href: link.href, title: link.textContent.trim(), nth: items.indexOf(link.closest('li')) + 1 };
  });
  if (!story) throw new Error(`No story on ${SITE}/lite/ links to a Lite Site page. See "Site setup" in script.md.`);
  return story;
}

// Starts a voice line, runs the scene's actions while it plays, ends with it.
async function speakWhile(page, text, actions = async () => {}) {
  const started = Date.now();
  const ms = await voiceover(page, text, { waitForEnd: false });
  await actions();
  await page.waitForTimeout(Math.max(0, ms - (Date.now() - started)) + 100);
}

// Smoothly scrolls so an element sits a little above the middle of the frame.
const glideTo = (page, selector, ms) => page.evaluate(([selector, ms]) => new Promise((done) => {
  const el = document.querySelector(selector);
  const r = el.getBoundingClientRect();
  const start = window.scrollY;
  const by = r.top + r.height / 2 - window.innerHeight * 0.4;
  const t0 = performance.now();
  const step = (now) => {
    const k = Math.min(1, (now - t0) / ms);
    window.scrollTo(0, start + by * (1 - Math.cos(k * Math.PI)) / 2);
    if (k < 1) requestAnimationFrame(step); else done();
  };
  requestAnimationFrame(step);
}), [selector, ms]);

const blank = (p) => p.setContent('<html><body style="margin:0;background:#fff"></body></html>');

// --- Before recording --------------------------------------------------------------
const only = process.env.ONLY?.split(',');
const wants = (name) => !only || only.includes(name);
if (wants('photo') && !existsSync(PHOTO)) {
  throw new Error(`Missing ${PHOTO}. Download it from the link under "Photos" in script.md.`);
}
// The front-end scenes need Lite Site on; the enable scene turns it on itself.
if ((await fetch(`${SITE}/lite/`)).status !== 200) {
  throw new Error(`${SITE}/lite/ isn't loading. Is Lite Site on? See "Site setup" in script.md.`);
}

const browser = await chromium.launch({ headless: true });

// Signing in happens once, before anything is recorded. Only the "enable"
// scene needs it.
const admin = wants('enable') ? await signInToDemoSite(chromium, 'gazette') : null;

// Lite Site's settings, read from inside the signed-in page (a request from
// outside a page signs the demo site's session out).
const liteSettings = (page) => page.evaluate(async (site) => {
  const nonce = (await (await fetch(`${site}/wp-admin/admin-ajax.php?action=rest-nonce`)).text()).trim();
  const res = await fetch(`${site}/wp-json/wp/v2/settings?context=edit`, { headers: { 'X-WP-Nonce': nonce } });
  return (await res.json()).newspack_lite_site_settings;
}, SITE);

const scenes = [];
let story;
const scene = async (name, options, run) => {
  if (!wants(name)) return;
  console.log(`scene: ${name}`);
  scenes.push(await recordScene(browser, { name, dir: SCENES_DIR, ...options }, run));
};

// --- Scenes --------------------------------------------------------------------------
await scene('title',
  { setup: async (p) => { await blank(p); await interstitial(p, 'Lite Site', { dwell: 0, keep: true }); } },
  (p) => p.waitForTimeout(1500));

// The photo: plain white headline, with a slow push-in on the black car, so
// the taxi's license plate leaves the frame.
await scene('photo',
  {
    setup: async (p) => {
      const src = `data:image/jpeg;base64,${readFileSync(PHOTO).toString('base64')}`;
      await p.setContent(`<html><body style="margin:0;overflow:hidden;background:#000">
        <img src="${src}" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:50% 70%">
        <div id="flooded-cars" style="position:absolute;left:20%;top:25%;width:42%;height:35%"></div>
      </body></html>`);
      await p.waitForFunction(() => document.images[0].complete);
    },
  },
  async (p) => {
    await headline(p, 'When the river rises', { style: 'plain' });
    await speakWhile(p, lines.photo, () => zoomTo(p, '#flooded-cars', { scale: 1.25, duration: 3000 }));
  });

// The full site, stalled. Opens on the page already drawn, with its images
// spinning, so the crossfade never shows a blank page.
await scene('slow',
  {
    setup: async (p) => {
      await blank(p);
      await throttle(p);
      await p.goto(`${SITE}/`, { waitUntil: 'domcontentloaded' });
      await stallImages(p);
      // Split during setup, so the scene opens already side by side.
      await headline(p, 'Heavy pages stop loading', { style: 'split' });
    },
  },
  async (p) => {
    await speakWhile(p, lines.slow, async () => {
      await p.waitForTimeout(600); // let the viewer see the spinners first
      await revealMainImage(p);
    });
    await p.waitForTimeout(1200); // hold on the stalled page a little longer
  });

// The Lite Site homepage, zooming straight onto the story the next scene opens.
await scene('latest',
  {
    setup: async (p) => {
      story = await findStory(p);
      console.log(`  story: ${story.title}`);
    },
  },
  async (p) => {
    await headline(p, 'The latest, first');
    await speakWhile(p, lines.latest, () =>
      zoomTo(p, `.post-list li:nth-child(${story.nth})`, { scale: 1.6, duration: 1000 }));
  });

// That story in the Lite Site. The cut from the zoomed link reads as opening
// it; the zoom lands on the photo placeholder and its "Load image" button.
await scene('article',
  {
    setup: async (p) => {
      story ??= await findStory(p);
      await p.goto(story.href, { waitUntil: 'load' });
      // The image in the story's content, below the fold (the first
      // placeholder can be the featured image at the top).
      const placeholder = p.locator('.content .lite-image-placeholder').first();
      if (!(await placeholder.count())) {
        throw new Error(`"${story.title}" has no image in its content, so the article scene has nothing to zoom onto.`);
      }
      await placeholder.evaluate((el) => el.setAttribute('data-zoom-target', '1'));
    },
  },
  async (p) => {
    await headline(p, '3 MB pages become 50 KB');
    await speakWhile(p, lines.article, async () => {
      // The placeholder is below the fold: glide down to it, then zoom.
      await glideTo(p, '[data-zoom-target="1"]', 1100);
      await zoomTo(p, '[data-zoom-target="1"]', { scale: 1.4, duration: 900 });
    });
  });

// Lite Site > Settings, signed in. Lite Site is turned off off camera first,
// and withChanges() puts the settings back when the scene ends, or if it
// fails. Lite Site keeps all its settings in one option, which the REST API
// replaces whole, so the whole object is sent with only `enabled` changed.
if (admin) {
  const settings = await liteSettings(admin.page);
  if (!settings) throw new Error('Could not read the Lite Site settings. Is the sign-in an administrator?');
  const undo = await withChanges(admin.page, { siteUrl: `${SITE}/`, ledgerPath: LEDGER }, async (changes) => {
    await scene('enable',
      {
        contextOptions: { storageState: admin.storageState },
        setup: async (p) => {
          await changes.update('/wp/v2/settings', { newspack_lite_site_settings: { ...settings, enabled: false } });
          await p.goto(`${SITE}/wp-admin/admin.php?page=newspack-lite-site`, { waitUntil: 'load' });
          const box = p.getByLabel('Enable Lite Site');
          await box.waitFor();
          // zoomTo() takes a CSS selector, so mark the checkbox's control first.
          await box.evaluate((el) => el.closest('.components-checkbox-control, .components-base-control, label, div')
            .setAttribute('data-zoom-target', '1'));
        },
      },
      async (p) => {
        await headline(p, 'Enable in seconds');
        await speakWhile(p, lines.enable, async () => {
          await zoomTo(p, '[data-zoom-target="1"]', { scale: 1.8, duration: 900 });
          await p.getByLabel('Enable Lite Site').check();
          await p.waitForTimeout(400);
          const saved = p.waitForResponse((r) => r.url().includes('/wp/v2/settings') && r.request().method() === 'POST');
          await p.getByRole('button', { name: 'Save' }).first().click();
          await saved;
        });
      });
  });
  console.log(`Put back: ${undo.undone.join('; ') || 'nothing'}`);
  for (const step of undo.byHand) console.log(`Undo by hand: ${step}`);
  for (const f of undo.failed) console.log(`Undo failed: ${f.description} (${f.error})`);
}

await scene('closing',
  { setup: async (p) => { await blank(p); await closingCard(p, { dwell: 0, keep: true }); } },
  (p) => speakWhile(p, lines.close));

await browser.close();
await admin?.close();

for (const s of scenes) console.log(`  ${s.name}: ${s.durationSec.toFixed(2)}s, ${s.entries.length} line(s)`);
if (only) {
  for (const s of scenes) console.log(`clip: ${s.videoPath} (kept from ${s.trimStartSec.toFixed(2)}s)`);
} else {
  await joinScenes(scenes, OUT);
  console.log(`video: ${OUT}`);
}
