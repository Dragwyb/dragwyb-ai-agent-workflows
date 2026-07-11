import { __ } from '@wordpress/i18n';

import NodeCard from './NodeCard';

import AgentNodeCard from './AgentNodeCard';

import ToolNodeCard from './ToolNodeCard';

import ChatModelSubNode from './ChatModelSubNode';

import MemorySubNode from './MemorySubNode';

import ConditionNodeCard from './ConditionNodeCard';

import FlowEdgeControls from './FlowEdgeControls';

import {
	conditionOutputPortPosition,
	getConditionRows,
	branchTargetInputPosition,
} from '../utils/conditionBranches';
import {
	nodeInputPortPosition,
	nodeOutputPortPosition,
	canStartFlowConnection,
	flowPathMidpoint,
} from '../utils/flowConnections';

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

function branchPath(from, to) {
	const midX = from.x + (to.x - from.x) * 0.5;

	return `M ${from.x} ${from.y} C ${midX} ${from.y}, ${midX} ${to.y}, ${to.x} ${to.y}`;
}

function flowPath(from, to) {
	const midY = from.y + (to.y - from.y) / 2;

	return `M ${from.x} ${from.y} C ${from.x} ${midY}, ${to.x} ${midY}, ${to.x} ${to.y}`;
}

export default function Canvas({
	nodes,

	connections = [],

	knownTypeSlugs,

	selectedNodeId,

	connectionDrag,

	isValidBranchDropTarget,

	isValidFlowDropTarget,

	onRegisterCanvas,

	onSelectNode,

	onMoveNode,

	onAddAgentChatModel,

	onAddAgentMemory,

	onAddAgentTool,

	onAddCondition,

	onRemoveCondition,

	onStartBranchConnectionDrag,

	onStartFlowConnectionDrag,

	onDisconnectBranch,

	selectedConnection,

	onSelectConnection,

	onDeleteConnection,

	onInsertOnConnection,

	onCanvasClick,

	registerNodeRef,
}) {
	const canvasNodes = mainCanvasNodes(nodes);

	const nodesById = Object.fromEntries(canvasNodes.map((node) => [node.id, node]));

	const isDropTarget = (node) => {
		if (!connectionDrag || !connectionDrag.hoverTargetNodeId) {
			return false;
		}

		if (node.id !== connectionDrag.hoverTargetNodeId) {
			return false;
		}

		if (connectionDrag.kind === 'branch') {
			return isValidBranchDropTarget(
				node.id,
				connectionDrag.conditionNodeId
			);
		}

		return isValidFlowDropTarget(node.id, connectionDrag.fromNodeId);
	};

	const flowEdges = (connections || [])
		.map((connection) => {
			const fromNode = nodesById[connection.from];
			const toNode = nodesById[connection.to];

			if (!fromNode || !toNode) {
				return null;
			}

			return {
				id: connection.id || `${connection.from}-${connection.to}`,
				kind: 'flow',
				from: nodeOutputPortPosition(fromNode),
				to: nodeInputPortPosition(toNode),
				path: flowPath(
					nodeOutputPortPosition(fromNode),
					nodeInputPortPosition(toNode)
				),
				midpoint: flowPathMidpoint(
					nodeOutputPortPosition(fromNode),
					nodeInputPortPosition(toNode)
				),
				sourceId: fromNode.id,
				targetId: toNode.id,
				fromNodeId: connection.from,
				toNodeId: connection.to,
			};
		})
		.filter(Boolean);

	const attachmentEdges = [];
	const branchEdges = [];

	canvasNodes.forEach((node) => {
		if (node.type !== 'condition_action') {
			return;
		}

		const rows = getConditionRows(node.config || {});

		rows.forEach((row) => {
			if (!row.node_id || !nodesById[row.node_id]) {
				return;
			}

			branchEdges.push({
				id: `branch-${node.id}-${row.id}`,
				kind: 'branch',
				conditionNodeId: node.id,
				branchId: row.id,
				targetNodeId: row.node_id,
				from: conditionOutputPortPosition(node, row.id, rows),
				to: branchTargetInputPosition(nodesById[row.node_id]),
				path: branchPath(
					conditionOutputPortPosition(node, row.id, rows),
					branchTargetInputPosition(nodesById[row.node_id])
				),
				midpoint: {
					x:
						(conditionOutputPortPosition(node, row.id, rows).x +
							branchTargetInputPosition(nodesById[row.node_id]).x) /
						2,
					y:
						(conditionOutputPortPosition(node, row.id, rows).y +
							branchTargetInputPosition(nodesById[row.node_id]).y) /
						2,
				},
			});
		});

		const defaultId = node.config?.default_branch_node_id;

		if (defaultId && nodesById[defaultId]) {
			branchEdges.push({
				id: `branch-${node.id}-default`,
				kind: 'branch',
				conditionNodeId: node.id,
				branchId: 'default',
				targetNodeId: defaultId,
				from: conditionOutputPortPosition(node, 'default', rows),
				to: branchTargetInputPosition(nodesById[defaultId]),
				path: branchPath(
					conditionOutputPortPosition(node, 'default', rows),
					branchTargetInputPosition(nodesById[defaultId])
				),
				midpoint: {
					x:
						(conditionOutputPortPosition(node, 'default', rows).x +
							branchTargetInputPosition(nodesById[defaultId]).x) /
						2,
					y:
						(conditionOutputPortPosition(node, 'default', rows).y +
							branchTargetInputPosition(nodesById[defaultId]).y) /
						2,
				},
			});
		}
	});

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
			ref={onRegisterCanvas}
			className={
				connectionDrag
					? 'wfa-builder-canvas wfa-builder-canvas--connecting'
					: 'wfa-builder-canvas'
			}

			role="region"

			aria-label={__('Workflow canvas', 'workflow-automate')}

			onClick={onCanvasClick}
		>
			{(flowEdges.length > 0 ||
				attachmentEdges.length > 0 ||
				branchEdges.length > 0 ||
				connectionDrag) && (
				<svg
					className="wfa-builder-canvas__edges"

					aria-hidden="true"

					focusable="false"
				>
					{flowEdges.map((edge) => {
						const isSelected =
							edge.sourceId === selectedNodeId ||
							edge.targetId === selectedNodeId ||
							(selectedConnection?.id === edge.id &&
								selectedConnection?.kind === 'flow');

						return (
							<path
								key={edge.id}
								className={
									isSelected
										? 'wfa-builder-canvas__edge wfa-builder-canvas__edge--selected'
										: 'wfa-builder-canvas__edge'
								}
								d={edge.path}
								fill="none"
							/>
						);
					})}

					{branchEdges.map((edge) => {
						const isSelected =
							selectedConnection?.id === edge.id &&
							selectedConnection?.kind === 'branch';

						return (
							<path
								key={edge.id}
								className={
									isSelected
										? 'wfa-builder-canvas__edge wfa-builder-canvas__edge--branch wfa-builder-canvas__edge--selected'
										: 'wfa-builder-canvas__edge wfa-builder-canvas__edge--branch'
								}
								d={edge.path}
								fill="none"
							/>
						);
					})}

					{connectionDrag && (
						<path
							className={
								connectionDrag.kind === 'branch'
									? 'wfa-builder-canvas__edge wfa-builder-canvas__edge--branch wfa-builder-canvas__edge--preview'
									: 'wfa-builder-canvas__edge wfa-builder-canvas__edge--preview'
							}
							d={
								connectionDrag.kind === 'branch'
									? branchPath(
											connectionDrag.from,
											connectionDrag.pointer
										)
									: flowPath(
											connectionDrag.from,
											connectionDrag.pointer
										)
							}
							fill="none"
						/>
					)}

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

			<FlowEdgeControls
				flowEdges={flowEdges}
				branchEdges={branchEdges}
				selectedConnection={selectedConnection}
				onSelectConnection={onSelectConnection}
				onDeleteConnection={onDeleteConnection}
				onInsertOnConnection={onInsertOnConnection}
			/>

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
							isLinkTarget={isDropTarget(node)}
							hasUnknownType={!knownTypeSlugs.includes(node.type)}
							hasChatModel={Boolean(chatModel)}
							hasMemory={Boolean(memory)}
							chatModelId={chatModel?.id || null}
							onSelect={onSelectNode}
							onMove={onMoveNode}
							onAddChatModel={onAddAgentChatModel}
							onAddMemory={onAddAgentMemory}
							onAddTool={onAddAgentTool}
							canStartFlowConnection={canStartFlowConnection(node)}
							onStartFlowConnectionDrag={onStartFlowConnectionDrag}
							registerRef={registerNodeRef}
						/>
					);
				}

				if (node.type === 'condition_action') {
					return (
						<ConditionNodeCard
							key={node.id}
							node={node}
							selected={node.id === selectedNodeId}
							hasUnknownType={!knownTypeSlugs.includes(node.type)}
							nodesById={nodesById}
							activeBranchDrag={
								connectionDrag?.kind === 'branch'
									? connectionDrag
									: null
							}
							hoverTargetNodeId={connectionDrag?.hoverTargetNodeId}
							onSelect={onSelectNode}
							onMove={onMoveNode}
							onAddCondition={onAddCondition}
							onRemoveCondition={onRemoveCondition}
							onStartBranchConnectionDrag={onStartBranchConnectionDrag}
							onDisconnectBranch={onDisconnectBranch}
							registerRef={registerNodeRef}
						/>
					);
				}

				return (
					<NodeCard
						key={node.id}

						node={node}

						selected={node.id === selectedNodeId}

						isLinkTarget={isDropTarget(node)}

						hasUnknownType={!knownTypeSlugs.includes(node.type)}

						canStartFlowConnection={canStartFlowConnection(node)}

						onSelect={onSelectNode}

						onMove={onMoveNode}

						onStartFlowConnectionDrag={onStartFlowConnectionDrag}

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
							onMove={onMoveNode}
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
							onMove={onMoveNode}
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
							onMove={onMoveNode}
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
						'Add Condition from Tools, then use + on each branch to connect different flows.',
						'workflow-automate'
					)}
				</li>
			</ol>
		</div>
	);
}
