<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * This Year — Overview: editorial lead card, 5-metric ribbon, highlights.
 *
 * @package LezWatch.TV
 *
 * @var int        $this_year
 * @var int        $first_year
 * @var int        $characters_on_air_count
 * @var int        $dead_characters_count
 * @var int        $shows_on_air_count
 * @var int        $new_shows_count
 * @var int        $canceled_shows_count
 * @var array      $characters_on_air_by_show
 * @var int        $prev_year   Set by Display::make() for the overview/default branch.
 * @var bool       $has_prev    Set by Display::make() for the overview/default branch.
 * @var array      $prev_counts Set by Display::make() for the overview/default branch.
 * @var array      $new_by_country  Set by Display::make() for the overview/default branch.
 * @var array      $new_by_name     Set by Display::make() for the overview/default branch.
 * @var array      $dead_by_date_ov Set by Display::make() for the overview/default branch.
 * @var int        $current_year    Set by Display::make(); the actual current year.
 * @var string     $ty_baseurl      Set by Display::make(); '/this-year/' or '/this-year/{year}/'.
 * @var array      $shows_by_country   Set by Display::make(); all on-air shows grouped by country.
 * @var array      $shows_by_format    Set by Display::make(); all on-air shows grouped by format.
 * @var array      $characters_on_air  Set by Display::make(); characters with their shows/role types.
 */

use LWTV\This_Year\Build\Deaths_Strip;
use LWTV\This_Year\Build\Longest_Running;
use LWTV\This_Year\Build\Breakdowns;
use LWTV\This_Year\Build\Standouts;
use LWTV\This_Year\Build\Characters_Builder;
use LWTV\This_Year\Build\Trends;
use LWTV\This_Year\Format\New_Shows_Formatter;
use LWTV\Rest_API\This_Year_JSON;

$coa      = (int) $characters_on_air_count;
$dead     = (int) $dead_characters_count;
$soa      = (int) $shows_on_air_count;
$new      = (int) $new_shows_count;
$canceled = (int) $canceled_shows_count;

// ---- Lead card: standout sentence + supporting narrative. ----
if ( 0 === $dead ) {
	$lead_stat = sprintf(
		/* translators: 1: characters on air count, 2: new shows count. */
		__( '%1$s characters on air, %2$s new shows, and not a single death ... so far.', 'lwtv' ),
		number_format_i18n( $coa ),
		number_format_i18n( $new )
	);
} else {
	$lead_stat = sprintf(
		/* translators: 1: characters on air count, 2: premieres count, 3: deaths count. */
		__( '%1$s queer characters on air, %2$s premieres, and %3$s we lost.', 'lwtv' ),
		number_format_i18n( $coa ),
		number_format_i18n( $new ),
		number_format_i18n( $dead )
	);
}

if ( ! $has_prev ) {
	$trend_word = __( 'the first year we tracked', 'lwtv' );
} else {
	$coa_delta = $coa - (int) $prev_counts['coa'];
	if ( $coa_delta > 0 ) {
		/* translators: 1: increase in characters on air since last year, 2: prior year. */
		$trend_word = sprintf( __( 'up %1$s from %2$s', 'lwtv' ), number_format_i18n( $coa_delta ), (string) $prev_year );
	} elseif ( $coa_delta < 0 ) {
		/* translators: 1: decrease in characters on air since last year, 2: prior year. */
		$trend_word = sprintf( __( 'down %1$s from %2$s', 'lwtv' ), number_format_i18n( abs( $coa_delta ) ), (string) $prev_year );
	} else {
		/* translators: %s: prior year. */
		$trend_word = sprintf( __( 'flat against %s', 'lwtv' ), (string) $prev_year );
	}
}

$deaths_clause = ( 0 === $dead )
	? __( 'and remarkably, no one has died.', 'lwtv' )
	: sprintf(
		/* translators: %s: number of characters who died this year. */
		__( 'while we lost %s.', 'lwtv' ),
		number_format_i18n( $dead )
	);

$narrative = sprintf(
	/* translators: 1: year, 2: characters on air count, 3: shows on air count, 4: trend phrase (already translated), 5: new shows count, 6: canceled shows count, 7: deaths clause (already translated). */
	__( '%1$s has %2$s queer characters on air across %3$s shows, %4$s. %5$s series premiered and %6$s wrapped, %7$s', 'lwtv' ),
	(string) $this_year,
	number_format_i18n( $coa ),
	number_format_i18n( $soa ),
	$trend_word,
	number_format_i18n( $new ),
	number_format_i18n( $canceled ),
	$deaths_clause
);

// ---- 5-metric ribbon: count + live delta vs. the prior year. ----
$ty_metrics = array(
	array(
		'label'  => __( 'Characters On Air', 'lwtv' ),
		'family' => 'green',
		'count'  => $coa,
		'prev'   => $prev_counts['coa'],
		'key'    => 'coa',
		'trend'  => 'characters',
	),
	array(
		'label'  => __( 'Dead Characters', 'lwtv' ),
		'family' => 'red',
		'count'  => $dead,
		'prev'   => $prev_counts['dead'],
		'key'    => 'dead',
		'trend'  => 'dead',
	),
	array(
		'label'  => __( 'Shows On Air', 'lwtv' ),
		'family' => 'blue',
		'count'  => $soa,
		'prev'   => $prev_counts['soa'],
		'key'    => 'soa',
		'trend'  => 'shows',
	),
	array(
		'label'  => __( 'New Shows', 'lwtv' ),
		'family' => 'pink',
		'count'  => $new,
		'prev'   => $prev_counts['new'],
		'key'    => 'new',
		'trend'  => 'started',
	),
	array(
		'label'  => __( 'Canceled Shows', 'lwtv' ),
		'family' => 'amber',
		'count'  => $canceled,
		'prev'   => $prev_counts['canceled'],
		'key'    => 'canceled',
		'trend'  => 'canceled',
	),
);

/**
 * Build the ribbon delta caption: "{arrow} {N} vs {prevYear}", or "first tracked"
 * when there is no prior year to compare (the floor year).
 *
 * @param int      $now  This year's count.
 * @param int|null $prev Prior year's count, or null at the floor year.
 * @return string
 */
$ty_delta_text = function ( $now, $prev ) use ( $prev_year ) {
	if ( null === $prev ) {
		return __( 'first tracked', 'lwtv' );
	}

	$delta = (int) $now - (int) $prev;
	$arrow = ( $delta > 0 ) ? '↑' : ( ( $delta < 0 ) ? '↓' : '–' );

	/* translators: 1: arrow glyph (up/down/flat), 2: absolute change, 3: prior year. */
	return sprintf( __( '%1$s %2$s vs %3$s', 'lwtv' ), $arrow, number_format_i18n( abs( $delta ) ), (string) $prev_year );
};

// ---- Highlights of the year (all derived; every fallback guarded). ----

// 1. Biggest premiere: the new show (by year-start) with the most characters on air.
$char_counts_by_show = array();
foreach ( $characters_on_air_by_show as $lwtv_ty_show ) {
	$char_counts_by_show[ $lwtv_ty_show['name'] ] = count( $lwtv_ty_show['characters'] );
}

$biggest_premiere       = null;
$biggest_premiere_chars = -1;
foreach ( $new_by_name as $lwtv_ty_group ) {
	foreach ( $lwtv_ty_group as $lwtv_ty_new_show ) {
		$lwtv_ty_show_chars = $char_counts_by_show[ $lwtv_ty_new_show['name'] ] ?? 0;
		if ( $lwtv_ty_show_chars > $biggest_premiere_chars ) {
			$biggest_premiere_chars = $lwtv_ty_show_chars;
			$biggest_premiere       = $lwtv_ty_new_show;
		}
	}
}

if ( $biggest_premiere ) {
	$highlight_premiere_title = $biggest_premiere['name'];
	$highlight_premiere_desc  = sprintf(
		/* translators: 1: show format, 2: show country, 3: character count. */
		__( 'A %1$s from %2$s with %3$s queer characters, the most of any new show this year.', 'lwtv' ),
		$biggest_premiere['format'],
		$biggest_premiere['country'],
		number_format_i18n( $biggest_premiere_chars )
	);
} else {
	$highlight_premiere_title = __( 'No new shows yet', 'lwtv' );
	$highlight_premiere_desc  = __( 'Nothing has premiered yet this year.', 'lwtv' );
}

// Resolve the biggest-premiere show's post ID from its permalink so the lead
// card can feature its poster. Only renders when the show has a featured image.
$biggest_premiere_id = ( $biggest_premiere && ! empty( $biggest_premiere['url'] ) ) ? url_to_postid( $biggest_premiere['url'] ) : 0;
$lead_media          = ( $biggest_premiere_id && has_post_thumbnail( $biggest_premiere_id ) )
	? get_the_post_thumbnail(
		$biggest_premiere_id,
		'medium',
		array(
			'class'   => 'lwtv-ty-lead-img',
			'loading' => 'lazy',
			'alt'     => $biggest_premiere['name'],
		)
	)
	: '';

// 2. Leading nation: the country with the most new shows this year.
$leading_nation       = null;
$leading_nation_count = 0;
foreach ( $new_by_country as $lwtv_ty_country => $lwtv_ty_country_shows ) {
	$lwtv_ty_count = count( $lwtv_ty_country_shows );
	if ( $lwtv_ty_count > $leading_nation_count ) {
		$leading_nation_count = $lwtv_ty_count;
		$leading_nation       = $lwtv_ty_country;
	}
}

if ( $leading_nation ) {
	$highlight_nation_title = $leading_nation;
	$highlight_nation_desc  = sprintf(
		/* translators: 1: number of new shows, 2: country name. */
		__( '%1$s of this year\'s new shows come from %2$s, more than any other country.', 'lwtv' ),
		number_format_i18n( $leading_nation_count ),
		$leading_nation
	);
} else {
	$highlight_nation_title = __( '—', 'lwtv' );
	$highlight_nation_desc  = __( 'No new shows to rank by country yet this year.', 'lwtv' );
}

// 3. Longest-running character we lost: of this year's deaths, the one with the
// most years actually on air (distinct appearance years across all their shows,
// NOT the debut-to-death span — that overstates gappy mini-series careers). This
// replaces the old "most recent death" card, which elevated one person for no
// stated reason. When no tenure resolves, the row is dropped rather than faked.
$lwtv_ty_lost_winner = null;
if ( $dead > 0 && ! empty( $dead_by_date_ov ) ) {
	$lwtv_ty_lost_candidates = array();
	foreach ( $dead_by_date_ov as $lwtv_ty_death_group ) {
		foreach ( $lwtv_ty_death_group as $lwtv_ty_dead_char ) {
			$lwtv_ty_char_post = get_page_by_path( $lwtv_ty_dead_char['slug'], OBJECT, 'post_type_characters' );
			if ( ! $lwtv_ty_char_post ) {
				continue;
			}

			$lwtv_ty_show_group = get_field( 'lezchars_show_group', $lwtv_ty_char_post->ID );
			$lwtv_ty_ten        = Longest_Running::tenure( is_array( $lwtv_ty_show_group ) ? $lwtv_ty_show_group : array() );
			if ( $lwtv_ty_ten['years'] <= 0 ) {
				continue;
			}

			$lwtv_ty_lost_candidates[] = array(
				'name'       => $lwtv_ty_dead_char['name'],
				'years'      => $lwtv_ty_ten['years'],
				'first_year' => $lwtv_ty_ten['first_year'],
				'show_id'    => $lwtv_ty_ten['show_id'],
				'url'        => get_permalink( $lwtv_ty_char_post->ID ),
			);
		}
	}
	$lwtv_ty_lost_winner = Longest_Running::pick( $lwtv_ty_lost_candidates );
}

// Leading-nation link: the country's own term archive, falling back to the
// nations statistics overview when the term can't be resolved by name.
$lwtv_ty_nation_url = home_url( '/statistics/nations/' );
if ( $leading_nation ) {
	$lwtv_ty_nation_term = get_term_by( 'name', $leading_nation, 'lez_country' );
	if ( $lwtv_ty_nation_term && ! is_wp_error( $lwtv_ty_nation_term ) ) {
		$lwtv_ty_nation_link = get_term_link( $lwtv_ty_nation_term );
		if ( ! is_wp_error( $lwtv_ty_nation_link ) ) {
			$lwtv_ty_nation_url = $lwtv_ty_nation_link;
		}
	}
}

$ty_standouts = array(
	array(
		'family' => 'pink',
		'icon'   => 'star.svg',
		'kicker' => __( 'Biggest premiere', 'lwtv' ),
		'title'  => $highlight_premiere_title,
		'desc'   => $highlight_premiere_desc,
		'url'    => $biggest_premiere ? $biggest_premiere['url'] : '',
	),
	array(
		'family' => 'blue',
		'icon'   => 'globe.svg',
		'kicker' => __( 'Leading nation', 'lwtv' ),
		'title'  => $highlight_nation_title,
		'desc'   => $highlight_nation_desc,
		'url'    => $leading_nation ? $lwtv_ty_nation_url : '',
	),
);

if ( 0 === $dead ) {
	$ty_standouts[] = array(
		'family' => 'green',
		'icon'   => 'heart.svg',
		'kicker' => __( 'The good news', 'lwtv' ),
		'title'  => __( 'Nobody died ... yet', 'lwtv' ),
		'desc'   => __( 'No queer character deaths recorded so far this year. Long may it last.', 'lwtv' ),
	);
} elseif ( $lwtv_ty_lost_winner ) {
	$lwtv_ty_lost_show   = ( $lwtv_ty_lost_winner['show_id'] ) ? get_the_title( (int) $lwtv_ty_lost_winner['show_id'] ) : '';
	$lwtv_ty_lost_tenure = max( 1, (int) $lwtv_ty_lost_winner['years'] );

	if ( '' !== $lwtv_ty_lost_show ) {
		$lwtv_ty_lost_desc = sprintf(
			/* translators: 1: first-appearance year, 2: number of years on air, 3: show name. */
			_n(
				'On air since %1$s on %3$s (for %2$s year).',
				'On air since %1$s on %3$s (for a total of %2$s years), the longest tenure of anyone we lost this year.',
				$lwtv_ty_lost_tenure,
				'lwtv'
			),
			(string) $lwtv_ty_lost_winner['first_year'],
			number_format_i18n( $lwtv_ty_lost_tenure ),
			$lwtv_ty_lost_show
		);
	} else {
		$lwtv_ty_lost_desc = sprintf(
			/* translators: 1: first-appearance year, 2: number of years on air. */
			_n(
				'On air since %1$s (%2$s year).',
				'On air since %1$s (%2$s years), the longest tenure of anyone we lost this year.',
				$lwtv_ty_lost_tenure,
				'lwtv'
			),
			(string) $lwtv_ty_lost_winner['first_year'],
			number_format_i18n( $lwtv_ty_lost_tenure )
		);
	}

	$ty_standouts[] = array(
		'family' => 'red',
		'icon'   => 'heart.svg',
		'kicker' => __( 'Longest-running character we lost', 'lwtv' ),
		'title'  => $lwtv_ty_lost_winner['name'],
		'desc'   => $lwtv_ty_lost_desc,
		'url'    => $lwtv_ty_lost_winner['url'] ?? '',
	);
}
// Otherwise: deaths occurred but no first-appearance data resolved — the row is
// intentionally dropped rather than reinstating an unexplained pick.

// Flatten shows-by-name once: a name→url map (for the ensemble link) and the
// start/finish rows the "longest run ended" selector needs.
$lwtv_ty_show_rows        = array();
$lwtv_ty_show_url_by_name = array();
foreach ( $shows_by_name as $lwtv_ty_marker_group ) {
	foreach ( $lwtv_ty_marker_group as $lwtv_ty_show_entry ) {
		$lwtv_ty_show_rows[]                                     = array(
			'name'   => $lwtv_ty_show_entry['name'],
			'url'    => $lwtv_ty_show_entry['url'],
			'start'  => $lwtv_ty_show_entry['airdates']['start'] ?? '',
			'finish' => $lwtv_ty_show_entry['airdates']['finish'] ?? '',
		);
		$lwtv_ty_show_url_by_name[ $lwtv_ty_show_entry['name'] ] = $lwtv_ty_show_entry['url'];
	}
}

// 4. Biggest ensemble: the on-air show with the largest queer cast.
$lwtv_ty_ensemble = Standouts::busiest( $char_counts_by_show );
if ( $lwtv_ty_ensemble ) {
	$ty_standouts[] = array(
		'family'  => 'green',
		'icon'    => 'group.svg',
		'icon_id' => 'svg-users',
		'kicker'  => __( 'Biggest ensemble', 'lwtv' ),
		'title'   => $lwtv_ty_ensemble['key'],
		'url'     => $lwtv_ty_show_url_by_name[ $lwtv_ty_ensemble['key'] ] ?? '',
		'desc'    => sprintf(
			/* translators: %s: number of queer characters. */
			_n(
				'%s queer character on air at once.',
				'%s queer characters on air at once, the largest cast of any show this year.',
				$lwtv_ty_ensemble['count'],
				'lwtv'
			),
			number_format_i18n( $lwtv_ty_ensemble['count'] )
		),
	);
}

// 5. Ended this year: the longest-running show whose run finished this year.
// Unlike "oldest show still on air" (perennially the same title), this is a
// genuine standout of the year; it drops when nothing ended.
$lwtv_ty_ended = Standouts::longest_run_ended( $lwtv_ty_show_rows, (int) $this_year );
if ( $lwtv_ty_ended ) {
	$ty_standouts[] = array(
		'family' => 'amber',
		'icon'   => 'calendar-alt.svg',
		'kicker' => __( 'Ended this year', 'lwtv' ),
		'title'  => $lwtv_ty_ended['name'],
		'url'    => $lwtv_ty_ended['url'] ?? '',
		'desc'   => sprintf(
			/* translators: 1: first air year, 2: final air year, 3: number of years the show ran. */
			_n(
				'On air %1$s-%2$s, at %3$s year it was the longest-running show to end this year.',
				'On air %1$s–%2$s, after %3$s years it was the longest-running show to end this year.',
				$lwtv_ty_ended['years'],
				'lwtv'
			),
			(string) $lwtv_ty_ended['start_year'],
			(string) $lwtv_ty_ended['end_year'],
			number_format_i18n( $lwtv_ty_ended['years'] )
		),
	);
}

// 6. Busiest actor: the actor who played the most queer roles on air this year.
$lwtv_ty_actor_counts = ( new Characters_Builder() )->get_actor_counts_for_year( (int) $this_year );
$lwtv_ty_busiest      = Standouts::busiest( $lwtv_ty_actor_counts );
if ( $lwtv_ty_busiest && $lwtv_ty_busiest['count'] >= 2 ) {
	$lwtv_ty_actor_name = get_the_title( (int) $lwtv_ty_busiest['key'] );
	if ( '' !== $lwtv_ty_actor_name ) {
		$ty_standouts[] = array(
			'family' => 'pink',
			'icon'   => 'user.svg',
			'kicker' => __( 'Busiest actor', 'lwtv' ),
			'title'  => $lwtv_ty_actor_name,
			'url'    => (string) get_permalink( (int) $lwtv_ty_busiest['key'] ),
			'desc'   => sprintf(
				/* translators: %s: number of queer roles played on air this year. */
				_n(
					'Playing %s queer role on air this year.',
					'Playing %s queer roles on air this year.',
					$lwtv_ty_busiest['count'],
					'lwtv'
				),
				number_format_i18n( $lwtv_ty_busiest['count'] )
			),
		);
	}
}

// ---- Shows lifecycle bar: New / Continuing / Canceled as a share of the year's shows. ----
// Continuing = every on-air show that neither started nor ended this year.
$lwtv_ty_show_steady   = max( 0, $soa - $new - $canceled );
$lwtv_ty_show_denom    = max( 1, $soa );
$lwtv_ty_show_segments = array(
	array(
		'key'   => 'new',
		'label' => __( 'New', 'lwtv' ),
		'count' => $new,
	),
	array(
		'key'   => 'steady',
		'label' => __( 'Continuing', 'lwtv' ),
		'count' => $lwtv_ty_show_steady,
	),
	array(
		'key'   => 'canceled',
		'label' => __( 'Canceled', 'lwtv' ),
		'count' => $canceled,
	),
);
$lwtv_ty_lifebar_aria  = sprintf(
	/* translators: 1: new shows, 2: continuing shows, 3: canceled shows, 4: total shows. */
	__( 'Of %4$s shows this year: %1$s new, %2$s continuing, %3$s canceled.', 'lwtv' ),
	number_format_i18n( $new ),
	number_format_i18n( $lwtv_ty_show_steady ),
	number_format_i18n( $canceled ),
	number_format_i18n( $soa )
);

// ---- Deaths strip: this year's deaths bucketed onto a twelve-month timeline. ----
// The in-progress treatment (elapsed shading + "today" rule) applies to the
// current year only; settled years read as a full, elapsed timeline.
$lwtv_ty_is_current   = ( $this_year === $current_year );
$lwtv_ty_deaths_strip = Deaths_Strip::build( $dead_by_date_ov, $lwtv_ty_is_current, (int) gmdate( 'n' ) );
$lwtv_ty_deaths_url   = home_url( $ty_baseurl . 'dead-characters/' );

/**
 * Render a numbered chapter heading: a pink chapter number, the title, a 1px
 * rule filling the remaining width, and an optional right-aligned hint.
 *
 * @param string $num   Two-digit chapter number (e.g. '01').
 * @param string $title Chapter title.
 * @param string $hint  Optional right-aligned hint text.
 * @param string $id    Optional id for the heading, so a nav can link to it.
 * @return void
 */
$lwtv_ty_chapter = function ( $num, $title, $hint = '', $id = '' ) {
	?>
	<div class="lwtv-ty-chapter"<?php echo $id ? ' id="' . esc_attr( $id ) . '"' : ''; ?>>
		<span class="lwtv-ty-chapter-num"><?php echo esc_html( $num ); ?></span>
		<h2 class="lwtv-ty-chapter-title"><?php echo esc_html( $title ); ?></h2>
		<span class="lwtv-ty-chapter-rule" aria-hidden="true"></span>
		<?php if ( '' !== $hint ) : ?>
			<span class="lwtv-ty-chapter-hint"><?php echo esc_html( $hint ); ?></span>
		<?php endif; ?>
	</div>
	<?php
};

// ---- Chapter 02 "Where it came from": origin, format, and role breakdowns. ----
// All derived from the full on-air set already built by Display::make().
$lwtv_ty_origin  = Breakdowns::origin( $shows_by_country, 5 );
$lwtv_ty_formats = Breakdowns::formats( $shows_by_format );
$lwtv_ty_roles   = Breakdowns::roles( $characters_on_air );

$lwtv_ty_role_labels = array(
	'regular'   => __( 'Regular', 'lwtv' ),
	'recurring' => __( 'Recurring', 'lwtv' ),
	'guest'     => __( 'Guest', 'lwtv' ),
);

/**
 * Render a horizontal bar list. Each row is [ 'name', 'count' ]; bar widths are
 * relative to the largest count in the set.
 *
 * @param array  $rows   List of [ 'name' => string, 'count' => int ].
 * @param string $family Colour family for the fill ('blue' or 'pink').
 * @return void
 */
$lwtv_ty_bars = function ( array $rows, $family = 'blue' ) {
	// Bars show each row's share of the whole, so the widths are comparable
	// across the whole set rather than relative to whichever row is largest.
	$total = 0;
	foreach ( $rows as $row ) {
		$total += (int) $row['count'];
	}
	$total = max( 1, $total );

	echo '<div class="lwtv-ty-bars">';
	foreach ( $rows as $row ) {
		$pct = round( (int) $row['count'] / $total * 100, 1 );
		printf(
			'<div class="lwtv-ty-bar-row"><span class="lwtv-ty-bar-label" title="%1$s">%2$s</span><span class="lwtv-ty-bar-track"><span class="lwtv-ty-bar-fill lwtv-ty-bar-fill--%3$s" style="width:%4$s%%"></span></span><span class="lwtv-ty-bar-count">%5$s</span></div>',
			esc_attr( $row['name'] ),
			esc_html( $row['name'] ),
			esc_attr( $family ),
			esc_attr( $pct ),
			esc_html( number_format_i18n( (int) $row['count'] ) )
		);
	}
	echo '</div>';
};

// Origin rows: the top countries, plus an aggregated "Other" row when present.
$lwtv_ty_origin_rows = $lwtv_ty_origin['top'];
if ( $lwtv_ty_origin['other'] > 0 ) {
	$lwtv_ty_origin_rows[] = array(
		'name'  => __( 'Other', 'lwtv' ),
		'count' => $lwtv_ty_origin['other'],
	);
}

// Role rows: map the tracked keys to their display labels.
$lwtv_ty_role_rows = array();
foreach ( $lwtv_ty_roles as $lwtv_ty_role ) {
	$lwtv_ty_role_rows[] = array(
		'name'  => $lwtv_ty_role_labels[ $lwtv_ty_role['key'] ] ?? $lwtv_ty_role['key'],
		'count' => $lwtv_ty_role['count'],
	);
}

// New Shows panel: this year's premieres tallied by format.
$lwtv_ty_new_format_rows = Breakdowns::formats(
	( new New_Shows_Formatter() )->format_by_format_for_year( (string) $this_year, $shows_by_format )
);

// Canceled Shows panel: the longest runs that ended this year (top six),
// as name → run-length rows for the bar list.
$lwtv_ty_canceled_rows = array();
foreach ( array_slice( Standouts::runs_ended( $lwtv_ty_show_rows, (int) $this_year ), 0, 6 ) as $lwtv_ty_run ) {
	$lwtv_ty_canceled_rows[] = array(
		'name'  => $lwtv_ty_run['name'],
		'count' => $lwtv_ty_run['years'],
	);
}

// Characters On Air panel extras: multi-show / debuting / non-binary counts.
$lwtv_ty_char_extras    = ( new Characters_Builder() )->get_character_extras_for_year( (int) $this_year );
$lwtv_ty_char_fact_rows = array(
	array(
		'label' => __( 'In two or more shows', 'lwtv' ),
		'count' => $lwtv_ty_char_extras['multi_show'],
	),
	array(
		'label' => __( 'Debuting this year', 'lwtv' ),
		'count' => $lwtv_ty_char_extras['debuting'],
	),
	array(
		'label' => __( 'Non-binary characters', 'lwtv' ),
		'count' => $lwtv_ty_char_extras['non_binary'],
	),
);

// ---- Chapter 01 drill-in: eleven-year trend per metric. ----
// The compact count map is warmed for the current year; past-year pages compute
// it once and cache it (stable data, low traffic) rather than on every load.
$lwtv_ty_trends = lwtv_plugin()->get_transient( Trends::cache_key( (int) $this_year ) );
if ( false === $lwtv_ty_trends ) {
	$lwtv_ty_trends = Trends::to_count_map( ( new This_Year_JSON() )->ten_years( (int) $this_year ) );
	lwtv_plugin()->set_transient( Trends::cache_key( (int) $this_year ), $lwtv_ty_trends, DAY_IN_SECONDS );
}

/**
 * Render the eleven-year column chart for one metric by reusing the shared
 * year-bars partial, wrapped in a family colour class.
 *
 * @param string $trend_key One of characters|dead|shows|started|canceled.
 * @param string $family    Colour family (green|red|blue|pink|amber).
 * @param string $headline  Chart headline.
 * @return void
 */
$lwtv_ty_yearbars = function ( $trend_key, $family, $headline ) use ( $lwtv_ty_trends, $this_year ) {
	$rows       = array();
	$peak_year  = 0;
	$peak_count = 0;
	$now_count  = 0;
	foreach ( $lwtv_ty_trends as $lwtv_ty_y => $lwtv_ty_counts ) {
		$lwtv_ty_c = (int) ( $lwtv_ty_counts[ $trend_key ] ?? 0 );
		$rows[]    = array(
			'year'  => (int) $lwtv_ty_y,
			'count' => $lwtv_ty_c,
		);
		if ( $lwtv_ty_c >= $peak_count ) {
			$peak_count = $lwtv_ty_c;
			$peak_year  = (int) $lwtv_ty_y;
		}
		if ( (int) $lwtv_ty_y === (int) $this_year ) {
			$now_count = $lwtv_ty_c;
		}
	}

	$eyebrow = '';
	if ( ! empty( $rows ) ) {
		$eyebrow = $rows[0]['year'] . '–' . $rows[ count( $rows ) - 1 ]['year'];
	}

	$yearbars = array(
		'rows'       => $rows,
		'peak_year'  => $peak_year,
		'peak_count' => $peak_count,
		'eyebrow'    => $eyebrow,
		'headline'   => $headline,
		'stat_num'   => $now_count,
		/* translators: %s: the year being reviewed. */
		'stat_sub'   => sprintf( __( 'in %s', 'lwtv' ), (string) $this_year ),
	);

	echo '<div class="lwtv-yearbars--' . esc_attr( $family ) . '">';
	include LWTV_PLUGIN_PATH . '/php/statistics/templates/partials/year-bars.php';
	echo '</div>';
};
?>

<div class="lwtv-ty-lead<?php echo ( '' !== $lead_media ) ? ' lwtv-ty-lead--media' : ''; ?>">
	<div class="lwtv-ty-lead-body">
		<p class="lwtv-stats-eyebrow"><?php /* translators: %s: the year being reviewed. */ printf( esc_html__( '%s in review', 'lwtv' ), esc_html( (string) $this_year ) ); ?></p>
		<p class="lwtv-ty-lead-stat"><?php echo esc_html( $lead_stat ); ?></p>
		<p class="lwtv-ty-lead-narrative"><?php echo esc_html( $narrative ); ?></p>

		<div class="btn-group" role="group" aria-label="Section Navigation">
			<a class="btn btn-primary" href="#shape-of-the-year">The shape of the year</a>
			<a class="btn btn-primary" href="#where-it-came-from">Where it came from</a>
			<a class="btn btn-primary" href="#standouts">Standouts</a>
		</div>
	</div>
	<?php if ( '' !== $lead_media ) : ?>
		<figure class="lwtv-ty-lead-media">
			<a href="<?php echo esc_url( $biggest_premiere['url'] ); ?>">
				<?php echo $lead_media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_the_post_thumbnail() returns safe markup. ?>
			</a>
			<figcaption class="lwtv-ty-lead-media-cap">
				<?php
				echo esc_html( $biggest_premiere['name'] );
				?>
			</figcaption>
		</figure>
	<?php endif; ?>
</div>

<?php
// Every metric tile is a pill tab that swaps the drill-in panel below.
$ty_panel_keys   = array( 'coa', 'dead', 'soa', 'new', 'canceled' );
$ty_has_panels   = ! empty( array_intersect( $ty_panel_keys, array_column( $ty_metrics, 'key' ) ) );
$ty_active_shown = false;
$lwtv_ty_chapter( '01', __( 'The shape of the year', 'lwtv' ), $ty_has_panels ? __( 'Click a metric to explore it', 'lwtv' ) : '', 'shape-of-the-year' );
?>

<div class="lwtv-ty-ribbon"<?php echo $ty_has_panels ? ' role="tablist"' : ''; ?>>
	<?php
	foreach ( $ty_metrics as $ty_metric ) :
		$ty_metric_delta = $ty_delta_text( $ty_metric['count'], $ty_metric['prev'] );
		$ty_is_tab       = in_array( $ty_metric['key'], $ty_panel_keys, true );
		$ty_is_active    = ( $ty_is_tab && ! $ty_active_shown );
		if ( $ty_is_active ) {
			$ty_active_shown = true;
		}
		$ty_tile_class = 'lwtv-ty-metric lwtv-ty-metric--' . $ty_metric['family'] . ( $ty_is_active ? ' active' : '' );
		?>
		<?php if ( $ty_is_tab ) : ?>
			<a class="<?php echo esc_attr( $ty_tile_class ); ?>" id="ty-tab-<?php echo esc_attr( $ty_metric['key'] ); ?>" href="#ty-panel-<?php echo esc_attr( $ty_metric['key'] ); ?>" data-bs-toggle="pill" role="tab" aria-controls="ty-panel-<?php echo esc_attr( $ty_metric['key'] ); ?>" aria-selected="<?php echo $ty_is_active ? 'true' : 'false'; ?>">
				<span class="lwtv-ty-metric-label"><?php echo esc_html( $ty_metric['label'] ); ?></span>
				<span class="lwtv-ty-metric-num" data-count-to="<?php echo (int) $ty_metric['count']; ?>"><?php echo esc_html( number_format_i18n( (int) $ty_metric['count'] ) ); ?></span>
				<span class="lwtv-ty-metric-delta"><?php echo esc_html( $ty_metric_delta ); ?></span>
			</a>
		<?php else : ?>
			<div class="<?php echo esc_attr( $ty_tile_class ); ?>">
				<span class="lwtv-ty-metric-label"><?php echo esc_html( $ty_metric['label'] ); ?></span>
				<span class="lwtv-ty-metric-num" data-count-to="<?php echo (int) $ty_metric['count']; ?>"><?php echo esc_html( number_format_i18n( (int) $ty_metric['count'] ) ); ?></span>
				<span class="lwtv-ty-metric-delta"><?php echo esc_html( $ty_metric_delta ); ?></span>
			</div>
		<?php endif; ?>
	<?php endforeach; ?>
</div>

<?php if ( $soa > 0 ) : ?>
<div class="lwtv-ty-lifebar">
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section">
		<?php
		/* translators: %s: total number of shows on air this year. */
		printf( esc_html( _n( '%s show this year', '%s shows this year', $soa, 'lwtv' ) ), esc_html( number_format_i18n( $soa ) ) );
		?>
	</p>
	<div class="lwtv-ty-lifebar-track" role="img" aria-label="<?php echo esc_attr( $lwtv_ty_lifebar_aria ); ?>">
		<?php
		foreach ( $lwtv_ty_show_segments as $lwtv_ty_seg ) :
			if ( $lwtv_ty_seg['count'] <= 0 ) {
				continue;
			}
			$lwtv_ty_seg_pct = round( $lwtv_ty_seg['count'] / $lwtv_ty_show_denom * 100, 1 );
			?>
			<div class="lwtv-ty-lifebar-seg lwtv-ty-lifebar-seg--<?php echo esc_attr( $lwtv_ty_seg['key'] ); ?>" style="width:<?php echo esc_attr( $lwtv_ty_seg_pct ); ?>%">
				<?php if ( $lwtv_ty_seg_pct >= 8 ) : ?>
					<span class="lwtv-ty-lifebar-seg-pct"><?php echo esc_html( round( $lwtv_ty_seg_pct ) . '%' ); ?></span>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<div class="lwtv-ty-lifebar-legend">
		<?php foreach ( $lwtv_ty_show_segments as $lwtv_ty_seg ) : ?>
			<span class="lwtv-ty-lifebar-legend-item">
				<span class="lwtv-ty-lifebar-dot lwtv-ty-lifebar-dot--<?php echo esc_attr( $lwtv_ty_seg['key'] ); ?>"></span>
				<?php echo esc_html( $lwtv_ty_seg['label'] ); ?>
				<span class="lwtv-ty-lifebar-legend-count"><?php echo esc_html( number_format_i18n( (int) $lwtv_ty_seg['count'] ) ); ?></span>
			</span>
		<?php endforeach; ?>
	</div>
</div>
<?php endif; ?>

<?php if ( $ty_has_panels ) : ?>
<div class="tab-content lwtv-ty-panels">
	<!-- Characters On Air -->
	<div class="tab-pane fade show active lwtv-ty-panel" id="ty-panel-coa" role="tabpanel" aria-labelledby="ty-tab-coa" tabindex="0">
		<div class="lwtv-ty-panel-grid">
			<div class="lwtv-ty-panel-chart">
				<?php $lwtv_ty_yearbars( 'characters', 'green', __( 'Characters on air', 'lwtv' ) ); ?>
			</div>
			<div class="lwtv-ty-panel-side">
				<p class="lwtv-ty-where-eyebrow"><?php esc_html_e( 'By role type', 'lwtv' ); ?></p>
				<?php $lwtv_ty_bars( $lwtv_ty_role_rows, 'green' ); ?>
				<ul class="lwtv-ty-panel-facts">
					<?php foreach ( $lwtv_ty_char_fact_rows as $lwtv_ty_fact ) : ?>
						<li class="lwtv-ty-panel-fact">
							<span><?php echo esc_html( $lwtv_ty_fact['label'] ); ?></span>
							<span class="lwtv-ty-panel-fact-num"><?php echo esc_html( number_format_i18n( (int) $lwtv_ty_fact['count'] ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<a class="lwtv-ty-where-link" href="<?php echo esc_url( home_url( $ty_baseurl . 'characters-on-air/' ) ); ?>"><?php esc_html_e( 'All characters on air', 'lwtv' ); ?> &rarr;</a>
			</div>
		</div>
	</div>

	<!-- Dead Characters -->
	<div class="tab-pane fade lwtv-ty-panel" id="ty-panel-dead" role="tabpanel" aria-labelledby="ty-tab-dead" tabindex="0">
		<div class="lwtv-ty-panel-grid">
			<div class="lwtv-ty-panel-chart">
				<?php $lwtv_ty_yearbars( 'dead', 'red', __( 'Characters we lost', 'lwtv' ) ); ?>
			</div>
			<div class="lwtv-ty-panel-side">
				<p class="lwtv-ty-where-eyebrow"><?php esc_html_e( 'Who we lost', 'lwtv' ); ?></p>
				<?php if ( ! empty( $dead_by_date_ov ) ) : ?>
					<ul class="lwtv-ty-panel-deaths">
						<?php
						$lwtv_ty_dead_shown = 0;
						foreach ( $dead_by_date_ov as $lwtv_ty_dead_date => $lwtv_ty_dead_group ) :
							$lwtv_ty_dead_stamp = strtotime( (string) $lwtv_ty_dead_date );
							foreach ( $lwtv_ty_dead_group as $lwtv_ty_dead_one ) :
								if ( $lwtv_ty_dead_shown >= 10 ) {
									break 2; // Cap the list at 10; the link below leads to the rest.
								}
								++$lwtv_ty_dead_shown;
								$lwtv_ty_dead_post = get_page_by_path( $lwtv_ty_dead_one['slug'], OBJECT, 'post_type_characters' );
								$lwtv_ty_dead_link = $lwtv_ty_dead_post ? get_permalink( $lwtv_ty_dead_post->ID ) : '';
								?>
								<li class="lwtv-ty-panel-death">
									<?php if ( $lwtv_ty_dead_link ) : ?>
										<a href="<?php echo esc_url( $lwtv_ty_dead_link ); ?>"><?php echo esc_html( $lwtv_ty_dead_one['name'] ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $lwtv_ty_dead_one['name'] ); ?>
									<?php endif; ?>
									<span class="lwtv-ty-panel-death-date"><?php echo esc_html( $lwtv_ty_dead_stamp ? date_i18n( 'M j', $lwtv_ty_dead_stamp ) : '' ); ?></span>
								</li>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="lwtv-ty-where-empty"><?php esc_html_e( 'No deaths recorded this year.', 'lwtv' ); ?></p>
				<?php endif; ?>
				<a class="lwtv-ty-where-link" href="<?php echo esc_url( $lwtv_ty_deaths_url ); ?>"><?php esc_html_e( 'All the characters we lost', 'lwtv' ); ?> &rarr;</a>
			</div>
		</div>
	</div>

	<!-- Shows On Air -->
	<div class="tab-pane fade lwtv-ty-panel" id="ty-panel-soa" role="tabpanel" aria-labelledby="ty-tab-soa" tabindex="0">
		<div class="lwtv-ty-panel-grid">
			<div class="lwtv-ty-panel-chart">
				<?php $lwtv_ty_yearbars( 'shows', 'blue', __( 'Shows on air', 'lwtv' ) ); ?>
			</div>
			<div class="lwtv-ty-panel-side">
				<p class="lwtv-ty-where-eyebrow"><?php esc_html_e( 'By country of origin', 'lwtv' ); ?></p>
				<?php if ( ! empty( $lwtv_ty_origin_rows ) ) : ?>
					<?php $lwtv_ty_bars( $lwtv_ty_origin_rows, 'blue' ); ?>
				<?php else : ?>
					<p class="lwtv-ty-where-empty"><?php esc_html_e( 'No shows to rank by country yet this year.', 'lwtv' ); ?></p>
				<?php endif; ?>
				<a class="lwtv-ty-where-link" href="<?php echo esc_url( home_url( $ty_baseurl . 'shows-on-air/' ) ); ?>"><?php esc_html_e( 'All shows on air', 'lwtv' ); ?> &rarr;</a>
			</div>
		</div>
	</div>

	<!-- New Shows -->
	<div class="tab-pane fade lwtv-ty-panel" id="ty-panel-new" role="tabpanel" aria-labelledby="ty-tab-new" tabindex="0">
		<div class="lwtv-ty-panel-grid">
			<div class="lwtv-ty-panel-chart">
				<?php $lwtv_ty_yearbars( 'started', 'pink', __( 'New shows', 'lwtv' ) ); ?>
			</div>
			<div class="lwtv-ty-panel-side">
				<p class="lwtv-ty-where-eyebrow"><?php esc_html_e( 'By format', 'lwtv' ); ?></p>
				<?php if ( ! empty( $lwtv_ty_new_format_rows ) ) : ?>
					<?php $lwtv_ty_bars( $lwtv_ty_new_format_rows, 'pink' ); ?>
				<?php else : ?>
					<p class="lwtv-ty-where-empty"><?php esc_html_e( 'No new shows to break down by format yet this year.', 'lwtv' ); ?></p>
				<?php endif; ?>
				<a class="lwtv-ty-where-link" href="<?php echo esc_url( home_url( $ty_baseurl . 'new-shows/' ) ); ?>"><?php esc_html_e( 'All new shows', 'lwtv' ); ?> &rarr;</a>
			</div>
		</div>
	</div>

	<!-- Canceled Shows -->
	<div class="tab-pane fade lwtv-ty-panel" id="ty-panel-canceled" role="tabpanel" aria-labelledby="ty-tab-canceled" tabindex="0">
		<div class="lwtv-ty-panel-grid">
			<div class="lwtv-ty-panel-chart">
				<?php $lwtv_ty_yearbars( 'canceled', 'amber', __( 'Shows that ended', 'lwtv' ) ); ?>
			</div>
			<div class="lwtv-ty-panel-side">
				<p class="lwtv-ty-where-eyebrow"><?php esc_html_e( 'Longest runs that ended', 'lwtv' ); ?></p>
				<?php if ( ! empty( $lwtv_ty_canceled_rows ) ) : ?>
					<?php $lwtv_ty_bars( $lwtv_ty_canceled_rows, 'amber' ); ?>
				<?php else : ?>
					<p class="lwtv-ty-where-empty"><?php esc_html_e( 'No shows have ended yet this year.', 'lwtv' ); ?></p>
				<?php endif; ?>
				<a class="lwtv-ty-where-link" href="<?php echo esc_url( home_url( $ty_baseurl . 'canceled-shows/' ) ); ?>"><?php esc_html_e( 'All canceled shows', 'lwtv' ); ?> &rarr;</a>
			</div>
		</div>
	</div>
</div>
<?php endif; ?>

<?php if ( $lwtv_ty_deaths_strip['total'] > 0 ) : ?>
<div class="lwtv-ty-deaths">
	<div class="lwtv-ty-deaths-head">
		<p class="lwtv-ty-deaths-title">
			<?php
			printf(
				/* translators: %s: number of characters lost this year. */
				esc_html( _n( 'The %s we lost', 'The %s we lost', (int) $lwtv_ty_deaths_strip['total'], 'lwtv' ) ),
				esc_html( number_format_i18n( (int) $lwtv_ty_deaths_strip['total'] ) )
			);
			?>
		</p>
		<a class="lwtv-ty-deaths-link" href="<?php echo esc_url( home_url( '/statistics/death/' ) ); ?>"><?php esc_html_e( 'Full death statistics', 'lwtv' ); ?> &rarr;</a>
	</div>
	<p class="lwtv-ty-deaths-sub"><?php esc_html_e( 'Placed on the calendar year, grouped by month.', 'lwtv' ); ?></p>
	<div class="lwtv-ty-deaths-strip<?php echo $lwtv_ty_deaths_strip['is_current_year'] ? ' is-current' : ''; ?>"<?php echo $lwtv_ty_deaths_strip['is_current_year'] ? ' style="--lwtv-ty-elapsed:' . esc_attr( $lwtv_ty_deaths_strip['elapsed_pct'] ) . '%"' : ''; ?>>
		<?php if ( $lwtv_ty_deaths_strip['is_current_year'] ) : ?>
			<span class="lwtv-ty-deaths-elapsed" aria-hidden="true"></span>
			<span class="lwtv-ty-deaths-today" aria-hidden="true"><?php esc_html_e( 'today', 'lwtv' ); ?></span>
		<?php endif; ?>
		<div class="lwtv-ty-deaths-months">
			<?php foreach ( $lwtv_ty_deaths_strip['months'] as $lwtv_ty_dm ) : ?>
				<?php
				$lwtv_ty_dm_ts    = mktime( 0, 0, 0, (int) $lwtv_ty_dm['month'], 1, (int) $this_year );
				$lwtv_ty_dm_month = date_i18n( 'F', $lwtv_ty_dm_ts );
				$lwtv_ty_dm_label = ( 0 === $lwtv_ty_dm['count'] )
					/* translators: %s: month name. */
					? sprintf( __( 'No deaths in %s', 'lwtv' ), $lwtv_ty_dm_month )
					: sprintf(
						/* translators: 1: number of deaths, 2: month name. */
						_n( '%1$s death in %2$s', '%1$s deaths in %2$s', (int) $lwtv_ty_dm['count'], 'lwtv' ),
						number_format_i18n( (int) $lwtv_ty_dm['count'] ),
						$lwtv_ty_dm_month
					);
				$lwtv_ty_dm_variant = $lwtv_ty_dm['show_count'] ? 'count' : ( $lwtv_ty_dm['is_single'] ? 'single' : 'empty' );
				?>
				<div class="lwtv-ty-deaths-month">
					<span class="lwtv-ty-deaths-marker-wrap">
						<?php if ( $lwtv_ty_dm['count'] > 0 ) : ?>
							<a class="lwtv-ty-deaths-marker lwtv-ty-deaths-marker--<?php echo esc_attr( $lwtv_ty_dm_variant ); ?>" href="<?php echo esc_url( $lwtv_ty_deaths_url ); ?>" style="--lwtv-ty-marker-size:<?php echo (int) $lwtv_ty_dm['size']; ?>px" title="<?php echo esc_attr( $lwtv_ty_dm_label ); ?>" aria-label="<?php echo esc_attr( $lwtv_ty_dm_label ); ?>"><?php echo $lwtv_ty_dm['show_count'] ? esc_html( number_format_i18n( (int) $lwtv_ty_dm['count'] ) ) : ''; ?></a>
						<?php else : ?>
							<span class="lwtv-ty-deaths-marker lwtv-ty-deaths-marker--empty" style="--lwtv-ty-marker-size:<?php echo (int) $lwtv_ty_dm['size']; ?>px" title="<?php echo esc_attr( $lwtv_ty_dm_label ); ?>"></span>
						<?php endif; ?>
					</span>
					<span class="lwtv-ty-deaths-month-initial" aria-hidden="true"><?php echo esc_html( mb_substr( $lwtv_ty_dm_month, 0, 1 ) ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
<?php endif; ?>

<?php $lwtv_ty_chapter( '02', __( 'Where it came from', 'lwtv' ), '', 'where-it-came-from' ); ?>
<div class="lwtv-ty-where">
	<div class="lwtv-ty-where-card">
		<p class="lwtv-ty-where-title"><?php esc_html_e( 'Where the shows come from', 'lwtv' ); ?></p>
		<p class="lwtv-ty-where-sub">
			<?php
			printf(
				/* translators: %s: total number of shows on air this year. */
				esc_html( _n( 'All %s show on air, by country of origin.', 'All %s shows on air, by country of origin.', $soa, 'lwtv' ) ),
				esc_html( number_format_i18n( $soa ) )
			);
			?>
		</p>
		<?php if ( ! empty( $lwtv_ty_origin_rows ) ) : ?>
			<?php $lwtv_ty_bars( $lwtv_ty_origin_rows, 'blue' ); ?>
			<a class="lwtv-ty-where-link" href="<?php echo esc_url( home_url( '/statistics/nations/' ) ); ?>"><?php esc_html_e( 'All countries', 'lwtv' ); ?> &rarr;</a>
		<?php else : ?>
			<p class="lwtv-ty-where-empty"><?php esc_html_e( 'No shows to rank by country yet this year.', 'lwtv' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="lwtv-ty-where-card">
		<p class="lwtv-ty-where-title"><?php esc_html_e( 'Formats and roles', 'lwtv' ); ?></p>
		<p class="lwtv-ty-where-sub"><?php esc_html_e( 'What kind of shows, and how central the characters are in them.', 'lwtv' ); ?></p>

		<p class="lwtv-ty-where-eyebrow"><?php esc_html_e( 'Formats', 'lwtv' ); ?></p>
		<?php if ( ! empty( $lwtv_ty_formats ) ) : ?>
			<?php $lwtv_ty_bars( $lwtv_ty_formats, 'blue' ); ?>
		<?php else : ?>
			<p class="lwtv-ty-where-empty"><?php esc_html_e( 'No formats to show yet.', 'lwtv' ); ?></p>
		<?php endif; ?>

		<p class="lwtv-ty-where-eyebrow"><?php esc_html_e( 'Roles', 'lwtv' ); ?></p>
		<?php $lwtv_ty_bars( $lwtv_ty_role_rows, 'pink' ); ?>
	</div>
</div>

<?php $lwtv_ty_chapter( '03', __( 'Standouts', 'lwtv' ), '', 'standouts' ); ?>
<div class="lwtv-ty-standouts">
	<?php foreach ( $ty_standouts as $ty_standout ) : ?>
		<?php $lwtv_ty_icon_id = $ty_standout['icon_id'] ?? ( 'svg-' . str_replace( '.svg', '', $ty_standout['icon'] ) ); ?>
		<div class="lwtv-ty-standout">
			<div class="lwtv-ty-standout-chip lwtv-ty-standout-chip--<?php echo esc_attr( $ty_standout['family'] ); ?>">
				<?php echo lwtv_plugin()->get_symbolicon( svg: $ty_standout['icon'], icon: $lwtv_ty_icon_id, max_size: '24' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<div class="lwtv-ty-standout-head">
				<p class="lwtv-ty-standout-kicker"><?php echo esc_html( $ty_standout['kicker'] ); ?></p>
				<p class="lwtv-ty-standout-title">
					<?php if ( ! empty( $ty_standout['url'] ) ) : ?>
						<a class="lwtv-ty-standout-link" href="<?php echo esc_url( $ty_standout['url'] ); ?>"><?php echo esc_html( $ty_standout['title'] ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $ty_standout['title'] ); ?>
					<?php endif; ?>
				</p>
			</div>
			<p class="lwtv-ty-standout-desc"><?php echo esc_html( $ty_standout['desc'] ); ?></p>
		</div>
	<?php endforeach; ?>
</div>
