<?php
/**
 * The template for displaying Archive pages.
 *
 * @package YIKES Starter
 */

$icon            = '<div class="archive-header-icon">';
$archive_details = '<div class="archive-header-details">';

if ( is_author() ) {
	// Use the Gravatar.
	$icon .= get_avatar( get_the_author_meta( 'user_email' ) );

	// Get author's Socials
	$user_socials = array(
		'bluesky'   => get_the_author_meta( 'bluesky' ),
		'mastodon'  => get_the_author_meta( 'mastodon' ),
		'instagram' => get_the_author_meta( 'instagram' ),
		'tiktok'    => get_the_author_meta( 'tiktok' ),
		'tumblr'    => get_the_author_meta( 'tumblr' ),
		'twitter'   => get_the_author_meta( 'twitter' ),
		'website'   => get_the_author_meta( 'url' ),
	);

	// Get all the stupid social...
	$bluesky   = ( ! empty( $user_socials['bluesky'] ) ) ? '<a href="' . $user_socials['bluesky'] . '" target="_blank" rel="nofollow">' . ( new LWTV_Functions() )->symbolicons( 'bluesky.svg', 'fa-instagram' ) . '</a>' : false;
	$instagram = ( ! empty( $user_socials['instagram'] ) ) ? '<a href="https://instagram.com/' . $user_socials['instagram'] . '" target="_blank" rel="nofollow">' . ( new LWTV_Functions() )->symbolicons( 'instagram.svg', 'fa-instagram' ) . '</a>' : false;
	$twitter   = ( ! empty( $user_socials['twitter'] ) ) ? '<a href="https://twitter.com/' . $user_socials['twitter'] . '" target="_blank" rel="nofollow">' . ( new LWTV_Functions() )->symbolicons( 'twitter.svg', 'fa-twitter' ) . '</a>' : false;
	$tumblr    = ( ! empty( $user_socials['tumblr'] ) ) ? '<a href="' . $user_socials['tumblr'] . '" target="_blank" rel="nofollow">' . ( new LWTV_Functions() )->symbolicons( 'tumblr.svg', 'fa-tumblr' ) . '</a>' : false;
	$website   = ( ! empty( $user_socials['website'] ) ) ? '<a href="' . $user_socials['website'] . '" target="_blank" rel="nofollow">' . ( new LWTV_Functions() )->symbolicons( 'home.svg', 'fa-home' ) . '</a>' : false;
	$mastodon  = ( ! empty( $user_socials['mastodon'] ) ) ? '<a href="' . $user_socials['mastodon'] . '" target="_blank" rel="nofollow">' . ( new LWTV_Functions() )->symbolicons( 'mastodon.svg', 'fa-mastodon' ) . '</a>' : false;

	$social_array = array( $website, $twitter, $instagram, $tumblr, $bluesky, $mastodon );
	$social_array = array_filter( $social_array );

	// Get author Fav Shows.
	$all_fav_shows = get_the_author_meta( 'lez_user_favourite_shows' );
	if ( '' !== $all_fav_shows ) {
		$show_title = array();
		foreach ( $all_fav_shows as $each_show ) {
			if ( 'publish' !== get_post_status( $each_show ) ) {
				array_push( $show_title, '<em><span class="disabled-show-link">' . get_the_title( $each_show ) . '</span></em>' );
			} else {
				array_push( $show_title, '<em><a href="' . get_permalink( $each_show ) . '">' . get_the_title( $each_show ) . '</a></em>' );
			}
		}
		$favourites = ( empty( $show_title ) ) ? '' : implode( ', ', $show_title );
		$fav_title  = _n( 'Show', 'Shows', count( $show_title ) );
	}

	// Add Socials.
	$archive_details .= '<div class="author-socials">' . implode( ' ', $social_array ) . '</div>';

	// Add favourite shows if they're there.
	$archive_details .= ( isset( $favourites ) && ! empty( $favourites ) ) ? '<div class="author-favourites">' . lwtv_symbolicons( 'tv-hd.svg', 'fa-tv' ) . '&nbsp;Favorite ' . $fav_title . ': ' . $favourites . '</div>' : '';
}

$icon            .= '</div>';
$archive_details .= '</div>';

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<section class="archive-header">
				<div class="row">
					<div class="col-10">
						<?php the_archive_title( '<h1 class="entry-title">', '</h1>' ); ?>
					</div>
					<div class="col-2 icon plain"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				</div>
				<div class="row">
					<div class="col">
						<?php the_archive_description( '<div class="archive-description">', $archive_details . '</div>' ); ?>
					</div>
				</div>
			</section><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" tabindex="-1" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-9">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix">
						<?php
						if ( have_posts() ) :
							?>
						<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
							<div class="entry-content">
								<div class="row site-loop main-posts-loop">
									<?php
									while ( have_posts() ) :
										the_post();
										get_template_part( 'template-parts/content', 'posts' );
									endwhile;
									else :
										get_template_part( 'template-parts/content', 'none' );
									endif;
									?>
								</div>
								<?php wp_bootstrap_pagination(); ?>
							</div>
						</article><!-- #post-## -->
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-9 -->

			<div class="col-sm-3 site-sidebar site-loop">
				<?php get_sidebar(); ?>
			</div><!-- .col-sm-3 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php

get_footer();
