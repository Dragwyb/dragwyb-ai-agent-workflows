import { __ } from '@wordpress/i18n';

import { useNodeDrag } from '../hooks/useNodeDrag';
import { getNodeMeta } from '../nodeMeta';
import {
	CHAT_MODEL_APP_IDS,
	CHAT_MODEL_NODE_SIZE,
} from '../utils/agentAttachments';

/**
 * Circular chat model card (logo + label below).
 */
export default function ChatModelSubNode({
	node,
	selected,
	onSelect,
	onMove,
}) {
	const appId = CHAT_MODEL_APP_IDS[node.type] || 'openai';
	const meta = getNodeMeta(node.type, 'action', appId);
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
				'aiawa-chat-model-node',
				selected ? 'aiawa-chat-model-node--selected' : '',
			]
				.filter(Boolean)
				.join(' ')}
			style={{ width: CHAT_MODEL_NODE_SIZE }}
			data-node-id={node.id}
			role="button"
			tabIndex={0}
			onPointerDown={handlePointerDown}
			onKeyDown={handleKeyDown}
		>
			<span className="aiawa-chat-model-node__port" aria-hidden="true" />
			<span
				className="aiawa-chat-model-node__ring"
				style={{ backgroundColor: meta.bg, color: meta.accent }}
				aria-hidden="true"
			>
				<span className="aiawa-chat-model-node__icon">{meta.icon}</span>
			</span>
			<span className="aiawa-chat-model-node__label">
				{node.label || __('Chat Model', 'dragwyb-agentflow')}
			</span>
			<span className="aiawa-chat-model-node__subtitle">
				{__('Chat Model', 'dragwyb-agentflow')}
			</span>
		</div>
	);
}
