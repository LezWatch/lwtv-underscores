<?php
/**
* Template Name: This Year
* Description: Used to show the yearly data of lezbians and what not.
*/

$thisyear = (int) (isset( $wp_query->query['thisyear'] ) )? $wp_query->query['thisyear'] : date('Y');

if ( !is_numeric( $thisyear ) ){
	wp_redirect( get_site_url().'/this-year/' , '301' );
	exit;
}

$previousurl = site_url( '/this-year/'.( $thisyear - 1 ).'/' );
$nexturl     = site_url( '/this-year/'.( $thisyear + 1 ).'/' );
$thisurl     = site_url( '/this-year/'. date('Y') .'/' );
$iconpath    = '<span role="img" aria-label="post_type_characters" title="Characters" class="taxonomy-svg characters">' . lwtv_yikes_symbolicons( 'calendar_alt.svg', 'fa-calendar' ) . '</span>';


function lwtv_underscore_this_year_dead( $thisyear ) {

	$dead_chars_loop = LWTV_Loops::post_meta_query( 'post_type_characters', 'lezchars_death_year', $thisyear, 'REGEXP');
	$dead_chars_query = wp_list_pluck( $dead_chars_loop->posts, 'ID' );

	// List all queers and the year they died
	if ( $dead_chars_loop->have_posts() ) {
		$death_list_array = array();
		?>
		<h3>Characters Died This Year (<?php echo $dead_chars_loop->post_count; ?>)</h3>

		<?php
			foreach( $dead_chars_query as $dead_char ) {
				// Since SOME characters have multiple shows, we force this to be an array
				$show_IDs = get_post_meta( $dead_char, 'lezchars_show_group', true );
				$show_title = array();

				foreach ( $show_IDs as $each_show ) {
					// if the show isn't published, no links
					if ( get_post_status ( $each_show['show'] ) !== 'publish' ) {
						array_push( $show_title, '<em><span class="disabled-show-link">' . get_the_title( $each_show['show'] ) . '</span></em> ('. $each_show['type'] .' character)' );
					} else {
						array_push( $show_title, '<em><a href="' . get_permalink( $each_show['show'] ) . '">' . get_the_title( $each_show['show'] ) . '</a></em> ('. $each_show['type'] .' character)' );
					}
				}

				$show_info = implode(", ", $show_title );

				// Only extract the date for this year and convert to unix time
				// Jesus, I hope no one dies twice in the same year....
				$died_date = get_post_meta( $dead_char, 'lezchars_death_year', true);
				foreach ( $died_date as $date ) {
					$died_year = substr($date, -4);
					if ( $died_year == $thisyear ) {
						$died_array = date_parse_from_format( 'm/d/Y' , $date);
						$died = mktime( $died_array['hour'], $died_array['minute'], $died_array['second'], $died_array['month'], $died_array['day'], $died_array['year'] );
					}
				}

				// Get the post slug
				$post_slug = get_post_field( 'post_name', get_post( $dead_char ) );

				$death_list_array[$post_slug] = array(
					'name'  => get_the_title( $dead_char ),
					'url'   => get_the_permalink( $dead_char ),
					'shows' => $show_info,
					'died'  => $died,
				);
			}

			// Reorder all the dead to sort by DoD
			uasort($death_list_array, function($a, $b) {
				return $a['died'] <=> $b['died'];
			});
		?>
		<ul>
			<?php
				foreach ( $death_list_array as $dead ) {
					echo '<li><a href="'.$dead['url'].'">'.$dead['name'].'</a> / '.$dead['shows'].' / '.date( 'd F', $dead['died']).' </li>';
				}
			?>
		</ul>
		<?php
	} else {
		?>
		<h3>A Miracle Occurred!</h3>

		<p>No one died in <?php echo $thisyear; ?></p>
		<?php
	}
	wp_reset_query();
}

function lwtv_underscore_this_year_shows( $thisyear ) {

	// Constants
	$shows_this_year = array( 'current' => 0, 'ended' => 0, 'started' => 0);
	$shows_current = array();
	$shows_started = array();
	$shows_ended = array();

	$all_shows_query = LWTV_Loops::post_type_query( 'post_type_shows' );

	if ($all_shows_query->have_posts() ) {
		while ( $all_shows_query->have_posts() ) {
			$all_shows_query->the_post();

			$show_id = get_the_ID();
			$show_name = preg_replace('/\s*/', '', get_the_title( $show_id ));
			$show_name = strtolower($show_name);

			// Shows Currently Airing
			if ( get_post_meta( $show_id, 'lezshows_airdates', true) ) {
				$airdates = get_post_meta( $show_id, 'lezshows_airdates', true);

				if (
					( $airdates['finish'] == 'current' && $thisyear == date('Y') ) // Still Current and it's NOW
			//		|| ($airdates['finish'] == $thisyear ) // Finished this year
					|| ( $airdates['finish'] >= $thisyear && $airdates['start'] <= $thisyear ) // Airdates between
				) {
				// Currently Airing Shows shows for the current year only
					$shows_current[$show_name] = array(
						'url'    => get_permalink( $show_id ),
						'name'   => get_the_title( $show_id ),
						'status' => get_post_status( $show_id ),
					);
					$shows_this_year['current']++;
				}

				// Shows that ended this year
				if( $airdates['finish'] == $thisyear ) {
					$shows_ended[$show_name] = array(
						'url'    => get_permalink( $show_id ),
						'name'   => get_the_title( $show_id ),
						'status' => get_post_status( $show_id ),
					);
					$shows_this_year['ended']++;
				}

				// Shows that STARTED this year
				if ( $airdates['start'] == $thisyear ) {
					$shows_started[$show_name] = array(
						'url'    => get_permalink( $show_id ),
						'name'   => get_the_title( $show_id ),
						'status' => get_post_status( $show_id ),
					);
					$shows_this_year['started']++;
				}
			}
		}
	}

	?>
	<div id="statistics">
	<?php

	if ( $shows_this_year['current'] !== 0 ) {
		echo '<h3>Shows Aired This Year ('.$shows_this_year['current'].')</h3>';
		echo '<ul>';
		foreach ( $shows_current as $show ) {
			$show_output = $show['name'];
			if ( $show['status'] == 'publish' ) {
				$show_output = '<a href="'.$show['url'].'">'.$show['name'].'</a>';
			}
			echo '<li>'.$show_output.'</li>';
		}
		echo '</ul>';
	} else {
		?>
		<h3>No Shows Aired This Year</h3>

		<p>No shows with known lesbian characters aired in <?php echo $thisyear; ?>.</p>

		<?php
	}
	?>
	</div>

	<hr>

	<div id="statistics">
	<?php
	if ( $shows_this_year['started'] !== 0 ) {
		echo '<h3>Shows Started This Year ('.$shows_this_year['started'].')</h3>';
		echo '<ul>';
		foreach ( $shows_started as $show ) {
			$show_output = $show['name'];
			if ( $show['status'] == 'publish' ) {
				$show_output = '<a href="'.$show['url'].'">'.$show['name'].'</a>';
			}
			echo '<li>'.$show_output.'</li>';
		}
		echo '</ul>';
	} else {
		?>
		<h3>No Shows Started This Year</h3>
		<p>No shows with known lesbian characters premiered in <?php echo $thisyear; ?>.</p>
		<?php
	}
	?>
	</div>

	<hr>

	<div id="statistics">
	<?php
	if ( $shows_this_year['ended'] !== 0 ) {
		echo '<h3>Shows Ended This Year ('.$shows_this_year['ended'].')</h3>';
		echo '<ul>';
		foreach ( $shows_ended as $show ) {
			$show_output = $show['name'];
			if ( $show['status'] == 'publish' ) {
				$show_output = '<a href="'.$show['url'].'">'.$show['name'].'</a>';
			}
			echo '<li>'.$show_output.'</li>';
		}
		echo '</ul>';
	} else {
		?>
		<h3>No Shows Ended This Year</h3>
		<p>No shows with known lesbian characters ended in <?php echo $thisyear; ?>.</p>
		<?php
	}
	?>

	</div>

	<?php
	wp_reset_query();
}

get_header(); 
?>

<div class="archive-subheader">
	<div class="jumbotron">
		<div class="container">
			<header class="archive-header">
				<div class="archive-description">
					<h1 class="archive-title">
						In This Year - <?php echo $thisyear; ?>
						<?php echo ( isset( $iconpath ) ? $iconpath : '' ); ?>
					</h1>
					<p>A recap of queer events that occurred in <?php echo $thisyear; ?></p>
					<div class="archive-pagination pagination">
						<ul>
							<?php
							if ( $thisyear !== date('Y') ) {
								?>
								<li class="pagination-next"><a href="<?php echo $nexturl; ?>">« Next Year</a></li>
								<li><a href="<?php echo $thisurl; ?>">This Year (<?php echo date('Y'); ?>)</a></li>
								<?php
							}
							?>
							<li class="pagination-previous"><a href="<?php echo $previousurl; ?>">Previous Year »</a></li>
						</ul>
					</div>
				</div>
			</header><!-- .archive-header -->
		</div><!-- .container -->
	</div><!-- /.jumbotron -->
</div>

<div id="main" class="site-main" role="main">
	<div class="container">
		<div class="row">
			<div class="col-sm-9">
				<div id="primary" class="content-area">
					<div id="content" class="site-content clearfix" role="main">				
						<?php
							lwtv_underscore_this_year_dead( $thisyear );
							lwtv_underscore_this_year_shows( $thisyear ); 
						?>
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- .col-sm-9 -->
			<div class="col-sm-3 site-sidebar site-loop">
				<?php get_sidebar(); ?>
			</div><!-- .col-sm-3 -->
		</div><!-- .row -->
	</div><!-- .container -->
</div><!-- #main -->

<?php get_footer(); ?>
