<?php
/**
 * Template for the lite site offline page.
 *
 * Served by the PWA plugin's service worker when the user is offline
 * and the requested page has not been cached.
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

defined( 'ABSPATH' ) || exit;

?>
<!DOCTYPE html>
<html lang="<?php bloginfo( 'language' ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'You\'re offline', 'newspack-lite-site' ); ?> - <?php bloginfo( 'name' ); ?></title>
	<?php Lite_Site::print_font_stylesheet(); ?>
	<?php require __DIR__ . '/lite-site-styles.php'; ?>
</head>
<body>
	<header class="back">
		<a href="<?php echo esc_url( home_url( '/' . Lite_Site::get_url_base() ) ); ?>">← <?php esc_html_e( 'Back to Lite Site homepage', 'newspack-lite-site' ); ?></a>
	</header>
	<main>
		<h1><?php esc_html_e( 'You\'re offline', 'newspack-lite-site' ); ?></h1>
		<hr class="separator">
		<?php if ( function_exists( 'wp_service_worker_error_message_placeholder' ) ) : ?>
			<?php wp_service_worker_error_message_placeholder(); ?>
		<?php endif; ?>
	</main>
	<?php
	$newspack_lite_site_footer_html = Lite_Site::get_footer_html();
	if ( ! empty( $newspack_lite_site_footer_html ) ) :
		?>
		<hr class="separator">
		<footer class="site-footer">
			<?php echo wp_kses_post( $newspack_lite_site_footer_html ); ?>
		</footer>
	<?php endif; ?>

	<?php
	/**
	 * Fires after the footer of the lite site offline page.
	 */
	do_action( 'newspack_lite_site_offline_after_footer' );
	?>
</body>
</html>
