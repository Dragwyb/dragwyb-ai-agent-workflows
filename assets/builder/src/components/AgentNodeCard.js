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
		'aiawa-builder-node',
		'aiawa-builder-node--agent',
		selected ? 'aiawa-builder-node--selected' : '',
		hasUnknownType ? 'aiawa-builder-node--unknown' : '',
		isLinkTarget ? 'aiawa-builder-node--link-target' : '',
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
				className="aiawa-agent-node__main"
				style={{ minHeight: `${AGENT_BODY_HEIGHT}px` }}
			>
				<span
					className="aiawa-builder-node__handle aiawa-builder-node__handle--input"
					aria-hidden="true"
				/>

				<div className="aiawa-builder-node__body">
					<span
						className="aiawa-builder-node__icon"
						style={{
							backgroundColor: meta.bg,
							color: meta.accent,
						}}
						aria-hidden="true"
					>
						{meta.icon}
					</span>
					<div className="aiawa-builder-node__text">
						<span className="aiawa-builder-node__label">{node.label}</span>
						<span className="aiawa-builder-node__subtitle">
							{__('AI Agent', 'ai-agent-workflow-automation')}
						</span>
					</div>
				</div>

				{canStartFlowConnection && onStartFlowConnectionDrag && (
					<button
						type="button"
						className="aiawa-builder-node__output-port aiawa-builder-node__output-port--side"
						title={__(
							'Drag to the next step to connect',
							'ai-agent-workflow-automation'
						)}
						aria-label={__(
							'Drag to the next step to connect',
							'ai-agent-workflow-automation'
						)}
						onPointerDown={(event) => {
							stopPointer(event);
							onStartFlowConnectionDrag(node.id, event);
						}}
					/>
				)}
			</div>

			<div
				className="aiawa-agent-node__ports"
				style={{ minHeight: `${AGENT_PORTS_HEIGHT}px` }}
			>
				<div className="aiawa-agent-node__port">
					<span className="aiawa-agent-node__port-label">
						{__('Chat Model', 'ai-agent-workflow-automation')}
						<span className="aiawa-agent-node__required">*</span>
					</span>
					{hasChatModel ? (
						<button
							type="button"
							className="aiawa-agent-node__port-dot aiawa-agent-node__port-dot--ok aiawa-agent-node__port-dot--link"
							title={__('Open chat model settings', 'ai-agent-workflow-automation')}
							aria-label={__(
								'Open chat model settings',
								'ai-agent-workflow-automation'
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
							className="aiawa-agent-node__add-port"
							aria-label={__(
								'Add chat model to agent',
								'ai-agent-workflow-automation'
							)}
							title={__(
								'Select OpenAI, Gemini, Claude, OpenRouter, Groq, or DeepSeek',
								'ai-agent-workflow-automation'
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

				<div className="aiawa-agent-node__port">
					<span className="aiawa-agent-node__port-label">
						{__('Memory', 'ai-agent-workflow-automation')}
					</span>
					{hasMemory ? (
						<span
							className="aiawa-agent-node__port-dot aiawa-agent-node__port-dot--ok"
							title={__('Memory connected', 'ai-agent-workflow-automation')}
						/>
					) : (
						<button
							type="button"
							className="aiawa-agent-node__add-port aiawa-agent-node__add-port--muted"
							aria-label={__('Add memory to agent', 'ai-agent-workflow-automation')}
							title={__('Add simple memory', 'ai-agent-workflow-automation')}
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

				<div className="aiawa-agent-node__port aiawa-agent-node__port--tool">
					<span className="aiawa-agent-node__port-label">
						{__('Tool', 'ai-agent-workflow-automation')}
					</span>
					<button
						type="button"
						className="aiawa-agent-node__add-port"
						aria-label={__('Add tool to agent', 'ai-agent-workflow-automation')}
						title={__(
							'Add an action as an agent tool',
							'ai-agent-workflow-automation'
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
