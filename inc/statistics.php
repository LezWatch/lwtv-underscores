<?php
/**
 * Functions that power the "Statistics" pages
 *
 * @package LezWatch.TV
 */

/**
 * Standarized Descriptions
 */
function lwtv_yikes_statistics_description( $type, $cpts_type, $view ) {
	$in_or_on = ( 'station' === $type ) ? 'on' : 'in';

	switch ( $cpts_type ) {
		case 'characters':
			switch ( $view ) {
				case 'cliches':
					$description = '<p>Clichés are overly used archtypes given to a character. This is the breakdown of cliches on characters from shows that air ' . $in_or_on . ' this ' . $type . '.</p>';
					break;
				default:
					$description = '<p>The following statistics relate to characters that air ' . $in_or_on . ' this ' . $type . '.</p>';
					break;
			}
			break;
		case 'shows':
			switch ( $view ) {
				case 'tropes':
					$description = '<p>Tropes represent overly common methods of telling a story on a show. This is the breakdown of tropes on shows that air ' . $in_or_on . ' this ' . $type . '.</p>';
					break;
				case 'intersections':
					$description = '<p>Intersectionality is a positive representation on a show, of a generally marginalized group of people. This is the breakdown of intersectionality on shows that air ' . $in_or_on . ' this ' . $type . '. Note: Not all shows have positive representation of intersectionality.</p>';
					break;
				case 'formats':
					$description = '<p>Formats are the way in which TV Shows are seen. This is the breakdown of TV show formats for shows that air ' . $in_or_on . ' this ' . $type . '</p>';
					break;
				default:
					$description = '<p>The following statistics relate to shows that air ' . $in_or_on . ' this ' . $type . '.</p>';
					break;
			}
			break;
	}

	return $description;
}
