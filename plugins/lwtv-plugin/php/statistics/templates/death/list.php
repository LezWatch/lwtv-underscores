<?php
/**
 * The template for displaying the death list statistics - Optimized Version
 *
 * @package LezWatch.TV
 *
 * Required variables:
 * @var array $deadchars_with_stats - Dead characters with stats
 *
 */

if ( empty( $deadchars_with_stats ) ) {
	lwtv_plugin()->error_log( 'dead-debug', 'Dead characters with stats is empty' );
	return;
}
$days           = $deadchars_with_stats['time'];
$start_date     = $deadchars_with_stats['start'];
$end_date       = $deadchars_with_stats['end'];
$most_dead      = $deadchars_with_stats['most']['count'];
$most_dead_date = $deadchars_with_stats['most']['date'];

?>
<h3>List of All Dead Characters</h3>

<p>The longest time span between character deaths is <strong><?php echo esc_html( $days ); ?> days</strong> (<a href="#<?php echo esc_html( $start_date ); ?>"><?php echo esc_html( $start_date ); ?></a> to <a href="#<?php echo esc_html( $end_date ); ?>"><?php echo esc_html( $end_date ); ?></a>). The shortest timespan is <strong>0 days</strong> (multiple characters have died on the same day). The most characters who have died on a single day is <strong><?php echo esc_html( $most_dead ); ?></strong> (<a href="#<?php echo esc_html( $most_dead_date ); ?>"><?php echo esc_html( $most_dead_date ); ?></a>).</p>

<div class="container chart-container">
	<div class="row">
		<div class="col">
			<?php echo lwtv_plugin()->generate_dead_statistics( 'characters', 'all', 'list' ); ?>
		</div>
	</div>
</div>
<?php
