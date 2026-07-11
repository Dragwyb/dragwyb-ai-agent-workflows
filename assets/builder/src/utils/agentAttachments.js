/**
 * AI Agent attachment helpers (chat model, memory, tools link to agent).
 */

import { NODE_WIDTH } from '../utils';

export const AI_AGENT_SLUG = 'ai_agent_action';

/** @type {Set<string>} */
export const CHAT_MODEL_SLUGS = new Set([
	'openai_chat_action',
	'gemini_generate_content_action',
	'claude_messages_action',
	'openrouter_chat_action',
	'groq_chat_action',
	'deepseek_chat_action',
]);

/** @type {Record<string, string>} */
export const PROVIDER_BY_CHAT_MODEL_SLUG = {
	openai_chat_action: 'openai',
	gemini_generate_content_action: 'gemini',
	claude_messages_action: 'claude',
	openrouter_chat_action: 'openrouter',
	groq_chat_action: 'groq',
	deepseek_chat_action: 'deepseek',
};

/** @type {Record<string, string>} */
export const CHAT_MODEL_APP_IDS = {
	openai_chat_action: 'openai',
	gemini_generate_content_action: 'google-ai',
	claude_messages_action: 'anthropic',
	openrouter_chat_action: 'openrouter',
	groq_chat_action: 'groq',
	deepseek_chat_action: 'deepseek',
};

/** @type {Record<string, string>} */
export const DEFAULT_MODEL_BY_PROVIDER = {
	openai: 'gpt-4o-mini',
	gemini: 'gemini-2.0-flash',
	claude: 'claude-sonnet-4-20250514',
	openrouter: 'openai/gpt-4o-mini',
	groq: 'llama-3.3-70b-versatile',
	deepseek: 'deepseek-chat',
};

export const AGENT_BODY_HEIGHT = 96;
export const AGENT_PORTS_HEIGHT = 36;
export const AGENT_TOTAL_HEIGHT = AGENT_BODY_HEIGHT + AGENT_PORTS_HEIGHT;
export const ATTACHMENT_GAP = 56;
export const TOOL_NODE_HEIGHT = 52;
export const CHAT_MODEL_NODE_SIZE = 72;
export const MEMORY_NODE_HEIGHT = 40;

/**
 * @param {Object} node
 * @return {boolean}
 */
export function isAgentNode(node) {
	return node?.type === AI_AGENT_SLUG;
}

/**
 * @param {Object} node
 * @return {boolean}
 */
export function isChatModelAttachment(node) {
	return (
		node?.attachment_type === 'chat_model' ||
		(Boolean(node?.parent_agent_id) && CHAT_MODEL_SLUGS.has(node?.type))
	);
}

/**
 * @param {Object} node
 * @return {boolean}
 */
export function isMemoryAttachment(node) {
	return node?.attachment_type === 'memory';
}

/**
 * @param {Object} node
 * @return {boolean}
 */
export function isToolAttachment(node) {
	return (
		node?.attachment_type === 'tool' ||
		(Boolean(node?.parent_agent_id) &&
			!isChatModelAttachment(node) &&
			!isMemoryAttachment(node))
	);
}

/**
 * @param {Array<Object>} nodes
 * @return {Array<Object>}
 */
export function mainCanvasNodes(nodes) {
	return nodes.filter((node) => !node.parent_agent_id);
}

/**
 * @param {Array<Object>} nodes
 * @param {string}        agentId
 * @return {Object|null}
 */
export function chatModelForAgent(nodes, agentId) {
	return (
		nodes.find(
			(node) =>
				node.parent_agent_id === agentId && isChatModelAttachment(node)
		) || null
	);
}

/**
 * @param {Array<Object>} nodes
 * @param {string}        agentId
 * @return {Object|null}
 */
export function memoryForAgent(nodes, agentId) {
	return (
		nodes.find(
			(node) =>
				node.parent_agent_id === agentId && isMemoryAttachment(node)
		) || null
	);
}

/**
 * @param {Array<Object>} nodes
 * @param {string}        agentId
 * @return {Array<Object>}
 */
export function toolsForAgent(nodes, agentId) {
	return nodes.filter(
		(node) => node.parent_agent_id === agentId && isToolAttachment(node)
	);
}

/**
 * @param {string} chatModelSlug
 * @return {string}
 */
export function providerFromChatModelSlug(chatModelSlug) {
	return (
		PROVIDER_BY_CHAT_MODEL_SLUG[chatModelSlug] ||
		PROVIDER_BY_CHAT_MODEL_SLUG.openai_chat_action
	);
}

/**
 * Sync agent inline config from an attached chat model sub-node.
 *
 * @param {Object} agentNode
 * @param {Object} chatModelNode
 * @return {Object}
 */
export function syncAgentConfigFromChatModel(agentNode, chatModelNode) {
	const provider = providerFromChatModelSlug(chatModelNode.type);

	return {
		...agentNode,
		config: {
			...agentNode.config,
			provider,
			connection_id: Number(chatModelNode.config?.connection_id || 0),
			model:
				chatModelNode.config?.model ||
				DEFAULT_MODEL_BY_PROVIDER[provider] ||
				DEFAULT_MODEL_BY_PROVIDER.openai,
		},
	};
}

/**
 * @param {Object} agentNode
 * @return {{ x: number, y: number }}
 */
export function agentChatModelPortPosition(agentNode) {
	return {
		x: agentNode.x + NODE_WIDTH / 6,
		y: agentNode.y + AGENT_BODY_HEIGHT + AGENT_PORTS_HEIGHT - 4,
	};
}

/**
 * @param {Object} agentNode
 * @return {{ x: number, y: number }}
 */
export function agentMemoryPortPosition(agentNode) {
	return {
		x: agentNode.x + NODE_WIDTH / 2,
		y: agentNode.y + AGENT_BODY_HEIGHT + AGENT_PORTS_HEIGHT - 4,
	};
}

/**
 * @param {Object} agentNode
 * @return {{ x: number, y: number }}
 */
export function agentToolPortPosition(agentNode) {
	return {
		x: agentNode.x + (NODE_WIDTH * 5) / 6,
		y: agentNode.y + AGENT_BODY_HEIGHT + AGENT_PORTS_HEIGHT - 4,
	};
}

/**
 * @param {Object} agentNode
 * @return {{ x: number, y: number }}
 */
export function chatModelAttachmentPosition(agentNode) {
	return {
		x: agentNode.x + NODE_WIDTH / 6 - CHAT_MODEL_NODE_SIZE / 2,
		y: agentNode.y + AGENT_TOTAL_HEIGHT + ATTACHMENT_GAP,
	};
}

/**
 * @param {Object} agentNode
 * @return {{ x: number, y: number }}
 */
export function memoryAttachmentPosition(agentNode) {
	return {
		x: agentNode.x + NODE_WIDTH / 2 - 70,
		y: agentNode.y + AGENT_TOTAL_HEIGHT + ATTACHMENT_GAP,
	};
}

/**
 * @param {Object} agentNode
 * @param {number} index
 * @return {{ x: number, y: number }}
 */
export function toolAttachmentPosition(agentNode, index) {
	return {
		x: agentNode.x + NODE_WIDTH / 2 - 20,
		y:
			agentNode.y +
			AGENT_TOTAL_HEIGHT +
			ATTACHMENT_GAP +
			index * (TOOL_NODE_HEIGHT + 16),
	};
}

/**
 * @param {Object} chatModelNode
 * @return {{ x: number, y: number }}
 */
export function chatModelInputPortPosition(chatModelNode) {
	return {
		x: chatModelNode.x + CHAT_MODEL_NODE_SIZE / 2,
		y: chatModelNode.y,
	};
}

/**
 * @param {Object} memoryNode
 * @return {{ x: number, y: number }}
 */
export function memoryInputPortPosition(memoryNode) {
	return {
		x: memoryNode.x + 70,
		y: memoryNode.y,
	};
}

/**
 * @param {Object} toolNode
 * @return {{ x: number, y: number }}
 */
export function toolInputPortPosition(toolNode) {
	return {
		x: toolNode.x + 100,
		y: toolNode.y,
	};
}

/**
 * Repositions an agent and every node attached via parent_agent_id.
 *
 * @param {Array<Object>} nodes   Full graph nodes.
 * @param {string}        agentId Agent client node id.
 * @param {number}        agentX  New agent x.
 * @param {number}        agentY  New agent y.
 * @return {Array<Object>}
 */
export function syncAgentGroupPositions(nodes, agentId, agentX, agentY) {
	const agentPoint = { x: agentX, y: agentY };
	const tools = toolsForAgent(nodes, agentId);

	return nodes.map((node) => {
		if (node.id === agentId) {
			return { ...node, x: agentX, y: agentY };
		}

		if (node.parent_agent_id !== agentId) {
			return node;
		}

		if (isChatModelAttachment(node)) {
			const position = chatModelAttachmentPosition(agentPoint);
			return { ...node, x: position.x, y: position.y };
		}

		if (isMemoryAttachment(node)) {
			const position = memoryAttachmentPosition(agentPoint);
			return { ...node, x: position.x, y: position.y };
		}

		if (isToolAttachment(node)) {
			const index = tools.findIndex((tool) => tool.id === node.id);
			const position = toolAttachmentPosition(
				agentPoint,
				index >= 0 ? index : 0
			);
			return { ...node, x: position.x, y: position.y };
		}

		return node;
	});
}

/**
 * @param {Array<Object>} nodes
 * @param {string}        agentId
 * @return {Array<Object>}
 */
export function removeAgentAttachments(nodes, agentId) {
	return nodes.filter((node) => node.parent_agent_id !== agentId);
}
