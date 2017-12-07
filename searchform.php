<?php
/**
 * The template for displaying search forms in YIKES Starter
 *
 * @package YIKES Starter
 */

// Pre flight magic to determine what search boxes were checked
$checked_shows = $checked_characters = $checked_actors = '';
$queery_types = get_query_var('post_type');

if ( is_null($queery_types) || empty($queery_types) ) {
	$queery_types = array( 'post_type_characters', 'post_type_shows' );
}
if ( !is_array( $queery_types ) ) { $queery_types = array( $queery_types ); }
if ( in_array( 'post_type_characters' , $queery_types) ) { $checked_characters = 'checked="checked"'; }
if ( in_array( 'post_type_shows' , $queery_types) ) { $checked_shows = 'checked="checked"'; }
if ( in_array( 'post_type_actors' , $queery_types) ) { $checked_actors = 'checked="checked"'; }
?>

<div class="card card-search">
	<div class="card-header">
		<h4><?php echo lwtv_yikes_symbolicons( 'search.svg', 'fa-search-o' ); ?> Search the Database</h4>
	</div>
	<div class="card-body">
		<form role="search" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
			<div class="input-group input-group-sm">
				<input type="text" name="s" id="search" class="form-control" aria-label="Search for..." value="<?php the_search_query(); ?>" title="<?php _ex( 'Search for:', 'label', 'yikes_starter' ); ?>" >
				<span class="input-group-btn">
					<button class="btn btn-primary" type="submit">Go</button>
				</span>
			</div>
			<div class="form-check form-check-inline ml-2">
				<label class="form-check-label">
					<input class="form-check-input" type="checkbox" name="post_type[]" value="post_type_shows" id="CheckboxShows" value="Shows" <?php echo $checked_shows; ?>> Shows
				</label>
			</div>
			<div class="form-check form-check-inline">
				<label class="form-check-label">
					<input class="form-check-input" type="checkbox" name="post_type[]" value="post_type_characters" id="CheckboxCharacters" value="Characters" <?php echo $checked_characters; ?>> Characters
				</label>
			</div>
			<div class="form-check form-check-inline">
				<label class="form-check-label">
					<input class="form-check-input" type="checkbox" name="post_type[]" value="post_type_actors" id="CheckboxActors" value="Actors" <?php echo $checked_actors; ?>> Actors
				</label>
			</div>
		</form>
	</div><!-- .card-body -->
</div><!-- .card -->