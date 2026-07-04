/**
 * Thin REST API client for the builder app.
 *
 * Relies on the `wp-api-fetch` script (declared as a dependency when this
 * bundle is enqueued) already having its nonce + REST-root middleware
 * configured by WordPress core itself — see BuilderPage::enqueueAssets().
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * @param {number} id Workflow id.
 * @return {Promise<Object>} The workflow resource.
 */
export function fetchWorkflow(id) {
	return apiFetch({ path: `/wfa/v1/workflows/${id}` });
}

/**
 * @param {{title: string, graph: Object}} data New workflow attributes.
 * @return {Promise<Object>} The created workflow resource.
 */
export function createWorkflow(data) {
	return apiFetch({
		path: '/wfa/v1/workflows',
		method: 'POST',
		data,
	});
}

/**
 * @param {number} id   Workflow id.
 * @param {Object} data Attributes to update (title, graph, and/or status).
 * @return {Promise<Object>} The updated workflow resource.
 */
export function updateWorkflow(id, data) {
	return apiFetch({
		path: `/wfa/v1/workflows/${id}`,
		method: 'PUT',
		data,
	});
}

/**
 * @return {Promise<{triggers: Array<Object>, actions: Array<Object>}>} Registered node types.
 */
export function fetchNodeTypes() {
	return apiFetch({ path: '/wfa/v1/node-types' });
}

/**
 * Credential-free connection summaries, used to populate a "connection"
 * config field (see ConfigPanel) with a picker rather than a raw id input.
 *
 * @return {Promise<Array<Object>>} Every stored connection's id/label/auth type.
 */
export function fetchConnections() {
	return apiFetch({ path: '/wfa/v1/connections' });
}

/**
 * Creates a connection from the builder (inline API key entry). The secret
 * is sent once over HTTPS and stored encrypted server-side; it is never
 * returned in the response.
 *
 * @param {{label: string, integration_slug: string, auth_type: string, credentials: Object}} data
 * @return {Promise<Object>} Credential-free connection summary.
 */
export function createConnection(data) {
	return apiFetch({
		path: '/wfa/v1/connections',
		method: 'POST',
		data,
	});
}

/**
 * Bootstrap data localized by BuilderPage::enqueueAssets().
 *
 * @return {{workflowId: number, listUrl: string}} Bootstrap settings.
 */
export function getBootstrap() {
	return window.wfaBuilderSettings || { workflowId: 0, listUrl: '' };
}

/**
 * @param {number} connectionId
 * @param {string} nodeType
 * @return {Promise<{options: Array<{value: string, label: string}>, error: string|null}>}
 */
export function fetchConnectionModels(connectionId, nodeType) {
	return apiFetch({
		path: `/wfa/v1/connections/${connectionId}/models?node_type=${encodeURIComponent(nodeType)}`,
	});
}

/**
 * @param {number} id Workflow id.
 * @return {Promise<Object>}
 */
export function startTestListen(id) {
	return apiFetch({
		path: `/wfa/v1/workflows/${id}/test/listen`,
		method: 'POST',
	});
}

/**
 * @param {number} id Workflow id.
 * @return {Promise<Object>}
 */
export function stopTestListen(id) {
	return apiFetch({
		path: `/wfa/v1/workflows/${id}/test/listen`,
		method: 'DELETE',
	});
}

/**
 * @param {number} id Workflow id.
 * @return {Promise<Object>}
 */
export function fetchTestStatus(id) {
	return apiFetch({ path: `/wfa/v1/workflows/${id}/test/status` });
}

/**
 * @param {number} id Workflow id.
 * @param {Object} [data]
 * @return {Promise<Object>}
 */
export function runWorkflow(id, data = {}) {
	return apiFetch({
		path: `/wfa/v1/workflows/${id}/run`,
		method: 'POST',
		data,
	});
}
