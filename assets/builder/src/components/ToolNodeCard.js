import { __ } from '@wordpress/i18n';

import { useNodeDrag } from '../hooks/useNodeDrag';
import { getNodeMeta } from '../nodeMeta';

/**
 * Compact tool card attached below an AI Agent.
 */
export default function ToolNodeCard({
	node,
	selected,
	onSelect,
	onMove,
}) {
	const meta = getNodeMeta(node.type, 'action');
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
				'aiawa-tool-node',
				selected ? 'aiawa-tool-node--selected' : '',
			]
				.filter(Boolean)
				.join(' ')}
			data-node-id={node.id}
			role="button"
			tabIndex={0}
			onPointerDown={handlePointerDown}
			onKeyDown={handleKeyDown}
		>
			<span className="aiawa-tool-node__input-dot" aria-hidden="true" />
			<span
				className="aiawa-tool-node__icon"
				style={{
					backgroundColor: meta.bg,
					color: meta.accent,
				}}
				aria-hidden="true"
			>
				{meta.icon}
			</span>
			<span className="aiawa-tool-node__text">
				<span className="aiawa-tool-node__label">{node.label}</span>
				<span className="aiawa-tool-node__subtitle">
					{__('Tool', 'dragwyb-agentflow')}
				</span>
			</span>
		</div>
	);
}
