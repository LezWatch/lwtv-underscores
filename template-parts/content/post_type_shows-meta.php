<?php
/**
 * Template part for displaying show post meta
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatch_TV
 */

	global $post, $wpdb;
	$show_id = $post->ID;

	// Loop to get the list of characters
	$charactersloop = LWTV_Loops::post_meta_query( 'post_type_characters', 'lezchars_show_group', $show_id, 'LIKE' );

	$havecharacters = array();
	$havecharcount  = 0;

	// Store as array to defeat some stupid with counting and prevent querying the database too many times
	if ($charactersloop->have_posts() ) {
		while ( $charactersloop->have_posts() ) {
			$charactersloop->the_post();
			$char_id = $post->ID;
			$shows_array = get_post_meta( $char_id, 'lezchars_show_group', true);

			// If the character is in this show, AND a published character
			// we will pass the following data to the character template
			// to determine what to display
			if ( $shows_array !== '' && get_post_status ( $char_id ) == 'publish' ) {
				foreach( $shows_array as $char_show ) {
					if ( $char_show['show'] == $show_id ) {
						$havecharacters[$char_id] = array(
							'id'        => $char_id,
							'title'     => get_the_title( $char_id ),
							'url'       => get_the_permalink( $char_id ),
							'content'   => get_the_content( $char_id ),
							'shows'     => $shows_array,
							'show_from' => $show_id,
						);
						$havecharcount++;
					}
				}
			}
		}
		wp_reset_query();
	}

	// Queer Plots - Only display if they exist
	if( (get_post_meta($show_id, "lezshows_plots", true) )  ) { ?>
		<section name="timeline" id="timeline" class="shows-extras"><h2>Queer Plotline Timeline</h2>
		<?php echo apply_filters('the_content', wp_kses_post( get_post_meta($show_id, 'lezshows_plots', true) ) ); ?></section><?php
	}

	// Best Lez Episodes - Only display if they exist
	if((get_post_meta($show_id, "lezshows_episodes", true))) { ?>
		<section name="episodes" id="episodes" class="shows-extras"><h2>Notable Lez-Centric Episodes</h2>
		<?php echo apply_filters('the_content', wp_kses_post( get_post_meta($show_id, 'lezshows_episodes', true) ) ); ?></section> <?php
	}

	// Great big characters section!
	echo '<section name="characters" id="characters" class="shows-extras">';
	echo '<h2>Characters</h2>';
	if ( empty($havecharacters) || $havecharacters == '0' ) {
		echo '<p>There are no queers listed yet for this show.</p>';
	} else {

		$deadtext = 'none are dead';
		if ( $havedeadcount > '0' )
			$deadtext = sprintf( _n( '%s is dead', '%s are dead', $havedeadcount ), $havedeadcount );

		echo '<p>There '. sprintf( _n( 'is %s queer character', 'are %s queer characters', $havecharcount ), $havecharcount ).' listed for this show. Of those, ' . $deadtext . '.</p>';
		foreach( $havecharacters as $character ) {
		?>
			<ul class="character-list">
				<li class="clearfix">
					<?php
						include( locate_template('template-parts/excerpt/post_type_characters.php') );
					?>
				</li>
				<!-- // The end of The Loop -->
			</ul>
		<?php
		}
	}
	echo '</section>';