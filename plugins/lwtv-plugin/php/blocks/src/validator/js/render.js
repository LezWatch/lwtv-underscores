// Plugin Specific Imports
import { PluginSidebar } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import { Panel, PanelBody, PanelRow, Spinner } from '@wordpress/components';
import { more } from '@wordpress/icons';

export default function Render() {
	const postId = useSelect((select) =>
		select('core/editor').getCurrentPostId()
	);
	const postType = useSelect((select) =>
		select('core/editor').getCurrentPostType()
	);
	const [apiData, setApiData] = useState(null);
	const [isLoading, setIsLoading] = useState(true);
	const [error, setError] = useState(null);
	const [refreshCounter, setRefreshCounter] = useState(0);
	const siteURL = window.location.origin;

	useEffect(() => {
		// Initial fetch
		if (postId) {
			const fetchData = async () => {
				setIsLoading(true); // Set loading state
				try {
					const response = await fetch(
						`${siteURL}/wp-json/lwtv/v1/wikidata/${postId}`
					);
					if (!response.ok) {
						throw new Error(
							`HTTP error! status: ${response.status}`
						);
					}

					const data = await response.json();
					setApiData(data);
					setError(null); // Clear any previous errors
				} catch (err) {
					setError(err.message); // Set the error message
					setApiData(null); // Reset apiData if there's an error
				} finally {
					setIsLoading(false); // Clear loading state, regardless of success/failure
				}
			};
			fetchData();
		}
	}, [postId, siteURL, refreshCounter]); // Run effect whenever postId changes

	const handleRefresh = () => {
		setRefreshCounter((prevCounter) => prevCounter + 1);
	};

	const filteredPersonData = (personData) => {
		const filteredEntries = Object.entries(personData).filter(
			([key, value]) => {
				const lowerCaseValue = String(value).toLowerCase(); // Convert to lowercase for comparison
				const lowerCaseKey = String(key).toLowerCase(); // Convert to lowercase for comparison
				return (
					lowerCaseValue !== 'match' &&
					lowerCaseValue !== 'n/a' &&
					lowerCaseKey !== 'wikidata' &&
					lowerCaseKey !== 'id' &&
					lowerCaseKey !== 'name'
				);
			}
		);

		return Object.fromEntries(filteredEntries); // Convert back to object
	};

	const returnAPIData = (data) => {
		return (
			<div>
				{data.map((item) => {
					const personData = Object.entries(item)[0]; // Get the person's data from the inner object.
					const filteredData = filteredPersonData(personData); // Filter the data(
					// Check if filteredData is empty and render message as needed
					if (Object.keys(filteredData).length === 0) {
						return (
							<p key={personData.id}>
								Congratulations! All data matches for{' '}
								{personData.name}.
							</p>
						);
					} // Render the data if filteredData is not empty
					return (
						<div key={personData.id}>
							<h3>{personData.name}</h3>
							<ul>
								{/* Mapping now renders this filtered data */}
								{Object.entries(filteredData).map(
									([key, value]) => (
										<li key={key}>
											{key}: {value}
										</li>
									)
								)}
							</ul>
						</div>
					);
				})}
			</div>
		);
	};

	// Conditional rendering based on post type
	const WikidataSidebar = () => {
		if ('post_type_actors' === postType) {
			return WikidataSidebarActor();
		}
		return null;
	};

	// Actor Checker Sidebar
	const WikidataSidebarActor = () => {
		return (
			<PanelBody title="Actor Checker" icon={more} initialOpen={true}>
				<PanelRow>
					{/* Conditional rendering based on loading/error/data states */}
					{isLoading && <Spinner />} {/* Show loading spinner */}
					<div>
						{error && <p>Error: {error}</p>}

						{!isLoading &&
							!error &&
							apiData && // Show data if loaded and no error
							returnAPIData(apiData)}
					</div>
					{!isLoading && !error && !apiData && (
						<div>No data found for this post.</div>
					)}
					<button onClick={handleRefresh} disabled={isLoading}>
						{isLoading ? 'Refreshing...' : 'Refresh'}
					</button>
				</PanelRow>
			</PanelBody>
		);
	};

	const allowedPostTypes = ['post_type_actors'];

	// Conditionally render the PluginSidebar
	if (allowedPostTypes.includes(postType)) {
		return (
			<PluginSidebar
				name="wikidata-sidebar"
				title="LWTV Wikidata"
				icon="vault"
			>
				<Panel>{WikidataSidebar()}</Panel>
			</PluginSidebar>
		);
	}
	return null;
}
