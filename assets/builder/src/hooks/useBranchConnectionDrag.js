import { useEffect } from '@wordpress/element';

import { conditionOutputPortPosition, getConditionRows } from '../utils/conditionBranches';

/**
 * @param {HTMLElement|null} canvasElement
 * @return {number}
 */
export function getCanvasZoom(canvasElement) {
	if (!canvasElement) {
		return 1;
	}

	const raw =
		canvasElement.style.getPropertyValue('--dragwyb-af-canvas-zoom') ||
		getComputedStyle(canvasElement).getPropertyValue('--dragwyb-af-canvas-zoom');
	const zoom = parseFloat(raw);

	return zoom > 0 ? zoom : 1;
}

/**
 * @param {HTMLElement|null} canvasElement
 * @param {number}           clientX
 * @param {number}           clientY
 * @return {{ x: number, y: number }}
 */
export function clientToCanvasPoint(canvasElement, clientX, clientY) {
	if (!canvasElement) {
		return { x: clientX, y: clientY };
	}

	const rect = canvasElement.getBoundingClientRect();
	const zoom = getCanvasZoom(canvasElement);

	return {
		x: (clientX - rect.left + canvasElement.scrollLeft) / zoom,
		y: (clientY - rect.top + canvasElement.scrollTop) / zoom,
	};
}

/**
 * @param {number}  clientX
 * @param {number}  clientY
 * @param {Object}  [options]
 * @param {string[]} [options.excludeNodeIds]
 * @return {string|null}
 */
export function nodeIdFromPointer(clientX, clientY, options = {}) {
	const excludeNodeIds = new Set(options.excludeNodeIds || []);
	const elements =
		typeof document.elementsFromPoint === 'function'
			? document.elementsFromPoint(clientX, clientY)
			: [document.elementFromPoint(clientX, clientY)].filter(Boolean);

	for (const element of elements) {
		const nodeElement = element?.closest('[data-node-id]');

		if (!nodeElement) {
			continue;
		}

		const nodeId = nodeElement.getAttribute('data-node-id');

		if (!nodeId || excludeNodeIds.has(nodeId)) {
			continue;
		}

		return nodeId;
	}

	return null;
}

/**
 * @param {HTMLElement}     portElement
 * @param {HTMLElement|null} canvasElement
 * @return {{ x: number, y: number }|null}
 */
export function portPositionFromElement(portElement, canvasElement) {
	if (!portElement || !canvasElement) {
		return null;
	}

	const portRect = portElement.getBoundingClientRect();
	const canvasRect = canvasElement.getBoundingClientRect();
	const zoom = getCanvasZoom(canvasElement);

	return {
		x:
			(portRect.left +
				portRect.width / 2 -
				canvasRect.left +
				canvasElement.scrollLeft) /
			zoom,
		y:
			(portRect.top +
				portRect.height / 2 -
				canvasRect.top +
				canvasElement.scrollTop) /
			zoom,
	};
}

/**
 * @param {Object|null} drag          Active drag state.
 * @param {Function}    onPointerMove ( event ) => void
 * @param {Function}    onPointerUp    ( event ) => void
 */
export function useBranchConnectionDrag(drag, onPointerMove, onPointerUp) {
	useEffect(() => {
		if (!drag) {
			return undefined;
		}

		const handleMove = (event) => {
			onPointerMove(event);
		};

		const handleUp = (event) => {
			onPointerUp(event);
		};

		window.addEventListener('pointermove', handleMove);
		window.addEventListener('pointerup', handleUp);
		window.addEventListener('pointercancel', handleUp);

		return () => {
			window.removeEventListener('pointermove', handleMove);
			window.removeEventListener('pointerup', handleUp);
			window.removeEventListener('pointercancel', handleUp);
		};
	}, [drag, onPointerMove, onPointerUp]);
}

/**
 * @param {Array<Object>} nodes
 * @param {string}        conditionNodeId
 * @param {string}        branchId
 * @return {{ x: number, y: number }|null}
 */
export function branchPortPosition(nodes, conditionNodeId, branchId) {
	const conditionNode = nodes.find((node) => node.id === conditionNodeId);

	if (!conditionNode) {
		return null;
	}

	const rows = getConditionRows(conditionNode.config || {});

	return conditionOutputPortPosition(conditionNode, branchId, rows);
}
