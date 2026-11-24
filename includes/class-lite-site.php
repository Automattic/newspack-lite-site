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
	 * How many pages below a moved or deleted page, nearest first and of any
	 * status, have their caches cleared by that change. It bounds the work
	 * one save or delete takes on; pages past it keep serving from their
	 * caches until those expire.
	 */
	const MAX_DESCENDANTS_CLEARED = 100;

	/**
	 * The longest srcset, in characters, this page will carry.
	 *
	 * @var int
	 */
	const SRCSET_MAX_LENGTH = 2048;

	/**
	 * ASCII whitespace, as the HTML srcset grammar defines it.
	 *
	 * @var string
	 */
	const SRCSET_WHITESPACE = " \t\n\r\f";

	/**
	 * Image source attributes in the order a reader should get them, each
	 * paired with the srcset belonging to the same image.
	 *
	 * @var array
	 */
	const IMAGE_SOURCE_PAIRS = [
		[ 'data-src', 'data-srcset' ],
		[ 'data-lazy-src', 'data-lazy-srcset' ],
		[ 'data-original', '' ],
		[ 'src', 'srcset' ],
	];

	/**
	 * Every attribute of an <img> this class reads.
	 *
	 * @var array
	 */
	const IMAGE_ATTRIBUTES = [
		'src',
		'srcset',
		'data-src',
		'data-srcset',
		'data-lazy-src',
		'data-lazy-srcset',
		'data-original',
		'alt',
	];

	/**
	 * Classes this plugin emits and the lite page's own script acts on.
	 *
	 * They are stripped from incoming content before any pass adds them, so a
	 * class in a post or an imported feed item cannot stand in for one of
	 * ours. A class is not provenance, and kses allows every piece of markup a
	 * placeholder is made of.
	 *
	 * @var string[]
	 */
	const RESERVED_CLASSES = [
		'lite-image-placeholder',
		'lite-image-label',
		'lite-image-caption',
		'lite-image-load-btn',
		'lite-table-scroll',
	];

	/**
	 * In-memory cache for the plugin settings option.
	 *
	 * @var array|null
	 */
	private static $settings = null;

	/**
	 * Lite paths that posts being updated in this request were published at
	 * before the update, keyed by post ID.
	 *
	 * @var array<int, string[]>
	 */
	private static $lite_paths_before_update = [];

	/**
	 * Initialize the lite site functionality.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_rewrite_rules' ] );
		add_filter( 'query_vars', [ __CLASS__, 'register_query_vars' ] );
		add_action( 'template_redirect', [ __CLASS__, 'handle_lite_site_templates' ] );
		add_filter( 'offline_template', [ __CLASS__, 'get_offline_template' ] );
		add_filter( 'wp_offline_error_precache_entry', [ __CLASS__, 'vary_offline_precache_entry' ] );
		add_action( 'pre_post_update', [ __CLASS__, 'remember_lite_path' ] );
		// Not save_post: a block-editor save stores the post's terms after
		// save_post fires, and a %category% permalink depends on them.
		add_action( 'wp_after_insert_post', [ __CLASS__, 'invalidate_post_caches' ], 10, 4 );
		// Not delete_post: by then the pages below the post have moved up,
		// and its terms, which a %category% permalink depends on, are gone.
		add_action( 'before_delete_post', [ __CLASS__, 'invalidate_deleted_post_caches' ] );

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
	 * Use the lite site offline template while Lite Site is on.
	 *
	 * @param string $template The offline template found so far, which is kept while Lite Site is off.
	 * @return string Absolute path to the offline template file.
	 */
	public static function get_offline_template( $template = '' ) {
		if ( ! self::is_enabled() ) {
			return $template;
		}
		return NEWSPACK_LITE_SITE_PLUGIN_DIR . 'templates/offline.php';
	}

	/**
	 * Vary the PWA plugin's precached offline page by whether Lite Site is on
	 * and by its offline template, so readers' devices fetch it again when
	 * either changes.
	 *
	 * @param array|false $entry The precache entry, with `url` and `revision`, or false when nothing is precached.
	 * @return array|false
	 */
	public static function vary_offline_precache_entry( $entry ) {
		if ( ! is_array( $entry ) ) {
			return $entry;
		}

		$revision = ';newspack-lite-site=' . ( self::is_enabled() ? 'on' : 'off' );
		if ( self::is_enabled() ) {
			$template  = NEWSPACK_LITE_SITE_PLUGIN_DIR . 'templates/offline.php';
			$revision .= ',' . md5_file( $template );
		}
		$entry['revision'] = ( $entry['revision'] ?? '' ) . $revision;

		return $entry;
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
	 * Check whether Lite Site is on or has been turned on before.
	 *
	 * Its addresses exist only then: once it has been on, links shared while
	 * it was on keep reaching the full site after it is turned off.
	 *
	 * @return bool
	 */
	public static function has_been_enabled(): bool {
		return self::is_enabled() || (bool) get_option( Lite_Site_Settings::WAS_ENABLED_OPTION_NAME );
	}

	/**
	 * Record that Lite Site has been turned on.
	 */
	public static function remember_enabled() {
		if ( ! get_option( Lite_Site_Settings::WAS_ENABLED_OPTION_NAME ) ) {
			update_option( Lite_Site_Settings::WAS_ENABLED_OPTION_NAME, true, true );
		}
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
	 * Print the custom CSS in a style element.
	 *
	 * The CSS prints as written. A style element is raw text, so HTML
	 * escaping would turn `>` into an entity that is never decoded, and
	 * stripping tags would cut CSS such as `content: "<"`. The element can
	 * only end at `</style`, so each one becomes `<\/style`, which CSS reads
	 * as the same text inside a string, a url() or a comment.
	 */
	public static function print_custom_css() {
		$custom_css = preg_replace( '#</(?=style)#i', '<\\/', self::get_custom_css() );
		if ( '' === $custom_css ) {
			return;
		}
		?>
		<style id="newspack-lite-site-custom-css"><?php echo $custom_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every `</style` is escaped above, so the CSS can't end the style element; HTML escaping would break selectors such as `>`. ?></style>
		<?php
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
	 * Print the font provider stylesheet, when one is set.
	 *
	 * Lite pages write their own <head> without wp_head(), so this prints
	 * only this stylesheet: not the rest of what the site enqueues, and not
	 * the wp_print_styles callbacks, which can echo markup of their own.
	 */
	public static function print_font_stylesheet() {
		$font_import_url = self::get_font_import_url();
		if ( ! $font_import_url ) {
			return;
		}

		// No version: a ver argument would change the URL the font provider receives.
		wp_enqueue_style( 'newspack-lite-site-fonts', $font_import_url, [], null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_styles()->do_items( 'newspack-lite-site-fonts' );
	}

	/**
	 * Print the GA4 tag, when a Measurement ID is set.
	 *
	 * Like print_font_stylesheet(), this prints only this script.
	 * wp_print_scripts() would also fire the wp_print_scripts action, even
	 * when given a handle. The config runs before the async loader, which
	 * picks up the queued calls from dataLayer when it arrives.
	 */
	public static function print_analytics_tag() {
		$measurement_id = self::get_ga4_measurement_id();
		if ( ! $measurement_id ) {
			return;
		}

		wp_enqueue_script(
			'newspack-lite-site-gtag',
			'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $measurement_id ),
			[],
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Served by Google, not versioned by the plugin.
			[ 'strategy' => 'async' ]
		);
		wp_add_inline_script(
			'newspack-lite-site-gtag',
			sprintf(
				"window.dataLayer = window.dataLayer || [];\nfunction gtag(){dataLayer.push(arguments);}\ngtag('js', new Date());\ngtag('config', %s);",
				wp_json_encode( $measurement_id )
			),
			'before'
		);
		wp_scripts()->do_items( 'newspack-lite-site-gtag' );
	}

	/**
	 * Get the font family for the lite site body text.
	 *
	 * Family names are quoted so names that are invalid as bare CSS identifiers,
	 * such as "Source Serif 4", still apply. The value is safe to print inside a
	 * style element without further escaping.
	 *
	 * @return string CSS font-family value.
	 */
	public static function get_font_family(): string {
		$settings  = self::get_settings();
		$font_body = ! empty( $settings['font_body'] ) ? $settings['font_body'] : '';

		$family = self::build_font_family( (string) $font_body );

		return '' !== $family ? $family : 'system-ui, -apple-system, sans-serif';
	}

	/**
	 * Turn a comma-separated list of font names into a valid CSS font-family value.
	 *
	 * Generic families and vendor keywords stay unquoted, other names are
	 * double-quoted, and characters that could end the declaration or the style
	 * element are removed. No fallback family is added. Returns an empty string
	 * when no usable name remains.
	 *
	 * @param string $value Raw font setting.
	 * @return string CSS font-family value, or an empty string.
	 */
	private static function build_font_family( string $value ): string {
		$generics = [
			'serif',
			'sans-serif',
			'monospace',
			'cursive',
			'fantasy',
			'system-ui',
			'ui-serif',
			'ui-sans-serif',
			'ui-monospace',
			'ui-rounded',
			'emoji',
			'math',
			'fangsong',
			'-apple-system',
			'blinkmacsystemfont',
		];

		$value = preg_replace( '/[\x00-\x1F\x7F]/', ' ', $value );
		$value = preg_replace( '/[;{}<>]/', '', (string) $value );
		preg_match_all( '/"[^"]*"|\'[^\']*\'|[^,]+/', (string) $value, $matches );

		$families = [];
		foreach ( $matches[0] as $name ) {
			$name = trim( $name );
			if ( strlen( $name ) >= 2 && ( ( '"' === $name[0] && '"' === substr( $name, -1 ) ) || ( "'" === $name[0] && "'" === substr( $name, -1 ) ) ) ) {
				$name = substr( $name, 1, -1 );
			}
			$name = trim( preg_replace( '/\s+/', ' ', trim( $name, " \"'" ) ) );
			if ( '' === $name ) {
				continue;
			}
			if ( in_array( strtolower( $name ), $generics, true ) ) {
				$families[] = $name;
				continue;
			}
			$families[] = '"' . str_replace( [ '\\', '"' ], [ '\\\\', '\\"' ], $name ) . '"';
		}

		if ( empty( $families ) ) {
			return '';
		}
		return implode( ', ', $families );
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

		return home_url( self::get_url_base() . '/' . self::get_lite_path( $permalink ) );
	}

	/**
	 * Get the part of a permalink's lite URL after the URL base, the path
	 * resolve_post() receives when that URL is requested.
	 *
	 * @param string $permalink A permalink on this site.
	 * @return string The lite path, without leading or trailing slashes.
	 */
	private static function get_lite_path( string $permalink ): string {
		return ltrim( str_replace( untrailingslashit( home_url() ), '', untrailingslashit( $permalink ) ), '/' );
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
	 *
	 * The rules exist only once Lite Site has been turned on. Until then,
	 * rules saved while they were registered for every site are flushed once.
	 */
	public static function register_rewrite_rules() {
		if ( self::is_enabled() ) {
			self::remember_enabled();
		}

		$url_base = self::get_url_base();
		$flush    = (bool) get_transient( 'nls_flush_rewrite_rules' );

		if ( self::has_been_enabled() ) {
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
		} else {
			$saved_rules = get_option( 'rewrite_rules' );
			$saved_query = is_array( $saved_rules ) ? ( $saved_rules[ '^' . $url_base . '/?$' ] ?? '' ) : '';
			// The lock keeps requests that arrive together, before the first flush is saved, from each flushing.
			if ( is_string( $saved_query ) && 0 === strpos( $saved_query, 'index.php?is_lite=' ) && ! get_transient( 'nls_flush_lock' ) ) {
				set_transient( 'nls_flush_lock', 1, MINUTE_IN_SECONDS );
				$flush = true;
			}
		}

		// Deferred flush: triggered by a settings change, runs here so rules are already in $wp_rewrite.
		if ( $flush ) {
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
		if ( ! self::has_been_enabled() ) {
			return $vars;
		}

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
	 *
	 * While Lite Site is off, its addresses redirect temporarily to the full
	 * site, so they work again as soon as it is turned back on.
	 */
	public static function handle_lite_site_templates() {
		$is_lite = get_query_var( 'is_lite' );

		if ( ! $is_lite ) {
			return;
		}

		if ( ! self::is_enabled() ) {
			wp_safe_redirect( self::get_full_version_url( $is_lite ), 302 );
			exit;
		}

		// Disable all other output.
		remove_all_actions( 'wp_head' );
		remove_all_actions( 'wp_footer' );

		if ( 'archive' === $is_lite ) {
			echo self::render_archive_page(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is fully escaped at the template level.
			exit;
		}

		$cache_key = self::get_request_page_cache_key();
		$cached    = '' !== $cache_key ? get_transient( $cache_key ) : false;

		if ( false !== $cached ) {
			echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is fully escaped at the template level.
			exit;
		}

		echo self::cache_single_page( $cache_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is fully escaped at the template level.
		exit;
	}

	/**
	 * The full-site address a lite address leads to while Lite Site is off.
	 *
	 * @param string $is_lite The request's `is_lite` query var.
	 * @return string The permalink of the article at the lite address, or the homepage for any other lite address.
	 */
	private static function get_full_version_url( $is_lite ): string {
		if ( 'single' !== $is_lite ) {
			return home_url( '/' );
		}

		$path    = get_query_var( 'lite_path' );
		$post_id = is_string( $path ) && '' !== $path ? self::lookup_post_id( $path ) : 0;
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, self::get_supported_post_types(), true ) ) {
			return home_url( '/' );
		}

		$permalink = get_permalink( $post );
		return $permalink && ! self::is_external_url( $permalink ) ? $permalink : home_url( '/' );
	}

	/**
	 * Whether the page being rendered is a lite not-found page.
	 *
	 * A not-found page must never be stored in the page cache: the post it
	 * asked for may be published a moment later, and the cached 404 would
	 * outlive it.
	 *
	 * @var bool
	 */
	private static bool $is_not_found = false;

	/**
	 * How many renders are on the stack.
	 *
	 * Only the outermost render owns the response. A nested one — a shortcode
	 * or an after-footer callback rendering another lite page — must not hand
	 * its own status and cache headers to the page that contains it.
	 *
	 * @var int
	 */
	private static int $render_depth = 0;

	/**
	 * Whether the page being rendered sits inside another render.
	 *
	 * @return bool
	 */
	public static function is_nested_render(): bool {
		return self::$render_depth > 1;
	}

	/**
	 * Record that the page being rendered is a lite not-found page.
	 *
	 * Called by the templates, which render the page themselves.
	 */
	public static function mark_not_found(): void {
		self::$is_not_found = true;
	}

	/**
	 * Render a lite single page and, when the request allows, store it in the page cache.
	 *
	 * The page renders as a signed-out reader sees it whether or not this
	 * visit stores it, so signing in can't change it, as Newspack's block
	 * visibility rules otherwise would, and a reader signed in without a
	 * session cookie can't store their own view. The template's not-found
	 * exit ends the request without restoring the reader.
	 *
	 * @param string $cache_key Transient key from get_page_cache_key(), or an empty string to store nothing.
	 * @return string The page HTML.
	 */
	public static function cache_single_page( string $cache_key ): string {
		$reader_id          = get_current_user_id();
		$was_not_found      = self::$is_not_found;
		self::$is_not_found = false;
		wp_set_current_user( 0 );

		$is_not_found = true;

		++self::$render_depth;

		ob_start();
		try {
			self::include_single_template();
			$is_not_found = self::$is_not_found;
		} finally {
			$output             = ob_get_clean();
			self::$is_not_found = $was_not_found;
			--self::$render_depth;
			wp_set_current_user( $reader_id );
		}

		if ( '' !== $cache_key && ! $is_not_found && self::can_fill_page_cache() ) {
			set_transient( $cache_key, $output, 15 * MINUTE_IN_SECONDS );
		}

		return $output;
	}

	/**
	 * Include the lite single template.
	 *
	 * Kept out of cache_single_page() so the template's variables can't
	 * overwrite that method's own, such as the reader it restores.
	 */
	private static function include_single_template() {
		include NEWSPACK_LITE_SITE_PLUGIN_DIR . 'templates/single.php';
	}

	/**
	 * Render the lite archive page.
	 *
	 * The render is counted, and the not-found flag fenced, the way
	 * cache_single_page() does both for a single page. Without the count an
	 * after-footer callback rendering a missing lite page would hand its 404
	 * and its no-store headers to an archive that rendered perfectly well.
	 * Without the fence a missing archive nested inside a single render would
	 * leave the flag set, and the article around it would go unstored though
	 * it rendered fine. The archive itself is not stored: the page cache holds
	 * single pages only.
	 *
	 * @return string The page HTML.
	 */
	public static function render_archive_page(): string {
		$was_not_found      = self::$is_not_found;
		self::$is_not_found = false;

		++self::$render_depth;

		ob_start();
		try {
			self::include_archive_template();
		} finally {
			$output             = ob_get_clean();
			self::$is_not_found = $was_not_found;
			--self::$render_depth;
		}

		return $output;
	}

	/**
	 * Include the lite archive template.
	 *
	 * Kept out of render_archive_page() so the template's variables can't
	 * overwrite that method's own, such as the markup it returns.
	 */
	private static function include_archive_template() {
		include NEWSPACK_LITE_SITE_PLUGIN_DIR . 'templates/archive.php';
	}

	/**
	 * Whether the current request's render may be stored for every reader.
	 *
	 * The page cache keys on the path alone, so a render shaped by anything
	 * else in the request would reach every reader after it. A request fills
	 * it only when it's a GET or HEAD sent to the site's own host, with no
	 * query in the URL it requested and none of the cookies Batcache skips a
	 * request for. Other cookies and request headers aren't read.
	 *
	 * @return bool
	 */
	private static function can_fill_page_cache(): bool {
		// A form's posted fields reach the page through $_REQUEST, as query
		// parameters do.
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( ! in_array( $method, [ 'GET', 'HEAD' ], true ) ) {
			return false;
		}

		// Code that prints the requested URL takes its host from the request,
		// which WordPress doesn't check against the site's. Browsers send the
		// host in lowercase, with the port only when it isn't the scheme's
		// default.
		$home         = wp_parse_url( home_url() );
		$default_port = 'https' === ( $home['scheme'] ?? '' ) ? 443 : 80;
		$home_port    = isset( $home['port'] ) && $default_port !== $home['port'] ? ':' . $home['port'] : '';
		$home_host    = strtolower( ( $home['host'] ?? '' ) . $home_port );
		if ( ! isset( $_SERVER['HTTP_HOST'] ) || $home_host !== $_SERVER['HTTP_HOST'] ) {
			return false;
		}

		// The query the visitor sent, which code that prints the requested URL
		// carries even when it holds no parameters. Read from the request URI,
		// since a server rewrite can add a query string of its own.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only checked for a "?".
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( false !== strpos( $request_uri, '?' ) ) {
			return false;
		}

		// Batcache skips a request carrying a cookie with one of these
		// prefixes: they mark a visitor whose pages can differ, such as a
		// session or an access bypass. The cookie WordPress sets to test
		// cookie support does not.
		// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE -- Reads cookie names only.
		foreach ( array_keys( $_COOKIE ) as $name ) {
			// phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- A cookie name prefix.
			if ( 'wordpress_test_cookie' !== $name && preg_match( '/^(?:wp|wordpress|comment_author)/', (string) $name ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Remember the lite path a published post has before an update changes it.
	 *
	 * Read here rather than from the `$post_before` that wp_after_insert_post
	 * passes: a %category% permalink is built from the terms the post has at
	 * that moment, and by then the save may have changed them.
	 *
	 * @param int $post_id The ID of the post about to be updated.
	 */
	public static function remember_lite_path( int $post_id ) {
		$post = get_post( $post_id );
		if ( $post && 'publish' === $post->post_status ) {
			self::$lite_paths_before_update[ $post_id ][] = self::get_lite_path( get_permalink( $post ) );
		}
	}

	/**
	 * Clear the cached page and post lookup at each lite path a post was
	 * published at, before or after a save, and at those of the published
	 * pages below it when the save moves it.
	 *
	 * A path the post left, by moving or being unpublished, would otherwise
	 * go on serving it from the page cache for minutes, and from the lookup
	 * for hours after a move. A path it takes could hold a lookup that found
	 * no post there, such as a signed-out visit while it was scheduled, and
	 * the post would show as not found until that entry expired.
	 *
	 * Where permalinks include %category%, renaming a category moves its
	 * posts without saving them, and the pages cached at their old paths are
	 * left to expire: clearing them would mean reading every post in the
	 * category. The lookup there needs no clearing, since WordPress finds a
	 * post by its slug whatever category the path names.
	 *
	 * @param int          $post_id     The saved post ID.
	 * @param WP_Post|null $post        The saved post.
	 * @param bool         $update      Whether the save updated an existing post.
	 * @param WP_Post|null $post_before The post before the save, or null for a new post.
	 */
	public static function invalidate_post_caches( int $post_id, $post = null, $update = false, $post_before = null ) {
		$paths = self::$lite_paths_before_update[ $post_id ] ?? [];
		unset( self::$lite_paths_before_update[ $post_id ] );

		$saved_post = get_post( $post_id );

		// Trashing a post adds `__trashed` to its slug before pre_post_update
		// fires, so the path it was published at is also read from the post
		// as it was before the save.
		foreach ( [ $post_before, $saved_post ] as $version ) {
			if ( $version instanceof \WP_Post && 'publish' === $version->post_status ) {
				$paths[] = self::get_lite_path( get_permalink( $version ) );
			}
		}

		if ( $saved_post && $post_before instanceof \WP_Post && is_post_type_hierarchical( $saved_post->post_type ) ) {
			$paths = array_merge( $paths, self::get_descendant_lite_paths( $saved_post, (string) get_page_uri( $post_before ) ) );
		}

		self::clear_lite_path_caches( $paths );
	}

	/**
	 * Clear the cached page and post lookup at the lite path of a published
	 * post about to be permanently deleted, and at those of the published
	 * pages below it, which move up to its parent.
	 *
	 * Deleting saves nothing, so invalidate_post_caches() never runs, and
	 * the page cache would go on serving the post for minutes.
	 *
	 * @param int $post_id The ID of the post about to be deleted.
	 */
	public static function invalidate_deleted_post_caches( int $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$paths = 'publish' === $post->post_status ? [ self::get_lite_path( get_permalink( $post ) ) ] : [];

		if ( is_post_type_hierarchical( $post->post_type ) ) {
			$parent_uri = $post->post_parent ? (string) get_page_uri( $post->post_parent ) : '';
			$paths      = array_merge( $paths, self::get_descendant_lite_paths( $post, $parent_uri ) );
		}

		self::clear_lite_path_caches( $paths );
	}

	/**
	 * Get the lite paths of the published pages below a post, both where
	 * they are and where they are at the other end of a move of the post.
	 *
	 * A page's permalink is built from the slugs of the pages above it, so
	 * renaming, re-parenting, trashing, or deleting a page moves every page
	 * below it without saving them. Each one's path at the other end of the
	 * move is its current path with the post's part swapped for $other_uri.
	 *
	 * @param \WP_Post $post      A post of a hierarchical type.
	 * @param string   $other_uri The post's page URI at the other end of the move: before a save, or its parent's after a delete.
	 * @return string[] Lite paths, as resolve_post() receives them.
	 */
	private static function get_descendant_lite_paths( \WP_Post $post, string $other_uri ): array {
		$uri = (string) get_page_uri( $post );
		if ( $uri === $other_uri ) {
			return [];
		}

		$paths = [];
		foreach ( self::get_descendants( $post ) as $descendant ) {
			if ( 'publish' !== $descendant->post_status ) {
				continue;
			}

			$path    = self::get_lite_path( get_permalink( $descendant ) );
			$paths[] = $path;

			// A permalink that doesn't end in the page URI, as a plugin could
			// filter it to, gives no way to tell its path at the other end.
			$descendant_uri = (string) get_page_uri( $descendant );
			if ( 0 === strpos( $descendant_uri, $uri . '/' ) && substr( $path, -strlen( $descendant_uri ) ) === $descendant_uri ) {
				$other_descendant_uri = ltrim( $other_uri . substr( $descendant_uri, strlen( $uri ) ), '/' );
				$paths[]              = substr( $path, 0, -strlen( $descendant_uri ) ) . $other_descendant_uri;
			}
		}

		return $paths;
	}

	/**
	 * Get the posts below a post, of any status, nearest first, up to
	 * MAX_DESCENDANTS_CLEARED of them.
	 *
	 * Any status, because a published page can sit below a draft. The limit
	 * also ends the walk on a parent loop, which WordPress prevents on save
	 * but not in the database.
	 *
	 * @param \WP_Post $post A post of a hierarchical type.
	 * @return \WP_Post[]
	 */
	private static function get_descendants( \WP_Post $post ): array {
		$descendants = [];
		$parent_ids  = [ $post->ID ];
		$remaining   = self::MAX_DESCENDANTS_CLEARED;

		while ( $parent_ids && $remaining > 0 ) {
			$children    = get_posts(
				[
					'post_type'              => $post->post_type,
					'post_status'            => array_keys( get_post_stati() ),
					'post_parent__in'        => $parent_ids,
					'posts_per_page'         => $remaining,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				]
			);
			$descendants = array_merge( $descendants, $children );
			$parent_ids  = wp_list_pluck( $children, 'ID' );
			$remaining  -= count( $children );
		}

		return $descendants;
	}

	/**
	 * Clear the cached page and post lookup at each of a set of lite paths.
	 *
	 * @param string[] $paths Lite paths, as resolve_post() receives them.
	 */
	private static function clear_lite_path_caches( array $paths ) {
		foreach ( array_unique( $paths ) as $path ) {
			wp_cache_delete( self::get_post_lookup_cache_key( $path ), 'newspack_lite_site' );
			// Keyed from the path below the home URL, the shape of the path
			// WordPress routes, so it matches whether or not the request URI
			// carries a subdirectory's path.
			delete_transient( self::get_page_cache_key( self::get_url_base() . '/' . $path ) );
		}
	}

	/**
	 * Clear the lite caches for a post.
	 *
	 * @deprecated 0.2.0 Use invalidate_post_caches().
	 *
	 * @param int $post_id The post ID.
	 */
	public static function invalidate_page_cache( int $post_id ) {
		_deprecated_function( __METHOD__, '0.2.0', __CLASS__ . '::invalidate_post_caches' );
		self::invalidate_post_caches( $post_id );
	}

	/**
	 * Build the transient key for the lite single page the current request is for.
	 *
	 * @return string The transient key, or an empty string when WordPress routed no path for the request.
	 */
	public static function get_request_page_cache_key(): string {
		global $wp;

		// The path WordPress routed, below the home URL and as received: the
		// string the lite rule reads `lite_path` from, so the key follows the
		// page that renders. Sanitizing it would drop characters and could give
		// two different pages one key.
		$routed_path = isset( $wp->request ) ? (string) $wp->request : '';

		// With no routed path every such request would share one key, so they
		// get none and their pages aren't cached.
		return '' === $routed_path ? '' : self::get_page_cache_key( $routed_path );
	}

	/**
	 * Build the transient key that caches a lite single page.
	 *
	 * Keyed on the path alone, so cache-busting query strings can't mint
	 * unbounded transients. A page can still read the query string, so a
	 * request carrying one doesn't fill the cache; see can_fill_page_cache().
	 * The path otherwise stays as WordPress routes it, so two paths that can
	 * render different pages never share a key: it's cut only at the first
	 * `?`, and only the letter case of its percent-encoding is normalized,
	 * because WordPress links slugs with lowercase escapes while a browser
	 * sends uppercase ones for a URL typed or pasted in Unicode.
	 *
	 * @param string $path A path below the home URL, with or without a query string.
	 * @return string The transient key.
	 */
	public static function get_page_cache_key( string $path ): string {
		$path = self::lowercase_percent_escapes( explode( '?', $path, 2 )[0] );
		return 'nls_page_' . md5( trim( $path, '/' ) );
	}

	/**
	 * Build the object cache key for the post lookup of a lite path.
	 *
	 * @param string $path Lite path, as resolve_post() receives it.
	 * @return string The cache key.
	 */
	private static function get_post_lookup_cache_key( string $path ): string {
		// Keyed on the path, not the full URL: home_url() takes its scheme
		// from the current request, so the request that saves the post and a
		// reader's could otherwise hash different keys. The escapes are
		// lowercased so a lite URL a browser sends shares the entry that
		// saving clears, but not decoded: misses are cached too, and a decoded
		// key would let a variant that finds nothing, like `my%2Dpost`, cache
		// a miss for `my-post`.
		return 'nls_post_' . md5( self::lowercase_percent_escapes( trim( $path, '/' ) ) );
	}

	/**
	 * Lowercase the percent-escapes in a path.
	 *
	 * @param string $path The path.
	 * @return string The path with lowercase escapes.
	 */
	private static function lowercase_percent_escapes( string $path ): string {
		return preg_replace_callback(
			'/%[0-9a-f]{2}/i',
			function ( $escape ) {
				return strtolower( $escape[0] );
			},
			$path
		);
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
	 * Find the ID of the post at a path, through the post lookup cache.
	 *
	 * The ID is cached by path for every visitor, whatever the post's status,
	 * so callers check the post themselves.
	 *
	 * @param string $path URL path without leading slash.
	 * @return int The post ID, or 0 when no post is at the path.
	 */
	private static function lookup_post_id( string $path ): int {
		$cache_key = self::get_post_lookup_cache_key( $path );
		$post_id   = wp_cache_get( $cache_key, 'newspack_lite_site' );

		if ( false === $post_id ) {
			$post_id = url_to_postid( home_url( '/' . ltrim( $path, '/' ) ) ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.url_to_postid_url_to_postid
			if ( $post_id ) {
				wp_cache_set( $cache_key, (int) $post_id, 'newspack_lite_site', 3 * HOUR_IN_SECONDS );
			} else {
				// A miss is kept for minutes: saving the post can't clear a
				// miss written after it, such as a lookup that read the post
				// just before it was published, or one for a variant of its URL.
				wp_cache_set( $cache_key, 0, 'newspack_lite_site', 5 * MINUTE_IN_SECONDS );
			}
		}

		return (int) $post_id;
	}

	/**
	 * Resolve a URL path to a published WP_Post.
	 *
	 * @param mixed $path URL path without leading slash. Anything but a non-empty string resolves to nothing.
	 * @return WP_Post|null The resolved post, or null if not found, not published, or excluded by term filters.
	 */
	public static function resolve_post( $path ) {
		if ( empty( $path ) || ! is_string( $path ) ) {
			return null;
		}

		$post_id = self::lookup_post_id( $path );

		if ( ! $post_id ) {
			return null;
		}

		$post = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return null;
		}

		// Lite pages render raw post content with no password form, so a
		// protected post must not resolve at all. The rendered page is cached
		// for every reader, so this can't depend on the visitor's password
		// cookie, as post_password_required() does.
		if ( '' !== $post->post_password ) {
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

		return $post;
	}

	/**
	 * Get the author(s) for a post.
	 *
	 * @param WP_Post $post The post object.
	 * @return string The formatted author(s) string with links.
	 */
	public static function get_authors( $post ) {
		// An active Newspack custom byline replaces the author-derived byline.
		// Guarded because the plugin runs standalone, without the Newspack stack.
		if ( class_exists( '\Newspack\Bylines' ) && method_exists( '\Newspack\Bylines', 'get_custom_byline_html' ) ) {
			$custom_byline = \Newspack\Bylines::get_custom_byline_html( $post->ID );
			if ( ! empty( $custom_byline ) ) {
				return $custom_byline;
			}
		}

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
		// A third-party callback on this public filter can return anything, and
		// the rest of this method needs a string.
		$filtered = apply_filters( 'newspack_lite_site_post_content', $content );
		$content  = is_string( $filtered ) ? $filtered : ( is_string( $content ) ? $content : '' );

		// Remove HTML comments. The single-token `.` with the `s` modifier stays
		// linear on an unclosed `<!--`, where alternation-based patterns
		// backtrack catastrophically and preg_replace returns null.
		$content = self::or_original( preg_replace( '/<!--.*?-->/s', '', $content ), $content );

		// Remove script and style tags along with their contents — wp_kses
		// would strip the tags but leave raw CSS/JS behind as text.
		$content = self::or_original( preg_replace( '/<script.*?>.*?<\/script>/is', '', $content ), $content );
		$content = self::or_original( preg_replace( '/<style.*?>.*?<\/style>/is', '', $content ), $content );

		$content = self::unwrap_atomic_elements( $content );

		// Clear the classes this plugin's own script acts on. This runs after
		// the passes above because each of them can rewrite text: a comment
		// inside a class value, say, is removed at this point rather than
		// later, where it would join `lite-image-place<!---->holder` back into
		// a reserved name after the walk had already passed over it. It runs
		// before the figure pass below, which is where the plugin adds those
		// names itself. See RESERVED_CLASSES.
		$content = self::strip_reserved_classes( $content );

		// Replace figures with lazy-load placeholders before stripping. The
		// pattern stops at a following `<figure` as well as at a closing tag,
		// so a figure left unclosed cannot swallow the content up to the next
		// figure's close. A tempered pattern would say this more directly and
		// is deliberately avoided: per-character alternation over figure
		// content exhausts the PCRE JIT stack at about 24.6 KB of content and
		// returns null, which blanks the article.
		$content = self::or_original(
			preg_replace_callback(
				'/<figure\b[^>]*>.*?(?:(?=<figure\b)|<\/figure>)/is',
				[ __CLASS__, 'figure_to_placeholder' ],
				$content
			),
			$content
		);

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
			'table'      => [],
			'thead'      => [],
			'tbody'      => [],
			'tfoot'      => [],
			'tr'         => [],
			'th'         => [
				'colspan' => true,
				'rowspan' => true,
				'scope'   => true,
			],
			'td'         => [
				'colspan' => true,
				'rowspan' => true,
			],
			'caption'    => [],
			'figcaption' => [],
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

		/**
		 * Filters the elements and attributes that lite page content keeps.
		 *
		 * Lets a plugin whose blocks render on lite pages keep the markup its
		 * own script relies on, such as data attributes. It runs just before
		 * wp_kses(), after scripts and styles are removed and figures holding
		 * an image are turned into placeholders, so additions can't bring
		 * those back. The single template still passes the result through
		 * wp_kses_post(), which bounds what reaches a lite single page; other
		 * callers of clean_content() get exactly this list.
		 *
		 * @param array $allowed_html Allowed elements and their attributes, in wp_kses() form.
		 */
		$filtered_html = apply_filters( 'newspack_lite_site_allowed_html', $allowed_html );

		// Same reasoning as the content filter above. A callback returning a
		// string makes wp_kses_attr() index it as a string offset and throw;
		// returning a scalar silently strips every tag on the page.
		$allowed_html = is_array( $filtered_html ) ? $filtered_html : $allowed_html;

		// Strip all HTML except allowed elements.
		$content = wp_kses( $content, $allowed_html );

		// Close what the content left open, and drop a closing tag with no
		// opening one. `wpautop` writes a stray `</p>` after a figure that was
		// never closed, and the allowlist keeps a closing tag whose opening tag
		// it has just removed. This runs before the empty-paragraph pass below
		// so a paragraph that balancing leaves empty is removed with the rest.
		$content = force_balance_tags( $content );

		// Clean up any empty paragraphs.
		$content = self::or_original( preg_replace( '/<p>\s*<\/p>/', '', $content ), $content );

		return self::add_external_link_attrs( $content );
	}

	/**
	 * Put each top-level table in its own horizontal scroller.
	 *
	 * Called by the templates on already-sanitized markup, so the wrapper's own
	 * attributes do not depend on what a kses allowlist happens to permit. A
	 * wide table has to be able to overflow its column while staying a table,
	 * so assistive technology still reads it as one, and the scroller is
	 * focusable so it can be scrolled from the keyboard.
	 *
	 * Only outermost tables are wrapped: wrapping a nested one would close the
	 * div inside the outer table, which the HTML parser drops, leaving the
	 * wrapper open over the rest of the article.
	 *
	 * @param string $html Sanitized markup.
	 * @return string
	 */
	public static function wrap_tables_for_scrolling( string $html ): string {
		if ( ! preg_match_all( '/<\/?table\b[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE ) ) {
			return $html;
		}

		$depth  = 0;
		$spans  = [];
		$opened = 0;

		foreach ( $matches[0] as $match ) {
			$is_close = '/' === $match[0][1];

			// <table /> survives sanitization and closes nothing, so counting it
			// as an open would strand the walk and unwrap every later table.
			if ( ! $is_close && preg_match( '/\/\s*>$/', $match[0] ) ) {
				continue;
			}

			if ( ! $is_close ) {
				if ( 0 === $depth ) {
					$opened = $match[1];
				}
				++$depth;
				continue;
			}

			if ( 0 === $depth ) {
				continue;
			}

			--$depth;
			if ( 0 === $depth ) {
				$spans[] = [ $opened, $match[1] + strlen( $match[0] ) ];
			}
		}

		foreach ( array_reverse( $spans ) as $span ) {
			list( $from, $to ) = $span;
			$html              = substr( $html, 0, $from )
				. sprintf(
					// The tab stop and the label are added by the page's own
					// script, and only to a table that actually overflows. A
					// two-column table on a phone does not, and announcing
					// scrolling that cannot happen, behind an extra tab stop,
					// is worse than saying nothing.
					'<div class="lite-table-scroll" data-scroll-label="%s">',
					esc_attr__( 'Table, scrolls sideways', 'newspack-lite-site' )
				)
				. substr( $html, $from, $to - $from )
				. '</div>'
				. substr( $html, $to );
		}

		return $html;
	}

	/**
	 * Fall back to the original string when a preg_* call fails.
	 *
	 * A pattern that hits a PCRE limit returns null, and passing that on blanks
	 * the article — on the page a reader reaches when everything else is down.
	 *
	 * The parameters stay loosely typed on purpose, though clean_content() now
	 * coerces its content to a string before the first call, so the non-string
	 * branch should be unreachable. It is kept because the cost is a single
	 * is_string() and the failure it guards against is a fatal on the page a
	 * reader reaches when everything else is already down.
	 *
	 * @param string|null $result   The preg_* result.
	 * @param mixed       $original The string passed in.
	 * @return string
	 */
	private static function or_original( $result, $original ): string {
		if ( is_string( $result ) ) {
			return $result;
		}

		return is_string( $original ) ? $original : '';
	}

	/**
	 * Remove the wrapper tags of elements the HTML parser treats as atomic.
	 *
	 * WP_HTML_Tag_Processor consumes TITLE, TEXTAREA, IFRAME, XMP, NOEMBED and
	 * NOFRAMES together with everything inside them, so strip_reserved_classes()
	 * never visits markup nested in one and cannot clear a class there. kses
	 * then drops the wrapper and keeps the children, which is how a forged
	 * placeholder hidden in a <textarea> reached the reader complete, from a
	 * post any contributor can write or a feed item this plugin imported. An
	 * unterminated wrapper is worse: the parser stops at it, so every tag after
	 * it goes unvisited too.
	 *
	 * Only the wrapper tags go, not their contents, which is what kses does to
	 * these elements anyway, so nothing a reader would have seen is lost. Their
	 * contents become ordinary markup that the walk can reach.
	 *
	 * script and style are absent on purpose: they are removed whole, contents
	 * and all, before this runs.
	 *
	 * @param string $html Post content.
	 * @return string Content with those wrappers removed.
	 */
	private static function unwrap_atomic_elements( string $html ): string {
		return self::or_original(
			preg_replace( '/<\/?(?:title|textarea|iframe|xmp|noembed|noframes)\b[^>]*>/i', '', $html ),
			$html
		);
	}

	/**
	 * Remove this plugin's own control classes from incoming content.
	 *
	 * Everything a placeholder is made of -- a container with data-src, a
	 * classed button -- passes wp_kses(), so post content and imported feed
	 * items can write one. The lite page's script would then act on it and a
	 * reader's tap would fetch whatever host it named, from the page whose
	 * promise is that nothing third-party loads unasked.
	 *
	 * Scoping the script's selector cannot fix that: a class is not
	 * provenance. Clearing these names before any pass adds them is what makes
	 * a placeholder on the page one this plugin built.
	 *
	 * @param string $html Post content.
	 * @return string Content with the reserved classes cleared.
	 */
	private static function strip_reserved_classes( string $html ): string {
		// Gate on something that cannot be written any other way. A class
		// VALUE can spell a reserved name with character references, as
		// lite&#45;image-placeholder, which kses later decodes back into the
		// real name -- so a test for the name itself is evadable. An attribute
		// NAME is never entity-decoded, so `class` is always spelled out.
		if ( false === stripos( $html, 'class' ) ) {
			return $html;
		}

		$processor = new \WP_HTML_Tag_Processor( $html );

		while ( $processor->next_tag() ) {
			foreach ( self::RESERVED_CLASSES as $class ) {
				if ( true === $processor->has_class( $class ) ) {
					$processor->remove_class( $class );
				}
			}
		}

		$html = $processor->get_updated_html();

		// The walk and kses do not always read the same markup, and where they
		// disagree the reader gets kses's reading. An unterminated <script>
		// pauses the processor at an incomplete token and everything after it
		// goes unwalked; a `>` inside a quoted attribute hides a tag from the
		// processor that kses later treats as real markup. Either way a
		// reserved name can still be in the string. The walk above is what
		// catches a name spelled with character references, which this cannot
		// see; this is what catches the markup the walk never reached. Nothing
		// legitimate carries these names yet, since the passes that add them
		// run after this one, so removing what is left costs nothing.
		foreach ( self::RESERVED_CLASSES as $class ) {
			if ( false === stripos( $html, $class ) ) {
				continue;
			}

			$html = self::or_original(
				preg_replace( '/' . preg_quote( $class, '/' ) . '/i', '', $html ),
				$html
			);
		}

		return $html;
	}

	/**
	 * Rebuild a srcset from candidates this plugin would accept as a source.
	 *
	 * The placeholder's data-src is checked with esc_url(), but data-srcset was
	 * copied through with nothing but attribute escaping. Per the HTML standard
	 * a srcset candidate REPLACES src rather than supplementing it, so an
	 * unvetted candidate here is the image the browser actually loads.
	 *
	 * What this does and does not buy: esc_url() vets the PROTOCOL, so it stops
	 * an inline data: payload and a javascript: URL. It does not stop a
	 * third-party host, which an author could equally have put in src.
	 *
	 * One rejected candidate drops the whole list. A partly filtered set would
	 * still change which image a given screen gets, and the reader still has
	 * the vetted data-src to load.
	 *
	 * @param string $srcset Raw srcset value.
	 * @return string Escaped srcset, or an empty string.
	 */
	private static function safe_image_srcset( string $srcset ): string {
		// Bound the work, not only the output. Without this a megabyte of
		// candidates is parsed and escaped in full before the budget below
		// throws the result away.
		if ( strlen( $srcset ) > self::SRCSET_MAX_LENGTH * 10 ) {
			return '';
		}

		$parts = [];

		foreach ( self::parse_srcset( $srcset ) as $candidate ) {
			// A descriptor is a width or a pixel density, nothing else. The
			// grammar tracks parentheses and parse_srcset() does not, so
			// without this a descriptor like `1x(,)` splits off a second
			// candidate whose URL is `)`, and esc_url() turns that into
			// `http://)`, which is not empty and so slips the test below.
			if ( '' !== $candidate['descriptor'] && 1 !== preg_match( '/^[0-9.]+[wx]$/', $candidate['descriptor'] ) ) {
				return '';
			}

			// esc_url() does not reject a document-relative path, it moves it:
			// photos/flood.jpg becomes http://photos/flood.jpg, a plaintext
			// request to a host named "photos". Because a candidate replaces
			// src, that is the request the browser would actually make.
			$has_scheme = 1 === preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $candidate['url'] );

			if ( ! $has_scheme && ! str_starts_with( $candidate['url'], '/' ) ) {
				return '';
			}

			$url = esc_url( $candidate['url'] );

			if ( '' === $url ) {
				return '';
			}

			// The descriptor is digits, a dot and w or x by the test above, so
			// there is nothing left in it to escape.
			$parts[] = '' === $candidate['descriptor']
				? $url
				: $url . ' ' . $candidate['descriptor'];
		}

		if ( ! $parts ) {
			return '';
		}

		$result = implode( ', ', $parts );

		// Measured after escaping, so the budget counts the bytes the page
		// actually carries rather than the bytes the author wrote.
		return strlen( $result ) > self::SRCSET_MAX_LENGTH ? '' : $result;
	}

	/**
	 * Split a srcset into its candidates, the way the HTML grammar does.
	 *
	 * A URL is collected up to the first ASCII whitespace, not the first comma,
	 * so a CDN path holding a transform like w_800,h_600 survives intact.
	 *
	 * @param string $srcset Raw srcset value.
	 * @return array<int,array{url:string,descriptor:string}> Candidates, in order.
	 */
	private static function parse_srcset( string $srcset ): array {
		$candidates = [];
		$length     = strlen( $srcset );
		$position   = 0;

		while ( $position < $length ) {
			$position += strspn( $srcset, self::SRCSET_WHITESPACE . ',', $position );

			if ( $position >= $length ) {
				break;
			}

			$url_length = strcspn( $srcset, self::SRCSET_WHITESPACE, $position );
			$url        = substr( $srcset, $position, $url_length );
			$position  += $url_length;
			$descriptor = '';

			if ( str_ends_with( $url, ',' ) ) {
				$url = rtrim( $url, ',' );
			} else {
				$position         += strspn( $srcset, self::SRCSET_WHITESPACE, $position );
				$descriptor_length = strcspn( $srcset, ',', $position );
				$descriptor        = trim( substr( $srcset, $position, $descriptor_length ) );
				$position         += $descriptor_length + 1;
			}

			if ( '' !== $url ) {
				$candidates[] = [
					'url'        => $url,
					'descriptor' => $descriptor,
				];
			}
		}

		return $candidates;
	}

	/**
	 * Read the attributes of the first <img> in a fragment.
	 *
	 * The values come from the parser rather than from a pattern over the tag's
	 * text. A pattern reads a literal data-src="..." written inside the value of
	 * some other attribute as though it were an attribute of its own, so a
	 * source forged in a title or a caption is chosen over the real one, and the
	 * image the reader gets is not the image the browser would have loaded.
	 *
	 * A fragment with no img the parser can reach yields no source, and the
	 * caller hands the figure back untouched, which is what it does for a figure
	 * that never had an image.
	 *
	 * @param string $html A fragment expected to contain an <img> tag.
	 * @return array The attributes in IMAGE_ATTRIBUTES, each '' when the tag does not carry it.
	 */
	private static function read_img_attrs( string $html ): array {
		$attributes = array_fill_keys( self::IMAGE_ATTRIBUTES, '' );
		$processor  = new \WP_HTML_Tag_Processor( $html );

		if ( ! $processor->next_tag( [ 'tag_name' => 'IMG' ] ) ) {
			return $attributes;
		}

		foreach ( self::IMAGE_ATTRIBUTES as $name ) {
			$value = $processor->get_attribute( $name );

			// An attribute written without a value reads as true, and one the tag
			// does not carry as null. Only a string holds a source or a caption.
			if ( is_string( $value ) ) {
				$attributes[ $name ] = $value;
			}
		}

		return $attributes;
	}

	/**
	 * Choose which of an <img> tag's source attributes the reader should get.
	 *
	 * A lazy loader leaves the real image in a data attribute and puts a stub in
	 * src, so the literal src is the last choice rather than the first. Reading
	 * src alone drops a figure whose image carries no src at all, which is what
	 * Jetpack Lazy Images and WP Rocket emit.
	 *
	 * The pair is chosen together because a srcset candidate replaces src rather
	 * than supplementing it, so taking the source from the real image and the
	 * srcset from the stub would still load the stub.
	 *
	 * @param array $attributes The attributes read by read_img_attrs().
	 * @return array {
	 *     @type string $src    The chosen source, not yet escaped, or ''.
	 *     @type string $srcset The srcset belonging to it, already vetted, or ''.
	 * }
	 */
	private static function pick_figure_source( array $attributes ): array {
		foreach ( self::IMAGE_SOURCE_PAIRS as $pair ) {
			list( $src_attr, $srcset_attr ) = $pair;

			$src = $attributes[ $src_attr ];

			if ( '' === $src ) {
				continue;
			}

			return [
				'src'    => $src,
				'srcset' => '' === $srcset_attr
					? ''
					: self::safe_image_srcset( $attributes[ $srcset_attr ] ),
			];
		}

		return [
			'src'    => '',
			'srcset' => '',
		];
	}

	/**
	 * Give a table figure's caption to the table itself.
	 *
	 * The allowlist keeps `figcaption` but drops `figure`, so a Table block's
	 * caption reaches the reader as loose text after the table with nothing
	 * tying the two together. A screen reader announces the table and then,
	 * separately, a sentence. `caption` is the element that belongs to a
	 * table, and the allowlist already keeps it.
	 *
	 * A table that captions itself keeps its own; only a figure's caption is
	 * moved, and only into a table that has none.
	 *
	 * @param string $figure_html A figure known to hold a table.
	 * @return string The figure with its caption moved into the table.
	 */
	private static function table_caption_from_figure( string $figure_html ): string {
		if ( ! preg_match( '/<figcaption[^>]*>(.*?)<\/figcaption>/is', $figure_html, $caption ) ) {
			return $figure_html;
		}

		if ( preg_match( '/<table\b[^>]*>\s*<caption\b/i', $figure_html ) ) {
			return $figure_html;
		}

		$without = self::or_original(
			preg_replace( '/<figcaption[^>]*>.*?<\/figcaption>/is', '', $figure_html ),
			$figure_html
		);

		$inner = $caption[1];

		$moved = preg_replace_callback(
			'/<table\b[^>]*>/i',
			static function ( array $matches ) use ( $inner ): string {
				return $matches[0] . '<caption>' . $inner . '</caption>';
			},
			$without,
			1
		);

		return self::or_original( $moved, $figure_html );
	}

	/**
	 * Convert a <figure> element into a lazy-load image placeholder.
	 *
	 * @param array $matches Regex match array; $matches[0] is the full <figure> HTML.
	 * @return string Placeholder div HTML, or empty string if no image src found.
	 */
	private static function figure_to_placeholder( $matches ) {
		$figure_html = $matches[0];

		// A match that stopped at a following `<figure` has no closing tag, so
		// where this figure ends is a guess. Replacing it would delete whatever
		// the guess took in: the prose between the two figures, and the second
		// figure's own image and caption. Leave it to the passes below.
		if ( ! preg_match( '/<\/figure\s*>$/i', $figure_html ) ) {
			return $figure_html;
		}

		// The block editor wraps a table in a figure. An image in any cell would
		// otherwise turn the whole table, cell text and all, into one placeholder.
		if ( preg_match( '/<table\b/i', $figure_html ) ) {
			return self::table_caption_from_figure( $figure_html );
		}

		$attributes = self::read_img_attrs( $figure_html );
		$source     = self::pick_figure_source( $attributes );
		$src        = $source['src'];
		$srcset     = $source['srcset'];
		$alt        = $attributes['alt'];

		if ( ! $src ) {
			return $figure_html;
		}

		$caption = '';
		if ( preg_match( '/<figcaption[^>]*>(.*?)<\/figcaption>/is', $figure_html, $cap_match ) ) {
			$caption = trim( wp_strip_all_tags( $cap_match[1] ) );
		}

		$label = ! empty( $alt )
			? sprintf(
				/* translators: %s: image alt text. */
				__( 'Image: %s', 'newspack-lite-site' ),
				$alt
			)
			: __( 'Image', 'newspack-lite-site' );

		$attrs = sprintf( ' data-src="%s"', esc_url( $src ) );
		if ( $srcset ) {
			$attrs .= sprintf( ' data-srcset="%s"', $srcset );
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
