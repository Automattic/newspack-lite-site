<?php
/**
 * Lite Site functionality.
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

defined( 'ABSPATH' ) || exit;

/**
 * Lite Site class.
 */
class Lite_Site {

	/**
	 * In-memory cache for the plugin settings option.
	 *
	 * @var array|null
	 */
	private static $settings = null;

	/**
	 * Initialize the lite site functionality.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_rewrite_rules' ] );
		add_filter( 'query_vars', [ __CLASS__, 'register_query_vars' ] );
		add_action( 'template_redirect', [ __CLASS__, 'handle_lite_site_templates' ] );
		add_filter( 'offline_template', [ __CLASS__, 'get_offline_template' ] );
		add_action( 'save_post', [ __CLASS__, 'invalidate_page_cache' ] );

		/** Add content filters to mimic 'the_content'. See 'wp-includes/default-filters.php' for reference. */
		add_filter( 'newspack_lite_site_post_content', 'capital_P_dangit', 11 );
		add_filter( 'newspack_lite_site_post_content', [ __CLASS__, 'do_blocks' ], 9 );
		add_filter( 'newspack_lite_site_post_content', 'wptexturize' );
		add_filter( 'newspack_lite_site_post_content', 'convert_smilies', 20 );
		add_filter( 'newspack_lite_site_post_content', 'wpautop' );
		add_filter( 'newspack_lite_site_post_content', 'shortcode_unautop' );
		add_filter( 'newspack_lite_site_post_content', 'prepend_attachment' );
		add_filter( 'newspack_lite_site_post_content', 'wp_filter_content_tags' );
		add_filter( 'newspack_lite_site_post_content', 'wp_replace_insecure_home_url' );
		add_filter( 'newspack_lite_site_post_content', 'do_shortcode', 11 );
	}

	/**
	 * Return the plugin settings, reading the option once per request.
	 *
	 * @return array Plugin settings.
	 */
	private static function get_settings(): array {
		if ( null === self::$settings ) {
			self::$settings = get_option( Lite_Site_Settings::OPTION_NAME, [] );
		}
		return self::$settings;
	}

	/**
	 * Return the path to the lite site offline template.
	 *
	 * @return string Absolute path to the offline template file.
	 */
	public static function get_offline_template() {
		return NEWSPACK_LITE_SITE_PLUGIN_DIR . 'templates/offline.php';
	}

	/**
	 * Check if the lite site feature is enabled.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public static function is_enabled() {
		$settings = self::get_settings();
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
	 * Get the URL base (slug used for lite pages).
	 *
	 * @return string The URL base slug.
	 */
	public static function get_url_base() {
		$settings = self::get_settings();
		return ! empty( $settings['url_base'] ) ? $settings['url_base'] : 'lite';
	}

	/**
	 * Get the number of posts to display per page in the archive.
	 *
	 * Falls back to the WordPress Reading setting (Blog pages show at most) if not explicitly configured.
	 *
	 * @return int Posts per page.
	 */
	public static function get_posts_per_page() {
		$settings = self::get_settings();
		return ! empty( $settings['posts_per_page'] )
			? intval( $settings['posts_per_page'] )
			: (int) get_option( 'posts_per_page', 10 );
	}

	/**
	 * Get the selected categories, optionally expanded to include all descendants.
	 *
	 * Returns an empty array when no categories are selected (meaning all categories).
	 * When include_subcategories is true (the default), child categories of each
	 * selected term are appended automatically.
	 *
	 * @return int[] Category IDs, or empty array for all categories.
	 */
	public static function get_categories() {
		$settings = self::get_settings();
		if ( empty( $settings['categories'] ) ) {
			return [];
		}

		$selected = array_map( 'intval', (array) $settings['categories'] );

		$include_subcategories = $settings['include_subcategories'] ?? true;
		if ( ! $include_subcategories ) {
			return $selected;
		}

		$all_ids = $selected;
		foreach ( $selected as $term_id ) {
			$children = get_term_children( $term_id, 'category' );
			if ( ! is_wp_error( $children ) ) {
				$all_ids = array_merge( $all_ids, array_map( 'intval', $children ) );
			}
		}

		return array_unique( $all_ids );
	}

	/**
	 * Get the selected tags for inclusion filtering.
	 *
	 * @return int[] Tag IDs, or empty array for no tag filter.
	 */
	public static function get_tags() {
		$settings = self::get_settings();
		return ! empty( $settings['tags'] ) ? array_map( 'intval', $settings['tags'] ) : [];
	}

	/**
	 * Get the categories excluded from the lite site.
	 *
	 * @return int[] Category IDs to exclude, or empty array for none.
	 */
	public static function get_excluded_categories() {
		$settings = self::get_settings();
		return ! empty( $settings['excluded_categories'] ) ? array_map( 'intval', $settings['excluded_categories'] ) : [];
	}

	/**
	 * Get the tags excluded from the lite site.
	 *
	 * @return int[] Tag IDs to exclude, or empty array for none.
	 */
	public static function get_excluded_tags() {
		$settings = self::get_settings();
		return ! empty( $settings['excluded_tags'] ) ? array_map( 'intval', $settings['excluded_tags'] ) : [];
	}

	/**
	 * Get whether external links should open in a new tab.
	 *
	 * @return bool True if external links should open in a new tab (default), false otherwise.
	 */
	public static function get_external_links_new_tab(): bool {
		$settings = self::get_settings();
		return isset( $settings['external_links_new_tab'] ) ? (bool) $settings['external_links_new_tab'] : true;
	}

	/**
	 * Get the custom CSS to inject into lite site pages.
	 *
	 * @return string Custom CSS, or empty string if not set.
	 */
	public static function get_custom_css() {
		$settings = self::get_settings();
		return ! empty( $settings['custom_css'] ) ? $settings['custom_css'] : '';
	}

	/**
	 * Get the footer HTML.
	 *
	 * @return string Footer HTML, or empty string if not set.
	 */
	public static function get_footer_html() {
		$settings = self::get_settings();
		return ! empty( $settings['footer_html'] ) ? $settings['footer_html'] : '';
	}

	/**
	 * Get the GA4 Measurement ID.
	 *
	 * @return string GA4 Measurement ID, or empty string if not set.
	 */
	public static function get_ga4_measurement_id() {
		$settings = self::get_settings();
		return ! empty( $settings['ga4_measurement_id'] ) ? $settings['ga4_measurement_id'] : '';
	}

	/**
	 * Get the primary color (admin override takes priority, then theme default).
	 *
	 * @return string The primary color.
	 */
	public static function get_primary_color() {
		$settings = self::get_settings();
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
	 * Get the font import URL set in the lite site settings.
	 *
	 * @return string Font provider URL, or empty string if not set.
	 */
	public static function get_font_import_url() {
		$settings = self::get_settings();
		return ! empty( $settings['font_import_url'] ) ? $settings['font_import_url'] : '';
	}

	/**
	 * Get the font family for the lite site body text.
	 *
	 * @return string CSS font-family value.
	 */
	public static function get_font_family() {
		$settings = self::get_settings();
		$font_body = ! empty( $settings['font_body'] ) ? $settings['font_body'] : '';
		if ( ! empty( $font_body ) ) {
			return $font_body;
		}

		return 'system-ui, -apple-system, sans-serif';
	}

	/**
	 * Get the lite version URL for a post.
	 *
	 * @param WP_Post $post The post object.
	 * @return string The lite site URL for the post, or the original permalink if external.
	 */
	public static function get_lite_page_url( $post ) {
		$permalink = untrailingslashit( get_permalink( $post ) );

		if ( self::is_external_url( $permalink ) ) {
			return $permalink;
		}

		$path = ltrim( str_replace( untrailingslashit( home_url() ), '', $permalink ), '/' );
		return home_url( self::get_url_base() . '/' . $path );
	}

	/**
	 * Checks whether a URL points to a different domain than the current site.
	 *
	 * Returns false for relative URLs, anchors, and non-HTTP protocols so they
	 * are never treated as external.
	 *
	 * @param string $url The URL to check.
	 * @return bool True if the URL is external, false otherwise.
	 */
	public static function is_external_url( string $url ): bool {
		$link_host = strtolower( wp_parse_url( $url, PHP_URL_HOST ) ?? '' );
		$site_host = strtolower( wp_parse_url( home_url(), PHP_URL_HOST ) ?? '' );

		// If no host could be parsed (e.g. relative URL), treat as internal.
		if ( ! $link_host ) {
			return false;
		}

		// Normalize both hosts: strip 'www.' prefix for comparison.
		$link_host = preg_replace( '/^www\./i', '', $link_host );
		$site_host = preg_replace( '/^www\./i', '', $site_host );

		return $link_host !== $site_host;
	}

	/**
	 * Register rewrite rules for lite site pages and flush if a settings change is pending.
	 */
	public static function register_rewrite_rules() {
		$url_base = self::get_url_base();

		// Archive: /{url_base}.
		add_rewrite_rule(
			'^' . $url_base . '/?$',
			'index.php?is_lite=archive',
			'top'
		);

		// Archive paginated: /{url_base}/page/{n}. Must come before the single post catch-all.
		add_rewrite_rule(
			'^' . $url_base . '/page/([0-9]+)/?$',
			'index.php?is_lite=archive&lite_page=$matches[1]',
			'top'
		);

		// Single: /{url_base}/{post-slug}.
		add_rewrite_rule(
			'^' . $url_base . '/(.+)/?$',
			'index.php?is_lite=single&lite_path=$matches[1]',
			'top'
		);

		// Deferred flush: triggered by a settings change, runs here so rules are already in $wp_rewrite.
		if ( get_transient( 'nls_flush_rewrite_rules' ) ) {
			delete_transient( 'nls_flush_rewrite_rules' );
			flush_rewrite_rules(); // phpcs:ignore
		}
	}

	/**
	 * Register custom query variables.
	 *
	 * @param array $vars The array of query variables.
	 * @return array The modified array of query variables.
	 */
	public static function register_query_vars( $vars ) {
		return array_merge(
			$vars,
			[
				'is_lite',
				'lite_path',
				'lite_page',
			]
		);
	}

	/**
	 * Handle template routing for lite site pages.
	 */
	public static function handle_lite_site_templates() {
		$is_lite = get_query_var( 'is_lite' );

		if ( ! $is_lite || ! self::is_enabled() ) {
			return;
		}

		// Disable all other output.
		remove_all_actions( 'wp_head' );
		remove_all_actions( 'wp_footer' );

		if ( 'archive' === $is_lite ) {
			include_once NEWSPACK_LITE_SITE_PLUGIN_DIR . 'templates/archive.php';
			exit;
		}

		// Resolved here as well as in the template: whether the page may be
		// cached has to be known before the cache is consulted, and the URL
		// lookup is memoized so the second resolution is cheap.
		$post      = self::resolve_post( get_query_var( 'lite_path' ) );
		$cacheable = $post && self::is_post_page_cacheable( $post->ID );

		$request_uri = untrailingslashit( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' );
		$cache_key   = 'nls_page_' . md5( $request_uri );

		if ( $cacheable ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is fully escaped at the template level.
				exit;
			}
		} else {
			// What keeps the page out of the transient has to keep it out of
			// any page cache or CDN in front of the site as well.
			if ( function_exists( 'batcache_cancel' ) ) {
				batcache_cancel();
			}
			nocache_headers();
		}

		ob_start();
		include_once NEWSPACK_LITE_SITE_PLUGIN_DIR . 'templates/single.php';
		$output = ob_get_clean();

		if ( $cacheable ) {
			set_transient( $cache_key, $output, 15 * MINUTE_IN_SECONDS );
		}

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is fully escaped at the template level.
		exit;
	}

	/**
	 * Invalidate the cached lite single page for a post when it is saved.
	 *
	 * @param int $post_id The saved post ID.
	 */
	public static function invalidate_page_cache( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$url_base    = self::get_url_base();
		$post_path   = ltrim( str_replace( trailingslashit( home_url() ), '', trailingslashit( get_permalink( $post ) ) ), '/' );
		$request_uri = untrailingslashit( '/' . $url_base . '/' . $post_path );
		$cache_key   = 'nls_page_' . md5( $request_uri );
		delete_transient( $cache_key );
	}

	/**
	 * Get the supported post types for lite site rendering.
	 *
	 * @return string[] Array of supported post type slugs.
	 */
	public static function get_supported_post_types() {
		/**
		 * Filter the post types eligible for lite site rendering.
		 *
		 * @param string[] $types Array of post type slugs.
		 */
		return apply_filters( 'newspack_lite_site_supported_post_types', [ 'post', 'page', Post_Type::POST_TYPE ] );
	}

	/**
	 * Resolve a URL path to a published WP_Post the current reader may see.
	 *
	 * @param string $path URL path without leading slash.
	 * @return WP_Post|null The resolved post, or null if not found, not published, excluded by term filters, or restricted for the reader.
	 */
	public static function resolve_post( $path ) {
		if ( empty( $path ) ) {
			return null;
		}

		$url       = home_url( '/' . ltrim( $path, '/' ) );
		$cache_key = 'nls_post_' . md5( $url );
		$post_id   = wp_cache_get( $cache_key, 'newspack_lite_site' );

		if ( false === $post_id ) {
			$post_id = url_to_postid( $url ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.url_to_postid_url_to_postid
			wp_cache_set( $cache_key, (int) $post_id, 'newspack_lite_site', 3 * HOUR_IN_SECONDS );
		}

		if ( ! $post_id ) {
			return null;
		}

		$post = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}

		if ( is_object_in_taxonomy( $post->post_type, 'category' ) ) {
			$post_categories     = wp_get_post_categories( $post->ID );
			$included_categories = self::get_categories();

			if ( ! empty( $included_categories ) && ! array_intersect( $included_categories, $post_categories ) ) {
				return null;
			}
			$excluded_categories = self::get_excluded_categories();
			if ( ! empty( $excluded_categories ) && array_intersect( $excluded_categories, $post_categories ) ) {
				return null;
			}
		}

		if ( is_object_in_taxonomy( $post->post_type, 'post_tag' ) ) {
			$post_tag_ids  = wp_get_post_tags( $post->ID, [ 'fields' => 'ids' ] );
			$included_tags = self::get_tags();

			if ( ! empty( $included_tags ) && ! array_intersect( $included_tags, $post_tag_ids ) ) {
				return null;
			}
			$excluded_tags = self::get_excluded_tags();
			if ( ! empty( $excluded_tags ) && array_intersect( $excluded_tags, $post_tag_ids ) ) {
				return null;
			}
		}

		if ( self::is_post_restricted( $post->ID ) ) {
			return null;
		}

		return $post;
	}

	/**
	 * Whether the current reader is kept from the post's content on the full site.
	 *
	 * Lite pages print post content with none of the full site's gating layer,
	 * so this check is all that stands between a restricted post and the
	 * reader. Newspack's content gates and its WooCommerce Memberships
	 * integration answer it; without them the filter is unanswered and no
	 * post is restricted.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_post_restricted( $post_id ) {
		/** This filter is documented in newspack-plugin, Content_Gate::is_post_restricted(). */
		return (bool) apply_filters( 'newspack_is_post_restricted', false, (int) $post_id );
	}

	/**
	 * Drop the posts the current reader cannot access from an archive listing.
	 *
	 * @param \WP_Post[] $posts Posts as queried.
	 * @return \WP_Post[] The accessible posts, re-indexed from zero.
	 */
	public static function exclude_restricted_posts( array $posts ) {
		return array_values(
			array_filter(
				$posts,
				function ( $post ) {
					return ! self::is_post_restricted( $post->ID );
				}
			)
		);
	}

	/**
	 * Whether a rendered lite page for the post may be stored in the page cache.
	 *
	 * The cache is keyed by URL alone, so a page rendered for a reader who is
	 * allowed past a restriction (a member, an editor, a reader carrying a
	 * bypass cookie) would be served to every reader after them. A post that
	 * any reader could be restricted from is rendered fresh on every request.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_post_page_cacheable( $post_id ) {
		/** This filter is documented in newspack-plugin, Content_Gate::post_has_restrictions(). */
		return ! apply_filters( 'newspack_post_has_restrictions', false, (int) $post_id );
	}

	/**
	 * Get the author(s) for a post.
	 *
	 * @param WP_Post $post The post object.
	 * @return string The formatted author(s) string with links.
	 */
	public static function get_authors( $post ) {
		if ( function_exists( 'coauthors_posts_links' ) ) {
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
	 * @return bool True if the post is an active or archived liveblog, false otherwise.
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
	 * @return WP_Comment[] Array of liveblog comment entries.
	 */
	public static function get_liveblog_entries( $post_id, $limit = 100 ) {
		$post_id = absint( $post_id );
		$limit   = absint( $limit );

		if ( ! $post_id ) {
			return [];
		}

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
	 * Add external link attributes to all external <a> tags in an HTML string.
	 *
	 * Adds class, target, and rel attributes to any link whose href points to
	 * a different domain.
	 *
	 * @param string $html HTML content to process.
	 * @return string Processed HTML with external link attributes added.
	 */
	public static function add_external_link_attrs( string $html ): string {
		if ( ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $html;
		}

		$processor    = new \WP_HTML_Tag_Processor( $html );
		$open_new_tab = self::get_external_links_new_tab();

		while ( $processor->next_tag( 'a' ) ) {
			$href = $processor->get_attribute( 'href' );
			if ( ! $href || ! self::is_external_url( $href ) ) {
				continue;
			}
			$processor->set_attribute( 'class', 'lite-site-external' );
			if ( $open_new_tab ) {
				$processor->set_attribute( 'target', '_blank' );
				$processor->set_attribute( 'rel', 'noopener noreferrer' );
			}
		}

		return $processor->get_updated_html();
	}

	/**
	 * Parses dynamic blocks and re-renders them.
	 *
	 * Copy of do_blocks() from wp-includes/blocks.php but targeting newspack_lite_site_post_content
	 * instead of the_content for the wpautop filter handling.
	 *
	 * @param string $content Post content.
	 * @return string Rendered content.
	 */
	public static function do_blocks( $content ) {
		$blocks = parse_blocks( $content );
		$output = '';

		foreach ( $blocks as $block ) {
			$output .= render_block( $block );
		}

		// Block content handles its own paragraph spacing, so suppress wpautop when blocks are present.
		$priority = has_filter( 'newspack_lite_site_post_content', 'wpautop' );
		if ( false !== $priority && doing_filter( 'newspack_lite_site_post_content' ) && has_blocks( $content ) ) {
			remove_filter( 'newspack_lite_site_post_content', 'wpautop', $priority );
			add_filter( 'newspack_lite_site_post_content', [ __CLASS__, 'restore_wpautop_hook' ], $priority + 1 );
		}

		return $output;
	}

	/**
	 * Restore the wpautop hook to newspack_lite_site_post_content after do_blocks() removed it.
	 *
	 * @param string $content Post content.
	 * @return string Unchanged content.
	 */
	public static function restore_wpautop_hook( $content ) {
		add_filter( 'newspack_lite_site_post_content', 'wpautop' );
		remove_filter( 'newspack_lite_site_post_content', [ __CLASS__, 'restore_wpautop_hook' ] );
		return $content;
	}

	/**
	 * Clean the post content for lite display.
	 *
	 * @param string $content The post content.
	 * @return string The cleaned content.
	 */
	public static function clean_content( $content ) {
		// Apply the full WP content pipeline without plugin callbacks from the_content.
		$content = apply_filters( 'newspack_lite_site_post_content', $content );

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

		return self::add_external_link_attrs( $content );
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
			return $figure_html;
		}

		$caption = '';
		if ( preg_match( '/<figcaption[^>]*>(.*?)<\/figcaption>/is', $figure_html, $cap_match ) ) {
			$caption = trim( wp_strip_all_tags( $cap_match[1] ) );
		}

		$label = ! empty( $alt )
		/* translators: %s: image alt text */
			? sprintf( __( 'Image | %s', 'newspack-lite-site' ), $alt )
			: __( 'Image', 'newspack-lite-site' );

		$attrs = sprintf( ' data-src="%s"', esc_url( $src ) );
		if ( $srcset ) {
			$attrs .= sprintf( ' data-srcset="%s"', esc_attr( $srcset ) );
		}
		if ( $alt ) {
			$attrs .= sprintf( ' data-alt="%s"', esc_attr( $alt ) );
		}
		if ( $caption ) {
			$attrs .= sprintf( ' data-caption="%s"', esc_attr( $caption ) );
		}

		$caption_html = $caption
			? sprintf(
				'<p class="lite-image-caption">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: image caption text */
						__( 'Caption: %s', 'newspack-lite-site' ),
						$caption
					)
				)
			)
			: '';

		return sprintf(
			'<div class="lite-image-placeholder"%s><p class="lite-image-label">%s</p>%s<button class="lite-image-load-btn" type="button">%s</button></div>',
			$attrs,
			esc_html( $label ),
			$caption_html,
			esc_html__( 'Load image', 'newspack-lite-site' )
		);
	}
}
