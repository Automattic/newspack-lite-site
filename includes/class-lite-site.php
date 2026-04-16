<?php
/**
 * Lite Site functionality
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

/**
 * Lite Site class
 */
class Lite_Site {
	/**
	 * The option name for storing settings
	 */
	const OPTION_NAME = 'newspack_lite_site_settings';

	/**
	 * Initialize the lite site functionality
	 */
	public static function init() {
		// Only register rewrite rules if the feature is enabled.
		if ( self::is_enabled() ) {
			add_action( 'init', [ __CLASS__, 'register_rewrite_rules' ] );
			add_filter( 'query_vars', [ __CLASS__, 'register_query_vars' ] );
			add_action( 'template_redirect', [ __CLASS__, 'handle_lite_site_templates' ] );
		}
	}

	/**
	 * Check if the lite site feature is enabled
	 */
	public static function is_enabled() {
		$settings = get_option( self::OPTION_NAME, [] );
		return ! empty( $settings['enabled'] );
	}

	/**
	 * Check if the current request is for the lite site.
	 *
	 * @return bool True if it's a lite site request, false otherwise.
	 */
	public static function is_lite_site_request() {
		$is_lite = get_query_var( 'is_lite' );
		return ! empty( $is_lite );
	}

	/**
	 * Get the URL base (slug used for lite pages)
	 */
	public static function get_url_base() {
		$settings = get_option( self::OPTION_NAME, [] );
		return ! empty( $settings['url_base'] ) ? $settings['url_base'] : 'lite';
	}

	/**
	 * Get the number of posts to display in the archive
	 */
	public static function get_number_of_posts() {
		$settings = get_option( self::OPTION_NAME, [] );
		return ! empty( $settings['number_of_posts'] ) ? intval( $settings['number_of_posts'] ) : 20;
	}

	/**
	 * Get the selected categories
	 */
	public static function get_categories() {
		$settings = get_option( self::OPTION_NAME, [] );
		return ! empty( $settings['categories'] ) ? (array) $settings['categories'] : [];
	}

	/**
	 * Get the footer HTML
	 */
	public static function get_footer_html() {
		$settings = get_option( self::OPTION_NAME, [] );
		return ! empty( $settings['footer_html'] ) ? $settings['footer_html'] : '';
	}

	/**
	 * Get the GA4 Measurement ID
	 */
	public static function get_ga4_measurement_id() {
		$settings = get_option( self::OPTION_NAME, [] );
		return ! empty( $settings['ga4_measurement_id'] ) ? $settings['ga4_measurement_id'] : '';
	}

	/**
	 * Get the primary color
	 *
	 * @return string The primary color.
	 */
	public static function get_primary_color() {
		if ( ! function_exists( 'newspack_get_primary_color' ) ) {
			return 'currentcolor';
		}

		$primary_color = newspack_get_primary_color();

		if ( 'default' !== get_theme_mod( 'theme_colors' ) ) {
			$primary_color = get_theme_mod( 'primary_color_hex', $primary_color );
		}

		return $primary_color;
	}

	/**
	 * Get the lite version URL for a post
	 *
	 * @param WP_Post $post The post object.
	 * @return string
	 */
	public static function get_lite_page_url( $post ) {
		$permalink = untrailingslashit( get_permalink( $post ) );
		$path      = ltrim( str_replace( untrailingslashit( home_url() ), '', $permalink ), '/' );
		return home_url( self::get_url_base() . '/' . $path );
	}

	/**
	 * Register rewrite rules for lite site pages
	 */
	public static function register_rewrite_rules() {
		$url_base = self::get_url_base();

		// Archive: /{url_base}.
		add_rewrite_rule(
			'^' . $url_base . '/?$',
			'index.php?is_lite=archive',
			'top'
		);

		// Single: /{url_base}/{post-slug}.
		add_rewrite_rule(
			'^' . $url_base . '/(.+)/?$',
			'index.php?is_lite=single&lite_path=$matches[1]',
			'top'
		);
	}

	/**
	 * Register custom query variables
	 *
	 * @param array $vars The array of query variables.
	 * @return array The modified array of query variables.
	 */
	public static function register_query_vars( $vars ) {
		$vars[] = 'is_lite';
		$vars[] = 'lite_path';
		return $vars;
	}

	/**
	 * Handle template routing for lite site pages
	 */
	public static function handle_lite_site_templates() {
		$is_lite = get_query_var( 'is_lite' );

		if ( ! $is_lite ) {
			return;
		}

		// Disable all other output.
		remove_all_actions( 'wp_head' );
		remove_all_actions( 'wp_footer' );

		if ( 'archive' === $is_lite ) {
			include_once NEWSPACK_LITE_SITE_PLUGIN_DIR . 'templates/archive.php';
			exit;
		}

		if ( 'single' === $is_lite ) {
			include_once NEWSPACK_LITE_SITE_PLUGIN_DIR . 'templates/single.php';
			exit;
		}
	}

	/**
	 * Get the supported post types for lite site rendering
	 *
	 * @return string[]
	 */
	public static function get_supported_post_types() {
		/**
		 * Filter the post types eligible for lite site rendering.
		 *
		 * @param string[] $types Array of post type slugs.
		 */
		return apply_filters( 'newspack_lite_site_supported_post_types', [ 'post', 'page' ] );
	}

	/**
	 * Resolve a URL path to a published WP_Post
	 *
	 * @param string $path URL path without leading slash.
	 * @return WP_Post|null
	 */
	public static function resolve_post( $path ) {
		if ( empty( $path ) ) {
			return null;
		}

		// Support numeric post IDs.
		if ( is_numeric( $path ) ) {
			$post = get_post( (int) $path );
			return ( $post && 'publish' === $post->post_status ) ? $post : null;
		}

		$url       = home_url( '/' . ltrim( $path, '/' ) );
		$cache_key = 'nls_post_' . md5( $url );
		$post_id   = wp_cache_get( $cache_key, 'newspack_lite_site' );

		if ( false === $post_id ) {
			$post_id = url_to_postid( $url ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.url_to_postid_url_to_postid

			if ( ! $post_id ) {
				$post_id = url_to_postid( trailingslashit( $url ) ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.url_to_postid_url_to_postid
			}

			wp_cache_set( $cache_key, (int) $post_id, 'newspack_lite_site' );
		}

		if ( ! $post_id ) {
			return null;
		}

		$post = get_post( $post_id );

		return ( $post && 'publish' === $post->post_status ) ? $post : null;
	}

	/**
	 * Get the author(s) for a post
	 *
	 * @param WP_Post $post The post object.
	 * @return string The formatted author(s) string with links.
	 */
	public static function get_authors( $post ) {
		if ( function_exists( 'get_coauthors' ) ) {
			$authors      = get_coauthors( $post->ID );
			$author_links = array_map(
				function ( $author ) {
					return sprintf(
						'<a href="%s">%s</a>',
						esc_url( get_author_posts_url( $author->ID, $author->user_nicename ) ),
						esc_html( $author->display_name )
					);
				},
				$authors
			);

			if ( count( $author_links ) > 1 ) {
				$last_author   = array_pop( $author_links );
				$first_authors = implode(
					', ',
					$author_links
				);

				$author_string = sprintf(
					/* translators: %1$s: a comma separated list of authors names with links and, after the "and" %2$s: one last author link */
					__( '%1$s and %2$s', 'newspack-lite-site' ),
					$first_authors,
					$last_author
				);

			} else {
				$author_string = $author_links[0] ?? '';
			}
		} else {
			$author_string = sprintf(
				'<a href="%s">%s</a>',
				esc_url( get_author_posts_url( $post->post_author ) ),
				esc_html( get_the_author_meta( 'display_name', $post->post_author ) )
			);
		}

		return sprintf(
			/* translators: %s: author name(s) */
			__( 'By %s', 'newspack-lite-site' ),
			$author_string
		);
	}

	/**
	 * Clean the post content for lite display
	 *
	 * @param string $content The post content.
	 * @return string The cleaned content.
	 */
	public static function clean_content( $content ) {
		// Remove HTML comments.
		$content = preg_replace( '/<!--(.|\s)*?-->/', '', $content );

		// First remove figures and their contents (including images and captions).
		$content = preg_replace( '/<figure.*?>.*?<\/figure>/is', '', $content );

		// Remove script tags.
		$content = preg_replace( '/<script.*?>.*?<\/script>/is', '', $content );

		// Define allowed HTML elements for text-only content.
		$allowed_html = [
			'p'          => [],
			'h1'         => [],
			'h2'         => [],
			'h3'         => [],
			'h4'         => [],
			'h5'         => [],
			'h6'         => [],
			'ul'         => [],
			'ol'         => [],
			'li'         => [],
			'blockquote' => [],
			'strong'     => [],
			'em'         => [],
			'b'          => [],
			'i'          => [],
			'a'          => [
				'href'  => true,
				'title' => true,
			],
			'br'         => [],
		];

		// Strip all HTML except allowed elements.
		$content = wp_kses( $content, $allowed_html );

		// Clean up any empty paragraphs.
		$content = preg_replace( '/<p>\s*<\/p>/', '', $content );

		return $content;
	}
}
