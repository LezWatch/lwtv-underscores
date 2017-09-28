<?php
/**
 * Template part for displaying shows
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatchTV
 */

$show_id = $post->ID;
$slug    = get_post_field( 'post_name', get_post( $show_id ) );
$term    = term_exists( $slug , 'post_tag' );
$related = LWTV_Related_Posts::are_there_posts( $slug );
?>

<?php the_post_thumbnail( 'show-img', array( 'class' => 'card-img-top' , 'alt' => get_the_title() , 'title' => get_the_title() ) ); ?>

<section id="toc" class="toc-container card-body">		
	<nav class="breadcrumb">
		<h4 class="toc-title">Table of Contents</h4>
		<a class="breadcrumb-item smoothscroll" href="#overview">Overview</a>
		<?php
		if( ( get_post_meta( get_the_ID(), 'lezshows_plots', true) ) ) {
			?><a class="breadcrumb-item smoothscroll" href="#timeline">Timeline</a><?php
		}
		if( ( get_post_meta( get_the_ID(), 'lezshows_episodes', true) ) ) {
			?><a class="breadcrumb-item smoothscroll" href="#episodes">Episodes</a><?php
		}
		if ( $related ) {
			?><a class="breadcrumb-item smoothscroll" href="#related-posts">Related Posts</a><?php
		}
		?>
		<a class="breadcrumb-item smoothscroll" href="#characters">Characters</a>
	</nav>
</section>

<?php
	
	// This shows a warning if the show has trigger warnings
	$warning    = lwtv_yikes_content_warning( get_the_ID() );
	$warn_image = lwtv_yikes_symbolicons( 'hand.svg', 'fa-hand-paper-o' );
	
	if ( $warning['card'] != 'none' ) {
	?>

	<section id="trigger-warning" class="trigger-warning-container">
		<div class="alert alert-<?php echo $warning['card']; ?>" role="alert">
			<span class="callout-<?php echo $warning['card']; ?>" role="img" aria-label="Warning Hand" title="Warning Hand"><?php echo $warn_image; ?></span>
			<?php echo $warning['content']; ?>
		</div>
	</section>

	<?php
	}
?>

<section class="showschar-section" name="overview" id="overview">
	<h2>Overview</h2>

	<div class="card-body">
		<?php the_content(); ?>
	</div>
</section>

<?php

$show_id = $post->ID;

// Queer Plots - Only display if they exist
if ( (get_post_meta($show_id, "lezshows_plots", true) ) ) { ?>
	<section name="timeline" id="timeline" class="showschar-section">
		<h2>Queer Plotline Timeline</h2>
		<div class="card-body">
			<?php echo apply_filters('the_content', wp_kses_post( get_post_meta($show_id, 'lezshows_plots', true) ) ); ?>
		</div>
	</section><?php
}

// Best Lez Episodes - Only display if they exist
if ( ( get_post_meta($show_id, "lezshows_episodes", true ) ) ) { ?>
	<section name="episodes" id="episodes" class="showschar-section">
		<h2>Notable Queer-Centric Episodes</h2>
		<div class="card-body">
			<?php echo apply_filters('the_content', wp_kses_post( get_post_meta($show_id, 'lezshows_episodes', true) ) ); ?>
		</div>
	</section> <?php
}

if ( $related ) {
	?>
	<section name="related-posts" id="related-posts" class="showschar-section">	
		<h2>Related Articles</h2>
		<div class="card-body">	
			<?php
				echo LWTV_CPT_Shows::related_posts( $slug );
			?>
		</div>
	</section> 
	<?php
}

// Great big characters section!
?>
<section name="characters" id="characters" class="showschar-section">
	<h2>Characters</h2>
	<div class="card-body">
		<?php

		// This just gets the numbers of all characters and how many are dead.
		$havecharcount  = LWTV_CPT_Characters::list_characters( $show_id, 'count' );
		$havedeadcount  = LWTV_CPT_Characters::list_characters( $show_id, 'dead' );
		
		if ( empty( $havecharcount ) || $havecharcount == '0' ) {
			echo '<p>There are no queers listed yet for this show.</p>';
		} else {
		
			$deadtext = 'none are dead';
			if ( $havedeadcount > '0' ) $deadtext = sprintf( _n( '%s is dead', '%s are dead', $havedeadcount ), $havedeadcount );
		
			echo '<p>There '. sprintf( _n( 'is %s queer character', 'are %s queer characters', $havecharcount ), $havecharcount ).' listed for this show. Of those, ' . $deadtext . '.</p>';
		
			// Get the list of REGULAR characters
			$chars_regular = lwtv_yikes_get_characters_for_show( $show_id, 'regular' );
			if ( !empty( $chars_regular ) ) {	
				?><h3 class="title-regulars">Regulars</h3>
				<div class="container characters-regulars-container"><div class="row site-loop character-show-loop equal-height"><?php
				foreach( $chars_regular as $character ) {
					?><div class="col-sm-4"><?php
						include( locate_template( 'template-parts/excerpt-post_type_characters.php' ) );
					?></div><?php
				}
				echo '</div></div>';
			}
			// Get the list of RECURRING characters
			$chars_recurring = lwtv_yikes_get_characters_for_show( $show_id, 'recurring' );
			if ( !empty( $chars_recurring ) ) {	
				?><h3 class="title-recurring">Recurring</h3>
				<div class="container characters-recurring-container"><div class="row site-loop character-show-loop equal-height"><?php
				foreach( $chars_recurring as $character ) {
					?><div class="col-sm-4"><?php
						include( locate_template( 'template-parts/excerpt-post_type_characters.php' ) );
					?></div><?php
				}
				echo '</div></div>';
			}
			// Get the list of GUEST characters
			$chars_guest = lwtv_yikes_get_characters_for_show( $show_id, 'guest' );
			if ( !empty( $chars_guest ) ) {	
				?><h3 class="title-guest">Guest</h3>
				<ul class="guest-character-list"><?php
				foreach( $chars_guest as $character ) {
					?><li><a href="<?php the_permalink( $character['id'] ); ?>" title="<?php the_title_attribute( $character['id'] ); ?>" ><?php echo get_the_title( $character['id'] ); ?></a></li><?php
				}
				echo '</ul>';
			}
		} 
		?>
	</div>
</section>