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
      			<div class="col-sm-8 site-loop"> 
        			<h2 class="posts-title">New Posts <i class="fa fa-newspaper-o" aria-hidden="true"></i></h4>        			

					<div class="card">
						<img class="card-img-top" src="http://placekitten.com.s3.amazonaws.com/homepage-samples/408/287.jpg" alt="">
						<div class="card-body">
					  		<h3 class="card-title">Fall 2017 Preview – Cable TV</h3>
								<div class="card-meta text-muted">
									October 27, 2017 
									<i class="fa fa-user-circle-o" aria-hidden="true"></i> <a href="#">Mika (ipstenu) Epstein</a>
								</div>
						  		<div class="card-text">
						  			<p>
						  				Following our surprisingly disappointing reveal on how there’s only one new Network TV show with a new queer female, it’s time to look at cable.
						  			</p>
						  		</div>
						  	</div>
						</div>
						<div class="card-footer">
							<a href="#" class="btn btn-outline-primary">
								Read More <span class="screen-reader-text">about [title]</span>
							</a>
						</div>
					</div>

					<div class="card-group">
						<div class="card">
							<img class="card-img" src="https://cldup.com/lQHR05Qone.jpg" alt="">
							<div class="card-img-overlay"></div>
						</div>
						<div class="card">
							<div class="card-body">
						  		<h3 class="card-title">Yet Another Day At A Time</h3>
								<div class="card-meta text-muted">
									October 26, 2017 
									<i class="fa fa-user-circle-o" aria-hidden="true"></i> <a href="#">Mika (ipstenu) Epstein</a>
								</div>
						  		<div class="card-text">
						  			<p>
						  				We got to go to a third taping of One Day at a Time and it was just as much fun as the first two.
						  			</p>
						  		</div>
						  	</div>
					  		<div class="card-footer">
								<a href="#" class="btn btn-outline-primary">
									Read More <span class="screen-reader-text">about [title]</span>
								</a>
							</div>
						</div><!-- .card -->
					</div><!-- .card-group -->
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
          					<img class="card-img-top" src="img/sense8-pic.jpg" alt="Card image cap">
            				<div class="card-body">
              					<h4 class="card-title">Sense8</h4>
								<div class="card-text">
									<strong>Airs on:</strong> Netflix<br />
									<strong>Airdates:</strong> 2015 - Current
								</div>
              					<p class="card-text show-excerpt">
                					A wonderful science fiction drama by siblings Lilly & Lana Wachowski and J. Michael Straczynski revolving around eight strangers from different parts of the world who suddenly become mentally and emotionally linked.
              					</p>
            				</div>
            				<div class="card-footer-light">
            					<a href="#" class="btn btn btn-outline-theme">Go to Show Profile</a>
            				</div>
          				</div>

						<div class="card"> 
							<img class="card-img-top img-fluid" src="img/odat.jpg" alt="Card image cap">
							<div class="card-body">
								<h4 class="card-title">One Day at Time</h4>
								<div class="card-text">
									<strong>Airs on:</strong> Netflix<br />
									<strong>Airdates:</strong> 2017 - current
								</div>
								<p class="card-text show-excerpt">
									This is it. This is it. This is life, the one you get. So go and have a ball.
								</p>
							</div>
							<div class="card-footer-light">
								<a href="#" class="btn btn btn-outline-theme">Go to Show Profile</a>
							</div>
						</div>

	          			<div class="card"> 
	          				<img class="card-img-top img-fluid" src="img/wayhaught.jpg" alt="Card image cap">
	            			<div class="card-body">
	              				<h4 class="card-title">Wynonna Earp</h4>
	              				<div class="card-text">
					                <strong>Airs on:</strong> Syfy<br />
					                <strong>Airdates:</strong> 2016 - current
	              				</div>
	              				<p class="card-text show-excerpt">
	              					When a badass troublemaker turns twenty-seven, she becomes the hero we all need.
	              				</p>
	            			</div>
	            			<div class="card-footer-light">
	            				<a href="#" class="btn btn btn-outline-theme">Go to Show Profile</a></div>
	          				</div>
	        			</div>

	        		</div><!-- .card-deck -->
      			</div><!-- .col -->
    		</div><!-- .row -->
		</div><!-- .container -->
	</section>

</div><!-- #main -->

<?php get_footer(); ?>
