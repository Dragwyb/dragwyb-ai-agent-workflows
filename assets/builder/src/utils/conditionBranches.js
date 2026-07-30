/**
 * Condition node branch helpers — ports, config shape, branch edges.
 */

import { NODE_WIDTH, NODE_HEIGHT, generateNodeId } from '../utils';
import {
	AGENT_TOTAL_HEIGHT,
} from './agentAttachments';

export const CONDITION_NODE_WIDTH = 320;
export const CONDITION_HEADER_HEIGHT = 44;
export const CONDITION_ROW_HEIGHT = 40;
export const CONDITION_ROW_BLOCK_HEIGHT = 72;
export const CONDITION_DEFAULT_ROW_HEIGHT = 36;
export const CONDITION_ADD_BETWEEN_HEIGHT = 28;
export const CONDITION_BRANCH_GAP_X = 72;

/** @type {Array<{ label: string, options: Array<{ value: string, label: string }> }>} */
export const CONDITION_OPERATOR_GROUPS = [
	{
		label: 'Basic operators',
		options: [
			{ value: 'exists', label: 'Exist' },
			{ value: 'not_exists', label: 'Does not exist' },
			{ value: 'is_empty', label: 'Is empty' },
			{ value: 'is_not_empty', label: 'Is not empty' },
		],
	},
	{
		label: 'Text operators',
		options: [
			{ value: 'equals', label: 'Equal to' },
			{ value: 'equals_i', label: 'Equal to (case insensitive)' },
			{ value: 'not_equals', label: 'Not equal to' },
			{ value: 'not_equals_i', label: 'Not equal to (case insensitive)' },
			{ value: 'contains', label: 'Contains' },
			{ value: 'contains_i', label: 'Contains (case insensitive)' },
			{ value: 'not_contains', label: 'Does not contain' },
			{ value: 'not_contains_i', label: 'Does not contain (case insensitive)' },
			{ value: 'starts_with', label: 'Starts with' },
			{ value: 'starts_with_i', label: 'Starts with (case insensitive)' },
			{ value: 'ends_with', label: 'Ends with' },
			{ value: 'ends_with_i', label: 'Ends with (case insensitive)' },
			{ value: 'regex', label: 'Matches regex' },
			{ value: 'regex_i', label: 'Matches regex (case insensitive)' },
			{ value: 'not_regex', label: 'Does not match regex' },
			{ value: 'not_regex_i', label: 'Does not match regex (case insensitive)' },
		],
	},
	{
		label: 'Number operators',
		options: [
			{ value: 'num_equals', label: 'Is equal to' },
			{ value: 'num_not_equals', label: 'Is not equal to' },
			{ value: 'gt', label: 'Is greater than' },
			{ value: 'lt', label: 'Is less than' },
			{ value: 'gte', label: 'Is greater than or equal to' },
			{ value: 'lte', label: 'Is less than or equal to' },
		],
	},
	{
		label: 'Date & time operators',
		options: [
			{ value: 'date_equals', label: 'Is equal to' },
			{ value: 'date_not_equals', label: 'Is not equal to' },
			{ value: 'after', label: 'Is after' },
			{ value: 'before', label: 'Is before' },
			{ value: 'after_equals', label: 'Is after or equal to' },
			{ value: 'before_equals', label: 'Is before or equal to' },
		],
	},
	{
		label: 'Boolean operators',
		options: [
			{ value: 'is_true', label: 'Is true' },
			{ value: 'is_false', label: 'Is false' },
			{ value: 'bool_equals', label: 'Is equal to' },
			{ value: 'bool_not_equals', label: 'Is not equal to' },
		],
	},
];

/** Operators that do not need a compare-to value. */
const UNARY_CONDITION_OPERATORS = new Set([
	'exists',
	'not_exists',
	'is_empty',
	'is_not_empty',
	'is_true',
	'is_false',
]);

/** Flat list used where a simple lookup is enough. */
export const CONDITION_OPERATORS = CONDITION_OPERATOR_GROUPS.flatMap(
	(group) => group.options
);

/**
 * @return {Array<{ label: string, value: string, disabled?: boolean }>}
 */
export function getConditionOperatorSelectOptions() {
	const options = [];

	CONDITION_OPERATOR_GROUPS.forEach((group) => {
		options.push({
			label: group.label,
			value: '',
			disabled: true,
		});

		group.options.forEach((operator) => {
			options.push({
				label: operator.label,
				value: operator.value,
			});
		});
	});

	return options;
}

/**
 * @param {string} operator
 * @return {boolean}
 */
export function conditionOperatorNeedsValue(operator) {
	return !UNARY_CONDITION_OPERATORS.has(operator);
}


/**
 * @return {string}
 */
export function generateConditionId() {
	return `cond-${generateNodeId().slice(0, 8)}`;
}

/**
 * @param {Object} config Node config.
 * @return {Array<Object>}
 */
export function getConditionRows(config = {}) {
	if (Array.isArray(config.conditions) && config.conditions.length > 0) {
		return config.conditions.map((row, index) => ({
			id: row.id || `cond-${index}`,
			label: row.label || 'Untitled Condition',
			field: row.field || '',
			operator: row.operator || 'equals',
			value: row.value || '',
			node_id: row.node_id || '',
		}));
	}

	if (config.field) {
		const rows = [
			{
				id: 'legacy-true',
				label: 'If yes',
				field: config.field,
				operator: config.operator || 'equals',
				value: config.value || '',
				node_id: config.true_branch_node_id || '',
			},
		];

		if (config.false_branch_node_id) {
			rows.push({
				id: 'legacy-false',
				label: 'If no',
				field: config.field,
				operator: config.operator || 'equals',
				value: config.value || '',
				node_id: config.false_branch_node_id || '',
			});
		}

		return rows;
	}

	return [];
}

/**
 * @param {Array<Object>} conditions
 * @return {number}
 */
export function conditionNodeHeight(conditions) {
	const rows = Math.max(conditions.length, 1);
	const betweenRows = Math.max(rows - 1, 0);

	return (
		CONDITION_HEADER_HEIGHT +
		8 +
		rows * CONDITION_ROW_BLOCK_HEIGHT +
		betweenRows * CONDITION_ADD_BETWEEN_HEIGHT +
		CONDITION_ADD_BETWEEN_HEIGHT +
		CONDITION_DEFAULT_ROW_HEIGHT +
		16
	);
}

/**
 * @param {Object} node Condition node.
 * @return {number}
 */
export function getConditionNodeHeight(node) {
	return conditionNodeHeight(getConditionRows(node.config || {}));
}

/**
 * @param {Object} node Canvas node.
 * @return {number}
 */
export function canvasNodeWidth(node) {
	if (node?.type === 'condition_action') {
		return CONDITION_NODE_WIDTH;
	}

	return NODE_WIDTH;
}

/**
 * @param {Object} node Canvas node.
 * @return {number}
 */
export function canvasNodeHeight(node) {
	if (node?.type === 'condition_action') {
		return getConditionNodeHeight(node);
	}

	if (node?.type === 'ai_agent_action') {
		return AGENT_TOTAL_HEIGHT;
	}

	return NODE_HEIGHT;
}


/**
 * @param {Object} node       Condition node.
 * @param {string} branchId   Condition row id or `default`.
 * @param {Array<Object>} rows Normalized condition rows.
 * @return {{ x: number, y: number }}
 */
export function conditionOutputPortPosition(node, branchId, rows) {
	const bodyTop = node.y + CONDITION_HEADER_HEIGHT + 8;

	if (branchId === 'default') {
		let y = bodyTop;

		rows.forEach((row, index) => {
			y += CONDITION_ROW_BLOCK_HEIGHT;

			if (index < rows.length - 1) {
				y += CONDITION_ADD_BETWEEN_HEIGHT;
			}
		});

		y += CONDITION_ADD_BETWEEN_HEIGHT + CONDITION_DEFAULT_ROW_HEIGHT / 2;

		return {
			x: node.x + CONDITION_NODE_WIDTH,
			y,
		};
	}

	const rowIndex = rows.findIndex((row) => row.id === branchId);
	const safeIndex = rowIndex >= 0 ? rowIndex : 0;
	let y = bodyTop + CONDITION_ROW_BLOCK_HEIGHT / 2;

	for (let index = 0; index < safeIndex; index += 1) {
		y += CONDITION_ROW_BLOCK_HEIGHT + CONDITION_ADD_BETWEEN_HEIGHT;
	}

	return {
		x: node.x + CONDITION_NODE_WIDTH,
		y,
	};
}

/**
 * @param {Object} targetNode Downstream node.
 * @return {{ x: number, y: number }}
 */
export function branchTargetInputPosition(targetNode) {
	return {
		x: targetNode.x + canvasNodeWidth(targetNode) / 2,
		y: targetNode.y,
	};
}

/**
 * @param {Object} config Node config.
 * @param {string} branchId Branch id or `default`.
 * @return {Object}
 */
export function clearConditionBranchTarget(config, branchId) {
	return setConditionBranchTarget(config, branchId, '');
}

/**
 * Main canvas nodes that a condition branch may connect to.
 *
 * @param {Array<Object>} graphNodes
 * @param {string}      excludeNodeId Condition node id.
 * @return {Array<Object>}
 */
export function getConnectableCanvasNodes(graphNodes, excludeNodeId) {
	return graphNodes
		.filter(
			(node) =>
				!node.parent_agent_id &&
				node.category !== 'trigger' &&
				node.id !== excludeNodeId
		)
		.sort((a, b) => {
			const labelA = (a.label || a.type || '').toLowerCase();
			const labelB = (b.label || b.type || '').toLowerCase();

			return labelA.localeCompare(labelB);
		});
}


/**
 * @param {Array<Object>} graphNodes All graph nodes.
 * @return {Set<string>}
 */
export function collectBranchTargetIds(graphNodes) {
	/** @type {Set<string>} */
	const targets = new Set();

	graphNodes.forEach((node) => {
		if (node.type === 'condition_action') {
			getConditionRows(node.config || {}).forEach((row) => {
				if (row.node_id) {
					targets.add(row.node_id);
				}
			});

			const defaultId = node.config?.default_branch_node_id;

			if (defaultId) {
				targets.add(defaultId);
			}
		}

		if (node.type === 'router_action') {
			const routes = node.config?.routes || [];

			routes.forEach((route) => {
				if (route?.node_id) {
					targets.add(route.node_id);
				}
			});

			const defaultId = node.config?.default_branch_node_id;

			if (defaultId) {
				targets.add(defaultId);
			}
		}
	});

	return targets;
}

/**
 * @param {Object} conditionNode Condition node.
 * @param {string} branchId      Branch id or `default`.
 * @param {number} [branchIndex] Row index for stacking.
 * @return {{ x: number, y: number }}
 */
export function defaultBranchNodePosition(conditionNode, branchId, branchIndex = 0) {
	const rows = getConditionRows(conditionNode.config || {});
	const port = conditionOutputPortPosition(conditionNode, branchId, rows);

	return {
		x: conditionNode.x + CONDITION_NODE_WIDTH + CONDITION_BRANCH_GAP_X,
		y: Math.max(48, port.y - NODE_HEIGHT / 2 + branchIndex * (NODE_HEIGHT + 24)),
	};
}

/**
 * @param {Object} config Existing config.
 * @return {Object}
 */
export function createEmptyConditionRow(config = {}) {
	return {
		id: generateConditionId(),
		label: 'Untitled Condition',
		field: '',
		operator: 'equals',
		value: '',
		node_id: '',
	};
}

/**
 * @param {Object} config Node config.
 * @param {string} branchId Branch id or `default`.
 * @param {string} nodeId Target node id.
 * @return {Object}
 */
export function setConditionBranchTarget(config, branchId, nodeId) {
	if (branchId === 'default') {
		return {
			...config,
			default_branch_node_id: nodeId,
		};
	}

	const rawConditions = Array.isArray(config?.conditions)
		? [...config.conditions]
		: [];

	if (rawConditions.length > 0) {
		let matched = false;
		const conditions = rawConditions.map((row, index) => {
			const rowId = row?.id || `cond-${index}`;

			if (rowId === branchId || row?.id === branchId) {
				matched = true;

				return {
					...row,
					id: rowId,
					node_id: nodeId,
				};
			}

			return {
				...row,
				id: rowId,
			};
		});

		if (matched) {
			return {
				...config,
				conditions,
			};
		}
	}

	const conditions = getConditionRows(config).map((row) =>
		row.id === branchId ? { ...row, node_id: nodeId } : row
	);

	return {
		...config,
		conditions,
	};
}
