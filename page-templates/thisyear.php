<?php
/**
* Template Name: This Year
* Description: Used to show the yearly data of lezbians and what not.
*/

$thisyear = (int) (isset( $wp_query->query['thisyear'] ) )? $wp_query->query['thisyear'] : date('Y');

if ( !is_numeric( $thisyear ) ){
	wp_redirect( get_site_url().'/this-year/' , '301' );
	exit;
}

$iconpath    = '<span role="img" aria-label="post_type_characters" title="Characters" data-toggle="tooltip" class="taxonomy-svg characters">' . lwtv_yikes_symbolicons( 'calendar_alt.svg', 'fa-calendar' ) . '</span>';

get_header(); 
?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<div class="archive-description">
					<h1 class="entry-title">
						In This Year - <?php echo $thisyear; ?>
						<?php echo ( isset( $iconpath ) ? $iconpath : '' ); ?>
					</h1>
					<p>An overview of queer events that occurred in <?php echo $thisyear; ?></p>
				</div>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-12">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">

						<section id="toc" class="toc-container card-body">
							<nav class="breadcrumb">
								<h4 class="toc-title">Go to:</h4>
								<a class="breadcrumb-item smoothscroll" href="#died">Characters Who Died</a>
								<a class="breadcrumb-item smoothscroll" href="#showsonair">Shows On The Air</a>
								<a class="breadcrumb-item smoothscroll" href="#showsstart">Shows That Began</a>
								<a class="breadcrumb-item smoothscroll" href="#showsend">Shows That Ended</a>
							</nav>
						</section>

						<div class="container thisyear-container">
							<div class="row">
								<div class="col">
									<?php lwtv_yikes_this_year_dead( $thisyear ); ?>
								</div>
							</div>
							<div class="row">
								<div class="col">
									<h2>This Years Shows</h2>
									<?php lwtv_yikes_this_year_shows( $thisyear );  ?>
								</div>
							</div>
						</div>

						<?php
							lwtv_yikes_this_year_navigation( $thisyear ); 
						?>
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-12 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php get_footer();