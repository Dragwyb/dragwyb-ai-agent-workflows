import { useMemo, useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import {
	buildPayloadTree,
	filterTree,
	pathToDisplayLabel,
} from '../utils/payloadVariables';

/**
 * Tree picker for trigger variables.
 *
 * @param {Object}      props
 * @param {*}           props.payload
 * @param {string}      props.sourceLabel
 * @param {Function}    props.onSelect
 * @param {Function}    props.onClose
 * @param {boolean}     [props.embedded]
 * @param {boolean}     [props.popover]
 * @param {boolean}     [props.showSearch]
 */
export default function VariablePicker({
	payload,
	sourceLabel,
	onSelect,
	onClose,
	embedded = false,
	popover = false,
	showSearch = false,
}) {
	const [query, setQuery] = useState('');
	const tree = useMemo(
		() => buildPayloadTree(payload, 'trigger', sourceLabel),
		[payload, sourceLabel]
	);
	const filtered = useMemo(() => {
		if (!showSearch || !query.trim()) {
			return tree;
		}

		return filterTree(tree, query) || tree;
	}, [tree, query, showSearch]);
	const hasData = (tree.children || []).length > 0;

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
						'No captured data yet. Use Test Flow → Listen new response.',
						'workflow-automate'
					)}
				</p>
			) : (
				<>
					<div className="wfa-variable-picker__source">
						<span className="wfa-variable-picker__source-badge">1</span>
						<span className="wfa-variable-picker__source-label">
							{sourceLabel}
						</span>
					</div>
					<ul className="wfa-variable-picker__tree">
						{(filtered.children || []).map((child) => (
							<TreeBranch
								key={child.id}
								node={child}
								depth={0}
								defaultOpen
								onSelect={onSelect}
							/>
						))}
					</ul>
				</>
			)}
		</div>
	);
}

/**
 * @param {Object}   props
 * @param {Object}   props.node
 * @param {number}   props.depth
 * @param {boolean}  props.defaultOpen
 * @param {Function} props.onSelect
 */
function TreeBranch({ node, depth, defaultOpen, onSelect }) {
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
						{pathToDisplayLabel(node.path)}
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
							onSelect={onSelect}
						/>
					))}
				</ul>
			)}
		</li>
	);
}
