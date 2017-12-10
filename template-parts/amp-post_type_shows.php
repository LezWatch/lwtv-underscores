<?php
/*
 * The AMP template for shows.
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
			$warning = lwtv_yikes_content_warning( get_the_ID() );
			if ( $warning['card'] != 'none' ) {
				?>
				<div class="callout trigger-<?php echo $warning['card']; ?>" role="alert">
					<?php echo $warning['content']; ?>
				</div>
				<?php
			}

			echo $this->get( 'post_amp_content' );

			$havecharacters = LWTV_CPT_Characters::list_characters( $this->ID, 'query' );
			$havecharcount  = LWTV_CPT_Characters::list_characters( $this->ID, 'count' );
			$havedeadcount  = LWTV_CPT_Characters::list_characters( $this->ID, 'dead' );

			if( (get_post_meta($this->ID, "lezshows_plots", true) )  ) { ?>
				<section id="timeline" class="shows-extras"><h2>Queer Plotline Timeline</h2>
				<?php echo wp_kses_post( get_post_meta($this->ID, 'lezshows_plots', true) ); ?></section><?php
			}

			if((get_post_meta($this->ID, "lezshows_episodes", true))) { ?>
				<section id="episodes" class="shows-extras"><h2>Notable Lez-Centric Episodes</h2>
				<?php echo wp_kses_post( get_post_meta($this->ID, 'lezshows_episodes', true) ); ?></section> <?php
			}

			echo '<section id="characters" class="shows-extras">';
			echo '<h2>Characters ('. $havecharcount .')</h2>';

			if ( empty($havecharacters) ) {
				echo '<p>There are no queers listed for this show.</p>';
			} else {
				foreach( $havecharacters as $character ) {
				?>
					<ul class="character-list">
						<li class="clearfix"><a href="<?php echo $character['url']; ?>amp/"><?php echo $character['title']; ?></a></li>
					</ul>
				<?php
				}
			}
		?>

	</div>

	<footer class="amp-wp-article-footer">
		<?php
			echo '<section class="amp-wp-show-footer">';
			$thumb_rating = get_post_meta( $this->ID, 'lezshows_worthit_rating', true);
			$networks = get_the_terms( $this->ID, 'lez_stations' );
			$airdates = get_post_meta($this->ID, 'lezshows_airdates', true);
			$tropes = get_the_terms( $this->ID, 'lez_tropes' );
			$realness_rating = (int) get_post_meta($this->ID, 'lezshows_realness_rating', true);
			$realness_rating = min( $realness_rating, 5 );
			$show_quality = (int) get_post_meta($this->ID, 'lezshows_quality_rating', true);
			$show_quality = min ( $show_quality, 5 );
			$screen_time = (int) get_post_meta($this->ID, 'lezshows_screentime_rating', true);
			$screen_time = min( $screen_time, 5 );

			$info_array = $ratings_array = array();

			if ( $thumb_rating ) {
				$info_array['worthit'] = '<strong>Worth Watching?</strong> '.$thumb_rating;
			}

			if ( $networks && ! is_wp_error( $networks ) ) {
				$info_array['networks'] = get_the_term_list( $this->ID, 'lez_stations', '<strong>Network(s):</strong> ', ', ' );
			}
			if ( $airdates ) {
				$airdate  = $airdates['start'] . ' - ' . $airdates['finish'];
				if ( $airdates['start'] == $airdates['finish'] ) { $airdate = $airdates['finish']; }
				$info_array['airdates'] =  '<strong>Airdates:</strong> '. $airdate;
			}

			if ( $tropes && ! is_wp_error( $tropes ) ) {
				$trope_echo = get_the_term_list( $this->ID, 'lez_tropes', '<p class="entry-meta"><strong>Tropes:</strong> ', ', ' );
			}

			if ( $realness_rating ) {
				$ratings_array['realness'] = '<strong>Realness:</strong> '.$realness_rating.' (out of 5)';
			}
			if ( $show_quality ) {
				$ratings_array['quality'] = '<strong>Quality:</strong> '.$show_quality.' (out of 5)';
			}
			if ( $screen_time ) {
				$ratings_array['screen_time'] = '<strong>Screen Time:</strong> '.$screen_time.' (out of 5)';
			}

			echo '<center>';
			echo '<p class="entry-meta">'.join(' &bull; ', $info_array).'</p>';
			if ( $trope_echo ) echo $trope_echo;
			echo '<p class="entry-meta">'.join(' &bull; ', $ratings_array).'</p>';
			echo '</center>';
			echo '</section>';
		?>
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