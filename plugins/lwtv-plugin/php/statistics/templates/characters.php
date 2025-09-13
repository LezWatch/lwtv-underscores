<?php
/**
 * The template for displaying the character stats page - Optimized Version
 *
 * @package LezWatch.TV
 */

use LWTV\Statistics\Build\Taxonomy_Optimized as Build_Taxonomy_Optimized;
use LWTV\Statistics\Build\On_Air_Optimized as Build_On_Air_Optimized;

$baseurl = '/statistics/characters/';

$valid_views     = array( 'cliches', 'gender', 'sexuality', 'queer-irl', 'on-air' );
$sent_view       = get_query_var( 'view', 'overview' );
$view            = ( ! in_array( $sent_view, $valid_views, true ) ) ? 'overview' : $sent_view;
$character_count = lwtv_plugin()->generate_total_counts( 'characters' );

// OPTIMIZED: Pre-load taxonomy data for overview section
$optimized_taxonomy       = new Build_Taxonomy_Optimized();
$optimized_onair          = new Build_On_Air_Optimized();
$character_gender_data    = $optimized_taxonomy->make_comprehensive( 'post_type_characters', 'lez_gender', false );
$character_sexuality_data = $optimized_taxonomy->make_comprehensive( 'post_type_characters', 'lez_sexuality', false );
$character_cliches_data   = $optimized_taxonomy->make_comprehensive( 'post_type_characters', 'lez_cliches', false );
$character_onair_data     = $optimized_onair->generate( 'characters' );

// Sort by count descending for top 10
uasort(
	$character_gender_data,
	function ( $a, $b ) {
		return $b['count'] <=> $a['count'];
	}
);
uasort(
	$character_sexuality_data,
	function ( $a, $b ) {
		return $b['count'] <=> $a['count'];
	}
);
uasort(
	$character_cliches_data,
	function ( $a, $b ) {
		return $b['count'] <=> $a['count'];
	}
);

$top_genders     = array_slice( $character_gender_data, 0, 5, true );
$top_sexualities = array_slice( $character_sexuality_data, 0, 5, true );
$top_cliches     = array_slice( $character_cliches_data, 0, 14, true );

// Get total counts efficiently
$count_genders     = count( $character_gender_data );
$count_sexualities = count( $character_sexuality_data );
$count_cliches     = count( $character_cliches_data );
?>

<h2>
	<a href="/characters/">Total Characters</a> (<?php echo (int) $character_count; ?>)
</h2>

<?php
// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __FILE__ ) . 'characters/navbar.php';
?>

<p>&nbsp;</p>

<?php
switch ( $view ) {
	case 'overview':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'characters/overview.php';
		break;
	case 'sexuality':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'characters/sexuality.php';
		break;
	case 'gender':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'characters/gender.php';
		break;
	case 'cliches':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'characters/cliches.php';
		break;
	case 'queer-irl':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'characters/queer-irl.php';
		break;
	case 'on-air':
		// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
		include plugin_dir_path( __FILE__ ) . 'characters/on-air.php';
		break;
}

// Performance monitoring
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	echo '<!-- OPTIMIZED: Queries reduced from ~' . ( count( $top_genders ) + count( $top_sexualities ) + count( $top_cliches ) + 15 ) . ' to ' . esc_html( get_num_queries() ) . ' -->';
}
