<?php
/*
 * The AMP template for characters.
 *
 * Customized AMP Template used by the custom post types.
 * This file is called by /inc/amp.php - if that file is
 * missing, this does nothing.
 * 
 * @package LezWatchTV
 */
?>

<!doctype html>
<html amp <?php echo AMP_HTML_Utils::build_attributes_string( $this->get( 'html_tag_attributes' ) ); ?>>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no">
	<?php do_action( 'amp_post_template_head', $this ); ?>
	<style amp-custom>
		<?php
			include_once( WP_PLUGIN_DIR . '/amp/templates/style.php' );
			do_action( 'amp_post_template_css', $this );
		?>
	</style>
</head>

<body class="<?php echo esc_attr( $this->get( 'body_class' ) ); ?>">

<?php include_once( WP_PLUGIN_DIR . '/amp/templates/header-bar.php' ); ?>

<article class="amp-wp-article">

	<header class="amp-wp-article-header">
		<h1 class="amp-wp-title"><?php echo wp_kses_data( $this->get( 'post_title' ) ); ?></h1>
	</header>

	<div class="amp-wp-article-content">

		<?php
			echo $this->get( 'post_amp_content' );
		?>

	</div>

	<footer class="amp-wp-article-footer">
		<section class="amp-wp-show-footer">
		<?php
			// Generate Character Type
			// Usage: $character_type
			$character_type = '';
	
			// Generate list of shows
			// Usage: $appears
			$all_shows = get_post_meta( get_the_ID(), 'lezchars_show_group', true );
			$show_title = array();
			foreach ( $all_shows as $each_show ) {
				array_push( $show_title, '<em><a href="' . get_permalink( $each_show['show'] ) . '">' . get_the_title( $each_show['show'] ) . '</a></em> (' . $each_show['type'] . ' character)' );
			}
			$on_shows = ( empty( $show_title ) )? ' no shows.' : ': ' . implode( ", ", $show_title );
			$appears = '<strong>Appears on</strong>' . $on_shows;
	
			// Generate actors
			// Usage: $actors
			$all_actors  = lwtv_yikes_chardata( get_the_ID(), 'actors' );
			if ( $all_actors !== '' ) {
				$the_actors = array();
				foreach ( $all_actors as $each_actor ) {
					if ( get_post_status ( $each_actor ) !== 'publish' ) {
						array_push( $the_actors, '<span class="disabled-show-link">' . get_the_title( $each_actor ) . '</span>' );
					} else {
						array_push( $the_actors, '<a href="' . get_permalink( $each_actor ) . '">' . get_the_title( $each_actor ) . '</a>' );
					}
				}
			}
			
			$is_actors = ( empty( $the_actors ) )? ' None' : ': ' . implode( ', ', $the_actors );
			if ( isset( $the_actors ) && count( $the_actors ) !== 0 ) {
				$actor_title = _n( 'Actor', 'Actors', count( $all_actors ) );
				$actors  = '<strong>' . $actor_title . '</strong>' . $is_actors;
			}
	
			// Generate RIP
			// Usage: $rip
			$rip = '';
			if ( get_post_meta( get_the_ID(), 'lezchars_death_year', true) ) {
				$character_death = get_post_meta( get_the_ID(), 'lezchars_death_year', true);
				if ( !is_array ( $character_death ) ) {
					$character_death = array( get_post_meta( get_the_ID(), 'lezchars_death_year', true) );
				}
				$echo_death = array();
				foreach( $character_death as $death ) {
					if ( (int) substr( $death, 0, 4 ) == substr( $death, 0, 4 ) ) {
						$date = date_format( date_create_from_format( 'Y-m-d', $death ), 'F d, Y');
					} else {
						$date = date_format( date_create_from_format( 'm/d/Y', $death ), 'F d, Y');
					}
					$echo_death[] = $date;
				}
				$echo_death = implode( '; ', $echo_death );
				$rip = '<p><strong>RIP:</strong> ' . $echo_death . '</p>';
			}

			// Generate Status
			// Usage: $dead_or_alive
			$doa_status    = ( has_term( 'dead', 'lez_cliches' , get_the_ID() ) )? 'Dead' : 'Alive';
			$dead_or_alive = '<strong>Status:</strong> ' . $doa_status;


			// Generate list of Cliches
			// Usage: $cliches
			$cliches = get_the_term_list( get_the_ID(), 'lez_cliches', '<strong>Clichés:</strong> ', ', ' );
	
			// Generate Gender & Sexuality Data
			// Usage: $gender_sexuality
			$gender_sexuality = '';
			$gender_terms = get_the_terms( get_the_ID(), 'lez_gender', true );
			if ( $gender_terms && ! is_wp_error( $gender_terms ) ) {
				foreach( $gender_terms as $gender_term ) {
					$gender_sexuality .= '<a href="' . get_term_link( $gender_term->slug, 'lez_gender' ) . '" rel="tag" title="' . $gender_term->name . '">' . $gender_term->name . '</a> ';
				}
			}
			$sexuality_terms = get_the_terms( get_the_ID(), 'lez_sexuality', true );
			if ( $sexuality_terms && ! is_wp_error( $sexuality_terms ) ) {
				foreach( $sexuality_terms as $sexuality_term ) {
					$gender_sexuality .= '<a href="' . get_term_link( $sexuality_term->slug, 'lez_sexuality' ) . '" rel="tag" title="' . $sexuality_term->name . '">' . $sexuality_term->name . '</a> ';
				}
			}
	
			$content =
				  '<p>' . $gender_sexuality . '</p>'
				. '<p><span class="entry-meta">' . $cliches .'</span></p>'
				. '<p>' . $actors . '</p>'
				. '<p>' . $appears . '</p>'
				. '<p>' . $dead_or_alive . '</p>'
				. $rip;
	
			echo $content;
		?>
		</section>
	</footer>

</article>

<footer class="amp-wp-footer">
	<div>
		<h2><?php echo esc_html( $this->get( 'blog_name' ) ); ?></h2>
		<p>
			<a href="<?php echo esc_url( __( 'https://wordpress.org/', 'amp' ) ); ?>"><?php printf( __( 'Powered by %s', 'amp' ), 'WordPress' ); ?></a>
		</p>
		<a href="#top" class="back-to-top"><?php _e( 'Back to top', 'amp' ); ?></a>
	</div>
</footer>

<?php do_action( 'amp_post_template_footer', $this ); ?>

</body>
</html>