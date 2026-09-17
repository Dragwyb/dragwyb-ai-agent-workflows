import { __ } from '@wordpress/i18n';

import { useNodeDrag } from '../hooks/useNodeDrag';
import { getNodeMeta } from '../nodeMeta';
import {
	AGENT_BODY_HEIGHT,
	AGENT_PORTS_HEIGHT,
} from '../utils/agentAttachments';

/**
 * n8n-style AI Agent card with Chat Model / Memory / Tool (+) ports.
 */
export default function AgentNodeCard({
	node,
	selected,
	isLinkTarget = false,
	hasUnknownType,
	hasChatModel,
	hasMemory,
	chatModelId,
	canStartFlowConnection = false,
	onSelect,
	onMove,
	onAddChatModel,
	onAddMemory,
	onAddTool,
	onStartFlowConnectionDrag,
	registerRef,
}) {
	const meta = getNodeMeta('ai-agent', 'action');
	const { handlePointerDown, handleKeyDown } = useNodeDrag({
		nodeId: node.id,
		x: node.x,
		y: node.y,
		onMove,
		onSelect,
	});

	const stopPointer = (event) => {
		event.stopPropagation();
	};

	const classNames = [
		'dragaiw-builder-node',
		'dragaiw-builder-node--agent',
		selected ? 'dragaiw-builder-node--selected' : '',
		hasUnknownType ? 'dragaiw-builder-node--unknown' : '',
		isLinkTarget ? 'dragaiw-builder-node--link-target' : '',
	]
		.filter(Boolean)
		.join(' ');

	return (
		<div
			ref={(element) => {
				if (registerRef) {
					registerRef(node.id, element);
				}
			}}
			className={classNames}
			style={{ transform: `translate(${node.x}px, ${node.y}px)` }}
			data-node-id={node.id}
			role="button"
			tabIndex={0}
			aria-pressed={selected}
			onPointerDown={handlePointerDown}
			onKeyDown={handleKeyDown}
		>
			<div
				className="dragaiw-agent-node__main"
				style={{ minHeight: `${AGENT_BODY_HEIGHT}px` }}
			>
				<span
					className="dragaiw-builder-node__handle dragaiw-builder-node__handle--input"
					aria-hidden="true"
				/>

				<div className="dragaiw-builder-node__body">
					<span
						className="dragaiw-builder-node__icon"
						style={{
							backgroundColor: meta.bg,
							color: meta.accent,
						}}
						aria-hidden="true"
					>
						{meta.icon}
					</span>
					<div className="dragaiw-builder-node__text">
						<span className="dragaiw-builder-node__label">{node.label}</span>
						<span className="dragaiw-builder-node__subtitle">
							{__('AI Agent', 'dragwyb-ai-agent-workflows')}
						</span>
					</div>
				</div>

				{canStartFlowConnection && onStartFlowConnectionDrag && (
					<button
						type="button"
						className="dragaiw-builder-node__output-port dragaiw-builder-node__output-port--side"
						title={__(
							'Drag to the next step to connect',
							'dragwyb-ai-agent-workflows'
						)}
						aria-label={__(
							'Drag to the next step to connect',
							'dragwyb-ai-agent-workflows'
						)}
						onPointerDown={(event) => {
							stopPointer(event);
							onStartFlowConnectionDrag(node.id, event);
						}}
					/>
				)}
			</div>

			<div
				className="dragaiw-agent-node__ports"
				style={{ minHeight: `${AGENT_PORTS_HEIGHT}px` }}
			>
				<div className="dragaiw-agent-node__port">
					<span className="dragaiw-agent-node__port-label">
						{__('Chat Model', 'dragwyb-ai-agent-workflows')}
						<span className="dragaiw-agent-node__required">*</span>
					</span>
					{hasChatModel ? (
						<button
							type="button"
							className="dragaiw-agent-node__port-dot dragaiw-agent-node__port-dot--ok dragaiw-agent-node__port-dot--link"
							title={__('Open chat model settings', 'dragwyb-ai-agent-workflows')}
							aria-label={__(
								'Open chat model settings',
								'dragwyb-ai-agent-workflows'
							)}
							onPointerDown={stopPointer}
							onClick={(event) => {
								event.stopPropagation();
								if (chatModelId) {
									onSelect(chatModelId);
								}
							}}
						/>
					) : (
						<button
							type="button"
							className="dragaiw-agent-node__add-port"
							aria-label={__(
								'Add chat model to agent',
								'dragwyb-ai-agent-workflows'
							)}
							title={__(
								'Select OpenAI, Gemini, Claude, OpenRouter, Groq, or DeepSeek',
								'dragwyb-ai-agent-workflows'
							)}
							onPointerDown={stopPointer}
							onClick={(event) => {
								event.stopPropagation();
								onAddChatModel(node.id);
							}}
						>
							+
						</button>
					)}
				</div>

				<div className="dragaiw-agent-node__port">
					<span className="dragaiw-agent-node__port-label">
						{__('Memory', 'dragwyb-ai-agent-workflows')}
					</span>
					{hasMemory ? (
						<span
							className="dragaiw-agent-node__port-dot dragaiw-agent-node__port-dot--ok"
							title={__('Memory connected', 'dragwyb-ai-agent-workflows')}
						/>
					) : (
						<button
							type="button"
							className="dragaiw-agent-node__add-port dragaiw-agent-node__add-port--muted"
							aria-label={__('Add memory to agent', 'dragwyb-ai-agent-workflows')}
							title={__('Add simple memory', 'dragwyb-ai-agent-workflows')}
							onPointerDown={stopPointer}
							onClick={(event) => {
								event.stopPropagation();
								onAddMemory(node.id);
							}}
						>
							+
						</button>
					)}
				</div>

				<div className="dragaiw-agent-node__port dragaiw-agent-node__port--tool">
					<span className="dragaiw-agent-node__port-label">
						{__('Tool', 'dragwyb-ai-agent-workflows')}
					</span>
					<button
						type="button"
						className="dragaiw-agent-node__add-port"
						aria-label={__('Add tool to agent', 'dragwyb-ai-agent-workflows')}
						title={__(
							'Add an action as an agent tool',
							'dragwyb-ai-agent-workflows'
						)}
						onPointerDown={stopPointer}
						onClick={(event) => {
							event.stopPropagation();
							onAddTool(node.id);
						}}
					>
						+
					</button>
				</div>
			</div>
		</div>
	);
}
