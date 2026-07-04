import { useState, useEffect } from '@wordpress/element';
import {
	Button,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { createConnection } from '../api';

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
						key={fieldName}
						fieldName={fieldName}
						fieldSchema={nodeType.config_schema[fieldName]}
						value={node.config ? node.config[fieldName] : undefined}
						connections={connections}
						nodeTypeSlug={nodeType.slug}
						nodeTypeLabel={nodeType.label}
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
	onConnectionsChange,
	onChange,
}) {
	const label = fieldSchema.label || fieldName;
	const resolved = value === undefined ? fieldSchema.default : value;

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
	const selectedId = Number(value || 0);
	const needsConnection = required && selectedId <= 0;

	const [showAddForm, setShowAddForm] = useState(needsConnection);
	const [connectionLabel, setConnectionLabel] = useState(
		nodeTypeLabel
			? `${nodeTypeLabel}`
			: __('New connection', 'workflow-automate')
	);
	const [authType, setAuthType] = useState('api_key');
	const [secret, setSecret] = useState('');
	const [saving, setSaving] = useState(false);
	const [error, setError] = useState('');

	useEffect(() => {
		if (needsConnection) {
			setShowAddForm(true);
		}
	}, [needsConnection]);

	const options = [
		{ value: '0', label: __('None', 'workflow-automate') },
		...connections.map((connection) => ({
			value: String(connection.id),
			label: `${connection.label} (${connection.auth_type_label})`,
		})),
	];

	const secretFieldName = authType === 'bearer_token' ? 'token' : 'api_key';
	const secretFieldLabel =
		authType === 'bearer_token'
			? __('Bearer token / access token', 'workflow-automate')
			: __('API key', 'workflow-automate');

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
				onChange={(nextValue) => onChange(Number(nextValue))}
				help={
					needsConnection
						? __(
								'Required — add an API key below or pick an existing connection.',
								'workflow-automate'
							)
						: undefined
				}
			/>

			{!showAddForm && (
				<Button
					variant="secondary"
					className="wfa-builder-config__add-connection"
					onClick={() => setShowAddForm(true)}
				>
					{__('+ Add API key here', 'workflow-automate')}
				</Button>
			)}

			{showAddForm && (
				<div className="wfa-builder-config__connection-form">
					<p className="wfa-builder-config__connection-form-title">
						{__(
							'Add credentials for this node',
							'workflow-automate'
						)}
					</p>
					<TextControl
						label={__('Connection name', 'workflow-automate')}
						value={connectionLabel}
						onChange={setConnectionLabel}
					/>
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
