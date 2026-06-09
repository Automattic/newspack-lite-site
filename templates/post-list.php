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

$sticky_post_ids        = (array) get_option( 'sticky_posts', [] );
$external_links_new_tab  = Lite_Site::get_external_links_new_tab();

foreach ( $query->posts as $current_post ) :

	$is_sticky   = in_array( $current_post->ID, $sticky_post_ids, true );
	$title_tag   = $is_sticky ? 'h3' : 'span';
	$post_url    = Lite_Site::get_lite_page_url( $current_post );
	$is_external = Lite_Site::is_external_url( $post_url );
	?>

	<li>
		<<?php echo esc_html( $title_tag ); ?>>
			<a
				href="<?php echo esc_url( $post_url ); ?>"
				<?php if ( $is_external ) : ?>
					class="lite-site-external"
					<?php if ( $external_links_new_tab ) : ?>
						target="_blank"
						rel="noopener noreferrer"
					<?php endif; ?>
				<?php endif; ?>
			>
				<?php echo esc_html( get_the_title( $current_post ) ); ?>
			</a>
		</<?php echo esc_html( $title_tag ); ?>>
	</li>

<?php endforeach; ?>
