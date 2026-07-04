/**
 * Visual metadata for node types (icons + accent colours).
 * Mirrors Bit Flow's light card style without copying their assets.
 */

/** @type {Record<string, {icon: string, accent: string, bg: string}>} */
const NODE_META = {
	wp_hook_trigger: { icon: '⚡', accent: '#7c3aed', bg: '#f3e8ff' },
	woocommerce_order_completed_trigger: {
		icon: '🛒',
		accent: '#7f54b3',
		bg: '#f5f0fa',
	},
	elementor_form_submitted_trigger: {
		icon: 'E',
		accent: '#92003b',
		bg: '#fce7f3',
	},
	contact_form_7_submitted_trigger: {
		icon: '7',
		accent: '#1a73e8',
		bg: '#e8f0fe',
	},
	wpforms_submitted_trigger: {
		icon: 'W',
		accent: '#e27730',
		bg: '#fff4ed',
	},
	http_request_action: { icon: '⇄', accent: '#057bde', bg: '#e8f4fd' },
	send_email_action: { icon: '✉', accent: '#0d9488', bg: '#ccfbf1' },
	slack_incoming_webhook_action: {
		icon: 'S',
		accent: '#611f69',
		bg: '#f4edf7',
	},
	openai_chat_action: { icon: 'AI', accent: '#10a37f', bg: '#d1fae5' },
	telegram_send_message_action: {
		icon: 'T',
		accent: '#229ed9',
		bg: '#e0f2fe',
	},
	whatsapp_cloud_send_message_action: {
		icon: 'W',
		accent: '#25d366',
		bg: '#dcfce7',
	},
	gemini_generate_content_action: {
		icon: 'G',
		accent: '#4285f4',
		bg: '#e8f0fe',
	},
	claude_messages_action: {
		icon: 'C',
		accent: '#d97757',
		bg: '#ffedd5',
	},
	google_sheets_append_row_action: {
		icon: '▦',
		accent: '#0f9d58',
		bg: '#dcfce7',
	},
};

const DEFAULT_META = { icon: '●', accent: '#051020', bg: '#f1f5f9' };

/**
 * @param {string} typeSlug  Node type slug from the registry.
 * @param {string} category  `trigger` or `action`.
 * @return {{icon: string, accent: string, bg: string, categoryLabel: string}}
 */
export function getNodeMeta(typeSlug, category) {
	const entry = NODE_META[typeSlug] || DEFAULT_META;

	return {
		...entry,
		categoryLabel:
			category === 'trigger' ? 'Trigger' : 'Action',
	};
}
