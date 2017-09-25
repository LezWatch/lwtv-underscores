<?php
/**
 * Template part for displaying posts
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package LezWatchTV
 */

// Generate Character Type
// Usage: $character_type	
$character_type = '';

// Generate list of shows
// Usage: $appears
$all_shows = get_post_meta( get_the_ID(), 'lezchars_show_group', true );
$show_title = array();

if ( $all_shows !== '' ) {
	foreach ( $all_shows as $each_show ) {
		if ( get_post_status ( $each_show['show'] ) !== 'publish' ) {
			array_push( $show_title, '<em><span class="disabled-show-link">' . get_the_title( $each_show['show'] ) . '</span></em> ('. $each_show['type'] .' character)' );
		} else {
			array_push( $show_title, '<em><a href="' . get_permalink( $each_show['show'] ) . '">' . get_the_title( $each_show['show'] ) . '</a></em> (' . $each_show['type'] . ' character)' );
		}
	}
}

$on_shows = ( empty( $show_title ) )? ' no shows.' : ': ' . implode( ", ", $show_title );
$appears = '<strong>Appears on</strong>' . $on_shows;

// Generate actors
// Usage: $actors
$character_actors = get_post_meta( get_the_ID(), 'lezchars_actor', true );
if ( !is_array ( $character_actors ) ) {
	$character_actors = array( get_post_meta( get_the_ID(), 'lezchars_actor', true ) );
}
$actor_count = count( $character_actors );
$actor_title = sprintf( _n( 'Actor', 'Actors', $actor_count ), $actor_count );
$actors = '<strong>' . $actor_title . ':</strong> ' . implode( ", ", $character_actors );

// Generate RIP
// Usage: $rip
$rip = '';
if ( get_post_meta( get_the_ID(), 'lezchars_death_year', true ) ) {
	$character_death = get_post_meta( get_the_ID(), 'lezchars_death_year', true );
	if ( !is_array ( $character_death ) ) {
		$character_death = array( get_post_meta( get_the_ID(), 'lezchars_death_year', true ) );
	}
	$echo_death = array();
	foreach( $character_death as $death ) {
		$date = date_create_from_format( 'm/j/Y', $death );
		$echo_death[] = date_format( $date, 'F d, Y');
	}
	$character_death = implode( ", ", $echo_death );
	$rip = '<strong>RIP:</strong> ' . $character_death;
}

// Generate list of Cliches
// Usage: $cliches
$lez_cliches = get_the_terms( get_the_ID(), 'lez_cliches' );
$cliches = '';
if ( $lez_cliches && ! is_wp_error( $lez_cliches ) ) {
    $cliches = ' &mdash; Clichés: ';
	foreach( $lez_cliches as $the_cliche ) {

		// Make sure Symbolicons exist. Display the name if not.
		$termicon = get_term_meta( $the_cliche->term_id, 'lez_termsmeta_icon', true );
		$tropicon = $termicon ? $termicon . '.svg' : 'square.svg';
		$icon     = lwtv_yikes_symbolicons( $tropicon, 'fa-square' );
		$cliches .= '&nbsp;<a href="' . get_term_link( $the_cliche->slug, 'lez_cliches') . '" rel="tag" class="character cliche cliche-' . $the_cliche->slug . '" title="' . $the_cliche->name . '"><span role="img" aria-label="' . $the_cliche->name . '" title="' . $the_cliche->name . '" class="character-cliche ' . $the_cliche->slug . '">' .$icon . '</span></a>';
	}
}

// Generate Gender & Sexuality Data
// Usage: $gender_sexuality
$gender_sexuality = '';
$gender_terms = get_the_terms( get_the_ID(), 'lez_gender', true );
if ( $gender_terms && ! is_wp_error( $gender_terms ) ) {
	foreach( $gender_terms as $gender_term ) {
		$gender_sexuality .= '<a href="' . get_term_link( $gender_term->slug, 'lez_gender') . '" rel="tag" title="' . $gender_term->name . '">' . $gender_term->name . '</a> ';
	}
}
$sexuality_terms = get_the_terms( get_the_ID(), 'lez_sexuality', true );
if ( $sexuality_terms && ! is_wp_error( $sexuality_terms ) ) {
	foreach($sexuality_terms as $sexuality_term) {
		$gender_sexuality .= '<a href="'. get_term_link( $sexuality_term->slug, 'lez_sexuality')  .'" rel="tag" title="' . $sexuality_term->name . '">' . $sexuality_term->name . '</a> ';
	}
}
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>

		<div class="entry-meta">
			
		</div><!-- .entry-meta -->
	</header><!-- .entry-header -->

	<div class="entry-content">
			<div class="shows-list-character-content">
				<p><span class="entry-meta">
					<a href="<?php the_permalink(); ?>"><?php echo get_the_post_thumbnail( get_the_ID(), 'character-img', array( 'alt' => get_the_title(), 'title' => get_the_title() ) ); ?></a>
			
					<?php if ( $character_type !== '' ) echo '('.$character_type .')'; ?>
			
					<br /><?php echo $gender_sexuality . $cliches; ?>
					<br /><?php echo $actors; ?>
					<?php if ( count( $show_title ) !== 0 ) echo '<br />'. $appears; ?>
					<?php if ( isset( $rip ) ) echo '<br />' . $rip ; ?>
				</span></p>
			
				<div class="characters-description">
					<p><?php echo the_content(); ?></p>
				</div>
			</div>

		<?php			
			wp_link_pages( array(
				'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'lwtv_yikes' ),
				'after'  => '</div>',
			) );
		?>
	</div><!-- .entry-content -->

	<footer class="entry-footer">
		<?php yikes_starter_entry_footer(); ?>
	</footer><!-- .entry-footer -->
</article><!-- #post-## -->