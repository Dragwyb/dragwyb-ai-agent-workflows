import { __ } from '@wordpress/i18n';

import { useNodeDrag } from '../hooks/useNodeDrag';

/**
 * Compact Structured Output Parser card attached below an AI Agent.
 * n8n-style: Output Parser port on top, Model* on the bottom for Auto-Fix.
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
				'wfa-output-parser-node',
				selected ? 'wfa-output-parser-node--selected' : '',
				!hasChatModel ? 'wfa-output-parser-node--needs-model' : '',
			]
				.filter(Boolean)
				.join(' ')}
			data-node-id={node.id}
			role="button"
			tabIndex={0}
			onPointerDown={handlePointerDown}
			onKeyDown={handleKeyDown}
		>
			<span className="wfa-output-parser-node__output-port" aria-hidden="true">
				{__('Output Parser', 'workflow-automate')}
			</span>
			<span className="wfa-output-parser-node__input-dot" aria-hidden="true" />
			<span className="wfa-output-parser-node__icon" aria-hidden="true">
				{'{✓}'}
			</span>
			<span className="wfa-output-parser-node__label">
				{node.label ||
					__('Structured Output Parser', 'workflow-automate')}
			</span>
			<div className="wfa-output-parser-node__model-row">
				<span
					className={[
						'wfa-output-parser-node__model-label',
						!hasChatModel
							? 'wfa-output-parser-node__model-label--required'
							: '',
					]
						.filter(Boolean)
						.join(' ')}
				>
					{__('Model', 'workflow-automate')}
					{!hasChatModel ? '*' : ''}
				</span>
				{!hasChatModel && typeof onAddChatModel === 'function' ? (
					<button
						type="button"
						className="wfa-output-parser-node__model-add"
						onClick={(event) => {
							event.stopPropagation();
							onAddChatModel(node.id);
						}}
						aria-label={__(
							'Connect chat model for Auto-Fix',
							'workflow-automate'
						)}
					>
						+
					</button>
				) : (
					<span
						className="wfa-output-parser-node__model-dot"
						aria-hidden="true"
					/>
				)}
			</div>
		</div>
	);
}
