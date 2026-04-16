// Plugin Specific Imports
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import { Button, PanelRow, Spinner } from '@wordpress/components';
import CopyIcon from '../../_common/svg/copy';

export default function Render() {
	const postType = useSelect((select) =>
		select('core/editor').getCurrentPostType()
	);

	const postId = useSelect((select) =>
		select('core/editor').getCurrentPostId()
	);

	const postStatus = useSelect(
		(select) => select('core/editor').getCurrentPost().status
	);

	const [apiData, setApiData] = useState(null);
	const [isLoading, setIsLoading] = useState(true);
	const [error, setError] = useState(null);
	const [refreshCounter, setRefreshCounter] = useState(0);
	const [showToast, setShowToast] = useState(false);
	const siteURL = window.location.origin;

	// Handle toast visibility with DOM manipulation
	useEffect(() => {
		if (showToast) {
			const toast = document.createElement('div');
			toast.id = 'lwtv-copy-toast';
			toast.textContent = 'Copied!';
			toast.style.cssText =
				'position: fixed; top: 50px; right: 50px; background-color: #cb3e85; color: #fff; padding: 12px 16px; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.3); z-index: 999999; font-size: 13px; font-weight: 500; pointer-events: none;';

			document.body.appendChild(toast);

			const timer = setTimeout(() => {
				if (document.body.contains(toast)) {
					document.body.removeChild(toast);
				}
				setShowToast(false);
			}, 2500);

			return () => {
				clearTimeout(timer);
				if (document.body.contains(toast)) {
					document.body.removeChild(toast);
				}
			};
		}
	}, [showToast]);

	useEffect(() => {
		if (
			postId &&
			postType === 'post_type_actors' &&
			postStatus !== 'auto-draft'
		) {
			const fetchData = async () => {
				setIsLoading(true);
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
					setError(null);
				} catch (err) {
					setError(err.message);
					setApiData(null);
				} finally {
					setIsLoading(false);
				}
			};
			fetchData();
		}
	}, [postId, postType, postStatus, siteURL, refreshCounter]);

	if (postType !== 'post_type_actors') {
		return null;
	}

	const handleRefresh = () => {
		setRefreshCounter((prevCounter) => prevCounter + 1);
	};

	const handleCopy = async (textToCopy) => {
		try {
			if (
				typeof navigator !== 'undefined' &&
				navigator.clipboard &&
				navigator.clipboard.writeText
			) {
				await navigator.clipboard.writeText(textToCopy);
			} else {
				// eslint-disable-next-line no-alert
				window.alert(
					'Copy to clipboard is not supported in your browser. Please copy manually.'
				);
			}
			setShowToast(true);
			setTimeout(() => {
				setShowToast(false);
			}, 2500);
		} catch (err) {
			// Display error to user
			// eslint-disable-next-line no-alert
			window.alert('Error: ' + err.message || 'Unknown error');
			setShowToast(true);
			setTimeout(() => {
				setShowToast(false);
			}, 2500);
		}
	};

	const filteredPersonData = (personData) => {
		const filteredEntries = Object.entries(personData).filter(
			([key, value]) => {
				const lowerCaseValue = String(value).toLowerCase();
				return (
					lowerCaseValue !== 'match' &&
					lowerCaseValue !== 'n/a' &&
					!['wikidata', 'id', 'name'].includes(key.toLowerCase())
				);
			}
		);
		return Object.fromEntries(filteredEntries);
	};

	const MetadataPanelAutoSave = () => (
		<PluginDocumentSettingPanel
			name="lwtv-wikidata-panel"
			title="WikiData Checker"
			className="lwtv-wikidata-panel"
		>
			<PanelRow>
				<div>
					<p>
						Put in the actor name and then we can run some checks.
					</p>
				</div>
			</PanelRow>
		</PluginDocumentSettingPanel>
	);

	const MetadataPanel = () => (
		<PluginDocumentSettingPanel
			name="lwtv-wikidata-panel"
			title="WikiData Checker"
			className="lwtv-wikidata-panel"
		>
			<PanelRow>
				<div>
					{isLoading && <Spinner />}
					{error && <p>Error: {error}</p>}
					{!isLoading && !error && apiData && (
						<>
							{apiData.map((item) => {
								const [key, personData] =
									Object.entries(item)[0];
								const filteredData =
									filteredPersonData(personData);

								if ('error' === personData.wikidata) {
									return (
										<div key={key}>
											<h3>{personData.name}</h3>
											<p>
												There is no information on
												WikiData for this actor.
											</p>
										</div>
									);
								}

								return (
									<div key={key}>
										<h3>
											<a
												href={`https://www.wikidata.org/wiki/${personData.wikidata}`}
												target="_blank"
												rel="noopener noreferrer"
											>
												{personData.name}
											</a>
										</h3>
										{Object.keys(filteredData).length ===
										0 ? (
											<p>
												Congratulations! All WikiData
												matches for {personData.name}.
											</p>
										) : (
											<div>
												{Object.entries(
													filteredData
												).map(([subKey, value]) => (
													<div key={subKey}>
														<h4>
															{subKey.toUpperCase()}
														</h4>
														{value && (
															<ul>
																{Object.entries(
																	value
																).map(
																	([
																		innerKey,
																		innerValue,
																	]) => (
																		<li
																			key={
																				innerKey
																			}
																		>
																			<strong>
																				{innerKey
																					.charAt(
																						0
																					)
																					.toUpperCase() +
																					innerKey.slice(
																						1
																					)}
																			</strong>
																			:{' '}
																			<span
																				style={{
																					display:
																						'inline-flex',
																					alignItems:
																						'center',
																					gap: '4px',
																				}}
																			>
																				{innerValue ? (
																					<span
																						className="wrapping-code"
																						role="button"
																						tabIndex={
																							0
																						}
																						onClick={() =>
																							handleCopy(
																								innerValue
																							)
																						}
																						onKeyDown={(
																							e
																						) => {
																							if (
																								e.key ===
																									'Enter' ||
																								e.key ===
																									' '
																							) {
																								e.preventDefault();
																								handleCopy(
																									innerValue
																								);
																							}
																						}}
																						style={{
																							cursor: 'pointer',
																						}}
																						title="Click to copy"
																					>
																						{
																							innerValue
																						}
																					</span>
																				) : (
																					<code className="wrapping-code">
																						empty
																					</code>
																				)}
																				{innerValue && (
																					<Button
																						size="small"
																						variant="tertiary"
																						onClick={() =>
																							handleCopy(
																								innerValue
																							)
																						}
																						title="Copy to clipboard"
																						style={{
																							minWidth:
																								'auto',
																							padding:
																								'2px 4px',
																							height: 'auto',
																						}}
																					>
																						<CopyIcon />
																					</Button>
																				)}
																			</span>
																		</li>
																	)
																)}
															</ul>
														)}
														{!value && 'empty'}
													</div>
												))}
											</div>
										)}
									</div>
								);
							})}
						</>
					)}

					{!isLoading && !error && !apiData && (
						<p>No data found for this post.</p>
					)}

					<Button
						variant="secondary"
						onClick={handleRefresh}
						isBusy={isLoading}
					>
						{isLoading ? 'Refreshing...' : 'Refresh'}
					</Button>
				</div>
			</PanelRow>
		</PluginDocumentSettingPanel>
	);

	if (postStatus === 'auto-draft') {
		return <MetadataPanelAutoSave />;
	}
	return <MetadataPanel />;
}
