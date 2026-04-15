<?php
/**
 * Template for the lite site single post page
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

$current_post = Lite_Site::resolve_post( get_query_var( 'lite_path' ) );

if ( ! $current_post || ! in_array( $current_post->post_type, Lite_Site::get_supported_post_types(), true ) ) {
	status_header( 404 );
	exit( 'Post not found' );
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
		<a href="<?php echo esc_url( home_url( '/' . Lite_Site::get_url_base() ) ); ?>">← <?php esc_html_e( 'Back to posts', 'newspack-lite-site' ); ?></a> |
		<a href="<?php echo esc_url( get_permalink( $current_post ) ); ?>"><?php esc_html_e( 'View full article', 'newspack-lite-site' ); ?></a>
	</header>
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

	<div class="content">
		<?php echo wp_kses_post( Lite_Site::clean_content( $current_post->post_content ) ); ?>
	</div>
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
	 * @param \WP_Post $current_post The current post.
	 */
	do_action( 'newspack_lite_site_single_after_footer', $current_post );
	?>
</body>
</html>
