/**
 * Node catalog helpers — single source for palette apps, groups, and defaults.
 */

/** @type {Set<string>} */
const AGENT_SLUGS = new Set([
	'openai_chat_action',
	'claude_messages_action',
	'gemini_generate_content_action',
]);

/** @type {Array<{ id: string, label: string, slugs: string[] }>} */
const INTEGRATION_TRIGGER_APPS = [
	{ id: 'elementor', label: 'Elementor', slugs: ['elementor_form_submitted_trigger'] },
	{ id: 'woocommerce', label: 'WooCommerce', slugs: ['woocommerce_order_completed_trigger'] },
	{ id: 'contact-form-7', label: 'Contact Form 7', slugs: ['contact_form7_submitted_trigger'] },
	{ id: 'wpforms', label: 'WPForms', slugs: ['wpforms_submitted_trigger'] },
];

/**
 * @param {Array<Object>} triggers
 * @param {string}      slug
 * @return {Object|undefined}
 */
function findTriggerBySlug(triggers, slug) {
	return triggers.find((trigger) => trigger.slug === slug);
}

/**
 * @param {Array<Object>} triggers
 * @param {Array<string>} slugs
 * @return {{ available: boolean, requiresPlugin: string|null }}
 */
function integrationAppStatus(triggers, slugs) {
	const items = slugs
		.map((slug) => findTriggerBySlug(triggers, slug))
		.filter(Boolean);

	if (items.length === 0) {
		return { available: false, requiresPlugin: null };
	}

	const available = items.some((item) => item.available !== false);
	const unavailable = items.find((item) => item.available === false);

	return {
		available,
		requiresPlugin: unavailable?.requires_plugin || null,
	};
}

/** @type {Record<string, { id: string, label: string, slugs: string[] }>} */
const AGENT_APPS = {
	openai: {
		id: 'openai',
		label: 'OpenAI',
		slugs: ['openai_chat_action'],
	},
	anthropic: {
		id: 'anthropic',
		label: 'Anthropic',
		slugs: ['claude_messages_action'],
	},
	'google-ai': {
		id: 'google-ai',
		label: 'Google AI',
		slugs: ['gemini_generate_content_action'],
	},
};

/** @type {Record<string, { id: string, label: string, slugs: string[] }>} */
const ACTION_APPS = {
	communication: {
		id: 'communication',
		label: 'Communication',
		slugs: [
			'send_email_action',
			'slack_incoming_webhook_action',
			'telegram_send_message_action',
			'whatsapp_cloud_send_message_action',
		],
	},
	integrations: {
		id: 'integrations',
		label: 'Integrations',
		slugs: ['http_request_action', 'google_sheets_append_row_action'],
	},
};

/**
 * @param {Object} nodeType
 * @return {Object}
 */
export function defaultConfigFor(nodeType) {
	const fromSchema = {};

	Object.entries(nodeType.config_schema || {}).forEach(([key, schema]) => {
		if (schema && Object.prototype.hasOwnProperty.call(schema, 'default')) {
			fromSchema[key] = schema.default;
		}
	});

	return {
		...fromSchema,
		...(nodeType.default_config || {}),
	};
}

/**
 * @param {Array<Object>} triggers
 * @param {string}      query
 * @return {Array<{ id: string, label: string }>}
 */
export function getTriggerApps(triggers, query = '') {
	const apps = [];
	const needle = query.trim().toLowerCase();

	if (triggers.some((trigger) => trigger.app === 'wordpress')) {
		apps.push({
			id: 'wordpress',
			label: 'WordPress',
			available: true,
		});
	}

	INTEGRATION_TRIGGER_APPS.forEach((appDef) => {
		const status = integrationAppStatus(triggers, appDef.slugs);

		apps.push({
			id: appDef.id,
			label: appDef.label,
			available: status.available,
			requiresPlugin: status.requiresPlugin || appDef.label,
		});
	});

	return apps.filter((app) => {
		if (!needle) {
			return true;
		}

		return app.label.toLowerCase().includes(needle);
	});
}

/**
 * @param {Array<Object>} actions
 * @param {string}      query
 * @return {Array<{ id: string, label: string }>}
 */
export function getAgentApps(actions, query = '') {
	const needle = query.trim().toLowerCase();
	const available = actions.filter((action) => AGENT_SLUGS.has(action.slug));

	return Object.values(AGENT_APPS)
		.filter((app) =>
			app.slugs.some((slug) => available.some((action) => action.slug === slug))
		)
		.filter((app) => !needle || app.label.toLowerCase().includes(needle));
}

/**
 * @param {Array<Object>} actions
 * @param {string}      query
 * @return {Array<{ id: string, label: string }>}
 */
export function getActionApps(actions, query = '') {
	const needle = query.trim().toLowerCase();
	const available = actions.filter((action) => !AGENT_SLUGS.has(action.slug));

	return Object.values(ACTION_APPS)
		.filter((app) =>
			app.slugs.some((slug) => available.some((action) => action.slug === slug))
		)
		.filter((app) => !needle || app.label.toLowerCase().includes(needle));
}

/**
 * @param {'trigger'|'agent'|'action'} kind
 * @param {string}                     appId
 * @param {Array<Object>}              triggers
 * @param {Array<Object>}              actions
 * @return {Array<{ id: string, label: string }>}
 */
export function getGroupsForApp(kind, appId, triggers, actions) {
	if (kind === 'trigger' && appId === 'wordpress') {
		const groups = new Map();

		triggers
			.filter((trigger) => trigger.app === 'wordpress')
			.forEach((trigger) => {
				if (!trigger.group) {
					return;
				}

				groups.set(trigger.group, {
					id: trigger.group,
					label: trigger.group_label || trigger.group,
				});
			});

		return [...groups.values()].sort((a, b) =>
			a.label.localeCompare(b.label)
		);
	}

	return [];
}

/**
 * @param {'trigger'|'agent'|'action'} kind
 * @param {string}                     appId
 * @param {string|null}                groupId
 * @param {Array<Object>}              triggers
 * @param {Array<Object>}              actions
 * @return {Array<Object>}
 */
export function getItemsForPicker(kind, appId, groupId, triggers, actions) {
	if (kind === 'trigger') {
		if (appId === 'wordpress') {
			return triggers.filter(
				(trigger) =>
					trigger.app === 'wordpress' &&
					(!groupId || trigger.group === groupId)
			);
		}

		const appDef = INTEGRATION_TRIGGER_APPS.find((app) => app.id === appId);

		if (!appDef) {
			return [];
		}

		return appDef.slugs
			.map((slug) => findTriggerBySlug(triggers, slug))
			.filter(Boolean);
	}

	if (kind === 'agent') {
		const app = AGENT_APPS[appId];

		if (!app) {
			return [];
		}

		return actions.filter((action) => app.slugs.includes(action.slug));
	}

	const app = ACTION_APPS[appId];

	if (!app) {
		return [];
	}

	return actions.filter((action) => app.slugs.includes(action.slug));
}

/**
 * @param {'trigger'|'agent'|'action'} kind
 * @param {string}                     appId
 * @param {Array<Object>}              triggers
 * @param {Array<Object>}              actions
 * @return {boolean}
 */
export function appUsesGroups(kind, appId, triggers) {
	return kind === 'trigger' && appId === 'wordpress';
}

/**
 * @param {'trigger'|'agent'|'action'} kind
 * @return {'trigger'|'action'}
 */
export function categoryForKind(kind) {
	return kind === 'trigger' ? 'trigger' : 'action';
}
