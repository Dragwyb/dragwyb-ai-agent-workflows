import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import Header from './components/Header';
import Palette from './components/Palette';
import Canvas from './components/Canvas';
import ConfigPanel from './components/ConfigPanel';
import PickerSidebar from './components/PickerSidebar';
import ChatPanel from './components/ChatPanel';
import useTestFlow from './hooks/useTestFlow';
import {
	useBranchConnectionDrag,
	clientToCanvasPoint,
	nodeIdFromPointer,
	branchPortPosition,
	portPositionFromElement,
} from './hooks/useBranchConnectionDrag';
import { defaultConfigFor } from './nodeCatalog';
import {
	fetchWorkflow,
	createWorkflow,
	updateWorkflow,
	fetchNodeTypes,
	fetchConnections,
	getBootstrap,
	fetchTestStatus,
	clearTestSample,
	sendWorkflowChat,
} from './api';
import {
	generateNodeId,
	emptyGraph,
	sortNodesForFlow,
} from './utils';
import {
	buildExportPayload,
	downloadWorkflowJson,
	exportFilenameFromTitle,
	parseImportJson,
	readFileAsText,
} from './utils/workflowJson';
import {
	createEmptyConditionRow,
	defaultBranchNodePosition,
	getConditionRows,
	setConditionBranchTarget,
	clearConditionBranchTarget,
	conditionOutputPortPosition,
	CONDITION_NODE_WIDTH,
} from './utils/conditionBranches';
import {
	canConnectFlowNodes,
	inferLegacyFlowConnections,
	placementForNewNode,
	nodeOutputPortPosition,
	removeConnectionsForNode,
	removeFlowConnection,
	insertNodeBetweenFlow,
	positionBetweenNodes,
	setFlowConnection,
} from './utils/flowConnections';
import {
	removeAgentAttachments,
	toolAttachmentPosition,
	chatModelAttachmentPosition,
	memoryAttachmentPosition,
	fallbackChatModelAttachmentPosition,
	outputParserAttachmentPosition,
	parserChatModelAttachmentPosition,
	toolsForAgent,
	syncAgentConfigFromChatModel,
	providerFromChatModelSlug,
	DEFAULT_MODEL_BY_PROVIDER,
	AI_AGENT_SLUG,
} from './utils/agentAttachments';
import {
	capturedSampleFromStatus,
	sampleMatchesTrigger,
} from './utils/testSample';

const AUTOSAVE_DELAY_MS = 1500;

/**
 * Normalizes whatever `graph` came back from the REST API into the shape
 * this app expects, so a hand-edited or pre-item-6 empty JSON blob can't
 * crash the app.
 *
 * @param {*} graph Raw graph value from the workflow resource.
 * @return {{nodes: Array<Object>, connections: Array<Object>}} A safe graph.
 */
function normalizeGraph(graph) {
	if (!graph || typeof graph !== 'object') {
		return emptyGraph();
	}

	const nodes = Array.isArray(graph.nodes) ? [...graph.nodes] : [];
	const mainNodes = nodes.filter((node) => !node.parent_agent_id);
	let connections = Array.isArray(graph.connections)
		? [...graph.connections]
		: [];

	if (connections.length === 0 && mainNodes.length > 1) {
		connections = inferLegacyFlowConnections(mainNodes);
	}

	const triggerNodes = nodes.filter((node) => node.category === 'trigger');

	// A workflow may only have one trigger — keep the topmost if legacy data has more.
	if (triggerNodes.length > 1) {
		const keepId = sortNodesForFlow(triggerNodes)[0].id;

		return {
			nodes: nodes.filter(
				(node) => node.category !== 'trigger' || node.id === keepId
			),
			connections,
		};
	}

	return {
		nodes,
		connections,
	};
}

export default function App() {
	const bootstrap = getBootstrap();

	const [workflowId, setWorkflowId] = useState(bootstrap.workflowId || 0);
	const [title, setTitle] = useState('');
	const [graph, setGraph] = useState(emptyGraph());
	const [nodeTypes, setNodeTypes] = useState({ triggers: [], actions: [] });
	const [connections, setConnections] = useState([]);
	const [selectedNodeId, setSelectedNodeId] = useState(null);
	const [loading, setLoading] = useState(true);
	const [loadError, setLoadError] = useState('');
	const [saveStatus, setSaveStatus] = useState('idle');
	// 0 = draft, 1 = active, 2 = paused (matches Workflow::STATUS_* / REST).
	const [workflowStatus, setWorkflowStatus] = useState(0);
	const [toggleActiveBusy, setToggleActiveBusy] = useState(false);
	const [picker, setPicker] = useState(null);
	const [connectionDrag, setConnectionDrag] = useState(null);
	const [selectedConnection, setSelectedConnection] = useState(null);
	const [capturedPayload, setCapturedPayload] = useState(null);
	const [capturedAt, setCapturedAt] = useState(null);
	const [nodeOutputSamples, setNodeOutputSamples] = useState({});
	const [chatOpen, setChatOpen] = useState(false);
	const [chatMessages, setChatMessages] = useState([]);
	const [chatSessionId, setChatSessionId] = useState('');
	const [chatSending, setChatSending] = useState(false);
	const [chatError, setChatError] = useState('');

	// Autosave fires from a setTimeout created by a stable useCallback, so it
	// needs a way to read state as of when it actually runs rather than as
	// of when it was scheduled — a plain ref mirror avoids stale closures
	// without having to recreate the timeout on every keystroke.
	const latestRef = useRef({
		title: '',
		graph: emptyGraph(),
		workflowId: 0,
		selectedNodeId: null,
	});
	const skipNextAutosaveRef = useRef(false);
	const autosaveTimeoutRef = useRef(null);
	const nodeElementsRef = useRef({});
	const focusNodeIdRef = useRef(null);
	const canvasRef = useRef(null);

	useEffect(() => {
		latestRef.current = { title, graph, workflowId, selectedNodeId };
	}, [title, graph, workflowId, selectedNodeId]);

	// Escape clears the selection so keyboard users are not stuck in the
	// config panel (roadmap item 16 accessibility pass).
	useEffect(() => {
		const onKeyDown = (event) => {
			if (event.key === 'Escape') {
				if (connectionDrag) {
					setConnectionDrag(null);
					return;
				}

				if (selectedConnection) {
					setSelectedConnection(null);
					return;
				}

				if (picker) {
					setPicker(null);
					return;
				}

				setSelectedNodeId(null);
			}
		};

		window.addEventListener('keydown', onKeyDown);

		return () => {
			window.removeEventListener('keydown', onKeyDown);
		};
	}, [picker, connectionDrag, selectedConnection]);

	// After adding a node, move focus to its card so keyboard users land
	// on the new element without hunting for it.
	useEffect(() => {
		const nodeId = focusNodeIdRef.current;

		if (!nodeId) {
			return;
		}

		focusNodeIdRef.current = null;
		const element = nodeElementsRef.current[nodeId];

		if (element && typeof element.focus === 'function') {
			element.focus();
		}
	}, [graph.nodes]);

	useEffect(() => {
		let cancelled = false;

		async function load() {
			try {
				const typesPromise = fetchNodeTypes();
				// A failure here shouldn't block loading the workflow
				// itself — worst case the "connection" picker field just
				// renders with no options besides "None" instead of the
				// whole builder failing to load.
				const connectionsPromise = fetchConnections().catch(() => []);
				let workflow;

				if (bootstrap.workflowId) {
					workflow = await fetchWorkflow(bootstrap.workflowId);
				} else {
					workflow = await createWorkflow({
						title: __('Untitled workflow', 'dragwyb-agentflow'),
						graph: emptyGraph(),
					});

					if (!cancelled) {
						setWorkflowId(workflow.id);

						// Replace, don't push: a refresh of this URL must re-open the
						// same workflow, never silently create another new one.
						const url = new URL(window.location.href);
						url.searchParams.set('workflow', String(workflow.id));
						window.history.replaceState({}, '', url.toString());
					}
				}

				const types = await typesPromise;
				const fetchedConnections = await connectionsPromise;

				if (cancelled) {
					return;
				}

				setTitle(workflow.title || '');
				setWorkflowStatus(
					typeof workflow.status === 'number' ? workflow.status : 0
				);
				setGraph(normalizeGraph(workflow.graph));
				setNodeTypes({
					triggers: types.triggers || [],
					actions: types.actions || [],
				});
				setConnections(
					Array.isArray(fetchedConnections) ? fetchedConnections : []
				);

				const urlParams = new URLSearchParams(window.location.search);
				const oauthConnectionId = Number(
					urlParams.get('aiawa_connection') || 0
				);
				const oauthNodeId = urlParams.get('aiawa_node') || '';

				if (oauthConnectionId > 0 && oauthNodeId) {
					setGraph((previous) => ({
						...previous,
						nodes: previous.nodes.map((node) =>
							node.id === oauthNodeId
								? {
										...node,
										config: {
											...(node.config || {}),
											connection_id: oauthConnectionId,
										},
									}
								: node
						),
					}));
					setSelectedNodeId(oauthNodeId);

					urlParams.delete('aiawa_connection');
					urlParams.delete('aiawa_node');
					urlParams.delete('aiawa_notice');
					urlParams.delete('aiawa_error');

					const cleaned = `${window.location.pathname}?${urlParams.toString()}`;
					window.history.replaceState(
						{},
						'',
						cleaned.endsWith('?') ? cleaned.slice(0, -1) : cleaned
					);
				}

				const settings = workflow.settings || {};
				const loadedGraph = normalizeGraph(workflow.graph);
				const loadedTrigger = loadedGraph.nodes.find(
					(node) => node.category === 'trigger'
				);
				const initialSample = capturedSampleFromStatus(
					{
						has_sample: Boolean(settings.sample_payload),
						sample_payload: settings.sample_payload,
						sample_payload_trigger_type:
							settings.sample_payload_trigger_type,
						captured_at: settings.sample_payload_captured_at,
					},
					loadedTrigger?.type || null
				);

				setCapturedPayload(initialSample.payload);
				setCapturedAt(initialSample.capturedAt);

				// The state we just set is data we loaded, not an edit — don't
				// let the autosave effect below treat it as a change to save.
				skipNextAutosaveRef.current = true;
			} catch (error) {
				if (!cancelled) {
					setLoadError(
						error && error.message
							? error.message
							: __(
									'Failed to load the workflow.',
									'dragwyb-agentflow'
								)
					);
				}
			} finally {
				if (!cancelled) {
					setLoading(false);
				}
			}
		}

		load();

		return () => {
			cancelled = true;
		};
		// Runs once: bootstrap.workflowId only ever transitions 0 -> a real id
		// via history.replaceState above, never by re-running this effect.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	const persist = useCallback(async () => {
		const current = latestRef.current;

		if (!current.workflowId) {
			return;
		}

		setSaveStatus('saving');

		try {
			await updateWorkflow(current.workflowId, {
				title: current.title,
				graph: current.graph,
			});
			setSaveStatus('saved');
		} catch (error) {
			setSaveStatus('error');
		}
	}, []);

	const scheduleAutosave = useCallback(() => {
		if (autosaveTimeoutRef.current) {
			clearTimeout(autosaveTimeoutRef.current);
		}

		autosaveTimeoutRef.current = setTimeout(persist, AUTOSAVE_DELAY_MS);
	}, [persist]);

	useEffect(() => {
		if (loading) {
			return;
		}

		if (skipNextAutosaveRef.current) {
			skipNextAutosaveRef.current = false;
			return;
		}

		setSaveStatus('dirty');
		scheduleAutosave();

		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [title, graph]);

	useEffect(
		() => () => {
			if (autosaveTimeoutRef.current) {
				clearTimeout(autosaveTimeoutRef.current);
			}
		},
		[]
	);

	const handleManualSave = useCallback(() => {
		if (autosaveTimeoutRef.current) {
			clearTimeout(autosaveTimeoutRef.current);
		}

		persist();
	}, [persist]);

	const handleExportWorkflow = useCallback(() => {
		const current = latestRef.current;
		const payload = buildExportPayload({
			name: current.title,
			graph: current.graph,
			active: workflowStatus === 1,
			id: current.workflowId,
		});

		downloadWorkflowJson(
			payload,
			exportFilenameFromTitle(current.title || __('workflow', 'dragwyb-agentflow'))
		);
	}, [workflowStatus]);

	const handleImportWorkflowFile = useCallback(
		async (file) => {
			if (!file) {
				return;
			}

			const current = latestRef.current;
			const hasNodes =
				Array.isArray(current.graph?.nodes) &&
				current.graph.nodes.length > 0;

			if (
				hasNodes &&
				!window.confirm(
					__(
						'Importing will replace the current workflow on the canvas. Continue?',
						'dragwyb-agentflow'
					)
				)
			) {
				return;
			}

			try {
				const text = await readFileAsText(file);
				const imported = parseImportJson(text);
				const nextGraph = normalizeGraph(imported.graph);

				setTitle(imported.name);
				setGraph(nextGraph);
				setSelectedNodeId(null);
				setSelectedConnection(null);
				setPicker(null);
				setCapturedPayload(null);
				setCapturedAt(null);
				setNodeOutputSamples({});

				// Import counts as an edit — let autosave / manual save persist it.
				skipNextAutosaveRef.current = false;
				setSaveStatus('dirty');

				if (autosaveTimeoutRef.current) {
					clearTimeout(autosaveTimeoutRef.current);
				}

				autosaveTimeoutRef.current = setTimeout(() => {
					persist();
				}, AUTOSAVE_DELAY_MS);
			} catch (error) {
				window.alert(
					error && error.message
						? error.message
						: __(
								'Failed to import the workflow JSON.',
								'dragwyb-agentflow'
							)
				);
			}
		},
		[persist]
	);

	const handleToggleActive = useCallback(async () => {
		const current = latestRef.current;

		if (!current.workflowId || toggleActiveBusy) {
			return;
		}

		// Active (1) → Pause (2). Draft (0) or Paused (2) → Active (1).
		const nextStatus = workflowStatus === 1 ? 2 : 1;

		setToggleActiveBusy(true);

		try {
			// Persist title/graph first so activating never runs an outdated
			// graph if the user just edited and autosave has not fired yet.
			if (autosaveTimeoutRef.current) {
				clearTimeout(autosaveTimeoutRef.current);
			}

			await updateWorkflow(current.workflowId, {
				title: current.title,
				graph: current.graph,
				status: nextStatus,
			});
			setWorkflowStatus(nextStatus);
			setSaveStatus('saved');
		} catch (error) {
			setSaveStatus('error');
		} finally {
			setToggleActiveBusy(false);
		}
	}, [workflowStatus, toggleActiveBusy]);

	const persistBeforeTest = useCallback(async () => {
		const current = latestRef.current;

		if (!current.workflowId) {
			return;
		}

		if (autosaveTimeoutRef.current) {
			clearTimeout(autosaveTimeoutRef.current);
		}

		await updateWorkflow(current.workflowId, {
			title: current.title,
			graph: current.graph,
		});
		setSaveStatus('saved');
	}, []);

	const handleToggleChat = useCallback(() => {
		setChatOpen((open) => {
			const next = !open;

			if (next && !chatSessionId) {
				const id =
					typeof crypto !== 'undefined' &&
					typeof crypto.randomUUID === 'function'
						? crypto.randomUUID()
						: `session-${Date.now()}`;
				setChatSessionId(id);
			}

			if (next) {
				setChatError('');
			}

			return next;
		});
	}, [chatSessionId]);

	const handleSendChat = useCallback(
		async (text) => {
			const current = latestRef.current;

			if (!current.workflowId || !text.trim()) {
				return;
			}

			const sessionId =
				chatSessionId ||
				(typeof crypto !== 'undefined' &&
				typeof crypto.randomUUID === 'function'
					? crypto.randomUUID()
					: `session-${Date.now()}`);

			if (!chatSessionId) {
				setChatSessionId(sessionId);
			}

			const userMessage = {
				id: `user-${Date.now()}`,
				role: 'user',
				content: text.trim(),
			};

			setChatMessages((previous) => [...previous, userMessage]);
			setChatSending(true);
			setChatError('');

			try {
				await persistBeforeTest();
				const result = await sendWorkflowChat(current.workflowId, {
					chatInput: text.trim(),
					sessionId,
				});
				const reply = (result?.output || '').trim();

				setChatMessages((previous) => [
					...previous,
					{
						id: `assistant-${result?.run_id || Date.now()}`,
						role: 'assistant',
						content:
							reply ||
							__(
								'(Workflow finished with no chat reply. Check the AI Agent output.)',
								'dragwyb-agentflow'
							),
					},
				]);

				if (result?.sessionId) {
					setChatSessionId(result.sessionId);
				}
			} catch (error) {
				const message =
					error?.message ||
					__('Chat request failed.', 'dragwyb-agentflow');
				setChatError(message);
			} finally {
				setChatSending(false);
			}
		},
		[chatSessionId, persistBeforeTest]
	);

	const testFlow = useTestFlow(workflowId, {
		persistBeforeTest,
		hasTrigger: () =>
			latestRef.current.graph.nodes.some(
				(node) => node.category === 'trigger'
			),
		getTriggerType: () => {
			const trigger = latestRef.current.graph.nodes.find(
				(node) => node.category === 'trigger'
			);

			return trigger?.type || null;
		},
		onSampleCaptured: (payload, status) => {
			const triggerNode = latestRef.current.graph.nodes.find(
				(node) => node.category === 'trigger'
			);

			if (
				status &&
				!sampleMatchesTrigger(status, triggerNode?.type || null)
			) {
				return;
			}

			setCapturedPayload(payload);
			fetchTestStatus(workflowId)
				.then((freshStatus) => {
					const sample = capturedSampleFromStatus(
						freshStatus,
						triggerNode?.type || null
					);
					setCapturedPayload(sample.payload);
					setCapturedAt(sample.capturedAt);
				})
				.catch(() => {});
		},
	});

	// Refresh captured trigger sample whenever a main canvas node is selected
	// so AI Agent / action Prompt fields still get form-field suggestions.
	useEffect(() => {
		if (!workflowId || !selectedNodeId) {
			return;
		}

		const node = graph.nodes.find((item) => item.id === selectedNodeId);

		if (!node || node.parent_agent_id) {
			return;
		}

		const triggerNode = graph.nodes.find(
			(item) => item.category === 'trigger'
		);

		if (!triggerNode) {
			return;
		}

		let cancelled = false;

		fetchTestStatus(workflowId)
			.then((status) => {
				if (cancelled) {
					return;
				}

				const sample = capturedSampleFromStatus(
					status,
					triggerNode.type
				);

				if (sample.payload) {
					setCapturedPayload(sample.payload);
					setCapturedAt(sample.capturedAt);
				}
			})
			.catch(() => {});

		return () => {
			cancelled = true;
		};
	}, [workflowId, selectedNodeId, graph.nodes]);

	const handleAddNode = (nodeTypeDefinition, category) => {
		setPicker(null);

		const existingTrigger =
			category === 'trigger'
				? latestRef.current.graph.nodes.find(
						(node) => node.category === 'trigger'
					)
				: null;

		if (existingTrigger) {
			focusNodeIdRef.current = existingTrigger.id;
			setCapturedPayload(null);
			setCapturedAt(null);

			if (workflowId) {
				clearTestSample(workflowId).catch(() => {});
			}

			setGraph((current) => ({
				...current,
				nodes: current.nodes.map((node) =>
					node.id === existingTrigger.id
						? {
								...node,
								type: nodeTypeDefinition.slug,
								label: nodeTypeDefinition.label,
								config: defaultConfigFor(nodeTypeDefinition),
							}
						: node
				),
			}));

			setSelectedNodeId(existingTrigger.id);
			return;
		}

		const newNode = {
			id: generateNodeId(),
			type: nodeTypeDefinition.slug,
			category,
			label: nodeTypeDefinition.label,
			x: 0,
			y: 0,
			config: defaultConfigFor(nodeTypeDefinition),
		};

		if (nodeTypeDefinition.slug === 'condition_action') {
			const conditions = newNode.config.conditions;

			newNode.config = {
				...newNode.config,
				conditions:
					Array.isArray(conditions) && conditions.length > 0
						? conditions
						: [createEmptyConditionRow()],
			};
		}

		focusNodeIdRef.current = newNode.id;

		const insertAfterId = latestRef.current.selectedNodeId;

		setGraph((current) => {
			const mainNodes = current.nodes.filter(
				(node) => !node.parent_agent_id
			);
			const position = placementForNewNode(mainNodes, insertAfterId);
			const newNodeWithPosition = {
				...newNode,
				x: position.x,
				y: position.y,
			};
			const attachmentNodes = current.nodes.filter(
				(node) => node.parent_agent_id
			);

			return {
				...current,
				nodes: [...mainNodes, newNodeWithPosition, ...attachmentNodes],
			};
		});

		setSelectedNodeId(newNode.id);
	};

	const handleAddCondition = (conditionNodeId, insertIndex = null) => {
		setGraph((current) => ({
			...current,
			nodes: current.nodes.map((node) => {
				if (node.id !== conditionNodeId) {
					return node;
				}

				const rows = [...getConditionRows(node.config || {})];
				const nextRow = createEmptyConditionRow();
				const index =
					insertIndex === null ? rows.length : insertIndex;

				rows.splice(index, 0, nextRow);

				return {
					...node,
					config: {
						...node.config,
						conditions: rows,
					},
				};
			}),
		}));
	};

	const handleRemoveCondition = (conditionNodeId, conditionId) => {
		setGraph((current) => ({
			...current,
			nodes: current.nodes.map((node) => {
				if (node.id !== conditionNodeId) {
					return node;
				}

				const rows = getConditionRows(node.config || {}).filter(
					(row) => row.id !== conditionId
				);

				return {
					...node,
					config: {
						...node.config,
						conditions: rows,
					},
				};
			}),
		}));
	};

	const handleAddNodeOnBranch = (conditionNodeId, branchId) => {
		setConnectionDrag(null);
		setPicker({
			kind: 'branch-action',
			conditionNodeId,
			branchId,
			appId: 'communication',
		});
		setSelectedNodeId(conditionNodeId);
	};

	const isValidBranchDropTarget = (targetNodeId, conditionNodeId) => {
		const targetNode = latestRef.current.graph.nodes.find(
			(node) => node.id === targetNodeId
		);

		return Boolean(
			targetNode &&
				!targetNode.parent_agent_id &&
				targetNode.category !== 'trigger' &&
				targetNode.id !== conditionNodeId
		);
	};

	const isValidFlowDropTarget = (targetNodeId, fromNodeId) => {
		const nodes = latestRef.current.graph.nodes;
		const fromNode = nodes.find((node) => node.id === fromNodeId);
		const toNode = nodes.find((node) => node.id === targetNodeId);

		return canConnectFlowNodes(fromNode, toNode);
	};

	const handleConnectFlowDirect = (fromNodeId, toNodeId) => {
		const nodes = latestRef.current.graph.nodes;
		const fromNode = nodes.find((node) => node.id === fromNodeId);
		const toNode = nodes.find((node) => node.id === toNodeId);

		if (!canConnectFlowNodes(fromNode, toNode)) {
			return;
		}

		setGraph((current) => ({
			...current,
			connections: setFlowConnection(
				current.connections,
				fromNodeId,
				toNodeId
			),
		}));
		setPicker(null);
		setConnectionDrag(null);
	};

	const handleConnectBranchDirect = (
		conditionNodeId,
		branchId,
		targetNodeId
	) => {
		setGraph((current) => ({
			...current,
			nodes: current.nodes.map((node) =>
				node.id === conditionNodeId
					? {
							...node,
							config: setConditionBranchTarget(
								node.config || {},
								branchId,
								targetNodeId
							),
						}
					: node
			),
		}));
		setPicker(null);
		setConnectionDrag(null);
		setSelectedNodeId(conditionNodeId);
	};

	const handleStartFlowConnectionDrag = (fromNodeId, event) => {
		const fromNode = latestRef.current.graph.nodes.find(
			(node) => node.id === fromNodeId
		);

		if (!fromNode) {
			return;
		}

		const from = nodeOutputPortPosition(fromNode);
		const pointer = clientToCanvasPoint(
			canvasRef.current,
			event.clientX,
			event.clientY
		);

		setPicker(null);
		setConnectionDrag({
			kind: 'flow',
			fromNodeId,
			from,
			pointer,
			hoverTargetNodeId: null,
		});
	};

	const handleStartBranchConnectionDrag = (
		conditionNodeId,
		branchId,
		event
	) => {
		const portElement = event.currentTarget;
		const from =
			portPositionFromElement(portElement, canvasRef.current) ||
			branchPortPosition(
				latestRef.current.graph.nodes,
				conditionNodeId,
				branchId
			);

		if (!from) {
			return;
		}

		const pointer = clientToCanvasPoint(
			canvasRef.current,
			event.clientX,
			event.clientY
		);

		setPicker(null);
		setConnectionDrag({
			kind: 'branch',
			conditionNodeId,
			branchId,
			from,
			pointer,
			hoverTargetNodeId: null,
		});
	};

	const handleConnectionDragMove = useCallback((event) => {
		const pointer = clientToCanvasPoint(
			canvasRef.current,
			event.clientX,
			event.clientY
		);

		setConnectionDrag((current) => {
			if (!current) {
				return null;
			}

			const excludeNodeIds =
				current.kind === 'branch'
					? [current.conditionNodeId]
					: current.kind === 'flow'
						? [current.fromNodeId]
						: [];
			const hoverTargetNodeId = nodeIdFromPointer(
				event.clientX,
				event.clientY,
				{ excludeNodeIds }
			);

			return {
				...current,
				pointer,
				hoverTargetNodeId,
			};
		});
	}, []);

	const handleConnectionDragEnd = useCallback(
		(event) => {
			setConnectionDrag((current) => {
				if (!current) {
					return null;
				}

				const excludeNodeIds =
					current.kind === 'branch'
						? [current.conditionNodeId]
						: current.kind === 'flow'
							? [current.fromNodeId]
							: [];
				const targetNodeId = nodeIdFromPointer(
					event.clientX,
					event.clientY,
					{ excludeNodeIds }
				);

				if (current.kind === 'branch') {
					if (
						targetNodeId &&
						isValidBranchDropTarget(
							targetNodeId,
							current.conditionNodeId
						)
					) {
						queueMicrotask(() =>
							handleConnectBranchDirect(
								current.conditionNodeId,
								current.branchId,
								targetNodeId
							)
						);
					}
				} else if (
					targetNodeId &&
					isValidFlowDropTarget(targetNodeId, current.fromNodeId)
				) {
					queueMicrotask(() =>
						handleConnectFlowDirect(
							current.fromNodeId,
							targetNodeId
						)
					);
				}

				return null;
			});
		},
		[handleConnectBranchDirect, handleConnectFlowDirect]
	);

	useBranchConnectionDrag(
		connectionDrag,
		handleConnectionDragMove,
		handleConnectionDragEnd
	);

	const handleSelectConnection = (edge) => {
		setPicker(null);
		setConnectionDrag(null);
		setSelectedNodeId(null);
		setSelectedConnection({
			id: edge.id,
			kind: edge.kind,
			fromNodeId: edge.fromNodeId,
			toNodeId: edge.toNodeId,
			conditionNodeId: edge.conditionNodeId,
			branchId: edge.branchId,
			targetNodeId: edge.targetNodeId,
		});
	};

	const handleDeleteConnection = (edge) => {
		if (edge.kind === 'branch') {
			handleDisconnectBranch(edge.conditionNodeId, edge.branchId);
		} else {
			setGraph((current) => ({
				...current,
				connections: removeFlowConnection(current.connections, edge.id),
			}));
		}

		setSelectedConnection(null);
	};

	const handleInsertOnConnection = (edge) => {
		setSelectedConnection({
			id: edge.id,
			kind: edge.kind,
			fromNodeId: edge.fromNodeId,
			toNodeId: edge.toNodeId,
			conditionNodeId: edge.conditionNodeId,
			branchId: edge.branchId,
			targetNodeId: edge.targetNodeId,
		});
		setSelectedNodeId(null);

		if (edge.kind === 'branch') {
			setPicker({
				kind: 'edge-branch-insert',
				conditionNodeId: edge.conditionNodeId,
				branchId: edge.branchId,
				targetNodeId: edge.targetNodeId,
				appId: 'communication',
			});
			return;
		}

		setPicker({
			kind: 'edge-insert',
			fromNodeId: edge.fromNodeId,
			toNodeId: edge.toNodeId,
			appId: 'communication',
		});
	};

	const buildActionNode = (nodeTypeDefinition) => {
		const newNode = {
			id: generateNodeId(),
			type: nodeTypeDefinition.slug,
			category: 'action',
			label: nodeTypeDefinition.label,
			x: 0,
			y: 0,
			config: defaultConfigFor(nodeTypeDefinition),
		};

		if (nodeTypeDefinition.slug === 'condition_action') {
			const conditions = newNode.config.conditions;

			newNode.config = {
				...newNode.config,
				conditions:
					Array.isArray(conditions) && conditions.length > 0
						? conditions
						: [createEmptyConditionRow()],
			};
		}

		return newNode;
	};

	const handleInsertNodeOnEdge = (
		nodeTypeDefinition,
		fromNodeId,
		toNodeId
	) => {
		setPicker(null);
		setSelectedConnection(null);

		const nodes = latestRef.current.graph.nodes;
		const fromNode = nodes.find((node) => node.id === fromNodeId);
		const toNode = nodes.find((node) => node.id === toNodeId);

		if (!fromNode || !toNode) {
			return;
		}

		const newNode = {
			...buildActionNode(nodeTypeDefinition),
			...positionBetweenNodes(fromNode, toNode),
		};

		focusNodeIdRef.current = newNode.id;

		setGraph((current) => {
			const attachmentNodes = current.nodes.filter(
				(node) => node.parent_agent_id
			);
			const mainNodes = current.nodes.filter(
				(node) => !node.parent_agent_id
			);

			return {
				...current,
				nodes: [...mainNodes, newNode, ...attachmentNodes],
				connections: insertNodeBetweenFlow(
					current.connections,
					fromNodeId,
					toNodeId,
					newNode.id
				),
			};
		});

		setSelectedNodeId(newNode.id);
	};

	const handleInsertNodeOnBranchEdge = (
		nodeTypeDefinition,
		conditionNodeId,
		branchId,
		targetNodeId
	) => {
		setPicker(null);
		setSelectedConnection(null);

		const nodes = latestRef.current.graph.nodes;
		const conditionNode = nodes.find((node) => node.id === conditionNodeId);
		const targetNode = nodes.find((node) => node.id === targetNodeId);

		if (!conditionNode || !targetNode) {
			return;
		}

		const rows = getConditionRows(conditionNode.config || {});
		const port = conditionOutputPortPosition(conditionNode, branchId, rows);
		const fromStub = {
			x: conditionNode.x + CONDITION_NODE_WIDTH,
			y: port.y - 48,
		};
		const position = positionBetweenNodes(fromStub, targetNode);
		const newNode = {
			...buildActionNode(nodeTypeDefinition),
			...position,
		};

		focusNodeIdRef.current = newNode.id;

		setGraph((current) => ({
			...current,
			nodes: current.nodes
				.map((node) =>
					node.id === conditionNodeId
						? {
								...node,
								config: setConditionBranchTarget(
									node.config || {},
									branchId,
									newNode.id
								),
							}
						: node
				)
				.concat(newNode),
			connections: setFlowConnection(
				current.connections,
				newNode.id,
				targetNodeId
			),
		}));

		setSelectedNodeId(newNode.id);
	};

	const handleDisconnectBranch = (conditionNodeId, branchId) => {
		setGraph((current) => ({
			...current,
			nodes: current.nodes.map((node) =>
				node.id === conditionNodeId
					? {
							...node,
							config: clearConditionBranchTarget(
								node.config || {},
								branchId
							),
						}
					: node
			),
		}));
	};

	const handleAttachBranchNode = (
		nodeTypeDefinition,
		conditionNodeId,
		branchId
	) => {
		setPicker(null);

		const conditionNode = latestRef.current.graph.nodes.find(
			(node) => node.id === conditionNodeId
		);

		if (!conditionNode) {
			return;
		}

		const branchTargets = getConditionRows(conditionNode.config || {})
			.map((row) => row.node_id)
			.filter(Boolean);
		const position = defaultBranchNodePosition(
			conditionNode,
			branchId,
			branchTargets.length
		);

		const newNode = {
			id: generateNodeId(),
			type: nodeTypeDefinition.slug,
			category: 'action',
			label: nodeTypeDefinition.label,
			x: position.x,
			y: position.y,
			config: defaultConfigFor(nodeTypeDefinition),
		};

		focusNodeIdRef.current = newNode.id;

		setGraph((current) => ({
			...current,
			nodes: current.nodes
				.map((node) =>
					node.id === conditionNodeId
						? {
								...node,
								config: setConditionBranchTarget(
									node.config || {},
									branchId,
									newNode.id
								),
							}
						: node
				)
				.concat(newNode),
		}));

		setSelectedNodeId(newNode.id);
	};

	const handleOpenPicker = (kind, appId) => {
		if (kind === 'tool') {
			const toolDef = nodeTypes.actions.find(
				(action) => action.slug === appId
			);

			if (toolDef) {
				handleAddNode(toolDef, 'action');
				return;
			}
		}

		setPicker({ kind, appId });
		setSelectedNodeId(null);
	};

	const handleAddAgentFallbackModel = (agentId) => {
		setPicker({
			kind: 'agent-fallback-chat-model',
			agentId,
			appId: 'chat-models',
		});
		setSelectedNodeId(agentId);
	};

	const handleAddAgentOutputParser = (agentId) => {
		const agent = latestRef.current.graph.nodes.find(
			(node) => node.id === agentId
		);

		if (!agent) {
			return;
		}

		const position = outputParserAttachmentPosition(agent);
		const newParser = {
			id: generateNodeId(),
			type: 'agent_output_parser',
			category: 'action',
			label: __('Structured Output Parser', 'dragwyb-agentflow'),
			parent_agent_id: agentId,
			attachment_type: 'output_parser',
			x: position.x,
			y: position.y,
			config: {
				schema_type: 'from_json',
				json_example:
					'{\n  "state": "California",\n  "cities": ["Los Angeles", "San Francisco", "San Diego"]\n}',
				json_schema:
					'{\n  "type": "object",\n  "properties": {\n    "state": {\n      "type": "string"\n    },\n    "cities": {\n      "type": "array",\n      "items": {\n        "type": "string"\n      }\n    }\n  }\n}',
				auto_fix: true,
				customize_retry_prompt: false,
				retry_prompt: '',
			},
		};

		focusNodeIdRef.current = newParser.id;

		setGraph((current) => ({
			...current,
			nodes: [
				...current.nodes.filter(
					(node) =>
						!(
							node.parent_agent_id === agentId &&
							node.attachment_type === 'output_parser'
						)
				),
				newParser,
			],
		}));

		setSelectedNodeId(newParser.id);
	};

	const handleAttachAgentFallbackChatModel = (nodeTypeDefinition, agentId) => {
		setPicker(null);

		const agent = latestRef.current.graph.nodes.find(
			(node) => node.id === agentId
		);

		if (!agent) {
			return;
		}

		const provider = providerFromChatModelSlug(nodeTypeDefinition.slug);
		const position = fallbackChatModelAttachmentPosition(agent);

		const newFallbackModel = {
			id: generateNodeId(),
			type: nodeTypeDefinition.slug,
			category: 'action',
			label: `${nodeTypeDefinition.label} (${__('Fallback', 'dragwyb-agentflow')})`,
			parent_agent_id: agentId,
			attachment_type: 'fallback_chat_model',
			x: position.x,
			y: position.y,
			config: {
				...defaultConfigFor(nodeTypeDefinition),
				model:
					defaultConfigFor(nodeTypeDefinition).model ||
					DEFAULT_MODEL_BY_PROVIDER[provider],
			},
		};

		focusNodeIdRef.current = newFallbackModel.id;

		setGraph((current) => ({
			...current,
			nodes: [
				...current.nodes.filter(
					(node) =>
						!(
							node.parent_agent_id === agentId &&
							node.attachment_type === 'fallback_chat_model'
						)
				),
				newFallbackModel,
			],
		}));

		setSelectedNodeId(newFallbackModel.id);
	};

	const handleAddAgentTool = (agentId) => {
		setPicker({ kind: 'agent-tool', agentId, appId: 'agent-tools' });
		setSelectedNodeId(agentId);
	};

	const handleAddAgentChatModel = (agentId) => {
		setPicker({ kind: 'agent-chat-model', agentId, appId: 'chat-models' });
		setSelectedNodeId(agentId);
	};

	const handleAddParserChatModel = (parserId) => {
		setPicker({
			kind: 'parser-chat-model',
			parserId,
			appId: 'chat-models',
		});
		setSelectedNodeId(parserId);
	};

	const handleAddAgentMemory = (agentId) => {
		const agent = latestRef.current.graph.nodes.find(
			(node) => node.id === agentId
		);

		if (!agent) {
			return;
		}

		const position = memoryAttachmentPosition(agent);
		const newMemory = {
			id: generateNodeId(),
			type: 'simple_memory',
			category: 'action',
			label: __('Simple Memory', 'dragwyb-agentflow'),
			parent_agent_id: agentId,
			attachment_type: 'memory',
			x: position.x,
			y: position.y,
			config: {},
		};

		focusNodeIdRef.current = newMemory.id;

		setGraph((current) => ({
			...current,
			nodes: [
				...current.nodes.filter(
					(node) =>
						!(
							node.parent_agent_id === agentId &&
							node.attachment_type === 'memory'
						)
				),
				newMemory,
			],
		}));

		setSelectedNodeId(newMemory.id);
	};

	const handleAttachAgentChatModel = (nodeTypeDefinition, agentId) => {
		setPicker(null);

		const agent = latestRef.current.graph.nodes.find(
			(node) => node.id === agentId
		);

		if (!agent) {
			return;
		}

		const provider = providerFromChatModelSlug(nodeTypeDefinition.slug);
		const position = chatModelAttachmentPosition(agent);

		const newChatModel = {
			id: generateNodeId(),
			type: nodeTypeDefinition.slug,
			category: 'action',
			label: nodeTypeDefinition.label,
			parent_agent_id: agentId,
			attachment_type: 'chat_model',
			x: position.x,
			y: position.y,
			config: {
				...defaultConfigFor(nodeTypeDefinition),
				model:
					defaultConfigFor(nodeTypeDefinition).model ||
					DEFAULT_MODEL_BY_PROVIDER[provider],
			},
		};

		focusNodeIdRef.current = newChatModel.id;

		setGraph((current) => {
			const withoutChatModel = current.nodes.filter(
				(node) =>
					!(
						node.parent_agent_id === agentId &&
						node.attachment_type === 'chat_model'
					)
			);

			const updatedAgent = syncAgentConfigFromChatModel(
				withoutChatModel.find((node) => node.id === agentId) || agent,
				newChatModel
			);

			return {
				...current,
				nodes: withoutChatModel
					.map((node) => (node.id === agentId ? updatedAgent : node))
					.concat(newChatModel),
			};
		});

		setSelectedNodeId(newChatModel.id);
	};

	const handleAttachParserChatModel = (nodeTypeDefinition, parserId) => {
		setPicker(null);

		const parser = latestRef.current.graph.nodes.find(
			(node) => node.id === parserId
		);

		if (!parser) {
			return;
		}

		const provider = providerFromChatModelSlug(nodeTypeDefinition.slug);
		const position = parserChatModelAttachmentPosition(parser);
		const defaults = defaultConfigFor(nodeTypeDefinition);

		const newChatModel = {
			id: generateNodeId(),
			type: nodeTypeDefinition.slug,
			category: 'action',
			label: nodeTypeDefinition.label,
			parent_agent_id: parserId,
			attachment_type: 'parser_chat_model',
			x: position.x,
			y: position.y,
			config: {
				...defaults,
				model: defaults.model || DEFAULT_MODEL_BY_PROVIDER[provider] || '',
			},
		};

		focusNodeIdRef.current = newChatModel.id;

		setGraph((current) => ({
			...current,
			nodes: [
				...current.nodes.filter(
					(node) =>
						!(
							node.parent_agent_id === parserId &&
							node.attachment_type === 'parser_chat_model'
						)
				),
				newChatModel,
			],
		}));

		setSelectedNodeId(newChatModel.id);
	};

	const handleAttachAgentTool = (nodeTypeDefinition, agentId) => {
		setPicker(null);

		const agent = latestRef.current.graph.nodes.find(
			(node) => node.id === agentId
		);

		if (!agent) {
			return;
		}

		const existingTools = toolsForAgent(
			latestRef.current.graph.nodes,
			agentId
		);
		const position = toolAttachmentPosition(agent, existingTools.length);

		const newTool = {
			id: generateNodeId(),
			type: nodeTypeDefinition.slug,
			category: 'action',
			label: nodeTypeDefinition.label,
			parent_agent_id: agentId,
			attachment_type: 'tool',
			x: position.x,
			y: position.y,
			config: defaultConfigFor(nodeTypeDefinition),
		};

		focusNodeIdRef.current = newTool.id;

		setGraph((current) => ({
			...current,
			nodes: [...current.nodes, newTool],
		}));

		setSelectedNodeId(newTool.id);
	};

	const registerNodeRef = useCallback((nodeId, element) => {
		if (element) {
			nodeElementsRef.current[nodeId] = element;
		} else {
			delete nodeElementsRef.current[nodeId];
		}
	}, []);

	const handleMoveNode = (nodeId, x, y) => {
		setGraph((current) => {
			const movedNode = current.nodes.find((node) => node.id === nodeId);

			if (!movedNode) {
				return current;
			}

			const dx = x - movedNode.x;
			const dy = y - movedNode.y;

			// Drag an attachment on its own — keep the agent and siblings put.
			if (
				movedNode.parent_agent_id &&
				movedNode.type !== AI_AGENT_SLUG
			) {
				return {
					...current,
					nodes: current.nodes.map((node) =>
						node.id === nodeId ? { ...node, x, y } : node
					),
				};
			}

			// Drag the agent — move every attached sub-node by the same delta.
			if (movedNode.type === AI_AGENT_SLUG) {
				return {
					...current,
					nodes: current.nodes.map((node) => {
						if (node.id === nodeId) {
							return { ...node, x, y };
						}

						if (node.parent_agent_id === nodeId) {
							return {
								...node,
								x: Math.max(0, node.x + dx),
								y: Math.max(0, node.y + dy),
							};
						}

						return node;
					}),
				};
			}

			return {
				...current,
				nodes: current.nodes.map((node) =>
					node.id === nodeId ? { ...node, x, y } : node
				),
			};
		});
	};

	const handleChangeLabel = (label) => {
		setGraph((current) => ({
			...current,
			nodes: current.nodes.map((node) =>
				node.id === selectedNodeId ? { ...node, label } : node
			),
		}));
	};

	const handleChangeConfig = (fieldName, value) => {
		setGraph((current) => {
			let nodes = current.nodes.map((node) => {
				if (node.id !== selectedNodeId) {
					return node;
				}

				const nextConfig = {
					...node.config,
					[fieldName]: value,
				};

				if (
					node.type === 'ai_agent_action' &&
					fieldName === 'provider'
				) {
					const provider = String(value || 'openai').toLowerCase();

					nextConfig.connection_id = 0;
					nextConfig.model =
						DEFAULT_MODEL_BY_PROVIDER[provider] ||
						DEFAULT_MODEL_BY_PROVIDER.openai;
				}

				return {
					...node,
					config: nextConfig,
				};
			});

			const updated = nodes.find((node) => node.id === selectedNodeId);

			if (
				updated?.attachment_type === 'chat_model' &&
				updated.parent_agent_id
			) {
				nodes = nodes.map((node) =>
					node.id === updated.parent_agent_id
						? syncAgentConfigFromChatModel(node, updated)
						: node
				);
			}

			return {
				...current,
				nodes,
			};
		});
	};

	const handleDeleteNode = () => {
		const deletingId = selectedNodeId;
		const deletingNode = graph.nodes.find((node) => node.id === deletingId);

		setGraph((current) => {
			let nodes = current.nodes.filter((node) => node.id !== deletingId);

			if (deletingNode && deletingNode.type === 'ai_agent_action') {
				nodes = removeAgentAttachments(nodes, deletingId);
			}

			if (
				deletingNode?.attachment_type === 'chat_model' &&
				deletingNode.parent_agent_id
			) {
				nodes = nodes.map((node) =>
					node.id === deletingNode.parent_agent_id
						? {
								...node,
								config: {
									...node.config,
									connection_id: 0,
									model: '',
								},
							}
						: node
				);
			}

			return {
				...current,
				nodes,
				connections: removeConnectionsForNode(
					current.connections,
					deletingId
				),
			};
		});
		setNodeOutputSamples((current) => {
			const next = { ...current };
			delete next[deletingId];
			return next;
		});
		setSelectedNodeId(null);
	};

	if (loading) {
		return (
			<div className="aiawa-builder-loading" role="status">
				{__('Loading…', 'dragwyb-agentflow')}
			</div>
		);
	}

	if (loadError) {
		return (
			<div className="aiawa-builder-error" role="alert">
				{loadError}
			</div>
		);
	}

	const selectedNode =
		graph.nodes.find((node) => node.id === selectedNodeId) || null;
	const allTypes = [...nodeTypes.triggers, ...nodeTypes.actions];
	const selectedNodeType = selectedNode
		? allTypes.find((type) => type.slug === selectedNode.type) || null
		: null;
	const knownTypeSlugs = allTypes.map((type) => type.slug);
	const triggerNode = graph.nodes.find((item) => item.category === 'trigger');
	const triggerLabel =
		triggerNode?.label || __('Trigger', 'dragwyb-agentflow');
	const hasExistingTrigger = Boolean(triggerNode);
	const hasChatTrigger =
		triggerNode?.type === 'chat_message_received_trigger';
	const chatInitialMessages = String(
		triggerNode?.config?.initial_messages || ''
	)
		.split(/\r\n|\r|\n/)
		.map((line) => line.trim())
		.filter(Boolean);

	return (
		<div className={`aiawa-builder${chatOpen ? ' aiawa-builder--chat-open' : ''}`}>
			<Header
				title={title}
				onTitleChange={setTitle}
				status={saveStatus}
				workflowStatus={workflowStatus}
				onToggleActive={handleToggleActive}
				toggleActiveBusy={toggleActiveBusy}
				onSave={handleManualSave}
				onExport={handleExportWorkflow}
				onImportFile={handleImportWorkflowFile}
				listUrl={bootstrap.listUrl}
				saveDisabled={saveStatus === 'saving' || toggleActiveBusy}
				testFlow={testFlow}
				showChat={hasChatTrigger}
				chatOpen={chatOpen}
				onToggleChat={handleToggleChat}
			/>
			<div className="aiawa-builder__body">
				<Palette
					triggers={nodeTypes.triggers}
					actions={nodeTypes.actions}
					onOpenPicker={handleOpenPicker}
				/>
				<div className="aiawa-builder__canvas-wrap">
				<Canvas
					nodes={graph.nodes}
					connections={graph.connections}
					knownTypeSlugs={knownTypeSlugs}
					selectedNodeId={selectedNodeId}
					connectionDrag={connectionDrag}
					isValidBranchDropTarget={isValidBranchDropTarget}
					isValidFlowDropTarget={isValidFlowDropTarget}
					onRegisterCanvas={(element) => {
						canvasRef.current = element;
					}}
					onSelectNode={(nodeId) => {
						setPicker(null);
						setConnectionDrag(null);
						setSelectedConnection(null);
						setSelectedNodeId(nodeId);
					}}
					onMoveNode={handleMoveNode}
					onAddAgentChatModel={handleAddAgentChatModel}
					onAddAgentMemory={handleAddAgentMemory}
					onAddAgentTool={handleAddAgentTool}
					onAddParserChatModel={handleAddParserChatModel}
					onAddCondition={handleAddCondition}
					onRemoveCondition={handleRemoveCondition}
					onStartBranchConnectionDrag={handleStartBranchConnectionDrag}
					onStartFlowConnectionDrag={handleStartFlowConnectionDrag}
					onDisconnectBranch={handleDisconnectBranch}
					selectedConnection={selectedConnection}
					onSelectConnection={handleSelectConnection}
					onDeleteConnection={handleDeleteConnection}
					onInsertOnConnection={handleInsertOnConnection}
					registerNodeRef={registerNodeRef}
					onCanvasClick={(event) => {
						if (event.target === event.currentTarget) {
							setSelectedNodeId(null);
							setSelectedConnection(null);
						}
					}}
				/>
				<ChatPanel
					open={chatOpen}
					onClose={() => setChatOpen(false)}
					messages={chatMessages}
					sending={chatSending}
					error={chatError}
					onSend={handleSendChat}
					title={triggerNode?.config?.title || __('Chat', 'dragwyb-agentflow')}
					initialMessages={chatInitialMessages}
				/>
				</div>
				{picker ? (
					<PickerSidebar
						kind={picker.kind}
						appId={picker.appId}
						triggers={nodeTypes.triggers}
						actions={nodeTypes.actions}
						hasExistingTrigger={hasExistingTrigger}
						onSelect={
							picker.kind === 'branch-action'
								? (item) =>
										handleAttachBranchNode(
											item,
											picker.conditionNodeId,
											picker.branchId
										)
								: picker.kind === 'edge-insert'
									? (item) =>
											handleInsertNodeOnEdge(
												item,
												picker.fromNodeId,
												picker.toNodeId
											)
									: picker.kind === 'edge-branch-insert'
										? (item) =>
												handleInsertNodeOnBranchEdge(
													item,
													picker.conditionNodeId,
													picker.branchId,
													picker.targetNodeId
												)
								: picker.kind === 'agent-tool'
								? (item) =>
										handleAttachAgentTool(
											item,
											picker.agentId
										)
								: picker.kind === 'agent-chat-model'
									? (item) =>
											handleAttachAgentChatModel(
												item,
												picker.agentId
											)
									: picker.kind === 'parser-chat-model'
										? (item) =>
												handleAttachParserChatModel(
													item,
													picker.parserId
												)
									: picker.kind === 'agent-fallback-chat-model'
										? (item) =>
												handleAttachAgentFallbackChatModel(
													item,
													picker.agentId
												)
									: handleAddNode
						}
						onClose={() => setPicker(null)}
					/>
				) : (
					<ConfigPanel
						node={selectedNode}
						nodeType={selectedNodeType}
						connections={connections}
						onConnectionsChange={setConnections}
						onChangeLabel={handleChangeLabel}
						onChangeConfig={handleChangeConfig}
						onDelete={handleDeleteNode}
						onClose={() => setSelectedNodeId(null)}
						capturedPayload={capturedPayload}
						capturedAt={capturedAt}
						triggerLabel={triggerLabel}
						graphNodes={graph.nodes}
						workflowId={workflowId}
						graph={graph}
						onPersistBeforeTest={persistBeforeTest}
						onAddAgentChatModel={handleAddAgentChatModel}
						onAddAgentMemory={handleAddAgentMemory}
						onAddAgentTool={handleAddAgentTool}
						onAddAgentFallbackModel={handleAddAgentFallbackModel}
						onAddAgentOutputParser={handleAddAgentOutputParser}
						onAddParserChatModel={handleAddParserChatModel}
						onSelectNode={setSelectedNodeId}
						nodeOutputSamples={nodeOutputSamples}
						onNodeTestResult={(nodeId, output) =>
							setNodeOutputSamples((current) => ({
								...current,
								[nodeId]: output,
							}))
						}
					/>
				)}
			</div>
		</div>
	);
}
