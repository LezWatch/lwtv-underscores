<?php
/**
 * Template Name: Home page
 *
 * @package YIKES Starter
 */
get_header(); ?>

<div id="main" class="site-main" role="main">

	<!-- Home page top section -->
	<section class="home-featured-posts">
		<div class="container">
    		<div class="row">

				<!-- Newest posts -->
      			<div class="col-sm-8 site-loop home-featured-post-loop"> 
        			<h2 class="posts-title">New Posts <i class="fa fa-newspaper-o" aria-hidden="true"></i></h2>

					<?php $lastpostloop = new WP_Query( array(
						'posts_per_page' => '1', 
						'orderby' => 'date', 
						'order' => 'DESC'
					) ); ?>
					 
					<!-- // The Loop -->
					<?php while ($lastpostloop->have_posts()) : $lastpostloop->the_post(); ?>

						<div class="card">
							<?php if ( has_post_thumbnail()) : ?>
							   <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>" >
							  	 <?php the_post_thumbnail( 'large', array( 'class' => 'card-img-top' ) );; ?>
							   </a>
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

					<div class="site-loop home-featured-secondary-loop">

						<?php $newpostsloop = new WP_Query( array(
							'posts_per_page' => '5',
							'offset' => '1',
							'orderby' => 'date', 
							'order' => 'DESC'
						) ); ?>
						 
						<!-- // The Loop -->
						<?php while ($newpostsloop->have_posts()) : $newpostsloop->the_post(); ?>

							<div class="card-group">
								<div class="card col-sm-5"	
									<?php if ( has_post_thumbnail() ) : ?>
									    style="background-image: url(<?php the_post_thumbnail_url( 'large' ); ?>);"
									<?php endif; ?>
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
				<div class="col-sm-4">
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
        			<h2>Shows We Love <i class="fa fa-heart" aria-hidden="true"></i></h2>
      			</div>
    		</div>
    		<div class="row">
      			<div class="col">
        			<div class="card-deck">
          				<div class="card">
          					<img class="card-img-top" src="<?php echo get_template_directory_uri(); ?>/images/sense8-pic.jpg">
            				<div class="card-body">
              					<h4 class="card-title">Sense8</h4>
								<div class="card-meta">
									<strong>Airs on:</strong> Netflix<br />
									<strong>Airdates:</strong> 2015 - Current
								</div>
              					<div class="card-text">
                					A wonderful science fiction drama by siblings Lilly & Lana Wachowski and J. Michael Straczynski revolving around eight strangers from different parts of the world who suddenly become mentally and emotionally linked.
              					</div>
            				</div>
            				<div class="card-footer">
            					<a href="#" class="btn btn-outline-primary">Go to Show Profile</a>
            				</div>
          				</div>

						<div class="card"> 
							<img class="card-img-top" src="<?php echo get_template_directory_uri(); ?>/images/odat.jpg">
							<div class="card-body">
								<h4 class="card-title">One Day at Time</h4>
								<div class="card-meta">
									<strong>Airs on:</strong> Netflix<br />
									<strong>Airdates:</strong> 2017 - current
								</div>
								<div class="card-text">
									This is it. This is it. This is life, the one you get. So go and have a ball.
								</div>
							</div>
							<div class="card-footer">
								<a href="#" class="btn btn-outline-primary">Go to Show Profile</a>
							</div>
						</div>

	          			<div class="card"> 
	          				<img class="card-img-top" src="<?php echo get_template_directory_uri(); ?>/images/wayhaught.jpg">
	            			<div class="card-body">
	              				<h4 class="card-title">Wynonna Earp</h4>
	              				<div class="card-meta">
					                <strong>Airs on:</strong> Syfy<br />
					                <strong>Airdates:</strong> 2016 - current
	              				</div>
	              				<div class="card-text">
	              					When a badass troublemaker turns twenty-seven, she becomes the hero we all need.
	              				</div>
	            			</div>
	            			<div class="card-footer">
	            				<a href="#" class="btn btn-outline-primary">Go to Show Profile</a>
	          				</div>
	        			</div>

	        		</div><!-- .card-deck -->
      			</div><!-- .col -->
    		</div><!-- .row -->
		</div><!-- .container -->
	</section>

	<!-- Older Posts -->
	<section class="home-older-posts">
	  	<div class="container">
	    	<div class="row">
	      		<div class="col">
	        		<h2 class="posts-title">More Posts <i class="fa fa-newspaper-o" aria-hidden="true"></i></h2>
	        	</div>
	        </div>
	        <div class="row site-loop home-featured-post-loop equal-height">
				<?php $oldpostsloop = new WP_Query( array(
					'posts_per_page' => '6',
					'offset' => '6',
					'orderby' => 'date', 
					'order' => 'DESC'
				) ); ?>
				 
				<!-- // The Loop -->
				<?php while ($oldpostsloop->have_posts()) : $oldpostsloop->the_post(); ?>
    				
    				<div class="col-sm-4">
						<div class="card"> 
							<?php if ( has_post_thumbnail()) : ?>
							   <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>" >
							  	 <?php the_post_thumbnail( 'large', array( 'class' => 'card-img-top' ) );; ?>
							   </a>
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
						  	</div>
					  		<div class="card-footer">
								<a href="<?php the_permalink(); ?>" class="btn btn-sm btn-outline-primary">
									Read More <span class="screen-reader-text">about <?php the_title(); ?></span>
								</a>
							</div>
						</div><!-- .card -->
					</div><!-- .col-sm-4 -->

				<?php endwhile;  ?>

				<?php wp_reset_postdata(); ?>					
	     	
     		</div><!-- .row .home-featured-post-loop -->
	    </div><!-- .container -->
	</section>

</div><!-- #main -->

<?php get_footer(); ?>
