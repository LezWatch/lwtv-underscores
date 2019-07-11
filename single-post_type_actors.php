<?php
/**
 * The Template for displaying all single actor pages.
 *
 * @package YIKES Starter
 */

// Build the icon
$icon = '<div class="show-header-svg">';
if ( lwtv_yikes_is_queer( $post->ID ) ) {
	$icon .= ' <span role="img" aria-label="Queer IRL Actor" data-toggle="tooltip" title="Queer IRL Actor" class="cliche-queer-irl">' . lwtv_symbolicons( 'rainbow.svg', 'fa-cloud' ) . '</span>';
}
if ( lwtv_yikes_is_birthday( $post->ID ) ) {
	$icon .= ' <span role="img" aria-label="Actor Having a Birthday" data-toggle="tooltip" title="Happy Birthday" class="happy-birthday">' . lwtv_symbolicons( 'cake.svg', 'fa-birthday-cake' ) . '</span>';
}
$icon .= '</div>';

// Privacy
$privacy = ( 'private' === get_post_status( $post->ID ) ) ? '<p><strong>Note:</strong> <em>This post is private and not visible to non-admins. <strong>Do not</strong> make this public without confirming in #staff first.</em></p>' : '';

get_header(); ?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<div class="row">
					<div class="col-10"><?php the_title( '<h1 class="entry-title">', '</h1>' ); ?><?php echo wp_kses_post( $privacy ); ?></div>
					<div class="col-2 icon plain"><?php echo $icon; // WPSC: XSS okay ?></div>
				</div>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-8">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">
						<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
							<div class="entry-content actor-page">

								<?php
								if ( lwtv_yikes_is_birthday( $post->ID ) ) {
									$old = ' ';
									$end = array( 'th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th' );
									if ( ! get_post_meta( $post->ID, 'lezactors_death', true ) ) {
										$age = lwtv_yikes_actordata( get_the_ID(), 'age', true );
										$num = $age->format( '%y' );
										if ( ( $num % 100 ) >= 11 && ( $num % 100 ) <= 13 ) {
											$years_old = $num . 'th';
										} else {
											$years_old = $num . $end[ $num % 10 ];
										}
										$old = ' ' . $years_old . ' ';
									}
									echo '<div class="alert alert-info" role="alert">Happy' . esc_html( $old ) . 'Birthday, ' . esc_html( get_the_title() ) . '!</div>';
								}
								?>
								<div class="card">
									<?php
									while ( have_posts() ) :
										the_post();
										get_template_part( 'template-parts/content', get_post_type() );
										// Force Jetpack to display sharing links where we want them.
										lwtv_yikes_jetpack_post_meta();
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
