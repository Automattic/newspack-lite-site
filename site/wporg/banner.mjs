import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

export const DEFAULT_FONT_PATH = fileURLToPath(
	new URL(
		'../node_modules/@fontsource-variable/inter/files/inter-latin-opsz-normal.woff2',
		import.meta.url
	)
);

const escapeHtml = ( text ) =>
	text.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );

const BAR = 34;

const WINDOW_CSS = ( { x, y, width } ) => `.line { max-width: none; }
.window { position: absolute; left: ${ x }px; top: ${ y }px; width: ${ width }px; bottom: -${ y }px; border-radius: 10px 10px 0 0; overflow: hidden; background: #fff; }
.window::after { content: ""; position: absolute; inset: 0; border-radius: inherit; box-shadow: inset 0 0 0 1px #e0e0e0; pointer-events: none; }
.chrome { height: ${ BAR }px; background: #f7f7f7; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; padding: 0 10px; gap: 6px; }
.chrome b { width: 8px; height: 8px; border-radius: 50%; background: #e0e0e0; flex: none; }
.url { margin-left: 8px; flex: 1; height: 22px; border-radius: 6px; background: #fff; box-shadow: inset 0 0 0 1px #e0e0e0; display: flex; align-items: center; padding: 0 9px; font-size: 12px; color: #6c6c6c; white-space: nowrap; overflow: hidden; }
.url strong { color: #003da5; font-weight: 650; }
.window img { display: block; width: 100%; height: auto; }`;

const windowHtml = ( { host, path }, cropDataUri ) =>
	`<div class="window"><div class="chrome"><b></b><b></b><b></b><div class="url">${ escapeHtml( host ) }<strong>${ escapeHtml( path ) }</strong></div></div><img alt="" src="${ cropDataUri }"></div>`;

/**
 * The 772x250 banner page (Inter with the optical-size axis, which sets the headline's width and so where it wraps) for a plugin config.
 *
 * Config: name, markPath, headline { before, accent, after }, headlineSize,
 * description, leftWidth, frame { x } (the bare screenshot's left edge), or
 * frame { x, y, width, window: { host, path } } to draw the screenshot inside
 * a browser window whose 34px bar shows the address.
 * `cropDataUri` is the cropped capture; `cropDrawHeight` its drawn height.
 */
export function bannerHtml( config, { cropDataUri, cropDrawHeight } ) {
	const font = readFileSync( config.fontPath ?? DEFAULT_FONT_PATH ).toString( 'base64' );
	const { before, accent, after } = config.headline;
	return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
@font-face { font-family: "Inter"; font-weight: 100 900; font-style: normal; src: url(data:font/woff2;base64,${ font }) format("woff2"); }
* { box-sizing: border-box; }
html, body { margin: 0; background: #fff; }
.banner { width: 772px; height: 250px; position: relative; overflow: hidden; background: #fff; font-family: "Inter", sans-serif; color: #1e1e1e; -webkit-font-smoothing: antialiased; }
.left { position: absolute; left: 40px; top: 0; bottom: 0; width: ${ config.leftWidth }px; display: flex; flex-direction: column; justify-content: center; z-index: 2; }
.wordmark { display: flex; align-items: center; gap: 4px; font-weight: 650; font-size: 15px; letter-spacing: -0.01em; margin-bottom: 16px; }
.mark { width: 28px; height: 28px; margin-left: -3px; fill: #003da5; flex: none; }
.headline { font-size: ${ config.headlineSize }px; line-height: 1.08; font-weight: 750; letter-spacing: -0.035em; margin: 0 0 12px; text-wrap: balance; }
.headline em { font-style: normal; color: #003da5; }
.line { margin: 0; font-size: 14px; line-height: 1.45; font-weight: 400; color: #5d5d5d; max-width: 32ch; }
${ config.frame.window ? WINDOW_CSS( config.frame ) : `.shot { position: absolute; left: ${ config.frame.x }px; top: 0; width: ${ config.crop.drawWidth }px; height: ${ cropDrawHeight }px; display: block; }` }
</style>
</head>
<body>
<div class="banner">
	<div class="left">
		<div class="wordmark"><svg class="mark" viewBox="0 0 24 24" aria-hidden="true"><path d="${ config.markPath }"/></svg>${ escapeHtml( config.name ) }</div>
		<p class="headline">${ escapeHtml( before ) }<em>${ escapeHtml( accent ) }</em>${ escapeHtml( after ) }</p>
		<p class="line">${ escapeHtml( config.description ) }</p>
	</div>
	${ config.frame.window ? windowHtml( config.frame.window, cropDataUri ) : `<img class="shot" alt="" src="${ cropDataUri }">` }
</div>
</body>
</html>
`;
}
