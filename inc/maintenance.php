<?php
/**
 * Static maintenance page.
 *
 * Rendered when a hard dependency is missing (see inc/requirements.php).
 *
 * IMPORTANT: this page must be entirely self-contained. It runs at `setup_theme`
 * time, before the LWTV plugin loads, so it deliberately uses NO theme
 * functions, NO template parts, NO symbolicons, NO enqueued styles, and NO ACF.
 * Anything it depended on could be the very thing that is broken. Inline CSS
 * only, no external requests.
 *
 * Note on i18n: strings are wrapped for consistency with the rest of the theme,
 * but the text domain is not loaded this early, so they render in English. Do
 * not rely on translation here.
 *
 * @package LWTV Underscores
 */

/**
 * Send a 503 and render the maintenance page, then exit.
 *
 * @param array<string, string> $missing Missing dependencies, keyed by plugin name.
 * @return never
 */
function lwtv_theme_render_maintenance_page( array $missing = array() ) {
	// 503 with Retry-After so search engines treat this as temporary and do
	// not drop pages from the index.
	if ( ! headers_sent() ) {
		nocache_headers();
		status_header( 503 );
		header( 'Retry-After: 3600' );
		header( 'Content-Type: text/html; charset=utf-8' );
	}

	$site_name = get_bloginfo( 'name' );
	$site_name = ( '' === $site_name ) ? 'LezWatch.TV' : $site_name;

	// Only expose which dependency failed when debugging; it is a private
	// detail, not something to advertise to visitors.
	$show_debug    = ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $missing ) );
	$debug_missing = $show_debug ? implode( ', ', array_keys( $missing ) ) : '';

	$title   = __( 'Down for maintenance', 'lwtv-underscores' );
	$heading = __( 'After this maintenance ... We&rsquo;ll be right back', 'lwtv-underscores' );
	$body    = __( 'LezWatch.TV is briefly offline for maintenance. Don\'t panic! The database is safe, and we have our best queers working on the issues.', 'lwtv-underscores' );
	$retry   = __( 'Try reloading in a little while.', 'lwtv-underscores' );

	?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( str_replace( '_', '-', get_locale() ) ); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $title ) . ' &ndash; ' . esc_html( $site_name ); ?></title>
	<style>
		:root {
			--lwtv-bg: #fbfafb;
			--lwtv-panel: #fff;
			--lwtv-border: #ece9eb;
			--lwtv-text: #333;
			--lwtv-muted: #666;
			--lwtv-accent: #cb3e85;
		}

		@media (prefers-color-scheme: dark) {
			:root {
				--lwtv-bg: #311721;
				--lwtv-panel: #3f2130;
				--lwtv-border: #5a3546;
				--lwtv-text: #f3f3f3;
				--lwtv-muted: #e0d6db;
				--lwtv-accent: #eecee3;
			}
		}

		* {
			box-sizing: border-box;
		}

		body {
			margin: 0;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 1.5rem;
			background: var(--lwtv-bg);
			color: var(--lwtv-text);
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
			font-size: 1rem;
			line-height: 1.6;
		}

		main {
			max-width: 34rem;
			width: 100%;
			padding: 2.5rem 2rem;
			background: var(--lwtv-panel);
			border: 1px solid var(--lwtv-border);
			border-radius: 0.5rem;
			text-align: center;
		}

		.lwtv-site {
			margin: 0 0 1.25rem;
			font-size: 0.875rem;
			font-weight: 700;
			letter-spacing: 0.08em;
			text-transform: uppercase;
			color: var(--lwtv-accent);
		}

		h1 {
			margin: 0 0 1rem;
			font-size: 1.75rem;
			line-height: 1.25;
			font-weight: 700;
		}

		p {
			margin: 0 0 0.75rem;
			color: var(--lwtv-muted);
		}

		p:last-child {
			margin-bottom: 0;
		}
	</style>
</head>
<body>
	<main>
		<p class="lwtv-site"><?php echo esc_html( $site_name ); ?></p>
		<h1><?php echo wp_kses_post( $heading ); ?></h1>
		<p><?php echo wp_kses_post( $body ); ?></p>
		<p><?php echo esc_html( $retry ); ?></p>
	</main>
	<?php
	if ( $show_debug ) {
		echo "\n\t<!-- Missing: " . esc_html( $debug_missing ) . " -->\n";
	}
	?>
</body>
</html>
	<?php
	exit;
}
