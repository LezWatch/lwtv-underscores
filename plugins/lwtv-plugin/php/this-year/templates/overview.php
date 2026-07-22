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
 */

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
	),
	array(
		'label'  => __( 'Dead Characters', 'lwtv' ),
		'family' => 'red',
		'count'  => $dead,
		'prev'   => $prev_counts['dead'],
	),
	array(
		'label'  => __( 'Shows On Air', 'lwtv' ),
		'family' => 'blue',
		'count'  => $soa,
		'prev'   => $prev_counts['soa'],
	),
	array(
		'label'  => __( 'New Shows', 'lwtv' ),
		'family' => 'pink',
		'count'  => $new,
		'prev'   => $prev_counts['new'],
	),
	array(
		'label'  => __( 'Canceled Shows', 'lwtv' ),
		'family' => 'amber',
		'count'  => $canceled,
		'prev'   => $prev_counts['canceled'],
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

// 3. In memoriam: the most recent death (dead_by_date_ov is ascending; last key wins) + its show.
$memoriam_char       = null;
$memoriam_show_tally = array();
if ( ! empty( $dead_by_date_ov ) ) {
	foreach ( $dead_by_date_ov as $lwtv_ty_death_group ) {
		foreach ( $lwtv_ty_death_group as $lwtv_ty_dead_char ) {
			foreach ( $lwtv_ty_dead_char['shows'] as $lwtv_ty_dead_show ) {
				$memoriam_show_tally[ $lwtv_ty_dead_show['name'] ] = ( $memoriam_show_tally[ $lwtv_ty_dead_show['name'] ] ?? 0 ) + 1;
			}
		}
	}

	$lwtv_ty_death_dates = array_keys( $dead_by_date_ov );
	$lwtv_ty_last_date   = end( $lwtv_ty_death_dates );
	$lwtv_ty_last_group  = $dead_by_date_ov[ $lwtv_ty_last_date ];
	$memoriam_char       = $lwtv_ty_last_group[0] ?? null;
}

arsort( $memoriam_show_tally );
$top_memoriam_show       = array_key_first( $memoriam_show_tally );
$top_memoriam_show_count = $top_memoriam_show ? $memoriam_show_tally[ $top_memoriam_show ] : 0;

if ( 0 === $dead ) {
	$highlight_memoriam_family = 'green';
	$highlight_memoriam_kicker = __( 'The good news', 'lwtv' );
	$highlight_memoriam_title  = __( 'Nobody died ... yet', 'lwtv' );
	$highlight_memoriam_desc   = __( 'No queer character deaths recorded so far this year. Long may it last.', 'lwtv' );
} else {
	$highlight_memoriam_family = 'red';
	$highlight_memoriam_kicker = __( 'In memoriam', 'lwtv' );
	$highlight_memoriam_title  = $memoriam_char ? $memoriam_char['name'] : '—';

	$highlight_memoriam_desc = sprintf(
		/* translators: %s: number of characters who died this year. */
		_n( 'Only %s character died this year', 'A total of %s characters died this year', $dead, 'lwtv' ),
		number_format_i18n( $dead )
	);

	if ( $top_memoriam_show && $top_memoriam_show_count > 1 ) {
		$highlight_memoriam_desc .= sprintf(
			/* translators: 1: number of this year's deaths that happened on one show, 2: show name. */
			__( ', %1$s of them on %2$s.', 'lwtv' ),
			number_format_i18n( $top_memoriam_show_count ),
			$top_memoriam_show
		);
	} else {
		$highlight_memoriam_desc .= '.';
	}
}

$ty_highlights = array(
	array(
		'family'  => 'pink',
		'icon'    => 'star.svg',
		'kicker'  => __( 'Biggest premiere', 'lwtv' ),
		'title'   => $highlight_premiere_title,
		'desc'    => $highlight_premiere_desc,
		'post_id' => $biggest_premiere_id,
	),
	array(
		'family' => 'blue',
		'icon'   => 'globe.svg',
		'kicker' => __( 'Leading nation', 'lwtv' ),
		'title'  => $highlight_nation_title,
		'desc'   => $highlight_nation_desc,
	),
	array(
		'family' => $highlight_memoriam_family,
		'icon'   => 'heart.svg',
		'kicker' => $highlight_memoriam_kicker,
		'title'  => $highlight_memoriam_title,
		'desc'   => $highlight_memoriam_desc,
	),
);

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
?>

<div class="lwtv-ty-lead<?php echo ( '' !== $lead_media ) ? ' lwtv-ty-lead--media' : ''; ?>">
	<div class="lwtv-ty-lead-body">
		<p class="lwtv-stats-eyebrow"><?php /* translators: %s: the year being reviewed. */ printf( esc_html__( '%s in review', 'lwtv' ), esc_html( (string) $this_year ) ); ?></p>
		<p class="lwtv-ty-lead-stat"><?php echo esc_html( $lead_stat ); ?></p>
		<p class="lwtv-ty-lead-narrative"><?php echo esc_html( $narrative ); ?></p>
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

<div class="lwtv-ty-ribbon">
	<?php foreach ( $ty_metrics as $ty_metric ) : ?>
		<?php $ty_metric_delta = $ty_delta_text( $ty_metric['count'], $ty_metric['prev'] ); ?>
		<div class="lwtv-ty-metric lwtv-ty-metric--<?php echo esc_attr( $ty_metric['family'] ); ?>">
			<span class="lwtv-ty-metric-label"><?php echo esc_html( $ty_metric['label'] ); ?></span>
			<span class="lwtv-ty-metric-num" data-count-to="<?php echo (int) $ty_metric['count']; ?>"><?php echo esc_html( number_format_i18n( (int) $ty_metric['count'] ) ); ?></span>
			<span class="lwtv-ty-metric-delta"><?php echo esc_html( $ty_metric_delta ); ?></span>
		</div>
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

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Highlights of the year', 'lwtv' ); ?></p>
<div class="lwtv-ty-highlights">
	<?php foreach ( $ty_highlights as $ty_highlight ) : ?>
		<div class="lwtv-ty-highlight">
			<div class="lwtv-ty-highlight-chip lwtv-ty-highlight-chip--<?php echo esc_attr( $ty_highlight['family'] ); ?>">
				<?php echo lwtv_plugin()->get_symbolicon( svg: $ty_highlight['icon'], icon: 'svg-' . str_replace( '.svg', '', $ty_highlight['icon'] ), max_size: '30' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<p class="lwtv-ty-highlight-kicker"><?php echo esc_html( $ty_highlight['kicker'] ); ?></p>
			<p class="lwtv-ty-highlight-title"><?php echo esc_html( $ty_highlight['title'] ); ?></p>
			<br style="clear: both;" />
			<p class="lwtv-ty-highlight-desc"><?php echo esc_html( $ty_highlight['desc'] ); ?></p>
		</div>
	<?php endforeach; ?>
</div>
