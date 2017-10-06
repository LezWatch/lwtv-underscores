<?php
/**
 * Template Name: Home page
 *
 * @package YIKES Starter
 */
get_header(); ?>

<?php 
	$paged = get_query_var( 'page' ) ? get_query_var( 'page' ) : 1; 
	$already_displayed_posts = array();
?>

<div id="main" class="site-main" role="main">

	<?php if ( $paged == 1 ) { ?>

	<!-- Home page top section -->
	<section class="home-featured-posts">
		<div class="container">
			<div class="row">
				<!-- Newest posts -->
				<div class="col-sm-8"> 

					<div class="site-loop home-featured-post-loop">
						<h2 class="posts-title">New Posts <?php echo lwtv_yikes_symbolicons( 'newspaper.svg', 'fa-newspaper-o' ); ?></h2>

						<?php 
						$lastpostloop = new WP_Query( array(
							'posts_per_page' => '1', 
							'orderby' => 'date', 
							'order' => 'DESC'
						) ); 
						?>

						<!-- // The Loop -->
						<?php while ($lastpostloop->have_posts()) : $lastpostloop->the_post(); $already_displayed_posts[]=get_the_ID(); ?>

							<div class="card">
								<?php if ( has_post_thumbnail()) : ?>
									<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>" ><?php the_post_thumbnail( 'large', array( 'class' => 'card-img-top' ) ); ?></a>
								<?php endif; ?>
								<div class="card-body">
									<h3 class="card-title"><?php the_title(); ?></h3>
									<div class="card-meta text-muted">
										<?php the_date(); ?>
										<i class="fa fa-user-circle-o" aria-hidden="true"></i> <?php the_author(); ?>
									</div>
									<div class="card-text">
										<?php the_excerpt(); ?>
									</div>
								</div><!-- .card-body -->
								<div class="card-footer">
									<a href="<?php the_permalink(); ?>" class="btn btn-outline-primary">
										Read More <span class="screen-reader-text">about <?php the_title(); ?></span>
									</a>
								</div><!-- .card-footer -->
							</div><!-- .card -->

						<?php endwhile;  ?>	

						<?php wp_reset_postdata(); ?>
					</div>

					<div class="site-loop home-featured-secondary-loop">

						<?php 
						$newpostsloop = new WP_Query( array(
							'posts_per_page' => '5',
							'offset'         => '1',
							'orderby'        => 'date', 
							'order'          => 'DESC'
						) ); 
						?>
						 
						<!-- // The Loop -->
						<?php while ($newpostsloop->have_posts()) : $newpostsloop->the_post(); $already_displayed_posts[]=get_the_ID(); ?>

							<div class="card-group">
								<div class="card col-sm-5"	
									<?php 
									if ( has_post_thumbnail() ) { ?>
										style="background-image: url(<?php the_post_thumbnail_url( 'large' ); ?>);"
									<?php } ?>
								>
								</div>
								<div class="card col-sm-7">
									<div class="card-body">
										<h3 class="card-title"><?php the_title(); ?></h3>
										<div class="card-meta text-muted">
											<?php the_date(); ?>
											<i class="fa fa-user-circle-o" aria-hidden="true"></i> <?php the_author(); ?>
										</div>
										<div class="card-text">
											<?php the_excerpt(); ?>
										</div>
									</div>
									<div class="card-footer">
										<a href="<?php the_permalink(); ?>" class="btn btn-sm btn-outline-primary">
											Read More <span class="screen-reader-text">about <?php the_title(); ?></span>
										</a>
									</div>
								</div><!-- .card -->
							</div><!-- .card-group -->

						<?php endwhile;  ?>

						<?php wp_reset_postdata(); ?>

					</div><!-- .home-featured-secondary-loop -->

				</div><!-- .col-sm-8 -->

				<!-- Home Page Sidebar -->
				<div class="col-sm-4 site-sidebar site-loop">
					<?php dynamic_sidebar( 'sidebar-1' ); ?>
				</div>
			</div><!-- .row -->
		</div><!-- .container -->
	</section>

	<!-- Shows We Love -->
	<section class="home-featured-shows">
		<div class="container site-loop">
			<div class="row">
				<div class="col">
					<h2>Shows We Love <?php echo lwtv_yikes_symbolicons( 'heart.svg', 'fa-heart' ); ?></h2>
				</div>
			</div>
			<div class="row">
				<div class="col">
					<div class="card-deck">
						<?php
							
						// Collect 30 loved posts (max) and then pick 3
						$lovedpostloop = new WP_Query( array(
							'post_type'         => 'post_type_shows',
							'posts_per_page'    => '30',
							'post_status'       => array( 'publish' ),
							'no_found_rows'     => true,
							'_loved_shuffle'    => 3,
							'meta_query'        => array( array(
								'key'     => 'lezshows_worthit_show_we_love',
								'value'   => 'on',
								'compare' => '=',
							),),
						) ); 
						
						while ( $lovedpostloop->have_posts() ) : $lovedpostloop->the_post();
						?>
							<div class="card">
								<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>" ><?php the_post_thumbnail( 'postloop-img', array( 'class' => 'card-img-top' ) ); ?></a>
								<div class="card-body">
									<h4 class="card-title"><?php the_title(); ?></h4>
									<div class="card-meta">
										<?php 
											$stations = get_the_terms( get_the_ID(), 'lez_stations' );
											if ( $stations && ! is_wp_error( $stations ) ) {
												echo get_the_term_list( get_the_ID(), 'lez_stations', '<strong>Network:</strong> ', ', ' ) .'<br />';
											}
											$airdates = get_post_meta( get_the_ID(), 'lezshows_airdates', true );
											if ( $airdates ) {
												echo '<strong>Airdates:</strong> '. $airdates['start'] .' - '. $airdates['finish'] .'<br />';
											}
										?>
									</div>
									<div class="card-text">
										<?php the_excerpt(); ?>
									</div>
								</div>
								<div class="card-footer">
									<a href="<?php the_permalink(); ?>" class="btn btn-sm btn-outline-primary">
										Go to Show Profile
									</a>
								</div>
							</div>

						<?php
						endwhile;
						wp_reset_postdata();
						?>
					<!-- End Loop -->

					</div><!-- .card-deck -->
				</div><!-- .col -->
			</div><!-- .row -->
		</div><!-- .container -->
	</section>

	<?php } ?>

	<!-- Older Posts -->
	<section class="home-older-posts">
		<div class="container">
			<div class="row">
				<div class="col">
					<h2 class="posts-title">More Posts <?php echo lwtv_yikes_symbolicons( 'newspaper.svg', 'fa-newspaper-o' ); ?></h2>
				</div>
			</div>
			<div class="row site-loop main-posts-loop equal-height">

				<?php
					
				$old_posts_per_page = ( $paged == 1 )? '6' : '12';
					
				$oldpostsloop = new WP_Query( array(
					'posts_per_page' => $old_posts_per_page,
					'paged'          => $paged,
					'post__not_in'   => $already_displayed_posts,
					'orderby'        => 'date', 
					'order'          => 'DESC'
				) ); 
				?>

				<!-- // The Loop -->
				<?php
				while ($oldpostsloop->have_posts()) : $oldpostsloop->the_post(); ?>

					<div class="col-sm-4">
						<?php get_template_part( 'template-parts/content', 'posts' ); ?>
					</div>
				<?php 
				endwhile;

				wp_reset_postdata();
				yikes_generate_pagination_buttons( $paged, $oldpostsloop->max_num_pages ); 
				?>

			</div><!-- .row .home-featured-post-loop -->
		</div><!-- .container -->
	</section>

</div><!-- #main -->

<?php get_footer();