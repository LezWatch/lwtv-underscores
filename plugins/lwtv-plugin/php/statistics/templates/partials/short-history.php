<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Short-history card for thin-data entities (nations/stations with only
 * a show or two). At that scale the catalog IS the story, so instead of
 * a chart we render an adaptive narrative plus one row per show.
 *
 * @package LezWatch.TV
 *
 * @var array $short_history {
 *   @type string $eyebrow  Section eyebrow.
 *   @type string $headline Card headline.
 *   @type array  $lines    Translated narrative sentences (joined into one paragraph).
 *   @type array  $rows     [ ['title','url','meta','score'], … ] one per show.
 * }
 */

$sh_lines = array_filter( array_map( 'trim', (array) ( $short_history['lines'] ?? array() ) ) );
$sh_rows  = (array) ( $short_history['rows'] ?? array() );
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php echo esc_html( $short_history['eyebrow'] ?? '' ); ?></p>

<section class="lwtv-yearbars-card lwtv-shorthist bg-light">
	<h2 class="lwtv-yearbars-headline"><?php echo esc_html( $short_history['headline'] ?? '' ); ?></h2>
	<?php if ( ! empty( $sh_lines ) ) : ?>
		<p class="lwtv-yearbars-desc lwtv-shorthist-desc"><?php echo esc_html( implode( ' ', $sh_lines ) ); ?></p>
	<?php endif; ?>

	<div class="lwtv-shorthist-rows">
		<?php foreach ( $sh_rows as $sh_row ) : ?>
			<div class="lwtv-shorthist-row">
				<div class="lwtv-shorthist-row-body">
					<a class="lwtv-shorthist-title" href="<?php echo esc_url( $sh_row['url'] ?? '' ); ?>"><?php echo esc_html( $sh_row['title'] ?? '' ); ?></a>
					<p class="lwtv-shorthist-meta"><?php echo esc_html( $sh_row['meta'] ?? '' ); ?></p>
				</div>
				<?php if ( isset( $sh_row['score'] ) && '' !== $sh_row['score'] ) : ?>
					<span class="lwtv-shorthist-score">
						<?php
						printf(
							/* translators: %s: the show score (0–100). */
							esc_html__( 'Score %s', 'lwtv' ),
							esc_html( number_format_i18n( (int) $sh_row['score'] ) )
						);
						?>
					</span>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</section>
