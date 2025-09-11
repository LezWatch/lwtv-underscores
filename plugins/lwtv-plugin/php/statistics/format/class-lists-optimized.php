<?php

namespace LWTV\Statistics\Format;

class Lists_Optimized {

	/**
	 * Format data as simple list
	 *
	 * @param array  $data Data to format
	 * @param string $context Station/Nation etc slug - CBS, USA, etc
	 * @param string $view View type - sexuality, gender, tropes, intersections, formats, on-air
	 * @param string $type Type of context - station, nation, etc
	 * @return string HTML output
	 */
	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function format( $data, $context, $view, $type ) {
		if ( empty( $data ) ) {
			return '<p>No data available for this list.</p>';
		}

		$data = Shared::sort_data( $data, $view );

		$output = '<ul class="list-group">';

		foreach ( $data as $item ) {
			$url     = isset( $item['url'] ) ? $item['url'] : '#';
			$output .= '<li class="list-group-item d-flex justify-content-between align-items-center">
				<a href="' . esc_url( $url ) . '">' . esc_html( $item['name'] ) . '</a>
				<span class="badge badge-primary badge-pill">' . (int) $item['count'] . '</span>
			</li>';
		}

		$output .= '</ul>';
		return $output;
	}
}
