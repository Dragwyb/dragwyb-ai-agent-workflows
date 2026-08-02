import { useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * @param {*} value
 * @return {boolean}
 */
function isScalar(value) {
	return (
		value === null ||
		value === undefined ||
		typeof value === 'string' ||
		typeof value === 'number' ||
		typeof value === 'boolean'
	);
}

/**
 * @param {*}      value
 * @param {string} parentPath
 * @param {Array<Object>} children
 * @return {void}
 */
function buildChildren(value, parentPath, children) {
	if (value === null || value === undefined) {
		return;
	}

	if (Array.isArray(value)) {
		value.forEach((item, index) => {
			const path = `${parentPath}.${index}`;
			const label = String(index);

			if (isScalar(item)) {
				children.push({
					id: path,
					label,
					path,
					value: item,
					isLeaf: true,
				});
				return;
			}

			const node = {
				id: path,
				label: `[${index}]`,
				path,
				isLeaf: false,
				children: [],
			};
			buildChildren(item, path, node.children);
			children.push(node);
		});
		return;
	}

	if (typeof value === 'object') {
		Object.entries(value).forEach(([key, child]) => {
			if (!key) {
				return;
			}

			const path = `${parentPath}.${key}`;

			if (isScalar(child)) {
				children.push({
					id: path,
					label: key,
					path,
					value: child,
					isLeaf: true,
				});
				return;
			}

			const node = {
				id: path,
				label: key,
				path,
				isLeaf: false,
				children: [],
			};
			buildChildren(child, path, node.children);
			children.push(node);
		});
	}
}

/**
 * @param {*} data
 * @return {{ id: string, label: string, path: string, isLeaf: boolean, children: Array<Object> }}
 */
function buildDisplayTree(data) {
	const root = {
		id: 'root',
		label: 'root',
		path: 'root',
		isLeaf: false,
		children: [],
	};

	if (data === null || data === undefined) {
		return root;
	}

	if (isScalar(data)) {
		root.children = [
			{
				id: 'root',
				label: 'root',
				path: 'root',
				value: data,
				isLeaf: true,
			},
		];
		return root;
	}

	buildChildren(data, 'root', root.children);
	return root;
}

/**
 * @param {*} value
 * @return {string}
 */
function formatScalarValue(value) {
	if (value === null) {
		return 'null';
	}

	if (value === undefined) {
		return '';
	}

	if (typeof value === 'string') {
		return `"${value}"`;
	}

	if (typeof value === 'boolean' || typeof value === 'number') {
		return String(value);
	}

	return String(value);
}

/**
 * @param {Object|null|undefined} data
 * @return {number}
 */
function countRootItems(data) {
	if (!data || typeof data !== 'object' || Array.isArray(data)) {
		return data === null || data === undefined ? 0 : 1;
	}

	return Object.keys(data).length;
}

/**
 * Read-only n8n-style tree for test input/output panels.
 *
 * @param {Object}      props
 * @param {string}      props.title
 * @param {Object|null} props.data
 * @param {boolean}     [props.embedded] Hide panel chrome when inside tab body.
 */
export default function TestDataTree({ title, data, embedded = false }) {
	const itemCount = countRootItems(data);
	const tree = useMemo(() => buildDisplayTree(data || {}), [data]);
	const hasData = itemCount > 0;

	return (
		<div
			className={
				embedded
					? 'dragwyb-af-test-io__panel dragwyb-af-test-io__panel--embedded'
					: 'dragwyb-af-test-io__panel'
			}
		>
			{!embedded && title && (
				<h4 className="dragwyb-af-test-io__panel-title">{title}</h4>
			)}

			{!hasData ? (
				<p className="dragwyb-af-test-io__empty">
					{__('No data', 'dragwyb-agentflow')}
				</p>
			) : (
				<div className="dragwyb-af-test-io__tree-wrap">
					<ul className="dragwyb-af-test-io__tree">
						<li className="dragwyb-af-test-io__branch">
							<button
								type="button"
								className="dragwyb-af-test-io__branch-btn"
								aria-expanded
								disabled
							>
								<span className="dragwyb-af-test-io__chevron">▾</span>
								<span className="dragwyb-af-test-io__branch-label">root</span>
								<span className="dragwyb-af-test-io__count">
									{sprintf(
										/* translators: %d: number of fields */
										__('%d items', 'dragwyb-agentflow'),
										itemCount
									)}
								</span>
							</button>
							<ul className="dragwyb-af-test-io__tree dragwyb-af-test-io__tree--nested">
								{(tree.children || []).map((child) => (
									<ReadOnlyBranch key={child.id} node={child} depth={0} />
								))}
							</ul>
						</li>
					</ul>
				</div>
			)}
		</div>
	);
}

/**
 * @param {Object} props
 * @param {Object} props.node
 * @param {number} props.depth
 */
function ReadOnlyBranch({ node, depth }) {
	const [open, setOpen] = useState(depth < 2);
	const children = node.children || [];

	if (node.isLeaf) {
		const fieldKey = node.label || node.path.split('.').pop() || '';
		const isResponse = fieldKey === 'response' || fieldKey === 'content';
		const rawValue = node.value;

		return (
			<li className="dragwyb-af-test-io__leaf">
				<div
					className={`dragwyb-af-test-io__field${isResponse ? ' dragwyb-af-test-io__field--response' : ''}`}
					style={{ paddingLeft: `${8 + depth * 14}px` }}
				>
					<span className="dragwyb-af-test-io__key">{fieldKey}</span>
					<span className="dragwyb-af-test-io__colon">:</span>
					{isResponse && typeof rawValue === 'string' ? (
						<pre className="dragwyb-af-test-io__response">{rawValue}</pre>
					) : (
						<span className="dragwyb-af-test-io__value">
							{formatScalarValue(rawValue)}
						</span>
					)}
				</div>
			</li>
		);
	}

	if (children.length === 0) {
		return null;
	}

	const childCount = children.length;

	return (
		<li className="dragwyb-af-test-io__branch">
			<button
				type="button"
				className="dragwyb-af-test-io__branch-btn"
				style={{ paddingLeft: `${8 + depth * 14}px` }}
				onClick={() => setOpen(!open)}
				aria-expanded={open}
			>
				<span className="dragwyb-af-test-io__chevron">{open ? '▾' : '▸'}</span>
				<span className="dragwyb-af-test-io__branch-label">{node.label}</span>
				<span className="dragwyb-af-test-io__count">
					{sprintf(
						/* translators: %d: number of nested fields */
						__('%d items', 'dragwyb-agentflow'),
						childCount
					)}
				</span>
			</button>
			{open && (
				<ul className="dragwyb-af-test-io__tree dragwyb-af-test-io__tree--nested">
					{children.map((child) => (
						<ReadOnlyBranch
							key={child.id}
							node={child}
							depth={depth + 1}
						/>
					))}
				</ul>
			)}
		</li>
	);
}
