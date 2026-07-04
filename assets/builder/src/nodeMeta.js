/**
 * Icons and colors for palette apps and node types.
 */

/** @type {Record<string, { icon: string, bg: string, accent: string }>} */
const APP_META = {
	wordpress: { icon: 'W', bg: '#21759b', accent: '#fff' },
	elementor: { icon: 'E', bg: '#92003b', accent: '#fff' },
	'contact-form-7': { icon: '7', bg: '#0073aa', accent: '#fff' },
	wpforms: { icon: 'F', bg: '#e27730', accent: '#fff' },
	woocommerce: { icon: 'WC', bg: '#7f54b3', accent: '#fff' },
	openai: { icon: 'AI', bg: '#10a37f', accent: '#fff' },
	anthropic: { icon: 'A', bg: '#d4a574', accent: '#1a1a1a' },
	'google-ai': { icon: 'G', bg: '#4285f4', accent: '#fff' },
	communication: { icon: '✉', bg: '#0ea5e9', accent: '#fff' },
	integrations: { icon: '⚡', bg: '#6366f1', accent: '#fff' },
};

/** @type {Record<string, { icon: string, bg: string, accent: string }>} */
const NODE_META = {
	trigger: { icon: '⚡', bg: '#e0f2fe', accent: '#0369a1' },
	action: { icon: '▶', bg: '#f0fdf4', accent: '#15803d' },
};

/**
 * @param {string} slugOrAppId
 * @param {'trigger'|'action'} category
 * @param {string} [appId]
 * @return {{ icon: string, bg: string, accent: string }}
 */
export function getNodeMeta(slugOrAppId, category, appId) {
	if (appId && APP_META[appId]) {
		return APP_META[appId];
	}

	if (APP_META[slugOrAppId]) {
		return APP_META[slugOrAppId];
	}

	return NODE_META[category] || NODE_META.action;
}
