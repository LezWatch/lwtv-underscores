<?php
/**
 * Displays 'this year' data.
 *
 * @package LezWatch.TV
 */

namespace LWTV\This_Year;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\This_Year\Build\Characters_Builder;
use LWTV\This_Year\Build\Shows_Builder;
use LWTV\This_Year\Format\Dead_Characters_Formatter;
use LWTV\This_Year\Format\New_Shows_Formatter;
use LWTV\This_Year\Format\Canceled_Shows_Formatter;

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

		// Build data we use for all templates
		$characters_on_air_count = ( new Characters_Builder() )->get_character_count_for_year( $this_year );
		$dead_characters_count   = ( new Characters_Builder() )->get_dead_character_count_for_year( $this_year );
		$shows_on_air_count      = ( new Shows_Builder() )->get_show_count_for_year( $this_year );
		$new_shows_count         = ( new Shows_Builder() )->get_started_show_count_for_year( $this_year );
		$canceled_shows_count    = ( new Shows_Builder() )->get_ended_show_count_for_year( $this_year );

		// Build the data we'll use for multiple templates
		$characters_on_air         = ( new Characters_Builder() )->get_characters_with_shows_for_year( $this_year );
		$characters_on_air_by_show = ( new Shows_Builder() )->get_shows_with_characters_for_year( $this_year );
		$shows_by_name             = ( new Shows_Builder() )->get_shows_for_year_by_name( $this_year );
		$shows_by_format           = ( new Shows_Builder() )->get_shows_for_year_by_format( $this_year );
		$shows_by_country          = ( new Shows_Builder() )->get_shows_for_year_by_nation( $this_year );

		if ( ! in_array( $view, $valid_views, true ) ) {
			$view = 'overview';
		}

		$this_year    = (int) $this_year;
		$current_year = (int) gmdate( 'Y' );
		$first_year   = (int) ( defined( 'LWTV_FIRST_YEAR' ) ? LWTV_FIRST_YEAR : 1961 );
		$ty_baseurl   = ( $this_year === $current_year ) ? '/this-year/' : '/this-year/' . $this_year . '/';
		$statstype    = 'this-year';
		// The formatters filter with a STRICT comparison against string airdate/
		// death-year post-meta (e.g. '2023'), so they need the year as a string —
		// the int above is only for the navigator's arithmetic/boundary checks.
		$year_str = (string) $this_year;
		?>
		<div class="container statistics thisyear">
			<?php
			// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
			include LWTV_PLUGIN_PATH . '/php/statistics/templates/main/tabbar.php';
			include_once 'templates/navigation-year.php'; // year navigator
			include_once 'templates/navigation.php';       // sub-nav

			switch ( $view ) {
				case 'overview':
				default:
					$prev_year       = $this_year - 1;
					$has_prev        = ( $this_year > $first_year );
					$prev_counts     = array(
						'coa'      => $has_prev ? ( new Characters_Builder() )->get_character_count_for_year( $prev_year ) : null,
						'dead'     => $has_prev ? ( new Characters_Builder() )->get_dead_character_count_for_year( $prev_year ) : null,
						'soa'      => $has_prev ? ( new Shows_Builder() )->get_show_count_for_year( $prev_year ) : null,
						'new'      => $has_prev ? ( new Shows_Builder() )->get_started_show_count_for_year( $prev_year ) : null,
						'canceled' => $has_prev ? ( new Shows_Builder() )->get_ended_show_count_for_year( $prev_year ) : null,
					);
					$new_by_country  = ( new New_Shows_Formatter() )->format_by_country_for_year( $year_str, $shows_by_country );
					$new_by_name     = ( new New_Shows_Formatter() )->format_by_name_for_year( $year_str, $shows_by_name );
					$dead_by_date_ov = ( new Dead_Characters_Formatter() )->format_by_date_for_year( $year_str, $characters_on_air );
					include_once 'templates/overview.php';
					break;
				case 'characters-on-air':
					include_once 'templates/characters-on-air.php';
					break;
				case 'dead-characters':
					$dead_by_date = ( new Dead_Characters_Formatter() )->format_by_date_for_year( $year_str, $characters_on_air );
					$dead_by_show = ( new Dead_Characters_Formatter() )->format_by_show_for_year( $year_str, $characters_on_air_by_show );
					include_once 'templates/dead-characters.php';
					break;
				case 'shows-on-air':
					include_once 'templates/shows-on-air.php';
					break;
				case 'new-shows':
					$new_shows_by_name    = ( new New_Shows_Formatter() )->format_by_name_for_year( $year_str, $shows_by_name );
					$new_shows_by_format  = ( new New_Shows_Formatter() )->format_by_format_for_year( $year_str, $shows_by_format );
					$new_shows_by_country = ( new New_Shows_Formatter() )->format_by_country_for_year( $year_str, $shows_by_country );
					include_once 'templates/new-shows.php';
					break;
				case 'canceled-shows':
					$canceled_shows_by_name    = ( new Canceled_Shows_Formatter() )->format_by_name_for_year( $year_str, $shows_by_name );
					$canceled_shows_by_format  = ( new Canceled_Shows_Formatter() )->format_by_format_for_year( $year_str, $shows_by_format );
					$canceled_shows_by_country = ( new Canceled_Shows_Formatter() )->format_by_country_for_year( $year_str, $shows_by_country );
					include_once 'templates/canceled-shows.php';
					break;
			}
			?>
		</div>
		<?php

		// Only the running year is volatile; settled years need no note.
		if ( $this_year === $current_year ) {
			$lwtv_lastcalc_variant = 'volatile';
			$lwtv_lastcalc_time    = lwtv_plugin()->get_this_year_generated_time( $this_year );
			include LWTV_PLUGIN_PATH . '/php/statistics/templates/partials/last-calculated.php';
		}
	}
}
