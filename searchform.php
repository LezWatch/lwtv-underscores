<?php
/**
 * Template for displaying search forms on LezWatch TV
 * Filter the search form to search for characters and shows
 *
 * @package WordPress
 * @subpackage lwtv_underscores
 */

$checked_shows = '';
$checked_characters = '';
$query_types = get_query_var('post_type');

if ( is_null($query_types) || empty($query_types) ) {
	$query_types = array( 'post_type_characters', 'post_type_shows' );
}
if ( !is_array( $query_types ) ) $query_types = array( $query_types );
if ( in_array( 'post_type_characters' , $query_types)) $checked_characters = 'checked="checked"';
if ( in_array( 'post_type_shows' , $query_types)) $checked_shows = 'checked="checked"';

?>
	<form method="get" id="searchform" action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<label for="s" class="assistive-text">Search the Database</label>
		<input type="text" class="field" name="s" id="s" placeholder="Search the Database" />
		<input type="submit" class="submit" name="submit" id="searchsubmit" value="Search" />
		<input type="checkbox" name="post_type[]" value="post_type_characters" <?php echo $checked_characters; ?> /> <label class="search-input-label">Characters</label>
		<input type="checkbox" name="post_type[]" value="post_type_shows" <?php echo $checked_shows; ?> /> <label class="search-input-label">Shows</label>
	</form>