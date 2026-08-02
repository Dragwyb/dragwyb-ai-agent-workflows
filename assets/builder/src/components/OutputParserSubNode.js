import { __ } from '@wordpress/i18n';

import { useNodeDrag } from '../hooks/useNodeDrag';

/**
 * Structured Output Parser card with Model* port for Auto-Fix.
 */
export default function OutputParserSubNode({
	node,
	selected,
	hasChatModel = false,
	onSelect,
	onMove,
	onAddChatModel,
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
				'dragwyb-af-output-parser-node',
				selected ? 'dragwyb-af-output-parser-node--selected' : '',
			]
				.filter(Boolean)
				.join(' ')}
			data-node-id={node.id}
			role="button"
			tabIndex={0}
			onPointerDown={handlePointerDown}
			onKeyDown={handleKeyDown}
		>
			<span className="dragwyb-af-output-parser-node__input-dot" aria-hidden="true" />
			<span className="dragwyb-af-output-parser-node__icon" aria-hidden="true">
				{'{✓}'}
			</span>
			<span className="dragwyb-af-output-parser-node__label">
				{node.label ||
					__('Structured Output Parser', 'dragwyb-agentflow')}
			</span>
			<div className="dragwyb-af-output-parser-node__model-row">
				<span className="dragwyb-af-output-parser-node__model-label">
					{__('Model', 'dragwyb-agentflow')}
					{!hasChatModel ? '*' : ''}
				</span>
				{!hasChatModel && onAddChatModel ? (
					<button
						type="button"
						className="dragwyb-af-output-parser-node__model-add"
						onClick={(event) => {
							event.stopPropagation();
							onAddChatModel(node.id);
						}}
						aria-label={__('Connect model', 'dragwyb-agentflow')}
					>
						+
					</button>
				) : (
					<span
						className="dragwyb-af-output-parser-node__model-dot"
						aria-hidden="true"
					/>
				)}
			</div>
		</div>
	);
}
