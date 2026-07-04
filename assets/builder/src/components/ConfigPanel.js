import { useState, useEffect, useMemo } from '@wordpress/element';
import {
	Button,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { createConnection, fetchConnectionModels } from '../api';
import CapturedResponse from './CapturedResponse';
import TokenField, { fieldSupportsVariables } from './TokenField';

/**
 * Per-integration defaults for the inline connection form so switching
 * nodes does not leak labels or auth types (e.g. Gemini → Telegram).
 *
 * @type {Record<string, {authType?: string, secretLabel?: string, secretFieldName?: string, hideAuthTypeSelect?: boolean}>}
 */
const INTEGRATION_CONNECTION_SETTINGS = {
	telegram_send_message_action: {
		authType: 'api_key',
		secretLabel: __('Bot token', 'workflow-automate'),
		secretFieldName: 'api_key',
		hideAuthTypeSelect: true,
	},
	openai_chat_action: {
		authType: 'api_key',
		secretLabel: __('OpenAI API key', 'workflow-automate'),
	},
	claude_messages_action: {
		authType: 'api_key',
		secretLabel: __('Anthropic API key', 'workflow-automate'),
	},
	gemini_generate_content_action: {
		authType: 'api_key',
		secretLabel: __('Google AI API key', 'workflow-automate'),
	},
	whatsapp_cloud_send_message_action: {
		authType: 'bearer_token',
		secretLabel: __('Access token', 'workflow-automate'),
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
	telegram_send_message_action: ['telegram'],
	whatsapp_cloud_send_message_action: ['whatsapp', 'whatsapp_cloud'],
	google_sheets_append_row_action: [
		'google_sheets',
		'google_sheets_api',
		'sheets',
	],
};

/**
 * @param {Object} connection
 * @param {string} nodeTypeSlug
 * @return {boolean}
 */
function connectionMatchesNodeType(connection, nodeTypeSlug) {
	const slug = connection.integration_slug || '';

	if (slug === nodeTypeSlug) {
		return true;
	}

	const aliases = INTEGRATION_SLUG_ALIASES[nodeTypeSlug] || [];

	return aliases.includes(slug);
}

/**
 * @param {Array<Object>} connections
 * @param {string}      nodeTypeSlug
 * @param {number}      selectedId
 * @return {Array<Object>}
 */
function filterMatchingConnections(connections, nodeTypeSlug, selectedId) {
	const list = (connections || []).filter((connection) =>
		connectionMatchesNodeType(connection, nodeTypeSlug)
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
}) {
	if (!node) {
		return (
			<aside
				className="wfa-builder-config wfa-builder-config--empty"
				aria-label={__('Node settings', 'workflow-automate')}
			>
				<p>
					{__(
						'Select a node to edit its settings.',
						'workflow-automate'
					)}
				</p>
			</aside>
		);
	}

	return (
		<aside
			className="wfa-builder-config"
			aria-label={__('Node settings', 'workflow-automate')}
		>
			<div className="wfa-builder-config__header">
				<h2>{nodeType ? nodeType.label : node.type}</h2>
				<Button
					className="wfa-builder-config__close"
					icon="no-alt"
					label={__('Close', 'workflow-automate')}
					onClick={onClose}
				/>
			</div>

			<TextControl
				label={__('Node label', 'workflow-automate')}
				value={node.label}
				onChange={onChangeLabel}
			/>

			{node.category === 'trigger' && (
				<CapturedResponse
					payload={capturedPayload}
					capturedAt={capturedAt}
					sourceLabel={node.label || triggerLabel}
				/>
			)}

			{!nodeType && (
				<p className="wfa-builder-config__warning">
					{__(
						'This node\u2019s type is not currently registered (the plugin or code that provided it may be inactive). Its saved configuration is preserved but cannot be edited here.',
						'workflow-automate'
					)}
				</p>
			)}

			{nodeType &&
				Object.keys(nodeType.config_schema || {}).map((fieldName) => (
					<ConfigField
						key={`${node.id}-${fieldName}`}
						fieldName={fieldName}
						fieldSchema={nodeType.config_schema[fieldName]}
						value={node.config ? node.config[fieldName] : undefined}
						connections={connections}
						nodeTypeSlug={nodeType.slug}
						nodeTypeLabel={nodeType.label}
						nodeCategory={node.category}
						nodeConfig={node.config || {}}
						capturedPayload={capturedPayload}
						triggerLabel={triggerLabel}
						onConnectionsChange={onConnectionsChange}
						onChange={(value) => onChangeConfig(fieldName, value)}
					/>
				))}

			<Button
				isDestructive
				variant="secondary"
				onClick={onDelete}
				className="wfa-builder-config__delete"
			>
				{__('Delete node', 'workflow-automate')}
			</Button>
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
	nodeCategory,
	nodeConfig,
	capturedPayload,
	triggerLabel,
	onConnectionsChange,
	onChange,
}) {
	if (fieldSchema.hidden) {
		return null;
	}

	const label = fieldSchema.label || fieldName;
	const resolved = value === undefined ? fieldSchema.default : value;

	if (
		fieldSchema.type === 'dynamic_select' &&
		fieldSchema.options_source === 'ai_models'
	) {
		const connectionField = fieldSchema.connection_field || 'connection_id';
		const connectionId = Number(nodeConfig[connectionField] || 0);

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
				connectionId={connectionId}
				nodeTypeSlug={nodeTypeSlug}
				onChange={onChange}
			/>
		);
	}

	if (fieldSchema.type === 'boolean') {
		return (
			<ToggleControl
				label={label}
				checked={Boolean(resolved)}
				onChange={onChange}
			/>
		);
	}

	if (fieldSchema.type === 'object' || fieldSchema.type === 'array') {
		return <JsonField label={label} value={resolved} onChange={onChange} />;
	}

	if (fieldSchema.type === 'connection') {
		return (
			<ConnectionField
				label={label}
				value={resolved}
				required={Boolean(fieldSchema.required)}
				connections={connections || []}
				nodeTypeSlug={nodeTypeSlug}
				nodeTypeLabel={nodeTypeLabel}
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
				label={label}
				value={
					resolved === undefined || resolved === null
						? ''
						: String(resolved)
				}
				required={Boolean(fieldSchema.required)}
				payload={capturedPayload}
				sourceLabel={triggerLabel}
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
 * Model picker loaded from the provider API for the selected connection.
 *
 * @param {Object}   props
 * @param {string}   props.label
 * @param {string}   props.value
 * @param {string}   props.defaultValue
 * @param {number}   props.connectionId
 * @param {string}   props.nodeTypeSlug
 * @param {Function} props.onChange
 */
function AiModelField({
	label,
	value,
	defaultValue,
	connectionId,
	nodeTypeSlug,
	onChange,
}) {
	const [options, setOptions] = useState([]);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState('');

	useEffect(() => {
		if (!connectionId || connectionId <= 0) {
			setOptions([]);
			setError('');
			setLoading(false);
			return undefined;
		}

		let cancelled = false;

		setLoading(true);
		setError('');

		fetchConnectionModels(connectionId, nodeTypeSlug)
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
						: __('Could not load models.', 'workflow-automate')
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
	}, [connectionId, nodeTypeSlug]);

	if (!connectionId || connectionId <= 0) {
		return (
			<TextControl
				label={label}
				value={value || defaultValue}
				onChange={onChange}
				help={__(
					'Select a connection above to load available models from the API.',
					'workflow-automate'
				)}
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
						label: __('Loading models…', 'workflow-automate'),
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
						'No models returned. Enter a model id manually.',
						'workflow-automate'
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
 * credentials on the node without leaving the builder.
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
	onConnectionsChange,
	onChange,
}) {
	const integrationSettings =
		INTEGRATION_CONNECTION_SETTINGS[nodeTypeSlug] || {};
	const defaultAuthType = integrationSettings.authType || 'api_key';

	const selectedId = Number(value || 0);
	const needsConnection = required && selectedId <= 0;

	const matchingConnections = useMemo(
		() => filterMatchingConnections(connections, nodeTypeSlug, selectedId),
		[connections, nodeTypeSlug, selectedId]
	);

	const [showAddForm, setShowAddForm] = useState(
		needsConnection && matchingConnections.length === 0
	);
	const [connectionLabel, setConnectionLabel] = useState(
		nodeTypeLabel
			? `${nodeTypeLabel}`
			: __('New connection', 'workflow-automate')
	);
	const [authType, setAuthType] = useState(defaultAuthType);
	const [secret, setSecret] = useState('');
	const [saving, setSaving] = useState(false);
	const [error, setError] = useState('');

	useEffect(() => {
		setConnectionLabel(
			nodeTypeLabel
				? `${nodeTypeLabel}`
				: __('New connection', 'workflow-automate')
		);
		setAuthType(defaultAuthType);
		setSecret('');
		setError('');

		if (selectedId > 0) {
			setShowAddForm(false);
			return;
		}

		setShowAddForm(needsConnection && matchingConnections.length === 0);
	}, [
		nodeTypeSlug,
		nodeTypeLabel,
		defaultAuthType,
		needsConnection,
		selectedId,
		matchingConnections.length,
	]);

	// Auto-select when exactly one saved connection matches this node type.
	useEffect(() => {
		if (!required || selectedId > 0 || matchingConnections.length !== 1) {
			return;
		}

		onChange(matchingConnections[0].id);
	}, [required, selectedId, matchingConnections, onChange]);

	const options = [
		{ value: '0', label: __('None', 'workflow-automate') },
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
			? __('Bearer token / access token', 'workflow-automate')
			: __('API key', 'workflow-automate'));

	const handleSaveConnection = async () => {
		const trimmedSecret = secret.trim();
		const trimmedLabel = connectionLabel.trim();

		if (!trimmedLabel) {
			setError(__('Enter a name for this connection.', 'workflow-automate'));
			return;
		}

		if (!trimmedSecret) {
			setError(__('Enter your API key or token.', 'workflow-automate'));
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
						'workflow-automate'
					)
			);
		} finally {
			setSaving(false);
		}
	};

	return (
		<div className="wfa-builder-config__connection">
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
						? __(
							'Required — add an API key below or pick an existing connection.',
							'workflow-automate'
						)
						: undefined
				}
			/>

			{!showAddForm && selectedId <= 0 && (
				<Button
					variant="secondary"
					className="wfa-builder-config__add-connection"
					onClick={() => {
						setSecret('');
						setError('');
						setAuthType(defaultAuthType);
						setShowAddForm(true);
					}}
				>
					{__('+ Add API key here', 'workflow-automate')}
				</Button>
			)}

			{!showAddForm && selectedId > 0 && (
				<Button
					variant="link"
					className="wfa-builder-config__add-connection"
					onClick={() => {
						setSecret('');
						setError('');
						setAuthType(defaultAuthType);
						setShowAddForm(true);
					}}
				>
					{__('Use a different API key', 'workflow-automate')}
				</Button>
			)}

			{showAddForm && (
				<div className="wfa-builder-config__connection-form">
					<p className="wfa-builder-config__connection-form-title">
						{nodeTypeLabel
							? sprintf(
								/* translators: %s: integration label */
								__(
									'Credentials for %s',
									'workflow-automate'
								),
								nodeTypeLabel
							)
							: __(
								'Add credentials for this node',
								'workflow-automate'
							)}
					</p>
					<TextControl
						label={__('Connection name', 'workflow-automate')}
						value={connectionLabel}
						onChange={setConnectionLabel}
					/>
					{!integrationSettings.hideAuthTypeSelect && (
						<SelectControl
							label={__('Auth type', 'workflow-automate')}
							value={authType}
							options={[
								{
									value: 'api_key',
									label: __('API Key', 'workflow-automate'),
								},
								{
									value: 'bearer_token',
									label: __('Bearer Token', 'workflow-automate'),
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
							'workflow-automate'
						)}
					/>
					{error && (
						<p className="wfa-builder-config__field-error" role="alert">
							{error}
						</p>
					)}
					<div className="wfa-builder-config__connection-form-actions">
						<Button
							isPrimary
							onClick={handleSaveConnection}
							disabled={saving}
						>
							{saving
								? __('Saving…', 'workflow-automate')
								: __('Save & use connection', 'workflow-automate')}
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
								{__('Cancel', 'workflow-automate')}
							</Button>
						)}
					</div>
				</div>
			)}
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
					'workflow-automate'
				)
			);
		}
	};

	return (
		<div className="wfa-builder-config__json-field">
			<TextareaControl
				label={label}
				value={text}
				onChange={handleChange}
				rows={4}
			/>
			{error && (
				<p className="wfa-builder-config__field-error">{error}</p>
			)}
		</div>
	);
}
