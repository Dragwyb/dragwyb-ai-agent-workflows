import { __ } from '@wordpress/i18n';

import NodeCard from './NodeCard';
import {
	NODE_WIDTH,
	NODE_HEIGHT,
	sortNodesForFlow,
} from '../utils';

/**
 * Renders the graph's nodes as absolutely-positioned cards with SVG
 * connectors between them in visual flow order.
 *
 * @param {Object}   props
 * @param {Array}    props.nodes
 * @param {Array}    props.knownTypeSlugs
 * @param {string}   props.selectedNodeId
 * @param {Function} props.onSelectNode
 * @param {Function} props.onMoveNode
 * @param {Function} props.onCanvasClick
 * @param {Function} props.registerNodeRef Optional (nodeId, element) => void for focus management.
 */
export default function Canvas({
	nodes,
	knownTypeSlugs,
	selectedNodeId,
	onSelectNode,
	onMoveNode,
	onCanvasClick,
	registerNodeRef,
}) {
	const flowNodes = sortNodesForFlow(nodes);

	return (
		// eslint-disable-next-line jsx-a11y/no-static-element-interactions, jsx-a11y/click-events-have-key-events, jsx-a11y/no-noninteractive-element-interactions -- deselect-on-background-click is a supplementary mouse affordance; keyboard users deselect via Escape or the config panel's close control.
		<div
			className="wfa-builder-canvas"
			role="region"
			aria-label={__('Workflow canvas', 'workflow-automate')}
			onClick={onCanvasClick}
		>
			{nodes.length > 1 && (
				<svg
					className="wfa-builder-canvas__edges"
					aria-hidden="true"
					focusable="false"
				>
					{flowNodes.slice(0, -1).map((node, index) => {
						const next = flowNodes[index + 1];
						const x1 = node.x + NODE_WIDTH / 2;
						const y1 = node.y + NODE_HEIGHT;
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
				</svg>
			)}

			{nodes.length === 0 && <EmptyCanvasGuide />}
			{nodes.map((node) => (
				<NodeCard
					key={node.id}
					node={node}
					selected={node.id === selectedNodeId}
					hasUnknownType={!knownTypeSlugs.includes(node.type)}
					onSelect={onSelectNode}
					onMove={onMoveNode}
					registerRef={registerNodeRef}
				/>
			))}
		</div>
	);
}

/**
 * Guided empty state for a brand-new workflow (roadmap item 16).
 */
function EmptyCanvasGuide() {
	return (
		<div className="wfa-builder-canvas__guide" role="status">
			<h2 className="wfa-builder-canvas__guide-title">
				{__('Build your workflow', 'workflow-automate')}
			</h2>
			<ol className="wfa-builder-canvas__guide-steps">
				<li>
					{__(
						'Add a trigger from the palette on the left (what starts the run).',
						'workflow-automate'
					)}
				</li>
				<li>
					{__(
						'Add one or more actions (what the workflow should do).',
						'workflow-automate'
					)}
				</li>
				<li>
					{__(
						'Select each node to configure it, then save. Activate the workflow from the Workflows list when you are ready.',
						'workflow-automate'
					)}
				</li>
			</ol>
			<p className="wfa-builder-canvas__guide-hint">
				{__(
					'Tip: use Tab to move between nodes, Enter to select, and arrow keys to nudge a selected node.',
					'workflow-automate'
				)}
			</p>
		</div>
	);
}
