<?php
/**
 * Description: REST-API: Actor name check
 *
 * Answers one question for the block editor: is there already an actor who might
 * be the person whose name someone is typing? Candidates and a tier, never a
 * verdict.
 *
 * UNLIKE every other route in this directory, this one is not public: it returns
 * draft and private actor names. See
 * docs/architecture/duplicate-detection.md#editor-warning.
 */

namespace LWTV\Rest_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LWTV\CPTs\Actors as CPT_Actors;
use LWTV\Queeries\Get_Actors_By_Name;

class Actor_Name_Check {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	/**
	 * Rest API init
	 *
	 * Creates the callback
	 *   - /lwtv/v1/actors/name-check?name=Bae+Doona&exclude=123
	 */
	public function rest_api_init() {
		register_rest_route(
			'lwtv/v1',
			'/actors/name-check',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_api_callback' ),
				'permission_callback' => array( $this, 'can_edit_actors' ),
				'args'                => array(
					'name'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'exclude' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Can this user edit actors?
	 *
	 * Read off the post type object (it is 'edit_actors', not 'edit_posts'), so
	 * it stays correct if the registration changes.
	 *
	 * @return bool
	 */
	public function can_edit_actors(): bool {
		$post_type = get_post_type_object( CPT_Actors::SLUG );

		if ( ! $post_type || ! isset( $post_type->cap->edit_posts ) ) {
			return false;
		}

		return current_user_can( $post_type->cap->edit_posts );
	}

	/**
	 * Rest API Callback
	 *
	 * @param  \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_api_callback( $request ) {
		$name    = (string) $request->get_param( 'name' );
		$exclude = (int) $request->get_param( 'exclude' );

		$matches = ( new Get_Actors_By_Name() )->make( $name, $exclude );
		$matches = $this->drop_acknowledged( $matches, $exclude );

		return rest_ensure_response(
			array(
				'name'    => $name,
				'matches' => array_values( array_map( array( $this, 'decorate' ), $matches ) ),
			)
		);
	}

	/**
	 * Remove candidates an editor has already confirmed are different people.
	 *
	 * Only meaningful when editing an existing actor; a brand new post has
	 * acknowledged nothing yet, which is why the warning exists in the first
	 * place.
	 *
	 * @param  array<int, array<string, mixed>> $matches Candidates.
	 * @param  int                              $post_id Post being edited.
	 * @return array<int, array<string, mixed>>
	 */
	private function drop_acknowledged( array $matches, int $post_id ): array {
		if ( ! $post_id ) {
			return $matches;
		}

		$acknowledged = get_post_meta( $post_id, 'lezactors_dupe_override', true );

		if ( ! is_array( $acknowledged ) || empty( $acknowledged ) ) {
			return $matches;
		}

		$acknowledged = array_map( 'intval', $acknowledged );

		return array_filter(
			$matches,
			static function ( array $candidate ) use ( $acknowledged ): bool {
				return ! in_array( (int) $candidate['id'], $acknowledged, true );
			}
		);
	}

	/**
	 * Add what the editor needs to render one candidate.
	 *
	 * @param  array<string, mixed> $candidate One candidate.
	 * @return array<string, mixed>
	 */
	public function decorate( array $candidate ): array {
		$candidate['edit_url'] = (string) get_edit_post_link( (int) $candidate['id'], 'raw' );

		return $candidate;
	}
}
