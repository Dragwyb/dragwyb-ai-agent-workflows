import { sortNodesForFlow } from '../utils';
import { getPriorFlowNodes } from './flowConnections';
import { buildPayloadTree } from './payloadVariables';
import { getNodeOutputFields } from './nodeOutputs';

/**
 * Action/agent nodes that run before the current node in flow order.
 *
 * @param {Array<Object>} graphNodes
 * @param {string|null}   currentNodeId
 * @param {Array<Object>} [connections]
 * @return {Array<Object>}
 */
export function getPriorActionNodes(
	graphNodes,
	currentNodeId,
	connections = []
) {
	if (!currentNodeId) {
		return [];
	}

	const mainNodes = graphNodes.filter((node) => !node.parent_agent_id);

	if (connections.length > 0) {
		return getPriorFlowNodes(mainNodes, connections, currentNodeId);
	}

	const flow = sortNodesForFlow(mainNodes);
	const index = flow.findIndex((node) => node.id === currentNodeId);

	if (index <= 0) {
		return [];
	}

	return flow.slice(0, index).filter((node) => node.category !== 'trigger');
}

/**
 * @param {Object}        options
 * @param {Array<Object>} options.graphNodes
 * @param {Array<Object>} [options.connections]
 * @param {string|null}   options.currentNodeId
 * @param {*}             options.triggerPayload
 * @param {string}        options.triggerLabel
 * @return {Array<{ id: string, label: string, badge: number, tree: Object }>}
 */
export function buildVariableSources({
	graphNodes,
	connections = [],
	currentNodeId,
	triggerPayload,
	triggerLabel,
}) {
	const sources = [];
	let badge = 1;

	const hasTriggerData =
		triggerPayload !== null &&
		triggerPayload !== undefined &&
		typeof triggerPayload === 'object' &&
		Object.keys(triggerPayload).length > 0;

	if (hasTriggerData) {
		sources.push({
			id: 'trigger',
			label: triggerLabel,
			badge,
			tree: buildPayloadTree(triggerPayload, 'trigger', triggerLabel),
		});
		badge += 1;
	}

	getPriorActionNodes(graphNodes, currentNodeId, connections).forEach(
		(node) => {
			const fields = getNodeOutputFields(node.type);
			const outputPayload = Object.fromEntries(
				fields.map((field) => [field.key, field.preview || ''])
			);

			sources.push({
				id: node.id,
				label: node.label || node.type,
				badge,
				tree: buildPayloadTree(
					outputPayload,
					`nodes.${node.id}`,
					node.label || 'Step'
				),
			});
			badge += 1;
		}
	);

	return sources;
}

/**
 * @param {Array<Object>} graphNodes
 * @return {Object|null}
 */
export function getTriggerNode(graphNodes = []) {
	return (
		graphNodes.find(
			(node) => node.category === 'trigger' && !node.parent_agent_id
		) || null
	);
}

/**
 * Minimal Elementor-shaped stub when the schema API is unavailable.
 *
 * @return {Object}
 */
export function elementorTriggerStubPayload() {
	return {
		source: 'elementor',
		event: 'form_submitted',
		form_name: '',
		form_id: '',
		form_post_id: '',
		fields: {
			name: '',
			email: '',
			message: '',
		},
	};
}
