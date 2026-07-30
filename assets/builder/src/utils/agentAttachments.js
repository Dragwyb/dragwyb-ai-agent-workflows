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
export const CHAT_MODEL_NODE_SIZE = 104;
export const MEMORY_NODE_HEIGHT = 40;
export const OUTPUT_PARSER_NODE_WIDTH = 200;
export const OUTPUT_PARSER_NODE_HEIGHT = 78;

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
	if (node?.attachment_type === 'parser_chat_model') {
		return false;
	}

	return (
		node?.attachment_type === 'chat_model' ||
		(Boolean(node?.parent_agent_id) &&
			CHAT_MODEL_SLUGS.has(node?.type) &&
			node?.attachment_type !== 'fallback_chat_model')
	);
}

/**
 * @param {Object} node
 * @return {boolean}
 */
export function isFallbackChatModelAttachment(node) {
	return node?.attachment_type === 'fallback_chat_model';
}

/**
 * @param {Object} node
 * @return {boolean}
 */
export function isOutputParserAttachment(node) {
	return node?.attachment_type === 'output_parser';
}

/**
 * @param {Object} node
 * @return {boolean}
 */
export function isParserChatModelAttachment(node) {
	return node?.attachment_type === 'parser_chat_model';
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
			!isFallbackChatModelAttachment(node) &&
			!isMemoryAttachment(node) &&
			!isOutputParserAttachment(node) &&
			!isParserChatModelAttachment(node))
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
export function fallbackChatModelForAgent(nodes, agentId) {
	return (
		nodes.find(
			(node) =>
				node.parent_agent_id === agentId &&
				isFallbackChatModelAttachment(node)
		) || null
	);
}

/**
 * @param {Array<Object>} nodes
 * @param {string}        agentId
 * @return {Object|null}
 */
export function outputParserForAgent(nodes, agentId) {
	return (
		nodes.find(
			(node) =>
				node.parent_agent_id === agentId &&
				isOutputParserAttachment(node)
		) || null
	);
}

/**
 * @param {Array<Object>} nodes
 * @param {string}        parserId
 * @return {Object|null}
 */
export function chatModelForOutputParser(nodes, parserId) {
	return (
		nodes.find(
			(node) =>
				node.parent_agent_id === parserId &&
				isParserChatModelAttachment(node)
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
 * Main workflow input port (left edge, body center).
 *
 * @param {Object} agentNode
 * @return {{ x: number, y: number }}
 */
export function agentMainInputPortPosition(agentNode) {
	return {
		x: agentNode.x,
		y: agentNode.y + AGENT_BODY_HEIGHT / 2,
	};
}

/**
 * Main workflow output port (right edge, body center).
 *
 * @param {Object} agentNode
 * @return {{ x: number, y: number }}
 */
export function agentMainOutputPortPosition(agentNode) {
	return {
		x: agentNode.x + NODE_WIDTH,
		y: agentNode.y + AGENT_BODY_HEIGHT / 2,
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
export function fallbackChatModelAttachmentPosition(agentNode) {
	return {
		x: agentNode.x + NODE_WIDTH / 4 - CHAT_MODEL_NODE_SIZE / 2,
		y: agentNode.y + AGENT_TOTAL_HEIGHT + ATTACHMENT_GAP + CHAT_MODEL_NODE_SIZE + 24,
	};
}

/**
 * @param {Object} agentNode
 * @return {{ x: number, y: number }}
 */
export function outputParserAttachmentPosition(agentNode) {
	return {
		x: agentNode.x + (NODE_WIDTH * 3) / 4 - OUTPUT_PARSER_NODE_WIDTH / 2,
		y: agentNode.y + AGENT_TOTAL_HEIGHT + ATTACHMENT_GAP + CHAT_MODEL_NODE_SIZE + 24,
	};
}

/**
 * @param {Object} parserNode
 * @return {{ x: number, y: number }}
 */
export function parserChatModelAttachmentPosition(parserNode) {
	return {
		x: parserNode.x + OUTPUT_PARSER_NODE_WIDTH / 2 - CHAT_MODEL_NODE_SIZE / 2,
		y: parserNode.y + OUTPUT_PARSER_NODE_HEIGHT + ATTACHMENT_GAP,
	};
}

/**
 * @param {Object} parserNode
 * @return {{ x: number, y: number }}
 */
export function outputParserInputPortPosition(parserNode) {
	return {
		x: parserNode.x + OUTPUT_PARSER_NODE_WIDTH / 2,
		y: parserNode.y,
	};
}

/**
 * Model* port on the bottom of the output parser card.
 *
 * @param {Object} parserNode
 * @return {{ x: number, y: number }}
 */
export function outputParserModelPortPosition(parserNode) {
	return {
		x: parserNode.x + OUTPUT_PARSER_NODE_WIDTH / 2,
		y: parserNode.y + OUTPUT_PARSER_NODE_HEIGHT,
	};
}

/**
 * Port on the agent body for the output-parser attachment edge.
 *
 * @param {Object} agentNode
 * @return {{ x: number, y: number }}
 */
export function agentOutputParserPortPosition(agentNode) {
	return {
		x: agentNode.x + (NODE_WIDTH * 3) / 4,
		y: agentNode.y + AGENT_TOTAL_HEIGHT,
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
 * @param {Array<Object>} nodes
 * @param {string}        agentId
 * @return {Array<Object>}
 */
export function removeAgentAttachments(nodes, agentId) {
	const parserIds = new Set(
		nodes
			.filter(
				(node) =>
					node.parent_agent_id === agentId &&
					isOutputParserAttachment(node)
			)
			.map((node) => node.id)
	);

	return nodes.filter(
		(node) =>
			node.parent_agent_id !== agentId &&
			!parserIds.has(node.parent_agent_id)
	);
}
