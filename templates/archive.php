<?php
/**
 * Template for the lite site archive page.
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

$posts_per_page      = Lite_Site::get_posts_per_page();
$categories          = Lite_Site::get_categories();
$tags                = Lite_Site::get_tags();
$excluded_categories = Lite_Site::get_excluded_categories();
$excluded_tags       = Lite_Site::get_excluded_tags();
$current_page        = max( 1, absint( get_query_var( 'lite_page', 1 ) ) );

$query_args = [
	'post_type'      => [ 'post', Post_Type::POST_TYPE ],
	'post_status'    => 'publish',
	'posts_per_page' => $posts_per_page,
	'paged'          => $current_page,
];
if ( ! empty( $categories ) ) {
	$query_args['category__in'] = $categories;
}
if ( ! empty( $tags ) ) {
	$query_args['tag__in'] = $tags;
}
if ( ! empty( $excluded_categories ) ) {
	$query_args['category__not_in'] = $excluded_categories;
}
if ( ! empty( $excluded_tags ) ) {
	$query_args['tag__not_in'] = $excluded_tags;
}

$query        = new \WP_Query( $query_args );
$total_pages  = max( 1, (int) $query->max_num_pages );
$current_page = min( $current_page, $total_pages );

?>
<!DOCTYPE html>
<html lang="<?php bloginfo( 'language' ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php bloginfo( 'name' ); ?></title>
	<?php
	$font_import_url = Lite_Site::get_font_import_url();
	if ( $font_import_url ) :
		?>
		<link rel="stylesheet" href="<?php echo esc_url( $font_import_url ); ?>">
		<?php
	endif;
	require __DIR__ . '/lite-site-styles.php';
	$custom_css = Lite_Site::get_custom_css();
	if ( $custom_css ) :
		?>
		<style><?php echo wp_kses( $custom_css, [] ); ?></style>
		<?php
	endif;
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
	<main>
		<h1><?php bloginfo( 'name' ); ?></h1>
		<hr class="separator">
		<ul class="post-list">
			<?php require __DIR__ . '/post-list.php'; ?>
		</ul>

		<?php
		$pagination = paginate_links(
			[
				'base'    => home_url( Lite_Site::get_url_base() . '/%_%' ),
				'format'  => 'page/%#%/',
				'current' => $current_page,
				'total'   => $total_pages,
			]
		);
		if ( $pagination ) :
			?>
			<nav class="pagination" aria-label="<?php esc_attr_e( 'Archive pagination', 'newspack-lite-site' ); ?>">
				<?php echo wp_kses_post( $pagination ); ?>
			</nav>
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
	 * Fires after the footer of the lite site archive page.
	 */
	do_action( 'newspack_lite_site_archive_after_footer' );
	?>

</body>
</html>
