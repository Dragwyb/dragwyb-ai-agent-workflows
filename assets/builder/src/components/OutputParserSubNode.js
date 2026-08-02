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
				'aiawa-output-parser-node',
				selected ? 'aiawa-output-parser-node--selected' : '',
			]
				.filter(Boolean)
				.join(' ')}
			data-node-id={node.id}
			role="button"
			tabIndex={0}
			onPointerDown={handlePointerDown}
			onKeyDown={handleKeyDown}
		>
			<span className="aiawa-output-parser-node__input-dot" aria-hidden="true" />
			<span className="aiawa-output-parser-node__icon" aria-hidden="true">
				{'{✓}'}
			</span>
			<span className="aiawa-output-parser-node__label">
				{node.label ||
					__('Structured Output Parser', 'ai-agent-workflow-automation')}
			</span>
			<div className="aiawa-output-parser-node__model-row">
				<span className="aiawa-output-parser-node__model-label">
					{__('Model', 'ai-agent-workflow-automation')}
					{!hasChatModel ? '*' : ''}
				</span>
				{!hasChatModel && onAddChatModel ? (
					<button
						type="button"
						className="aiawa-output-parser-node__model-add"
						onClick={(event) => {
							event.stopPropagation();
							onAddChatModel(node.id);
						}}
						aria-label={__('Connect model', 'ai-agent-workflow-automation')}
					>
						+
					</button>
				) : (
					<span
						className="aiawa-output-parser-node__model-dot"
						aria-hidden="true"
					/>
				)}
			</div>
		</div>
	);
}
