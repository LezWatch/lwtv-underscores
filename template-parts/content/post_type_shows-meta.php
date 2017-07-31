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

	// Related Posts
	$slug = get_post_field( 'post_name', get_post() );
	$term = term_exists( $slug , 'post_tag' );
	if ( $term !== 0 && $term !== null ) { ?>
		<section name="related-posts" id="related-posts" class="shows-extras">
			<h2>Related Posts</h2>
			<?php
				echo LWTV_CPT_Shows::related_posts( $slug, 'post' );
			?>
		</section> <?php
	}

	// Great big characters section!
	echo '<section name="characters" id="characters" class="shows-extras">';
	echo '<h2>Characters</h2>';

	$havecharacters = LWTV_CPT_Characters::list_characters( $show_id, 'query' );
	$havecharcount  = LWTV_CPT_Characters::list_characters( $show_id, 'count' );
	$havedeadcount  = LWTV_CPT_Characters::list_characters( $show_id, 'dead' );

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