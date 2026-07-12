import { generateNodeId, NODE_HEIGHT, sortNodesForFlow } from '../utils';
import {
	canvasNodeHeight,
	canvasNodeWidth,
	collectBranchTargetIds,
} from './conditionBranches';
import {
	isAgentNode,
	isChatModelAttachment,
	isMemoryAttachment,
	isToolAttachment,
	isFallbackChatModelAttachment,
	isOutputParserAttachment,
	agentMainInputPortPosition,
	agentMainOutputPortPosition,
} from './agentAttachments';

const BRANCHING_TYPES = new Set(['condition_action', 'router_action']);
const NODE_GAP_X = 64;
const NODE_START_X = 80;
const NODE_START_Y = 48;

/**
 * @param {Object} node Canvas node.
 * @return {{ x: number, y: number }}
 */
export function nodeOutputPortPosition(node) {
	if (isAgentNode(node)) {
		return agentMainOutputPortPosition(node);
	}

	return {
		x: node.x + canvasNodeWidth(node) / 2,
		y: node.y + canvasNodeHeight(node),
	};
}

/**
 * @param {Object} node Canvas node.
 * @return {{ x: number, y: number }}
 */
export function nodeInputPortPosition(node) {
	if (isAgentNode(node)) {
		return agentMainInputPortPosition(node);
	}

	return {
		x: node.x + canvasNodeWidth(node) / 2,
		y: node.y,
	};
}

/**
 * @param {Object} node Canvas node.
 * @return {boolean}
 */
export function canStartFlowConnection(node) {
	if (!node || node.parent_agent_id) {
		return false;
	}

	return !BRANCHING_TYPES.has(node.type);
}

/**
 * @param {Object} fromNode Source node.
 * @param {Object} toNode   Target node.
 * @return {boolean}
 */
export function canConnectFlowNodes(fromNode, toNode) {
	if (!fromNode || !toNode || fromNode.id === toNode.id) {
		return false;
	}

	if (fromNode.parent_agent_id || toNode.parent_agent_id) {
		return false;
	}

	if (
		isChatModelAttachment(fromNode) ||
		isMemoryAttachment(fromNode) ||
		isToolAttachment(fromNode) ||
		isFallbackChatModelAttachment(fromNode) ||
		isOutputParserAttachment(fromNode) ||
		isChatModelAttachment(toNode) ||
		isMemoryAttachment(toNode) ||
		isToolAttachment(toNode) ||
		isFallbackChatModelAttachment(toNode) ||
		isOutputParserAttachment(toNode)
	) {
		return false;
	}

	if (toNode.category === 'trigger') {
		return false;
	}

	if (!canStartFlowConnection(fromNode)) {
		return false;
	}

	return true;
}

/**
 * Builds sequential connections from legacy vertical canvas layout.
 *
 * @param {Array<Object>} mainNodes Main canvas nodes (no attachments).
 * @return {Array<Object>}
 */
export function inferLegacyFlowConnections(mainNodes) {
	const branchTargets = collectBranchTargetIds(mainNodes);
	const flow = sortNodesForFlow(mainNodes);
	const connections = [];

	for (let index = 0; index < flow.length - 1; index += 1) {
		const node = flow[index];
		const next = flow[index + 1];

		if (BRANCHING_TYPES.has(node.type)) {
			continue;
		}

		if (branchTargets.has(next.id)) {
			continue;
		}

		connections.push({
			id: generateNodeId(),
			from: node.id,
			to: next.id,
		});
	}

	return connections;
}

/**
 * @param {Array<Object>} connections
 * @param {string}        fromNodeId
 * @param {string}        toNodeId
 * @return {Array<Object>}
 */
export function setFlowConnection(connections, fromNodeId, toNodeId) {
	const list = connections || [];
	const exists = list.some(
		(connection) =>
			connection.from === fromNodeId && connection.to === toNodeId
	);

	if (exists) {
		return list;
	}

	// Allow multiple outgoing edges from the same source (n8n-style fan-out).
	return [
		...list,
		{
			id: generateNodeId(),
			from: fromNodeId,
			to: toNodeId,
		},
	];
}

/**
 * @param {Array<Object>} connections
 * @param {string}        nodeId
 * @return {Array<Object>}
 */
export function removeConnectionsForNode(connections, nodeId) {
	return (connections || []).filter(
		(connection) => connection.from !== nodeId && connection.to !== nodeId
	);
}

export function flowPathMidpoint(from, to) {
	const midY = from.y + (to.y - from.y) / 2;

	return {
		x: (from.x + to.x) / 2,
		y: midY,
	};
}

/**
 * @param {Object} fromNode
 * @param {Object} toNode
 * @return {{ x: number, y: number }}
 */
export function positionBetweenNodes(fromNode, toNode) {
	const fromBottom = fromNode.y + canvasNodeHeight(fromNode);
	const verticalGap = toNode.y - fromBottom;
	const centeredY =
		verticalGap > NODE_HEIGHT + 16
			? fromBottom + (verticalGap - NODE_HEIGHT) / 2
			: fromBottom + 32;

	return {
		x: (fromNode.x + toNode.x) / 2,
		y: Math.max(48, centeredY),
	};
}

/**
 * @param {Array<Object>} connections
 * @param {string}        connectionId
 * @return {Array<Object>}
 */
export function removeFlowConnection(connections, connectionId) {
	return (connections || []).filter((connection) => {
		const id = connection.id || `${connection.from}-${connection.to}`;

		return id !== connectionId;
	});
}

/**
 * @param {Array<Object>} connections
 * @param {string}        fromNodeId
 * @param {string}        toNodeId
 * @param {string}        newNodeId
 * @return {Array<Object>}
 */
export function insertNodeBetweenFlow(
	connections,
	fromNodeId,
	toNodeId,
	newNodeId
) {
	const withoutEdge = (connections || []).filter(
		(connection) =>
			!(connection.from === fromNodeId && connection.to === toNodeId)
	);

	return [
		...withoutEdge,
		{
			id: generateNodeId(),
			from: fromNodeId,
			to: newNodeId,
		},
		{
			id: generateNodeId(),
			from: newNodeId,
			to: toNodeId,
		},
	];
}

/**
 * @param {Array<Object>} connections
 * @param {string}        fromNodeId
 * @param {string}        toNodeId
 * @return {Object|null}
 */
export function findFlowConnection(connections, fromNodeId, toNodeId) {
	return (
		(connections || []).find(
			(connection) =>
				connection.from === fromNodeId && connection.to === toNodeId
		) || null
	);
}

/**
 * @param {Array<Object>} connections
 * @param {string}        connectionId
 * @return {Object|null}
 */
export function getFlowConnectionById(connections, connectionId) {
	return (
		(connections || []).find((connection) => {
			const id = connection.id || `${connection.from}-${connection.to}`;

			return id === connectionId;
		}) || null
	);
}

/**
 * @param {Array<Object>} mainNodes
 * @param {Array<Object>} connections
 * @param {string|null}   selectedNodeId
 * @return {{ x: number, y: number }}
 */
export function placementForNewNode(mainNodes, selectedNodeId) {
	if (selectedNodeId) {
		const selected = mainNodes.find((node) => node.id === selectedNodeId);

		if (selected) {
			return {
				x: selected.x + canvasNodeWidth(selected) + NODE_GAP_X,
				y: selected.y,
			};
		}
	}

	if (mainNodes.length === 0) {
		return { x: NODE_START_X, y: NODE_START_Y };
	}

	const sorted = sortNodesForFlow(mainNodes);
	const last = sorted[sorted.length - 1];

	return {
		x: NODE_START_X,
		y: last.y + canvasNodeHeight(last) + 48,
	};
}

/**
 * Nodes that run before `currentNodeId` following explicit flow connections.
 *
 * @param {Array<Object>} mainNodes
 * @param {Array<Object>} connections
 * @param {string|null}   currentNodeId
 * @return {Array<Object>}
 */
export function getPriorFlowNodes(mainNodes, connections, currentNodeId) {
	if (!currentNodeId) {
		return [];
	}

	const incoming = new Map();

	(connections || []).forEach((connection) => {
		if (connection.from && connection.to) {
			incoming.set(connection.to, connection.from);
		}
	});

	const nodesById = Object.fromEntries(
		mainNodes.map((node) => [node.id, node])
	);
	const prior = [];
	let cursor = incoming.get(currentNodeId);

	while (cursor && nodesById[cursor]) {
		const node = nodesById[cursor];

		if (node.category !== 'trigger') {
			prior.unshift(node);
		}

		cursor = incoming.get(cursor);
	}

	return prior;
}
