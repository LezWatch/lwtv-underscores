<?php
/**
 * LezWatch Search Form
 *
 * Filter the search form to search for characters and shows
 *
 * @license GPL-2.0+
 */

// if this file is called directly abort
if ( ! defined('WPINC' ) ) {
	die;
}

/*
 * Filter Search Text
 *
 * Change the search text to what we want
 * 
 * @param string $text The text that comes in by default
 * @return Customized Text
 */
add_filter( 'genesis_search_text', 'lwtv_underscore_search_text' );
function lwtv_underscore_search_text( $text ) {
	return esc_attr( 'Search the database ...' );
}

/*
 * Filter Search Form
 *
 * Totally rework the search form to allow us to search by post type
 * 
 * @param string $form Default Search Form
 * @param string $search_text Text entered by searcher
 * @param string $button_text The search button text
 * @return Form
 */
add_filter('genesis_search_form', 'lwtv_underscore_search_form', 10, 3);
function lwtv_underscore_search_form($form, $search_text, $button_text) {

	$label = apply_filters( 'genesis_search_form_label', '' );
	$value_or_placeholder = ( get_search_query() == '' ) ? 'placeholder' : 'value';

	$checked_shows = '';
	$checked_characters = '';
	$query_types = get_query_var('post_type');

	if ( is_null($query_types) || empty($query_types) ) {
		$query_types = array( 'post_type_characters', 'post_type_shows' );
	}
	if ( !is_array( $query_types ) ) { $query_types = array( $query_types ); }
	if ( in_array( 'post_type_characters' , $query_types)) { $checked_characters = 'checked="checked"'; }
	if ( in_array( 'post_type_shows' , $query_types)) { $checked_shows = 'checked="checked"'; }

	$form  = sprintf( '<form %s>', genesis_attr( 'search-form' ) );

	$form_id = uniqid( 'searchform-' );

	$form .= sprintf(
		'<meta itemprop="target" content="%s"/>
		<label class="search-form-label screen-reader-text" for="%s">%s</label>
		<input itemprop="query-input" type="search" name="s" id="%s" %s="%s" />
		<input type="submit" value="%s" />
		<input type="checkbox" name="post_type[]" value="post_type_characters" %s /> <label class="search-input-label">Characters</label>
		<input type="checkbox" name="post_type[]" value="post_type_shows" %s /> <label class="search-input-label">Shows</label>
		</form>',
		home_url( '/?s={s}' ),
		esc_attr( $form_id ),
		esc_html( $label ),
		esc_attr( $form_id ),
		$value_or_placeholder,
		esc_attr( $search_text ),
		esc_attr( $button_text ),
		esc_attr( $checked_characters ),
		esc_attr( $checked_shows )
	);

	return $form;
}