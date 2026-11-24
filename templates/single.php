<?php
/**
 * Template for the lite site single post page.
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from Lite_Site::include_single_template(), so these variables are local to that method.

$current_post = Lite_Site::resolve_post( get_query_var( 'lite_path' ) );

if ( ! $current_post || ! in_array( $current_post->post_type, Lite_Site::get_supported_post_types(), true ) ) {
	require __DIR__ . '/not-found.php';
	return;
}

$is_liveblog  = Lite_Site::is_liveblog( $current_post );
$post_content = Lite_Site::clean_content( $current_post->post_content );

if ( $is_liveblog ) {
	$liveblog_state   = Lite_Site::get_liveblog_state( $current_post );
	$liveblog_entries = Lite_Site::get_liveblog_entries( $current_post->ID );
}
?>
<!DOCTYPE html>
<html lang="<?php bloginfo( 'language' ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $current_post->post_title ); ?> - <?php bloginfo( 'name' ); ?></title>
	<link rel="canonical" href="<?php echo esc_url( get_permalink( $current_post ) ); ?>">
	<meta name="robots" content="noindex, follow">
	<?php
	Lite_Site::print_font_stylesheet();
	require __DIR__ . '/lite-site-styles.php';
	if ( $is_liveblog ) :
		require __DIR__ . '/liveblog-styles.php';
	endif;
	Lite_Site::print_custom_css();
	Lite_Site::print_analytics_tag();
	?>
</head>
<body>
	<header class="back">
		<a href="<?php echo esc_url( home_url( '/' . Lite_Site::get_url_base() ) ); ?>">← <?php esc_html_e( 'Back to Lite Site homepage', 'newspack-lite-site' ); ?></a> |
		<a href="<?php echo esc_url( get_permalink( $current_post ) ); ?>"><?php esc_html_e( 'View full version', 'newspack-lite-site' ); ?></a>
	</header>
	<main>
		<article>
			<h1><?php echo esc_html( $current_post->post_title ); ?></h1>
			<div class="meta">
				<div class="authors">
					<?php echo wp_kses_post( Lite_Site::get_authors( $current_post ) ); ?>
				</div>
				<div class="date">
					<?php echo esc_html( get_the_date( '', $current_post ) ); ?>
				</div>
			</div>
			<hr class="separator">

			<?php if ( $post_content ) : ?>
				<div class="content">
					<?php echo Lite_Site::wrap_tables_for_scrolling( wp_kses_post( $post_content ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized above; the wrapper adds only balanced div tags and an esc_attr__() label. ?>
				</div>
				<?php if ( $is_liveblog ) : ?>
					<hr class="separator">
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $is_liveblog ) : ?>
				<div class="liveblog-status <?php echo esc_attr( $liveblog_state ); ?>">
					<?php
					if ( 'enable' === $liveblog_state ) :
						esc_html_e( 'Live', 'newspack-lite-site' );
					else :
						esc_html_e( 'Archived', 'newspack-lite-site' );
					endif;
					?>
				</div>

				<?php if ( ! empty( $liveblog_entries ) ) : ?>
					<div class="liveblog-entries">
						<?php foreach ( $liveblog_entries as $entry ) : ?>
							<div class="liveblog-entry">
								<div class="liveblog-entry-meta">
									<time><?php echo esc_html( get_comment_date( 'g:i a', $entry ) ); ?></time>
									<?php if ( $entry->user_id ) : ?>
										&mdash; <?php echo esc_html( get_the_author_meta( 'display_name', $entry->user_id ) ); ?>
									<?php elseif ( $entry->comment_author ) : ?>
										&mdash; <?php echo esc_html( $entry->comment_author ); ?>
									<?php endif; ?>
								</div>
								<div class="liveblog-entry-content">
									<?php echo Lite_Site::wrap_tables_for_scrolling( wp_kses_post( Lite_Site::clean_content( $entry->comment_content ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized above; the wrapper adds only balanced div tags and an esc_attr__() label. ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p><?php esc_html_e( 'There are no entries on this page.', 'newspack-lite-site' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</article>
	</main>

	<?php
	$footer_html = Lite_Site::get_footer_html();
	if ( ! empty( $footer_html ) ) :
		?>
		<hr class="separator">
		<footer class="site-footer">
			<?php echo wp_kses_post( $footer_html ); ?>
		</footer>
	<?php endif; ?>

	<?php
	/**
	 * Fires after the footer of the lite site single post page.
	 *
	 * Callbacks run as a signed-out reader, like the rest of the page, since
	 * what they print can be cached and served to every reader.
	 *
	 * @param \WP_Post $current_post The current post.
	 */
	do_action( 'newspack_lite_site_single_after_footer', $current_post );
	?>
	
	<script>
		( function () {
			// A single template can render inside another, so this block can
			// be printed twice. A class declaration would throw on the second.
			if ( window.liteImageLoaderReady ) {
				return;
			}
			window.liteImageLoaderReady = true;

			function buildFigure( placeholder ) {
				const {
					src,
					srcset,
					alt = '',
					caption = '',
					width = '',
					height = '',
				} = placeholder.dataset;

				const figure = document.createElement( 'figure' );
				const img    = document.createElement( 'img' );

				// Not in the tab order, but focusable by script, so focus can
				// follow the image that replaces the button.
				figure.setAttribute( 'tabindex', '-1' );

				img.alt = alt;

				// With `w` descriptors and no sizes the browser assumes the
				// image fills the viewport and downloads a candidate for the
				// whole screen rather than for a 39.5rem column. Both are set
				// before src so the first fetch is the right one.
				if ( srcset ) {
					const width = Math.ceil( placeholder.getBoundingClientRect().width );

					if ( width > 0 ) {
						img.sizes = width + 'px';
					}

					img.srcset = srcset;
				}

				img.src = src;

				// Declared so the browser can reserve the space before the
				// bytes arrive. Without them the article reflows under the
				// reader, on exactly the connections this page is for.
				if ( width && height ) {
					img.width  = width;
					img.height = height;
				}

				figure.appendChild( img );

				if ( caption ) {
					const figcaption = document.createElement( 'figcaption' );
					figcaption.textContent = caption;
					figure.appendChild( figcaption );
				}

				return figure;
			}

			function handleClick( e ) {
				// Only a button, and only one whose class reached the page
				// from this plugin rather than from post content. A class is
				// not provenance on its own: everything a placeholder is made
				// of passes kses, so a Custom HTML block or an imported feed
				// item could write one and have this fetch whatever host it
				// named. What makes the class trustworthy is that it is
				// cleared from incoming content before any pass adds it. See
				// RESERVED_CLASSES.
				const btn = e.target.closest( 'button.lite-image-load-btn' );
				const placeholder = ( btn || e.target ).closest( '.lite-image-placeholder' );

				if ( ! placeholder || ! placeholder.dataset.src ) {
					return;
				}

				// Inside a linked image the whole placeholder sits in the
				// <a>, so a tap on the label or the caption follows the link
				// even though it never reached the button. Anywhere in the
				// placeholder loads the image instead.
				if ( ! btn && ! placeholder.closest( 'a' ) ) {
					return;
				}

				// The classic editor links an image to the full-size original
				// by default, so this button can sit inside an <a>. Without
				// this the tap would navigate the reader to that file -- the
				// heaviest asset on the site, over the connection this page
				// exists to protect -- instead of inlining the smaller one.
				// Once the image is in place the link works as it should.
				e.preventDefault();

				const figure = buildFigure( placeholder );
				placeholder.parentNode.replaceChild( figure, placeholder );

				// replaceChild destroys the button that was focused, which
				// drops a keyboard or screen reader user back to the document
				// with nothing announced. Move focus to what replaced it.
				figure.focus();
			}

			document.addEventListener( 'click', handleClick );
		}() );

		// A table wrapper is a tab stop, and announces that it scrolls, only
		// once it actually overflows. The server cannot know: the same table
		// fits in landscape and scrolls in portrait. Re-checked on resize so
		// turning the phone is enough to change it.
		( function () {
			const wrappers = document.querySelectorAll( '.lite-table-scroll' );

			if ( ! wrappers.length ) {
				return;
			}

			function sync() {
				wrappers.forEach( function ( wrapper ) {
					const scrolls = wrapper.scrollWidth > wrapper.clientWidth;

					if ( scrolls ) {
						wrapper.setAttribute( 'tabindex', '0' );
						wrapper.setAttribute( 'role', 'group' );
						wrapper.setAttribute( 'aria-label', wrapper.dataset.scrollLabel || '' );
						return;
					}

					wrapper.removeAttribute( 'tabindex' );
					wrapper.removeAttribute( 'role' );
					wrapper.removeAttribute( 'aria-label' );
				} );
			}

			sync();

			let pending = null;
			window.addEventListener( 'resize', function () {
				window.clearTimeout( pending );
				pending = window.setTimeout( sync, 150 );
			} );
		} )();
	</script>
</body>
</html>
