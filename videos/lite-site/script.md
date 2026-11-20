Title: Lite Site
Type: marketing
Site: gazette
Length: about 35 seconds
Voice: Kokoro af_heart, speed 1.05

<!--
Audience: a newsroom deciding whether they need Lite Site.

Recorded with the browser-record skill, which copies this folder into a
recording session and runs record.mjs from there. This file is the story;
record.mjs is how each scene is staged (the slow connection, the loading
spinners, the zooms and their timing). When the UI changes, update record.mjs
and record again; this file changes only when the story does. The finished
video and the photo are not committed: download the photo from the link under
"Photos" into assets/ before recording.
-->

## Scenes

### 1. Title card

- **Card:** "Lite Site"

### 2. The moment

- **On screen:** the flood photo (`assets/pexels-photo-8568720.jpg`), slow push-in toward the black car, so the taxi's license plate leaves the frame
- **Headline (plain):** When the river rises
- **Voice:** "When disaster strikes… networks get overloaded."

### 3. The problem

- **On screen:** the full homepage on a slow connection: text in, images stuck loading, held a moment after the line
- **Headline (split):** Heavy pages stop loading
- **Voice:** "And heavy news pages? They stop loading."

### 4. The feature

- **On screen:** the Lite Site homepage, the flood story pinned at the top
- **Headline (fade):** The latest, first
- **Zoom:** the pinned flood story, as the scene starts
- **Voice:** "Lite Site gives your newsroom a text-only edition, with the latest updates first."

### 5. The proof

- **On screen:** the Lite Site version of that story (the cut from scene 4 reads as opening it), scrolled to its photo
- **Headline (fade):** 3 MB pages become 50 KB
- **Zoom:** the photo placeholder and its "Load image" button
- **Voice:** "Every story loads, even on the weakest connection."

### 6. The effort

- **On screen:** Lite Site > Settings, "Enable Lite Site" being checked and saved
- **Headline (fade):** Enable in seconds
- **Zoom:** "Enable Lite Site"
- **Voice:** "Enable it in seconds. There's no new site to build!"

### 7. Closing card

- **Card:** Newspack logo
- **Voice:** "Lite Site. From Newspack and Emergency Mode for News. Keep your readers informed… when it matters most."

## Photos

- `assets/pexels-photo-8568720.jpg`: https://www.pexels.com/photo/two-cars-in-the-flooded-road-8568720/ (Pexels license: commercial use allowed, credit optional)

## Site setup

The Gazette demo site keeps the content these scenes need:

- Lite Site turned on, at `/lite/`.
- "Kettle River flood: what to know as the water rises", pinned, in News, with the flood photo already in the site's media library as its featured image and in its content.

The homepage and story scenes use the pinned story, or the latest story when nothing is pinned. Recording turns Lite Site off for the "Enable in seconds" scene, which turns it back on, and the skill's undo list restores the settings afterwards.

## Notes

- **Size figure:** "3 MB pages become 50 KB" matches the docs site's About page ("a typical article fell from about 3 MB to under 50 KB"), which is about articles, so it sits on the article scene.
- **Slow homepage:** staged on a real slow connection. The page and its text load for real; the images are covered by grey boxes with spinners, and the page never finishes. The Lite Site scenes are not staged.
- **Credit:** in the voiceover for now. Emergency Mode for News is a collaboration between OpenNews, Newspack and NC Local, supported by Press Forward.
- **Photo:** the white car carries a taxi company's branding and a readable license plate; the zoom moves the plate out of frame. Fine for this experiment; look at it again before any public release.
