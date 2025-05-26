<?php
/**
 * The template for displaying search forms in the header.
 *
 * @package LWTV Underscores
 */
?>
<a class="nav-link" data-bs-toggle="collapse" role="button" data-bs-target="#collapseSearch" href="#collapseSearch" aria-expanded="false">
	<?php echo lwtv_plugin()->get_symbolicon( svg: 'search.svg', icon: 'svg-search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<span class="screen-reader-text">Search the Site</span>
</a>

<div class="collapse fixed-top header-search-bar" id="collapseSearch">
	<div class="container">
		<div class="row">
			<div class="col">
				<div class="search-body">
					<div class="search-box">
						<form role="search" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
							<div class="form-group row">
								<label class="col-sm-3 col-form-label" for="search">Search the Site</label>
								<div class="col-sm-7 searchbox-input">
									<input type="text" name="s" id="header-search" class="form-control" placeholder="<?php echo esc_attr_x( 'Enter keywords &hellip;', 'placeholder', 'lwtv-underscores' ); ?>" value="<?php the_search_query(); ?>" title="<?php echo esc_attr_x( 'Search for:', 'label', 'lwtv-underscores' ); ?>" data-swplive="true" />
								</div>
								<div class="col-sm-1 searchbox-button">
									<button class="btn btn-primary" type="submit">Search</button>
								</div>
								<div class="col-sm-1 searchbox-close">
									<span class="close-btn-container">
										<a href="#collapseSearch" data-bs-target="#collapseSearch" data-bs-toggle="collapse">
											<?php echo lwtv_plugin()->get_symbolicon( svg: 'cancel-circle.svg', icon: 'svg-times-circle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										</a>
									</span>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>


