<?php
/**
 * Styles for lite site liveblog pages
 *
 * @package newspack-lite-site
 */

namespace Newspack_Lite_Site;

?>
<style>
	.liveblog-status {
		background: #767676;
		color: #fff;
		display: inline-block;
		font-size: 0.75rem;
		font-weight: bold;
		margin-bottom: 1.5rem;
		padding: 0.25rem 0.5rem;
		text-transform: uppercase;
	}
	.liveblog-status.enable {
		background: <?php echo esc_html( Lite_Site::get_primary_color() ); ?>;
	}
	.liveblog-entry {
		border-bottom: 1px solid currentcolor;
		margin-bottom: 1.5rem;
		padding-bottom: 1.5rem;
	}
	.liveblog-entry:last-child {
		border-bottom: none;
	}
	.liveblog-entry-meta {
		font-size: 0.85rem;
		margin-bottom: 0.5rem;
		opacity: 0.7;
	}

	<?php
	/**
	 * Fires at the end of the style tag in lite site liveblog templates.
	 */
	do_action( 'newspack_lite_site_liveblog_styles' );
	?>

</style>
