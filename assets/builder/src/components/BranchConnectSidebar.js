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
			className="dragwyb-af-builder-picker dragwyb-af-builder-picker--branch-connect"
			aria-label={__('Connect branch to node', 'dragwyb-agentflow')}
		>
			<div className="dragwyb-af-builder-picker__header">
				<h2 className="dragwyb-af-builder-picker__title">
					{__('Connect branch', 'dragwyb-agentflow')}
				</h2>
				<Button
					className="dragwyb-af-builder-picker__close"
					icon="no-alt"
					label={__('Close', 'dragwyb-agentflow')}
					onClick={onClose}
				/>
			</div>

			<p className="dragwyb-af-builder-picker__hint">
				{__(
					'Choose any step on the canvas for',
					'dragwyb-agentflow'
				)}{' '}
				<strong>{branchLabel}</strong>
			</p>

			<div className="dragwyb-af-builder-picker__search">
				<TextControl
					label={__('Search nodes', 'dragwyb-agentflow')}
					hideLabelFromVision
					placeholder={__('Search nodes…', 'dragwyb-agentflow')}
					value={query}
					onChange={setQuery}
				/>
			</div>

			{filtered.length === 0 ? (
				<p className="dragwyb-af-builder-picker__empty">
					{__(
						'No steps on the canvas yet. Add an AI Agent or action first.',
						'dragwyb-agentflow'
					)}
				</p>
			) : (
				<ul className="dragwyb-af-builder-picker__list">
					{filtered.map((node) => {
						const meta = getNodeMeta(node.type, node.category);
						const isCurrent = node.id === currentTargetId;

						return (
							<li key={node.id}>
								<button
									type="button"
									className={
										isCurrent
											? 'dragwyb-af-builder-picker__item dragwyb-af-builder-picker__item--selected'
											: 'dragwyb-af-builder-picker__item'
									}
									onClick={() => onSelect(node.id)}
								>
									<span
										className="dragwyb-af-builder-picker__item-icon"
										style={{
											backgroundColor: meta.bg,
											color: meta.accent,
										}}
										aria-hidden="true"
									>
										{meta.icon}
									</span>
									<span className="dragwyb-af-builder-picker__item-content">
										<span className="dragwyb-af-builder-picker__item-label">
											{node.label || node.type}
										</span>
										<span className="dragwyb-af-builder-picker__item-hint">
											{node.type === 'ai_agent_action'
												? __('AI Agent', 'dragwyb-agentflow')
												: node.type === 'condition_action'
													? __('Condition', 'dragwyb-agentflow')
													: node.type === 'router_action'
														? __('Router', 'dragwyb-agentflow')
														: __('Action', 'dragwyb-agentflow')}
										</span>
									</span>
									{isCurrent && (
										<span className="dragwyb-af-builder-picker__item-badge">
											{__('Connected', 'dragwyb-agentflow')}
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
