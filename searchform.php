<?php
/**
 * The template for displaying search forms in YIKES Starter
 *
 * @package YIKES Starter
 */
?>

<div class="card card-search">
	<div class="card-header">
		<h4><?php echo lwtv_symbolicons( 'search.svg', 'fa-search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> Search the Database</h4>
	</div>
	<div class="card-body">
		<form role="search" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
			<div class="input-group input-group-sm">
				<input type="text" name="s" id="search" class="form-control" aria-label="Search for..." value="<?php the_search_query(); ?>" title="<?php echo esc_html_x( 'Search for:', 'label', 'lwtv-underscores' ); ?>" >
				<?php
				if ( ! class_exists( 'Jetpack_Search' ) ) {
					?>
					<span class="input-group-btn">
						<button class="btn btn-primary btn-sm" type="submit">Go</button>
					</span>
					<?php
				}
				?>
			</div>
		</form>
	</div><!-- .card-body -->
</div><!-- .card -->
