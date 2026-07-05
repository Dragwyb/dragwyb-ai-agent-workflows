/**
 * Known output fields per action type (for variable picker + docs).
 * Matches keys written to context.nodes[nodeId] at runtime.
 */

/** @type {Record<string, Array<{ key: string, preview?: string }>>} */
const NODE_OUTPUT_FIELDS = {
	ai_agent_action: [
		{ key: 'response', preview: 'Agent reply' },
		{ key: 'parsed', preview: '{ "key": "value" }' },
		{ key: 'iterations', preview: '1' },
		{ key: 'finish_reason', preview: 'stop' },
		{ key: 'tool_calls', preview: '{}' },
		{ key: 'provider', preview: 'openai' },
		{ key: 'model', preview: 'gpt-4o-mini' },
	],
	router_action: [
		{ key: 'matched_route', preview: 'high' },
		{ key: 'field_value', preview: 'high' },
	],
	condition_action: [
		{ key: 'passed', preview: 'true' },
		{ key: 'evaluated_value', preview: 'high' },
	],
	openai_chat_action: [
		{ key: 'content', preview: 'Assistant reply' },
		{ key: 'model', preview: 'gpt-5' },
		{ key: 'status_code', preview: '200' },
	],
	claude_messages_action: [
		{ key: 'content', preview: 'Assistant reply' },
		{ key: 'model', preview: 'claude-sonnet-4-5' },
		{ key: 'status_code', preview: '200' },
	],
	gemini_generate_content_action: [
		{ key: 'content', preview: 'Generated text' },
		{ key: 'model', preview: 'gemini-2.5-flash' },
		{ key: 'status_code', preview: '200' },
	],
	telegram_send_message_action: [
		{ key: 'status_code', preview: '200' },
	],
	slack_incoming_webhook_action: [
		{ key: 'status_code', preview: '200' },
	],
	whatsapp_cloud_send_message_action: [
		{ key: 'status_code', preview: '200' },
	],
	send_email_action: [
		{ key: 'recipients', preview: 'user@example.com' },
	],
	http_request_action: [
		{ key: 'body', preview: 'Response body' },
		{ key: 'status_code', preview: '200' },
	],
	google_sheets_append_row_action: [
		{ key: 'updated_range', preview: 'Sheet1!A1' },
		{ key: 'status_code', preview: '200' },
	],
};

/**
 * @param {string} nodeTypeSlug
 * @return {Array<{ key: string, preview?: string }>}
 */
export function getNodeOutputFields(nodeTypeSlug) {
	return NODE_OUTPUT_FIELDS[nodeTypeSlug] || [
		{ key: 'success', preview: 'true' },
		{ key: 'status_code', preview: '200' },
	];
}
