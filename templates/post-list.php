<?php
/**
 * Template partial for rendering the post list on the archive page.
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

if ( empty( $query ) || empty( $query->posts ) ) {
	return;
}

$sticky_post_ids = (array) get_option( 'sticky_posts', [] );

foreach ( $query->posts as $current_post ) :

	$is_sticky = in_array( $current_post->ID, $sticky_post_ids, true );
	$title_tag = $is_sticky ? 'h3' : 'span';
	?>

	<li>
		<<?php echo esc_html( $title_tag ); ?>>
			<a href="<?php echo esc_url( Lite_Site::get_lite_page_url( $current_post ) ); ?>">
				<?php echo esc_html( get_the_title( $current_post ) ); ?>
			</a>
		</<?php echo esc_html( $title_tag ); ?>>
	</li>

<?php endforeach; ?>
