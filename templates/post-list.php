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
$external_links_new_tab = Lite_Site::get_external_links_new_tab();

foreach ( $query->posts as $current_post ) :

	$is_sticky   = in_array( $current_post->ID, $sticky_post_ids, true );
	$title_tag   = $is_sticky ? 'h3' : 'span';
	$post_url    = Lite_Site::get_lite_page_url( $current_post );
	$is_external = Lite_Site::is_external_url( $post_url );

	if ( $is_external && $external_links_new_tab ) {
		$anchor_template = '<a href="%s" class="lite-site-external" target="_blank" rel="noopener noreferrer">%s</a>';
	} elseif ( $is_external ) {
		$anchor_template = '<a href="%s" class="lite-site-external">%s</a>';
	} else {
		$anchor_template = '<a href="%s">%s</a>';
	}
	?>

	<li>
		<<?php echo esc_html( $title_tag ); ?>>
			<?php
			echo wp_kses_post(
				sprintf(
					$anchor_template,
					esc_url( $post_url ),
					esc_html( get_the_title( $current_post ) )
				)
			);
			?>
		</<?php echo esc_html( $title_tag ); ?>>
	</li>

<?php endforeach; ?>
