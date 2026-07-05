import { __ } from '@wordpress/i18n';

import { getNodeMeta } from '../nodeMeta';

/**
 * Compact tool card attached below an AI Agent.
 */
export default function ToolNodeCard({
	node,
	selected,
	onSelect,
}) {
	const meta = getNodeMeta(node.type, 'action');

	return (
		<button
			type="button"
			className={[
				'wfa-tool-node',
				selected ? 'wfa-tool-node--selected' : '',
			]
				.filter(Boolean)
				.join(' ')}
			data-node-id={node.id}
			onClick={(event) => {
				event.stopPropagation();
				onSelect(node.id);
			}}
		>
			<span className="wfa-tool-node__input-dot" aria-hidden="true" />
			<span
				className="wfa-tool-node__icon"
				style={{
					backgroundColor: meta.bg,
					color: meta.accent,
				}}
				aria-hidden="true"
			>
				{meta.icon}
			</span>
			<span className="wfa-tool-node__text">
				<span className="wfa-tool-node__label">{node.label}</span>
				<span className="wfa-tool-node__subtitle">
					{__('Tool', 'workflow-automate')}
				</span>
			</span>
		</button>
	);
}
