<?php
/**
 * Displays 'this year' data.
 *
 * @package LezWatch.TV
 */

namespace LWTV\This_Year;

use LWTV\This_Year\Build\Characters;
use LWTV\This_Year\Build\Shows;

class Display {
	/**
	 * Build the stuff for this year
	 *
	 * @param  int    $this_year the year.
	 *
	 * @return n/a    outputs everything
	 */
	public function make( $this_year ) {
		$this_year   = ( isset( $this_year ) ) ? $this_year : gmdate( 'Y' );
		$valid_views = array( 'characters-on-air', 'dead-characters', 'shows-on-air', 'new-shows', 'canceled-shows' );
		$view        = get_query_var( 'view', 'overview' );
		$baseurl     = '/this-year/';

		// Get the characters on air count
		$characters_on_air_count = ( new Characters() )->get_character_count_for_year( $this_year );
		$dead_characters_count   = ( new Characters() )->get_dead_character_count_for_year( $this_year );
		$shows_on_air_count      = ( new Shows() )->get_show_count_for_year( $this_year );
		$new_shows_count         = ( new Shows() )->get_started_show_count_for_year( $this_year );
		$canceled_shows_count    = ( new Shows() )->get_ended_show_count_for_year( $this_year );

		// Build the data we'll use for all the templates
		$characters_on_air         = ( new Characters() )->get_characters_with_shows_for_year( $this_year );
		$characters_on_air_by_show = ( new Shows() )->get_shows_with_characters_for_year( $this_year );

		if ( ! in_array( $view, $valid_views, true ) ) {
			$view = 'overview';
		}
		?>
		<div class="container">
			<?php include_once 'templates/navigation.php'; ?>

			<p>&nbsp;</p>

			<?php
			switch ( $view ) {
				case 'overview':
					include_once 'templates/overview.php';
					break;
				case 'characters-on-air':
					include_once 'templates/characters-on-air.php';
					break;
				case 'dead-characters':
					include_once 'templates/dead-characters.php';
					break;
				case 'shows-on-air':
					include_once 'templates/shows-on-air.php';
					break;
				case 'new-shows':
					include_once 'templates/new-shows.php';
					break;
				case 'canceled-shows':
					include_once 'templates/canceled-shows.php';
					break;
				default:
					include_once 'templates/overview.php';
			}

			// Navigation
			include_once 'templates/navigation-year.php';
			?>
		</div>
		<?php
	}
}
