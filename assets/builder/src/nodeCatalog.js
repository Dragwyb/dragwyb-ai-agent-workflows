/**
 * Node catalog helpers — single source for palette apps, groups, and defaults.
 */

/** @type {Set<string>} */
const AGENT_SLUGS = new Set([
	'ai_agent_action',
	'agent_output_parser',
	'openai_chat_action',
	'claude_messages_action',
	'gemini_generate_content_action',
	'openrouter_chat_action',
	'groq_chat_action',
	'deepseek_chat_action',
]);

/** @type {Set<string>} */
const TOOL_SLUGS = new Set(['condition_action', 'router_action']);

/** @type {Array<{ id: string, label: string, slugs: string[] }>} */
const INTEGRATION_TRIGGER_APPS = [
	{ id: 'elementor', label: 'Elementor', slugs: ['elementor_form_submitted_trigger', 'elementor_atomic_form_submitted_trigger'] },
	{ id: 'woocommerce', label: 'WooCommerce', slugs: [
		'woocommerce_new_order_trigger',
		'woocommerce_restore_order_trigger',
		'woocommerce_new_coupon_trigger',
		'woocommerce_create_customer_trigger',
		'woocommerce_update_customer_trigger',
		'woocommerce_delete_customer_trigger',
		'woocommerce_create_product_trigger',
		'woocommerce_update_product_trigger',
		'woocommerce_product_status_updated_trigger',
		'woocommerce_product_stock_status_updated_trigger',
		'woocommerce_delete_product_trigger',
		'woocommerce_restore_product_trigger',
		'woocommerce_product_status_changed_trigger',
		'woocommerce_product_added_to_cart_trigger',
		'woocommerce_product_removed_from_cart_trigger',
		'woocommerce_order_status_pending_trigger',
		'woocommerce_order_status_failed_trigger',
		'woocommerce_order_status_on_hold_trigger',
		'woocommerce_order_status_processing_trigger',
		'woocommerce_order_completed_trigger',
		'woocommerce_order_status_refunded_trigger',
		'woocommerce_order_status_cancelled_trigger',
		'woocommerce_order_status_changed_trigger',
	] },
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
	openrouter: {
		id: 'openrouter',
		label: 'OpenRouter',
		slugs: ['openrouter_chat_action'],
	},
	groq: {
		id: 'groq',
		label: 'Groq',
		slugs: ['groq_chat_action'],
	},
	deepseek: {
		id: 'deepseek',
		label: 'DeepSeek',
		slugs: ['deepseek_chat_action'],
	},
	'ai-agent': {
		id: 'ai-agent',
		label: 'AI Agent',
		slugs: ['ai_agent_action'],
	},
};

/** @type {Array<{ id: string, label: string, slugs: string[] }>} */
const GOOGLE_SHEETS_GROUPS = [
	{
		id: 'spreadsheet',
		label: 'Spreadsheet',
		slugs: [
			'google_sheets_create_spreadsheet_action',
			'google_sheets_find_spreadsheets_action',
			'google_sheets_delete_spreadsheet_action',
		],
	},
	{
		id: 'sheet',
		label: 'Sheet',
		slugs: [
			'google_sheets_create_sheet_action',
			'google_sheets_find_sheet_action',
			'google_sheets_copy_sheet_action',
			'google_sheets_delete_sheet_action',
			'google_sheets_clear_sheet_action',
			'google_sheets_export_sheet_action',
			'google_sheets_add_row_action',
			'google_sheets_append_row_action',
			'google_sheets_update_row_action',
			'google_sheets_append_or_update_row_action',
			'google_sheets_get_row_action',
			'google_sheets_get_all_rows_action',
			'google_sheets_delete_row_action',
			'google_sheets_create_column_action',
		],
	},
];

/** @type {Array<{ id: string, label: string }>} */
const WORDPRESS_ACTION_GROUP_ORDER = [
	{ id: 'user', label: 'User Management' },
	{ id: 'user_retrieval', label: 'User Retrieval' },
	{ id: 'user_metadata', label: 'User Metadata' },
	{ id: 'role', label: 'Role Management' },
	{ id: 'capabilities', label: 'Capabilities Management' },
	{ id: 'post', label: 'Post Management' },
	{ id: 'comment', label: 'Comment Management' },
	{ id: 'post_type', label: 'Post Type Management' },
	{ id: 'post_tag', label: 'Post Tag Management' },
	{ id: 'media', label: 'Media Management' },
	{ id: 'term', label: 'Term Management' },
	{ id: 'taxonomy', label: 'Taxonomy Management' },
	{ id: 'category', label: 'Category Management' },
	{ id: 'product_tag', label: 'Product Tag Management' },
	{ id: 'product_category', label: 'Product Category Management' },
	{ id: 'product_type', label: 'Product Type Management' },
	{ id: 'plugin', label: 'Plugin Management' },
];

/**
 * @param {Object} action
 * @return {boolean}
 */
function isWordPressAction(action) {
	return action?.app === 'wordpress' || String(action?.slug || '').startsWith('wp_');
}

/**
 * @param {Array<Object>} actions
 * @return {Array<Object>}
 */
function getWordPressActions(actions) {
	return actions.filter(isWordPressAction);
}

/** @type {Record<string, { id: string, label: string, parentId: string, slugs: string[] }>} */
const NESTED_ACTION_APPS = {
	'google-sheets': {
		id: 'google-sheets',
		label: 'Google Sheet',
		parentId: 'communication',
		slugs: GOOGLE_SHEETS_GROUPS.flatMap((group) => group.slugs),
	},
};

/** @type {Record<string, { id: string, label: string, slugs: string[], subApps?: string[], matchActions?: (action: Object) => boolean }>} */
const ACTION_APPS = {
	wordpress: {
		id: 'wordpress',
		label: 'WordPress',
		slugs: [],
		matchActions: isWordPressAction,
	},
	communication: {
		id: 'communication',
		label: 'Communication',
		slugs: [
			'send_email_action',
			'slack_incoming_webhook_action',
			'telegram_send_message_action',
			'whatsapp_cloud_send_message_action',
		],
		subApps: ['google-sheets'],
	},
	integrations: {
		id: 'integrations',
		label: 'Integrations',
		slugs: ['http_request_action'],
	},
};

/**
 * @param {string}      appId
 * @param {string|null} subAppId
 * @return {string}
 */
function getEffectiveActionAppId(appId, subAppId) {
	return subAppId || appId;
}

/**
 * @param {string}      appId
 * @param {string|null} subAppId
 * @return {{ id: string, label: string, slugs: string[] }|undefined}
 */
function getActionAppDef(appId, subAppId = null) {
	if (subAppId) {
		return NESTED_ACTION_APPS[subAppId];
	}

	return ACTION_APPS[appId];
}

/**
 * @param {Array<Object>} actions
 * @param {{ slugs: string[], subApps?: string[], matchActions?: (action: Object) => boolean }} app
 * @return {boolean}
 */
function actionAppHasItems(actions, app) {
	if (typeof app.matchActions === 'function' && actions.some((action) => app.matchActions(action))) {
		return true;
	}

	if (app.slugs.some((slug) => actions.some((action) => action.slug === slug))) {
		return true;
	}

	return (app.subApps || []).some((subAppId) => {
		const subApp = NESTED_ACTION_APPS[subAppId];

		return (
			subApp &&
			subApp.slugs.some((slug) =>
				actions.some((action) => action.slug === slug)
			)
		);
	});
}

/**
 * @param {Array<Object>} actions
 * @param {{ label: string, slugs: string[], subApps?: string[], matchActions?: (action: Object) => boolean }} app
 * @param {string}        needle
 * @return {boolean}
 */
function actionAppMatchesSearch(actions, app, needle) {
	if (app.label.toLowerCase().includes(needle)) {
		return true;
	}

	if (typeof app.matchActions === 'function') {
		if (
			actions.some(
				(action) =>
					app.matchActions(action) &&
					(action.label || '').toLowerCase().includes(needle)
			)
		) {
			return true;
		}
	}

	if (
		app.slugs.some((slug) => {
			const action = actions.find((item) => item.slug === slug);

			return action && (action.label || '').toLowerCase().includes(needle);
		})
	) {
		return true;
	}

	return (app.subApps || []).some((subAppId) => {
		const subApp = NESTED_ACTION_APPS[subAppId];

		if (!subApp) {
			return false;
		}

		if (subApp.label.toLowerCase().includes(needle)) {
			return true;
		}

		return subApp.slugs.some((slug) => {
			const action = actions.find((item) => item.slug === slug);

			return action && (action.label || '').toLowerCase().includes(needle);
		});
	});
}

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
	const available = actions.filter(
		(action) =>
			action.role === 'agent' || action.slug === 'ai_agent_action'
	);

	return available
		.filter((action) => !needle || action.label.toLowerCase().includes(needle))
		.map((action) => ({
			id: 'ai-agent',
			label: action.label,
			available: action.available !== false,
		}))
		.filter(
			(item, index, list) =>
				list.findIndex((entry) => entry.id === item.id) === index
		);
}

/** @type {Array<{ slug: string, label: string, appId: string }>} */
const CHAT_MODEL_PICKER_ITEMS = [
	{
		slug: 'openai_chat_action',
		label: 'OpenAI Chat Model',
		appId: 'openai',
	},
	{
		slug: 'gemini_generate_content_action',
		label: 'Gemini Chat Model',
		appId: 'google-ai',
	},
	{
		slug: 'claude_messages_action',
		label: 'Claude Chat Model',
		appId: 'anthropic',
	},
	{
		slug: 'openrouter_chat_action',
		label: 'OpenRouter Chat Model',
		appId: 'openrouter',
	},
	{
		slug: 'groq_chat_action',
		label: 'Groq Chat Model',
		appId: 'groq',
	},
	{
		slug: 'deepseek_chat_action',
		label: 'DeepSeek Chat Model',
		appId: 'deepseek',
	},
];

/**
 * Chat model providers for the agent + picker (not the main palette).
 *
 * @param {Array<Object>} actions
 * @param {string}      [query]
 * @return {Array<Object>}
 */
export function getChatModelPickerItems(actions, query = '') {
	const needle = query.trim().toLowerCase();

	return CHAT_MODEL_PICKER_ITEMS.map((item) => {
		const action = actions.find((entry) => entry.slug === item.slug);

		if (!action) {
			return null;
		}

		return {
			...action,
			label: item.label,
			pickerAppId: item.appId,
		};
	})
		.filter(Boolean)
		.filter(
			(item) => !needle || item.label.toLowerCase().includes(needle)
		);
}

/**
 * Flow tool nodes (Router, Condition) for the main canvas palette.
 *
 * @param {Array<Object>} actions
 * @param {string}      query
 * @return {Array<{ id: string, label: string, available: boolean }>}
 */
export function getToolApps(actions, query = '') {
	const needle = query.trim().toLowerCase();

	return getFlowToolTypes(actions)
		.filter(
			(tool) => !needle || tool.label.toLowerCase().includes(needle)
		)
		.map((tool) => ({
			id: tool.slug,
			label: tool.label,
			available: tool.available !== false,
		}));
}

/**
 * Router, Condition, and other flow tool node types.
 *
 * @param {Array<Object>} actions
 * @return {Array<Object>}
 */
export function getFlowToolTypes(actions) {
	return actions.filter(
		(action) =>
			action.role === 'tool' || TOOL_SLUGS.has(action.slug)
	);
}

/**
 * Action nodes attachable as agent tools (email, telegram, etc.).
 *
 * @param {Array<Object>} actions
 * @return {Array<Object>}
 */
export function getAgentActionToolTypes(actions) {
	return actions.filter(
		(action) =>
			!AGENT_SLUGS.has(action.slug) &&
			!TOOL_SLUGS.has(action.slug) &&
			action.role !== 'agent' &&
			action.role !== 'tool' &&
			action.available !== false
	);
}

/**
 * Ordered app sections for the agent tool picker.
 *
 * @type {Array<{ id: string, label: string, pickerAppId: string, slugs?: string[], match?: (action: Object) => boolean }>}
 */
const AGENT_TOOL_SECTION_DEFS = [
	{
		id: 'wordpress',
		label: 'WordPress',
		pickerAppId: 'wordpress',
		match: isWordPressAction,
	},
	{
		id: 'email',
		label: 'Email',
		pickerAppId: 'send_email_action',
		slugs: ['send_email_action'],
	},
	{
		id: 'slack',
		label: 'Slack',
		pickerAppId: 'integrations',
		slugs: ['slack_incoming_webhook_action'],
	},
	{
		id: 'telegram',
		label: 'Telegram',
		pickerAppId: 'telegram_send_message_action',
		slugs: ['telegram_send_message_action'],
	},
	{
		id: 'whatsapp',
		label: 'WhatsApp',
		pickerAppId: 'integrations',
		slugs: ['whatsapp_cloud_send_message_action'],
	},
	{
		id: 'google-sheets',
		label: 'Google Sheets',
		pickerAppId: 'google-sheets',
		slugs: NESTED_ACTION_APPS['google-sheets'].slugs,
	},
	{
		id: 'integrations',
		label: 'Integrations',
		pickerAppId: 'integrations',
		slugs: ['http_request_action'],
	},
];

/**
 * @param {Object} action
 * @param {string} needle
 * @return {boolean}
 */
function agentToolMatchesSearch(action, needle) {
	if (!needle) {
		return true;
	}

	return (
		(action.label || '').toLowerCase().includes(needle) ||
		(action.description || '').toLowerCase().includes(needle) ||
		(action.slug || '').toLowerCase().includes(needle)
	);
}

/**
 * Grouped picker sections for agent tools (by integration type).
 *
 * @param {Array<Object>} actions
 * @param {string}        [query]
 * @return {Array<{ id: string, label: string, items: Array<Object> }>}
 */
export function getAgentToolPickerSections(actions, query = '') {
	const tools = getAgentActionToolTypes(actions);
	const needle = query.trim().toLowerCase();
	const claimed = new Set();

	const sections = AGENT_TOOL_SECTION_DEFS.map((def) => {
		let items = tools
			.filter((action) =>
				typeof def.match === 'function'
					? def.match(action)
					: (def.slugs || []).includes(action.slug)
			)
			.map((action) => {
				claimed.add(action.slug);

				return {
					...action,
					pickerAppId: def.pickerAppId,
				};
			});

		const sectionMatches = needle && def.label.toLowerCase().includes(needle);

		if (needle && !sectionMatches) {
			items = items.filter((action) => agentToolMatchesSearch(action, needle));
		}

		return {
			id: def.id,
			label: def.label,
			items,
		};
	}).filter((section) => section.items.length > 0);

	let other = tools
		.filter((action) => !claimed.has(action.slug))
		.filter((action) => agentToolMatchesSearch(action, needle));

	if (other.length > 0) {
		sections.push({
			id: 'other',
			label: 'Other',
			items: other,
		});
	}

	return sections;
}

/**
 * @param {Array<Object>} actions
 * @param {string}      query
 * @return {Array<{ id: string, label: string }>}
 */
export function getActionApps(actions, query = '') {
	const needle = query.trim().toLowerCase();
	const available = actions.filter(
		(action) =>
			!AGENT_SLUGS.has(action.slug) &&
			!TOOL_SLUGS.has(action.slug) &&
			action.role !== 'agent' &&
			action.role !== 'tool'
	);

	return Object.values(ACTION_APPS)
		.filter((app) => actionAppHasItems(available, app))
		.filter((app) => !needle || actionAppMatchesSearch(available, app, needle));
}

/**
 * @param {'trigger'|'agent'|'action'} kind
 * @param {string}                     appId
 * @param {Array<Object>}              actions
 * @return {Array<{ id: string, label: string }>}
 */
export function getSubAppsForPicker(kind, appId, actions) {
	if (kind !== 'action') {
		return [];
	}

	const app = ACTION_APPS[appId];

	if (!app?.subApps?.length) {
		return [];
	}

	return app.subApps
		.map((subAppId) => NESTED_ACTION_APPS[subAppId])
		.filter(
			(subApp) =>
				subApp &&
				subApp.slugs.some((slug) =>
					actions.some((action) => action.slug === slug)
				)
		)
		.map((subApp) => ({
			id: subApp.id,
			label: subApp.label,
		}));
}

/**
 * @param {'trigger'|'agent'|'action'} kind
 * @param {string}                     appId
 * @param {Array<Object>}              triggers
 * @return {Array<{ id: string, label: string }>}
 */
export function getGroupsForApp(kind, appId, triggers) {
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
 * @param {string|null}                subAppId
 * @param {Array<Object>}              triggers
 * @param {Array<Object>}              actions
 * @return {Array<Object>}
 */
export function getItemsForPicker(
	kind,
	appId,
	groupId,
	subAppId,
	triggers,
	actions
) {
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
		const unified = actions.find(
			(action) => action.slug === 'ai_agent_action'
		);

		if (unified) {
			return [unified];
		}

		const app = AGENT_APPS[appId];

		if (!app) {
			return [];
		}

		return actions.filter((action) => app.slugs.includes(action.slug));
	}

	if (kind === 'agent-chat-model' || kind === 'agent-fallback-chat-model' || kind === 'parser-chat-model') {
		return getChatModelPickerItems(actions);
	}

	if (kind === 'agent-tool') {
		return getAgentActionToolTypes(actions);
	}

	if (kind === 'tool') {
		const tool = actions.find((action) => action.slug === appId);

		return tool ? [tool] : getFlowToolTypes(actions);
	}

	const app = getActionAppDef(appId, subAppId);

	if (!app) {
		return [];
	}

	if (typeof app.matchActions === 'function') {
		return actions.filter((action) => app.matchActions(action));
	}

	return actions.filter((action) => app.slugs.includes(action.slug));
}

/**
 * @param {'trigger'|'agent'|'action'} kind
 * @param {string}                     appId
 * @return {boolean}
 */
export function appUsesGroups(kind, appId) {
	return kind === 'trigger' && appId === 'wordpress';
}

/**
 * @param {'trigger'|'agent'|'action'} kind
 * @param {string}                     appId
 * @param {string|null}                subAppId
 * @return {boolean}
 */
export function appUsesGroupedSections(kind, appId, subAppId = null) {
	const effectiveId = getEffectiveActionAppId(appId, subAppId);

	return (
		kind === 'action' &&
		(effectiveId === 'google-sheets' || effectiveId === 'wordpress')
	);
}

/**
 * @param {'trigger'|'agent'|'action'} kind
 * @param {string}                     appId
 * @param {string|null}                subAppId
 * @return {string}
 */
export function getAppLabel(kind, appId, subAppId = null) {
	if (kind === 'tool') {
		return 'Tools';
	}

	if (kind === 'agent') {
		return AGENT_APPS[appId]?.label || appId;
	}

	if (kind === 'action') {
		if (subAppId && NESTED_ACTION_APPS[subAppId]) {
			return NESTED_ACTION_APPS[subAppId].label;
		}

		return ACTION_APPS[appId]?.label || appId;
	}

	if (appId === 'wordpress') {
		return 'WordPress';
	}

	return (
		INTEGRATION_TRIGGER_APPS.find((app) => app.id === appId)?.label || appId
	);
}

/**
 * @param {Object} item
 * @param {string} appId
 * @return {string}
 */
export function getPickerItemLabel(item, appId) {
	if (appId === 'google-sheets' && item.label) {
		return item.label.replace(/^Google Sheets\s+/i, '');
	}

	return item.label;
}

/**
 * @param {'trigger'|'agent'|'action'} kind
 * @param {string}                     appId
 * @param {string|null}                subAppId
 * @param {Array<Object>}              triggers
 * @param {Array<Object>}              actions
 * @return {Array<{ id: string, label: string, items: Array<Object> }>|null}
 */
export function getGroupedItemsForPicker(
	kind,
	appId,
	subAppId,
	triggers,
	actions
) {
	if (!appUsesGroupedSections(kind, appId, subAppId)) {
		return null;
	}

	const effectiveId = getEffectiveActionAppId(appId, subAppId);

	if (effectiveId === 'wordpress') {
		const appActions = getWordPressActions(actions);
		const byGroup = new Map();

		appActions.forEach((action) => {
			const groupId = action.group || 'other';
			if (!byGroup.has(groupId)) {
				byGroup.set(groupId, []);
			}
			byGroup.get(groupId).push(action);
		});

		const ordered = WORDPRESS_ACTION_GROUP_ORDER.map((group) => ({
			id: group.id,
			label: group.label,
			items: byGroup.get(group.id) || [],
			metaAppId: 'wordpress',
		})).filter((group) => group.items.length > 0);

		const knownIds = new Set(WORDPRESS_ACTION_GROUP_ORDER.map((group) => group.id));
		const extras = [...byGroup.entries()]
			.filter(([groupId]) => !knownIds.has(groupId))
			.map(([groupId, items]) => ({
				id: groupId,
				label: items[0]?.group_label || groupId,
				items,
				metaAppId: 'wordpress',
			}));

		return [...ordered, ...extras];
	}

	const app = getActionAppDef(appId, subAppId);

	if (!app) {
		return null;
	}

	const appActions = actions.filter((action) => app.slugs.includes(action.slug));
	const metaAppId = effectiveId;

	return GOOGLE_SHEETS_GROUPS.map((group) => ({
		id: group.id,
		label: group.label,
		items: group.slugs
			.map((slug) => appActions.find((action) => action.slug === slug))
			.filter(Boolean),
		metaAppId,
	})).filter((group) => group.items.length > 0);
}

/**
 * @param {'trigger'|'agent'|'action'} kind
 * @return {'trigger'|'action'}
 */
export function categoryForKind(kind) {
	return kind === 'trigger' ? 'trigger' : 'action';
}
