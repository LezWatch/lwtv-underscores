<?php
/**
 * Name: Content Warning
 * Description: The card style and text for a show's content warning.
 *
 * Alias handling ('on', 'medium') is delegated to Trigger_Warning::normalize()
 * rather than duplicated here. This file used to carry its own copy of that
 * table, independently of Calculations::show_score() -- two copies of one
 * decision, the exact pattern Character_Score's docblock documents causing
 * three real bugs in this project already.
 *
 * @package LWTV
 */

namespace LWTV\Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Shows\Trigger_Warning;

class Content_Warning {

	/** Bootstrap alert style per canonical trigger level. */
	const CARD_STYLES = array(
		'high' => 'danger',
		'med'  => 'warning',
		'low'  => 'info',
	);

	/**
	 * Show content warning.
	 *
	 * If a show has a content warning, let's show it.
	 *
	 * @access public
	 * @param int $show_id Show post ID.
	 * @return array{card:string,content:string}
	 */
	public function make( $show_id ) {

		$warning_array = array(
			'card'    => 'none',
			'content' => 'none',
		);

		// If there's no post ID passed or it's not a show, we show nothing.
		if ( is_null( $show_id ) || 'post_type_shows' !== get_post_type( $show_id ) ) {
			return $warning_array;
		}

		$trigger_terms = get_the_terms( $show_id, 'lez_triggers' );
		$has_term      = ! empty( $trigger_terms ) && ! is_wp_error( $trigger_terms );
		$trigger       = $has_term ? $trigger_terms[0]->slug : get_post_meta( $show_id, 'lezshows_triggerwarning', true );
		$level         = Trigger_Warning::normalize( (string) $trigger );

		if ( ! isset( self::CARD_STYLES[ $level ] ) ) {
			return $warning_array;
		}

		$warning_array['card']    = self::CARD_STYLES[ $level ];
		$warning_array['content'] = $has_term ? term_description( $trigger_terms[0]->term_id ) : '<strong>WARNING</strong> This show may be upsetting to watch.';

		return $warning_array;
	}
}
