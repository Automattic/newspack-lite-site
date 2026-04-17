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
	<?php $font_import_url = Lite_Site::get_font_import_url(); ?>
	<?php if ( $font_import_url ) : ?>
		<link rel="stylesheet" href="<?php echo esc_url( $font_import_url ); ?>">
	<?php endif; ?>
	<?php require __DIR__ . '/lite-site-styles.php'; ?>
	<?php if ( $is_liveblog ) : ?>
		<?php require __DIR__ . '/liveblog-styles.php'; ?>
	<?php endif; ?>
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

	<?php if ( $post_content ) : ?>
		<div class="content">
			<?php echo wp_kses_post( $post_content ); ?>
		</div>
		<?php if ( $is_liveblog ) : ?>
			<hr class="separator">
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $is_liveblog ) : ?>
		<div class="liveblog-status <?php echo esc_attr( $liveblog_state ); ?>">
			<?php if ( 'enable' === $liveblog_state ) : ?>
				<?php esc_html_e( 'Live', 'newspack-lite-site' ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Archived', 'newspack-lite-site' ); ?>
			<?php endif; ?>
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
							<?php echo wp_kses_post( Lite_Site::clean_content( $entry->comment_content ) ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p>
				<?php
				esc_html_e( 'There are no entries on this page.', 'newspack-lite-site' );
				?>
			</p>
		<?php endif; ?>
	<?php endif; ?>
	
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
