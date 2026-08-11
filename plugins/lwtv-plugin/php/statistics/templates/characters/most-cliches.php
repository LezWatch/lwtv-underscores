<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Characters → Most: "The Records" — a five-column spotlight row (the #1
 * character in each category) followed by a Full Rankings table (ranks 1-5
 * across all five categories). Replaces the earlier five-stacked-panels
 * layout with the two-part design from the "Characters Most - Records"
 * handoff: same five data sources, same top-5 depth, laid out as spotlight +
 * table instead of five separate ranked-bars panels.
 *
 * Categories: most clichés (existing, trimmed from a top-25 list), most
 * shows (crossover/recurring-guest characters, distinct show count via the
 * lezchars_show_group repeater), most actors (recast characters, via the
 * lezchars_actor relationship field), most resurrected (2+ recorded deaths
 * via the lezchars_death_year repeater — can render fewer than 5 rows, or
 * none, if the data doesn't have that many repeat-deaths on record), and
 * longest-running (widest earliest-to-latest on-screen year span, via the
 * show-group repeater's own `appears` years).
 *
 * Every category can legitimately come back with fewer than 5 rows (Most
 * Resurrected and Longest-Running especially) — missing ranks render as an
 * em dash rather than fabricated data, both in the spotlight (falls back to
 * "No record yet") and the table.
 *
 * URL/view slug stays "most-cliches" (unchanged, avoids breaking existing
 * links); the on-page title and subnav label are "Most".
 *
 * @package LezWatch.TV
 *
 * @var int $character_count
 */

use LWTV\Statistics\Build\Cliche_Leaders as Build_Cliche_Leaders;
use LWTV\Statistics\Build\Character_Show_Leaders as Build_Character_Show_Leaders;
use LWTV\Statistics\Build\Character_Actor_Leaders as Build_Character_Actor_Leaders;
use LWTV\Statistics\Build\Character_Death_Leaders as Build_Character_Death_Leaders;
use LWTV\Statistics\Build\Character_Longevity_Leaders as Build_Character_Longevity_Leaders;

// Top 5 each — a page of parallel records at the same scale, rather than
// clichés alone staying a deep top-25 list beside shallow ones.
$lwtv_most_limit = 5;

$cliche_leaders    = ( new Build_Cliche_Leaders() )->generate( $lwtv_most_limit );
$show_leaders      = ( new Build_Character_Show_Leaders() )->generate( $lwtv_most_limit );
$actor_leaders     = ( new Build_Character_Actor_Leaders() )->generate( $lwtv_most_limit );
$death_leaders     = ( new Build_Character_Death_Leaders() )->generate( $lwtv_most_limit );
$longevity_leaders = ( new Build_Character_Longevity_Leaders() )->generate( $lwtv_most_limit );

// A plain count for every category except Longest-Running, which also needs
// the earliest–latest year range spelled out in the table (not the
// spotlight, where "50 yrs" alone is enough).
$lwtv_most_categories = array(
	array(
		'rows'            => is_array( $cliche_leaders ) ? array_values( $cliche_leaders ) : array(),
		'svg'             => 'medal.svg',
		'icon'            => 'svg-trophy',
		'spotlight_label' => __( 'Most Clichés', 'lwtv' ),
		'table_label'     => __( 'Clichés', 'lwtv' ),
		'value'           => static fn( $row ) => number_format_i18n( (int) $row['count'] ),
		'value_detail'    => static fn( $row ) => number_format_i18n( (int) $row['count'] ),
	),
	array(
		'rows'            => is_array( $show_leaders ) ? array_values( $show_leaders ) : array(),
		'svg'             => 'tv.svg',
		'icon'            => 'svg-tv',
		'spotlight_label' => __( 'Most Shows', 'lwtv' ),
		'table_label'     => __( 'Shows', 'lwtv' ),
		'value'           => static fn( $row ) => number_format_i18n( (int) $row['count'] ),
		'value_detail'    => static fn( $row ) => number_format_i18n( (int) $row['count'] ),
	),
	array(
		'rows'            => is_array( $actor_leaders ) ? array_values( $actor_leaders ) : array(),
		'svg'             => 'group.svg',
		'icon'            => 'svg-users',
		'spotlight_label' => __( 'Most Actors', 'lwtv' ),
		'table_label'     => __( 'Actors', 'lwtv' ),
		'value'           => static fn( $row ) => number_format_i18n( (int) $row['count'] ),
		'value_detail'    => static fn( $row ) => number_format_i18n( (int) $row['count'] ),
	),
	array(
		'rows'            => is_array( $death_leaders ) ? array_values( $death_leaders ) : array(),
		'svg'             => 'skull.svg',
		'icon'            => 'svg-skull',
		'spotlight_label' => __( 'Most Resurrected', 'lwtv' ),
		'table_label'     => __( 'Resurrected', 'lwtv' ),
		'value'           => static fn( $row ) => number_format_i18n( (int) $row['count'] ),
		'value_detail'    => static fn( $row ) => number_format_i18n( (int) $row['count'] ),
	),
	array(
		'rows'            => is_array( $longevity_leaders ) ? array_values( $longevity_leaders ) : array(),
		'svg'             => 'calendar-alt.svg',
		'icon'            => 'svg-calendar',
		'spotlight_label' => __( 'Longest-Running', 'lwtv' ),
		'table_label'     => __( 'Longest-Running', 'lwtv' ),
		/* translators: %s: number of years active. */
		'value'           => static fn( $row ) => sprintf( __( '%s yrs', 'lwtv' ), number_format_i18n( (int) $row['count'] ) ),
		/* translators: 1: number of years active, 2: earliest on-screen year, 3: latest on-screen year. */
		'value_detail'    => static fn( $row ) => sprintf( __( '%1$s yrs (%2$d–%3$d)', 'lwtv' ), number_format_i18n( (int) $row['count'] ), (int) $row['min'], (int) $row['max'] ),
	),
);
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'The Records', 'lwtv' ); ?></p>

<div class="lwtv-metric-grid lwtv-metric-grid--5 lwtv-records-spotlight">
	<?php
	foreach ( $lwtv_most_categories as $lwtv_most_cat ) {
		$lwtv_most_winner = ! empty( $lwtv_most_cat['rows'] ) ? $lwtv_most_cat['rows'][0] : null;
		?>
		<div class="lwtv-metric-card">
			<div class="lwtv-metric-top">
				<span class="lwtv-stats-eyebrow"><?php echo esc_html( $lwtv_most_cat['spotlight_label'] ); ?></span>
				<span class="lwtv-metric-icon characters">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $lwtv_most_cat['svg'], icon: $lwtv_most_cat['icon'], max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
			</div>
			<?php if ( null !== $lwtv_most_winner ) : ?>
				<span class="lwtv-metric-number"><?php echo esc_html( $lwtv_most_cat['value']( $lwtv_most_winner ) ); ?></span>
				<?php if ( ! empty( $lwtv_most_winner['url'] ) ) : ?>
					<a class="lwtv-metric-caption lwtv-records-winner" href="<?php echo esc_url( $lwtv_most_winner['url'] ); ?>"><?php echo esc_html( $lwtv_most_winner['name'] ); ?></a>
				<?php else : ?>
					<span class="lwtv-metric-caption lwtv-records-winner"><?php echo esc_html( $lwtv_most_winner['name'] ); ?></span>
				<?php endif; ?>
			<?php else : ?>
				<span class="lwtv-metric-number lwtv-records-number--empty" aria-hidden="true">—</span>
				<span class="lwtv-metric-caption"><?php esc_html_e( 'No record yet', 'lwtv' ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}
	?>
</div>

<p class="lwtv-stats-eyebrow lwtv-records-table-label"><?php esc_html_e( 'Full Rankings', 'lwtv' ); ?></p>

<div class="lwtv-records-table-wrap">
	<div class="lwtv-records-table">
		<div class="lwtv-records-row lwtv-records-row--head">
			<div class="lwtv-records-cell lwtv-records-cell--rank"></div>
			<?php foreach ( $lwtv_most_categories as $lwtv_most_cat ) : ?>
				<div class="lwtv-records-cell lwtv-records-cell--head"><?php echo esc_html( $lwtv_most_cat['table_label'] ); ?></div>
			<?php endforeach; ?>
		</div>
		<?php for ( $lwtv_rank = 0; $lwtv_rank < $lwtv_most_limit; $lwtv_rank++ ) : ?>
			<div class="lwtv-records-row<?php echo ( 0 === ( $lwtv_rank % 2 ) ) ? '' : ' lwtv-records-row--alt'; ?>">
				<div class="lwtv-records-cell lwtv-records-cell--rank"><?php echo esc_html( (string) ( $lwtv_rank + 1 ) ); ?></div>
				<?php
				foreach ( $lwtv_most_categories as $lwtv_most_cat ) {
					$lwtv_cat_row = $lwtv_most_cat['rows'][ $lwtv_rank ] ?? null;
					?>
					<div class="lwtv-records-cell">
						<?php if ( null !== $lwtv_cat_row ) : ?>
							<?php if ( ! empty( $lwtv_cat_row['url'] ) ) : ?>
								<a class="lwtv-records-name" href="<?php echo esc_url( $lwtv_cat_row['url'] ); ?>"><?php echo esc_html( $lwtv_cat_row['name'] ); ?></a>
							<?php else : ?>
								<span class="lwtv-records-name"><?php echo esc_html( $lwtv_cat_row['name'] ); ?></span>
							<?php endif; ?>
							<span class="lwtv-records-value"><?php echo esc_html( '· ' . $lwtv_most_cat['value_detail']( $lwtv_cat_row ) ); ?></span>
						<?php else : ?>
							<span class="lwtv-records-dash" aria-hidden="true">—</span>
						<?php endif; ?>
					</div>
					<?php
				}
				?>
			</div>
		<?php endfor; ?>
	</div>
</div>

<p class="lwtv-records-note"><?php esc_html_e( 'The spotlight shows the #1 record holder in each category; the table gives the full top five. Dashes mark categories with fewer than five qualifying characters.', 'lwtv' ); ?></p>
