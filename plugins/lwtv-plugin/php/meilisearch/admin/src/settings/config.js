/**
 * The Settings screen's start-up values, from the page.
 */
const config = window.lwtvMeiliSettings || {};

export const REST = config.restPath || 'lwtv/v1/meili-settings';
export const POLL_MS = Number( config.pollMs ) || 2000;
export const POLL_MAX_MS = Number( config.pollMaxMs ) || 600000;
