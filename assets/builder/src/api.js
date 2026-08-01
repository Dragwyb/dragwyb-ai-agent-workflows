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
	return apiFetch({ path: `/aiawa/v1/workflows/${id}` });
}

/**
 * @param {{title: string, graph: Object}} data New workflow attributes.
 * @return {Promise<Object>} The created workflow resource.
 */
export function createWorkflow(data) {
	return apiFetch({
		path: '/aiawa/v1/workflows',
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
		path: `/aiawa/v1/workflows/${id}`,
		method: 'PUT',
		data,
	});
}

/**
 * @return {Promise<{triggers: Array<Object>, actions: Array<Object>}>} Registered node types.
 */
export function fetchNodeTypes() {
	return apiFetch({ path: '/aiawa/v1/node-types' });
}

/**
 * Sample trigger payload (field keys) for the variable picker.
 *
 * @param {string} triggerType Trigger node slug.
 * @param {string} [formId]    Optional Elementor form id.
 * @return {Promise<{ success: boolean, payload: Object }>}
 */
export function fetchTriggerSampleSchema(triggerType, formId = '') {
	const params = new URLSearchParams({
		trigger_type: triggerType || '',
	});

	if (formId) {
		params.set('form_id', formId);
	}

	return apiFetch({
		path: `/aiawa/v1/trigger-sample-schema?${params.toString()}`,
	});
}

/**
 * Credential-free connection summaries, used to populate a "connection"
 * config field (see ConfigPanel) with a picker rather than a raw id input.
 *
 * @return {Promise<Array<Object>>} Every stored connection's id/label/auth type.
 */
export function fetchConnections() {
	return apiFetch({ path: '/aiawa/v1/connections' });
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
		path: '/aiawa/v1/connections',
		method: 'POST',
		data,
	});
}

/**
 * @param {number} connectionId
 * @param {{returnUrl?: string, nodeId?: string}} [options]
 * @return {Promise<{authorize_url: string, callback_url: string, credentials_url: string}>}
 */
export function fetchGoogleOAuthAuthorizeUrl(connectionId, options = {}) {
	const params = new URLSearchParams();

	if (options.returnUrl) {
		params.set('return_url', options.returnUrl);
	}

	if (options.nodeId) {
		params.set('node_id', options.nodeId);
	}

	const query = params.toString();

	return apiFetch({
		path: `/aiawa/v1/connections/${connectionId}/oauth/authorize-url${
			query ? `?${query}` : ''
		}`,
	});
}

/**
 * Bootstrap data localized by BuilderPage::enqueueAssets().
 *
 * @return {{workflowId: number, listUrl: string}} Bootstrap settings.
 */
export function getBootstrap() {
	return (
		window.aiawaBuilderSettings || {
			workflowId: 0,
			listUrl: '',
			connectionsUrl: '',
			aiCredentialsUrl: '',
			googleCredentialsUrl: 'https://console.cloud.google.com/apis/credentials',
			googleOAuthCallbackUrl: '',
		}
	);
}

/**
 * @param {string} provider Provider slug or node type slug.
 * @param {string} [nodeType] Optional node type for mapping.
 * @return {Promise<{options: Array<{value: string, label: string}>, error: string|null, configured?: boolean}>}
 */
export function fetchAiProviderModels(provider, nodeType = '') {
	const params = new URLSearchParams();
	if (provider) {
		params.set('provider', provider);
	}
	if (nodeType) {
		params.set('node_type', nodeType);
	}
	return apiFetch({
		path: `/aiawa/v1/ai/models?${params.toString()}`,
	});
}

/**
 * @return {Promise<{available: boolean, providers: Object<string, boolean>}>}
 */
export function fetchAiProviderStatus() {
	return apiFetch({ path: '/aiawa/v1/ai/status' });
}

/**
 * @param {string} provider
 * @param {string} apiKey
 * @return {Promise<{success: boolean, provider: string, configured: boolean}>}
 */
export function saveAiProviderCredentials(provider, apiKey) {
	return apiFetch({
		path: '/aiawa/v1/ai/credentials',
		method: 'POST',
		data: {
			provider,
			api_key: apiKey,
		},
	});
}

/**
 * @param {string} provider
 * @return {Promise<{success: boolean, provider: string, configured: boolean}>}
 */
export function clearAiProviderCredentials(provider) {
	const params = new URLSearchParams({ provider });
	return apiFetch({
		path: `/aiawa/v1/ai/credentials?${params.toString()}`,
		method: 'DELETE',
	});
}


/**
 * @param {number} id Workflow id.
 * @return {Promise<Object>}
 */
export function startTestListen(id) {
	return apiFetch({
		path: `/aiawa/v1/workflows/${id}/test/listen`,
		method: 'POST',
	});
}

/**
 * @param {number} id Workflow id.
 * @return {Promise<Object>}
 */
export function stopTestListen(id) {
	return apiFetch({
		path: `/aiawa/v1/workflows/${id}/test/listen`,
		method: 'DELETE',
	});
}

/**
 * @param {number} id Workflow id.
 * @return {Promise<Object>}
 */
export function fetchTestStatus(id) {
	return apiFetch({ path: `/aiawa/v1/workflows/${id}/test/status` });
}

/**
 * @param {number} id Workflow id.
 * @return {Promise<Object>}
 */
export function clearTestSample(id) {
	return apiFetch({
		path: `/aiawa/v1/workflows/${id}/test/sample`,
		method: 'DELETE',
	});
}

/**
 * @param {number} id Workflow id.
 * @param {{ node_id: string, graph?: Object }} data
 * @return {Promise<{ success: boolean, kind: string, output?: Object, error?: string }>}
 */
export function testWorkflowNode(id, data) {
	return apiFetch({
		path: `/aiawa/v1/workflows/${id}/test/node`,
		method: 'POST',
		data,
	});
}

/**
 * @param {number} id Workflow id.
 * @param {Object} [data]
 * @return {Promise<Object>}
 */
export function runWorkflow(id, data = {}) {
	return apiFetch({
		path: `/aiawa/v1/workflows/${id}/run`,
		method: 'POST',
		data,
	});
}

/**
 * Builder Chat panel — runs the workflow with a chatInput payload.
 *
 * @param {number} id Workflow id.
 * @param {{ chatInput: string, sessionId?: string }} data
 * @return {Promise<{ output: string, sessionId: string, run_id: number, status: number }>}
 */
export function sendWorkflowChat(id, data) {
	return apiFetch({
		path: `/aiawa/v1/workflows/${id}/chat`,
		method: 'POST',
		data,
	});
}
