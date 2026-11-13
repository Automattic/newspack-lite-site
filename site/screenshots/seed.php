<?php
/**
 * Turns a local WordPress site into the demo site the docs screenshots show.
 *
 * Run it with WP-CLI against a single site set aside for screenshots:
 *
 *     wp eval-file <plugin>/site/screenshots/seed.php           Seed, or reset to the known state.
 *     wp eval-file <plugin>/site/screenshots/seed.php fresh     Seed, with Lite Site as it is right after activation.
 *     wp eval-file <plugin>/site/screenshots/seed.php remove    Delete everything it created.
 *
 * Add `force` to seed a site whose environment type isn't local or
 * development. See README.md next to this file.
 *
 * @package newspack-lite-site
 */

use Newspack_Lite_Site\Lite_Site_Settings;
use Newspack_Lite_Site\RSS_Importer;

defined( 'ABSPATH' ) || exit;

const NEWSPACK_LS_SCREENSHOTS_MARK  = '_newspack_ls_screenshots';
const NEWSPACK_LS_SCREENSHOTS_STATE = 'newspack_ls_screenshots_state';
const NEWSPACK_LS_SCREENSHOTS_FAKES = 'newspack-ls-screenshots.php';
const NEWSPACK_LS_SCREENSHOTS_THEME = 'newspack-theme';

const NEWSPACK_LS_SCREENSHOTS_SETTINGS_OPTION = 'newspack_lite_site_settings';
const NEWSPACK_LS_SCREENSHOTS_FEEDS_OPTION    = 'newspack_lite_site_rss_importer';
const NEWSPACK_LS_SCREENSHOTS_CRON_HOOK       = 'newspack_lite_site_rss_import';
const NEWSPACK_LS_SCREENSHOTS_ENTRY_TYPE      = 'nls_rss_entry';
const NEWSPACK_LS_SCREENSHOTS_SUBTITLE        = 'newspack_post_subtitle';

/**
 * Entry point.
 *
 * @param array $args Positional arguments passed to `wp eval-file`.
 */
function newspack_ls_screenshots_main( $args ) {
	$args = (array) $args;

	if ( is_multisite() ) {
		WP_CLI::error( 'Nothing was changed. The seed works on a single site only, not a multisite network.' );
	}
	newspack_ls_screenshots_require_admin_files();
	newspack_ls_screenshots_block_requests();

	if ( in_array( 'remove', $args, true ) ) {
		$state = get_option( NEWSPACK_LS_SCREENSHOTS_STATE );
		if ( ! $state && ! in_array( 'force', $args, true ) ) {
			WP_CLI::error( "Nothing was changed. This site hasn't been seeded, or its seed state is gone. To delete marked content anyway, add force: wp eval-file <path>/seed.php remove force" );
		}
		$admin_id = newspack_ls_screenshots_site_admin();
		wp_set_current_user( $admin_id ? $admin_id : newspack_ls_screenshots_any_admin() );
		newspack_ls_screenshots_remove( true );
		WP_CLI::success( 'Removed the screenshot content and fakes plugin, and put back the settings the seed changed.' );
		return;
	}

	if ( ! defined( 'NEWSPACK_LITE_SITE_PLUGIN_FILE' ) ) {
		WP_CLI::error( 'Nothing was changed. Lite Site is not active. Activate your git checkout of the plugin, with its assets built.' );
	}

	WP_CLI::log( 'This seed is meant for a local site set aside for screenshots, not an everyday dev site.' );
	newspack_ls_screenshots_check_environment( in_array( 'force', $args, true ) );
	newspack_ls_screenshots_check_prerequisites();

	$admin_id = newspack_ls_screenshots_site_admin();
	if ( ! $admin_id ) {
		WP_CLI::error( 'Nothing was changed. The site needs an administrator account of its own.' );
	}
	wp_set_current_user( $admin_id );

	$content = newspack_ls_screenshots_read_content();
	newspack_ls_screenshots_preflight( $content );
	newspack_ls_screenshots_warn_others( $content );

	$fresh = in_array( 'fresh', $args, true );
	newspack_ls_screenshots_seed( $content, $fresh );
	if ( $fresh ) {
		WP_CLI::success( 'The screenshot site is ready, with Lite Site turned off and no RSS feeds. Run the seed without fresh to load the full demo.' );
	} else {
		WP_CLI::success( 'The screenshot site is ready. Rerun this command any time to reset it.' );
	}
}

/**
 * Stops a run that failed partway, saying how to undo it.
 *
 * @param string $what    What the seed was doing.
 * @param string $message Why it failed.
 */
function newspack_ls_screenshots_fail( $what, $message ) {
	WP_CLI::error( sprintf( '%s: %s. Run the seed with remove to undo this run.', $what, rtrim( $message, '. ' ) ) );
}

/**
 * Loads the wp-admin functions the seed uses.
 */
function newspack_ls_screenshots_require_admin_files() {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/user.php';
}

/**
 * Keeps this run from contacting anything but the site itself: no pings,
 * no email, and no remote requests from hooks that fire as content is
 * created.
 */
function newspack_ls_screenshots_block_requests() {
	remove_action( 'publish_post', '_publish_post_hook', 5 );
	add_filter( 'pre_wp_mail', '__return_false' );
	add_filter( 'pre_option_ping_sites', '__return_empty_string' );
	add_filter(
		'pre_http_request',
		function ( $response, $args, $url ) {
			$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== $host ) {
				return new WP_Error( 'newspack_ls_screenshots_blocked', 'Blocked while seeding the screenshot site.' );
			}
			return $response;
		},
		10,
		3
	);
}

/**
 * Refuses sites that don't say they are local, unless told otherwise.
 *
 * @param bool $force Whether `force` was passed.
 */
function newspack_ls_screenshots_check_environment( $force ) {
	$type = wp_get_environment_type();
	if ( $force || in_array( $type, [ 'local', 'development' ], true ) ) {
		return;
	}
	WP_CLI::error(
		"Nothing was changed. This site's environment type is \"{$type}\", and the seed only runs on local or development sites.\n"
		. "Set it in wp-config.php with define( 'WP_ENVIRONMENT_TYPE', 'local' ); or the WP_ENVIRONMENT_TYPE environment variable. WordPress assumes production when neither is set, which is the case on many Docker images and Studio sites.\n"
		. 'To seed this site anyway, add force: wp eval-file <path>/seed.php force'
	);
}

/**
 * Stops before changing anything when the Newspack stack isn't in place.
 */
function newspack_ls_screenshots_check_prerequisites() {
	$missing = [];
	if ( ! defined( 'NEWSPACK_PLUGIN_FILE' ) ) {
		$missing[] = 'The Newspack plugin is not active. Install and activate it from https://github.com/Automattic/newspack-plugin/releases';
	}
	if ( ! wp_get_theme( NEWSPACK_LS_SCREENSHOTS_THEME )->exists() ) {
		$missing[] = 'The Newspack theme is not installed. Download it from https://github.com/Automattic/newspack-theme/releases';
	}
	if ( $missing ) {
		WP_CLI::error( "Nothing was changed.\n- " . implode( "\n- ", $missing ) );
	}
}

/**
 * The site's first administrator the seed didn't create.
 *
 * @return int User ID, or 0.
 */
function newspack_ls_screenshots_site_admin() {
	foreach ( get_users(
		[
			'role'    => 'administrator',
			'orderby' => 'ID',
			'fields'  => 'ID',
		]
	) as $user_id ) {
		if ( ! get_user_meta( (int) $user_id, NEWSPACK_LS_SCREENSHOTS_MARK, true ) ) {
			return (int) $user_id;
		}
	}
	return 0;
}

/**
 * Any administrator, for removal on a site whose only administrators the
 * seed created.
 *
 * @return int User ID, or 0.
 */
function newspack_ls_screenshots_any_admin() {
	$admins = get_users(
		[
			'role'    => 'administrator',
			'orderby' => 'ID',
			'number'  => 1,
			'fields'  => 'ID',
		]
	);
	return $admins ? (int) $admins[0] : 0;
}

/**
 * Reads a file shipped next to this one.
 *
 * @param string $path Path relative to this folder.
 * @return string|false
 */
function newspack_ls_screenshots_read_file( $path ) {
	$file = __DIR__ . '/' . $path;
	return is_readable( $file ) ? file_get_contents( $file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
}

/**
 * The demo content and settings.
 *
 * @return array
 */
function newspack_ls_screenshots_read_content() {
	$content = json_decode( (string) newspack_ls_screenshots_read_file( 'content.json' ), true );
	if ( ! is_array( $content ) ) {
		WP_CLI::error( 'Nothing was changed. Could not read content.json next to this file.' );
	}
	return $content;
}

/**
 * The site's RSS feeds, read without Lite Site so removal works when the
 * plugin is inactive.
 *
 * @return array Feeds by feed ID.
 */
function newspack_ls_screenshots_feeds() {
	$option = get_option( NEWSPACK_LS_SCREENSHOTS_FEEDS_OPTION, [] );
	return isset( $option['feeds'] ) && is_array( $option['feeds'] ) ? $option['feeds'] : [];
}

/**
 * The seed's state, as saved by the last run.
 *
 * @return array
 */
function newspack_ls_screenshots_state() {
	$state = get_option( NEWSPACK_LS_SCREENSHOTS_STATE, [] );
	return is_array( $state ) ? $state : [];
}

/**
 * Saves the seed's state.
 *
 * @param array $state Seed state.
 */
function newspack_ls_screenshots_save_state( $state ) {
	update_option( NEWSPACK_LS_SCREENSHOTS_STATE, $state, false );
}

/**
 * Checks everything that could stop the seed halfway, so it either runs
 * through or changes nothing.
 *
 * @param array $content Demo content.
 */
function newspack_ls_screenshots_preflight( $content ) {
	$problems = [];

	foreach ( $content['posts'] as $post ) {
		$existing = get_page_by_path( $post['slug'], OBJECT, 'post' );
		if ( $existing && ! get_post_meta( $existing->ID, NEWSPACK_LS_SCREENSHOTS_MARK, true ) ) {
			$problems[] = sprintf( 'The site already has a post with the slug "%s".', $post['slug'] );
		}
	}

	foreach ( $content['theme']['pages'] as $page ) {
		$existing = get_page_by_path( $page['slug'] );
		if ( $existing && ! get_post_meta( $existing->ID, NEWSPACK_LS_SCREENSHOTS_MARK, true ) ) {
			$problems[] = sprintf( 'The site already has a page with the slug "%s".', $page['slug'] );
		}
	}

	foreach ( $content['theme']['menus'] as $menu ) {
		$existing = wp_get_nav_menu_object( $menu['name'] );
		if ( $existing && ! get_term_meta( $existing->term_id, NEWSPACK_LS_SCREENSHOTS_MARK, true ) ) {
			$problems[] = sprintf( 'The site already has a menu called "%s".', $menu['name'] );
		}
	}

	foreach ( array_merge( $content['users'], [ $content['admin'] ] ) as $user ) {
		foreach ( [ 'login', 'email' ] as $field ) {
			$existing = get_user_by( $field, $user[ $field ] );
			if ( $existing && ! get_user_meta( $existing->ID, NEWSPACK_LS_SCREENSHOTS_MARK, true ) ) {
				$problems[] = sprintf( 'The site already has a user with the %s "%s".', $field, $user[ $field ] );
			}
		}
	}

	$seeded_feeds = array_keys( newspack_ls_screenshots_state()['feeds'] ?? [] );
	$own_feeds    = array_diff( array_keys( newspack_ls_screenshots_feeds() ), $seeded_feeds );
	if ( $own_feeds ) {
		$problems[] = sprintf( 'The site already has %d RSS feed(s) of its own under Lite Site > RSS. Delete them first: the seed replaces the feed list.', count( $own_feeds ) );
	}

	if ( ! get_option( 'permalink_structure' ) ) {
		$problems[] = 'The site uses plain permalinks, and Lite Site needs pretty ones. Choose Post name under Settings > Permalinks.';
	}

	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) || ! wp_is_writable( $uploads['basedir'] ) ) {
		$problems[] = 'The uploads folder is not writable: ' . ( $uploads['error'] ? $uploads['error'] : $uploads['basedir'] );
	}
	$mu_plugins = is_dir( WPMU_PLUGIN_DIR ) ? WPMU_PLUGIN_DIR : dirname( WPMU_PLUGIN_DIR );
	if ( ! wp_is_writable( $mu_plugins ) ) {
		$problems[] = 'The mu-plugins folder is not writable: ' . WPMU_PLUGIN_DIR;
	}

	$files = [ 'fakes.php', 'assets/media/' . $content['site']['icon'], 'assets/media/' . $content['site']['logo'], 'assets/media/' . $content['theme']['footer_logo'] ];
	foreach ( $content['media'] as $item ) {
		$files[] = 'assets/media/' . $item['file'];
	}
	foreach ( $content['fonts'] as $file ) {
		$files[] = 'assets/' . $file;
	}
	foreach ( $content['users'] as $user ) {
		if ( ! empty( $user['avatar'] ) ) {
			$files[] = 'assets/avatars/' . $user['avatar'];
		}
	}
	$media_files = array_column( $content['media'], 'file' );
	foreach ( $content['posts'] as $post ) {
		if ( ! empty( $post['image'] ) && ! in_array( $post['image'], $media_files, true ) ) {
			$problems[] = sprintf( 'The post "%s" uses the image %s, which is not in the media list.', $post['slug'], $post['image'] );
		}
	}
	foreach ( $files as $file ) {
		if ( ! is_readable( __DIR__ . '/' . $file ) ) {
			$problems[] = "Can't read {$file} next to this file.";
		}
	}

	$requirements = validate_theme_requirements( NEWSPACK_LS_SCREENSHOTS_THEME );
	if ( is_wp_error( $requirements ) ) {
		$problems[] = 'The Newspack theme cannot run on this site: ' . $requirements->get_error_message();
	}

	$plugin_dir = realpath( NEWSPACK_LITE_SITE_PLUGIN_DIR );
	if ( realpath( dirname( __DIR__, 2 ) ) !== $plugin_dir ) {
		$problems[] = sprintf( 'This seed.php is not in the copy of Lite Site the site runs (%s). Run the seed.php inside that plugin folder, or activate this checkout.', $plugin_dir );
	}

	if ( ! current_user_can( 'unfiltered_html' ) ) {
		$problems[] = "The site's administrators can't save unfiltered HTML (DISALLOW_UNFILTERED_HTML), which the demo posts need.";
	}

	if ( $problems ) {
		WP_CLI::error( "Nothing was changed.\n- " . implode( "\n- ", $problems ) . "\nUse a site set aside for screenshots." );
	}
}

/**
 * Warns about posts, RSS entries, categories and accounts the demo doesn't
 * use. The Lite Site homepage lists every published post and RSS entry, the
 * Categories fields in the settings list every category, and the RSS feed
 * Author menu lists every account that can publish, so they can show up in
 * screenshots.
 *
 * @param array $content Demo content.
 */
function newspack_ls_screenshots_warn_others( $content ) {
	$unmarked = [
		[
			'key'     => NEWSPACK_LS_SCREENSHOTS_MARK,
			'compare' => 'NOT EXISTS',
		],
	];
	$others   = get_posts(
		[
			'post_type'   => [ 'post', NEWSPACK_LS_SCREENSHOTS_ENTRY_TYPE ],
			'post_status' => 'publish',
			'numberposts' => 3,
			'meta_query'  => $unmarked, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		]
	);
	if ( $others ) {
		WP_CLI::warning( sprintf( 'The site has posts or RSS entries the seed didn\'t create, such as "%s". The Lite Site homepage lists every published post and RSS entry, so delete them before taking screenshots.', get_the_title( $others[0] ) ) );
	}

	$authors = get_users(
		[
			'capability' => 'publish_posts',
			'fields'     => 'ID',
			'meta_query' => $unmarked, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		]
	);
	if ( count( $authors ) > 1 ) {
		WP_CLI::warning( sprintf( 'The site has %d accounts of its own that can publish posts. The RSS feed Author menu lists all of them, so delete all but the administrator before taking screenshots.', count( $authors ) ) );
	}

	$used       = array_column( $content['posts'], 'category' );
	$categories = get_terms(
		[
			'taxonomy'   => 'category',
			'hide_empty' => false,
		]
	);
	$unused     = [];
	foreach ( is_array( $categories ) ? $categories : [] as $category ) {
		if ( (int) get_option( 'default_category' ) !== $category->term_id && ! in_array( html_entity_decode( $category->name, ENT_QUOTES ), $used, true ) ) {
			$unused[] = html_entity_decode( $category->name, ENT_QUOTES );
		}
	}
	if ( $unused ) {
		WP_CLI::warning( sprintf( 'The site has categories the demo posts don\'t use: %s. The Categories fields in the Lite Site settings list every category, so delete them before taking screenshots.', implode( ', ', $unused ) ) );
	}
}

/**
 * Seeds the demo site.
 *
 * @param array $content Demo content.
 * @param bool  $fresh   Whether to leave Lite Site as it is right after activation.
 */
function newspack_ls_screenshots_seed( $content, $fresh ) {
	$state = newspack_ls_screenshots_state();
	if ( empty( $state['previous'] ) ) {
		$state['previous'] = [
			'blogname'        => get_option( 'blogname' ),
			'blogdescription' => get_option( 'blogdescription' ),
			'show_on_front'   => get_option( 'show_on_front' ),
			'page_on_front'   => get_option( 'page_on_front' ),
			'site_icon'       => get_option( 'site_icon' ),
			'site_logo'       => get_option( 'site_logo' ),
			'stylesheet'      => get_stylesheet(),
			'theme_mods'      => [
				get_stylesheet()              => get_option( 'theme_mods_' . get_stylesheet() ),
				NEWSPACK_LS_SCREENSHOTS_THEME => get_option( 'theme_mods_' . NEWSPACK_LS_SCREENSHOTS_THEME ),
			],
			'custom_css'      => newspack_ls_screenshots_custom_css(),
			'widgets'         => get_option( 'sidebars_widgets' ),
			'widget_block'    => get_option( 'widget_block' ),
			'settings'        => get_option( NEWSPACK_LS_SCREENSHOTS_SETTINGS_OPTION ),
			'feeds'           => get_option( NEWSPACK_LS_SCREENSHOTS_FEEDS_OPTION ),
		];
		newspack_ls_screenshots_save_state( $state );
	}

	newspack_ls_screenshots_install_fakes();
	newspack_ls_screenshots_remove( false );
	delete_option( 'newspack_ls_screenshots_retry_works' );
	$state = newspack_ls_screenshots_state();
	wp_set_current_user( newspack_ls_screenshots_seed_admin( $content['admin'] ) );

	if ( get_stylesheet() !== NEWSPACK_LS_SCREENSHOTS_THEME ) {
		newspack_ls_screenshots_switch_theme( NEWSPACK_LS_SCREENSHOTS_THEME );
	}
	update_option( 'blogname', $content['site']['title'] );
	update_option( 'blogdescription', $content['site']['tagline'] );

	$users           = newspack_ls_screenshots_seed_users( $content['users'] );
	$users['james']  = get_current_user_id();
	$media           = newspack_ls_screenshots_seed_media( $content['media'] );
	$posts           = newspack_ls_screenshots_seed_posts( $content['posts'], $users, $media );
	$state['sticky'] = array_values( array_intersect( $posts, array_map( 'intval', (array) get_option( 'sticky_posts', [] ) ) ) );
	newspack_ls_screenshots_save_state( $state );

	$icon = [
		'file'    => $content['site']['icon'],
		'title'   => 'Site icon',
		'alt'     => '',
		'caption' => '',
	];
	$icon_id = newspack_ls_screenshots_seed_media( [ $icon ] )[ $icon['file'] ];
	update_option( 'site_icon', $icon_id );
	$logo = [
		'file'    => $content['site']['logo'],
		'title'   => 'Site logo',
		'alt'     => '',
		'caption' => '',
	];
	update_option( 'site_logo', newspack_ls_screenshots_seed_media( [ $logo ] )[ $logo['file'] ] );
	update_option( 'show_on_front', 'posts' );
	newspack_ls_screenshots_seed_theme( $content['theme'], [ $icon['file'] => $icon_id ] );
	$state = newspack_ls_screenshots_state();

	if ( $fresh ) {
		delete_option( NEWSPACK_LS_SCREENSHOTS_SETTINGS_OPTION );
	} else {
		update_option( NEWSPACK_LS_SCREENSHOTS_SETTINGS_OPTION, Lite_Site_Settings::sanitize_settings( $content['settings'] ) );
		$state['feeds'] = newspack_ls_screenshots_seed_feeds( $content['feeds'], $users );
		newspack_ls_screenshots_save_state( $state );
		newspack_ls_screenshots_seed_entries( $content['entries'], $content['feeds'], $users );
	}
	$state['stage'] = $fresh ? 'fresh' : 'demo';
	newspack_ls_screenshots_save_state( $state );

	wp_cache_flush();
	set_transient( 'nls_flush_rewrite_rules', true );
}

/**
 * Activates a theme.
 *
 * WordPress runs the switch's follow-up hooks on the next request, with the
 * new theme loaded. There, the Newspack theme copies the previous Newspack
 * theme's mods over its own, and core maps the previous theme's menu
 * locations and widgets onto the new one, which would undo what the seed sets
 * and restores. The seed sets them itself, so those hooks are skipped.
 *
 * @param string $stylesheet Theme to activate.
 */
function newspack_ls_screenshots_switch_theme( $stylesheet ) {
	switch_theme( $stylesheet );
	update_option( 'theme_switched', false );
	delete_option( 'theme_switch_menu_locations' );
}

/**
 * The Additional CSS saved for the Newspack theme.
 *
 * @return string
 */
function newspack_ls_screenshots_custom_css() {
	$post = wp_get_custom_css_post( NEWSPACK_LS_SCREENSHOTS_THEME );
	return $post ? $post->post_content : '';
}

/**
 * Styles the Newspack theme like The Riverbend Gazette: its theme mods, footer
 * logo and Additional CSS, the pages its menus link to, its menus and its
 * widgets.
 *
 * @param array $theme Theme settings from content.json.
 * @param array $media Attachment IDs of the media seeded so far, by file name. The footer logo reuses one of them when it's the same file.
 */
function newspack_ls_screenshots_seed_theme( $theme, $media ) {
	foreach ( $theme['mods'] as $name => $value ) {
		set_theme_mod( $name, $value );
	}
	$footer_logo = $theme['footer_logo'];
	if ( ! isset( $media[ $footer_logo ] ) ) {
		$media += newspack_ls_screenshots_seed_media(
			[
				[
					'file'    => $footer_logo,
					'title'   => 'Footer logo',
					'alt'     => '',
					'caption' => '',
				],
			]
		);
	}
	set_theme_mod( 'newspack_footer_logo', $media[ $footer_logo ] );

	$css_post = wp_get_custom_css_post( NEWSPACK_LS_SCREENSHOTS_THEME );
	$saved    = wp_update_custom_css_post( $theme['custom_css'], [ 'stylesheet' => NEWSPACK_LS_SCREENSHOTS_THEME ] );
	if ( is_wp_error( $saved ) ) {
		newspack_ls_screenshots_fail( 'Could not save the Additional CSS', $saved->get_error_message() );
	}
	if ( ! $css_post ) {
		update_post_meta( $saved->ID, NEWSPACK_LS_SCREENSHOTS_MARK, 1 );
	}

	$pages = [];
	foreach ( $theme['pages'] as $page ) {
		$pages[ $page['slug'] ] = newspack_ls_screenshots_insert_post(
			[
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $page['slug'],
				'post_title'   => $page['title'],
				'post_content' => $page['content'],
			],
			0
		);
	}

	newspack_ls_screenshots_seed_menus( $theme['menus'], $pages );
	newspack_ls_screenshots_seed_widgets( $theme['widgets'], $pages );
}

/**
 * Creates the menus and assigns them to the theme's menu locations.
 *
 * @param array $menus Menus from content.json.
 * @param array $pages Page IDs by slug.
 */
function newspack_ls_screenshots_seed_menus( $menus, $pages ) {
	$locations = [];
	foreach ( $menus as $menu ) {
		$menu_id = wp_create_nav_menu( $menu['name'] );
		if ( is_wp_error( $menu_id ) ) {
			newspack_ls_screenshots_fail( sprintf( 'Could not create the %s menu', $menu['name'] ), $menu_id->get_error_message() );
		}
		update_term_meta( $menu_id, NEWSPACK_LS_SCREENSHOTS_MARK, 1 );

		foreach ( $menu['items'] as $position => $item ) {
			$args = [
				'menu-item-status'   => 'publish',
				'menu-item-position' => $position + 1,
			];
			if ( isset( $item['category'] ) ) {
				$args['menu-item-type']      = 'taxonomy';
				$args['menu-item-object']    = 'category';
				$args['menu-item-object-id'] = newspack_ls_screenshots_term( $item['category'], 'category' );
			} elseif ( isset( $item['page'] ) ) {
				$args['menu-item-type']      = 'post_type';
				$args['menu-item-object']    = 'page';
				$args['menu-item-object-id'] = $pages[ $item['page'] ];
			} else {
				$args['menu-item-type']  = 'custom';
				$args['menu-item-title'] = $item['title'];
				$args['menu-item-url']   = $item['url'];
			}
			$item_id = wp_update_nav_menu_item( $menu_id, 0, $args );
			if ( is_wp_error( $item_id ) ) {
				newspack_ls_screenshots_fail( sprintf( 'Could not add a link to the %s menu', $menu['name'] ), $item_id->get_error_message() );
			}
			update_post_meta( $item_id, NEWSPACK_LS_SCREENSHOTS_MARK, 1 );
		}
		$locations[ $menu['location'] ] = $menu_id;
	}
	set_theme_mod( 'nav_menu_locations', $locations );
}

/**
 * Fills the theme's widget areas with block widgets, replacing what they held
 * and the widgets an earlier run added.
 *
 * @param array $widgets Block markup for each widget, by widget area.
 * @param array $pages   Page IDs by slug.
 */
function newspack_ls_screenshots_seed_widgets( $widgets, $pages ) {
	$state     = newspack_ls_screenshots_state();
	$instances = get_option( 'widget_block' );
	$instances = is_array( $instances ) ? $instances : [];
	$sidebars  = get_option( 'sidebars_widgets' );
	$sidebars  = is_array( $sidebars ) ? $sidebars : [ 'array_version' => 3 ];

	$added = $state['widgets_added'] ?? [];
	foreach ( $added as $number ) {
		unset( $instances[ $number ] );
	}
	foreach ( $sidebars as $sidebar => $ids ) {
		if ( is_array( $ids ) ) {
			$sidebars[ $sidebar ] = array_values( array_diff( $ids, preg_filter( '/^/', 'block-', $added ) ) );
		}
	}

	$links = [];
	foreach ( $pages as $slug => $page_id ) {
		$links[ '{{page:' . $slug . '}}' ] = get_permalink( $page_id );
	}

	$added  = [];
	$number = max( array_merge( [ 1 ], array_filter( array_keys( $instances ), 'is_int' ) ) );
	foreach ( $widgets as $sidebar => $blocks ) {
		$kept = $sidebars[ $sidebar ] ?? [];
		if ( $kept ) {
			$sidebars['wp_inactive_widgets'] = array_merge( $sidebars['wp_inactive_widgets'] ?? [], $kept );
		}
		$sidebars[ $sidebar ] = [];
		foreach ( $blocks as $block ) {
			++$number;
			$instances[ $number ]   = [ 'content' => strtr( $block, $links ) ];
			$sidebars[ $sidebar ][] = 'block-' . $number;
			$added[]                = $number;
		}
	}
	$instances['_multiwidget'] = 1;

	update_option( 'widget_block', $instances );
	update_option( 'sidebars_widgets', $sidebars );
	$state['widgets_added'] = $added;
	newspack_ls_screenshots_save_state( $state );
}

/**
 * Creates the administrator the screenshots show, unless the seed created it
 * on an earlier run, and prints its password once.
 *
 * @param array $admin Administrator from content.json.
 * @return int User ID.
 */
function newspack_ls_screenshots_seed_admin( $admin ) {
	$existing = get_user_by( 'login', $admin['login'] );
	if ( $existing ) {
		return $existing->ID;
	}
	$password = wp_generate_password( 24, false );
	$user_id  = newspack_ls_screenshots_insert_user( array_merge( $admin, [ 'role' => 'administrator' ] ), $password );
	WP_CLI::log( "Created the administrator {$admin['login']} with the password {$password}" );
	WP_CLI::log( "It's shown only this once. To set a new one: wp user update {$admin['login']} --user_pass=<new password>" );
	return $user_id;
}

/**
 * Creates the authors, or reuses the ones an earlier run created.
 *
 * @param array $users Users from content.json.
 * @return array<string, int> User IDs by login.
 */
function newspack_ls_screenshots_seed_users( $users ) {
	$ids = [];
	foreach ( $users as $user ) {
		$existing              = get_user_by( 'login', $user['login'] );
		$ids[ $user['login'] ] = $existing ? $existing->ID : newspack_ls_screenshots_insert_user( $user, wp_generate_password( 32 ) );
	}
	return $ids;
}

/**
 * Inserts a marked user.
 *
 * @param array  $user     User from content.json.
 * @param string $password Password.
 * @return int User ID.
 */
function newspack_ls_screenshots_insert_user( $user, $password ) {
	$user_id = wp_insert_user(
		[
			'user_login'   => $user['login'],
			'user_email'   => $user['email'],
			'user_pass'    => $password,
			'first_name'   => $user['first_name'],
			'last_name'    => $user['last_name'],
			'display_name' => trim( $user['first_name'] . ' ' . $user['last_name'] ),
			'role'         => $user['role'],
		]
	);
	if ( is_wp_error( $user_id ) ) {
		newspack_ls_screenshots_fail( sprintf( 'Could not create the user %s', $user['login'] ), $user_id->get_error_message() );
	}
	update_user_meta( $user_id, NEWSPACK_LS_SCREENSHOTS_MARK, 1 );
	return $user_id;
}

/**
 * Adds photos to the media library, with their captions and the credits the
 * Newspack plugin shows under them.
 *
 * @param array $media Media from content.json.
 * @return array<string, int> Attachment IDs by file name.
 */
function newspack_ls_screenshots_seed_media( $media ) {
	$ids = [];
	foreach ( $media as $item ) {
		$bits = newspack_ls_screenshots_read_file( 'assets/media/' . $item['file'] );
		$file = false === $bits ? [ 'error' => 'the file is not readable' ] : wp_upload_bits( $item['file'], null, $bits );
		if ( ! empty( $file['error'] ) ) {
			newspack_ls_screenshots_fail( sprintf( 'Could not upload %s', $item['file'] ), $file['error'] );
		}
		$meta = [
			NEWSPACK_LS_SCREENSHOTS_MARK => 1,
			'_wp_attachment_image_alt'   => $item['alt'],
		];
		$attachment_id = wp_insert_attachment(
			wp_slash(
				[
					'post_title'     => $item['title'],
					'post_excerpt'   => $item['caption'],
					'post_mime_type' => wp_check_filetype( $item['file'] )['type'],
					'post_status'    => 'inherit',
					'meta_input'     => $meta,
				]
			),
			$file['file'],
			0,
			true
		);
		if ( is_wp_error( $attachment_id ) ) {
			newspack_ls_screenshots_fail( sprintf( 'Could not add %s to the media library', $item['file'] ), $attachment_id->get_error_message() );
		}
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file['file'] ) );
		if ( ! empty( $item['credit'] ) ) {
			update_post_meta( $attachment_id, '_media_credit', wp_slash( $item['credit'] ) );
		}
		if ( ! empty( $item['credit_org'] ) ) {
			update_post_meta( $attachment_id, '_navis_media_credit_org', wp_slash( $item['credit_org'] ) );
		}
		$ids[ $item['file'] ] = $attachment_id;
	}
	return $ids;
}

/**
 * Finds or creates a term, marking the ones it creates.
 *
 * @param string $name     Term name.
 * @param string $taxonomy Taxonomy.
 * @return int Term ID.
 */
function newspack_ls_screenshots_term( $name, $taxonomy ) {
	$term = get_term_by( 'name', $name, $taxonomy );
	if ( $term ) {
		return (int) $term->term_id;
	}
	$term = wp_insert_term( $name, $taxonomy );
	if ( is_wp_error( $term ) ) {
		newspack_ls_screenshots_fail( sprintf( 'Could not create the term %s', $name ), $term->get_error_message() );
	}
	update_term_meta( $term['term_id'], NEWSPACK_LS_SCREENSHOTS_MARK, 1 );
	return (int) $term['term_id'];
}

/**
 * Creates the posts, with their categories, tags, standfirsts, featured images
 * and sticky flags, then fills in the links between them. A post's standfirst
 * is its excerpt unless content.json gives it a subtitle.
 *
 * @param array $posts Posts from content.json.
 * @param array $users User IDs by login.
 * @param array $media Attachment IDs by file name.
 * @return array<string, int> Post IDs by slug.
 */
function newspack_ls_screenshots_seed_posts( $posts, $users, $media ) {
	$tokens = [];
	foreach ( $media as $file => $attachment_id ) {
		$tokens[ '"{{media-id:' . $file . '}}"' ] = (string) $attachment_id;
		$tokens[ '{{media-id:' . $file . '}}' ]   = (string) $attachment_id;
		$tokens[ '{{media-url:' . $file . '}}' ]  = (string) wp_get_attachment_image_url( $attachment_id, 'large' );
	}

	$ids = [];
	foreach ( $posts as $post ) {
		$post_id = newspack_ls_screenshots_insert_post(
			[
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_name'     => $post['slug'],
				'post_title'    => $post['title'],
				'post_excerpt'  => $post['excerpt'],
				'post_content'  => strtr( $post['content'], $tokens ),
				'post_author'   => $users[ $post['author'] ],
				'post_category' => [ newspack_ls_screenshots_term( $post['category'], 'category' ) ],
				'meta_input'    => [ NEWSPACK_LS_SCREENSHOTS_SUBTITLE => $post['subtitle'] ?? $post['excerpt'] ],
				'tags_input'    => array_map(
					function ( $tag ) {
						return newspack_ls_screenshots_term( $tag, 'post_tag' );
					},
					$post['tags']
				),
			],
			$post['age']
		);
		if ( ! empty( $post['image'] ) ) {
			set_post_thumbnail( $post_id, $media[ $post['image'] ] );
		}
		if ( ! empty( $post['sticky'] ) ) {
			stick_post( $post_id );
		}
		$ids[ $post['slug'] ] = $post_id;
	}

	$links = [];
	foreach ( $ids as $slug => $post_id ) {
		$links[ '{{post:' . $slug . '}}' ] = get_permalink( $post_id );
	}
	foreach ( $ids as $post_id ) {
		$text = get_post_field( 'post_content', $post_id );
		if ( false !== strpos( $text, '{{post:' ) ) {
			wp_update_post(
				wp_slash(
					[
						'ID'           => $post_id,
						'post_content' => strtr( $text, $links ),
					]
				)
			);
		}
	}
	return $ids;
}

/**
 * Stores the RSS feeds with the results of their last runs, and schedules the
 * active ones. The feeds point at made-up hosts, and the fakes plugin answers
 * their scheduled runs and Retry Now for them, so nothing is fetched.
 *
 * @param array $feeds Feeds from content.json.
 * @param array $users User IDs by login.
 * @return array Seeded feeds, keyed by feed ID, with the key each has in content.json.
 */
function newspack_ls_screenshots_seed_feeds( $feeds, $users ) {
	$option = newspack_ls_screenshots_feeds();
	$seeded = [];
	foreach ( $feeds as $key => $feed ) {
		$feed_id            = RSS_Importer::get_feed_id( $feed['url'] );
		$option[ $feed_id ] = [
			'feed_url'    => $feed['url'],
			'interval'    => $feed['interval'],
			'author_id'   => $users[ $feed['author'] ],
			'status'      => $feed['status'],
			'last_run'    => time() - (int) $feed['last_run'],
			'last_result' => $feed['last_result'],
		];
		wp_clear_scheduled_hook( NEWSPACK_LS_SCREENSHOTS_CRON_HOOK, [ $feed_id ] );
		if ( 'active' === $feed['status'] ) {
			$scheduled = wp_schedule_event( time() + (int) $feed['next_run'], $feed['interval'], NEWSPACK_LS_SCREENSHOTS_CRON_HOOK, [ $feed_id ], true );
			if ( is_wp_error( $scheduled ) ) {
				newspack_ls_screenshots_fail( sprintf( 'Could not schedule the RSS feed %s', $feed['url'] ), $scheduled->get_error_message() );
			}
		}
		$seeded[ $feed_id ] = $key;
	}
	update_option( NEWSPACK_LS_SCREENSHOTS_FEEDS_OPTION, [ 'feeds' => $option ] );
	return $seeded;
}

/**
 * Creates the RSS entries the feeds imported.
 *
 * @param array $entries Entries from content.json.
 * @param array $feeds   Feeds from content.json.
 * @param array $users   User IDs by login.
 */
function newspack_ls_screenshots_seed_entries( $entries, $feeds, $users ) {
	foreach ( $entries as $entry ) {
		$feed     = $feeds[ $entry['feed'] ];
		$entry_id = newspack_ls_screenshots_insert_post(
			[
				'post_type'    => NEWSPACK_LS_SCREENSHOTS_ENTRY_TYPE,
				'post_status'  => $entry['status'] ?? 'publish',
				'post_title'   => $entry['title'],
				'post_content' => $entry['content'],
				'post_author'  => $users[ $feed['author'] ],
			],
			$entry['age']
		);
		update_post_meta( $entry_id, '_rss_import_guid', $entry['guid'] );
		update_post_meta( $entry_id, '_rss_import_source_url', esc_url_raw( $entry['source'] ) );
		update_post_meta( $entry_id, '_rss_import_feed_url', esc_url_raw( $feed['url'] ) );
	}
}

/**
 * Inserts a marked post dated a given number of seconds before now.
 *
 * @param array $postarr Post fields.
 * @param int   $age     Seconds before now.
 * @return int Post ID.
 */
function newspack_ls_screenshots_insert_post( $postarr, $age ) {
	$date_gmt = gmdate( 'Y-m-d H:i:s', time() - (int) $age );
	$post_id  = wp_insert_post(
		wp_slash(
			array_merge(
				$postarr,
				[
					'post_date'     => get_date_from_gmt( $date_gmt ),
					'post_date_gmt' => $date_gmt,
					'meta_input'    => array_merge( $postarr['meta_input'] ?? [], [ NEWSPACK_LS_SCREENSHOTS_MARK => 1 ] ),
				]
			)
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		newspack_ls_screenshots_fail( sprintf( 'Could not create "%s"', $postarr['post_title'] ), $post_id->get_error_message() );
	}
	return $post_id;
}

/**
 * Copies the fakes plugin into mu-plugins.
 */
function newspack_ls_screenshots_install_fakes() {
	wp_mkdir_p( WPMU_PLUGIN_DIR );
	if ( ! copy( __DIR__ . '/fakes.php', WPMU_PLUGIN_DIR . '/' . NEWSPACK_LS_SCREENSHOTS_FAKES ) ) { // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_copy
		newspack_ls_screenshots_fail( 'Could not copy fakes.php into ' . WPMU_PLUGIN_DIR, 'the folder is not writable' );
	}
}

/**
 * Deletes what the seed marked, and the RSS feeds it added. A full removal
 * also deletes the accounts it created, puts back the settings it changed and
 * removes the fakes plugin; the reset before each seed keeps the accounts and
 * settings.
 *
 * @param bool $everything Whether this is a full removal.
 */
function newspack_ls_screenshots_remove( $everything ) {
	$state  = newspack_ls_screenshots_state();
	$marked = [
		'key'   => NEWSPACK_LS_SCREENSHOTS_MARK,
		'value' => '1',
	];

	$posts = get_posts(
		[
			'post_type'   => array_values( array_unique( array_merge( get_post_types(), [ NEWSPACK_LS_SCREENSHOTS_ENTRY_TYPE ] ) ) ),
			'post_status' => array_keys( get_post_stati() ),
			'numberposts' => -1, // phpcs:ignore WordPressVIPMinimum.Performance.NoPaging.posts_per_page_numberposts -- Every marked item, from a command line run.
			'fields'      => 'ids',
			'meta_query'  => [ $marked ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		]
	);
	$posts = array_values(
		array_filter(
			array_map( 'intval', $posts ),
			function ( $post_id ) {
				return '1' === get_post_meta( $post_id, NEWSPACK_LS_SCREENSHOTS_MARK, true );
			}
		)
	);
	foreach ( $posts as $post_id ) {
		if ( 'attachment' === get_post_type( $post_id ) ) {
			wp_delete_attachment( $post_id, true );
		} else {
			wp_delete_post( $post_id, true );
		}
	}

	$sticky = array_map( 'intval', (array) get_option( 'sticky_posts', [] ) );
	$kept   = array_values( array_diff( $sticky, $posts, $state['sticky'] ?? [] ) );
	if ( $kept !== $sticky ) {
		update_option( 'sticky_posts', $kept );
	}
	$state['sticky'] = [];

	foreach ( [ 'category', 'post_tag', 'nav_menu' ] as $taxonomy ) {
		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
				'meta_query' => [ $marked ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			]
		);
		foreach ( $terms as $term_id ) {
			if ( '1' === get_term_meta( $term_id, NEWSPACK_LS_SCREENSHOTS_MARK, true ) ) {
				wp_delete_term( $term_id, $taxonomy );
			}
		}
	}

	if ( ! empty( $state['feeds'] ) ) {
		$feeds = newspack_ls_screenshots_feeds();
		foreach ( array_keys( $state['feeds'] ) as $feed_id ) {
			wp_clear_scheduled_hook( NEWSPACK_LS_SCREENSHOTS_CRON_HOOK, [ $feed_id ] );
			unset( $feeds[ $feed_id ] );
		}
		if ( ! $feeds && false === ( $state['previous']['feeds'] ?? false ) ) {
			delete_option( NEWSPACK_LS_SCREENSHOTS_FEEDS_OPTION );
		} else {
			update_option( NEWSPACK_LS_SCREENSHOTS_FEEDS_OPTION, [ 'feeds' => $feeds ] );
		}
		$state['feeds'] = [];
	}
	if ( $state ) {
		newspack_ls_screenshots_save_state( $state );
	}

	if ( ! $everything ) {
		return;
	}

	$reassign = newspack_ls_screenshots_site_admin();
	$users    = get_users(
		[
			'fields'     => 'ID',
			'meta_query' => [ $marked ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		]
	);
	$admins   = array_map(
		'intval',
		get_users(
			[
				'role'   => 'administrator',
				'fields' => 'ID',
			]
		)
	);
	foreach ( array_unique( array_map( 'intval', $users ) ) as $user_id ) {
		if ( '1' !== get_user_meta( $user_id, NEWSPACK_LS_SCREENSHOTS_MARK, true ) ) {
			continue;
		}
		if ( in_array( $user_id, $admins, true ) && count( $admins ) <= 1 ) {
			WP_CLI::warning( "Kept user {$user_id}: it's the site's only administrator." );
			continue;
		}
		$admins = array_values( array_diff( $admins, [ $user_id ] ) );
		if ( $reassign ) {
			wp_delete_user( (int) $user_id, $reassign );
		} elseif ( ! newspack_ls_screenshots_has_posts( (int) $user_id ) ) {
			wp_delete_user( (int) $user_id );
		} else {
			WP_CLI::warning( "Kept user {$user_id}: it has posts and there's no other administrator to give them to." );
		}
	}

	if ( ! empty( $state['previous'] ) ) {
		$previous = $state['previous'];
		foreach ( [ 'blogname', 'blogdescription', 'show_on_front', 'page_on_front', 'site_icon' ] as $option ) {
			update_option( $option, $previous[ $option ] );
		}
		if ( false === $previous['settings'] ) {
			delete_option( NEWSPACK_LS_SCREENSHOTS_SETTINGS_OPTION );
		} else {
			update_option( NEWSPACK_LS_SCREENSHOTS_SETTINGS_OPTION, $previous['settings'] );
		}
		newspack_ls_screenshots_restore_theme( $previous );
	}
	delete_option( NEWSPACK_LS_SCREENSHOTS_STATE );
	delete_option( 'newspack_ls_screenshots_retry_works' );

	$fakes = WPMU_PLUGIN_DIR . '/' . NEWSPACK_LS_SCREENSHOTS_FAKES;
	if ( file_exists( $fakes ) ) {
		wp_delete_file( $fakes );
	}
	wp_cache_flush();
	set_transient( 'nls_flush_rewrite_rules', true );
}

/**
 * Puts back the theme the site had before the seed, the widgets, theme mods
 * and custom CSS the seed changed, and the site logo.
 *
 * Core keeps the site_logo option and the active theme's custom_logo mod in
 * step: it deletes site_logo when the mods of the theme the request started
 * with are deleted or lose their custom_logo, and removes the active theme's
 * custom_logo when site_logo is deleted. A removal normally starts with the
 * Newspack theme active, so deleting its mods after switching back would delete
 * site_logo and, through it, the custom_logo just restored to the previous
 * theme. Both deletions are off for the whole restore: the first through
 * core's $_ignore_site_logo_changes flag, the second by unhooking it, since
 * that callback ignores the flag and clears it. The logo is then put back
 * exactly as it was.
 *
 * @param array $previous The settings from before the seed.
 */
function newspack_ls_screenshots_restore_theme( $previous ) {
	global $_ignore_site_logo_changes;

	$ignore                    = $_ignore_site_logo_changes;
	$_ignore_site_logo_changes = true;
	$sync                      = has_action( 'delete_option_site_logo', '_delete_custom_logo_on_remove_site_logo' );
	if ( false !== $sync ) {
		remove_action( 'delete_option_site_logo', '_delete_custom_logo_on_remove_site_logo', $sync );
	}

	if ( get_stylesheet() !== $previous['stylesheet'] ) {
		$requirements = wp_get_theme( $previous['stylesheet'] )->exists() ? validate_theme_requirements( $previous['stylesheet'] ) : false;
		if ( is_wp_error( $requirements ) ) {
			WP_CLI::warning( "The site's previous theme, {$previous['stylesheet']}, can't run on this site ({$requirements->get_error_message()}), so the Newspack theme stays active." );
		} elseif ( $requirements ) {
			newspack_ls_screenshots_switch_theme( $previous['stylesheet'] );
		} else {
			WP_CLI::warning( "The site's previous theme, {$previous['stylesheet']}, is no longer installed, so the Newspack theme stays active." );
		}
	}

	$options = [
		'sidebars_widgets' => $previous['widgets'] ?? null,
		'widget_block'     => $previous['widget_block'] ?? null,
	];
	foreach ( $previous['theme_mods'] ?? [] as $stylesheet => $mods ) {
		$options[ 'theme_mods_' . $stylesheet ] = $mods;
	}
	foreach ( array_filter( $options, 'is_array' ) as $option => $value ) {
		update_option( $option, $value );
	}
	foreach ( array_keys( $options, false, true ) as $option ) {
		delete_option( $option );
	}

	if ( isset( $previous['custom_css'] ) && wp_get_custom_css_post( NEWSPACK_LS_SCREENSHOTS_THEME ) ) {
		wp_update_custom_css_post( $previous['custom_css'], [ 'stylesheet' => NEWSPACK_LS_SCREENSHOTS_THEME ] );
	}

	if ( false === $previous['site_logo'] ) {
		delete_option( 'site_logo' );
	} else {
		update_option( 'site_logo', $previous['site_logo'] );
	}

	if ( false !== $sync ) {
		add_action( 'delete_option_site_logo', '_delete_custom_logo_on_remove_site_logo', $sync );
	}
	$_ignore_site_logo_changes = $ignore;
}

/**
 * Whether a user wrote any post, of any type or status.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function newspack_ls_screenshots_has_posts( $user_id ) {
	return (bool) get_posts(
		[
			'author'      => $user_id,
			'post_type'   => array_values( get_post_types() ),
			'post_status' => array_keys( get_post_stati() ),
			'numberposts' => 1,
			'fields'      => 'ids',
		]
	);
}

newspack_ls_screenshots_main( $args ?? [] );
