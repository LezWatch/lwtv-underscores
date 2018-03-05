<?php
/**
 * Functions that power the "This Year" pages
 *
 * @package LezWatchTV
 */
 
/**
 * Collect the dead
 * 
 * @access public
 * @param mixed $thisyear
 * @return void
 */
function lwtv_yikes_this_year_dead( $thisyear ) {
	
	$thisyear    = ( isset( $thisyear ) )? $thisyear : date( 'Y' );
	$dead_loop   = LWTV_Loops::post_meta_query( 'post_type_characters', 'lezchars_death_year', $thisyear, 'REGEXP' );
	$dead_queery = wp_list_pluck( $dead_loop->posts, 'ID' );

	?>
	<h2><a name="died">Characters Died This Year (<?php echo $dead_loop->post_count; ?>)</a></h2>

	<?php
	// List all queers and the year they died
	if ( $dead_loop->have_posts() ) {
		$death_list_array = array();
			foreach( $dead_queery as $dead_char ) {
				// Since SOME characters have multiple shows, we force this to be an array
				$show_IDs = get_post_meta( $dead_char, 'lezchars_show_group', true );
				$show_title = array();

				foreach ( $show_IDs as $each_show ) {
					// if the show isn't published, no links
					if ( get_post_status ( $each_show['show'] ) !== 'publish' ) {
						array_push( $show_title, '<em><span class="disabled-show-link">' . get_the_title( $each_show['show'] ) . '</span></em> (' . $each_show['type'] . ' character)' );
					} else {
						array_push( $show_title, '<em><a href="' . get_permalink( $each_show['show'] ) . '">' . get_the_title( $each_show['show'] ) . '</a></em> (' . $each_show['type'] . ' character)' );
					}
				}

				$show_info = implode( ', ', $show_title );

				// Only extract the date for this year and convert to unix time
				// Jesus, I hope no one dies twice in the same year ... SARA
				$died_date = get_post_meta( $dead_char, 'lezchars_death_year', true);
				foreach ( $died_date as $date ) {
					if ( (int) substr( $date, 0, 4 ) == substr( $date, 0, 4 ) ) {
						$died_year = substr( $date, 0, 4 );
						$died_array = date_parse_from_format( 'Y-m-d', $date );
					} else {
						$died_year = substr( $date, -4 );
						$died_array = date_parse_from_format( 'm/d/Y', $date );
					}

					if ( $died_year == $thisyear ) {
						$died = mktime( $died_array['hour'], $died_array['minute'], $died_array['second'], $died_array['month'], $died_array['day'], $died_array['year'] );
					}
				}

				// Get the post slug
				$post_slug = get_post_field( 'post_name', get_post( $dead_char ) );

				$death_list_array[$post_slug] = array(
					'name'  => get_the_title( $dead_char ),
					'url'   => get_the_permalink( $dead_char ),
					'shows' => $show_info,
					'died'  => $died,
				);
			}

			// Reorder all the dead to sort by DoD
			uasort( $death_list_array, function($a, $b) {
				// Spaceship doesn't work
				// $return = $a['died'] <=> $b['died'];
				
				$return = '0';
				if ( $a['died'] < $b['died'] ) $return = '-1';
				if ( $a['died'] > $b['died'] ) $return = '1';
				return $return;

			});
		?>
		<ul>
			<?php
				foreach ( $death_list_array as $dead ) {
					echo '<li><a href="' . $dead['url'] . '">' . $dead['name'] . '</a> / ' . $dead['shows'] . ' / ' . date( 'd F', $dead['died'] ) . ' </li>';
				}
			?>
		</ul>
		<?php
	} else {
		?>
		<p>No known characters died in <?php echo $thisyear; ?>.</p>
		<?php
	}
	wp_reset_query();
}

/**
 * List of shows for the year.
 * 
 * @access public
 * @param mixed $thisyear
 * @return void
 */
function lwtv_yikes_this_year_shows( $thisyear ) {

	// Constants
	$shows_this_year = array( 'current' => 0, 'ended' => 0, 'started' => 0);
	$shows_current = $shows_started = $shows_ended = array();

	$shows_queery = LWTV_Loops::post_type_query( 'post_type_shows' );

	if ($shows_queery->have_posts() ) {
		while ( $shows_queery->have_posts() ) {
			$shows_queery->the_post();

			$show_id   = get_the_ID();
			$show_name = preg_replace( '/\s*/', '', get_the_title( $show_id ) );
			$show_name = strtolower( $show_name );

			// Shows Currently Airing
			if ( get_post_meta( $show_id, 'lezshows_airdates', true) ) {
				$airdates = get_post_meta( $show_id, 'lezshows_airdates', true);

				if (
					( $airdates['finish'] == 'current' && $thisyear == date('Y') ) // Still Current and it's NOW
					|| ( $airdates['finish'] >= $thisyear && $airdates['start'] <= $thisyear ) // Airdates between
				) {
				// Currently Airing Shows shows for the current year only
					$shows_current[$show_name] = array(
						'url'    => get_permalink( $show_id ),
						'name'   => get_the_title( $show_id ),
						'status' => get_post_status( $show_id ),
					);
					$shows_this_year['current']++;
				}

				// Shows that ended this year
				if( $airdates['finish'] == $thisyear ) {
					$shows_ended[$show_name] = array(
						'url'    => get_permalink( $show_id ),
						'name'   => get_the_title( $show_id ),
						'status' => get_post_status( $show_id ),
					);
					$shows_this_year['ended']++;
				}

				// Shows that STARTED this year
				if ( $airdates['start'] == $thisyear ) {
					$shows_started[$show_name] = array(
						'url'    => get_permalink( $show_id ),
						'name'   => get_the_title( $show_id ),
						'status' => get_post_status( $show_id ),
					);
					$shows_this_year['started']++;
				}
			}
		}
	}

	?>
	
	<hr>

	<h3><a name="showsonair">Shows Aired This Year (<?php echo $shows_this_year['current']; ?>)</a></h3>
	
	<?php
	if ( $shows_this_year['current'] !== 0 ) {
		echo '<ul class="this-year-shows showsonair">';
		foreach ( $shows_current as $show ) {
			$show_output = $show['name'];
			if ( $show['status'] == 'publish' ) {
				$show_output = '<a href="' . $show['url'] . '">' . $show['name'] . '</a>';
			}
			echo '<li>' . $show_output . '</li>';
		}
		echo '</ul>';
	} else {
		?><p>No shows with known queer female characters aired in <?php echo $thisyear; ?>.</p><?php
	}
	?>

	<hr>

	<h3><a name="showsstart">Shows That Started This Year (<?php echo $shows_this_year['started']; ?>)</a></h3>

	<?php
	if ( $shows_this_year['started'] !== 0 ) {
		echo '<ul class="this-year-shows showsstart">';
		foreach ( $shows_started as $show ) {
			$show_output = $show['name'];
			if ( $show['status'] == 'publish' ) {
				$show_output = '<a href="' . $show['url'] . '">' . $show['name'] . '</a>';
			}
			echo '<li>' . $show_output . '</li>';
		}
		echo '</ul>';
	} else {
		?><p>No shows with known queer female characters premiered in <?php echo $thisyear; ?>.</p><?php
	}
	?>

	<hr>
	
	<h3><a name="showsend">Shows That Ended This Year (<?php echo $shows_this_year['ended']; ?>)</a></h3>

	<?php
	if ( $shows_this_year['ended'] !== 0 ) {
		echo '<ul class="this-year-shows showsend">';
		foreach ( $shows_ended as $show ) {
			$show_output = $show['name'];
			if ( $show['status'] == 'publish' ) {
				$show_output = '<a href="' . $show['url'] . '">' . $show['name'] . '</a>';
			}
			echo '<li>' . $show_output . '</li>';
		}
		echo '</ul>';
	} else {
		?><p>No shows with known queer female characters ended in <?php echo $thisyear; ?>.</p><?php
	}
	?>

	<?php
	wp_reset_query();
}

function lwtv_yikes_this_year_navigation( $thisyear ) {
	
	$thisyear = ( isset( $thisyear ) )? $thisyear : date( 'Y' );
	$lastyear = FIRST_LWTV_YEAR;
	$baseurl  = '/this-year/';
	?>

	<nav aria-label="This Year navigation" role="navigation">
		<ul class="pagination justify-content-center">
			
			<?php
			// If it's not 1961, we can show the first year we have queers
			if ( $thisyear !== $lastyear ) {
				?>
				<li class="page-item first mr-auto"><a href="<?php echo $baseurl . $lastyear . '/'; ?>" class="page-link"><?php echo lwtv_yikes_symbolicons( 'caret-left-circle.svg', 'fa-chevron-circle-left' ); ?> First (<?php echo $lastyear; ?>)</a></li>
				<li class="page-item previous"><a href="<?php echo $baseurl . ( $thisyear - 1 ) . '/'; ?>" title="previous year" class="page-link"><?php echo lwtv_yikes_symbolicons( 'caret-left.svg', 'fa-chevron-left' ); ?> Previous</a></li>	
				<li class="page-item"><a href="<?php echo $baseurl . ( $thisyear - 2 ) . '/'; ?>" class="page-link"><?php echo ( $thisyear - 2 ); ?></a></li>
				<li class="page-item"><a href="<?php echo $baseurl . ( $thisyear - 1 ) . '/'; ?>" class="page-link"><?php echo ( $thisyear - 1 ); ?></a></li>
				<?php
			}	
			?>
						
			<li class="page-item active"><span class="active page-link"><?php echo $thisyear; ?></span></li>

			<?php
			if ( $thisyear !== date('Y') ) {
				?>
				<li class="page-item"><a href="<?php echo $baseurl . ( $thisyear +1 ) . '/'; ?>" class="page-link"><?php echo ( $thisyear + 1 ); ?></a></li>
				<li class="page-item next"><a href="<?php echo $baseurl . ( $thisyear +1 ) . '/'; ?>" class="page-link" title="next year">Next <?php echo lwtv_yikes_symbolicons( 'caret-right-circle.svg', 'fa-chevron-circle-right' ); ?></a></li>
				<li class="page-item last ml-auto"><a href="<?php echo $baseurl . date( 'Y' ) . '/'; ?>" class="page-link">Last (<?php echo date( 'Y' ); ?>)<?php echo lwtv_yikes_symbolicons( 'caret-right.svg', 'fa-chevron-right' ); ?></a></li>
				<?php
			}
			?>
		</ul>
	</nav><!-- .navigation -->
	<?php
}