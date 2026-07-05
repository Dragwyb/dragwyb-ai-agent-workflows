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
 * @param {string}              path
 * @param {Record<string, string>} [nodeLabels] Map of node id → label for nodes.* paths.
 * @return {string}
 */
function tokenShortLabel(path, nodeLabels = {}) {
	if (path.startsWith('nodes.')) {
		const segments = path.split('.');
		const nodeId = segments[1] || '';
		const fieldPath = segments.slice(2).join('.');

		if (nodeId && fieldPath) {
			const stepLabel = nodeLabels[nodeId] || 'step';
			return `${stepLabel} → ${fieldPath}`;
		}
	}

	return path.replace(/^trigger\./, '');
}

/**
 * @param {string}              path e.g. trigger.post_status or nodes.uuid.content
 * @param {Record<string, string>} [nodeLabels]
 * @return {string} Human label
 */
export function pathToDisplayLabel(path, nodeLabels = {}) {
	return tokenShortLabel(path, nodeLabels).replace(/_/g, ' ').replace(/ → /g, ' → ');
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
function treeNodeMatchesQuery(node, query) {
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

