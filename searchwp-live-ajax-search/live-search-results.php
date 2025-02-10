<?php
/**
 * SearchWP Live Search Results Template
 *
 * More Info: https://searchwp.com/documentation/extensions/live-search/#customizing-results
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// DO NOT remove global $post; unless you're being intentional.
global $post;

$settings = searchwp_live_search()->get( 'Settings_Api' )->get();
?>

<?php
/**
 * $live_search_results is an array of entries, defined within the SearchWP Live Search plugin
 */
if ( ! empty( $live_search_results ) ) :
	?>
	<div class="<?php echo ! empty( $container_classes ) ? esc_attr( $container_classes ) : ''; ?>">
		<?php foreach ( $live_search_results as $search_result ) : ?>
			<?php $display_data = SearchWP_Live_Search_Template::get_display_data( $search_result ); ?>

			<div class="searchwp-live-search-result" role="option" id="" aria-selected="false">

				<?php if ( $settings['swp-image-size'] ) : ?>
				<div class="searchwp-live-search-result--img">
					<?php if ( ! empty( $display_data['image_html'] ) ) : ?>
						<?php echo wp_kses_post( $display_data['image_html'] ); ?>
					<?php else : ?>
						<svg viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
							<rect width="120" height="120" fill="#EFF1F3"/>
							<path fill-rule="evenodd" clip-rule="evenodd" d="M33.2503 38.4816C33.2603 37.0472 34.4199 35.8864 35.8543 35.875H83.1463C84.5848 35.875 85.7503 37.0431 85.7503 38.4816V80.5184C85.7403 81.9528 84.5807 83.1136 83.1463 83.125H35.8543C34.4158 83.1236 33.2503 81.957 33.2503 80.5184V38.4816ZM80.5006 41.1251H38.5006V77.8751L62.8921 53.4783C63.9172 52.4536 65.5788 52.4536 66.6039 53.4783L80.5006 67.4013V41.1251ZM43.75 51.6249C43.75 54.5244 46.1005 56.8749 49 56.8749C51.8995 56.8749 54.25 54.5244 54.25 51.6249C54.25 48.7254 51.8995 46.3749 49 46.3749C46.1005 46.3749 43.75 48.7254 43.75 51.6249Z" fill="#687787"/>
						</svg>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<div class="searchwp-live-search-result--info">
					<h4 class="searchwp-live-search-result--title">
						<?php
						$svg                = 'newspaper.svg';
						$fontawesome        = 'fa-newspaper';
						$screen_reader_text = 'News Article';
						if ( ! empty( $display_data['type'] ) ) {
							switch ( $display_data['type'] ) {
								case 'page':
									$svg                = 'chalkboard.svg';
									$fontawesome        = 'fa-file';
									$screen_reader_text = 'Page';
									break;
								case 'post_type_shows':
									$svg                = 'tv.svg';
									$fontawesome        = 'fa-tv';
									$screen_reader_text = 'TV Show, mini-series, or movie';
									break;
								case 'post_type_characters':
									$svg                = 'rubber-stamp.svg';
									$fontawesome        = 'fa-user';
									$screen_reader_text = 'Character';
									break;
								case 'post_type_actors':
									$svg                = 'award-academy.svg';
									$fontawesome        = 'fa-user-tie';
									$screen_reader_text = 'Actor';
									break;
							}
						}
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo lwtv_plugin()->get_symbolicon( svg: $svg, fontawesome: $fontawesome ) . '&nbsp;';
						?>
						<span class="screen-reader-text"><?php echo esc_html( $screen_reader_text ); ?></span>
						<a href="<?php echo esc_url( $display_data['permalink'] ); ?>">
							<?php echo wp_kses_post( $display_data['title'] ); ?>
						</a>
					</h4>
					<p class="searchwp-live-search-result--desc">
						<?php echo wp_kses_post( get_the_excerpt( $display_data['id'] ) ); ?>
					</p>
				</div>
			</div>
		<?php endforeach; ?>

		<?php
		$all_results_url = add_query_arg( 's', get_search_query(), home_url() );
		?>
		<div class="searchwp-live-search-view-all">
			<a href="<?php echo esc_url( $all_results_url ); ?>">
				<?php echo esc_html( '' ); ?>
			</a>
		</div>
	</div>
<?php else : ?>
	<p class="searchwp-live-search-no-results" role="option">
		<em><?php SearchWP_Live_Search_Template::render_no_results_message(); ?></em>
	</p>
<?php endif; ?>
