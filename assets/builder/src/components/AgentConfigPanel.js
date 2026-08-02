import { useMemo, useState } from '@wordpress/element';
import {
	Button,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import TokenField from './TokenField';
import { getNodeMeta } from '../nodeMeta';
import {
	AGENT_OPTION_CATALOG,
	AI_AGENT_VERSION,
	ON_ERROR_CONTINUE,
	ON_ERROR_ERROR_OUTPUT,
	ON_ERROR_STOP,
	PROMPT_SOURCE_CHAT_TRIGGER,
	PROMPT_SOURCE_DEFINE,
	agentHasConnectedChatTrigger,
	dismissAgentTutorial,
	isAgentTutorialDismissed,
	normalizeAgentConfig,
	resolveAgentAttachments,
	validateAgentConfig,
} from '../utils/agentConfig';
import {
	CHAT_MODEL_APP_IDS,
	isChatModelAttachment,
} from '../utils/agentAttachments';

const TUTORIAL_URL = 'https://docs.n8n.io/advanced-ai/intro-tutorial/';

/**
 * n8n-style Parameters / Settings panel for the AI Agent node.
 */
export default function AgentConfigPanel({
	node,
	graphNodes = [],
	graphConnections = [],
	variableSources = [],
	nodeLabels = {},
	onChangeConfig,
	onAddChatModel,
	onAddMemory,
	onAddTool,
	onAddFallbackModel,
	onAddOutputParser,
	onSelectNode,
	onExecuteStep,
	testing = false,
}) {
	const [activeTab, setActiveTab] = useState('parameters');
	const [bannerDismissed, setBannerDismissed] = useState(
		isAgentTutorialDismissed()
	);
	const [optionsMenuOpen, setOptionsMenuOpen] = useState(false);

	const config = useMemo(
		() => normalizeAgentConfig(node?.config || {}),
		[node?.config]
	);

	const attachments = useMemo(
		() => resolveAgentAttachments(graphNodes, node.id),
		[graphNodes, node.id]
	);

	const validationErrors = useMemo(
		() =>
			validateAgentConfig(
				node?.config || {},
				graphNodes,
				node.id,
				graphConnections
			),
		[node?.config, graphNodes, node.id, graphConnections]
	);

	const validationByField = useMemo(() => {
		const map = {};

		validationErrors.forEach((error) => {
			map[error.field] = error.message;
		});

		return map;
	}, [validationErrors]);

	const hasChatTrigger = agentHasConnectedChatTrigger(
		graphNodes,
		node.id,
		graphConnections
	);

	const patchSettings = (patch) => {
		onChangeConfig('settings', {
			...config.settings,
			...patch,
		});
	};

	const addOption = (optionId) => {
		if (config.options.includes(optionId)) {
			return;
		}

		onChangeConfig('options', [...config.options, optionId]);
		setOptionsMenuOpen(false);
	};

	const removeOption = (optionId) => {
		onChangeConfig(
			'options',
			config.options.filter((id) => id !== optionId)
		);
	};

	const availableOptions = AGENT_OPTION_CATALOG.filter(
		(option) => !config.options.includes(option.id)
	);

	const canExecute = validationErrors.length === 0;

	return (
		<div className="dragwyb-af-agent-config">
			<div className="dragwyb-af-agent-config__tabs">
				<button
					type="button"
					className={
						activeTab === 'parameters'
							? 'dragwyb-af-agent-config__tab dragwyb-af-agent-config__tab--active'
							: 'dragwyb-af-agent-config__tab'
					}
					onClick={() => setActiveTab('parameters')}
				>
					{__('Parameters', 'dragwyb-agentflow')}
				</button>
				<button
					type="button"
					className={
						activeTab === 'settings'
							? 'dragwyb-af-agent-config__tab dragwyb-af-agent-config__tab--active'
							: 'dragwyb-af-agent-config__tab'
					}
					onClick={() => setActiveTab('settings')}
				>
					{__('Settings', 'dragwyb-agentflow')}
				</button>
				<Button
					variant="primary"
					className="dragwyb-af-agent-config__execute"
					onClick={onExecuteStep}
					isBusy={testing}
					disabled={testing || !canExecute}
				>
					{__('Execute step', 'dragwyb-agentflow')}
				</Button>
			</div>

			{activeTab === 'parameters' && (
				<div className="dragwyb-af-agent-config__panel">
					{!bannerDismissed && (
						<div className="dragwyb-af-agent-config__banner" role="note">
							<span className="dragwyb-af-agent-config__banner-icon" aria-hidden="true">
								i
							</span>
							<p className="dragwyb-af-agent-config__banner-text">
								{__(
									'Tip: Get a feel for agents with our quick',
									'dragwyb-agentflow'
								)}{' '}
								<a
									href={TUTORIAL_URL}
									target="_blank"
									rel="noopener noreferrer"
								>
									{__('tutorial', 'dragwyb-agentflow')}
								</a>
								.
							</p>
							<button
								type="button"
								className="dragwyb-af-agent-config__banner-close"
								aria-label={__('Dismiss tip', 'dragwyb-agentflow')}
								onClick={() => {
									dismissAgentTutorial();
									setBannerDismissed(true);
								}}
							>
								×
							</button>
						</div>
					)}

					<SelectControl
						label={__(
							'Source for Prompt (User Message)',
							'dragwyb-agentflow'
						)}
						value={config.prompt_source}
						options={[
							{
								label: __(
									'Connected Chat Trigger Node',
									'dragwyb-agentflow'
								),
								value: PROMPT_SOURCE_CHAT_TRIGGER,
							},
							{
								label: __('Define below', 'dragwyb-agentflow'),
								value: PROMPT_SOURCE_DEFINE,
							},
						]}
						onChange={(value) => onChangeConfig('prompt_source', value)}
					/>

					{config.prompt_source === PROMPT_SOURCE_CHAT_TRIGGER ? (
						<p className="dragwyb-af-agent-config__help">
							{__(
								'Looks for an input field called chatInput from a directly connected Chat Trigger node. The prompt textarea is hidden while this source is selected.',
								'dragwyb-agentflow'
							)}
							{!hasChatTrigger && (
								<span className="dragwyb-af-builder-config__field-error">
									{' '}
									{__(
										'No trigger is connected to this agent yet.',
										'dragwyb-agentflow'
									)}
								</span>
							)}
						</p>
					) : (
						<div className="dragwyb-af-builder-config__field">
							<TokenField
								label={__(
									'Prompt (User Message)',
									'dragwyb-agentflow'
								)}
								value={config.prompt}
								variableSources={variableSources}
								nodeLabels={nodeLabels}
								onChange={(value) => onChangeConfig('prompt', value)}
							/>
							{validationByField.prompt && (
								<p className="dragwyb-af-builder-config__field-error">
									{validationByField.prompt}
								</p>
							)}
						</div>
					)}

					<ToggleControl
						label={__(
							'Require Specific Output Format',
							'dragwyb-agentflow'
						)}
						checked={config.require_output_format}
						onChange={(checked) =>
							onChangeConfig('require_output_format', checked)
						}
					/>

					{config.require_output_format && (
						<div className="dragwyb-af-agent-config__notice dragwyb-af-agent-config__notice--warning">
							{attachments.outputParser
								? __(
										'Output Parser connected. Click it on the canvas to edit the JSON example or schema.',
										'dragwyb-agentflow'
								  )
								: __(
										'Connect an Output Parser node on the canvas to specify the output format you require.',
										'dragwyb-agentflow'
								  )}
						</div>
					)}

					{validationByField.output_parser && (
						<p className="dragwyb-af-builder-config__field-error">
							{validationByField.output_parser}
						</p>
					)}

					<ToggleControl
						label={__(
							'Clean output (strip markdown)',
							'dragwyb-agentflow'
						)}
						help={__(
							'Removes ``` code fences from {{output}} so HTTP Request gets plain text. Raw reply stays in {{response}}.',
							'dragwyb-agentflow'
						)}
						checked={config.clean_output}
						onChange={(checked) =>
							onChangeConfig('clean_output', checked)
						}
					/>

					<ToggleControl
						label={__('Enable Fallback Model', 'dragwyb-agentflow')}
						checked={config.fallback_enabled}
						onChange={(checked) =>
							onChangeConfig('fallback_enabled', checked)
						}
					/>

					{config.fallback_enabled && (
						<div className="dragwyb-af-agent-config__notice dragwyb-af-agent-config__notice--info">
							{__(
								'Connect an additional language model on the canvas to use it as a fallback if the main model fails.',
								'dragwyb-agentflow'
							)}
						</div>
					)}

					{validationByField.fallback_chat_model && (
						<p className="dragwyb-af-builder-config__field-error">
							{validationByField.fallback_chat_model}
						</p>
					)}

					<div className="dragwyb-af-agent-config__options">
						<h3 className="dragwyb-af-agent-config__options-title">
							{__('Options', 'dragwyb-agentflow')}
						</h3>

						{config.options.length === 0 ? (
							<p className="dragwyb-af-agent-config__options-empty">
								{__('No properties', 'dragwyb-agentflow')}
							</p>
						) : (
							config.options.map((optionId) => {
								const optionMeta = AGENT_OPTION_CATALOG.find(
									(option) => option.id === optionId
								);

								if (!optionMeta) {
									return null;
								}

								if (optionId === 'system_prompt') {
									return (
										<div
											key={optionId}
											className="dragwyb-af-agent-config__option-row"
										>
											<TokenField
												label={optionMeta.label}
												value={config.system_prompt}
												variableSources={variableSources}
												nodeLabels={nodeLabels}
												onChange={(value) =>
													onChangeConfig('system_prompt', value)
												}
											/>
											<Button
												variant="link"
												isDestructive
												onClick={() => removeOption(optionId)}
											>
												{__('Remove', 'dragwyb-agentflow')}
											</Button>
										</div>
									);
								}

								if (optionId === 'max_iterations') {
									return (
										<div
											key={optionId}
											className="dragwyb-af-agent-config__option-row"
										>
											<TextControl
												label={optionMeta.label}
												type="number"
												min={1}
												max={10}
												value={String(config.max_iterations)}
												onChange={(value) =>
													onChangeConfig(
														'max_iterations',
														Math.max(1, Number(value || 5))
													)
												}
											/>
											<Button
												variant="link"
												isDestructive
												onClick={() => removeOption(optionId)}
											>
												{__('Remove', 'dragwyb-agentflow')}
											</Button>
										</div>
									);
								}

								return null;
							})
						)}

						<div className="dragwyb-af-agent-config__add-option-wrap">
							<Button
								variant="secondary"
								className="dragwyb-af-agent-config__add-option"
								onClick={() => setOptionsMenuOpen((open) => !open)}
								disabled={availableOptions.length === 0}
							>
								{__('Add Option', 'dragwyb-agentflow')}
							</Button>
							{optionsMenuOpen && availableOptions.length > 0 && (
								<div className="dragwyb-af-agent-config__add-option-menu">
									{availableOptions.map((option) => (
										<button
											key={option.id}
											type="button"
											className="dragwyb-af-agent-config__add-option-item"
											onClick={() => addOption(option.id)}
										>
											{option.label}
										</button>
									))}
								</div>
							)}
						</div>
					</div>
				</div>
			)}

			{activeTab === 'settings' && (
				<div className="dragwyb-af-agent-config__panel">
					<ToggleControl
						label={__('Always Output Data', 'dragwyb-agentflow')}
						checked={config.settings.always_output_data}
						onChange={(checked) =>
							patchSettings({ always_output_data: checked })
						}
					/>

					<ToggleControl
						label={__('Execute Once', 'dragwyb-agentflow')}
						checked={config.settings.execute_once}
						onChange={(checked) =>
							patchSettings({ execute_once: checked })
						}
					/>

					<ToggleControl
						label={__('Retry On Fail', 'dragwyb-agentflow')}
						checked={config.settings.retry_on_fail}
						onChange={(checked) =>
							patchSettings({ retry_on_fail: checked })
						}
					/>

					{config.settings.retry_on_fail && (
						<>
							<TextControl
								label={__('Max. Tries', 'dragwyb-agentflow')}
								type="number"
								min={1}
								max={10}
								value={String(config.settings.max_tries)}
								onChange={(value) =>
									patchSettings({
										max_tries: Math.max(1, Number(value || 3)),
									})
								}
							/>
							<TextControl
								label={__(
									'Wait Between Tries (ms)',
									'dragwyb-agentflow'
								)}
								type="number"
								min={0}
								value={String(config.settings.wait_between_tries_ms)}
								onChange={(value) =>
									patchSettings({
										wait_between_tries_ms: Math.max(
											0,
											Number(value || 1000)
										),
									})
								}
							/>
						</>
					)}

					<SelectControl
						label={__('On Error', 'dragwyb-agentflow')}
						value={config.settings.on_error}
						options={[
							{
								label: __('Stop Workflow', 'dragwyb-agentflow'),
								value: ON_ERROR_STOP,
							},
							{
								label: __('Continue', 'dragwyb-agentflow'),
								value: ON_ERROR_CONTINUE,
							},
							{
								label: __(
									'Continue using Error Output',
									'dragwyb-agentflow'
								),
								value: ON_ERROR_ERROR_OUTPUT,
							},
						]}
						onChange={(value) => patchSettings({ on_error: value })}
					/>

					<TextareaControl
						label={__('Notes', 'dragwyb-agentflow')}
						value={config.settings.notes}
						onChange={(value) => patchSettings({ notes: value })}
					/>

					<ToggleControl
						label={__(
							'Display Note in Flow?',
							'dragwyb-agentflow'
						)}
						checked={config.settings.display_note_in_flow}
						onChange={(checked) =>
							patchSettings({ display_note_in_flow: checked })
						}
					/>

					<p className="dragwyb-af-agent-config__version">
						{__(
							'AI Agent node version',
							'dragwyb-agentflow'
						)}{' '}
						{AI_AGENT_VERSION}
					</p>
				</div>
			)}

			<AgentConnectorRow
				attachments={attachments}
				fallbackEnabled={config.fallback_enabled}
				requireOutputFormat={config.require_output_format}
				validationByField={validationByField}
				onAddChatModel={() => onAddChatModel(node.id)}
				onAddMemory={() => onAddMemory(node.id)}
				onAddTool={() => onAddTool(node.id)}
				onAddFallbackModel={() => onAddFallbackModel(node.id)}
				onAddOutputParser={() => onAddOutputParser(node.id)}
				onSelectNode={onSelectNode}
			/>
		</div>
	);
}

function AgentConnectorRow({
	attachments,
	fallbackEnabled,
	requireOutputFormat,
	validationByField,
	onAddChatModel,
	onAddMemory,
	onAddTool,
	onAddFallbackModel,
	onAddOutputParser,
	onSelectNode,
}) {
	const connectors = [
		{
			id: 'chat_model',
			label: __('Chat Model', 'dragwyb-agentflow'),
			required: true,
			connected: attachments.chatModel,
			onAdd: onAddChatModel,
			error: validationByField.chat_model,
		},
		{
			id: 'memory',
			label: __('Memory', 'dragwyb-agentflow'),
			connected: attachments.memory,
			onAdd: onAddMemory,
		},
		{
			id: 'tool',
			label: __('Tool', 'dragwyb-agentflow'),
			connected: attachments.tools?.length > 0 ? attachments.tools[0] : null,
			toolCount: attachments.tools?.length || 0,
			onAdd: onAddTool,
		},
	];

	if (fallbackEnabled) {
		connectors.push({
			id: 'fallback_chat_model',
			label: __('Fallback Chat Model', 'dragwyb-agentflow'),
			required: true,
			connected: attachments.fallbackChatModel,
			onAdd: onAddFallbackModel,
			error: validationByField.fallback_chat_model,
		});
	}

	if (requireOutputFormat) {
		connectors.splice(1, 0, {
			id: 'output_parser',
			label: __('Output Parser', 'dragwyb-agentflow'),
			required: true,
			connected: attachments.outputParser,
			onAdd: onAddOutputParser,
			error: validationByField.output_parser,
		});
	}

	return (
		<div className="dragwyb-af-agent-config__connectors">
			{connectors.map((connector) => (
				<AgentConnectorSlot
					key={connector.id}
					connector={connector}
					onSelectNode={onSelectNode}
				/>
			))}
		</div>
	);
}

function AgentConnectorSlot({ connector, onSelectNode }) {
	const { connected, label, required, onAdd, error, toolCount } = connector;

	return (
		<div className="dragwyb-af-agent-config__connector">
			<span className="dragwyb-af-agent-config__connector-label">
				{label}
				{required ? (
					<span className="dragwyb-af-agent-config__connector-required">*</span>
				) : null}
			</span>
			{connected ? (
				<button
					type="button"
					className="dragwyb-af-agent-config__connector-chip"
					onClick={() => onSelectNode(connected.id)}
				>
					<ConnectorIcon node={connected} />
					<span className="dragwyb-af-agent-config__connector-chip-label">
						{connected.label || connected.type}
						{toolCount > 1 ? ` (+${toolCount - 1})` : ''}
					</span>
				</button>
			) : (
				<button
					type="button"
					className="dragwyb-af-agent-config__connector-add"
					onClick={onAdd}
					aria-label={__('Add connection', 'dragwyb-agentflow')}
				>
					+
				</button>
			)}
			{error ? (
				<span className="dragwyb-af-agent-config__connector-error">{error}</span>
			) : null}
		</div>
	);
}

function ConnectorIcon({ node }) {
	if (isChatModelAttachment(node) || node.attachment_type === 'fallback_chat_model') {
		const appId = CHAT_MODEL_APP_IDS[node.type] || 'openai';
		const meta = getNodeMeta(appId, 'action', appId);

		return (
			<span
				className="dragwyb-af-agent-config__connector-icon"
				style={{ backgroundColor: meta.bg, color: meta.accent }}
			>
				{meta.icon}
			</span>
		);
	}

	if (node.attachment_type === 'memory') {
		return (
			<span className="dragwyb-af-agent-config__connector-icon dragwyb-af-agent-config__connector-icon--muted">
				M
			</span>
		);
	}

	if (node.attachment_type === 'output_parser') {
		return (
			<span className="dragwyb-af-agent-config__connector-icon dragwyb-af-agent-config__connector-icon--parser">
				{'{ }'}
			</span>
		);
	}

	return (
		<span className="dragwyb-af-agent-config__connector-icon dragwyb-af-agent-config__connector-icon--tool">
			T
		</span>
	);
}
