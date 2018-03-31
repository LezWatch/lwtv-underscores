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
$tag     = get_term_by( 'name', $slug, 'post_tag');
$related = LWTV_Related_Posts::are_there_posts( $slug );

// Microformats Fix
lwtv_microformats_fix( $post->ID );

// Thumbnail attribution
$thumb_attribution = get_post_meta( get_post_thumbnail_id(), 'lwtv_attribution', true );
$thumb_title       = ( empty( $thumb_attribution ) )? get_the_title() : get_the_title() . ' &copy; ' . $thumb_attribution;

// Echo the header image
the_post_thumbnail( 'large', array( 'class' => 'card-img-top' , 'alt' => get_the_title() , 'title' => $thumb_title ) );

?>

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
// The Game of Thrones Flag of Gratuitious Violence:
// This shows a notice if the show has trigger warnings
$warning    = lwtv_yikes_content_warning( get_the_ID() );
$warn_image = lwtv_yikes_symbolicons( 'hand.svg', 'fa-hand-paper' );

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
// Queer Plots - Only display if they exist
if ( (get_post_meta($show_id, 'lezshows_plots', true) ) ) { 
	?>
	<section name="timeline" id="timeline" class="showschar-section">
		<h2>Queer Plotline Timeline</h2>
		<div class="card-body">
			<?php echo apply_filters('the_content', wp_kses_post( get_post_meta($show_id, 'lezshows_plots', true ) ) ); ?>
		</div>
	</section>
	<?php
}

// Best Episodes - Only display if they exist
if ( ( get_post_meta($show_id, "lezshows_episodes", true ) ) ) { 
	?>
	<section name="episodes" id="episodes" class="showschar-section">
		<h2>Notable Queer-Centric Episodes</h2>
		<div class="card-body">
			<?php echo apply_filters('the_content', wp_kses_post( get_post_meta( $show_id, 'lezshows_episodes', true ) ) ); ?>
		</div>
	</section>
	<?php
}

if ( $related ) {
	?>
	<section name="related-posts" id="related-posts" class="showschar-section">	
		<h2>Related Articles</h2>
		<div class="card-body">	
			<?php
				echo LWTV_Related_Posts::related_posts( $slug );
				if ( count ( LWTV_Related_Posts::count_related_posts( $slug ) ) > '5' ) {
					$tag = term_exists( $slug , 'post_tag' );
					if ( !is_null( $tag ) && $tag >= 1 ) {
						echo '<p><a href="' . get_tag_link( $tag['term_id'] ) . '">Read More ...</a></p>';
					}
				}
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
		$havecharcount = LWTV_CPT_Characters::list_characters( $show_id, 'count' );
		$havedeadcount = LWTV_CPT_Characters::list_characters( $show_id, 'dead' );
		
		if ( empty( $havecharcount ) || $havecharcount == '0' ) {
			echo '<p>There are no characters listed yet for this show.</p>';
		} else {
		
			$deadtext = 'none are dead';
			if ( $havedeadcount > '0' ) $deadtext = sprintf( _n( '<strong>%s</strong> is dead', '<strong>%s</strong> are dead', $havedeadcount ), $havedeadcount );
		
			echo '<p>There '. sprintf( _n( 'is <strong>%s</strong> queer character', 'are <strong>%s</strong> queer characters', $havecharcount ), $havecharcount ).' listed for this show; ' . $deadtext . '.</p>';
		
			// Get the list of REGULAR characters
			$chars_regular = lwtv_yikes_get_characters_for_show( $show_id, $havecharcount, 'regular' );
			if ( !empty( $chars_regular ) ) {
				?><h3 class="title-regulars">Regular<?php echo _n( '', 's', count( $chars_regular ) ); ?> (<?php echo count( $chars_regular ); ?>)</h3>
				<div class="container characters-regulars-container"><div class="row site-loop character-show-loop equal-height"><?php
				foreach( $chars_regular as $character ) {
					?><div class="col-sm-4"><?php
						include( locate_template( 'template-parts/excerpt-post_type_characters.php' ) );
					?></div><?php
				}
				echo '</div></div>';
			}
			// Get the list of RECURRING characters
			$chars_recurring = lwtv_yikes_get_characters_for_show( $show_id, $havecharcount, 'recurring' );
			if ( !empty( $chars_recurring ) ) {	
				?><h3 class="title-recurring">Recurring (<?php echo count( $chars_recurring ); ?>)</h3>
				<div class="container characters-recurring-container"><div class="row site-loop character-show-loop equal-height"><?php
				foreach( $chars_recurring as $character ) {
					?><div class="col-sm-4"><?php
						include( locate_template( 'template-parts/excerpt-post_type_characters.php' ) );
					?></div><?php
				}
				echo '</div></div>';
			}
			// Get the list of GUEST characters
			$chars_guest = lwtv_yikes_get_characters_for_show( $show_id, $havecharcount, 'guest' );
			if ( !empty( $chars_guest ) ) {
				?><h3 class="title-guest">Guest<?php echo _n( '', 's', count( $chars_guest ) ); ?> (<?php echo count( $chars_guest ); ?>)</h3>
				<ul class="guest-character-list"><?php
				foreach( $chars_guest as $character ) {
					$grave = ( has_term( 'dead', 'lez_cliches' , $character['id'] ) )? '<span role="img" aria-label="RIP Tombstone" title="RIP Tombstone" class="charlist-grave-sm">' . lwtv_yikes_symbolicons( 'rest-in-peace.svg', 'fa-times-circle' ) . '</span>' : '';
					?><li><a href="<?php the_permalink( $character['id'] ); ?>" title="<?php echo get_the_title( $character['id'] ); ?>" ><?php echo get_the_title( $character['id'] ) . ' ' . $grave; ?></a></li><?php
				}
				echo '</ul>';
			}
		}
		?>
	</div>
</section>