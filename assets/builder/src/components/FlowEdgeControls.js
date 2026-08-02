import { __ } from '@wordpress/i18n';

/**
 * Clickable flow/branch wire controls (+ insert step, × delete connection).
 *
 * @param {Object}        props
 * @param {Array<Object>} props.flowEdges
 * @param {Array<Object>} props.branchEdges
 * @param {Object|null}   props.selectedConnection
 * @param {Function}      props.onSelectConnection
 * @param {Function}      props.onDeleteConnection
 * @param {Function}      props.onInsertOnConnection
 */
export default function FlowEdgeControls({
	flowEdges,
	branchEdges,
	selectedConnection,
	onSelectConnection,
	onDeleteConnection,
	onInsertOnConnection,
}) {
	const edges = [...flowEdges, ...branchEdges];

	return (
		<div
			className="aiawa-builder-canvas__edge-interactions"
			aria-hidden={edges.length === 0}
		>
			<svg
				className="aiawa-builder-canvas__edge-hits"
				aria-hidden="true"
				focusable="false"
			>
				{edges.map((edge) => (
					<path
						key={`hit-${edge.id}`}
						className="aiawa-builder-canvas__edge-hit"
						d={edge.path}
						fill="none"
						onClick={(event) => {
							event.stopPropagation();
							onSelectConnection(edge);
						}}
					/>
				))}
			</svg>

			{edges.map((edge) => {
				const midpoint = edge.midpoint;
				const isSelected =
					selectedConnection?.id === edge.id &&
					selectedConnection?.kind === edge.kind;

				if (!isSelected) {
					return null;
				}

				return (
					<div
						key={`toolbar-${edge.id}`}
						className="aiawa-builder-edge-toolbar"
						style={{
							left: `${midpoint.x}px`,
							top: `${midpoint.y}px`,
						}}
						onClick={(event) => event.stopPropagation()}
					>
						<button
							type="button"
							className="aiawa-builder-edge-toolbar__btn aiawa-builder-edge-toolbar__btn--add"
							title={__(
								'Add a step between these nodes',
								'dragwyb-agentflow'
							)}
							aria-label={__(
								'Add a step between these nodes',
								'dragwyb-agentflow'
							)}
							onClick={() => onInsertOnConnection(edge)}
						>
							+
						</button>
						<button
							type="button"
							className="aiawa-builder-edge-toolbar__btn aiawa-builder-edge-toolbar__btn--delete"
							title={__(
								'Delete this connection',
								'dragwyb-agentflow'
							)}
							aria-label={__(
								'Delete this connection',
								'dragwyb-agentflow'
							)}
							onClick={() => onDeleteConnection(edge)}
						>
							{__('×', 'dragwyb-agentflow')}
						</button>
					</div>
				);
			})}
		</div>
	);
}
