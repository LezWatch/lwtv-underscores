<?php
/**
 * Name: Watch Term Match
 * Description: Does an existing provider term already look like this host?
 *
 * The Watch Providers tab offers "create a term" for every host with none, and
 * the easy mistake is creating one that already exists under a slightly
 * different spelling. That is how the live data ended up with "Lesflicks" beside
 * "LezFlicks", and "FX" beside "FX Networks".
 *
 * So before offering to create, look. `hbomax.com` and the existing term
 * "HBO Max" are the same string once you stop caring about spaces;
 * `paramountplus.com` and "Paramount+" are the same once you know what the plus
 * means.
 *
 * Deliberately **not** fuzzy. No edit distance, no prefix matching, no
 * similarity threshold — those turn a suggestion into a guess, and a wrong
 * suggestion on a pre-selected dropdown is worse than no suggestion at all.
 * This only ever reports an exact match after canonicalisation, which is a rule
 * a person can hold in their head and predict.
 *
 * PURE. Array in, int out, no WordPress calls beyond Host_Name, which is itself
 * pure.
 *
 * @package LWTV
 */

namespace LWTV\CPTs\Shows;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Watch_Term_Match {

	/**
	 * Reduce a provider name to the form worth comparing.
	 *
	 * The substitutions are not cosmetic. Seven providers in the live data spell
	 * themselves with a trailing `+` -- Paramount+, Disney+, BET+, MGM+, Apple
	 * TV+, M6+, SVTV+ -- and every one of them owns a domain that writes it out
	 * as "plus". Without that rule the single most useful match never fires.
	 *
	 * `&` becomes `and` for the same reason ("Seed&Spark" against
	 * `seedandspark.com`), and entities are decoded first because WordPress
	 * stores term names encoded, so the raw name is "Seed&amp;Spark".
	 *
	 * @param string $name A term name, a discovered site name, or a host label.
	 * @return string Lowercase alphanumerics only, or '' when nothing survives.
	 */
	public static function canonical( string $name ): string {
		/*
		 * Not Theme\Ways_To_Watch::term_name(), which is the shared decoder for
		 * *rendering* a term name. This is a step inside a comparison, and this
		 * class is pure -- it runs in the unit suite with no WordPress bootstrap,
		 * so it cannot reach into a theme class. Same call, different job.
		 */
		$name = html_entity_decode( $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$name = strtolower( $name );

		$name = str_replace(
			array( '+', '&' ),
			array( 'plus', 'and' ),
			$name
		);

		return (string) preg_replace( '/[^a-z0-9]/', '', $name );
	}

	/**
	 * Every canonical form worth testing for one host.
	 *
	 * Three, because a provider's identity shows up in different parts of its
	 * domain depending on the provider:
	 *
	 *   - the name we would propose, which may have come from the site's own
	 *     og:site_name and is therefore the best evidence available;
	 *   - the registrable label, which catches `hbomax.com` => "hbomax" and
	 *     survives generic subdomains, so `watch.revry.tv` => "revry";
	 *   - the registrable domain with its dots removed, which catches `acorn.tv`
	 *     => "acorntv" where the label alone gives only "acorn".
	 *
	 * @param string $host     Hostname.
	 * @param string $proposed The name the tab would prefill.
	 * @return array<string> Unique non-empty canonical forms.
	 */
	public static function candidates( string $host, string $proposed = '' ): array {
		$forms = array(
			self::canonical( $proposed ),
			self::canonical( Host_Name::registrable_label( $host ) ),
			self::canonical( Host_Name::registrable_domain( $host ) ),
		);

		return array_values( array_unique( array_filter( $forms ) ) );
	}

	/**
	 * The existing term that already looks like this host, if any.
	 *
	 * @param string             $host     Hostname.
	 * @param string             $proposed The name the tab would prefill.
	 * @param array<int, string> $terms    term_id => term name, as get_terms()
	 *                                     returns with 'fields' => 'id=>name'.
	 * @return int Term ID, or 0 when nothing matches.
	 */
	public static function suggest( string $host, string $proposed, array $terms ): int {
		$candidates = self::candidates( $host, $proposed );

		if ( empty( $candidates ) || empty( $terms ) ) {
			return 0;
		}

		// Canonical term name => term_id. Built first so the candidate order
		// decides which match wins, rather than the term order.
		$by_name = array();
		foreach ( $terms as $term_id => $term_name ) {
			$key = self::canonical( (string) $term_name );

			// First term to claim a canonical form keeps it. Two terms
			// canonicalising the same is itself a duplicate worth finding, but
			// this is not the place that reports it.
			if ( '' !== $key && ! isset( $by_name[ $key ] ) ) {
				$by_name[ $key ] = (int) $term_id;
			}
		}

		// Candidates are ordered best-evidence first: what the site calls itself
		// beats what we inferred from its domain.
		foreach ( $candidates as $candidate ) {
			if ( isset( $by_name[ $candidate ] ) ) {
				return $by_name[ $candidate ];
			}
		}

		return 0;
	}
}
