<?php
/**
 * The template for displaying search forms in YIKES Starter
 *
 * @package YIKES Starter
 */
?>
     
<div class="card card-search">
	<div class="card-header">
		<h4><i class="fa fa-search float-right" aria-hidden="true"></i> Search the Database</h4>
	</div>
	<div class="card-body">
		<form role="search" class="form-inline search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
			<div class="input-group input-group-sm">
				<div class="input-group-btn">
					<button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						Search
					</button>
					<div class="dropdown-menu">
						<a class="dropdown-item" href="#">Shows</a>
						<a class="dropdown-item" href="#">Characters</a>
						<a class="dropdown-item" href="#">Both</a>
					</div>
				</div>
				<input type="text" name="s" id="search" class="form-control" aria-label="Search for..." value="<?php the_search_query(); ?>" title="<?php _ex( 'Search for:', 'label', 'yikes_starter' ); ?>" >
				<span class="input-group-btn">
					<button class="btn btn-primary" type="submit">Go</button>
				</span>
			</div>
		</form>
	</div>
</div>