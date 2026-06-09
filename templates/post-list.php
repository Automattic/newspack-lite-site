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

$sticky_post_ids         = (array) get_option( 'sticky_posts', [] );
$external_links_new_tab  = Lite_Site::get_external_links_new_tab();

foreach ( $query->posts as $current_post ) :

	$is_sticky   = in_array( $current_post->ID, $sticky_post_ids, true );
	$title_tag   = $is_sticky ? 'h3' : 'span';
	$post_url    = Lite_Site::get_lite_page_url( $current_post );
	$is_external = Lite_Site::is_external_url( $post_url );

	$link_attrs = '';
	if ( $is_external ) {
		$link_attrs = ' class="lite-site-external"';
		if ( $external_links_new_tab ) {
			$link_attrs .= ' target="_blank" rel="noopener noreferrer"';
		}
	}
	?>

	<li>
		<<?php echo esc_html( $title_tag ); ?>>
			<?php
			printf(
				'<a href="%s"%s>%s</a>',
				esc_url( $post_url ),
				$link_attrs,    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded safe HTML attributes, never user input.
				esc_html( get_the_title( $current_post ) )
			);
			?>
		</<?php echo esc_html( $title_tag ); ?>>
	</li>

<?php endforeach; ?>
