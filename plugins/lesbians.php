<?php
/**
 * Weird LWTV functions and definitions
 *
 * This is crazy shit Mika wrote to force everything to play
 * nicely with each other. Including cursing at Jetpack.
 *
 * TODO: Move this to the lwtv-plugin/php/theme folder
 *
 * @package LezWatch.TV
 */

/** THE ARCHIVE SECTION **/

/*
 * https://wordpress.stackexchange.com/questions/172645/get-the-post-type-a-taxonomy-is-attached-to
 */
function lwtv_yikes_get_post_types_by_taxonomy( $tax = 'category' ) {
	$out        = '';
	$post_types = get_post_types();
	foreach ( $post_types as $post_type ) {
		$taxonomies = get_object_taxonomies( $post_type );
		if ( in_array( $tax, $taxonomies, true ) ) {
			// There should only be one (Highlander)
			$out = $post_type;
		}
	}
	return $out;
}

/** THE SEO SECTION **/

/**
 * Fix microformats
 * We have to have author, updated, and entry-title IN the hentry data.
 *
 * @access public
 * @param mixed $post_id
 * @return void
 */
function lwtv_microformats_fix( $post_id ) {
	$valid_types = array( 'post_type_authors', 'post_type_characters', 'post_type_shows' );
	if ( in_array( get_post_type( $post_id ), $valid_types, true ) ) {
		echo '<div class="hatom-extra" style="display:none;visibility:hidden;">
			<span class="entry-title">' . esc_html( get_the_title( $post_id ) ) . '</span>
			<span class="updated">' . esc_html( get_the_modified_time( 'F jS, Y', $post_id ) ) . '</span>
			<span class="author vcard"><span class="fn">' . esc_html( get_option( 'blogname' ) ) . '</span></span>
		</div>';
	}
}

/** LWTV Plugin **/
// This section includes all the code we call from the LWTV plugin, with sanity checks.
// PORT ALL TO PLUGINS AND CALL WITH lwtv_plugin()->METHOD()

/**
 * Get Last Updated
 *
 * @param string $post_ID
 *
 * @return n/a
 */
function lwtv_last_updated_date( $post_id ) {
	$updated_date = get_the_modified_time( 'F jS, Y', $post_id );
	$last_updated = '<div class="last-updated"><small class="text-muted">This page was last edited on ' . $updated_date . '.</small></div>';

	echo wp_kses_post( $last_updated );
}

/**
 * Show the last death
 *
 * @return n/a
 */
function lwtv_last_death() {
	$return     = '<p>The LezWatch.TV API is temporarily unavailable.</p>';
	$last_death = lwtv_plugin()->get_json_last_death();
	if ( '' !== $last_death ) {
		$died_time = human_time_diff( $last_death['died'], (int) wp_date( 'U' ) );

		// If the death was within 24 hours, be more vague.
		$seconds = (int) time() - (int) $last_death['died'];
		if ( DAY_IN_SECONDS >= $seconds ) {
			$died_time = 'less than 24 hours';
		}

		$return  = '<p>' . sprintf( 'It has been %s since the last queer female, non-binary, or transgender death on television', '<strong>' . $died_time . '</strong> ' );
		$return .= ': <span><a href="' . $last_death['url'] . '">' . $last_death['name'] . '</a></span> - ' . gmdate( 'F j, Y', $last_death['died'] ) . '</p>';
		// NOTE! Add `class="hidden-death"` to the span above if you want to blur the display of the last death.
	}

	$return = '<div class="lezwatchtv last-death">' . $return . '</div>';

	return $return;
}
