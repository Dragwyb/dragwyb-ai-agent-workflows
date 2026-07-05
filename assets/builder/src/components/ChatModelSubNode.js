import { __ } from '@wordpress/i18n';

import { getNodeMeta } from '../nodeMeta';
import {
	CHAT_MODEL_APP_IDS,
	CHAT_MODEL_NODE_SIZE,
} from '../utils/agentAttachments';

/**
 * Circular chat model card attached below an AI Agent.
 */
export default function ChatModelSubNode({
	node,
	selected,
	onSelect,
}) {
	const appId = CHAT_MODEL_APP_IDS[node.type] || 'openai';
	const meta = getNodeMeta(node.type, 'action', appId);

	return (
		<button
			type="button"
			className={[
				'wfa-chat-model-node',
				selected ? 'wfa-chat-model-node--selected' : '',
			]
				.filter(Boolean)
				.join(' ')}
			style={{ width: CHAT_MODEL_NODE_SIZE, height: CHAT_MODEL_NODE_SIZE }}
			data-node-id={node.id}
			onClick={(event) => {
				event.stopPropagation();
				onSelect(node.id);
			}}
		>
			<span className="wfa-chat-model-node__input-dot" aria-hidden="true" />
			<span
				className="wfa-chat-model-node__ring"
				style={{
					backgroundColor: meta.bg,
					color: meta.accent,
				}}
				aria-hidden="true"
			>
				<span className="wfa-chat-model-node__icon">{meta.icon}</span>
			</span>
			<span className="wfa-chat-model-node__label">{node.label}</span>
			<span className="wfa-chat-model-node__subtitle">
				{__('Chat Model', 'workflow-automate')}
			</span>
		</button>
	);
}
