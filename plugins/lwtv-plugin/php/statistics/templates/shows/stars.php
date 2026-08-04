<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Shows → Stars: medal podium + callout rail.
 *
 * Three plates whose heights encode the tier counts (gold centred, the
 * physical podium order), a rail of solid stat cards for the numbers
 * the podium can't express, and "no star" demoted to a footnote. All
 * copy is adaptive: the headline follows the leading tier, the share
 * sentence uses the fraction-phrase ladder, and the silver/bronze
 * footnote names a dead heat only while it is one. Podium math lives
 * in the pure Build\Star_Podium transform.
 *
 * @package LezWatch.TV
 *
 * @var int $shows_count
 */

use LWTV\Statistics\Build\Star_Podium;
use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
require_once plugin_dir_path( __DIR__ ) . 'partials/phrases.php';

$stars_raw  = lwtv_plugin()->generate_shows_statistics( 'array', 'stars' );
$stars_data = ( is_array( $stars_raw ) && ! empty( $stars_raw ) ) ? (array) reset( $stars_raw ) : array();

$stars_counts = array();
foreach ( array( 'gold', 'silver', 'bronze', 'anti' ) as $stars_tier ) {
	$stars_counts[ $stars_tier ] = isset( $stars_data[ $stars_tier ] ) ? (int) $stars_data[ $stars_tier ]['count'] : 0;
}

// Distinct shows carrying at least one star — the star-rate denominator's
// counterpart. The leader's share divides by the tier sum instead, so the
// two stay correct even if a show ever carries two stars.
$stars_inter   = ( new Build_Taxonomy_Optimized() )->get_terms_per_object_stats( 'post_type_shows', 'lez_stars' );
$stars_starred = (int) ( $stars_inter['shows'] ?? 0 );

$stars_facts   = Star_Podium::facts( $stars_counts, (int) $shows_count, $stars_starred );
$stars_columns = Star_Podium::columns( $stars_counts );

// Tier meta: label, definition ("who it's for"), medal icon.
$stars_tiers = array(
	'gold'   => array(
		'label' => __( 'Gold', 'lwtv' ),
		'def'   => __( 'For us specifically', 'lwtv' ),
		'svg'   => 'star.svg',
		'icon'  => 'svg-star',
	),
	'silver' => array(
		'label' => __( 'Silver', 'lwtv' ),
		'def'   => __( 'For queers broadly', 'lwtv' ),
		'svg'   => 'star.svg',
		'icon'  => 'svg-star',
	),
	'bronze' => array(
		'label' => __( 'Bronze', 'lwtv' ),
		'def'   => __( 'Queer men first', 'lwtv' ),
		'svg'   => 'star.svg',
		'icon'  => 'svg-star',
	),
	'anti'   => array(
		'label' => __( 'Anti', 'lwtv' ),
		'def'   => __( 'For the straights', 'lwtv' ),
		'svg'   => 'eye-evil.svg',
		'icon'  => 'svg-eye-evil',
	),
);

// Headline follows the leading tier — "made for us" is only true while gold leads.
switch ( $stars_facts['leader'] ) {
	case 'gold':
		$stars_headline = __( 'Most starred shows were made for us', 'lwtv' );
		break;
	case 'silver':
		$stars_headline = __( 'Most starred shows were made for queers broadly', 'lwtv' );
		break;
	case 'bronze':
		$stars_headline = __( 'Most starred shows put queer men first', 'lwtv' );
		break;
	default:
		$stars_headline = __( 'No show has earned a star yet', 'lwtv' );
		break;
}

// Leader-share sentence, phrased from the ladder ("Nearly two thirds…").
$stars_share_phrases = array(
	/* translators: %s: a fraction phrase, e.g. "Nearly two thirds". */
	'gold'   => __( '%s of all stars go to shows made squarely for queer women, trans and non-binary viewers.', 'lwtv' ),
	/* translators: %s: a fraction phrase, e.g. "Nearly two thirds". */
	'silver' => __( '%s of all stars go to shows made for queers broadly.', 'lwtv' ),
	/* translators: %s: a fraction phrase, e.g. "Nearly two thirds". */
	'bronze' => __( '%s of all stars go to shows that put queer men first.', 'lwtv' ),
);

// Anti card: zero is the story; nonzero changes the sentence, not the card.
if ( $stars_counts['anti'] > 0 ) {
	$stars_anti_text = sprintf(
		/* translators: %s: number of shows flagged "anti". */
		_n( '%s show has been flagged as made by queers for the straights.', '%s shows have been flagged as made by queers for the straights.', $stars_counts['anti'], 'lwtv' ),
		number_format_i18n( $stars_counts['anti'] )
	);
} else {
	$stars_anti_text = __( 'No show has been made by queers for the straights. Long may that last.', 'lwtv' );
}
?>

<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Star Ratings', 'lwtv' ); ?></p>

<div class="lwtv-star-podium bg-light">
	<div class="lwtv-star-podium-layout">

		<div class="lwtv-star-rail">
			<div class="lwtv-star-callout lwtv-star-callout--pink">
				<div class="lwtv-star-callout-top">
					<span class="lwtv-star-callout-eyebrow"><?php esc_html_e( 'Star Rate', 'lwtv' ); ?></span>
					<span class="lwtv-star-callout-chip"><?php echo lwtv_plugin()->get_symbolicon( svg: 'fireworks.svg', icon: 'svg-fireworks', max_size: '16' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
				<span class="lwtv-star-callout-num"><?php echo esc_html( number_format_i18n( $stars_facts['star_rate_pct'], 1 ) . '%' ); ?></span>
				<p class="lwtv-star-callout-text">
					<?php
					printf(
						/* translators: 1: number of shows with a star, 2: total number of shows. */
						esc_html__( '%1$s of %2$s shows have earned any star at all.', 'lwtv' ),
						esc_html( number_format_i18n( $stars_starred ) ),
						esc_html( number_format_i18n( (int) $shows_count ) )
					);
					?>
				</p>
			</div>

			<?php if ( '' !== $stars_facts['leader'] ) : ?>
				<div class="lwtv-star-callout lwtv-star-callout--teal">
					<div class="lwtv-star-callout-top">
						<span class="lwtv-star-callout-eyebrow">
							<?php
							printf(
								/* translators: %s: the leading star tier (Gold/Silver/Bronze). */
								esc_html__( '%s Leads', 'lwtv' ),
								esc_html( $stars_tiers[ $stars_facts['leader'] ]['label'] )
							);
							?>
						</span>
						<span class="lwtv-star-callout-chip"><?php echo lwtv_plugin()->get_symbolicon( svg: 'star.svg', icon: 'svg-star', max_size: '16' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</div>
					<span class="lwtv-star-callout-num" data-count-to="<?php echo (int) $stars_facts['leader_share_pct']; ?>" data-count-suffix="%"><?php echo esc_html( number_format_i18n( $stars_facts['leader_share_pct'] ) . '%' ); ?></span>
					<p class="lwtv-star-callout-text">
						<?php echo esc_html( sprintf( $stars_share_phrases[ $stars_facts['leader'] ], lwtv_stats_fraction_phrase( $stars_facts['leader_share_pct'] ) ) ); ?>
					</p>
				</div>
			<?php endif; ?>

			<div class="lwtv-star-callout lwtv-star-callout--raspberry">
				<div class="lwtv-star-callout-top">
					<span class="lwtv-star-callout-eyebrow"><?php esc_html_e( 'Anti Flag', 'lwtv' ); ?></span>
					<span class="lwtv-star-callout-chip"><?php echo lwtv_plugin()->get_symbolicon( svg: 'eye-evil.svg', icon: 'svg-eye-evil', max_size: '16' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
				<span class="lwtv-star-callout-num" data-count-to="<?php echo (int) $stars_counts['anti']; ?>"><?php echo esc_html( number_format_i18n( $stars_counts['anti'] ) ); ?></span>
				<p class="lwtv-star-callout-text"><?php echo esc_html( $stars_anti_text ); ?></p>
			</div>
		</div>

		<div class="lwtv-star-podium-chart">
			<h2 class="lwtv-yearbars-headline"><?php echo esc_html( $stars_headline ); ?></h2>
			<p class="lwtv-yearbars-desc">
				<?php echo wp_kses( __( 'A star marks who a show was <em>for</em>, not whether it&#8217;s good.', 'lwtv' ), array( 'em' => array() ) ); ?>
			</p>

			<?php if ( ! empty( $stars_columns ) && $stars_facts['star_sum'] >= 3 ) : ?>
				<figure class="lwtv-star-figure">
					<figcaption class="visually-hidden">
						<?php
						printf(
							/* translators: 1-4: gold/silver/bronze/anti counts, 5: total shows. */
							esc_html__( 'Star ratings: %1$s gold, %2$s silver, %3$s bronze, %4$s anti, of %5$s shows.', 'lwtv' ),
							esc_html( number_format_i18n( $stars_counts['gold'] ) ),
							esc_html( number_format_i18n( $stars_counts['silver'] ) ),
							esc_html( number_format_i18n( $stars_counts['bronze'] ) ),
							esc_html( number_format_i18n( $stars_counts['anti'] ) ),
							esc_html( number_format_i18n( (int) $shows_count ) )
						);
						?>
					</figcaption>
					<div class="lwtv-star-podium-columns" style="grid-template-columns: repeat(<?php echo (int) count( $stars_columns ); ?>, minmax(0, 1fr));">
						<?php foreach ( $stars_columns as $stars_col ) : ?>
							<div class="lwtv-star-col lwtv-star-col--<?php echo esc_attr( $stars_col['tier'] ); ?>">
								<span class="lwtv-star-count" data-count-to="<?php echo (int) $stars_col['count']; ?>"><?php echo esc_html( number_format_i18n( $stars_col['count'] ) ); ?></span>
								<div class="lwtv-star-plate" style="height:<?php echo (int) $stars_col['height']; ?>px">
									<span class="lwtv-star-medal" aria-hidden="true">
										<?php echo lwtv_plugin()->get_symbolicon( svg: $stars_tiers[ $stars_col['tier'] ]['svg'], icon: $stars_tiers[ $stars_col['tier'] ]['icon'], max_size: ( 'gold' === $stars_col['tier'] ) ? '26' : '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</span>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="lwtv-star-baseline"></div>
					<div class="lwtv-star-podium-labels" style="grid-template-columns: repeat(<?php echo (int) count( $stars_columns ); ?>, minmax(0, 1fr));">
						<?php foreach ( $stars_columns as $stars_col ) : ?>
							<div class="lwtv-star-label lwtv-star-label--<?php echo esc_attr( $stars_col['tier'] ); ?>">
								<span class="lwtv-star-tier"><?php echo esc_html( $stars_tiers[ $stars_col['tier'] ]['label'] ); ?></span>
								<span class="lwtv-star-def"><?php echo esc_html( $stars_tiers[ $stars_col['tier'] ]['def'] ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</figure>
			<?php endif; ?>

			<?php if ( $stars_facts['none_count'] > 0 ) : ?>
				<p class="lwtv-star-footnote">
					<?php
					printf(
						/* translators: 1: number of shows without a star, 2: their percentage of all shows (one decimal). */
						esc_html__( 'The other %1$s shows (%2$s%%) carry no star. Stars are a mark of distinction, not a default.', 'lwtv' ),
						esc_html( number_format_i18n( $stars_facts['none_count'] ) ),
						esc_html( number_format_i18n( $stars_facts['none_pct'], 1 ) )
					);
					?>
				</p>
			<?php endif; ?>

			<?php
			// Silver vs bronze, named honestly: a dead heat only while it is one.
			$stars_sb = Star_Podium::relationship( $stars_counts['silver'], $stars_counts['bronze'] );
			if ( 'none' !== $stars_sb ) {
				switch ( $stars_sb ) {
					case 'dead-heat':
						/* translators: 1: silver count, 2: bronze count. */
						$stars_sb_text = __( 'Silver and bronze are almost a dead heat: %1$s made for queers broadly against %2$s that put queer men first.', 'lwtv' );
						break;
					case 'first-leads':
						/* translators: 1: silver count, 2: bronze count. */
						$stars_sb_text = __( 'Silver outpaces bronze: %1$s made for queers broadly against %2$s that put queer men first.', 'lwtv' );
						break;
					default:
						/* translators: 1: silver count, 2: bronze count. */
						$stars_sb_text = __( 'Bronze outpaces silver: %2$s that put queer men first against %1$s made for queers broadly.', 'lwtv' );
						break;
				}
				?>
				<p class="lwtv-star-footnote">
					<?php
					printf(
						esc_html( $stars_sb_text ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Assembled from the translated variants above.
						esc_html( number_format_i18n( $stars_counts['silver'] ) ),
						esc_html( number_format_i18n( $stars_counts['bronze'] ) )
					);
					?>
				</p>
				<?php
			}
			?>
		</div>

	</div>
</div>
