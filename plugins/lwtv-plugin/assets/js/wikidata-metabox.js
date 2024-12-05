// post-meta-verification.js

(function($) {  // Use a self-executing anonymous function or other scoping pattern.  Best practice!

	async function fetchData(postId) {
		try {
			const response = await wp.apiFetch({
				path: `${pmv_params.rest_url}${postId}`,
			});

			displayResults(response); // Call displayResults function with the API response.


		} catch (error) {
			$('#post-meta-verification-results').text('Error loading data: ' + error.message);
		}
	}

	function displayResults(data) {
		const resultsContainer = $('#post-meta-verification-results');
		resultsContainer.empty(); // Clear previous results

		//Check if everything matches and display appropriate message or details

		if (data && data.length > 0) {

			const [_, actorWikiData] = Object.entries(data[0])[0];
			const mismatches = Object.entries(actorWikiData).filter(([key, value]) => typeof value === 'object' && value !== null ); // Array of mismatched fields

			if (mismatches.length === 0) {
				resultsContainer.html(`<p>Congratulations! All data matches with WikiData for ${actorWikiData.name}.</p>`);
			} else {
				resultsContainer.append(`<h3>${actorWikiData.name} - QID ${actorWikiData.wikidata}</h3>`)
				resultsContainer.append(`<p>Double check all Social Media values, as WikiData can be out of date.</p>`);
				mismatches.forEach(([key,value]) => {
					const capitalizedKey = key.charAt(0).toUpperCase() + key.slice(1); // Capitalize the key
					resultsContainer.append(`<p><strong>${capitalizedKey}:</strong></p>`);
					resultsContainer.append("<ul>");

					if ( value.ours ) {
						resultsContainer.append(`<li>Ours: <code>${value.ours}</code></li>`);
					} else {
						resultsContainer.append("<li>Ours: <em>Not set</em></li>");
					}

					resultsContainer.append(`<li>WikiData: <code>${value.wikidata}</code></li></ul>`);
				});
			}
		} else {
			resultsContainer.html('<p>No WikiData found for this actor.</p>');
		}
	}

	//On document ready
	$(document).ready(function() {
		const postId = pmv_params.postId;
		const resultsContainer = $('#post-meta-verification-results');
		const refreshButton = $('<button id="refresh-data" class="components-button is-primary is-compact"><span class="dashicons dashicons-update"></span> Refresh</button>');
		const spinner = $('<span class="dashicons dashicons-update loading"></span>');

		$('#post_meta_verification_meta_box .inside').append(refreshButton); // Append to meta box

		let isRefreshing = false; // Flag to track refresh state

		const refreshData = () => {
			if (isRefreshing) {
				return; // Don't refresh if already refreshing
			}

			isRefreshing = true; // Set refreshing flag

			refreshButton.prop('disabled', true);    // Disable the button
			refreshButton.prepend(spinner);

			fetchData(pmv_params.postId)
				.finally(() => {
					isRefreshing = false;
					refreshButton.prop('disabled', false);
					spinner.remove();
				});
		};

		refreshData();

		// Refresh on click
		refreshButton.on('click', refreshData );
	});

})(jQuery);

