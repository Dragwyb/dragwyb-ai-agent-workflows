/**
 * Portable workflow JSON helpers (n8n-style import/export).
 */

export const WORKFLOW_JSON_FORMAT = 'dragwyb-agentflow';
export const WORKFLOW_JSON_VERSION = 1;

const TRANSIENT_SETTING_KEYS = [
	'sample_payload',
	'sample_payload_trigger_type',
	'sample_payload_captured_at',
	'test_listen_active',
	'test_listen_started_at',
];

/**
 * @param {Object} options
 * @param {string} options.name
 * @param {{nodes?: Array, connections?: Array}} options.graph
 * @param {boolean} [options.active]
 * @param {Object|null} [options.settings]
 * @param {number} [options.id]
 * @return {Object}
 */
export function buildExportPayload({
	name,
	graph,
	active = false,
	settings = null,
	id = 0,
}) {
	const nodes = Array.isArray(graph?.nodes) ? graph.nodes : [];
	const connections = Array.isArray(graph?.connections)
		? graph.connections
		: [];

	return {
		name: name || 'Untitled workflow',
		nodes,
		connections,
		active: Boolean(active),
		settings: portableSettings(settings),
		meta: {
			format: WORKFLOW_JSON_FORMAT,
			version: WORKFLOW_JSON_VERSION,
			exportedAt: new Date().toISOString(),
		},
		...(id > 0 ? { id } : {}),
	};
}

/**
 * @param {Object} payload
 * @return {{name: string, graph: {nodes: Array, connections: Array}, active: boolean, settings: Object|null}}
 */
export function parseImportPayload(payload) {
	if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
		throw new Error('The import file must be a workflow JSON object.');
	}

	if (looksLikeN8n(payload)) {
		throw new Error(
			'This file looks like an n8n workflow. Import a Workflow Automate JSON export instead.'
		);
	}

	const name =
		(typeof payload.name === 'string' && payload.name.trim()) ||
		(typeof payload.title === 'string' && payload.title.trim()) ||
		'Imported workflow';

	const graph = extractGraph(payload);
	const hasGraphKeys =
		Boolean(payload.graph) ||
		Array.isArray(payload.nodes) ||
		isOurFormat(payload);

	if (graph.nodes.length === 0 && !hasGraphKeys) {
		throw new Error('This JSON does not look like a workflow definition.');
	}

	let settings = null;

	if (payload.settings && typeof payload.settings === 'object') {
		settings = portableSettings(payload.settings);
	}

	let active = false;

	if (Object.prototype.hasOwnProperty.call(payload, 'active')) {
		active = Boolean(payload.active);
	} else if (typeof payload.status === 'number') {
		active = payload.status === 1;
	}

	return {
		name,
		graph,
		active,
		settings,
	};
}

/**
 * @param {string} text
 * @return {Object}
 */
export function parseImportJson(text) {
	let decoded;

	try {
		decoded = JSON.parse(String(text || '').trim());
	} catch (error) {
		throw new Error('The import file is not valid JSON.');
	}

	return parseImportPayload(decoded);
}

/**
 * @param {Object} payload
 * @param {string} [filename]
 */
export function downloadWorkflowJson(payload, filename = 'workflow.json') {
	const blob = new Blob([JSON.stringify(payload, null, 2)], {
		type: 'application/json',
	});
	const url = URL.createObjectURL(blob);
	const link = document.createElement('a');
	const safeName = String(filename || 'workflow.json').replace(
		/[^\w.\-]+/g,
		'_'
	);

	link.href = url;
	link.download = safeName.endsWith('.json') ? safeName : `${safeName}.json`;
	document.body.appendChild(link);
	link.click();
	document.body.removeChild(link);
	URL.revokeObjectURL(url);
}

/**
 * @param {string} title
 * @return {string}
 */
export function exportFilenameFromTitle(title) {
	const slug = String(title || 'workflow')
		.toLowerCase()
		.trim()
		.replace(/[^a-z0-9]+/g, '-')
		.replace(/^-+|-+$/g, '');

	return `${slug || 'workflow'}.json`;
}

/**
 * @param {File} file
 * @return {Promise<string>}
 */
export function readFileAsText(file) {
	return new Promise((resolve, reject) => {
		const reader = new FileReader();

		reader.onload = () => resolve(String(reader.result || ''));
		reader.onerror = () => reject(new Error('Failed to read the import file.'));
		reader.readAsText(file);
	});
}

/**
 * @param {Object|null|undefined} settings
 * @return {Object}
 */
function portableSettings(settings) {
	if (!settings || typeof settings !== 'object') {
		return {};
	}

	const next = { ...settings };

	TRANSIENT_SETTING_KEYS.forEach((key) => {
		delete next[key];
	});

	return next;
}

/**
 * @param {Object} payload
 * @return {{nodes: Array, connections: Array}}
 */
function extractGraph(payload) {
	let nodes = [];
	let connections = [];

	if (payload.graph && typeof payload.graph === 'object') {
		nodes = Array.isArray(payload.graph.nodes) ? payload.graph.nodes : [];
		connections = Array.isArray(payload.graph.connections)
			? payload.graph.connections
			: [];
	} else {
		nodes = Array.isArray(payload.nodes) ? payload.nodes : [];
		connections = Array.isArray(payload.connections)
			? payload.connections
			: [];
	}

	if (
		connections &&
		typeof connections === 'object' &&
		!Array.isArray(connections)
	) {
		throw new Error(
			'Unsupported connections format. Expected a Workflow Automate connections array.'
		);
	}

	const normalizedNodes = nodes.filter(
		(node) =>
			node &&
			typeof node === 'object' &&
			typeof node.id === 'string' &&
			node.id &&
			typeof node.type === 'string' &&
			node.type
	);

	const normalizedConnections = connections
		.filter(
			(connection) =>
				connection &&
				typeof connection === 'object' &&
				connection.from &&
				connection.to
		)
		.map((connection) => ({
			...connection,
			id: connection.id || `conn-${Date.now()}-${Math.random()}`,
		}));

	return {
		nodes: normalizedNodes,
		connections: normalizedConnections,
	};
}

/**
 * @param {Object} payload
 * @return {boolean}
 */
function isOurFormat(payload) {
	return payload?.meta?.format === WORKFLOW_JSON_FORMAT;
}

/**
 * @param {Object} payload
 * @return {boolean}
 */
function looksLikeN8n(payload) {
	if (isOurFormat(payload)) {
		return false;
	}

	const nodes = payload.nodes;

	if (!Array.isArray(nodes) || nodes.length === 0) {
		return false;
	}

	const first = nodes[0];

	if (!first || typeof first !== 'object') {
		return false;
	}

	const hasTypeVersion = Object.prototype.hasOwnProperty.call(
		first,
		'typeVersion'
	);
	const type = typeof first.type === 'string' ? first.type : '';
	const isN8nType =
		type.startsWith('n8n-nodes-') || type.startsWith('@n8n/');
	const connections = payload.connections;
	const n8nConnections =
		connections &&
		typeof connections === 'object' &&
		!Array.isArray(connections);

	return (
		(hasTypeVersion && isN8nType) || (hasTypeVersion && n8nConnections)
	);
}
