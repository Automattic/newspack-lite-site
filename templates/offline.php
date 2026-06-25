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

?>
<!DOCTYPE html>
<html lang="<?php bloginfo( 'language' ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'You are offline', 'newspack-lite-site' ); ?> - <?php bloginfo( 'name' ); ?></title>
	<?php $font_import_url = Lite_Site::get_font_import_url(); ?>
	<?php if ( $font_import_url ) : ?>
		<link rel="stylesheet" href="<?php echo esc_url( $font_import_url ); ?>">
	<?php endif; ?>
	<?php require __DIR__ . '/lite-site-styles.php'; ?>
</head>
<body>
	<header class="back">
		<a href="<?php echo esc_url( home_url( '/' . Lite_Site::get_url_base() ) ); ?>">← <?php esc_html_e( 'Back to Lite Site homepage', 'newspack-lite-site' ); ?></a>
	</header>
	<main>
		<h1><?php esc_html_e( 'You are offline', 'newspack-lite-site' ); ?></h1>
		<hr class="separator">
		<?php if ( function_exists( 'wp_service_worker_error_message_placeholder' ) ) : ?>
			<?php wp_service_worker_error_message_placeholder(); ?>
		<?php endif; ?>
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
	 * Fires after the footer of the lite site offline page.
	 */
	do_action( 'newspack_lite_site_offline_after_footer' );
	?>
</body>
</html>
