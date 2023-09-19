<?php
/**
 * The template for displaying show CPT Character Sidebar
 *
 * @package LezWatch.TV
 */

global $post;
$char_id = $post->ID;
?>

<section id="search" class="widget widget-search">
	<div class="widget-wrap">
		<?php get_search_form(); ?>
	</div>
</section>

<section id="suggest-edits" class="widget widget_suggestedits">
	<?php get_template_part( 'template-parts/suggestedit', 'form' ); ?>
</section>

<section id="join-slack" class="widget widget_joinslack">
	<div class="widget-wrap">
		<div class="card card-slack">
			<div class="card-header">
				<h4 class="widget-title">Join Our Community!</h4>
			</div>
			<div class="card-body">
				<a href="https://lezwatchtv.com/slack/">
					<img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/Slack-logo-RGB.png" alt="Slack" style="margin-bottom: 1rem;">
				</a>
				<p>The LezWatch.TV Slack is a free safe space to chat with other wlw TV fans in real time.</p>
				<p style="text-align:center;"><a href="https://lezwatchtv.com/slack/" class="btn btn-primary">Join Us!</a></p>
			</div>
		</div>
	</div>
</section>
