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
		<form role="search" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
			<div class="input-group input-group-sm">
				<input type="text" name="s" id="search" class="form-control" aria-label="Search for..." value="<?php the_search_query(); ?>" title="<?php _ex( 'Search for:', 'label', 'yikes_starter' ); ?>" >
				<!-- Search everything by default. This changes based on javascript selections. -->
				<input type="hidden" id="searchCPTInput" name="post_type[]" value="any" />

				<span class="input-group-btn">
					<button class="btn btn-primary" type="submit">Go</button>
				</span>
			</div>
			<div class="form-check form-check-inline">
				<label class="form-check-label">
					<input class="form-check-input" type="checkbox"  onclick="searchCPT( 'post_type_shows' )" id="CheckboxShows" value="Shows"> Shows
				</label>
			</div>
			<div class="form-check form-check-inline">
				<label class="form-check-label">
					<input class="form-check-input" type="checkbox" onclick="searchCPT( 'post_type_characters' )" id="CheckboxCharacters" value="Characters"> Characters
				</label>
			</div>
		</form>
	</div><!-- .card-body -->
</div><!-- .card -->

<script>
	function searchCPT( custom_post_type, display_name ) {
		if ( custom_post_type === undefined ) { custom_post_type = 'any'; }
	    document.getElementById( "searchCPTInput").value = custom_post_type;
	}
</script>