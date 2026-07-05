import { __ } from '@wordpress/i18n';

/**
 * Compact memory card attached below an AI Agent.
 */
export default function MemorySubNode({
	node,
	selected,
	onSelect,
}) {
	return (
		<button
			type="button"
			className={[
				'wfa-memory-node',
				selected ? 'wfa-memory-node--selected' : '',
			]
				.filter(Boolean)
				.join(' ')}
			data-node-id={node.id}
			onClick={(event) => {
				event.stopPropagation();
				onSelect(node.id);
			}}
		>
			<span className="wfa-memory-node__input-dot" aria-hidden="true" />
			<span className="wfa-memory-node__icon" aria-hidden="true">
				🧠
			</span>
			<span className="wfa-memory-node__label">
				{node.label || __('Simple Memory', 'workflow-automate')}
			</span>
		</button>
	);
}
