<?php
/**
 * Template for the lite site archive page.
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
	<title><?php bloginfo( 'name' ); ?></title>
	<?php $font_import_url = Lite_Site::get_font_import_url(); ?>
	<?php if ( $font_import_url ) : ?>
		<link rel="stylesheet" href="<?php echo esc_url( $font_import_url ); ?>">
	<?php endif; ?>
	<?php require __DIR__ . '/lite-site-styles.php'; ?>
	<?php
	$ga4_measurement_id = Lite_Site::get_ga4_measurement_id();
	if ( $ga4_measurement_id ) :
		?>
		<!-- Global site tag (gtag.js) - Google Analytics -->
		<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $ga4_measurement_id ); ?>"></script>
		<script>
			window.dataLayer = window.dataLayer || [];
			function gtag(){dataLayer.push(arguments);}
			gtag('js', new Date());
			gtag('config', '<?php echo esc_attr( $ga4_measurement_id ); ?>');
		</script>
	<?php endif; ?>
</head>
<body>
	<header class="back">
		<a href="<?php echo esc_url( home_url() ); ?>"><?php esc_html_e( 'View full site', 'newspack-lite-site' ); ?></a>
	</header>
	<h1><?php bloginfo( 'name' ); ?></h1>
	<hr class="separator">
	<ul class="post-list">
		<?php require __DIR__ . '/post-list.php'; ?>
	</ul>
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
	 * Fires after the footer of the lite site archive page.
	 */
	do_action( 'newspack_lite_site_archive_after_footer' );
	?>

</body>
</html>
