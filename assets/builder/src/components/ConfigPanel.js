import { useState, useEffect } from '@wordpress/element';
import {
	Button,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Right-hand panel for the currently selected node. Fields are rendered
 * generically from the node type's `configSchema()` (mirrors the shape
 * defined in Domain\Contracts\NodeTypeInterface on the PHP side) rather
 * than needing a bespoke React component per node type — this is what lets
 * third-party node types (registered via `wfa/nodes/register`) show up in
 * the builder with zero front-end changes. The one exception is the
 * `connection` field type (item 12): it still needs no per-*node-type*
 * component, but it does need the connections list itself, which is why
 * that one extra prop is threaded through from App.js rather than fetched
 * by this component directly.
 *
 * @param {Object}        props
 * @param {Object}        props.node
 * @param {Object|null}   props.nodeType
 * @param {Array<Object>} props.connections
 * @param {Function}      props.onChangeLabel
 * @param {Function}      props.onChangeConfig
 * @param {Function}      props.onDelete
 * @param {Function}      props.onClose
 */
export default function ConfigPanel({
	node,
	nodeType,
	connections,
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

function ConfigField({ fieldName, fieldSchema, value, connections, onChange }) {
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
				connections={connections || []}
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
 * Renders a "connection" field (item 12) as a `<select>` of every stored
 * connection, identified by its stored id — never its credentials, which
 * this component never receives in the first place (see
 * `ConnectionsController`, which the `connections` prop ultimately comes
 * from). `0`/"None" is always the first option since every consumer of
 * this field type (e.g. `HttpRequestAction`) treats an unset connection as
 * "send unauthenticated", not as an error.
 *
 * @param {Object}        props
 * @param {string}        props.label
 * @param {*}             props.value
 * @param {Array<Object>} props.connections
 * @param {Function}      props.onChange
 */
function ConnectionField({ label, value, connections, onChange }) {
	const options = [
		{ value: '0', label: __('None', 'workflow-automate') },
		...connections.map((connection) => ({
			value: String(connection.id),
			label: `${connection.label} (${connection.auth_type_label})`,
		})),
	];

	return (
		<SelectControl
			label={label}
			value={String(value || 0)}
			options={options}
			onChange={(nextValue) => onChange(Number(nextValue))}
		/>
	);
}

/**
 * Object/array fields are edited as raw JSON text. This keeps the panel
 * generic for arbitrary structured config (e.g. HTTP headers) without
 * building a key/value editor widget in this shell increment; invalid JSON
 * is kept local to the textarea (with an inline error) instead of being
 * committed to the graph, so a typo can't corrupt saved config.
 *
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
