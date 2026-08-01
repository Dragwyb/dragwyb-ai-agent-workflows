import { useRef } from '@wordpress/element';

import { getCanvasZoom } from './useBranchConnectionDrag';

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
 * Pointer + keyboard drag for canvas nodes.
 *
 * @param {Object}   options
 * @param {string}   options.nodeId   Node id passed to onMove.
 * @param {number}   options.x        Current x position.
 * @param {number}   options.y        Current y position.
 * @param {Function} options.onMove   (nodeId, x, y) => void
 * @param {Function} options.onSelect (nodeId) => void
 * @param {boolean}  [options.linkConnectMode] Immediate select (branch linking).
 *
 * @return {{ handlePointerDown: Function, handleKeyDown: Function }}
 */
export function useNodeDrag({ nodeId, x, y, onMove, onSelect, linkConnectMode = false }) {
	const draggingRef = useRef(null);

	const handlePointerDown = (event) => {
		if (linkConnectMode && onSelect) {
			event.stopPropagation();
			onSelect(nodeId);
			return;
		}

		if (!onMove || (event.button !== undefined && event.button !== 0)) {
			return;
		}

		const target = event.currentTarget;
		target.setPointerCapture(event.pointerId);

		draggingRef.current = {
			startX: event.clientX,
			startY: event.clientY,
			originX: x,
			originY: y,
			moved: false,
			zoom: getCanvasZoom(target.closest('.aiawa-builder-canvas')),
		};

		const handlePointerMove = (moveEvent) => {
			const drag = draggingRef.current;
			if (!drag) {
				return;
			}

			const zoom = drag.zoom || 1;
			const dx = (moveEvent.clientX - drag.startX) / zoom;
			const dy = (moveEvent.clientY - drag.startY) / zoom;

			if (
				Math.abs(moveEvent.clientX - drag.startX) > DRAG_THRESHOLD_PX ||
				Math.abs(moveEvent.clientY - drag.startY) > DRAG_THRESHOLD_PX
			) {
				drag.moved = true;
			}

			onMove(
				nodeId,
				Math.max(0, drag.originX + dx),
				Math.max(0, drag.originY + dy)
			);
		};

		const handlePointerUp = (upEvent) => {
			const drag = draggingRef.current;
			draggingRef.current = null;
			target.removeEventListener('pointermove', handlePointerMove);
			target.removeEventListener('pointerup', handlePointerUp);
			target.removeEventListener('pointercancel', handlePointerUp);

			if (drag?.moved) {
				upEvent.stopPropagation();
				return;
			}

			if (drag && onSelect) {
				onSelect(nodeId);
			}
		};

		target.addEventListener('pointermove', handlePointerMove);
		target.addEventListener('pointerup', handlePointerUp);
		target.addEventListener('pointercancel', handlePointerUp);
	};

	const handleKeyDown = (event) => {
		if (!onMove) {
			return;
		}

		if (event.key === 'Enter' || event.key === ' ') {
			event.preventDefault();
			if (onSelect) {
				onSelect(nodeId);
			}
			return;
		}

		const delta = ARROW_DELTAS[event.key];
		if (!delta) {
			return;
		}

		event.preventDefault();
		const step = event.shiftKey ? NUDGE_STEP_LARGE : NUDGE_STEP;
		onMove(
			nodeId,
			Math.max(0, x + delta[0] * step),
			Math.max(0, y + delta[1] * step)
		);
	};

	return { handlePointerDown, handleKeyDown };
}
