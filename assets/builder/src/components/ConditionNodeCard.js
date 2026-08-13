import { useRef } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { getNodeMeta } from '../nodeMeta';
import {
	CONDITION_NODE_WIDTH,
	getConditionRows,
	getConditionNodeHeight,
} from '../utils/conditionBranches';

const DRAG_THRESHOLD_PX = 3;
const NUDGE_STEP = 10;
const NUDGE_STEP_LARGE = 40;

const ARROW_DELTAS = {
	ArrowUp: [0, -1],
	ArrowDown: [0, 1],
	ArrowLeft: [-1, 0],
	ArrowRight: [1, 0],
};

/**
 * Multi-branch Condition node — drag from output port to connect.
 */
export default function ConditionNodeCard({
	node,
	selected,
	hasUnknownType,
	nodesById,
	activeBranchDrag,
	hoverTargetNodeId,
	onSelect,
	onMove,
	onAddCondition,
	onRemoveCondition,
	onStartBranchConnectionDrag,
	onDisconnectBranch,
	registerRef,
}) {
	const draggingRef = useRef(null);
	const meta = getNodeMeta(node.type, node.category);
	const rows = getConditionRows(node.config || {});
	const displayRows = rows.length > 0 ? rows : [null];
	const nodeHeight = getConditionNodeHeight(node);
	const defaultTargetId = node.config?.default_branch_node_id || '';

	const handlePointerDown = (event) => {
		if (event.button !== undefined && event.button !== 0) {
			return;
		}

		const target = event.currentTarget;
		target.setPointerCapture(event.pointerId);

		draggingRef.current = {
			startX: event.clientX,
			startY: event.clientY,
			originX: node.x,
			originY: node.y,
			moved: false,
		};

		const handleMove = (moveEvent) => {
			const drag = draggingRef.current;

			if (!drag) {
				return;
			}

			const dx = moveEvent.clientX - drag.startX;
			const dy = moveEvent.clientY - drag.startY;

			if (
				Math.abs(dx) > DRAG_THRESHOLD_PX ||
				Math.abs(dy) > DRAG_THRESHOLD_PX
			) {
				drag.moved = true;
			}

			onMove(
				node.id,
				Math.max(0, drag.originX + dx),
				Math.max(0, drag.originY + dy)
			);
		};

		const handleUp = () => {
			const drag = draggingRef.current;
			draggingRef.current = null;
			target.removeEventListener('pointermove', handleMove);
			target.removeEventListener('pointerup', handleUp);

			if (drag && !drag.moved) {
				onSelect(node.id);
			}
		};

		target.addEventListener('pointermove', handleMove);
		target.addEventListener('pointerup', handleUp);
	};

	const handleKeyDown = (event) => {
		if (event.key === 'Enter' || event.key === ' ') {
			event.preventDefault();
			onSelect(node.id);
			return;
		}

		const delta = ARROW_DELTAS[event.key];

		if (!delta) {
			return;
		}

		event.preventDefault();
		const step = event.shiftKey ? NUDGE_STEP_LARGE : NUDGE_STEP;
		onMove(
			node.id,
			Math.max(0, node.x + delta[0] * step),
			Math.max(0, node.y + delta[1] * step)
		);
	};

	const stopPointer = (event) => {
		event.stopPropagation();
	};

	const classNames = [
		'daiaw-builder-node',
		'daiaw-builder-node--condition',
		selected ? 'daiaw-builder-node--selected' : '',
		hasUnknownType ? 'daiaw-builder-node--unknown' : '',
	]
		.filter(Boolean)
		.join(' ');

	const renderBranchPort = (branchId, targetId, branchLabel) => {
		const isDragging =
			activeBranchDrag?.conditionNodeId === node.id &&
			activeBranchDrag?.branchId === branchId;
		const targetLabel = targetId
			? nodesById[targetId]?.label || __('Connected', 'dragwyb-ai-agent-workflows')
			: '';

		return (
			<div className="daiaw-condition-node__row-port">
				{targetId && (
					<span className="daiaw-condition-node__link-chip" title={targetLabel}>
						→ {targetLabel}
					</span>
				)}
				{targetId && (
					<Button
						variant="link"
						className="daiaw-condition-node__row-btn daiaw-condition-node__row-btn--danger"
						onPointerDown={stopPointer}
						onClick={(event) => {
							event.stopPropagation();
							onDisconnectBranch(node.id, branchId);
						}}
					>
						{__('×', 'dragwyb-ai-agent-workflows')}
					</Button>
				)}
				<button
					type="button"
					className={
						targetId
							? 'daiaw-condition-node__port-dot daiaw-condition-node__port-dot--connected'
							: isDragging
								? 'daiaw-condition-node__port-dot daiaw-condition-node__port-dot--dragging'
								: 'daiaw-condition-node__port-dot'
					}
					data-branch-id={branchId}
					title={__(
						'Drag this port to any step on the canvas (each condition can connect to a different step)',
						'dragwyb-ai-agent-workflows'
					)}
					aria-label={sprintf(
						/* translators: %s: condition branch label */
						__(
							'Drag to connect branch: %s',
							'dragwyb-ai-agent-workflows'
						),
						branchLabel
					)}
					onPointerDown={(event) => {
						event.preventDefault();
						stopPointer(event);
						onStartBranchConnectionDrag(node.id, branchId, event);
					}}
				/>
			</div>
		);
	};

	const renderBranchRow = (row, index) => {
		if (!row?.id) {
			return null;
		}

		const branchId = row.id;
		const targetId = row.node_id || '';
		const branchLabel = row.label || __('Untitled Condition', 'dragwyb-ai-agent-workflows');

		return (
			<div key={branchId} className="daiaw-condition-node__row">
				<div className="daiaw-condition-node__row-main">
					<span className="daiaw-condition-node__row-index">
						{index + 1}
					</span>
					<span className="daiaw-condition-node__row-label">
						{branchLabel}
					</span>
				</div>
				{row && (
					<div className="daiaw-condition-node__row-tools">
						<Button
							variant="link"
							className="daiaw-condition-node__row-btn"
							onPointerDown={stopPointer}
							onClick={(event) => {
								event.stopPropagation();
								onSelect(node.id);
							}}
						>
							{__('Edit', 'dragwyb-ai-agent-workflows')}
						</Button>
						<Button
							variant="link"
							className="daiaw-condition-node__row-btn daiaw-condition-node__row-btn--danger"
							onPointerDown={stopPointer}
							onClick={(event) => {
								event.stopPropagation();
								onRemoveCondition(node.id, row.id);
							}}
						>
							{__('Remove', 'dragwyb-ai-agent-workflows')}
						</Button>
					</div>
				)}
				{renderBranchPort(branchId, targetId, branchLabel)}
			</div>
		);
	};

	return (
		<div
			ref={(element) => {
				if (registerRef) {
					registerRef(node.id, element);
				}
			}}
			className={classNames}
			style={{
				transform: `translate(${node.x}px, ${node.y}px)`,
				width: `${CONDITION_NODE_WIDTH}px`,
				minHeight: `${nodeHeight}px`,
			}}
			data-node-id={node.id}
			role="button"
			tabIndex={0}
			aria-pressed={selected}
			onPointerDown={handlePointerDown}
			onKeyDown={handleKeyDown}
		>
			<span
				className="daiaw-condition-node__input-dot"
				aria-hidden="true"
			/>

			<div className="daiaw-condition-node__card">
				<div className="daiaw-condition-node__header">
					<span
						className="daiaw-condition-node__icon"
						style={{
							backgroundColor: meta.bg,
							color: meta.accent,
						}}
						aria-hidden="true"
					>
						{meta.icon}
					</span>
					<div className="daiaw-condition-node__header-text">
						<span className="daiaw-condition-node__title">
							{__('Condition', 'dragwyb-ai-agent-workflows')}
						</span>
						<span className="daiaw-condition-node__subtitle">
							{__(
								'Each orange port connects to a different step — drag one port per branch',
								'dragwyb-ai-agent-workflows'
							)}
						</span>
					</div>
				</div>

				<div className="daiaw-condition-node__body">
					{displayRows.map((row, index) => (
						<div key={row?.id || `row-${index}`}>
							{renderBranchRow(row, index)}
							{index < displayRows.length - 1 && (
								<div className="daiaw-condition-node__add-between">
									<span
										className="daiaw-condition-node__add-line"
										aria-hidden="true"
									/>
									<button
										type="button"
										className="daiaw-condition-node__add-btn"
										onPointerDown={stopPointer}
										onClick={(event) => {
											event.stopPropagation();
											onAddCondition(node.id, index + 1);
										}}
										title={__('Add condition', 'dragwyb-ai-agent-workflows')}
										aria-label={__(
											'Add condition',
											'dragwyb-ai-agent-workflows'
										)}
									>
										+
									</button>
									<span
										className="daiaw-condition-node__add-line"
										aria-hidden="true"
									/>
								</div>
							)}
						</div>
					))}

					<div className="daiaw-condition-node__add-between">
						<span
							className="daiaw-condition-node__add-line"
							aria-hidden="true"
						/>
						<button
							type="button"
							className="daiaw-condition-node__add-btn"
							onPointerDown={stopPointer}
							onClick={(event) => {
								event.stopPropagation();
								onAddCondition(node.id, rows.length);
							}}
							title={__('Add condition', 'dragwyb-ai-agent-workflows')}
							aria-label={__('Add condition', 'dragwyb-ai-agent-workflows')}
						>
							+
						</button>
						<span
							className="daiaw-condition-node__add-line"
							aria-hidden="true"
						/>
					</div>

					<div className="daiaw-condition-node__row daiaw-condition-node__row--default">
						<div className="daiaw-condition-node__row-main">
							<span className="daiaw-condition-node__row-index">∅</span>
							<span className="daiaw-condition-node__row-label">
								{__('No Condition Matched', 'dragwyb-ai-agent-workflows')}
							</span>
						</div>
						{renderBranchPort(
							'default',
							defaultTargetId,
							__('No Condition Matched', 'dragwyb-ai-agent-workflows')
						)}
					</div>
				</div>
			</div>
		</div>
	);
}
