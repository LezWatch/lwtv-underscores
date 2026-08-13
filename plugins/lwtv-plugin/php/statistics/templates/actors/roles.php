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
use LWTV\Statistics\Build\Actors as Build_Actors;

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

// ---- Pullstats: total tagged appearances, leading type's share, guest share ----
$roles_pullstats = array();

if ( $roles_facts['sum'] > 0 ) {
	$roles_pullstats[] = array(
		'icon'   => 'tag.svg',
		'number' => number_format_i18n( $roles_facts['sum'] ),
		'label'  => __( 'Tagged show appearances, across every role type.', 'lwtv' ),
	);
}

if ( '' !== $roles_facts['leader'] ) {
	$roles_pullstats[] = array(
		'icon'   => 'chart-pie.svg',
		/* translators: %s: the leading role type's share of all tagged appearances (one decimal). */
		'number' => sprintf( __( '%s%%', 'lwtv' ), number_format_i18n( $roles_facts['leader_share_pct'], 1 ) ),
		/* translators: %s: the leading role type's name (e.g. "Regular/Main Character"). */
		'label'  => sprintf( __( 'Of appearances are %s, the most common type.', 'lwtv' ), lcfirst( $roles_data[ $roles_facts['leader'] ]['name'] ?? '' ) ),
	);
}

if ( isset( $roles_facts['levels']['guest'] ) && $roles_facts['sum'] > 0 ) {
	$roles_pullstats[] = array(
		'icon'   => 'user.svg',
		/* translators: %s: percentage of tagged appearances that are one-off Guest roles (one decimal). */
		'number' => sprintf( __( '%s%%', 'lwtv' ), number_format_i18n( $roles_facts['levels']['guest']['share'], 1 ) ),
		'label'  => __( 'Are one-off Guest appearances rather than a Regular or Recurring part.', 'lwtv' ),
	);
}

if ( ! empty( $roles_pullstats ) ) :
	?>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--actors">
		<?php foreach ( $roles_pullstats as $roles_pullstat ) : ?>
			<div class="lwtv-statcard">
				<span class="lwtv-statcard-icon">
					<?php echo lwtv_plugin()->get_symbolicon( svg: $roles_pullstat['icon'], icon: 'svg-' . str_replace( '.svg', '', $roles_pullstat['icon'] ), max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="lwtv-statcard-number"><?php echo esc_html( $roles_pullstat['number'] ); ?></span>
				<p class="lwtv-statcard-label"><?php echo esc_html( $roles_pullstat['label'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
endif;

// ---- Most prolific actor per role type ----
// "Most recent actor first" approximation — see Build_Actors::
// get_first_actor_by_character()'s docblock: role type lives on the
// character's show-group row, not the actor, so a recast character's
// appearances of every type are credited to whichever actor is listed
// first today, not necessarily whoever actually played that specific row.
$roles_prolific = ( new Build_Actors() )->generate_prolific_by_role();
if ( ! empty( $roles_prolific ) ) :
	?>
	<p class="lwtv-stats-eyebrow lwtv-stats-eyebrow--section"><?php esc_html_e( 'Most Prolific by Role Type', 'lwtv' ); ?></p>
	<div class="lwtv-pullstats lwtv-pullstats--three lwtv-statcards lwtv-bars--actors">
		<?php
		foreach ( Role_Podium::ORDER as $roles_prolific_type ) :
			if ( ! isset( $roles_prolific[ $roles_prolific_type ] ) ) :
				continue;
			endif;
			$roles_prolific_row = $roles_prolific[ $roles_prolific_type ];
			?>
			<div class="lwtv-statcard lwtv-statcard--firsts">
				<span class="lwtv-statcard-icon">
					<?php echo lwtv_plugin()->get_symbolicon( svg: 'trophy.svg', icon: 'svg-trophy', max_size: '18' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</span>
				<span class="lwtv-statcard-number"><?php echo esc_html( number_format_i18n( $roles_prolific_row['count'] ) ); ?></span>
				<p class="lwtv-statcard-label">
					<?php echo esc_html( $roles_data[ $roles_prolific_type ]['name'] ?? $roles_prolific_type ); ?>:
					<a href="<?php echo esc_url( $roles_prolific_row['url'] ); ?>"><?php echo esc_html( $roles_prolific_row['name'] ); ?></a>
				</p>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
