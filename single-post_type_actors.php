<?php
/**
 * The Template for displaying all single actor pages.
 *
 * @package YIKES Starter
 */

// Build the icon.
$dead = get_post_meta( $post->ID, 'lezactors_death', true );
$icon = '<div class="show-header-svg">';
if ( lwtv_plugin()->is_actor_queer( $post->ID ) ) {
	$icon .= ' <span role="img" aria-label="Queer IRL Actor" data-bs-target="tooltip" title="Queer IRL Actor" class="cliche-queer-irl">' . lwtv_plugin()->get_symbolicon( svg: 'rainbow.svg', icon: 'svg-cloud', max_size: '50' ) . '</span>';
}
if ( lwtv_plugin()->is_actor_birthday( $post->ID ) && ! $dead ) {
	$icon .= ' <span role="img" aria-label="Actor Having a Birthday" data-bs-target="tooltip" title="Happy Birthday" class="happy-birthday">' . lwtv_plugin()->get_symbolicon( svg: 'cake.svg', icon: 'svg-birthday-cake', max_size: '50' ) . '</span>';
}
if ( $dead ) {
	$icon .= ' <span role="img" aria-label="RIP - Dead Actor" data-bs-target="tooltip" title="RIP - Dead Actor" class="cliche-dead">' . lwtv_plugin()->get_symbolicon( svg: 'rest-in-peace.svg', icon: 'svg-ban', max_size: '50' ) . '</span>';
}

$icon .= '</div>';

// Privacy.
$privacy = ( 'private' === get_post_status( $post->ID ) ) ? '<p><strong>Note:</strong> <em>This post is private and not visible to non-admins. <strong>Do not</strong> make this public without confirming in <code>#editors</code> Slack first.</em></p>' : '';

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<div class="row">
					<div class="col-10"><?php the_title( '<h1 class="entry-title">', '</h1>' ); ?><?php echo wp_kses_post( $privacy ); ?></div>
					<div class="col-2 icon plain"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				</div>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" tabindex="-1" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-8">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">
						<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
							<div class="entry-content actor-page">
								<?php
								// If it's their birthday and they're not dead, we wish them a happy!
								if ( lwtv_plugin()->is_actor_birthday( $post->ID ) && ! $dead ) {
									lwtv_plugin()->get_actor_birthday( $post->ID );
								}
								?>
								<div class="card">
									<?php
									while ( have_posts() ) :
										the_post();
										get_template_part( 'template-parts/content/' . get_post_type() );
										// Display sharing links where we want them.
										lwtv_plugin()->post_meta_sharing( get_the_ID() );
										// Echo last updated.
										lwtv_plugin()->get_last_updated( get_the_ID() );
									endwhile; // end of the loop.
									?>
								</div>
							</div><!-- .entry-content -->
						</article><!-- #post-## -->
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-8 -->

			<div class="col-sm-4 site-sidebar site-loop showschar-section">
				<?php get_sidebar(); ?>
			</div><!-- .col-sm-4 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php

get_footer();
