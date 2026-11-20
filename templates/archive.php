<?php
/**
 * Template for the lite site archive page.
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included from Lite_Site::handle_lite_site_templates(), so these variables are local to that method.

$posts_per_page      = Lite_Site::get_posts_per_page();
$categories          = Lite_Site::get_categories();
$tags                = Lite_Site::get_tags();
$excluded_categories = Lite_Site::get_excluded_categories();
$excluded_tags       = Lite_Site::get_excluded_tags();
$current_page        = max( 1, absint( get_query_var( 'lite_page', 1 ) ) );

$query_args = [
	'post_type'      => [ 'post', Post_Type::POST_TYPE ],
	'post_status'    => 'publish',
	'has_password'   => false,
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

$query       = new \WP_Query( $query_args );
$total_pages = max( 1, (int) $query->max_num_pages );

// A page number past the end has no posts to show. Serving it as an empty
// page leaves a reader unable to tell a stale link from a broken site, so
// answer the way the rest of the web does.
if ( $current_page > $total_pages ) {
	require __DIR__ . '/not-found.php';
	return;
}

$has_posts = ! empty( $query->posts );

?>
<!DOCTYPE html>
<html lang="<?php bloginfo( 'language' ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex, follow">
	<title><?php bloginfo( 'name' ); ?></title>
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
		<h1><?php bloginfo( 'name' ); ?></h1>
		<hr class="separator">
		<?php if ( $has_posts ) : ?>
			<ul class="post-list">
				<?php require __DIR__ . '/post-list.php'; ?>
			</ul>
		<?php else : ?>
			<div class="content">
				<p><?php esc_html_e( 'Nothing to show here yet.', 'newspack-lite-site' ); ?></p>
				<?php if ( $categories || $tags || $excluded_categories || $excluded_tags ) : ?>
					<p><?php esc_html_e( 'No published post matches the categories or tags this Lite Site is filtered to.', 'newspack-lite-site' ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

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
			<nav class="pagination" aria-label="<?php esc_attr_e( 'Pagination', 'newspack-lite-site' ); ?>">
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
