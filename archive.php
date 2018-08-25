<?php
/**
 * The template for displaying Archive pages.
 *
 * @package YIKES Starter
 */

$icon            = '<div class="archive-header-icon">';
$archive_details = '<div class="archive-header-details">';

if ( is_author() ) {
	// Use the Gravatar
	$icon .= get_avatar( get_the_author_meta( 'user_email' ) );

	// Get author's website URL
	$user_twitter = get_the_author_meta( 'twitter' );

	// Get author Fav Shows
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

	// Add Twitter if it's there
	$archive_details .= ( ! empty( $user_twitter ) ) ? '<div class="author-twitter">' . lwtv_yikes_symbolicons( 'twitter.svg', 'fa-twitter' ) . '&nbsp;<a href="https://twitter.com/' . $user_twitter . '" target="_blank" rel="nofollow">@' . $user_twitter . '</a> </div>' : '';

	// Add favourite shows if they're there
	$archive_details .= ( isset( $favourites ) && ! empty( $favourites ) ) ? '<div class="author-favourites">' . lwtv_yikes_symbolicons( 'tv-hd.svg', 'fa-tv' ) . '&nbsp;Favorite ' . $fav_title . ': ' . $favourites . '</div>' : '';
}

$icon            .= '</div>';
$archive_details .= '</div>';

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<div class="row">
					<div class="col-10"><?php the_archive_title( '<h1 class="entry-title">', '</h1>' ); ?></div>
					<div class="col-2 icon plain"><?php echo lwtv_sanitized( $icon ); ?></div>
				</div>
				<div class="row">
					<div class="archive-description"><?php the_archive_description( '<div class="archive-description">', $archive_details . '</div>' ); ?></div>
				</div>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-9">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">
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
									wp_bootstrap_pagination();
									else :
										get_template_part( 'template-parts/content', 'none' );
									endif;
									?>
								</div>
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
