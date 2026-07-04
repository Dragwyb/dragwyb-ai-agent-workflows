import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import Header from './components/Header';
import Palette from './components/Palette';
import Canvas from './components/Canvas';
import ConfigPanel from './components/ConfigPanel';
import {
	fetchWorkflow,
	createWorkflow,
	updateWorkflow,
	fetchNodeTypes,
	getBootstrap,
} from './api';
import { generateNodeId, emptyGraph, defaultNodePosition } from './utils';

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

	return {
		nodes: Array.isArray(graph.nodes) ? graph.nodes : [],
		connections: Array.isArray(graph.connections) ? graph.connections : [],
	};
}

export default function App() {
	const bootstrap = getBootstrap();

	const [workflowId, setWorkflowId] = useState(bootstrap.workflowId || 0);
	const [title, setTitle] = useState('');
	const [graph, setGraph] = useState(emptyGraph());
	const [nodeTypes, setNodeTypes] = useState({ triggers: [], actions: [] });
	const [selectedNodeId, setSelectedNodeId] = useState(null);
	const [loading, setLoading] = useState(true);
	const [loadError, setLoadError] = useState('');
	const [saveStatus, setSaveStatus] = useState('idle');

	// Autosave fires from a setTimeout created by a stable useCallback, so it
	// needs a way to read state as of when it actually runs rather than as
	// of when it was scheduled — a plain ref mirror avoids stale closures
	// without having to recreate the timeout on every keystroke.
	const latestRef = useRef({ title: '', graph: emptyGraph(), workflowId: 0 });
	const skipNextAutosaveRef = useRef(false);
	const autosaveTimeoutRef = useRef(null);

	useEffect(() => {
		latestRef.current = { title, graph, workflowId };
	}, [title, graph, workflowId]);

	useEffect(() => {
		let cancelled = false;

		async function load() {
			try {
				const typesPromise = fetchNodeTypes();
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

				if (cancelled) {
					return;
				}

				setTitle(workflow.title || '');
				setGraph(normalizeGraph(workflow.graph));
				setNodeTypes({
					triggers: types.triggers || [],
					actions: types.actions || [],
				});

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

	const handleAddNode = (nodeTypeDefinition, category) => {
		const newNode = {
			id: generateNodeId(),
			type: nodeTypeDefinition.slug,
			category,
			label: nodeTypeDefinition.label,
			x: 0,
			y: 0,
			config: {},
		};

		setGraph((current) => {
			const position = defaultNodePosition(current.nodes.length);
			return {
				...current,
				nodes: [
					...current.nodes,
					{ ...newNode, x: position.x, y: position.y },
				],
			};
		});

		setSelectedNodeId(newNode.id);
	};

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
		setGraph((current) => ({
			...current,
			nodes: current.nodes.map((node) =>
				node.id === selectedNodeId
					? {
							...node,
							config: { ...node.config, [fieldName]: value },
						}
					: node
			),
		}));
	};

	const handleDeleteNode = () => {
		setGraph((current) => ({
			...current,
			nodes: current.nodes.filter((node) => node.id !== selectedNodeId),
		}));
		setSelectedNodeId(null);
	};

	if (loading) {
		return (
			<div className="wfa-builder-loading">
				{__('Loading…', 'workflow-automate')}
			</div>
		);
	}

	if (loadError) {
		return <div className="wfa-builder-error">{loadError}</div>;
	}

	const selectedNode =
		graph.nodes.find((node) => node.id === selectedNodeId) || null;
	const allTypes = [...nodeTypes.triggers, ...nodeTypes.actions];
	const selectedNodeType = selectedNode
		? allTypes.find((type) => type.slug === selectedNode.type) || null
		: null;
	const knownTypeSlugs = allTypes.map((type) => type.slug);

	return (
		<div className="wfa-builder">
			<Header
				title={title}
				onTitleChange={setTitle}
				status={saveStatus}
				onSave={handleManualSave}
				listUrl={bootstrap.listUrl}
				saveDisabled={saveStatus === 'saving'}
			/>
			<div className="wfa-builder__body">
				<Palette
					triggers={nodeTypes.triggers}
					actions={nodeTypes.actions}
					onAdd={handleAddNode}
				/>
				<Canvas
					nodes={graph.nodes}
					knownTypeSlugs={knownTypeSlugs}
					selectedNodeId={selectedNodeId}
					onSelectNode={setSelectedNodeId}
					onMoveNode={handleMoveNode}
					onCanvasClick={(event) => {
						if (event.target === event.currentTarget) {
							setSelectedNodeId(null);
						}
					}}
				/>
				<ConfigPanel
					node={selectedNode}
					nodeType={selectedNodeType}
					onChangeLabel={handleChangeLabel}
					onChangeConfig={handleChangeConfig}
					onDelete={handleDeleteNode}
					onClose={() => setSelectedNodeId(null)}
				/>
			</div>
		</div>
	);
}
