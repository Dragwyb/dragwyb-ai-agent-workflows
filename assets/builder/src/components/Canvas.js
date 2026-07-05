import { __ } from '@wordpress/i18n';

import NodeCard from './NodeCard';

import AgentNodeCard from './AgentNodeCard';

import ToolNodeCard from './ToolNodeCard';

import ChatModelSubNode from './ChatModelSubNode';

import MemorySubNode from './MemorySubNode';

import { NODE_WIDTH, NODE_HEIGHT, sortNodesForFlow } from '../utils';

import {
	isAgentNode,
	mainCanvasNodes,
	toolsForAgent,
	chatModelForAgent,
	memoryForAgent,
	agentToolPortPosition,
	agentChatModelPortPosition,
	agentMemoryPortPosition,
	toolInputPortPosition,
	chatModelInputPortPosition,
	memoryInputPortPosition,
	AGENT_TOTAL_HEIGHT,
	isToolAttachment,
	isChatModelAttachment,
	isMemoryAttachment,
} from '../utils/agentAttachments';

/**
 
 * @param {{ x: number, y: number }} from
 
 * @param {{ x: number, y: number }} to
 
 * @return {string}
 
 */

function dashedPath(from, to) {
	const midY = from.y + (to.y - from.y) / 2;

	return `M ${from.x} ${from.y} C ${from.x} ${midY}, ${to.x} ${midY}, ${to.x} ${to.y}`;
}

export default function Canvas({
	nodes,

	knownTypeSlugs,

	selectedNodeId,

	onSelectNode,

	onMoveNode,

	onAddAgentChatModel,

	onAddAgentMemory,

	onAddAgentTool,

	onCanvasClick,

	registerNodeRef,
}) {
	const canvasNodes = mainCanvasNodes(nodes);

	const flowNodes = sortNodesForFlow(canvasNodes);

	const attachmentEdges = [];

	canvasNodes.forEach((node) => {
		if (!isAgentNode(node)) {
			return;
		}

		const chatModel = chatModelForAgent(nodes, node.id);

		if (chatModel) {
			attachmentEdges.push({
				id: `attach-model-${node.id}-${chatModel.id}`,

				from: agentChatModelPortPosition(node),

				to: chatModelInputPortPosition(chatModel),
			});
		}

		const memory = memoryForAgent(nodes, node.id);

		if (memory) {
			attachmentEdges.push({
				id: `attach-memory-${node.id}-${memory.id}`,

				from: agentMemoryPortPosition(node),

				to: memoryInputPortPosition(memory),
			});
		}

		const tools = toolsForAgent(nodes, node.id);

		const from = agentToolPortPosition(node);

		tools.forEach((tool) => {
			attachmentEdges.push({
				id: `attach-tool-${node.id}-${tool.id}`,

				from,

				to: toolInputPortPosition(tool),
			});
		});
	});

	return (
		<div
			className="wfa-builder-canvas"

			role="region"

			aria-label={__('Workflow canvas', 'workflow-automate')}

			onClick={onCanvasClick}
		>
			{(flowNodes.length > 1 || attachmentEdges.length > 0) && (
				<svg
					className="wfa-builder-canvas__edges"

					aria-hidden="true"

					focusable="false"
				>
					{flowNodes.slice(0, -1).map((node, index) => {
						const next = flowNodes[index + 1];

						const x1 = node.x + NODE_WIDTH / 2;

						const y1 =
							node.y +
							(isAgentNode(node)
								? AGENT_TOTAL_HEIGHT
								: NODE_HEIGHT);

						const x2 = next.x + NODE_WIDTH / 2;

						const y2 = next.y;

						const midY = y1 + (y2 - y1) / 2;

						const path = `M ${x1} ${y1} C ${x1} ${midY}, ${x2} ${midY}, ${x2} ${y2}`;

						const isSelected =
							node.id === selectedNodeId ||
							next.id === selectedNodeId;

						return (
							<path
								key={`${node.id}-${next.id}`}

								className={
									isSelected
										? 'wfa-builder-canvas__edge wfa-builder-canvas__edge--selected'
										: 'wfa-builder-canvas__edge'
								}

								d={path}

								fill="none"
							/>
						);
					})}

					{attachmentEdges.map((edge) => (
						<path
							key={edge.id}

							className="wfa-builder-canvas__edge wfa-builder-canvas__edge--attachment"

							d={dashedPath(edge.from, edge.to)}

							fill="none"
						/>
					))}
				</svg>
			)}

			{nodes.length === 0 && <EmptyCanvasGuide />}

			{canvasNodes.map((node) => {
				if (isAgentNode(node)) {
					const chatModel = chatModelForAgent(nodes, node.id);

					const memory = memoryForAgent(nodes, node.id);

					return (
						<AgentNodeCard
							key={node.id}

							node={node}

							selected={node.id === selectedNodeId}

							hasUnknownType={!knownTypeSlugs.includes(node.type)}

							hasChatModel={Boolean(chatModel)}

							hasMemory={Boolean(memory)}

							chatModelId={chatModel?.id || null}

							onSelect={onSelectNode}

							onAddChatModel={onAddAgentChatModel}

							onAddMemory={onAddAgentMemory}

							onAddTool={onAddAgentTool}

							registerRef={registerNodeRef}
						/>
					);
				}

				return (
					<NodeCard
						key={node.id}

						node={node}

						selected={node.id === selectedNodeId}

						hasUnknownType={!knownTypeSlugs.includes(node.type)}

						onSelect={onSelectNode}

						onMove={onMoveNode}

						registerRef={registerNodeRef}
					/>
				);
			})}

			{nodes

				.filter(
					(node) =>
						isChatModelAttachment(node) && node.parent_agent_id
				)

				.map((chatModel) => (
					<div
						key={chatModel.id}

						className="wfa-chat-model-node-wrap"

						style={{
							transform: `translate(${chatModel.x}px, ${chatModel.y}px)`,
						}}
					>
						<ChatModelSubNode
							node={chatModel}

							selected={chatModel.id === selectedNodeId}

							onSelect={onSelectNode}
						/>
					</div>
				))}

			{nodes

				.filter(
					(node) => isMemoryAttachment(node) && node.parent_agent_id
				)

				.map((memory) => (
					<div
						key={memory.id}

						className="wfa-memory-node-wrap"

						style={{
							transform: `translate(${memory.x}px, ${memory.y}px)`,
						}}
					>
						<MemorySubNode
							node={memory}

							selected={memory.id === selectedNodeId}

							onSelect={onSelectNode}
						/>
					</div>
				))}

			{nodes

				.filter(
					(node) => isToolAttachment(node) && node.parent_agent_id
				)

				.map((tool) => (
					<div
						key={tool.id}

						className="wfa-tool-node-wrap"

						style={{
							transform: `translate(${tool.x}px, ${tool.y}px)`,
						}}
					>
						<ToolNodeCard
							node={tool}

							selected={tool.id === selectedNodeId}

							onSelect={onSelectNode}
						/>
					</div>
				))}
		</div>
	);
}

function EmptyCanvasGuide() {
	return (
		<div className="wfa-builder-canvas__guide" role="status">
			<h2 className="wfa-builder-canvas__guide-title">
				{__('Build your workflow', 'workflow-automate')}
			</h2>

			<ol className="wfa-builder-canvas__guide-steps">
				<li>
					{__(
						'Add a trigger, then add an AI Agent from the Agents section.',

						'workflow-automate'
					)}
				</li>

				<li>
					{__(
						'Click + under Chat Model to pick OpenAI, Gemini, or Claude.',

						'workflow-automate'
					)}
				</li>

				<li>
					{__(
						'Click + under Tool to attach Router, Condition, or actions.',

						'workflow-automate'
					)}
				</li>
			</ol>
		</div>
	);
}
