/**
 * Ways to Watch: switch the provider strip between countries.
 *
 * The server renders the strip for its default country (US, else the first) so
 * the page stays cacheable and works without JavaScript. This picks the
 * reader's country, shows the country select, and redraws the strip from the
 * JSON payload next to it. See docs/plans/tmdb-watch-providers.md#script.
 *
 * Built with createElement and textContent only: provider names come from a
 * third party.
 */
( function () {
	const { __, _n, sprintf } = window.wp.i18n;

	const STORAGE_KEY = 'lwtv-watch-country';
	const lang = document.documentElement.lang || 'en';

	/**
	 * Country names in the page's language, or the code when the browser
	 * cannot name it.
	 *
	 * @param {string} code Country code.
	 * @return {string} Name.
	 */
	const regionNames = ( () => {
		try {
			const names = new Intl.DisplayNames( [ lang ], { type: 'region' } );
			return ( code ) => names.of( code ) || code;
		} catch {
			return ( code ) => code;
		}
	} )();

	/**
	 * "A, B, and C" in the page's language.
	 *
	 * @param {string[]} items Items.
	 * @return {string} The list.
	 */
	const listOf = ( items ) => {
		try {
			return new Intl.ListFormat( lang, {
				style: 'long',
				type: 'conjunction',
			} ).format( items );
		} catch {
			return items.join( ', ' );
		}
	};

	/**
	 * Read and write the reader's choice. Storage can throw (private windows,
	 * blocked site data), and the strip must work without it.
	 */
	const stored = {
		get() {
			try {
				return window.localStorage.getItem( STORAGE_KEY ) || '';
			} catch {
				return '';
			}
		},
		set( code ) {
			try {
				window.localStorage.setItem( STORAGE_KEY, code );
			} catch {}
		},
	};

	/**
	 * The region subtags of the browser's languages, in preference order.
	 * A guess: a browser cannot know where its reader is, which is why the
	 * select is visible.
	 *
	 * @return {string[]} Country codes.
	 */
	const browserRegions = () =>
		( navigator.languages || [ navigator.language || '' ] )
			.map( ( tag ) => {
				try {
					return new Intl.Locale( tag ).region || '';
				} catch {
					return '';
				}
			} )
			.filter( Boolean );

	const setUp = ( root ) => {
		const dataTag = root.querySelector( '[data-watch-providers-data]' );
		const picker = root.querySelector( '[data-watch-providers-picker]' );
		const select = picker && picker.querySelector( 'select' );
		const link = root.querySelector( '.watch-providers__strip' );
		const logos = root.querySelector( '.watch-providers__logos' );
		const more = root.querySelector( '.watch-providers__more' );
		const status = root.querySelector( '.watch-providers__status' );

		if (
			! dataTag ||
			! select ||
			! link ||
			! logos ||
			! more ||
			! status
		) {
			return;
		}

		let data;
		try {
			data = JSON.parse( dataTag.textContent );
		} catch {
			return;
		}

		const countries = data.countries || {};
		const has = ( code ) =>
			Object.prototype.hasOwnProperty.call( countries, code );

		/**
		 * Redraw the strip for one country.
		 *
		 * @param {string} code Country code with a list.
		 */
		const draw = ( code ) => {
			const ids = countries[ code ].ids;
			const shown = ids.slice( 0, data.cap );
			const extra = ids.length - shown.length;
			const names = [];

			logos.replaceChildren(
				...shown.map( ( id ) => {
					const provider = data.providers[ id ];
					names.push( provider.name );

					if ( ! provider.logo ) {
						const text = document.createElement( 'span' );
						text.className =
							'watch-providers__logo watch-providers__logo--text';
						text.textContent = provider.name;
						return text;
					}

					const img = document.createElement( 'img' );
					img.className = 'watch-providers__logo';
					img.src = data.logoBase + provider.logo;
					img.alt = '';
					img.width = 46;
					img.height = 46;
					img.decoding = 'async';
					return img;
				} )
			);

			const moreText =
				extra > 0
					? sprintf(
							/* translators: %s: number of further streaming services. */
							_n(
								'and %s more',
								'and %s more',
								extra,
								'lwtv-underscores'
							),
							extra.toLocaleString( lang )
						)
					: '';

			more.textContent = moreText;
			more.hidden = '' === moreText;

			link.href = countries[ code ].link;
			link.setAttribute(
				'aria-label',
				sprintf(
					/* translators: %s: list of streaming services, such as "Netflix, Hulu, and Max". */
					__(
						'%s. See all options on TMDB (opens in a new tab)',
						'lwtv-underscores'
					),
					listOf(
						extra > 0
							? names.concat(
									sprintf(
										/* translators: %s: number of further streaming services, the last item in a list. */
										_n(
											'%s more',
											'%s more',
											extra,
											'lwtv-underscores'
										),
										extra.toLocaleString( lang )
									)
								)
							: names
					)
				)
			);
		};

		const announce = ( code, fellBack ) => {
			const name = regionNames( code );
			const count = countries[ code ].ids.length;

			status.textContent = fellBack
				? sprintf(
						/* translators: %s: country name. */
						__(
							'Not listed in your country, showing %s.',
							'lwtv-underscores'
						),
						name
					)
				: sprintf(
						/* translators: 1: number of streaming services. 2: country name. */
						_n(
							'Showing %1$s provider in %2$s.',
							'Showing %1$s providers in %2$s.',
							count,
							'lwtv-underscores'
						),
						count.toLocaleString( lang ),
						name
					);
		};

		// Rename the options in the reader's language and re-sort by name.
		const options = Array.from( select.options ).filter( ( option ) =>
			has( option.value )
		);
		options.forEach( ( option ) => {
			option.textContent = regionNames( option.value );
		} );
		options
			.sort( ( a, b ) =>
				a.textContent.localeCompare( b.textContent, lang )
			)
			.forEach( ( option ) => select.append( option ) );

		// Stored choice, else the browser's region, else the server's default.
		const choice = stored.get();
		const guesses = browserRegions();
		let country = data.default;
		let fellBack = false;

		if ( has( choice ) ) {
			country = choice;
		} else {
			const listed = guesses.find( has );
			if ( listed ) {
				country = listed;
			} else if ( guesses.length ) {
				fellBack = true;
			}
		}

		if ( country !== data.default ) {
			draw( country );
		}

		select.value = country;
		picker.hidden = false;

		// The select now names the country; the heading need not.
		const heading = document.querySelector( '[data-watch-providers-in]' );
		if ( heading ) {
			heading.hidden = true;
		}

		// Explain why the reader sees another country's list. Shown, not only
		// announced: sighted readers need the reason too.
		if ( fellBack ) {
			announce( country, true );
		}

		select.addEventListener( 'change', () => {
			if ( ! has( select.value ) ) {
				return;
			}

			draw( select.value );
			stored.set( select.value );
			announce( select.value, false );
		} );
	};

	document.querySelectorAll( '[data-watch-providers]' ).forEach( setUp );
} )();
