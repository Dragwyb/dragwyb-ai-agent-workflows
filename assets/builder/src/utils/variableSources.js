import { sortNodesForFlow } from '../utils';
import { buildPayloadTree } from './payloadVariables';
import { getNodeOutputFields } from './nodeOutputs';

/**
 * Action/agent nodes that run before the current node in canvas order.
 *
 * @param {Array<Object>} graphNodes
 * @param {string|null}   currentNodeId
 * @return {Array<Object>}
 */
export function getPriorActionNodes(graphNodes, currentNodeId) {
	if (!currentNodeId) {
		return [];
	}

	const flow = sortNodesForFlow(graphNodes);
	const index = flow.findIndex((node) => node.id === currentNodeId);

	if (index <= 0) {
		return [];
	}

	return flow.slice(0, index).filter((node) => node.category !== 'trigger');
}

/**
 * @param {Object}        options
 * @param {Array<Object>} options.graphNodes
 * @param {string|null}   options.currentNodeId
 * @param {*}             options.triggerPayload
 * @param {string}        options.triggerLabel
 * @return {Array<{ id: string, label: string, badge: number, tree: Object }>}
 */
export function buildVariableSources({
	graphNodes,
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

	getPriorActionNodes(graphNodes, currentNodeId).forEach((node) => {
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
	});

	return sources;
}
