<?php
/*
 * Plugins
 */
namespace LWTV\_Components;

use LWTV\Plugins\CMB2;
use LWTV\Plugins\Comment_Probation;
use LWTV\Plugins\FacetWP;
use LWTV\Plugins\Gravity_Forms;
use LWTV\Plugins\Jetpack;
use LWTV\Plugins\Related_Posts_By_Taxonomy;
use LWTV\Plugins\SearchWP;
use LWTV\Plugins\WP_Rocket;
use LWTV\Plugins\Yoast;

class Plugins implements Component, Templater {

	/*
	 * Init
	 *
	 * Call the sub plugins
	 */
	public function init(): void {
		new Comment_Probation();
		new CMB2();
		new FacetWP();
		new Gravity_Forms();
		new Jetpack();
		new Related_Posts_By_Taxonomy();
		new SearchWP();
		new WP_Rocket();
		new Yoast();

		// Shadow Taxonomy
		require_once LWTV_THEME_PATH . '/plugins/shadow-taxonomy/index.php';
	}

	/**
	 * Gets tags to expose as methods accessible through `lwtv_plugin()`.
	 *
	 * @return array Associative array of $method_name => $callback_info pairs. Each $callback_info must either be
	 *               a callable or an array with key 'callable'. This approach is used to reserve the possibility of
	 *               adding support for further arguments in the future.
	 */
	public function get_template_tags(): array {
		return array(
			'post_meta_sharing' => array( $this, 'post_meta_sharing' ),
		);
	}

	/**
	 * Create post meta sharing
	 *
	 * @param  int    $post_id
	 * @return mixed
	 */
	public function post_meta_sharing( $post_id = 0 ) {
		if ( function_exists( 'sharing_display' ) ) {
			sharing_display( '', true );
		}

		if ( 0 === $post_id ) {
			return;
		}

		$title    = get_the_title( $post_id );
		$post_url = get_permalink( $post_id );

		?>
		<div class="lwtvshare lwtv-sharing-enabled">
			<div class="robots-nocontent sd-block sd-social lwtv-social-icon lwtv-sharing">
				<h3 class="lwtv-share-title">Share this:</h3>
				<div class="lwtv-share-content">
					<ul data-sharing-events-added="true">
						<li class="share-bluesky">
							<a rel="nofollow noopener noreferrer" data-shared="sharing-bluesky-<?php echo esc_attr( $post_id ); ?>" class="share-bluesky lwtv-share-button share-icon no-text" href="https://bsky.app/intent/compose?text=<?php echo esc_url( $post_url ); ?>&text=<?php echo esc_attr( the_title() ); ?>" target="_blank" title="Click to share on Bluesky" onclick="window.open(this.href, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=468,width=768');return false;"><span></span><span class="sharing-screen-reader-text">Click to share on Bluesky (Opens in new window)</span></a>
						</li>
						<li class="share-tumblr">
							<a rel="nofollow noopener noreferrer" data-shared="sharing-tumblr-<?php echo esc_attr( $post_id ); ?>" class="share-tumblr lwtv-share-button share-icon no-text" href="http://www.tumblr.com/share/link?url=<?php echo rawurlencode( $post_url ); ?>" target="_blank" title="Click to share on Tumblr" onclick="window.open(this.href, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=468,width=768');return false;"><span></span><span class="sharing-screen-reader-text">Click to share on Tumblr (Opens in new window)</span></a>
						</li>
						<li class="share-facebook">
							<a rel="nofollow noopener noreferrer" data-shared="sharing-facebook-<?php echo esc_attr( $post_id ); ?>" class="share-facebook lwtv-share-button share-icon no-text" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo esc_url( $post_url ); ?>" target="_blank" title="Click to share on Facebook" onclick="window.open(this.href, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=468,width=768');return false;"><span></span><span class="sharing-screen-reader-text">Click to share on Facebook (Opens in new window)</span></a>
						</li>
						<li class="share-mastodon">
							<a rel="nofollow noopener noreferrer" data-shared="sharing-mastodon-<?php echo esc_attr( $post_id ); ?>" class="share-mastodon lwtv-share-button share-icon no-text" href="https://mastodonshare.com/share?text=<?php echo esc_attr( $title ) . rawurlencode( $post_url ); ?>" target="_blank" title="Click to share on Mastodon" onclick="window.open(this.href, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=468,width=768');return false;"><span></span><span class="sharing-screen-reader-text">Click to share on Mastodon (Opens in new window)</span></a>
						</li>
						<li class="share-reddit">
							<a rel="nofollow noopener noreferrer" data-shared="sharing-reddit-<?php echo esc_attr( $post_id ); ?>" class="share-reddit lwtv-share-button share-icon no-text" href="http://www.reddit.com/submit?url=<?php echo esc_url( $post_url ); ?>&title=<?php echo esc_attr( $title ); ?>" target="_blank" title="Click to share on Reddit" onclick="window.open(this.href, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=468,width=768');return false;"><span></span><span class="sharing-screen-reader-text">Click to share on Reddit (Opens in new window)</span></a>
						</li>
						<li class="share-x">
							<a rel="nofollow noopener noreferrer" data-shared="sharing-x-<?php echo esc_attr( $post_id ); ?>" class="share-x lwtv-share-button share-icon no-text" href="<?php echo esc_url( $post_url ); ?>&text=<?php echo esc_attr( the_title() ); ?>&via=lezwatchtv" target="_blank" title="Click to share on X/Twitter" onclick="window.open(this.href, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=468,width=768');return false;"><span></span><span class="sharing-screen-reader-text">Click to share on X/Twitter (Opens in new window)</span></a>
						</li>
						<li class="share-email">
							<a rel="nofollow noopener noreferrer" data-shared="sharing-email-<?php echo esc_attr( $post_id ); ?>" class="share-email lwtv-share-button share-icon no-text" href="mailto:?subject=%5BShared%20Post%5D%20New%20Feature%3A%20Calendar%20Views&amp;body=https%3A%2F%2Flezwatchtv.com%2F2024%2Fnew-feature-calendar-views%2F&amp;share=email&amp;nb=1" target="_blank" title="Click to email a link to a friend" data-email-share-error-title="Do you have email set up?" data-email-share-error-text="If you're having problems sharing via email, you might not have email set up for your browser. You may need to create a new email yourself." data-email-share-nonce="bf74ce8659" data-email-share-track-url="https://lezwatchtv.com/2024/new-feature-calendar-views/?share=email" jetpack-share-click-count="0"><span></span><span class="sharing-screen-reader-text">Click to email a link to a friend (Opens in new window)</span></a>
						</li>
						<li class="share-end"></li>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}
}
