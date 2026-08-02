import { useState, useEffect, useMemo } from '@wordpress/element';
import {
	Button,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { createConnection, fetchAiProviderModels, fetchAiProviderStatus, saveAiProviderCredentials, clearAiProviderCredentials, fetchGoogleOAuthAuthorizeUrl, fetchTriggerSampleSchema, getBootstrap, testWorkflowNode} from '../api';
import CapturedResponse from './CapturedResponse';
import NodeTestResult from './NodeTestResult';
import AgentConfigPanel from './AgentConfigPanel';
import TokenField, { fieldSupportsVariables } from './TokenField';
import {
	buildVariableSources,
	elementorTriggerStubPayload,
	getTriggerNode,
} from '../utils/variableSources';
import { validateAgentConfig } from '../utils/agentConfig';
import {
	getConditionOperatorSelectOptions,
	conditionOperatorNeedsValue,
	createEmptyConditionRow,
	getConnectableCanvasNodes,
} from '../utils/conditionBranches';

const GOOGLE_SHEETS_OAUTH_SETTINGS = {
	authType: 'oauth2',
	hideAuthTypeSelect: true,
	oauthConnection: true,
};

const GOOGLE_SHEETS_SLUG_ALIASES = [
	'google_sheets',
	'google_sheets_api',
	'sheets',
];

/**
 * @param {string} slug
 * @return {boolean}
 */
function isGoogleSheetsAction(slug) {
	return Boolean(slug && slug.startsWith('google_sheets_'));
}

/**
 * Per-integration defaults for the inline connection form so switching
 * nodes does not leak labels or auth types (e.g. Gemini → Telegram).
 *
 * @type {Record<string, {authType?: string, secretLabel?: string, secretFieldName?: string, hideAuthTypeSelect?: boolean, oauthConnection?: boolean}>}
 */
const INTEGRATION_CONNECTION_SETTINGS = {
	telegram_send_message_action: {
		authType: 'api_key',
		secretLabel: __('Bot token', 'dragwyb-agentflow'),
		secretFieldName: 'api_key',
		hideAuthTypeSelect: true,
	},
	whatsapp_cloud_send_message_action: {
		authType: 'bearer_token',
		secretLabel: __('Access token', 'dragwyb-agentflow'),
	},
};

/**
 * Acceptable `integration_slug` values on saved connections for each node type.
 * Connections created from the admin Connections screen often use short names
 * (e.g. `gemini`) instead of the full node type slug.
 *
 * @type {Record<string, string[]>}
 */
const INTEGRATION_SLUG_ALIASES = {
	gemini_generate_content_action: [
		'gemini',
		'google_gemini',
		'google_gemini_api',
		'google_ai',
	],
	openai_chat_action: ['openai', 'open_ai'],
	claude_messages_action: ['claude', 'anthropic'],
	openrouter_chat_action: ['openrouter', 'open_router'],
	groq_chat_action: ['groq'],
	deepseek_chat_action: ['deepseek', 'deep_seek'],
	telegram_send_message_action: ['telegram'],
	whatsapp_cloud_send_message_action: ['whatsapp', 'whatsapp_cloud'],
	google_sheets_append_row_action: [
		'google_sheets',
		'google_sheets_api',
		'sheets',
	],
	ai_agent_action: [
		'openai',
		'open_ai',
		'gemini',
		'google_gemini',
		'google_ai',
		'claude',
		'anthropic',
		'openrouter',
		'open_router',
		'groq',
		'deepseek',
		'deep_seek',
		'ai_agent',
	],
};

/** @type {Set<string>} */
const AGENT_SIDEBAR_HIDDEN_FIELDS = new Set([
	'provider',
	'connection_id',
	'api_credentials',
	'model',
	'prompt',
	'system_prompt',
	'max_iterations',
	'output_format',
	'prompt_source',
	'require_output_format',
	'clean_output',
	'fallback_enabled',
	'options',
	'settings',
]);

/** @type {Set<string>} */
const CHAT_MODEL_ATTACHMENT_FIELDS = new Set(['api_credentials', 'model']);

/** @type {Record<string, string>} */
const AGENT_PROVIDER_NODE_SLUGS = {
	openai: 'openai_chat_action',
	gemini: 'gemini_generate_content_action',
	claude: 'claude_messages_action',
	openrouter: 'openrouter_chat_action',
	groq: 'groq_chat_action',
	deepseek: 'deepseek_chat_action',
};

/** @type {Record<string, { secretLabel: string }>} */
const AGENT_PROVIDER_CONNECTION_SETTINGS = {
	openai: {
		secretLabel: __('OpenAI API key', 'dragwyb-agentflow'),
	},
	gemini: {
		secretLabel: __('Google AI API key', 'dragwyb-agentflow'),
	},
	claude: {
		secretLabel: __('Anthropic API key', 'dragwyb-agentflow'),
	},
	openrouter: {
		secretLabel: __('OpenRouter API key', 'dragwyb-agentflow'),
	},
	groq: {
		secretLabel: __('Groq API key', 'dragwyb-agentflow'),
	},
	deepseek: {
		secretLabel: __('DeepSeek API key', 'dragwyb-agentflow'),
	},
};

/**
 * @param {string} nodeTypeSlug
 * @param {Object} nodeConfig
 * @return {string}
 */
function resolveConnectionNodeSlug(nodeTypeSlug, nodeConfig = {}) {
	if (nodeTypeSlug !== 'ai_agent_action') {
		return nodeTypeSlug;
	}

	const provider = String(nodeConfig.provider || 'openai').toLowerCase();

	return AGENT_PROVIDER_NODE_SLUGS[provider] || AGENT_PROVIDER_NODE_SLUGS.openai;
}

/**
 * @param {Object} connection
 * @param {string} nodeTypeSlug
 * @param {Object} [nodeConfig]
 * @return {boolean}
 */
function connectionMatchesNodeType(connection, nodeTypeSlug, nodeConfig = {}) {
	const effectiveSlug = resolveConnectionNodeSlug(nodeTypeSlug, nodeConfig);
	const slug = connection.integration_slug || '';

	if (slug === effectiveSlug || slug === nodeTypeSlug) {
		return true;
	}

	if (isGoogleSheetsAction(nodeTypeSlug)) {
		return GOOGLE_SHEETS_SLUG_ALIASES.includes(slug) || isGoogleSheetsAction(slug);
	}

	const aliases = [
		...(INTEGRATION_SLUG_ALIASES[nodeTypeSlug] || []),
		...(INTEGRATION_SLUG_ALIASES[effectiveSlug] || []),
	];

	return aliases.includes(slug);
}

/**
 * @param {Array<Object>} connections
 * @param {string}      nodeTypeSlug
 * @param {number}      selectedId
 * @return {Array<Object>}
 */
function filterMatchingConnections(connections, nodeTypeSlug, selectedId, nodeConfig = {}) {
	const list = (connections || []).filter((connection) =>
		connectionMatchesNodeType(connection, nodeTypeSlug, nodeConfig)
	);

	if (selectedId > 0 && !list.some((connection) => connection.id === selectedId)) {
		const selected = (connections || []).find(
			(connection) => connection.id === selectedId
		);

		if (selected) {
			return [selected, ...list];
		}
	}

	return list;
}

/**
 * Right-hand panel for the currently selected node.
 *
 * @param {Object}        props
 * @param {Object}        props.node
 * @param {Object|null}   props.nodeType
 * @param {Array<Object>} props.connections
 * @param {Function}      props.onConnectionsChange
 * @param {Function}      props.onChangeLabel
 * @param {Function}      props.onChangeConfig
 * @param {Function}      props.onDelete
 * @param {Function}      props.onClose
 * @param {*}             [props.capturedPayload]
 * @param {string|null}   [props.capturedAt]
 * @param {string}        [props.triggerLabel]
 * @param {Array<Object>} [props.graphNodes]
 * @param {number}        [props.workflowId]
 * @param {Object}        [props.graph]
 * @param {Function}      [props.onPersistBeforeTest]
 * @param {Function}      [props.onAddAgentChatModel]
 * @param {Function}      [props.onAddAgentMemory]
 * @param {Function}      [props.onAddAgentTool]
 * @param {Function}      [props.onAddAgentFallbackModel]
 * @param {Function}      [props.onAddAgentOutputParser]
 * @param {Function}      [props.onAddParserChatModel]
 * @param {Function}      [props.onSelectNode]
 * @param {Object}        [props.nodeOutputSamples]
 * @param {Function}      [props.onNodeTestResult]
 */
export default function ConfigPanel({
	node,
	nodeType,
	connections,
	onConnectionsChange,
	onChangeLabel,
	onChangeConfig,
	onDelete,
	onClose,
	capturedPayload,
	capturedAt,
	triggerLabel = 'Trigger',
	graphNodes = [],
	workflowId = 0,
	graph = { nodes: [], connections: [] },
	onPersistBeforeTest,
	onAddAgentChatModel,
	onAddAgentMemory,
	onAddAgentTool,
	onAddAgentFallbackModel,
	onAddAgentOutputParser,
	onAddParserChatModel,
	onSelectNode,
	nodeOutputSamples = {},
	onNodeTestResult,
}) {
	const [testing, setTesting] = useState(false);
	const [testResult, setTestResult] = useState(null);
	const [schemaPayload, setSchemaPayload] = useState(null);
	const nodeLabels = useMemo(() => {
		const labels = {};

		graphNodes.forEach((graphNode) => {
			labels[graphNode.id] = graphNode.label || graphNode.type;
		});

		return labels;
	}, [graphNodes]);

	const triggerNode = useMemo(
		() => getTriggerNode(graphNodes),
		[graphNodes]
	);

	const hasCapturedTrigger =
		capturedPayload !== null &&
		capturedPayload !== undefined &&
		typeof capturedPayload === 'object' &&
		Object.keys(capturedPayload).length > 0;

	useEffect(() => {
		if (hasCapturedTrigger || !triggerNode) {
			setSchemaPayload(null);
			return undefined;
		}

		const triggerType = triggerNode.type || '';
		const formId = String(triggerNode.config?.form_id || '');
		let cancelled = false;

		if (
			triggerType !== 'elementor_form_submitted_trigger' &&
			triggerType !== 'elementor_atomic_form_submitted_trigger'
		) {
			setSchemaPayload(null);
			return undefined;
		}

		fetchTriggerSampleSchema(triggerType, formId)
			.then((result) => {
				if (cancelled) {
					return;
				}

				if (result?.payload && typeof result.payload === 'object') {
					setSchemaPayload(result.payload);
					return;
				}

				setSchemaPayload(elementorTriggerStubPayload());
			})
			.catch(() => {
				if (!cancelled) {
					setSchemaPayload(elementorTriggerStubPayload());
				}
			});

		return () => {
			cancelled = true;
		};
	}, [
		hasCapturedTrigger,
		triggerNode?.id,
		triggerNode?.type,
		triggerNode?.config?.form_id,
	]);

	const effectiveTriggerPayload = hasCapturedTrigger
		? capturedPayload
		: schemaPayload;

	const variableSources = useMemo(
		() =>
			buildVariableSources({
				graphNodes,
				connections: graph.connections || [],
				currentNodeId: node?.id || null,
				triggerPayload: effectiveTriggerPayload,
				triggerLabel,
				nodeOutputSamples,
			}),
		[
			graphNodes,
			graph.connections,
			node?.id,
			effectiveTriggerPayload,
			triggerLabel,
			nodeOutputSamples,
		]
	);

	useEffect(() => {
		setTestResult(null);
	}, [node?.id, node?.type]);

	const handleTestNode = async () => {
		if (!workflowId || !node?.id) {
			return;
		}

		if (node.type === 'ai_agent_action') {
			const agentErrors = validateAgentConfig(
				node.config || {},
				graphNodes,
				node.id,
				graph.connections || []
			);

			if (agentErrors.length > 0) {
				setTestResult({
					success: false,
					error: agentErrors.map((entry) => entry.message).join(' '),
				});
				return;
			}
		}

		setTesting(true);
		setTestResult(null);

		try {
			if (onPersistBeforeTest) {
				await onPersistBeforeTest();
			}

			const result = await testWorkflowNode(workflowId, {
				node_id: node.id,
				graph,
			});

			setTestResult(result);
			if (
				result?.success &&
				result.output &&
				typeof onNodeTestResult === 'function'
			) {
				onNodeTestResult(node.id, result.output);
			}
		} catch (error) {
			setTestResult({
				success: false,
				error:
					error && error.message
						? error.message
						: __('Could not test this node.', 'dragwyb-agentflow'),
			});
		} finally {
			setTesting(false);
		}
	};

	if (!node) {
		return (
			<aside
				className="dragwyb-af-builder-config dragwyb-af-builder-config--empty"
				aria-label={__('Node settings', 'dragwyb-agentflow')}
			>
				<p>
					{__(
						'Select a node to edit its settings.',
						'dragwyb-agentflow'
					)}
				</p>
			</aside>
		);
	}

	return (
		<aside
			className={
				node.type === 'ai_agent_action'
					? 'dragwyb-af-builder-config dragwyb-af-builder-config--agent'
					: 'dragwyb-af-builder-config'
			}
			aria-label={__('Node settings', 'dragwyb-agentflow')}
		>
			<div className="dragwyb-af-builder-config__header">
				<h2>{nodeType ? nodeType.label : node.type}</h2>
				<Button
					className="dragwyb-af-builder-config__close"
					icon="no-alt"
					label={__('Close', 'dragwyb-agentflow')}
					onClick={onClose}
				/>
			</div>

			<TextControl
				label={__('Node label', 'dragwyb-agentflow')}
				value={node.label}
				onChange={onChangeLabel}
			/>

			{node.parent_agent_id && node.attachment_type === 'tool' && (
				<p className="dragwyb-af-builder-config__field-help">
					{__(
						'This tool is attached to your AI Agent. Remove it from the agent or delete it here.',
						'dragwyb-agentflow'
					)}
				</p>
			)}

			{node.attachment_type === 'chat_model' && (
				<p className="dragwyb-af-builder-config__field-help">
					{__(
						'Chat model linked to your agent. Add an API key and pick a model below.',
						'dragwyb-agentflow'
					)}
				</p>
			)}

			{node.attachment_type === 'fallback_chat_model' && (
				<p className="dragwyb-af-builder-config__field-help">
					{__(
						'Fallback chat model used when the primary model fails.',
						'dragwyb-agentflow'
					)}
				</p>
			)}

			{node.attachment_type === 'output_parser' && (
				<>
					<p className="dragwyb-af-builder-config__field-help">
						{__(
							'JSON structure for the AI Agent reply. Connect a Model* on the canvas for Auto-Fix.',
							'dragwyb-agentflow'
						)}
					</p>
					{onAddParserChatModel &&
						!graphNodes.some(
							(entry) =>
								entry.parent_agent_id === node.id &&
								entry.attachment_type === 'parser_chat_model'
						) && (
							<Button
								variant="secondary"
								onClick={() => onAddParserChatModel(node.id)}
							>
								{__('Connect model', 'dragwyb-agentflow')}
							</Button>
						)}
				</>
			)}

			{node.attachment_type === 'memory' && (
				<p className="dragwyb-af-builder-config__field-help">
					{__(
						'Simple memory keeps conversation context for this agent run.',
						'dragwyb-agentflow'
					)}
				</p>
			)}

			{node.type === 'condition_action' && (
				<p className="dragwyb-af-builder-config__field-help">
					{__(
						'Each condition has its own orange port on the right — drag each port to a different step (AI Agent, actions, etc.). Or pick targets under “Then run” for each condition below.',
						'dragwyb-agentflow'
					)}
				</p>
			)}

			{node.type === 'ai_agent_action' && (
				<AgentConfigPanel
					node={node}
					graphNodes={graphNodes}
					graphConnections={graph.connections || []}
					variableSources={variableSources}
					nodeLabels={nodeLabels}
					onChangeConfig={onChangeConfig}
					onAddChatModel={onAddAgentChatModel}
					onAddMemory={onAddAgentMemory}
					onAddTool={onAddAgentTool}
					onAddFallbackModel={onAddAgentFallbackModel}
					onAddOutputParser={onAddAgentOutputParser}
					onSelectNode={onSelectNode}
					onExecuteStep={handleTestNode}
					testing={testing}
				/>
			)}

			{node.category === 'trigger' &&
				node.type === 'chat_message_received_trigger' && (
					<p className="dragwyb-af-builder-config__field-help">
						{__(
							'Click Chat in the header to open the chat panel and send messages (same idea as n8n). Save the workflow first if you just added this trigger.',
							'dragwyb-agentflow'
						)}
					</p>
				)}

			{node.category === 'trigger' && (
				<CapturedResponse
					payload={capturedPayload}
					capturedAt={capturedAt}
					sourceLabel={node.label || triggerLabel}
				/>
			)}

			{!nodeType && (
				<p className="dragwyb-af-builder-config__warning">
					{__(
						'This node\u2019s type is not currently registered (the plugin or code that provided it may be inactive). Its saved configuration is preserved but cannot be edited here.',
						'dragwyb-agentflow'
					)}
				</p>
			)}

			{nodeType &&
				node.type !== 'ai_agent_action' &&
				node.attachment_type !== 'memory' &&
				Object.keys(nodeType.config_schema || {})
					.filter((fieldName) => {
						if (
							node.type === 'ai_agent_action' &&
							AGENT_SIDEBAR_HIDDEN_FIELDS.has(fieldName)
						) {
							return false;
						}

						if (
							node.attachment_type === 'chat_model' ||
							node.attachment_type === 'fallback_chat_model' ||
							node.attachment_type === 'parser_chat_model'
						) {
							return CHAT_MODEL_ATTACHMENT_FIELDS.has(fieldName);
						}

						return true;
					})
					.map((fieldName) => (
					<div
						key={`${node.id}-${fieldName}`}
						className="dragwyb-af-builder-config__field"
					>
						<ConfigField
							fieldName={fieldName}
							fieldSchema={nodeType.config_schema[fieldName]}
							value={node.config ? node.config[fieldName] : undefined}
							connections={connections}
							nodeTypeSlug={nodeType.slug}
							nodeTypeLabel={nodeType.label}
							nodeId={node.id}
							nodeCategory={node.category}
							nodeConfig={node.config || {}}
							variableSources={variableSources}
							nodeLabels={nodeLabels}
							graphNodes={graphNodes}
							onConnectionsChange={onConnectionsChange}
							onChange={(value) => onChangeConfig(fieldName, value)}
						/>
					</div>
				))}

			{testResult && (
				<NodeTestResult
					success={Boolean(testResult.success)}
					error={testResult.error || null}
					input={testResult.input || null}
					output={testResult.output || null}
				/>
			)}

			<div className="dragwyb-af-builder-config__actions">
				<Button
					variant="secondary"
					onClick={handleTestNode}
					isBusy={testing}
					disabled={testing || !workflowId}
					className="dragwyb-af-builder-config__test"
				>
					{__('Test node', 'dragwyb-agentflow')}
				</Button>

				<Button
					isDestructive
					variant="secondary"
					onClick={onDelete}
					className="dragwyb-af-builder-config__delete"
				>
					{__('Delete node', 'dragwyb-agentflow')}
				</Button>
			</div>
		</aside>
	);
}

function ConfigField({
	fieldName,
	fieldSchema,
	value,
	connections,
	nodeTypeSlug,
	nodeTypeLabel,
	nodeId,
	nodeCategory,
	nodeConfig,
	variableSources,
	nodeLabels,
	graphNodes = [],
	onConnectionsChange,
	onChange,
}) {
	if (fieldSchema.hidden) {
		return null;
	}

	if (!isFieldVisible(fieldSchema, nodeConfig)) {
		return null;
	}

	const label = fieldSchema.label || fieldName;
	const help = fieldSchema.help || '';
	const resolved = value === undefined ? fieldSchema.default : value;
	const connectionNodeSlug = resolveConnectionNodeSlug(
		nodeTypeSlug,
		nodeConfig
	);

	if (fieldSchema.type === 'select') {
		const rawOptions = fieldSchema.options || [];
		const options = rawOptions.map((option) => ({
			label: option.label || option.value,
			value: String(option.value ?? ''),
		}));
		const selectedValue =
			resolved === undefined || resolved === null
				? ''
				: String(resolved);
		const selectedOption = rawOptions.find(
			(option) => String(option.value ?? '') === selectedValue
		);
		const pageLinks = Array.isArray(selectedOption?.pages)
			? selectedOption.pages.filter((page) => page?.url)
			: selectedOption?.url
				? [
						{
							label: selectedOption.label || '',
							url: selectedOption.url,
						},
					]
				: [];

		return (
			<>
				<SelectControl
					label={label}
					help={help || undefined}
					value={selectedValue}
					options={options}
					onChange={onChange}
				/>
				{pageLinks.length > 0 && (
					<div className="dragwyb-af-builder-config__form-page-link">
						{pageLinks.length === 1 ? (
							<a
								href={pageLinks[0].url}
								target="_blank"
								rel="noopener noreferrer"
							>
								{__(
									'Open form page',
									'dragwyb-agentflow'
								)}
								{pageLinks[0].label
									? ` — ${pageLinks[0].label}`
									: ''}
							</a>
						) : (
							<>
								<span className="dragwyb-af-builder-config__form-page-link-label">
									{__('Form pages:', 'dragwyb-agentflow')}
								</span>
								<ul className="dragwyb-af-builder-config__form-page-link-list">
									{pageLinks.map((page) => (
										<li key={page.url}>
											<a
												href={page.url}
												target="_blank"
												rel="noopener noreferrer"
											>
												{page.label ||
													__(
														'Open form page',
														'dragwyb-agentflow'
													)}
											</a>
										</li>
									))}
								</ul>
							</>
						)}
					</div>
				)}
			</>
		);
	}

	if (fieldSchema.type === 'info') {
		const text =
			resolved === undefined || resolved === null || resolved === ''
				? String(fieldSchema.default || '')
				: String(resolved);
		return (
			<div className="dragwyb-af-field dragwyb-af-field--info">
				<strong>{label}</strong>
				<p style={{ marginTop: 4 }}>{text}</p>
			</div>
		);
	}

	if (fieldSchema.type === 'ai_credentials') {
		const providerField = fieldSchema.provider_field || 'provider';
		const provider =
			fieldSchema.provider ||
			nodeConfig[providerField] ||
			connectionNodeSlug ||
			'openai';

		return (
			<AiCredentialsField
				label={label}
				provider={String(provider)}
				onCredentialsChange={() => {
					/* model field refetches via shared status key bump */
				}}
			/>
		);
	}

	if (
		fieldSchema.type === 'dynamic_select' &&
		fieldSchema.options_source === 'ai_models'
	) {
		const providerField = fieldSchema.provider_field || 'provider';
		const provider =
			fieldSchema.provider ||
			nodeConfig[providerField] ||
			connectionNodeSlug ||
			'openai';

		return (
			<AiModelField
				label={label}
				value={
					resolved === undefined || resolved === null
						? ''
						: String(resolved)
				}
				defaultValue={
					fieldSchema.default === undefined || fieldSchema.default === null
						? ''
						: String(fieldSchema.default)
				}
				provider={String(provider)}
				nodeTypeSlug={connectionNodeSlug}
				onChange={onChange}
			/>
		);
	}

	if (fieldSchema.type === 'boolean') {
		return (
			<ToggleControl
				label={label}
				help={help || undefined}
				checked={Boolean(resolved)}
				onChange={onChange}
			/>
		);
	}

	if (fieldSchema.type === 'object') {
		return <JsonField label={label} value={resolved} onChange={onChange} />;
	}

	if (
		fieldSchema.type === 'array' &&
		nodeCategory === 'action' &&
		fieldSupportsVariables(fieldName, fieldSchema)
	) {
		const displayValue = Array.isArray(resolved)
			? resolved.join(', ')
			: resolved === undefined || resolved === null
				? ''
				: String(resolved);

		return (
			<TokenField
				key={`${nodeId}-${fieldName}`}
				label={label}
				value={displayValue}
				required={Boolean(fieldSchema.required)}
				variableSources={variableSources}
				nodeLabels={nodeLabels}
				onChange={onChange}
			/>
		);
	}

	if (fieldSchema.type === 'array') {
		return <JsonField label={label} value={resolved} onChange={onChange} />;
	}

	if (fieldSchema.type === 'key_value') {
		return (
			<KeyValueField
				label={label}
				value={resolved}
				help={help || undefined}
				addLabel={fieldSchema.button_label}
				variableSources={variableSources}
				nodeLabels={nodeLabels}
				onChange={onChange}
			/>
		);
	}

	if (fieldSchema.type === 'node_select') {
		return (
			<NodeSelectField
				label={label}
				value={resolved}
				help={help || undefined}
				nodeId={nodeId}
				graphNodes={graphNodes}
				onChange={onChange}
			/>
		);
	}

	if (fieldSchema.type === 'condition_routes') {
		return (
			<ConditionRoutesField
				label={label}
				value={resolved}
				help={help || undefined}
				nodeId={nodeId}
				graphNodes={graphNodes}
				variableSources={variableSources}
				nodeLabels={nodeLabels}
				onChange={onChange}
			/>
		);
	}

	if (fieldSchema.type === 'connection') {
		return (
			<ConnectionField
				label={label}
				value={resolved}
				required={Boolean(fieldSchema.required)}
				connections={connections || []}
				nodeTypeSlug={connectionNodeSlug}
				nodeTypeLabel={nodeTypeLabel}
				nodeId={nodeId}
				nodeConfig={nodeConfig}
				onConnectionsChange={onConnectionsChange}
				onChange={onChange}
			/>
		);
	}

	if (
		fieldSchema.type === 'string' &&
		nodeCategory === 'action' &&
		fieldSupportsVariables(fieldName, fieldSchema)
	) {
		return (
			<TokenField
				key={`${nodeId}-${fieldName}`}
				label={label}
				value={
					resolved === undefined || resolved === null
						? ''
						: String(resolved)
				}
				required={Boolean(fieldSchema.required)}
				variableSources={variableSources}
				nodeLabels={nodeLabels}
				onChange={onChange}
			/>
		);
	}

	if (fieldSchema.type === 'string' && fieldSchema.multiline) {
		return (
			<TextareaControl
				label={label}
				help={help || undefined}
				value={
					resolved === undefined || resolved === null
						? ''
						: String(resolved)
				}
				rows={Number(fieldSchema.rows) || 8}
				onChange={onChange}
			/>
		);
	}

	return (
		<TextControl
			label={label}
			value={
				resolved === undefined || resolved === null
					? ''
					: String(resolved)
			}
			required={Boolean(fieldSchema.required)}
			onChange={onChange}
		/>
	);
}

/**
 * In-builder site-wide AI API key field (no redirect to Connectors).
 *
 * @param {Object}   props
 * @param {string}   props.label
 * @param {string}   props.provider
 * @param {Function} [props.onCredentialsChange]
 */
function AiCredentialsField({ label, provider, onCredentialsChange }) {
	const [configured, setConfigured] = useState(false);
	const [loading, setLoading] = useState(true);
	const [replacing, setReplacing] = useState(false);
	const [apiKey, setApiKey] = useState('');
	const [saving, setSaving] = useState(false);
	const [clearing, setClearing] = useState(false);
	const [error, setError] = useState('');
	const [notice, setNotice] = useState('');

	useEffect(() => {
		if (!provider) {
			setConfigured(false);
			setLoading(false);
			return undefined;
		}

		let cancelled = false;
		setLoading(true);
		setError('');

		fetchAiProviderStatus()
			.then((result) => {
				if (cancelled) {
					return;
				}
				const providers = result?.providers || {};
				const providerId = String(provider).toLowerCase();
				const mapped =
					providers[providerId] ??
					providers[
						providerId === 'claude'
							? 'anthropic'
							: providerId === 'gemini'
								? 'google'
								: providerId
					];
				setConfigured(Boolean(mapped));
			})
			.catch(() => {
				if (!cancelled) {
					setConfigured(false);
				}
			})
			.finally(() => {
				if (!cancelled) {
					setLoading(false);
				}
			});

		return () => {
			cancelled = true;
		};
	}, [provider]);

	const notifyModels = () => {
		if (typeof window !== 'undefined') {
			window.dispatchEvent(
				new CustomEvent('dragwyb-af-ai-credentials-changed', {
					detail: { provider },
				})
			);
		}
		if (typeof onCredentialsChange === 'function') {
			onCredentialsChange();
		}
	};

	const handleSave = async () => {
		if (!apiKey.trim()) {
			setError(__('Enter an API key.', 'dragwyb-agentflow'));
			return;
		}

		setSaving(true);
		setError('');
		setNotice('');

		try {
			await saveAiProviderCredentials(provider, apiKey.trim());
			setApiKey('');
			setReplacing(false);
			setConfigured(true);
			setNotice(__('API key saved.', 'dragwyb-agentflow'));
			notifyModels();
		} catch (err) {
			setError(
				err && err.message
					? err.message
					: __(
							'Could not save API key. Check that the key is valid.',
							'dragwyb-agentflow'
						)
			);
		} finally {
			setSaving(false);
		}
	};

	const handleClear = async () => {
		setClearing(true);
		setError('');
		setNotice('');

		try {
			await clearAiProviderCredentials(provider);
			setConfigured(false);
			setReplacing(true);
			setNotice(__('API key removed.', 'dragwyb-agentflow'));
			notifyModels();
		} catch (err) {
			setError(
				err && err.message
					? err.message
					: __('Could not remove API key.', 'dragwyb-agentflow')
			);
		} finally {
			setClearing(false);
		}
	};

	if (loading) {
		return (
			<div className="dragwyb-af-field dragwyb-af-field--ai-credentials">
				<p className="dragwyb-af-builder-config__field-help">
					{__('Checking API key…', 'dragwyb-agentflow')}
				</p>
			</div>
		);
	}

	if (configured && !replacing) {
		return (
			<div className="dragwyb-af-field dragwyb-af-field--ai-credentials">
				<strong>{label}</strong>
				<p className="dragwyb-af-builder-config__connection-notice dragwyb-af-builder-config__connection-notice--success">
					{__('API key saved for this site.', 'dragwyb-agentflow')}
				</p>
				{notice ? (
					<p className="dragwyb-af-builder-config__connection-notice dragwyb-af-builder-config__connection-notice--success">
						{notice}
					</p>
				) : null}
				{error ? (
					<p className="dragwyb-af-builder-config__field-error" role="alert">
						{error}
					</p>
				) : null}
				<div className="dragwyb-af-builder-config__connection-actions">
					<Button
						variant="secondary"
						onClick={() => {
							setReplacing(true);
							setApiKey('');
							setError('');
							setNotice('');
						}}
					>
						{__('Replace API key', 'dragwyb-agentflow')}
					</Button>
					<Button
						variant="link"
						isDestructive
						onClick={handleClear}
						disabled={clearing}
					>
						{clearing
							? __('Removing…', 'dragwyb-agentflow')
							: __('Remove', 'dragwyb-agentflow')}
					</Button>
				</div>
			</div>
		);
	}

	return (
		<div className="dragwyb-af-field dragwyb-af-field--ai-credentials">
			<TextControl
				label={label}
				type="password"
				value={apiKey}
				onChange={setApiKey}
				autoComplete="off"
				help={__(
					'Saved for this WordPress site and used by all workflows.',
					'dragwyb-agentflow'
				)}
			/>
			{error ? (
				<p className="dragwyb-af-builder-config__field-error" role="alert">
					{error}
				</p>
			) : null}
			{notice ? (
				<p className="dragwyb-af-builder-config__connection-notice dragwyb-af-builder-config__connection-notice--success">
					{notice}
				</p>
			) : null}
			<div className="dragwyb-af-builder-config__connection-actions">
				<Button isPrimary onClick={handleSave} disabled={saving}>
					{saving
						? __('Saving…', 'dragwyb-agentflow')
						: __('Save API key', 'dragwyb-agentflow')}
				</Button>
				{configured || replacing ? (
					<Button
						variant="link"
						onClick={() => {
							setReplacing(false);
							setApiKey('');
							setError('');
						}}
						disabled={saving}
					>
						{__('Cancel', 'dragwyb-agentflow')}
					</Button>
				) : null}
			</div>
		</div>
	);
}

/**
 * Model picker loaded from the WordPress AI Client provider registry.
 *
 * @param {Object}   props
 * @param {string}   props.label
 * @param {string}   props.value
 * @param {string}   props.defaultValue
 * @param {string}   props.provider
 * @param {string}   props.nodeTypeSlug
 * @param {Function} props.onChange
 */
function AiModelField({
	label,
	value,
	defaultValue,
	provider,
	nodeTypeSlug,
	onChange,
}) {
	const [options, setOptions] = useState([]);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState('');
	const [reloadToken, setReloadToken] = useState(0);

	useEffect(() => {
		const onCredentialsChanged = () => {
			setReloadToken((token) => token + 1);
		};

		window.addEventListener(
			'dragwyb-af-ai-credentials-changed',
			onCredentialsChanged
		);
		return () => {
			window.removeEventListener(
				'dragwyb-af-ai-credentials-changed',
				onCredentialsChanged
			);
		};
	}, []);

	useEffect(() => {
		if (!provider) {
			setOptions([]);
			setError('');
			setLoading(false);
			return undefined;
		}

		let cancelled = false;

		setLoading(true);
		setError('');

		fetchAiProviderModels(provider, nodeTypeSlug)
			.then((result) => {
				if (cancelled) {
					return;
				}

				setOptions(Array.isArray(result.options) ? result.options : []);
				setError(result.error || '');
			})
			.catch((err) => {
				if (cancelled) {
					return;
				}

				setOptions([]);
				setError(
					err && err.message
						? err.message
						: __('Could not load models.', 'dragwyb-agentflow')
				);
			})
			.finally(() => {
				if (!cancelled) {
					setLoading(false);
				}
			});

		return () => {
			cancelled = true;
		};
	}, [provider, nodeTypeSlug, reloadToken]);

	if (error && options.length === 0) {
		return (
			<TextControl
				label={label}
				value={value || defaultValue}
				onChange={onChange}
				help={error}
			/>
		);
	}

	if (loading) {
		return (
			<SelectControl
				label={label}
				value={value || defaultValue}
				options={[
					{
						value: value || defaultValue || '',
						label: __('Loading models…', 'dragwyb-agentflow'),
					},
				]}
				disabled
				onChange={onChange}
			/>
		);
	}

	if (options.length === 0) {
		return (
			<TextControl
				label={label}
				value={value || defaultValue}
				onChange={onChange}
				help={
					error ||
					__(
						'No models listed. Enter a model id manually or save an API key above.',
						'dragwyb-agentflow'
					)
				}
			/>
		);
	}

	const selectOptions = options.map((option) => ({
		value: option.value,
		label: option.label,
	}));

	const currentValue = value || defaultValue;
	const hasCurrent = selectOptions.some(
		(option) => option.value === currentValue
	);

	if (currentValue && !hasCurrent) {
		selectOptions.unshift({
			value: currentValue,
			label: currentValue,
		});
	}

	return (
		<SelectControl
			label={label}
			value={currentValue}
			options={selectOptions}
			onChange={onChange}
			help={error || undefined}
		/>
	);
}

/**
 * Connection picker with inline "add API key" form so authors configure
 * Telegram / WhatsApp / Google (etc.) without leaving the builder.
 *
 * @param {Object}        props
 * @param {string}        props.label
 * @param {*}             props.value
 * @param {boolean}       props.required
 * @param {Array<Object>} props.connections
 * @param {string}        props.nodeTypeSlug
 * @param {string}        props.nodeTypeLabel
 * @param {Function}      props.onConnectionsChange
 * @param {Function}      props.onChange
 */
function ConnectionField({
	label,
	value,
	required,
	connections,
	nodeTypeSlug,
	nodeTypeLabel,
	nodeId,
	nodeConfig = {},
	onConnectionsChange,
	onChange,
}) {
	const bootstrap = getBootstrap();
	const integrationSettings = isGoogleSheetsAction(nodeTypeSlug)
		? GOOGLE_SHEETS_OAUTH_SETTINGS
		: INTEGRATION_CONNECTION_SETTINGS[nodeTypeSlug] ||
			AGENT_PROVIDER_CONNECTION_SETTINGS[nodeConfig.provider] ||
			{};
	const defaultAuthType = integrationSettings.authType || 'api_key';
	const isGoogleOAuth = Boolean(integrationSettings.oauthConnection);

	const selectedId = Number(value || 0);
	const needsConnection = required && selectedId <= 0;

	const matchingConnections = useMemo(
		() =>
			filterMatchingConnections(
				connections,
				nodeTypeSlug,
				selectedId,
				nodeConfig
			),
		[connections, nodeTypeSlug, selectedId, nodeConfig]
	);

	const selectedConnection = matchingConnections.find(
		(connection) => connection.id === selectedId
	);

	const [showAddForm, setShowAddForm] = useState(
		isGoogleOAuth ? selectedId <= 0 : needsConnection && matchingConnections.length === 0
	);
	const [connectionLabel, setConnectionLabel] = useState(
		nodeTypeLabel
			? `${nodeTypeLabel}`
			: __('New connection', 'dragwyb-agentflow')
	);
	const [authType, setAuthType] = useState(defaultAuthType);
	const [secret, setSecret] = useState('');
	const [clientId, setClientId] = useState('');
	const [clientSecret, setClientSecret] = useState('');
	const [saving, setSaving] = useState(false);
	const [connecting, setConnecting] = useState(false);
	const [error, setError] = useState('');
	const [oauthNotice, setOauthNotice] = useState('');

	useEffect(() => {
		const params = new URLSearchParams(window.location.search);
		const notice = params.get('dragwyb_af_notice') || '';

		if ('oauth_connected' === notice) {
			setOauthNotice(
				__('Google account connected successfully.', 'dragwyb-agentflow')
			);
		} else if ('error' === notice && params.get('dragwyb_af_error')) {
			setOauthNotice(String(params.get('dragwyb_af_error')));
		} else {
			setOauthNotice('');
		}
	}, [selectedId]);

	useEffect(() => {
		setConnectionLabel(
			nodeTypeLabel
				? `${nodeTypeLabel}`
				: __('New connection', 'dragwyb-agentflow')
		);
		setAuthType(defaultAuthType);
		setSecret('');
		setClientId('');
		setClientSecret('');
		setError('');

		if (selectedId > 0) {
			setShowAddForm(false);
			return;
		}

		setShowAddForm(
			isGoogleOAuth ? true : needsConnection && matchingConnections.length === 0
		);
	}, [
		nodeTypeSlug,
		nodeTypeLabel,
		defaultAuthType,
		needsConnection,
		selectedId,
		matchingConnections.length,
		isGoogleOAuth,
	]);

	// Auto-select when exactly one saved connection matches this node type.
	useEffect(() => {
		if (!required || selectedId > 0 || matchingConnections.length !== 1) {
			return;
		}

		onChange(matchingConnections[0].id);
	}, [required, selectedId, matchingConnections, onChange]);

	const options = [
		{ value: '0', label: __('None', 'dragwyb-agentflow') },
		...matchingConnections.map((connection) => ({
			value: String(connection.id),
			label: `${connection.label} (${connection.auth_type_label})`,
		})),
	];

	const secretFieldName =
		integrationSettings.secretFieldName ||
		(authType === 'bearer_token' ? 'token' : 'api_key');
	const secretFieldLabel =
		integrationSettings.secretLabel ||
		(authType === 'bearer_token'
			? __('Bearer token / access token', 'dragwyb-agentflow')
			: __('API key', 'dragwyb-agentflow'));

	const handleSaveConnection = async () => {
		const trimmedLabel = connectionLabel.trim();

		if (!trimmedLabel) {
			setError(__('Enter a name for this connection.', 'dragwyb-agentflow'));
			return;
		}

		if (isGoogleOAuth) {
			const trimmedClientId = clientId.trim();
			const trimmedClientSecret = clientSecret.trim();

			if (!trimmedClientId || !trimmedClientSecret) {
				setError(
					__(
						'Enter both Client ID and Client Secret.',
						'dragwyb-agentflow'
					)
				);
				return;
			}

			setSaving(true);
			setError('');

			try {
				const created = await createConnection({
					label: trimmedLabel,
					integration_slug: 'google_sheets',
					auth_type: 'oauth2',
					credentials: {
						client_id: trimmedClientId,
						client_secret: trimmedClientSecret,
					},
				});

				const nextList = Array.isArray(connections)
					? [created, ...connections]
					: [created];

				if (typeof onConnectionsChange === 'function') {
					onConnectionsChange(nextList);
				}

				onChange(created.id);
				setClientSecret('');
				setShowAddForm(false);
			} catch (err) {
				setError(
					err && err.message
						? err.message
						: __(
							'Could not save the connection. Check your permissions and try again.',
							'dragwyb-agentflow'
						)
				);
			} finally {
				setSaving(false);
			}

			return;
		}

		const trimmedSecret = secret.trim();

		if (!trimmedSecret) {
			setError(__('Enter your API key or token.', 'dragwyb-agentflow'));
			return;
		}

		setSaving(true);
		setError('');

		try {
			const created = await createConnection({
				label: trimmedLabel,
				integration_slug: nodeTypeSlug || 'custom',
				auth_type: authType,
				credentials: {
					[secretFieldName]: trimmedSecret,
				},
			});

			const nextList = Array.isArray(connections)
				? [created, ...connections]
				: [created];

			if (typeof onConnectionsChange === 'function') {
				onConnectionsChange(nextList);
			}

			onChange(created.id);
			setSecret('');
			setShowAddForm(false);
		} catch (err) {
			setError(
				err && err.message
					? err.message
					: __(
						'Could not save the connection. Check your permissions and try again.',
						'dragwyb-agentflow'
					)
			);
		} finally {
			setSaving(false);
		}
	};

	const buildOAuthReturnUrl = () => {
		const url = new URL(window.location.href);
		url.searchParams.delete('dragwyb_af_notice');
		url.searchParams.delete('dragwyb_af_error');

		if (selectedId > 0) {
			url.searchParams.set('dragwyb_af_connection', String(selectedId));
		}

		if (nodeId) {
			url.searchParams.set('dragwyb_af_node', nodeId);
		}

		return url.toString();
	};

	const handleConnectGoogle = async (connectionId = selectedId) => {
		if (connectionId <= 0) {
			setError(
				__(
					'Save your Client ID and Client Secret first.',
					'dragwyb-agentflow'
				)
			);
			return;
		}

		setConnecting(true);
		setError('');

		try {
			const result = await fetchGoogleOAuthAuthorizeUrl(connectionId, {
				returnUrl: buildOAuthReturnUrl(),
				nodeId: nodeId || '',
			});

			if (result && result.authorize_url) {
				window.location.assign(result.authorize_url);
				return;
			}

			setError(
				__(
					'Could not start Google authorization.',
					'dragwyb-agentflow'
				)
			);
		} catch (err) {
			setError(
				err && err.message
					? err.message
					: __(
						'Could not start Google authorization.',
						'dragwyb-agentflow'
					)
			);
		} finally {
			setConnecting(false);
		}
	};

	return (
		<div className="dragwyb-af-builder-config__connection">
			<SelectControl
				label={label}
				value={String(selectedId)}
				options={options}
				onChange={(nextValue) => {
					const id = Number(nextValue);
					onChange(id);

					if (id > 0) {
						setShowAddForm(false);
					}
				}}
				help={
					needsConnection
						? isGoogleOAuth
							? __(
								'Required — add your Google OAuth credentials below, then connect your Google account.',
								'dragwyb-agentflow'
							)
							: __(
								'Required — add an API key below or pick an existing connection.',
								'dragwyb-agentflow'
							)
						: undefined
				}
			/>

			{oauthNotice && (
				<p
					className={
						oauthNotice.includes('successfully')
							? 'dragwyb-af-builder-config__connection-notice dragwyb-af-builder-config__connection-notice--success'
							: 'dragwyb-af-builder-config__field-error'
					}
					role="status"
				>
					{oauthNotice}
				</p>
			)}

			{selectedId > 0 &&
				selectedConnection &&
				isGoogleOAuth &&
				!selectedConnection.oauth_connected && (
					<div className="dragwyb-af-builder-config__connection-form">
						<p className="dragwyb-af-builder-config__connection-form-help">
							{__(
								'Credentials saved. Connect your Google account to finish setup.',
								'dragwyb-agentflow'
							)}
						</p>
						<Button
							isPrimary
							onClick={() => handleConnectGoogle(selectedId)}
							disabled={connecting}
						>
							{connecting
								? __('Connecting…', 'dragwyb-agentflow')
								: __('Connect with Google', 'dragwyb-agentflow')}
						</Button>
					</div>
				)}

			{selectedId > 0 &&
				selectedConnection &&
				isGoogleOAuth &&
				selectedConnection.oauth_connected && (
					<p className="dragwyb-af-builder-config__connection-notice dragwyb-af-builder-config__connection-notice--success">
						{__('Google account connected.', 'dragwyb-agentflow')}
					</p>
				)}

			{!showAddForm && selectedId <= 0 && !isGoogleOAuth && (
				<Button
					variant="secondary"
					className="dragwyb-af-builder-config__add-connection"
					onClick={() => {
						setSecret('');
						setError('');
						setAuthType(defaultAuthType);
						setShowAddForm(true);
					}}
				>
					{__('+ Add API key here', 'dragwyb-agentflow')}
				</Button>
			)}

			{!showAddForm && selectedId <= 0 && isGoogleOAuth && (
				<Button
					variant="secondary"
					className="dragwyb-af-builder-config__add-connection"
					onClick={() => {
						setClientId('');
						setClientSecret('');
						setError('');
						setShowAddForm(true);
					}}
				>
					{__('+ Add Google OAuth connection', 'dragwyb-agentflow')}
				</Button>
			)}

			{!showAddForm && selectedId > 0 && !isGoogleOAuth && (
				<Button
					variant="link"
					className="dragwyb-af-builder-config__add-connection"
					onClick={() => {
						setSecret('');
						setError('');
						setAuthType(defaultAuthType);
						setShowAddForm(true);
					}}
				>
					{__('Use a different API key', 'dragwyb-agentflow')}
				</Button>
			)}

			{!showAddForm && selectedId > 0 && isGoogleOAuth && (
				<Button
					variant="link"
					className="dragwyb-af-builder-config__add-connection"
					onClick={() => {
						onChange(0);
						setClientId('');
						setClientSecret('');
						setError('');
						setShowAddForm(true);
					}}
				>
					{__('Use different Google credentials', 'dragwyb-agentflow')}
				</Button>
			)}

			{showAddForm && isGoogleOAuth && (
				<div className="dragwyb-af-builder-config__connection-form">
					<p className="dragwyb-af-builder-config__connection-form-title">
						{__('Google OAuth connection', 'dragwyb-agentflow')}
					</p>
					<p className="dragwyb-af-builder-config__connection-form-help">
						<a
							href={bootstrap.googleCredentialsUrl}
							target="_blank"
							rel="noopener noreferrer"
						>
							{__(
								'Create credentials in Google Cloud Console',
								'dragwyb-agentflow'
							)}
						</a>
						{' · '}
						{__(
							'Enable Google Sheets API and Google Drive API.',
							'dragwyb-agentflow'
						)}
					</p>
					<TextControl
						label={__('Connection name', 'dragwyb-agentflow')}
						value={connectionLabel}
						onChange={setConnectionLabel}
					/>
					<TextControl
						label={__('Client ID', 'dragwyb-agentflow')}
						value={clientId}
						onChange={setClientId}
						autoComplete="off"
					/>
					<TextControl
						label={__('Client Secret', 'dragwyb-agentflow')}
						type="password"
						value={clientSecret}
						onChange={setClientSecret}
						autoComplete="off"
						help={__(
							'Saved encrypted. You will not see it again after saving.',
							'dragwyb-agentflow'
						)}
					/>
					<TextControl
						label={__('Callback URL', 'dragwyb-agentflow')}
						value={bootstrap.googleOAuthCallbackUrl || ''}
						readOnly
						help={__(
							'Add this exact URL as an Authorized redirect URI in your Google OAuth client.',
							'dragwyb-agentflow'
						)}
						onFocus={(event) => event.target.select()}
					/>
					{error && (
						<p className="dragwyb-af-builder-config__field-error" role="alert">
							{error}
						</p>
					)}
					<div className="dragwyb-af-builder-config__connection-form-actions">
						<Button
							variant="secondary"
							onClick={handleSaveConnection}
							disabled={saving || connecting}
						>
							{saving
								? __('Saving…', 'dragwyb-agentflow')
								: __('Save credentials', 'dragwyb-agentflow')}
						</Button>
						<Button
							isPrimary
							onClick={async () => {
								if (selectedId > 0) {
									await handleConnectGoogle(selectedId);
									return;
								}

								const trimmedLabel = connectionLabel.trim();
								const trimmedClientId = clientId.trim();
								const trimmedClientSecret = clientSecret.trim();

								if (
									!trimmedLabel ||
									!trimmedClientId ||
									!trimmedClientSecret
								) {
									setError(
										__(
											'Enter connection name, Client ID, and Client Secret first.',
											'dragwyb-agentflow'
										)
									);
									return;
								}

								setSaving(true);
								setError('');

								try {
									const created = await createConnection({
										label: trimmedLabel,
										integration_slug: 'google_sheets',
										auth_type: 'oauth2',
										credentials: {
											client_id: trimmedClientId,
											client_secret: trimmedClientSecret,
										},
									});

									const nextList = Array.isArray(connections)
										? [created, ...connections]
										: [created];

									if (typeof onConnectionsChange === 'function') {
										onConnectionsChange(nextList);
									}

									onChange(created.id);
									setClientSecret('');
									setShowAddForm(false);
									await handleConnectGoogle(created.id);
								} catch (err) {
									setError(
										err && err.message
											? err.message
											: __(
												'Could not save the connection.',
												'dragwyb-agentflow'
											)
									);
								} finally {
									setSaving(false);
								}
							}}
							disabled={saving || connecting}
						>
							{connecting
								? __('Connecting…', 'dragwyb-agentflow')
								: __('Connect with Google', 'dragwyb-agentflow')}
						</Button>
						{!needsConnection && (
							<Button
								variant="tertiary"
								onClick={() => {
									setShowAddForm(false);
									setError('');
									setClientSecret('');
								}}
								disabled={saving || connecting}
							>
								{__('Cancel', 'dragwyb-agentflow')}
							</Button>
						)}
					</div>
				</div>
			)}

			{showAddForm && !isGoogleOAuth && (
				<div className="dragwyb-af-builder-config__connection-form">
					<p className="dragwyb-af-builder-config__connection-form-title">
						{nodeTypeLabel
							? sprintf(
								/* translators: %s: integration label */
								__(
									'Credentials for %s',
									'dragwyb-agentflow'
								),
								nodeTypeLabel
							)
							: __(
								'Add credentials for this node',
								'dragwyb-agentflow'
							)}
					</p>
					<TextControl
						label={__('Connection name', 'dragwyb-agentflow')}
						value={connectionLabel}
						onChange={setConnectionLabel}
					/>
					{!integrationSettings.hideAuthTypeSelect && (
						<SelectControl
							label={__('Auth type', 'dragwyb-agentflow')}
							value={authType}
							options={[
								{
									value: 'api_key',
									label: __('API Key', 'dragwyb-agentflow'),
								},
								{
									value: 'bearer_token',
									label: __('Bearer Token', 'dragwyb-agentflow'),
								},
							]}
							onChange={setAuthType}
						/>
					)}
					<TextControl
						label={secretFieldLabel}
						type="password"
						value={secret}
						autoComplete="off"
						onChange={setSecret}
						help={__(
							'Saved encrypted. You will not see it again after saving.',
							'dragwyb-agentflow'
						)}
					/>
					{error && (
						<p className="dragwyb-af-builder-config__field-error" role="alert">
							{error}
						</p>
					)}
					<div className="dragwyb-af-builder-config__connection-form-actions">
						<Button
							isPrimary
							onClick={handleSaveConnection}
							disabled={saving}
						>
							{saving
								? __('Saving…', 'dragwyb-agentflow')
								: __('Save & use connection', 'dragwyb-agentflow')}
						</Button>
						{!needsConnection && (
							<Button
								variant="tertiary"
								onClick={() => {
									setShowAddForm(false);
									setError('');
									setSecret('');
								}}
								disabled={saving}
							>
								{__('Cancel', 'dragwyb-agentflow')}
							</Button>
						)}
					</div>
				</div>
			)}
		</div>
	);
}

/**
 * Evaluates a field's optional `show_when` conditions against the current
 * node config. Conditions are ANDed together; each is `{ field, equals }`.
 * Boolean `equals` compares truthiness so a toggle that is on/off matches
 * regardless of how the value is stored.
 *
 * @param {Object} fieldSchema
 * @param {Object} nodeConfig
 * @return {boolean}
 */
function isFieldVisible(fieldSchema, nodeConfig = {}) {
	const conditions = fieldSchema.show_when;

	if (!conditions) {
		return true;
	}

	const list = Array.isArray(conditions) ? conditions : [conditions];

	return list.every((condition) => {
		if (!condition || !('equals' in condition)) {
			return true;
		}

		const actual = nodeConfig[condition.field];

		if (typeof condition.equals === 'boolean') {
			return Boolean(actual) === condition.equals;
		}

		return String(actual ?? '') === String(condition.equals);
	});
}

/**
 * Dropdown of canvas nodes for branch targets.
 */
function NodeSelectField({ label, value, help, nodeId, graphNodes, onChange }) {
	const connectable = getConnectableCanvasNodes(graphNodes, nodeId);
	const options = [
		{ label: __('— None —', 'dragwyb-agentflow'), value: '' },
		...connectable.map((graphNode) => ({
			label: `${graphNode.label || graphNode.type}${
				graphNode.type === 'ai_agent_action' ? ' (AI Agent)' : ''
			}`,
			value: graphNode.id,
		})),
	];

	return (
		<SelectControl
			label={label}
			help={help || undefined}
			value={value ? String(value) : ''}
			options={options}
			onChange={onChange}
		/>
	);
}

/**
 * Multi-condition editor for Condition nodes.
 */
function ConditionRoutesField({
	label,
	value,
	help,
	nodeId,
	graphNodes,
	variableSources,
	nodeLabels,
	onChange,
}) {
	const rows = Array.isArray(value) ? value : [];

	const updateRow = (index, key, nextValue) => {
		onChange(
			rows.map((row, rowIndex) =>
				rowIndex === index ? { ...row, [key]: nextValue } : row
			)
		);
	};

	const addRow = () => {
		onChange([...rows, createEmptyConditionRow()]);
	};

	const removeRow = (index) => {
		onChange(rows.filter((_, rowIndex) => rowIndex !== index));
	};

	return (
		<div className="dragwyb-af-builder-config__condition-routes">
			<span className="dragwyb-af-builder-config__key-value-label">{label}</span>
			{help && (
				<p className="dragwyb-af-builder-config__field-help">{help}</p>
			)}
			{rows.map((row, index) => (
				<div
					key={row.id || `condition-${index}`}
					className="dragwyb-af-builder-config__condition-route"
				>
					<TextControl
						label={__('Label', 'dragwyb-agentflow')}
						value={row.label || ''}
						onChange={(nextValue) =>
							updateRow(index, 'label', nextValue)
						}
					/>
					<TokenField
						label={__('Value to check', 'dragwyb-agentflow')}
						value={row.field || ''}
						variableSources={variableSources}
						nodeLabels={nodeLabels}
						onChange={(nextValue) =>
							updateRow(index, 'field', nextValue)
						}
					/>
					<SelectControl
						label={__('Comparison', 'dragwyb-agentflow')}
						value={row.operator || 'equals'}
						options={getConditionOperatorSelectOptions()}
						onChange={(nextValue) => {
							if (!nextValue) {
								return;
							}

							updateRow(index, 'operator', nextValue);
						}}
					/>
					{conditionOperatorNeedsValue(row.operator || 'equals') && (
						<TextControl
							label={__('Compare to', 'dragwyb-agentflow')}
							value={row.value || ''}
							onChange={(nextValue) =>
								updateRow(index, 'value', nextValue)
							}
						/>
					)}
					<NodeSelectField
						label={__('Then run', 'dragwyb-agentflow')}
						value={row.node_id || ''}
						nodeId={nodeId}
						graphNodes={graphNodes}
						onChange={(nextValue) =>
							updateRow(index, 'node_id', nextValue)
						}
					/>
					<Button
						isDestructive
						variant="tertiary"
						onClick={() => removeRow(index)}
					>
						{__('Remove condition', 'dragwyb-agentflow')}
					</Button>
				</div>
			))}
			<Button variant="secondary" onClick={addRow}>
				{__('Add condition', 'dragwyb-agentflow')}
			</Button>
		</div>
	);
}

/**
 * Repeatable Name/Value editor (n8n "Using Fields Below" style). Stores an
 * array of `{ name, value }` objects.
 *
 * @param {Object}   props
 * @param {string}   props.label
 * @param {*}        props.value
 * @param {string}   [props.help]
 * @param {string}   [props.addLabel]
 * @param {Array}    [props.variableSources]
 * @param {Object}   [props.nodeLabels]
 * @param {Function} props.onChange
 */
function KeyValueField({
	label,
	value,
	help,
	addLabel,
	variableSources = [],
	nodeLabels = {},
	onChange,
}) {
	const rows = Array.isArray(value) ? value : [];

	const updateRow = (index, key, nextValue) => {
		onChange(
			rows.map((row, i) =>
				i === index ? { ...row, [key]: nextValue } : row
			)
		);
	};

	const addRow = () => {
		onChange([...rows, { name: '', value: '' }]);
	};

	const removeRow = (index) => {
		onChange(rows.filter((_, i) => i !== index));
	};

	return (
		<div className="dragwyb-af-builder-config__key-value">
			<span className="dragwyb-af-builder-config__key-value-label">{label}</span>
			{help && (
				<p className="dragwyb-af-builder-config__field-help">{help}</p>
			)}
			{rows.map((row, index) => (
				<div
					// eslint-disable-next-line react/no-array-index-key
					key={index}
					className="dragwyb-af-builder-config__key-value-row"
				>
					<TokenField
						label={__('Name', 'dragwyb-agentflow')}
						value={row.name || ''}
						variableSources={variableSources}
						nodeLabels={nodeLabels}
						onChange={(nextValue) =>
							updateRow(index, 'name', nextValue)
						}
					/>
					<TokenField
						label={__('Value', 'dragwyb-agentflow')}
						value={row.value || ''}
						variableSources={variableSources}
						nodeLabels={nodeLabels}
						onChange={(nextValue) =>
							updateRow(index, 'value', nextValue)
						}
					/>
					<Button
						isDestructive
						variant="tertiary"
						className="dragwyb-af-builder-config__key-value-remove"
						onClick={() => removeRow(index)}
					>
						{__('Remove', 'dragwyb-agentflow')}
					</Button>
				</div>
			))}
			<Button
				variant="secondary"
				className="dragwyb-af-builder-config__key-value-add"
				onClick={addRow}
			>
				{addLabel || __('Add Field', 'dragwyb-agentflow')}
			</Button>
		</div>
	);
}

/**
 * @param {Object}   props
 * @param {string}   props.label
 * @param {*}        props.value
 * @param {Function} props.onChange
 */
function JsonField({ label, value, onChange }) {
	const [text, setText] = useState(() =>
		JSON.stringify(value ?? {}, null, 2)
	);
	const [error, setError] = useState('');

	useEffect(() => {
		setText(JSON.stringify(value ?? {}, null, 2));
		setError('');
	}, [value]);

	const handleChange = (nextText) => {
		setText(nextText);

		try {
			const parsed = JSON.parse(nextText);
			setError('');
			onChange(parsed);
		} catch (parseError) {
			setError(
				__(
					'Invalid JSON — changes here are not saved until this is fixed.',
					'dragwyb-agentflow'
				)
			);
		}
	};

	return (
		<div className="dragwyb-af-builder-config__json-field">
			<TextareaControl
				label={label}
				value={text}
				onChange={handleChange}
				rows={4}
			/>
			{error && (
				<p className="dragwyb-af-builder-config__field-error">{error}</p>
			)}
		</div>
	);
}
