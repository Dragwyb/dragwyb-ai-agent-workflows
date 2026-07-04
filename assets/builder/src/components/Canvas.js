import { __ } from '@wordpress/i18n';

import NodeCard from './NodeCard';

/**
 * Renders the graph's nodes as absolutely-positioned cards. Connection
 * drawing between nodes is intentionally out of scope for this shell (see
 * roadmap item 6 notes) — `graph.connections` is preserved untouched so
 * adding that interaction later doesn't require a data migration.
 *
 * @param {Object}   props
 * @param {Array}    props.nodes
 * @param {Array}    props.knownTypeSlugs
 * @param {string}   props.selectedNodeId
 * @param {Function} props.onSelectNode
 * @param {Function} props.onMoveNode
 * @param {Function} props.onCanvasClick
 */
export default function Canvas({
	nodes,
	knownTypeSlugs,
	selectedNodeId,
	onSelectNode,
	onMoveNode,
	onCanvasClick,
}) {
	return (
		// eslint-disable-next-line jsx-a11y/no-static-element-interactions, jsx-a11y/click-events-have-key-events -- deselect-on-background-click is a supplementary mouse affordance; keyboard users deselect via the config panel's close control.
		<div className="wfa-builder-canvas" onClick={onCanvasClick}>
			{nodes.length === 0 && (
				<p className="wfa-builder-canvas__empty">
					{__(
						'Add a trigger or action from the palette to get started.',
						'workflow-automate'
					)}
				</p>
			)}
			{nodes.map((node) => (
				<NodeCard
					key={node.id}
					node={node}
					selected={node.id === selectedNodeId}
					hasUnknownType={!knownTypeSlugs.includes(node.type)}
					onSelect={onSelectNode}
					onMove={onMoveNode}
				/>
			))}
		</div>
	);
}
