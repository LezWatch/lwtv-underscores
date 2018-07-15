<?php
/*
 * The AMP template for characters.
 *
 * Customized AMP Template used by the custom post types.
 * This file is called by /inc/amp.php - if that file is
 * missing, this does nothing.
 *
 * @package LezWatch.TV
 */
?>

<!doctype html>
<html amp <?php echo AMP_HTML_Utils::build_attributes_string( $this->get( 'html_tag_attributes' ) ); // WPCS: XSS okay. ?>>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1,minimum-scale=1,maximum-scale=1,user-scalable=no">
	<?php do_action( 'amp_post_template_head', $this ); ?>
	<style amp-custom>
		<?php
			require_once WP_PLUGIN_DIR . '/amp/templates/style.php';
			do_action( 'amp_post_template_css', $this );
		?>
	</style>
</head>

<body class="<?php echo esc_attr( $this->get( 'body_class' ) ); ?>">

<?php require_once WP_PLUGIN_DIR . '/amp/templates/header-bar.php'; ?>

<article class="amp-wp-article">

	<header class="amp-wp-article-header">
		<h1 class="amp-wp-title"><?php echo wp_kses_data( $this->get( 'post_title' ) ); ?></h1>
	</header>

	<div class="amp-wp-article-content">

		<?php
		if ( ! empty( $this->get( 'post_amp_content' ) ) ) {
			echo lwtv_sanitized( $this->get( 'post_amp_content' ) );
		} else {
			the_title( '<p>', ' is an actor who has played at least one character on TV.</p>' );
		}

		$all_chars = lwtv_yikes_actordata( get_the_ID(), 'characters' );

		echo '<section id="characters" class="shows-extras">';
		echo '<h2>Characters</h2>';

		if ( empty( $all_chars ) || '0' === count( $all_chars ) ) {
			echo '<p>There are no characters listed yet for this actor.</p>';
		} else {
			// translators: %s is the number of characters
			echo '<p>There ' . lwtv_sanitized( sprintf( _n( 'is <strong>%s</strong> character', 'are <strong>%s</strong> characters', count( $all_chars ) ), count( $all_chars ) ) ) . ' played by this actor.</p>';

			echo '<div class="container characters-regulars-container"><div class="row site-loop character-show-loop equal-height">';
			foreach ( $all_chars as $character ) {
				?>
				<ul class="character-list">
					<li class="clearfix"><a href="<?php echo esc_url( $character['url'] ); ?>amp/"><?php echo wp_kses_post( $character['title'] ); ?></a></li>
				</ul>
				<?php
			}
			echo '</div></div>';
		}
		?>
	</div>

	<footer class="amp-wp-article-footer">
		<section class="amp-wp-show-footer">
			<?php
			$gender    = lwtv_yikes_actordata( get_the_ID(), 'gender', true );
			$sexuality = lwtv_yikes_actordata( get_the_ID(), 'sexuality', true );

			$urls = array();
			if ( get_post_meta( get_the_ID(), 'lezactors_imdb', true ) ) {
				$urls['IMDb'] = esc_url( 'https://imdb.com/name/' . get_post_meta( get_the_ID(), 'lezactors_imdb', true ) );
			}
			if ( get_post_meta( get_the_ID(), 'lezactors_wikipedia', true ) ) {
				$urls['WikiPedia'] = esc_url( get_post_meta( get_the_ID(), 'lezactors_wikipedia', true ) );
			}

			if ( isset( $gender ) && ! empty( $gender ) ) {
				echo wp_kses_post( $gender );
			}
			if ( isset( $gender ) && ! empty( $gender ) && isset( $sexuality ) && ! empty( $sexuality ) ) {
				echo ' &bull; ';
			}
			if ( isset( $sexuality ) && ! empty( $sexuality ) ) {
				echo wp_kses_post( $sexuality );
			}

			$life = array();
			if ( get_post_meta( get_the_ID(), 'lezactors_birth', true ) ) {
				$get_birth     = date_create_from_format( 'Y-m-d', get_post_meta( get_the_ID(), 'lezactors_birth', true ) );
				$life['birth'] = date_format( $get_birth, 'F d, Y' );
			}
			if ( get_post_meta( get_the_ID(), 'lezactors_death', true ) ) {
				$get_death     = date_create_from_format( 'Y-m-d', get_post_meta( get_the_ID(), 'lezactors_death', true ) );
				$life['death'] = date_format( $get_death, 'F d, Y' );
			}

			$life_total = count( $life );
			$life_count = 1;
			foreach ( $life as $event => $date ) {
				echo '<strong>' . wp_kses_post( ucfirst( $event ) ) . '</strong>: ' . wp_kses_post( $date );
				if ( $life_count !== $life_total ) {
					echo ' &bull; ';
				}
				$life_count++;
			}

			$urls_total = count( $urls );
			$urls_count = 1;
			foreach ( $urls as $source => $link ) {
				echo '<a href="' . esc_url( $link ) . '">' . esc_html( $source ) . '</a>';
				if ( $urls_count !== $urls_total ) {
					echo ' &bull; ';
				}
				$urls_count++;
			}
			?>
		</section>
	</footer>

</article>

<footer class="amp-wp-footer">
	<div>
		<h2><?php echo esc_html( $this->get( 'blog_name' ) ); ?></h2>
		<p>
			<a href="<?php echo esc_url( __( 'https://wordpress.org/', 'amp' ) ); ?>">
			<?php
				// translators: %s is ... WordPress.
				printf( esc_html__( 'Powered by %s', 'amp' ), 'WordPress' );
			?>
			</a>
		</p>
		<a href="#top" class="back-to-top"><?php esc_html_e( 'Back to top', 'amp' ); ?></a>
	</div>
</footer>

<?php do_action( 'amp_post_template_footer', $this ); ?>

</body>
</html>
