import { __ } from '@wordpress/i18n';

import { useNodeDrag } from '../hooks/useNodeDrag';

/**
 * Compact memory card attached below an AI Agent.
 */
export default function MemorySubNode({
	node,
	selected,
	onSelect,
	onMove,
}) {
	const { handlePointerDown, handleKeyDown } = useNodeDrag({
		nodeId: node.id,
		x: node.x,
		y: node.y,
		onMove,
		onSelect: () => onSelect(node.id),
	});

	return (
		<div
			className={[
				'aiawa-memory-node',
				selected ? 'aiawa-memory-node--selected' : '',
			]
				.filter(Boolean)
				.join(' ')}
			data-node-id={node.id}
			role="button"
			tabIndex={0}
			onPointerDown={handlePointerDown}
			onKeyDown={handleKeyDown}
		>
			<span className="aiawa-memory-node__input-dot" aria-hidden="true" />
			<span className="aiawa-memory-node__icon" aria-hidden="true">
				🧠
			</span>
			<span className="aiawa-memory-node__label">
				{node.label || __('Simple Memory', 'ai-agent-workflow-automation')}
			</span>
		</div>
	);
}
