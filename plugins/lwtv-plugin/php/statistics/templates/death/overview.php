<?php
/**
 * The template for displaying the death overview statistics - Optimized Version
 *
 * @package LezWatch.TV
 *
 * @var int $deadchar_percent
 * @var int $deadchars
 * @var int $deadshow_percent
 * @var int $deadshows
 * @var int $dead_years_average
 */
?>
<div class="container">
	<div class="row">
		<div class="col">
			<div class="card text-center">
				<h3 class="card-header characters">Characters</h3>
				<div class="card-body bg-light">
					<h5 class="card-title"><?php echo esc_html( $deadchar_percent ); ?>% (<?php echo esc_html( $deadchars ); ?>)</h5>
				</div>
			</div>
		</div>
		<div class="col">
			<div class="card text-center">
				<h3 class="card-header shows">Shows</h3>
				<div class="card-body bg-light">
					<h5 class="card-title"><?php echo esc_html( $deadshow_percent ); ?>% (<?php echo esc_html( $deadshows ); ?>)</h5>
				</div>
			</div>
		</div>
	</div>
</div>

<p>&nbsp;<br/>On average, <strong><?php echo esc_html( $dead_years_average ); ?></strong> characters die per year (including years where no queers died).</p>

<div class="container">
	<div class="row">
		<div class="col">
			<?php echo lwtv_plugin()->generate_dead_statistics( 'characters', 'years', 'trendline' ); ?>
		</div>
	</div>
</div>
<?php
