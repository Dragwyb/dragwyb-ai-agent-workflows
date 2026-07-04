/**
 * Small, dependency-free helpers shared across the builder app.
 */

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
 * Computes a staggered default position for a newly added node so a chain
 * of additions doesn't stack every card at the same coordinates.
 *
 * @param {number} index Zero-based index of the node being placed.
 * @return {{x: number, y: number}} Canvas coordinates.
 */
export function defaultNodePosition(index) {
	const columns = 4;
	const columnWidth = 220;
	const rowHeight = 140;

	return {
		x: 40 + (index % columns) * columnWidth,
		y: 40 + Math.floor(index / columns) * rowHeight,
	};
}
