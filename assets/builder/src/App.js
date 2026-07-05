import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import Header from './components/Header';
import Palette from './components/Palette';
import Canvas from './components/Canvas';
import ConfigPanel from './components/ConfigPanel';
import PickerSidebar from './components/PickerSidebar';
import useTestFlow from './hooks/useTestFlow';
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
} from './api';
import {
	generateNodeId,
	emptyGraph,
	defaultNodePosition,
	sortNodesForFlow,
	insertNodeInFlow,
} from './utils';
import {
	removeAgentAttachments,
	toolAttachmentPosition,
	chatModelAttachmentPosition,
	memoryAttachmentPosition,
	toolsForAgent,
	syncAgentConfigFromChatModel,
	providerFromChatModelSlug,
	DEFAULT_MODEL_BY_PROVIDER,
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
	const triggerNodes = nodes.filter((node) => node.category === 'trigger');

	// A workflow may only have one trigger — keep the topmost if legacy data has more.
	if (triggerNodes.length > 1) {
		const keepId = sortNodesForFlow(triggerNodes)[0].id;

		return {
			nodes: nodes.filter(
				(node) => node.category !== 'trigger' || node.id === keepId
			),
			connections: Array.isArray(graph.connections)
				? graph.connections
				: [],
		};
	}

	return {
		nodes,
		connections: Array.isArray(graph.connections) ? graph.connections : [],
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
	const [capturedPayload, setCapturedPayload] = useState(null);
	const [capturedAt, setCapturedAt] = useState(null);

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

	useEffect(() => {
		latestRef.current = { title, graph, workflowId, selectedNodeId };
	}, [title, graph, workflowId, selectedNodeId]);

	// Escape clears the selection so keyboard users are not stuck in the
	// config panel (roadmap item 16 accessibility pass).
	useEffect(() => {
		const onKeyDown = (event) => {
			if (event.key === 'Escape') {
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
	}, [picker]);

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
						title: __('Untitled workflow', 'workflow-automate'),
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
								'workflow-automate'
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
				.catch(() => { });
		},
	});

	// Refresh captured data when a trigger node is selected.
	useEffect(() => {
		if (!workflowId || !selectedNodeId) {
			return;
		}

		const node = graph.nodes.find((item) => item.id === selectedNodeId);

		if (!node || node.category !== 'trigger') {
			return;
		}

		let cancelled = false;

		fetchTestStatus(workflowId)
			.then((status) => {
				if (cancelled) {
					return;
				}

				const sample = capturedSampleFromStatus(status, node.type);
				setCapturedPayload(sample.payload);
				setCapturedAt(sample.capturedAt);
			})
			.catch(() => { });

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
				clearTestSample(workflowId).catch(() => { });
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

		focusNodeIdRef.current = newNode.id;

		const insertAfterId = latestRef.current.selectedNodeId;

		setGraph((current) => {
			const mainNodes = current.nodes.filter((node) => !node.parent_agent_id);
			const { position, nodes: shiftedNodes } = insertNodeInFlow(
				mainNodes,
				insertAfterId
			);
			const newNodeWithPosition = {
				...newNode,
				x: position.x,
				y: position.y,
			};
			const ordered = sortNodesForFlow(shiftedNodes);
			let insertIndex = ordered.length;

			if (insertAfterId) {
				const afterIndex = ordered.findIndex(
					(node) => node.id === insertAfterId
				);

				if (afterIndex >= 0) {
					insertIndex = afterIndex + 1;
				}
			}

			ordered.splice(insertIndex, 0, newNodeWithPosition);

			const attachmentNodes = current.nodes.filter(
				(node) => node.parent_agent_id
			);

			return {
				...current,
				nodes: [...ordered, ...attachmentNodes],
			};
		});

		setSelectedNodeId(newNode.id);
	};

	const handleOpenPicker = (kind, appId) => {
		setPicker({ kind, appId });
		setSelectedNodeId(null);
	};

	const handleAddAgentTool = (agentId) => {
		setPicker({ kind: 'agent-tool', agentId, appId: 'agent-tools' });
		setSelectedNodeId(agentId);
	};

	const handleAddAgentChatModel = (agentId) => {
		setPicker({ kind: 'agent-chat-model', agentId, appId: 'chat-models' });
		setSelectedNodeId(agentId);
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
			label: __('Simple Memory', 'workflow-automate'),
			parent_agent_id: agentId,
			attachment_type: 'memory',
			x: position.x,
			y: position.y,
			config: {},
		};

		focusNodeIdRef.current = newMemory.id;

		setGraph((current) => {
			const withoutMemory = current.nodes.filter(
				(node) =>
					!(
						node.parent_agent_id === agentId &&
						node.attachment_type === 'memory'
					)
			);

			const nodes = withoutMemory.map((node) =>
				node.id === agentId
					? {
						...node,
						config: {
							...node.config,
							memory_enabled: true,
						},
					}
					: node
			);

			return {
				...current,
				nodes: [...nodes, newMemory],
			};
		});

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
		setGraph((current) => ({
			...current,
			nodes: current.nodes.map((node) =>
				node.id === nodeId ? { ...node, x, y } : node
			),
		}));
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

			if (
				deletingNode?.attachment_type === 'memory' &&
				deletingNode.parent_agent_id
			) {
				nodes = nodes.map((node) =>
					node.id === deletingNode.parent_agent_id
						? {
							...node,
							config: {
								...node.config,
								memory_enabled: false,
							},
						}
						: node
				);
			}

			return {
				...current,
				nodes,
			};
		});
		setSelectedNodeId(null);
	};

	if (loading) {
		return (
			<div className="wfa-builder-loading" role="status">
				{__('Loading…', 'workflow-automate')}
			</div>
		);
	}

	if (loadError) {
		return (
			<div className="wfa-builder-error" role="alert">
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
	const triggerLabel = triggerNode?.label || __('Trigger', 'workflow-automate');
	const hasExistingTrigger = Boolean(triggerNode);

	return (
		<div className="wfa-builder">
			<Header
				title={title}
				onTitleChange={setTitle}
				status={saveStatus}
				workflowStatus={workflowStatus}
				onToggleActive={handleToggleActive}
				toggleActiveBusy={toggleActiveBusy}
				onSave={handleManualSave}
				listUrl={bootstrap.listUrl}
				saveDisabled={saveStatus === 'saving' || toggleActiveBusy}
				testFlow={testFlow}
			/>
			<div className="wfa-builder__body">
				<Palette
					triggers={nodeTypes.triggers}
					actions={nodeTypes.actions}
					onOpenPicker={handleOpenPicker}
				/>
				<Canvas
					nodes={graph.nodes}
					knownTypeSlugs={knownTypeSlugs}
					selectedNodeId={selectedNodeId}
					onSelectNode={(nodeId) => {
						setPicker(null);
						setSelectedNodeId(nodeId);
					}}
					onMoveNode={handleMoveNode}
					onAddAgentChatModel={handleAddAgentChatModel}
					onAddAgentMemory={handleAddAgentMemory}
					onAddAgentTool={handleAddAgentTool}
					registerNodeRef={registerNodeRef}
					onCanvasClick={(event) => {
						if (event.target === event.currentTarget) {
							setSelectedNodeId(null);
						}
					}}
				/>
				{picker ? (
					<PickerSidebar
						kind={picker.kind}
						appId={picker.appId}
						triggers={nodeTypes.triggers}
						actions={nodeTypes.actions}
						hasExistingTrigger={hasExistingTrigger}
						onSelect={
							picker.kind === 'agent-tool'
								? (item) =>
									handleAttachAgentTool(item, picker.agentId)
								: picker.kind === 'agent-chat-model'
									? (item) =>
										handleAttachAgentChatModel(
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
					/>
				)}
			</div>
		</div>
	);
}
