<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Reusable donut chart: SVG ring (pathLength=100) + legend with mini-bars.
 * Ring renders at final proportions; center figure + legend counts count up.
 *
 * @package LezWatch.TV
 *
 * @var array $donut {
 *   @type array  $segments   Ordered [ ['label','count','pct','class'], … ].
 *   @type int    $center     Center headline figure.
 *   @type string $center_sub Center sublabel.
 *   @type string $eyebrow    Section eyebrow.
 *   @type string $headline   Headline sentence.
 *   @type string $description Supporting sentence.
 * }
 */

$donut_segments = $donut['segments'] ?? array();
$donut_offset   = 0.0; // cumulative share for stroke-dashoffset.
$donut_layout   = $donut['layout'] ?? 'full';

if ( 'compact' === $donut_layout ) :
	$donut_has_pct = isset( $donut['center_pct'] );
	$donut_family  = $donut['center_family'] ?? '';
	?>
	<section class="lwtv-donut-card lwtv-donut-card--compact bg-light">
		<?php
		/*
		 * Eyebrow allows one safe inline tag: a caller like Formats' decade
		 * tiles needs "1980s" to keep a lowercase "s" against this label's
		 * uppercase text-transform (CSS can't do that without an element
		 * boundary — see .lwtv-decade-suffix). Plain-string callers pass
		 * through wp_kses() unchanged. The SVG's aria-label strips any markup
		 * back out, since an accessible name should never contain tag syntax.
		 */
		?>
		<p class="lwtv-stats-eyebrow"><?php echo wp_kses( $donut['eyebrow'] ?? '', array( 'span' => array( 'class' => array() ) ) ); ?></p>
		<div class="lwtv-donut-figure">
			<svg class="lwtv-donut" viewBox="0 0 120 120" role="img" aria-label="<?php echo esc_attr( wp_strip_all_tags( $donut['eyebrow'] ?? '' ) ); ?>">
				<g transform="rotate(-90 60 60)">
					<circle class="lwtv-donut-track" cx="60" cy="60" r="50" fill="none" stroke-width="15" pathLength="100" />
					<?php
					foreach ( $donut_segments as $donut_seg ) {
						$donut_share = max( 0, (float) $donut_seg['pct'] );
						printf(
							'<circle class="lwtv-donut-seg lwtv-donut-seg--%1$s" cx="60" cy="60" r="50" fill="none" stroke-width="15" pathLength="100" stroke-dasharray="%2$s %3$s" stroke-dashoffset="%4$s" />',
							esc_attr( $donut_seg['class'] ),
							esc_attr( (string) $donut_share ),
							esc_attr( (string) ( 100 - $donut_share ) ),
							esc_attr( (string) ( -1 * $donut_offset ) )
						);
						$donut_offset += $donut_share;
					}
					?>
				</g>
			</svg>
			<div class="lwtv-donut-center">
				<?php if ( $donut_has_pct ) : ?>
					<span class="lwtv-donut-center-num lwtv-donut-center-num--<?php echo esc_attr( $donut_family ); ?>" data-count-to="<?php echo (int) $donut['center_pct']; ?>" data-count-suffix="%"><?php echo esc_html( number_format_i18n( (int) $donut['center_pct'] ) ); ?>%</span>
				<?php else : ?>
					<span class="lwtv-donut-center-num" data-count-to="<?php echo (int) ( $donut['center'] ?? 0 ); ?>"><?php echo esc_html( number_format_i18n( (int) ( $donut['center'] ?? 0 ) ) ); ?></span>
				<?php endif; ?>
				<span class="lwtv-donut-center-sub"><?php echo esc_html( $donut['center_sub'] ?? '' ); ?></span>
			</div>
		</div>
		<ul class="lwtv-donut-legend lwtv-donut-legend--compact">
			<?php
			foreach ( $donut_segments as $donut_seg ) {
				?>
				<li class="lwtv-donut-legend-row">
					<span class="lwtv-donut-dot lwtv-donut-seg--<?php echo esc_attr( $donut_seg['class'] ); ?>"></span>
					<span class="lwtv-donut-legend-name"><?php echo esc_html( $donut_seg['label'] ); ?></span>
					<span class="lwtv-donut-legend-val"><?php echo esc_html( number_format_i18n( (int) $donut_seg['count'] ) . ' · ' . $donut_seg['pct'] . '%' ); ?></span>
				</li>
				<?php
			}
			?>
		</ul>
	</section>
	<?php
	return;
endif;
?>
<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php echo esc_html( $donut['eyebrow'] ?? '' ); ?></p>

<section class="lwtv-donut-card bg-light">
	<div class="lwtv-donut-figure">
		<svg class="lwtv-donut" viewBox="0 0 120 120" role="img" aria-label="<?php echo esc_attr( $donut['eyebrow'] ?? '' ); ?>">
			<g transform="rotate(-90 60 60)">
				<circle class="lwtv-donut-track" cx="60" cy="60" r="50" fill="none" stroke-width="15" pathLength="100" />
				<?php
				foreach ( $donut_segments as $donut_seg ) {
					$donut_share = max( 0, (float) $donut_seg['pct'] );
					printf(
						'<circle class="lwtv-donut-seg lwtv-donut-seg--%1$s" cx="60" cy="60" r="50" fill="none" stroke-width="15" pathLength="100" stroke-dasharray="%2$s %3$s" stroke-dashoffset="%4$s" />',
						esc_attr( $donut_seg['class'] ),
						esc_attr( (string) $donut_share ),
						esc_attr( (string) ( 100 - $donut_share ) ),
						esc_attr( (string) ( -1 * $donut_offset ) )
					);
					$donut_offset += $donut_share;
				}
				?>
			</g>
		</svg>
		<div class="lwtv-donut-center">
			<span class="lwtv-donut-center-num" data-count-to="<?php echo (int) ( $donut['center'] ?? 0 ); ?>"><?php echo esc_html( number_format_i18n( (int) ( $donut['center'] ?? 0 ) ) ); ?></span>
			<span class="lwtv-donut-center-sub"><?php echo esc_html( $donut['center_sub'] ?? '' ); ?></span>
		</div>
	</div>

	<div class="lwtv-donut-body">
		<h2 class="lwtv-donut-headline"><?php echo esc_html( $donut['headline'] ?? '' ); ?></h2>
		<?php if ( ! empty( $donut['description'] ) ) : ?>
			<p class="lwtv-donut-desc"><?php echo esc_html( $donut['description'] ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $donut['waffle'] ) ) : ?>
			<div class="lwtv-donut-waffle">
				<?php
				$waffle = $donut['waffle'];
				// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
				include __DIR__ . '/waffle.php';
				?>
			</div>
		<?php endif; ?>
		<ul class="lwtv-donut-legend">
			<?php
			foreach ( $donut_segments as $donut_seg ) {
				?>
				<li class="lwtv-donut-legend-row">
					<span class="lwtv-donut-dot lwtv-donut-seg--<?php echo esc_attr( $donut_seg['class'] ); ?>"></span>
					<span class="lwtv-donut-legend-name"><?php echo esc_html( $donut_seg['label'] ); ?></span>
					<div class="progress lwtv-donut-legend-track">
						<div class="progress-bar lwtv-donut-seg--<?php echo esc_attr( $donut_seg['class'] ); ?>" role="progressbar" style="width:0" data-grow-to="<?php echo esc_attr( (string) $donut_seg['pct'] ); ?>" aria-valuenow="<?php echo esc_attr( (int) $donut_seg['count'] ); ?>" aria-valuemin="0" aria-valuemax="100"></div>
					</div>
					<span class="lwtv-donut-legend-val"><?php echo esc_html( number_format_i18n( (int) $donut_seg['count'] ) . ' · ' . $donut_seg['pct'] . '%' ); ?></span>
				</li>
				<?php
			}
			?>
		</ul>
	</div>
</section>
