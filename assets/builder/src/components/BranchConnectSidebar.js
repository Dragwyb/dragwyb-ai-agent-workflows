import { useMemo, useState } from '@wordpress/element';
import { Button, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { getNodeMeta } from '../nodeMeta';
import { getConnectableCanvasNodes } from '../utils/conditionBranches';

/**
 * Pick any canvas node to connect a condition branch to.
 *
 * @param {Object}   props
 * @param {string}   props.branchLabel
 * @param {Array}    props.nodes         All graph nodes.
 * @param {string}   props.conditionNodeId
 * @param {string}   [props.currentTargetId]
 * @param {Function} props.onSelect      ( targetNodeId ) => void
 * @param {Function} props.onClose
 */
export default function BranchConnectSidebar({
	branchLabel,
	nodes,
	conditionNodeId,
	currentTargetId = '',
	onSelect,
	onClose,
}) {
	const [query, setQuery] = useState('');

	const connectable = useMemo(
		() => getConnectableCanvasNodes(nodes, conditionNodeId),
		[nodes, conditionNodeId]
	);

	const filtered = useMemo(() => {
		const needle = query.trim().toLowerCase();

		if (!needle) {
			return connectable;
		}

		return connectable.filter((node) => {
			const label = (node.label || node.type || '').toLowerCase();
			const type = (node.type || '').toLowerCase();

			return label.includes(needle) || type.includes(needle);
		});
	}, [connectable, query]);

	return (
		<aside
			className="daiaw-builder-picker daiaw-builder-picker--branch-connect"
			aria-label={__('Connect branch to node', 'dragwyb-ai-agent-workflows')}
		>
			<div className="daiaw-builder-picker__header">
				<h2 className="daiaw-builder-picker__title">
					{__('Connect branch', 'dragwyb-ai-agent-workflows')}
				</h2>
				<Button
					className="daiaw-builder-picker__close"
					icon="no-alt"
					label={__('Close', 'dragwyb-ai-agent-workflows')}
					onClick={onClose}
				/>
			</div>

			<p className="daiaw-builder-picker__hint">
				{__(
					'Choose any step on the canvas for',
					'dragwyb-ai-agent-workflows'
				)}{' '}
				<strong>{branchLabel}</strong>
			</p>

			<div className="daiaw-builder-picker__search">
				<TextControl
					label={__('Search nodes', 'dragwyb-ai-agent-workflows')}
					hideLabelFromVision
					placeholder={__('Search nodes…', 'dragwyb-ai-agent-workflows')}
					value={query}
					onChange={setQuery}
				/>
			</div>

			{filtered.length === 0 ? (
				<p className="daiaw-builder-picker__empty">
					{__(
						'No steps on the canvas yet. Add an AI Agent or action first.',
						'dragwyb-ai-agent-workflows'
					)}
				</p>
			) : (
				<ul className="daiaw-builder-picker__list">
					{filtered.map((node) => {
						const meta = getNodeMeta(node.type, node.category);
						const isCurrent = node.id === currentTargetId;

						return (
							<li key={node.id}>
								<button
									type="button"
									className={
										isCurrent
											? 'daiaw-builder-picker__item daiaw-builder-picker__item--selected'
											: 'daiaw-builder-picker__item'
									}
									onClick={() => onSelect(node.id)}
								>
									<span
										className="daiaw-builder-picker__item-icon"
										style={{
											backgroundColor: meta.bg,
											color: meta.accent,
										}}
										aria-hidden="true"
									>
										{meta.icon}
									</span>
									<span className="daiaw-builder-picker__item-content">
										<span className="daiaw-builder-picker__item-label">
											{node.label || node.type}
										</span>
										<span className="daiaw-builder-picker__item-hint">
											{node.type === 'ai_agent_action'
												? __('AI Agent', 'dragwyb-ai-agent-workflows')
												: node.type === 'condition_action'
													? __('Condition', 'dragwyb-ai-agent-workflows')
													: node.type === 'router_action'
														? __('Router', 'dragwyb-ai-agent-workflows')
														: __('Action', 'dragwyb-ai-agent-workflows')}
										</span>
									</span>
									{isCurrent && (
										<span className="daiaw-builder-picker__item-badge">
											{__('Connected', 'dragwyb-ai-agent-workflows')}
										</span>
									)}
								</button>
							</li>
						);
					})}
				</ul>
			)}
		</aside>
	);
}
