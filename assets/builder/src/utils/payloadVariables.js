/**
 * Flattens a trigger payload into copyable {{trigger.*}} variable paths.
 */

/**
 * @param {*} value
 * @return {string}
 */
function formatPreview(value) {
	if (value === null || value === undefined) {
		return '';
	}

	if (typeof value === 'string') {
		return value.length > 80 ? `${value.slice(0, 80)}…` : value;
	}

	if (typeof value === 'number' || typeof value === 'boolean') {
		return String(value);
	}

	if (Array.isArray(value)) {
		return `[${value.length}]`;
	}

	if (typeof value === 'object') {
		return '{…}';
	}

	return String(value);
}

/**
 * @param {*}      value
 * @param {string} path
 * @param {Array<{ path: string, token: string, preview: string }>} variables
 * @return {void}
 */
function walkPayload(value, path, variables) {
	if (value === null || value === undefined) {
		return;
	}

	if (Array.isArray(value)) {
		value.forEach((item, index) => {
			walkPayload(item, `${path}.${index}`, variables);
		});
		return;
	}

	if (typeof value === 'object') {
		Object.entries(value).forEach(([key, child]) => {
			if (!key) {
				return;
			}

			walkPayload(child, `${path}.${key}`, variables);
		});
		return;
	}

	variables.push({
		path,
		token: `{{${path}}}`,
		preview: formatPreview(value),
	});
}

/**
 * @param {*}      payload Raw captured trigger payload.
 * @param {string} [prefix='trigger']
 * @return {Array<{ path: string, token: string, preview: string }>}
 */
export function flattenPayloadVariables(payload, prefix = 'trigger') {
	if (payload === null || payload === undefined) {
		return [];
	}

	const variables = [];

	if (Array.isArray(payload)) {
		payload.forEach((item, index) => {
			walkPayload(item, `${prefix}.${index}`, variables);
		});
	} else if (typeof payload === 'object') {
		Object.entries(payload).forEach(([key, value]) => {
			if (!key) {
				return;
			}

			walkPayload(value, `${prefix}.${key}`, variables);
		});
	} else {
		variables.push({
			path: prefix,
			token: `{{${prefix}}}`,
			preview: formatPreview(payload),
		});
	}

	return variables;
}

/**
 * @param {*} value
 * @return {boolean}
 */
function isScalar(value) {
	return (
		value === null ||
		value === undefined ||
		typeof value === 'string' ||
		typeof value === 'number' ||
		typeof value === 'boolean'
	);
}

/**
 * @typedef {{ id: string, label: string, path: string, token?: string, preview?: string, isLeaf: boolean, children?: Array<Object> }} TreeNode
 */

/**
 * @param {*}      value
 * @param {string} parentPath
 * @param {Array<TreeNode>} children
 * @return {void}
 */
function buildChildren(value, parentPath, children) {
	if (value === null || value === undefined) {
		return;
	}

	if (Array.isArray(value)) {
		value.forEach((item, index) => {
			const path = `${parentPath}.${index}`;
			const label = String(index);

			if (isScalar(item)) {
				children.push({
					id: path,
					label,
					path,
					token: `{{${path}}}`,
					preview: formatPreview(item),
					isLeaf: true,
				});
				return;
			}

			const node = {
				id: path,
				label: `[${index}]`,
				path,
				isLeaf: false,
				children: [],
			};
			buildChildren(item, path, node.children);
			children.push(node);
		});
		return;
	}

	if (typeof value === 'object') {
		Object.entries(value).forEach(([key, child]) => {
			if (!key) {
				return;
			}

			const path = `${parentPath}.${key}`;

			if (isScalar(child)) {
				children.push({
					id: path,
					label: key,
					path,
					token: `{{${path}}}`,
					preview: formatPreview(child),
					isLeaf: true,
				});
				return;
			}

			const node = {
				id: path,
				label: key,
				path,
				isLeaf: false,
				children: [],
			};
			buildChildren(child, path, node.children);
			children.push(node);
		});
	}
}

/**
 * @param {*}           payload
 * @param {string}      [prefix='trigger']
 * @param {string}      [sourceLabel='Trigger']
 * @return {TreeNode}
 */
export function buildPayloadTree(
	payload,
	prefix = 'trigger',
	sourceLabel = 'Trigger'
) {
	const root = {
		id: prefix,
		label: sourceLabel,
		path: prefix,
		isLeaf: false,
		children: [],
	};

	if (payload === null || payload === undefined) {
		return root;
	}

	if (isScalar(payload)) {
		root.children = [
			{
				id: prefix,
				label: sourceLabel,
				path: prefix,
				token: `{{${prefix}}}`,
				preview: formatPreview(payload),
				isLeaf: true,
			},
		];
		return root;
	}

	buildChildren(payload, prefix, root.children);
	return root;
}

/**
 * @param {string} path e.g. trigger.fields.email
 * @return {string}
 */
export function tokenShortLabel(path) {
	return path.replace(/^trigger\./, '');
}

/**
 * @param {string} path e.g. trigger.post_status
 * @return {string} Human label, e.g. "post status"
 */
export function pathToDisplayLabel(path) {
	return tokenShortLabel(path).replace(/_/g, ' ');
}

const TOKEN_PATTERN = /\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/g;

/**
 * @param {string} value
 * @return {Array<{ type: 'text'|'token', value: string }>}
 */
export function segmentValueWithTokens(value) {
	const text = value || '';
	const segments = [];
	let lastIndex = 0;
	const pattern = new RegExp(TOKEN_PATTERN.source, 'g');
	let match = pattern.exec(text);

	while (match) {
		if (match.index > lastIndex) {
			segments.push({
				type: 'text',
				value: text.slice(lastIndex, match.index),
			});
		}

		segments.push({
			type: 'token',
			value: match[0],
			path: match[1],
		});
		lastIndex = match.index + match[0].length;
		match = pattern.exec(text);
	}

	if (lastIndex < text.length) {
		segments.push({
			type: 'text',
			value: text.slice(lastIndex),
		});
	}

	return segments;
}

/**
 * @param {TreeNode} node
 * @param {string}   query
 * @return {boolean}
 */
export function treeNodeMatchesQuery(node, query) {
	const needle = query.trim().toLowerCase();

	if (!needle) {
		return true;
	}

	const haystack = `${node.label} ${node.path} ${node.preview || ''}`.toLowerCase();

	if (node.isLeaf) {
		return haystack.includes(needle);
	}

	return (
		haystack.includes(needle) ||
		(node.children || []).some((child) => treeNodeMatchesQuery(child, query))
	);
}

/**
 * @param {TreeNode} node
 * @param {string}   query
 * @return {TreeNode|null}
 */
export function filterTree(node, query) {
	if (!query.trim()) {
		return node;
	}

	if (!treeNodeMatchesQuery(node, query)) {
		return null;
	}

	if (node.isLeaf) {
		return node;
	}

	const children = (node.children || [])
		.map((child) => filterTree(child, query))
		.filter(Boolean);

	return {
		...node,
		children,
	};
}

