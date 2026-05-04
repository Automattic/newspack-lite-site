<?php
/**
 * Template partial for rendering the post list on the archive page.
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

$query_args = [
	'posts_per_page' => Lite_Site::get_number_of_posts(),
	'post_status'    => 'publish',
];

$categories = Lite_Site::get_categories();
if ( ! empty( $categories ) ) {
	$query_args['category__in'] = $categories;
}

// Render sticky posts prominently at the top.
$sticky_post_ids_option = get_option( 'sticky_posts' );
$sticky_post_ids        = [];
$sticky_posts           = [];
if ( ! empty( $sticky_post_ids_option ) ) {
	$sticky_post_ids = array_values( $sticky_post_ids_option );
	$sticky_posts    = get_posts(
		[
			'post__in' => $sticky_post_ids,
		]
	);
}

$recent_posts = get_posts( $query_args );

$all_posts = array_merge( $sticky_posts, $recent_posts );

$all_posts = array_unique( $all_posts, SORT_REGULAR );

$all_posts = array_slice( $all_posts, 0, Lite_Site::get_number_of_posts() );

foreach ( $all_posts as $current_post ) {
	$is_sticky = in_array( $current_post->ID, $sticky_post_ids, true );
	printf(
		'<li>%s<a href="%s">%s</a>%s</li>',
		$is_sticky ? '<h3>' : '',
		esc_url( Lite_Site::get_lite_page_url( $current_post ) ),
		esc_html( $current_post->post_title ),
		$is_sticky ? '</h3>' : ''
	);
}
