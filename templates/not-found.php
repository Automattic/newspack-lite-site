<?php
/**
 * Template for a lite site page that has nothing to show.
 *
 * Used for an address with no article behind it, and for a page number past
 * the end of the list. A reader who follows a stale emergency link lands
 * here, so it carries the same header and footer as every other lite page
 * and always offers a way onward.
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Required from the lite templates, so these variables are local to the method that includes them.

Lite_Site::mark_not_found();
status_header( 404 );

/*
 * A page cache in front of PHP would otherwise store this answer: a reader who
 * asks for an article seconds before it publishes would pin a 404 at the edge
 * that outlives the post. The transient guard above only covers this plugin's
 * own cache.
 */
nocache_headers();

$lite_home = home_url( Lite_Site::get_url_base() . '/' );
?>
<!DOCTYPE html>
<html lang="<?php bloginfo( 'language' ); ?>">
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex, follow">
	<title>
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: "Page not found", 2: the site's name. */
				__( '%1$s - %2$s', 'newspack-lite-site' ),
				__( 'Page not found', 'newspack-lite-site' ),
				get_bloginfo( 'name' )
			)
		);
		?>
	</title>
	<?php
	Lite_Site::print_font_stylesheet();
	require __DIR__ . '/lite-site-styles.php';
	Lite_Site::print_custom_css();
	Lite_Site::print_analytics_tag();
	?>
</head>
<body>
	<header class="back">
		<a href="<?php echo esc_url( home_url() ); ?>"><?php esc_html_e( 'View full site', 'newspack-lite-site' ); ?></a>
	</header>
	<main>
		<h1><?php esc_html_e( 'Page not found', 'newspack-lite-site' ); ?></h1>
		<hr class="separator">
		<?php
		/*
		 * The copy below sits outside .content on purpose. That class colors
		 * links with the site's primary color, which a theme can resolve to
		 * its own background, and this page's only link has to be visible.
		 */
		?>
		<p><?php esc_html_e( 'This page is not here. It may have been moved, or the address may be wrong.', 'newspack-lite-site' ); ?></p>
		<p>
			<a href="<?php echo esc_url( $lite_home ); ?>"><?php esc_html_e( 'Go to the latest updates', 'newspack-lite-site' ); ?></a>
		</p>
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
	 * Fires after the footer of the lite site not-found page.
	 */
	do_action( 'newspack_lite_site_not_found_after_footer' );
	?>
</body>
</html>
