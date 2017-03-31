<?php
/*
 * This shows the excerpts for characters content
 * It's used on both the show page and the character page
 */

global $post;

if ( empty($character) ) {
	$character = array(
		'id'        => get_the_ID(),
		'title'     => get_the_title(),
		'url'       => get_the_permalink(),
		'content'   => '',
		'shows'     => get_post_meta( get_the_ID(), 'lezchars_show_group', true ),
		'show_from' => 0,
	);
}

/*
 * Prep all the data
 */

// Generate The Title
// Usage: $the_title

$the_title = '<a href="'. $character['url'] .'">'. $character['title'] .'</a>';
if ( is_singular( 'post_type_characters' ) ) {
	$the_title = '';
}

// Generate Character Type
// Usage: $character_type

$character_type = '';

// Generate list of shows
// Usage: $appears

$all_shows = $character['shows'];
$show_title = array();

if ( $all_shows !== '' ) {
	foreach ( $all_shows as $each_show ) {
		// IF the show ID passed through is the same as the ID we're checking, don't count
		if ( $character['show_from'] == $each_show['show'] ) {
			$character_type = ucfirst( esc_html( $each_show['type'] ) ) . ' Character';
		}
		// if the show isn't published, no links
		elseif ( get_post_status ( $each_show['show'] ) !== 'publish' ) {
			array_push( $show_title, '<em><span class="disabled-show-link">' . get_the_title( $each_show['show'] ) . '</span></em> ('. $each_show['type'] .' character)' );
		}
		// If neither of the above are the case, party time!
		else {
			array_push( $show_title, '<em><a href="' . get_permalink( $each_show['show'] ) . '">' . get_the_title( $each_show['show'] ) . '</a></em> ('. $each_show['type'] .' character)' );
		}
	}
}

$on_shows = ( empty( $show_title ) )? ' no shows.' : ': '.implode(", ", $show_title );
$appears = ( $character['show_from'] !== 0 && count( $show_title ) !== 0 )? 'Also appears on' : 'Appears on';
$appears = '<strong>'.$appears.'</strong>' . $on_shows;

// Generate actors
// Usage: $actors

$character_actors = get_post_meta( $character['id'], 'lezchars_actor', true);
if ( !is_array ( $character_actors ) ) {
	$character_actors = array( get_post_meta( $character['id'], 'lezchars_actor', true) );
}
$actor_count = count( $character_actors );
$actor_title = sprintf( _n( 'Actor', 'Actors', $actor_count ), $actor_count );
$actors = '<strong>'. $actor_title .':</strong> '.implode(", ", $character_actors );

// Generate RIP
// Usage: $rip

$rip = '';
if ( get_post_meta( $character['id'], 'lezchars_death_year', true) ) {
	$character_death = get_post_meta( $character['id'], 'lezchars_death_year', true);
	if ( !is_array ( $character_death ) ) {
		$character_death = array( get_post_meta( $character['id'], 'lezchars_death_year', true) );
	}
	$character_death = implode(", ", $character_death );
	$rip = '<strong>RIP:</strong> '. $character_death;
}

// Generate list of Cliches
// Usage: $cliches

$lez_cliches = get_the_terms( $character['id'], 'lez_cliches' );
$cliches = '';
if ( $lez_cliches && ! is_wp_error( $lez_cliches ) ) {
    $cliches = ' &mdash; Clichés: ';
	foreach($lez_cliches as $the_cliche) {
		$iconpath = LWTV_SYMBOLICONS_PATH.'/svg/';
		$termicon = get_term_meta( $the_cliche->term_id, 'lez_termsmeta_icon', true );
		$icon = ( $termicon && file_exists( $iconpath.$termicon.'.svg' ) )? $termicon.'.svg' : 'square.svg';
		$cliches .= '&nbsp;<a href="'. get_term_link( $the_cliche->slug, 'lez_cliches') .'" rel="tag" class="character cliche cliche-'. $the_cliche->slug .'" title="'. $the_cliche->name .'"><span role="img" aria-label="'. $the_cliche->name .'" title="'. $the_cliche->name .'" class="character-cliche '. $the_cliche->slug .'">'. file_get_contents( $iconpath . $icon ) .'</span></a>';
	}
}

// Generate Gender & Sexuality Data
// Usage: $gender_sexuality
$gender_sexuality = '';
$gender_terms = get_the_terms( $character['id'], 'lez_gender', true);
if ( $gender_terms && ! is_wp_error( $gender_terms ) ) {
	foreach($gender_terms as $gender_term) {
		$gender_sexuality .= '<a href="'. get_term_link( $gender_term->slug, 'lez_gender') .'" rel="tag" title="'. $gender_term->name .'">'. $gender_term->name .'</a> ';
	}
}
$sexuality_terms = get_the_terms( $character['id'], 'lez_sexuality', true);
if ( $sexuality_terms && ! is_wp_error( $sexuality_terms ) ) {
	foreach($sexuality_terms as $sexuality_term) {
		$gender_sexuality .= '<a href="'. get_term_link( $sexuality_term->slug, 'lez_sexuality') .'" rel="tag" title="'. $sexuality_term->name .'">'. $sexuality_term->name .'</a> ';
	}
}
?>
<div class="shows-list-character-content">
	<p><span class="entry-meta">
		<a href="<?php echo $character['url']; ?>"><?php echo get_the_post_thumbnail( $character['id'], 'character-img', array( 'alt' => $character['title'] , 'title' => $character['title'] ) ); ?></a>
	
		<?php echo $the_title; ?> 
		<?php if ( $character_type !== '' ) echo '('.$character_type .')'; ?>

		<br /><?php echo $gender_sexuality . $cliches; ?>
		<br /><?php echo $actors; ?>
		<?php if ( count( $show_title ) !== 0 ) echo '<br />'. $appears; ?>
		<?php if ( isset( $rip ) ) echo '<br />' . $rip ; ?>
	</span></p>

	<div class="characters-description">
		<p><?php 
			if ( $character['content'] !== '' ) { 
				echo $character['content']; 
			} else {
				the_content();
			}
			?></p>
	</div>

</div>