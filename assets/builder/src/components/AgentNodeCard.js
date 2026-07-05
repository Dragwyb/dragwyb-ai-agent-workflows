import { __ } from '@wordpress/i18n';

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
	hasUnknownType,
	hasChatModel,
	hasMemory,
	chatModelId,
	onSelect,
	onAddChatModel,
	onAddMemory,
	onAddTool,
	registerRef,
}) {
	const meta = getNodeMeta('ai-agent', 'action');

	return (
		<div
			className="wfa-agent-node-wrap"
			style={{ transform: `translate(${node.x}px, ${node.y}px)` }}
			data-node-id={node.id}
		>
			<div
				ref={(element) => {
					if (registerRef) {
						registerRef(node.id, element);
					}
				}}
				className={[
					'wfa-builder-node',
					'wfa-builder-node--agent',
					selected ? 'wfa-builder-node--selected' : '',
					hasUnknownType ? 'wfa-builder-node--unknown' : '',
				]
					.filter(Boolean)
					.join(' ')}
				style={{ transform: 'none', position: 'relative' }}
				role="button"
				tabIndex={0}
				aria-pressed={selected}
				onClick={() => onSelect(node.id)}
				onKeyDown={(event) => {
					if (event.key === 'Enter' || event.key === ' ') {
						event.preventDefault();
						onSelect(node.id);
					}
				}}
			>
				<span
					className="wfa-builder-node__handle wfa-builder-node__handle--input"
					aria-hidden="true"
				/>

				<div
					className="wfa-builder-node__body"
					style={{ minHeight: `${AGENT_BODY_HEIGHT}px` }}
				>
					<span
						className="wfa-builder-node__icon"
						style={{
							backgroundColor: meta.bg,
							color: meta.accent,
						}}
						aria-hidden="true"
					>
						{meta.icon}
					</span>
					<div className="wfa-builder-node__text">
						<span className="wfa-builder-node__label">{node.label}</span>
						<span className="wfa-builder-node__subtitle">
							{__('AI Agent', 'workflow-automate')}
						</span>
					</div>
				</div>

				<div
					className="wfa-agent-node__ports"
					style={{ minHeight: `${AGENT_PORTS_HEIGHT}px` }}
				>
					<div className="wfa-agent-node__port">
						<span className="wfa-agent-node__port-label">
							{__('Chat Model', 'workflow-automate')}
							<span className="wfa-agent-node__required">*</span>
						</span>
						{hasChatModel ? (
							<button
								type="button"
								className="wfa-agent-node__port-dot wfa-agent-node__port-dot--ok wfa-agent-node__port-dot--link"
								title={__('Open chat model settings', 'workflow-automate')}
								aria-label={__(
									'Open chat model settings',
									'workflow-automate'
								)}
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
								className="wfa-agent-node__add-port"
								aria-label={__(
									'Add chat model to agent',
									'workflow-automate'
								)}
								title={__('Select OpenAI, Gemini, or Claude', 'workflow-automate')}
								onClick={(event) => {
									event.stopPropagation();
									onAddChatModel(node.id);
								}}
							>
								+
							</button>
						)}
					</div>

					<div className="wfa-agent-node__port">
						<span className="wfa-agent-node__port-label">
							{__('Memory', 'workflow-automate')}
						</span>
						{hasMemory ? (
							<span
								className="wfa-agent-node__port-dot wfa-agent-node__port-dot--ok"
								title={__('Memory connected', 'workflow-automate')}
							/>
						) : (
							<button
								type="button"
								className="wfa-agent-node__add-port wfa-agent-node__add-port--muted"
								aria-label={__('Add memory to agent', 'workflow-automate')}
								title={__('Add simple memory', 'workflow-automate')}
								onClick={(event) => {
									event.stopPropagation();
									onAddMemory(node.id);
								}}
							>
								+
							</button>
						)}
					</div>

					<div className="wfa-agent-node__port wfa-agent-node__port--tool">
						<span className="wfa-agent-node__port-label">
							{__('Tool', 'workflow-automate')}
						</span>
						<button
							type="button"
							className="wfa-agent-node__add-port"
							aria-label={__('Add tool to agent', 'workflow-automate')}
							title={__(
								'Add Router, Condition, or an action',
								'workflow-automate'
							)}
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
		</div>
	);
}
