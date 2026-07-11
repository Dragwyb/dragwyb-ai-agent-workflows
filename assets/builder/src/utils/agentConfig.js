/**
 * AI Agent configuration defaults, validation, and constants.
 */

import {
	chatModelForAgent,
	fallbackChatModelForAgent,
	memoryForAgent,
	outputParserForAgent,
	toolsForAgent,
} from './agentAttachments';

export const AI_AGENT_VERSION = '1.0';

export const AGENT_TUTORIAL_DISMISS_KEY = 'wfa_agent_tutorial_dismissed';

export const PROMPT_SOURCE_DEFINE = 'define_below';

export const PROMPT_SOURCE_CHAT_TRIGGER = 'connected_chat_trigger';

export const ON_ERROR_STOP = 'stop_workflow';

export const ON_ERROR_CONTINUE = 'continue';

export const ON_ERROR_ERROR_OUTPUT = 'continue_error_output';

/** @type {Array<{ id: string, label: string, description?: string }>} */
export const AGENT_OPTION_CATALOG = [
	{
		id: 'system_prompt',
		label: 'Instructions for the AI',
	},
	{
		id: 'max_iterations',
		label: 'Max tool iterations',
	},
];

/**
 * @param {Object} [config]
 * @return {Object}
 */
export function normalizeAgentConfig(config = {}) {
	const settings = config.settings && typeof config.settings === 'object'
		? config.settings
		: {};

	return {
		prompt_source: config.prompt_source || PROMPT_SOURCE_DEFINE,
		prompt: config.prompt ?? '',
		require_output_format: Boolean(config.require_output_format),
		fallback_enabled: Boolean(config.fallback_enabled),
		system_prompt: config.system_prompt ?? '',
		max_iterations: Number(config.max_iterations ?? 5),
		output_format: config.output_format || 'text',
		options: Array.isArray(config.options) ? [...config.options] : [],
		settings: {
			always_output_data: Boolean(settings.always_output_data),
			execute_once: Boolean(settings.execute_once),
			retry_on_fail: Boolean(settings.retry_on_fail),
			max_tries: Math.max(1, Number(settings.max_tries ?? 3)),
			wait_between_tries_ms: Math.max(
				0,
				Number(settings.wait_between_tries_ms ?? 1000)
			),
			on_error: settings.on_error || ON_ERROR_STOP,
			notes: settings.notes ?? '',
			display_note_in_flow: Boolean(settings.display_note_in_flow),
		},
	};
}

/**
 * @return {boolean}
 */
export function isAgentTutorialDismissed() {
	try {
		return localStorage.getItem(AGENT_TUTORIAL_DISMISS_KEY) === '1';
	} catch {
		return false;
	}
}

/**
 * @return {void}
 */
export function dismissAgentTutorial() {
	try {
		localStorage.setItem(AGENT_TUTORIAL_DISMISS_KEY, '1');
	} catch {
		// Ignore storage failures.
	}
}

/**
 * @param {Object}        config
 * @param {Array<Object>} graphNodes
 * @param {string}        agentId
 * @param {Array<Object>} [graphConnections]
 * @return {Array<{ field: string, message: string }>}
 */
export function validateAgentConfig(
	config,
	graphNodes,
	agentId,
	graphConnections = []
) {
	const normalized = normalizeAgentConfig(config);
	const errors = [];

	if (!chatModelForAgent(graphNodes, agentId)) {
		errors.push({
			field: 'chat_model',
			message: 'Connect a Chat Model to the agent.',
		});
	}

	if (normalized.prompt_source === PROMPT_SOURCE_DEFINE) {
		if (!String(normalized.prompt || '').trim()) {
			errors.push({
				field: 'prompt',
				message: 'Enter a prompt or switch to Connected Chat Trigger Node.',
			});
		}
	} else if (
		!agentHasConnectedChatTrigger(graphNodes, agentId, graphConnections)
	) {
		errors.push({
			field: 'prompt_source',
			message: 'Connect a trigger node to the AI Agent for chatInput.',
		});
	}

	if (normalized.require_output_format && !outputParserForAgent(graphNodes, agentId)) {
		errors.push({
			field: 'output_parser',
			message: 'Connect an Output Parser on the canvas when output format is required.',
		});
	}

	if (normalized.fallback_enabled && !fallbackChatModelForAgent(graphNodes, agentId)) {
		errors.push({
			field: 'fallback_chat_model',
			message: 'Connect a Fallback Chat Model when fallback is enabled.',
		});
	}

	return errors;
}

/**
 * @param {Array<Object>} graphNodes
 * @param {string}        agentId
 * @return {boolean}
 */
export function agentHasConnectedChatTrigger(graphNodes, agentId, connections = []) {
	const incoming = (connections || []).find(
		(connection) => connection.to === agentId
	);

	if (!incoming) {
		return false;
	}

	const source = graphNodes.find((node) => node.id === incoming.from);

	return Boolean(source && source.category === 'trigger');
}

/**
 * @param {Array<Object>} graphNodes
 * @param {string}        agentId
 * @return {{ chatModel: Object|null, fallbackChatModel: Object|null, memory: Object|null, tools: Array<Object>, outputParser: Object|null }}
 */
export function resolveAgentAttachments(graphNodes, agentId) {
	return {
		chatModel: chatModelForAgent(graphNodes, agentId),
		fallbackChatModel: fallbackChatModelForAgent(graphNodes, agentId),
		memory: memoryForAgent(graphNodes, agentId),
		tools: toolsForAgent(graphNodes, agentId),
		outputParser: outputParserForAgent(graphNodes, agentId),
	};
}
