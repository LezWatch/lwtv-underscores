<?php
/**
 * Template for the Shows on Air page
 *
 * @package LezWatch.TV
 *
 * Required variables:
 * @var int $shows_on_air_count
 */

// translators: %s is the number of shows on air
$h2_title = sprintf( _n( '%s Show On Air', '%s Shows On Air', $shows_on_air_count ), $shows_on_air_count );
?>

<h2><a name="showsonair"><?php echo esc_html( $h2_title ); ?></a></h2>

<p>&nbsp;</p>

<ul class="nav nav-pills nav-fill" id="v-pills-tab" role="tablist">
	<li class="nav-item"><a class="nav-link active" id="v-pills-byname-tab" data-bs-toggle="pill" href="#v-pills-byname" role="tab" aria-controls="v-pills-byname" aria-selected="true">By Name</a></li>
	<li class="nav-item"><a class="nav-link" id="v-pills-byformat-tab" data-bs-toggle="pill" href="#v-pills-byformat" role="tab" aria-controls="v-pills-byformat" aria-selected="true">By Format</a></li>
	<li class="nav-item"><a class="nav-link" id="v-pills-bycountry-tab" data-bs-toggle="pill" href="#v-pills-bycountry" role="tab" aria-controls="v-pills-bycountry" aria-selected="true">By Country</a></li>
</ul>

