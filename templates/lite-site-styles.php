<?php
/**
 * Shared styles for lite site templates.
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

defined( 'ABSPATH' ) || exit;

?>
<style>
	body {
		font-family: <?php echo Lite_Site::get_font_family(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Quoted and stripped of characters that end a declaration or the style element; esc_html() would turn the quotes into entities. ?>;
		font-size: clamp( 1.125rem, 0.929rem + 0.402vw, 1.25rem );
		line-height: 1.6;
		margin: 0 auto;
		max-width: 39.5rem;
		padding: 2rem 1rem;
	}
	h1 {
		font-size: clamp( 1.75rem, -0.213rem + 4.016vw, 3rem );
		line-height: 1.25;
		margin: 0 0 2rem;
	}
	ul.post-list {
		list-style: none;
		padding-left: 0;
	}
	.post-list li {
		margin-bottom: 1rem;
	}
	a {
		color: currentcolor;
	}
	.content a {
		color: <?php echo esc_html( Lite_Site::get_primary_color() ); ?>;
	}
	.content figure {
		margin: 0 0 1.5rem;
	}
	@media ( prefers-reduced-motion: no-preference ) {
		@keyframes nls-fade-in {
			from { opacity: 0; }
			to { opacity: 1; }
		}
		.content figure {
			animation: nls-fade-in 0.3s ease;
		}
	}
	.content figure img {
		display: block;
		max-width: 100%;
		/* The loaded image carries the width and height the source declared,
		so the browser reserves its space before it arrives. Those are the
		image's own pixels, not the column's: without this the picture keeps
		its full height while max-width narrows it, and a tall photograph
		renders stretched on a phone and inside a table cell. */
		height: auto;
	}
	.content figcaption {
		font-size: 0.875rem;
		margin-top: 0.5rem;
	}
	.lite-table-scroll {
		margin: 0 0 1.5rem;
		overflow-x: auto;
	}
	.content table,
	.liveblog-entry-content table {
		border-collapse: collapse;
		margin: 0 0 1.5rem;
		max-width: 100%;
	}
	.lite-table-scroll table {
		margin: 0;
		max-width: none;
	}
	.lite-table-scroll:focus-visible {
		outline: 2px solid currentcolor;
		outline-offset: 2px;
	}
	.content caption,
	.liveblog-entry-content caption {
		font-size: 0.875rem;
		margin-bottom: 0.5rem;
		text-align: left;
	}
	.liveblog-entry-content figcaption {
		font-size: 0.875rem;
		margin-top: 0.5rem;
	}
	.liveblog-entry-content figure img {
		display: block;
		max-width: 100%;
		height: auto;
	}
	.content th,
	.content td,
	.liveblog-entry-content th,
	.liveblog-entry-content td {
		border: 1px solid currentcolor;
		padding: 0.375rem 0.5rem;
		text-align: left;
		vertical-align: top;
	}
	.content th,
	.liveblog-entry-content th {
		font-weight: 700;
	}
	.lite-image-placeholder {
		border: 1px solid currentcolor;
		display: block;
		margin: 0 0 1.5rem;
		padding: 1rem;
	}
	.lite-image-label {
		display: block;
		margin: 0 0 0.5rem;
	}
	.lite-image-caption {
		display: block;
		font-size: 0.875rem;
		margin: 0 0 1rem;
	}
	.lite-image-load-btn {
		background: transparent;
		border: 1px solid currentcolor;
		cursor: pointer;
		font-family: inherit;
		padding: 0.375rem 0.75rem;
	}
	hr.separator {
		border: 0.125rem solid <?php echo esc_html( Lite_Site::get_primary_color() ); ?>;
		margin: 2rem 0;
	}
	.back {
		display: block;
		font-size: 1rem;
		line-height: 1.5;
		margin: 0 0 0.5rem;
	}
	.meta {
		color: currentcolor;
		margin-bottom: 2rem;
	}
	.meta .date {
		margin-top: 0.5rem;
	}
	.site-footer {
		font-size: 1rem;
		line-height: 1.5;
		margin-top: 2rem;
	}
	.site-footer > :last-child {
		margin-bottom: 0;
	}
	.pagination {
		display: flex;
		flex-wrap: wrap;
		font-size: 1rem;
		gap: 0.5rem;
		justify-content: center;
		margin-top: 2rem;
	}
	.pagination .page-numbers {
		padding: 0 0.25rem;
	}

	.lite-site-external::after {
		content: " ↗";
		font-size: 0.8em;
		opacity: 0.7;
	}

	<?php
	/**
	 * Fires at the end of the style tag in lite site templates.
	 */
	do_action( 'newspack_lite_site_styles' );
	?>

</style>
