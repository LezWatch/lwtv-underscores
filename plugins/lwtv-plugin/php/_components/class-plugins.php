<?php
/*
 * Plugins
 */
namespace LWTV\_Components;

use LWTV\Plugins\ActionScheduler;
use LWTV\Plugins\CMB2;
use LWTV\Plugins\Comment_Probation;
use LWTV\Plugins\FacetWP;
use LWTV\Plugins\Gravity_Forms;
use LWTV\Plugins\MonsterInsights;
use LWTV\Plugins\Postiz;
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
		new ActionScheduler();
		new Comment_Probation();
		new CMB2();
		new FacetWP();
		new Gravity_Forms();
		new MonsterInsights();
		new Postiz();
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
			/** @disregard sharing_display() is provided by the sharing plugin **/
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
							<a
								rel="nofollow noopener noreferrer"
								data-shared="sharing-bluesky-<?php echo esc_attr( $post_id ); ?>"
								class="share-bluesky lwtv-share-button share-icon no-text"
								href="https://bsky.app/intent/compose?text=<?php echo esc_url( $post_url ); ?>&text=<?php echo esc_attr( the_title() ); ?>"
								target="_blank"
								title="Click to share on Bluesky"
								onclick="window.open(this.href, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=468,width=768');return false;"
							>
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo lwtv_plugin()->get_symbolicon( svg: 'bluesky.svg', icon: 'svg-bluesky', max_size: '20' );
								?>
								<span class="sharing-screen-reader-text">Click to share on Bluesky (Opens in new window)</span>
							</a>
						</li>

						<li class="share-tumblr">
							<a
								rel="nofollow noopener noreferrer"
								data-shared="sharing-tumblr-<?php echo esc_attr( $post_id ); ?>"
								class="share-tumblr lwtv-share-button share-icon no-text"
								href="https://www.tumblr.com/share/link?url=<?php echo rawurlencode( $post_url ); ?>"
								target="_blank"
								title="Click to share on Tumblr"
								onclick="window.open(this.href, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=468,width=768');return false;"
							>
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo lwtv_plugin()->get_symbolicon( svg: 'tumblr-t.svg', icon: 'svg-tumblr-t', max_size: '20' );
								?>
								<span class="sharing-screen-reader-text">Click to share on Tumblr (Opens in new window)</span>
							</a>
						</li>

						<li class="share-facebook">
							<a
								rel="nofollow noopener noreferrer"
								data-shared="sharing-facebook-<?php echo esc_attr( $post_id ); ?>"
								class="share-facebook lwtv-share-button share-icon no-text"
								href="https://www.facebook.com/sharer/sharer.php?u=<?php echo esc_url( $post_url ); ?>"
								target="_blank"
								title="Click to share on Facebook"
								onclick="window.open(this.href, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=468,width=768');return false;"
							>
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo lwtv_plugin()->get_symbolicon( svg: 'facebook-f.svg', icon: 'svg-facebook-f', max_size: '20' );
								?>
								<span class="sharing-screen-reader-text">Click to share on Facebook (Opens in new window)</span>
							</a>
						</li>

						<li class="share-mastodon">
							<a
								rel="nofollow noopener noreferrer"
								data-shared="sharing-mastodon-<?php echo esc_attr( $post_id ); ?>"
								class="share-mastodon lwtv-share-button share-icon no-text"
								href="https://mastodonshare.com/share?text=<?php echo esc_attr( $title ) . rawurlencode( $post_url ); ?>"
								target="_blank"
								title="Click to share on Mastodon"
								onclick="window.open(this.href, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=468,width=768');return false;"
							>
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo lwtv_plugin()->get_symbolicon( svg: 'mastodon.svg', icon: 'svg-mastodon', max_size: '20' );
								?>
								<span class="sharing-screen-reader-text">Click to share on Mastodon (Opens in new window)</span>
							</a>
						</li>

						<li class="share-reddit">
							<a
								rel="nofollow noopener noreferrer"
								data-shared="sharing-reddit-<?php echo esc_attr( $post_id ); ?>"
								class="share-reddit lwtv-share-button share-icon no-text"
								href="http://www.reddit.com/submit?url=<?php echo esc_url( $post_url ); ?>&title=<?php echo esc_attr( $title ); ?>"
								target="_blank"
								title="Click to share on Reddit"
								onclick="window.open(this.href, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=468,width=768');return false;"
							>
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo lwtv_plugin()->get_symbolicon( svg: 'reddit.svg', icon: 'svg-reddit', max_size: '20' );
								?>
								<span class="sharing-screen-reader-text">Click to share on Reddit (Opens in new window)</span>
							</a>
						</li>

						<li class="share-x">
							<a
								rel="nofollow noopener noreferrer"
								data-shared="sharing-x-<?php echo esc_attr( $post_id ); ?>"
								class="share-x lwtv-share-button share-icon no-text"
								href="https://x.com/intent/tweet?url=<?php echo esc_url( $post_url ); ?>&text=<?php echo esc_attr( 'I just checked out ' . $title . ' on LezWatch.TV' ); ?>&via=lezwatchtv"
								target="_blank"
								title="Click to share on X/Twitter"
								onclick="window.open(this.href, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=468,width=768');return false;"
							>
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo lwtv_plugin()->get_symbolicon( svg: 'x-twitter.svg', icon: 'svg-x-twitter', max_size: '20' );
								?>
								<span class="sharing-screen-reader-text">Click to share on X/Twitter (Opens in new window)</span>
							</a>
						</li>
						<li class="share-email">
							<a
								rel="nofollow noopener noreferrer"
								data-shared="sharing-email-<?php echo esc_attr( $post_id ); ?>"
								class="share-email lwtv-share-button share-icon no-text"
								href="mailto:?subject=<?php echo esc_attr( $title ); ?>&amp;body=<?php echo esc_url( $post_url ); ?>&amp;share=email&amp;nb=1"
								target="_blank"
								title="Click to email a link to a friend"
								onclick="window.open(this.href, '', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=468,width=768');return false;"
							>
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								echo lwtv_plugin()->get_symbolicon( svg: 'mail.svg', icon: 'svg-mail', max_size: '20' );
								?>
								<span class="sharing-screen-reader-text">Click to email a link to a friend (Opens in new window)</span>
							</a>
						</li>
						<li class="share-end"></li>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}
}
