<?php
/**
 * Render the airdate calendar as a single scrolling agenda.
 *
 * Data shaping lives in LWTV\Calendar\Build\Agenda; this class only turns the
 * built day-groups into markup.
 *
 * @package lwtv-plugin
 */

namespace LWTV\Calendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\_Helpers\Calendar_Object_Pool;
use LWTV\Calendar\Build\Agenda;

class Display_Agenda {

	/**
	 * Render the agenda for a single week.
	 *
	 * @param  array $calendar Processed calendar, keyed by Y-m-d.
	 * @param  array $week     Ordered seven Y-m-d dates, Sunday first.
	 * @return string
	 */
	public function get_shows( array $calendar, array $week ): string {
		$display = Calendar_Object_Pool::get_display();
		$today   = $display->today->format( 'Y-m-d' );

		$agenda = new Agenda( LWTV_TIMEZONE );
		$groups = $agenda->build( $calendar, $today, $week );

		// Today only has a marker to jump to when it falls inside the week
		// being shown, so the control is hidden while browsing other weeks.
		$has_today = in_array( $today, $week, true );

		$output  = '<div class="ep-agenda" data-lwtv-agenda>';
		$output .= $this->get_header( $agenda->week_strip( $calendar, $today, $week ), $has_today );

		if ( empty( $groups ) ) {
			$output .= '<p class="ep-agenda-empty">' . esc_html__( 'Nothing is scheduled to air in this window. Check back soon.', 'lwtv' ) . '</p>';
		} else {
			foreach ( $groups as $group ) {
				$output .= $this->get_day_group( $group );
			}
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Sticky header: timezone eyebrow, jump-to-today control, week strip.
	 *
	 * @param  array $strip     Week strip entries.
	 * @param  bool  $has_today Whether today falls inside the week being shown.
	 * @return string
	 */
	private function get_header( array $strip, bool $has_today ): string {
		$header  = '<div class="ep-agenda-header">';
		$header .= '<div class="ep-agenda-header-top">';
		$header .= '<p class="ep-agenda-eyebrow">' . esc_html__( 'Airdate Calendar', 'lwtv' ) . ' &middot; ' . esc_html__( 'US/Eastern', 'lwtv' ) . '</p>';

		if ( $has_today ) {
			$icon    = lwtv_plugin()->get_symbolicon( svg: 'clock.svg', icon: 'svg-clock', max_size: '13' );
			$header .= '<a href="#ep-agenda-today" class="ep-agenda-jump" data-lwtv-agenda-jump>' . $icon . esc_html__( 'Today', 'lwtv' ) . '</a>';
		}

		$header .= '</div>';

		$header .= '<ul class="ep-agenda-week">';
		foreach ( $strip as $day ) {
			$classes = 'ep-agenda-week-day is-' . $day['state'];

			// Keep the pills functional: a day with episodes jumps to its group.
			if ( $day['has_shows'] ) {
				$header .= '<li class="' . esc_attr( $classes ) . '"><a href="#ep-agenda-' . esc_attr( $day['date'] ) . '">' . esc_html( $day['label'] ) . '</a></li>';
			} else {
				$header .= '<li class="' . esc_attr( $classes ) . ' is-quiet"><span>' . esc_html( $day['label'] ) . '</span></li>';
			}
		}
		$header .= '</ul>';
		$header .= '</div>';

		return $header;
	}

	/**
	 * One day-group: header row plus its episode rows.
	 *
	 * @param  array $group Day-group, already carrying its past/today/future state.
	 * @return string
	 */
	private function get_day_group( array $group ): string {
		$is_today = ( 'today' === $group['state'] );
		$anchor   = $is_today ? ' id="ep-agenda-today"' : '';
		$icon     = lwtv_plugin()->get_symbolicon( svg: 'calendar-alt.svg', icon: 'svg-calendar-alt', max_size: '12' );

		$out  = '<section class="ep-agenda-day is-' . esc_attr( $group['state'] ) . '" id="ep-agenda-' . esc_attr( $group['date'] ) . '">';
		$out .= '<h3 class="ep-agenda-day-heading"' . $anchor . '>';
		$out .= $icon;
		$out .= '<span class="ep-agenda-day-label">' . esc_html( $group['label'] ) . '</span>';

		if ( $is_today ) {
			$out .= '<span class="ep-agenda-badge">' . esc_html__( 'Today', 'lwtv' ) . '</span>';
		}

		$out .= '</h3>';

		$out .= '<ul class="ep-agenda-episodes">';
		foreach ( $group['episodes'] as $episode ) {
			$out .= $this->get_episode( $episode );
		}
		$out .= '</ul>';
		$out .= '</section>';

		return $out;
	}

	/**
	 * One episode row.
	 *
	 * The dot carries the ISO airtime so the client can decide whether it has
	 * actually aired; `dot_state` is the server-rendered fallback.
	 *
	 * @param  array $episode Episode entry.
	 * @return string
	 */
	private function get_episode( array $episode ): string {
		$row  = '<li class="ep-agenda-episode">';
		$row .= '<span class="ep-agenda-dot is-' . esc_attr( $episode['dot_state'] ) . '" data-airtime="' . esc_attr( $episode['iso_airtime'] ) . '" aria-hidden="true"></span>';
		$row .= '<time class="ep-agenda-time" datetime="' . esc_attr( $episode['iso_airtime'] ) . '">' . esc_html( $episode['time_label'] ) . '</time>';
		$row .= '<span class="ep-agenda-show">';

		// show_link is pre-escaped markup from the data processor - do not re-escape.
		if ( is_array( $episode['title'] ) ) {
			$row .= '<span class="ep-agenda-show-name">' . $episode['show_link'] . '</span>' . $episode['badge'];
			$row .= '<ul class="ep-agenda-multi">';
			foreach ( $episode['title'] as $one ) {
				$row .= '<li>' . esc_html( $one ) . '</li>';
			}
			$row .= '</ul>';
		} else {
			$row .= '<span class="ep-agenda-show-name">' . $episode['show_link'] . '</span>';
			$row .= '<span class="ep-agenda-episode-title"> &ndash; ' . esc_html( $episode['title'] ) . '</span>';
		}

		$row .= '</span>';
		$row .= '</li>';

		return $row;
	}
}
