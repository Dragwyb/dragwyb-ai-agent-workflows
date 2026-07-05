/**
 * Small, dependency-free helpers shared across the builder app.
 */

export const NODE_WIDTH = 240;
export const NODE_HEIGHT = 96;
const NODE_GAP_Y = 48;
const NODE_START_X = 80;
const NODE_START_Y = 48;

/**
 * Generates a client-side id for a node. Never persisted as a database
 * primary key — the graph is stored as opaque JSON on the workflow, so
 * these only need to be unique within a single graph.
 *
 * @return {string} A reasonably unique id.
 */
export function generateNodeId() {
	if (
		typeof crypto !== 'undefined' &&
		typeof crypto.randomUUID === 'function'
	) {
		return crypto.randomUUID();
	}

	return (
		'node-' +
		Date.now().toString(36) +
		'-' +
		Math.random().toString(36).slice(2, 10)
	);
}

/**
 * @return {{nodes: Array<Object>, connections: Array<Object>}} An empty graph.
 *
 * `connections` is reserved for a future increment (drag-to-connect wiring
 * between nodes belongs to the execution-engine work, not this shell) but is
 * included now so the persisted shape never needs a breaking migration.
 */
export function emptyGraph() {
	return { nodes: [], connections: [] };
}

/**
 * Sorts nodes in visual flow order (top-to-bottom, then left-to-right).
 *
 * @param {Array<Object>} nodes
 * @return {Array<Object>}
 */
export function sortNodesForFlow(nodes) {
	return [...nodes].sort((a, b) => {
		if (a.y !== b.y) {
			return a.y - b.y;
		}

		return a.x - b.x;
	});
}

/**
 * Places new nodes at the bottom of the vertical chain.
 *
 * @param {Array<Object>} existingNodes Nodes already on the canvas.
 * @return {{x: number, y: number}} Canvas coordinates.
 */
export function defaultNodePosition(existingNodes) {
	const sorted = sortNodesForFlow(existingNodes);

	if (sorted.length === 0) {
		return { x: NODE_START_X, y: NODE_START_Y };
	}

	const last = sorted[sorted.length - 1];

	return {
		x: NODE_START_X,
		y: last.y + NODE_HEIGHT + NODE_GAP_Y,
	};
}

/**
 * Inserts a new node immediately after `afterNodeId` in flow order and
 * shifts later nodes down to keep spacing.
 *
 * @param {Array<Object>} existingNodes
 * @param {string|null}   afterNodeId Selected node to insert after; null = append.
 * @return {{ position: { x: number, y: number }, nodes: Array<Object> }}
 */
export function insertNodeInFlow(existingNodes, afterNodeId) {
	if (!afterNodeId) {
		return {
			position: defaultNodePosition(existingNodes),
			nodes: existingNodes,
		};
	}

	const flow = sortNodesForFlow(existingNodes);
	const afterIndex = flow.findIndex((node) => node.id === afterNodeId);

	if (afterIndex < 0) {
		return {
			position: defaultNodePosition(existingNodes),
			nodes: existingNodes,
		};
	}

	const afterNode = flow[afterIndex];
	const insertY = afterNode.y + NODE_HEIGHT + NODE_GAP_Y;
	const shiftBy = NODE_HEIGHT + NODE_GAP_Y;

	const nodes = existingNodes.map((node) =>
		node.y >= insertY ? { ...node, y: node.y + shiftBy } : node
	);

	return {
		position: { x: afterNode.x, y: insertY },
		nodes,
	};
}
