<?php
/**
 * Template Name: This Year
 * Description: Used to show the yearly data of lesbians and what not.
 *
 * @package LezWatch.TV
 */

$thisyear = (int) ( isset( $wp_query->query['thisyear'] ) && is_numeric( $wp_query->query['thisyear'] ) && 4 === strlen( $wp_query->query['thisyear'] ) ) ? $wp_query->query['thisyear'] : gmdate( 'Y' );

if ( ! is_numeric( $thisyear ) || $thisyear < FIRST_LWTV_YEAR ) {
	wp_safe_redirect( '/this-year/', '301' );
	exit;
}

$iconpath = '<span role="img" aria-label="post_type_characters" title="Calendar" class="taxonomy-svg calendar">' . lwtv_symbolicons( 'calendar-15.svg', 'fa-calendar-alt' ) . '</span>';

get_header();
?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<div class="row">
					<div class="col-10">
						<h1 class="entry-title">
							In This Year - <?php echo (int) $thisyear; ?>
						</h1>
					</div>
					<div class="col-2 icon plain">
					<?php
						echo ( isset( $iconpath ) ? $iconpath : '' );  // phpcs:ignore WordPress.Security.EscapeOutput
					?>
					</div>
				</div>
				<div class="row">
					<div class="col">
						<div class="archive-description">
							<p>An overview of queer events that occurred in <?php echo (int) $thisyear; ?>.</p>
						</div>
					</div>
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
						<?php
						if ( method_exists( 'LWTV_This_Year', 'display' ) ) {
							// phpcs:ignore WordPress.Security.EscapeOutput
							echo LWTV_This_Year::display( $thisyear );
						}
						?>
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-12 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php

get_footer();
