<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Actors → Roles: the Regular/Recurring/Guest breakdown across every
 * character a queer-role actor has played (amber ramp, matching the
 * "actors" family color used for this stat elsewhere on the site).
 *
 * Role type lives on the character's show-group repeater (one per show a
 * character appears in), not on the actor directly — see
 * Build_Actors::generate_roles_totals()'s docblock for why this is still
 * framed as an Actors-facing stat: it's "what kind of parts do the actors
 * behind queer characters tend to get," even though the underlying tally
 * runs across characters' show appearances, not actor postmeta.
 *
 * @package LezWatch.TV
 *
 * @var int $actor_count
 */

use LWTV\Statistics\Build\Role_Podium;

$roles_raw  = lwtv_plugin()->generate_actors_statistics( 'array', 'roles' );
$roles_data = ( is_array( $roles_raw ) && ! empty( $roles_raw ) ) ? (array) reset( $roles_raw ) : array();

$roles_counts = array();
foreach ( Role_Podium::ORDER as $roles_type ) {
	$roles_counts[ $roles_type ] = isset( $roles_data[ $roles_type ] ) ? (int) $roles_data[ $roles_type ]['count'] : 0;
}
$roles_facts = Role_Podium::facts( $roles_counts );

$roles_ramp = array(
	'regular'   => 'amber',
	'recurring' => 'medamber',
	'guest'     => 'ltamber',
);

$roles_segments = array();
foreach ( Role_Podium::ORDER as $roles_type ) {
	if ( $roles_counts[ $roles_type ] <= 0 ) {
		continue;
	}
	$roles_segments[] = array(
		'label' => $roles_data[ $roles_type ]['name'] ?? $roles_type,
		'count' => $roles_counts[ $roles_type ],
		'pct'   => $roles_facts['levels'][ $roles_type ]['share'] ?? 0,
		'class' => $roles_ramp[ $roles_type ],
	);
}

// translators: %1$s: leading role type's name (e.g. "Regular/Main Character"), %2$s: its share of all tagged appearances.
$roles_headline = ( '' !== $roles_facts['leader'] ) ? sprintf( __( '%1$s roles lead, at %2$s%%', 'lwtv' ), $roles_data[ $roles_facts['leader'] ]['name'] ?? '', number_format_i18n( $roles_facts['leader_share_pct'] ) ) : __( 'Role breakdown', 'lwtv' );

$donut = array(
	'segments'    => $roles_segments,
	'center'      => $roles_facts['sum'],
	'center_sub'  => __( 'tagged appearances', 'lwtv' ),
	'eyebrow'     => __( 'Character Role Type', 'lwtv' ),
	'headline'    => $roles_headline,
	'description' => __( 'Every show a character appears in is tagged Regular, Recurring, or Guest — this is the split across all of them.', 'lwtv' ),
);

// phpcs:ignore PEAR.Files.IncludingFile.UseRequire
include plugin_dir_path( __DIR__ ) . 'partials/donut.php';
