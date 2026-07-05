import { useMemo, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
	buildPayloadTree,
	filterTree,
	pathToDisplayLabel,
} from '../utils/payloadVariables';

/**
 * Tree picker for trigger + prior-step variables.
 *
 * @param {Object}      props
 * @param {Array<Object>} [props.sources]  `{ id, label, badge, tree }` list.
 * @param {*}           [props.payload]   Legacy single trigger payload.
 * @param {string}      [props.sourceLabel]
 * @param {Record<string, string>} [props.nodeLabels]
 * @param {Function}    props.onSelect
 * @param {Function}    props.onClose
 * @param {boolean}     [props.embedded]
 * @param {boolean}     [props.popover]
 * @param {boolean}     [props.showSearch]
 */
export default function VariablePicker({
	sources,
	payload,
	sourceLabel,
	nodeLabels = {},
	onSelect,
	onClose,
	embedded = false,
	popover = false,
	showSearch = false,
}) {
	const [query, setQuery] = useState('');

	const resolvedSources = useMemo(() => {
		if (sources && sources.length > 0) {
			return sources;
		}

		if (
			payload !== null &&
			payload !== undefined &&
			typeof payload === 'object' &&
			Object.keys(payload).length > 0
		) {
			return [
				{
					id: 'trigger',
					label: sourceLabel || 'Trigger',
					badge: 1,
					tree: buildPayloadTree(payload, 'trigger', sourceLabel || 'Trigger'),
				},
			];
		}

		return [];
	}, [sources, payload, sourceLabel]);

	const filteredSources = useMemo(() => {
		if (!showSearch || !query.trim()) {
			return resolvedSources;
		}

		return resolvedSources
			.map((source) => {
				const filtered = filterTree(source.tree, query);

				if (!filtered) {
					return null;
				}

				return { ...source, tree: filtered };
			})
			.filter(Boolean);
	}, [resolvedSources, query, showSearch]);

	const hasData = resolvedSources.some(
		(source) => (source.tree.children || []).length > 0
	);

	return (
		<div
			className={`wfa-variable-picker${embedded ? ' wfa-variable-picker--embedded' : ''}${popover ? ' wfa-variable-picker--popover' : ''}`}
		>
			{!popover && (
				<div className="wfa-variable-picker__header">
					<h3>{__('Variables', 'workflow-automate')}</h3>
					{!embedded && (
						<Button
							icon="no-alt"
							label={__('Close', 'workflow-automate')}
							onClick={onClose}
						/>
					)}
				</div>
			)}

			{showSearch && (
				<div className="wfa-variable-picker__search">
					<input
						type="search"
						className="wfa-variable-picker__search-input"
						placeholder={__('Search variables…', 'workflow-automate')}
						value={query}
						onChange={(event) => setQuery(event.target.value)}
					/>
				</div>
			)}

			{!hasData ? (
				<p className="wfa-variable-picker__empty">
					{__(
						'No variables yet. Listen for trigger data or add steps above this node.',
						'workflow-automate'
					)}
				</p>
			) : (
				filteredSources.map((source) => (
					<div key={source.id} className="wfa-variable-picker__source-block">
						<div className="wfa-variable-picker__source">
							<span className="wfa-variable-picker__source-badge">
								{source.badge}
							</span>
							<span className="wfa-variable-picker__source-label">
								{source.label}
							</span>
						</div>
						<ul className="wfa-variable-picker__tree">
							{(source.tree.children || []).map((child) => (
								<TreeBranch
									key={`${source.id}-${child.id}`}
									node={child}
									depth={0}
									defaultOpen
									nodeLabels={nodeLabels}
									onSelect={onSelect}
								/>
							))}
						</ul>
					</div>
				))
			)}
		</div>
	);
}

/**
 * @param {Object}   props
 * @param {Object}   props.node
 * @param {number}   props.depth
 * @param {boolean}  props.defaultOpen
 * @param {Record<string, string>} props.nodeLabels
 * @param {Function} props.onSelect
 */
function TreeBranch({ node, depth, defaultOpen, nodeLabels, onSelect }) {
	const [open, setOpen] = useState(defaultOpen);
	const children = node.children || [];

	if (node.isLeaf) {
		return (
			<li className="wfa-variable-picker__leaf">
				<button
					type="button"
					className="wfa-variable-picker__leaf-btn"
					style={{ paddingLeft: `${8 + depth * 14}px` }}
					onClick={() => onSelect(node.token, node.path)}
					title={node.token}
				>
					<span className="wfa-variable-picker__pill">
						{pathToDisplayLabel(node.path, nodeLabels)}
					</span>
					{node.preview && (
						<span className="wfa-variable-picker__preview">
							{node.preview}
						</span>
					)}
				</button>
			</li>
		);
	}

	if (children.length === 0) {
		return null;
	}

	return (
		<li className="wfa-variable-picker__branch">
			<button
				type="button"
				className="wfa-variable-picker__branch-btn"
				style={{ paddingLeft: `${8 + depth * 14}px` }}
				onClick={() => setOpen(!open)}
				aria-expanded={open}
			>
				<span className="wfa-variable-picker__chevron">{open ? '▾' : '▸'}</span>
				<span className="wfa-variable-picker__branch-label">{node.label}</span>
			</button>
			{open && (
				<ul className="wfa-variable-picker__tree wfa-variable-picker__tree--nested">
					{children.map((child) => (
						<TreeBranch
							key={child.id}
							node={child}
							depth={depth + 1}
							defaultOpen={depth < 1}
							nodeLabels={nodeLabels}
							onSelect={onSelect}
						/>
					))}
				</ul>
			)}
		</li>
	);
}
