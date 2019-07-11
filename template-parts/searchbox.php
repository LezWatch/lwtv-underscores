<?php
/**
 * The template for displaying search forms in YIKES Starter
 *
 * @package YIKES Starter
 */
?>

<form role="search" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
	<div class="form-group row">
		<label class="col-sm-3 col-form-label" for="search">Search the Site</label>
		<div class="col-sm-7 searchbox-input">
			<input type="text" name="s" id="search" class="form-control" placeholder="<?php echo esc_attr_x( 'Enter keywords &hellip;', 'placeholder', 'yikes_starter' ); ?>" value="<?php the_search_query(); ?>" title="<?php echo esc_attr_x( 'Search for:', 'label', 'yikes_starter' ); ?>" />
		</div>

		<div class="col-sm-1 searchbox-button">
			<button class="btn btn-primary" type="submit">Search</button>
		</div>
		<div class="col-sm-1 searchbox-close">
			<span class="close-btn-container">
				<a href="#collapseSearch" data-toggle="collapse">
					<?php echo lwtv_symbolicons( 'cancel-circle.svg', 'fa-times-circle' ); ?>
				</a>
			</span>
		</div>
	</div>
</form>
