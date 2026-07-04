import { useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

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
 * A single node on the canvas. Supports pointer dragging (click-and-drag to
 * reposition, plain click to select) and keyboard operation (Tab to focus,
 * Enter/Space to select, arrow keys to nudge) so the canvas doesn't require
 * a mouse to add, select, or delete nodes — deleting happens from the
 * config panel once a node is selected.
 *
 * @param {Object}   props
 * @param {Object}   props.node
 * @param {boolean}  props.selected
 * @param {boolean}  props.hasUnknownType
 * @param {Function} props.onSelect
 * @param {Function} props.onMove
 * @param {Function} [props.registerRef]
 */
export default function NodeCard({
	node,
	selected,
	hasUnknownType,
	onSelect,
	onMove,
	registerRef,
}) {
	const draggingRef = useRef(null);

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

	const classNames = ['wfa-builder-node'];
	if (selected) {
		classNames.push('wfa-builder-node--selected');
	}
	if (hasUnknownType) {
		classNames.push('wfa-builder-node--unknown');
	}

	const ariaLabel = [
		node.label || node.type,
		node.category === 'trigger'
			? __('Trigger', 'workflow-automate')
			: __('Action', 'workflow-automate'),
		selected ? __('selected', 'workflow-automate') : '',
	]
		.filter(Boolean)
		.join(', ');

	return (
		<div
			ref={(element) => {
				if (registerRef) {
					registerRef(node.id, element);
				}
			}}
			className={classNames.join(' ')}
			style={{ transform: `translate(${node.x}px, ${node.y}px)` }}
			role="button"
			tabIndex={0}
			aria-pressed={selected}
			aria-label={ariaLabel}
			data-node-id={node.id}
			onPointerDown={handlePointerDown}
			onKeyDown={handleKeyDown}
		>
			<span className="wfa-builder-node__label" aria-hidden="true">
				{node.label}
			</span>
			<span className="wfa-builder-node__type" aria-hidden="true">
				{node.type}
			</span>
		</div>
	);
}
