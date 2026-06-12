<?php
/*
 * SearchWP
 *
 * @package lwtv-plugin
 */
namespace LWTV\Plugins;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class SearchWP {
	/**
	 * Constructor
	 */
	public function __construct() {
		if ( ! class_exists( 'SearchWP' ) ) {
			return;
		}

		// If we're on a dev site, we want to index the alternate content.
		if ( defined( 'LWTV_DEV' ) && LWTV_DEV ) {
			add_filter( 'searchwp\indexer\alternate', '__return_true' );
		}

		add_filter( 'searchwp\query\args', array( $this, 'maybe_switch_engine' ), 10, 2 );
	}

	/**
	 * Switch SearchWP engine based on the lwtv_scope request parameter.
	 *
	 * Used by the sidebar search form scope selector. The modal uses swpmfe
	 * instead, which runs at priority 99 and takes precedence there.
	 *
	 * @param array $args  SearchWP query args.
	 * @param mixed $query The SearchWP query object.
	 * @return array
	 */
	public function maybe_switch_engine( array $args, $query ): array {
		$allowed = array( 'shows', 'characters', 'actors' );
		$scope   = sanitize_text_field( wp_unslash( $_REQUEST['lwtv_scope'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $scope && in_array( $scope, $allowed, true ) ) {
			$args['engine'] = $scope;
		}

		return $args;
	}
}
