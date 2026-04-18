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
		$settings = get_option( Lite_Site_Settings::OPTION_NAME, [] );
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
		$settings = get_option( Lite_Site_Settings::OPTION_NAME, [] );
		return ! empty( $settings['url_base'] ) ? $settings['url_base'] : 'lite';
	}

	/**
	 * Get the number of posts to display in the archive
	 */
	public static function get_number_of_posts() {
		$settings = get_option( Lite_Site_Settings::OPTION_NAME, [] );
		return ! empty( $settings['number_of_posts'] ) ? intval( $settings['number_of_posts'] ) : 20;
	}

	/**
	 * Get the selected categories
	 */
	public static function get_categories() {
		$settings = get_option( Lite_Site_Settings::OPTION_NAME, [] );
		return ! empty( $settings['categories'] ) ? (array) $settings['categories'] : [];
	}

	/**
	 * Get the footer HTML
	 */
	public static function get_footer_html() {
		$settings = get_option( Lite_Site_Settings::OPTION_NAME, [] );
		return ! empty( $settings['footer_html'] ) ? $settings['footer_html'] : '';
	}

	/**
	 * Get the GA4 Measurement ID
	 */
	public static function get_ga4_measurement_id() {
		$settings = get_option( Lite_Site_Settings::OPTION_NAME, [] );
		return ! empty( $settings['ga4_measurement_id'] ) ? $settings['ga4_measurement_id'] : '';
	}

	/**
	 * Get the primary color (admin override takes priority, then theme default)
	 *
	 * @return string The primary color.
	 */
	public static function get_primary_color() {
		$settings = get_option( Lite_Site_Settings::OPTION_NAME, [] );
		if ( ! empty( $settings['primary_color'] ) ) {
			return $settings['primary_color'];
		}

		return self::get_theme_primary_color();
	}

	/**
	 * Get the theme's primary color, ignoring any admin override.
	 *
	 * @return string Hex color or 'currentcolor' if undetectable.
	 */
	public static function get_theme_primary_color() {
		if ( wp_is_block_theme() ) {
			$settings = wp_get_global_settings();
			$palettes = $settings['color']['palette'] ?? [];
			$palette  = [];
			foreach ( [ 'custom', 'theme', 'default' ] as $origin ) {
				if ( ! empty( $palettes[ $origin ] ) ) {
					$palette = $palettes[ $origin ];
					break;
				}
			}
			return $palette[0]['color'] ?? 'currentcolor';
		}

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
	 * Get the font import URL set in the lite site settings
	 *
	 * @return string Font provider URL, or empty string if not set.
	 */
	public static function get_font_import_url() {
		$settings = get_option( Lite_Site_Settings::OPTION_NAME, [] );
		return ! empty( $settings['font_import_url'] ) ? $settings['font_import_url'] : '';
	}

	/**
	 * Get the font family for the lite site body text
	 *
	 * @return string CSS font-family value.
	 */
	public static function get_font_family() {
		$settings  = get_option( Lite_Site_Settings::OPTION_NAME, [] );
		$font_body = ! empty( $settings['font_body'] ) ? $settings['font_body'] : '';
		if ( ! empty( $font_body ) ) {
			return $font_body;
		}

		return 'system-ui, -apple-system, sans-serif';
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
	 * Check if a post is an active or archived liveblog.
	 *
	 * @param WP_Post $post The post object.
	 * @return bool
	 */
	public static function is_liveblog( $post ) {
		$state = self::get_liveblog_state( $post );
		return in_array( $state, [ 'enable', 'archive' ], true );
	}

	/**
	 * Get the liveblog state for a post.
	 *
	 * @param WP_Post $post The post object.
	 * @return string 'enable', 'archive', or 'disable'.
	 */
	public static function get_liveblog_state( $post ) {
		return get_post_meta( $post->ID, 'liveblog', true );
	}

	/**
	 * Get liveblog entries for a post, with replaced/deleted entries filtered out.
	 *
	 * @param int $post_id The post ID.
	 * @param int $limit   Max number of entries to return.
	 * @return WP_Comment[]
	 */
	public static function get_liveblog_entries( $post_id, $limit = 100 ) {
		$entries = get_comments(
			[
				'post_id' => $post_id,
				'type'    => 'liveblog',
				'status'  => 'liveblog',
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
				'number'  => $limit,
			]
		);

		if ( empty( $entries ) ) {
			return [];
		}

		return array_values(
			array_filter(
				$entries,
				function ( $entry ) {
					return ! get_comment_meta( $entry->comment_ID, 'liveblog_replaces', true );
				}
			)
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

		// Replace figures with lazy-load placeholders before stripping.
		$content = preg_replace_callback(
			'/<figure\b[^>]*>.*?<\/figure>/is',
			[ __CLASS__, 'figure_to_placeholder' ],
			$content
		);

		// Remove script tags.
		$content = preg_replace( '/<script.*?>.*?<\/script>/is', '', $content );

		// Define allowed HTML elements for text-only content.
		$allowed_html = [
			'p'          => [ 'class' => true ],
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
			'div'        => [
				'class'        => true,
				'data-src'     => true,
				'data-srcset'  => true,
				'data-alt'     => true,
				'data-caption' => true,
			],
			'button'     => [
				'class' => true,
				'type'  => true,
			],
		];

		// Strip all HTML except allowed elements.
		$content = wp_kses( $content, $allowed_html );

		// Clean up any empty paragraphs.
		$content = preg_replace( '/<p>\s*<\/p>/', '', $content );

		return $content;
	}

	/**
	 * Convert a <figure> element into a lazy-load image placeholder.
	 *
	 * @param array $matches Regex match array; $matches[0] is the full <figure> HTML.
	 * @return string Placeholder div HTML, or empty string if no image src found.
	 */
	private static function figure_to_placeholder( $matches ) {
		$figure_html = $matches[0];

		$src    = '';
		$srcset = '';
		$alt    = '';

		if ( preg_match( '/<img\b([^>]*)>/i', $figure_html, $img_match ) ) {
			$attrs = $img_match[1];
			if ( preg_match( '/\bsrc=["\']([^"\']+)["\']/', $attrs, $m ) ) {
				$src = $m[1];
			}
			if ( preg_match( '/\bsrcset=["\']([^"\']+)["\']/', $attrs, $m ) ) {
				$srcset = $m[1];
			}
			if ( preg_match( '/\balt=["\']([^"\']*)["\']/', $attrs, $m ) ) {
				$alt = $m[1];
			}
		}

		if ( ! $src ) {
			return '';
		}

		$caption = '';
		if ( preg_match( '/<figcaption[^>]*>(.*?)<\/figcaption>/is', $figure_html, $cap_match ) ) {
			$caption = trim( wp_strip_all_tags( $cap_match[1] ) );
		}

		$label = ! empty( $alt )
		/* translators: %s: image alt text */
			? sprintf( __( 'Image | %s', 'newspack-lite-site' ), $alt )
			: __( 'Image', 'newspack-lite-site' );

		$html  = '<div class="lite-image-placeholder"';
		$html .= ' data-src="' . esc_url( $src ) . '"';
		if ( $srcset ) {
			$html .= ' data-srcset="' . esc_attr( $srcset ) . '"';
		}
		if ( $alt ) {
			$html .= ' data-alt="' . esc_attr( $alt ) . '"';
		}
		if ( $caption ) {
			$html .= ' data-caption="' . esc_attr( $caption ) . '"';
		}
		$html .= '>';
		$html .= '<p class="lite-image-label">' . esc_html( $label ) . '</p>';
		if ( $caption ) {
			/* translators: %s: image caption text */
			$html .= '<p class="lite-image-caption">' . esc_html( sprintf( __( 'Caption: %s', 'newspack-lite-site' ), $caption ) ) . '</p>';
		}
		$html .= '<button class="lite-image-load-btn" type="button">' . esc_html__( 'Load image', 'newspack-lite-site' ) . '</button>';
		$html .= '</div>';

		return $html;
	}
}
