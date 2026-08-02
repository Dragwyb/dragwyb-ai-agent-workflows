import {
	useRef,
	useState,
	useEffect,
	useCallback,
	createPortal,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import VariablePicker from './VariablePicker';
import {
	pathToDisplayLabel,
	segmentValueWithTokens,
} from '../utils/payloadVariables';

/**
 * @param {string} token
 * @param {string} path
 * @return {HTMLSpanElement}
 */
function createPillElement(token, path, nodeLabels = {}) {
	const span = document.createElement('span');
	span.className = 'dragwyb-af-token-field__pill';
	span.contentEditable = 'false';
	span.dataset.token = token;
	const label =
		path === 'trigger'
			? 'All data (JSON)'
			: pathToDisplayLabel(path, nodeLabels);
	span.textContent = label || path;
	return span;
}

/**
 * @param {HTMLElement} editor
 * @return {string}
 */
function serializeEditor(editor) {
	let result = '';

	editor.childNodes.forEach((node) => {
		if (node.nodeType === Node.TEXT_NODE) {
			result += node.textContent;
			return;
		}

		if (node.nodeType === Node.ELEMENT_NODE && node.dataset?.token) {
			result += node.dataset.token;
		}
	});

	return result;
}

/**
 * @param {HTMLElement} editor
 * @param {string}      value
 * @return {void}
 */
function populateEditor(editor, value, nodeLabels = {}) {
	editor.innerHTML = '';

	segmentValueWithTokens(value).forEach((segment) => {
		if (segment.type === 'token') {
			editor.appendChild(
				createPillElement(segment.value, segment.path || '', nodeLabels)
			);
			return;
		}

		if (segment.value) {
			editor.appendChild(document.createTextNode(segment.value));
		}
	});
}

/**
 * @param {HTMLElement} editor
 * @param {string}      token
 * @param {string}      path
 * @return {void}
 */
function insertTokenAtCursor(editor, token, path, nodeLabels = {}) {
	editor.focus();
	const selection = window.getSelection();
	const pill = createPillElement(token, path, nodeLabels);
	const space = document.createTextNode(' ');

	if (!selection || selection.rangeCount === 0) {
		editor.appendChild(pill);
		editor.appendChild(space);
		return;
	}

	const range = selection.getRangeAt(0);
	range.deleteContents();
	range.insertNode(space);
	range.insertNode(pill);
	range.setStartAfter(space);
	range.collapse(true);
	selection.removeAllRanges();
	selection.addRange(range);
}

/**
 * Prompt/message field with variable pills for trigger + prior steps.
 */
export default function TokenField({
	label,
	value,
	required,
	variableSources = [],
	nodeLabels = {},
	onChange,
}) {
	const wrapperRef = useRef(null);
	const editorRef = useRef(null);
	const popoverRef = useRef(null);
	const lastSerializedRef = useRef(value || '');
	const [pickerOpen, setPickerOpen] = useState(false);
	const [popoverPos, setPopoverPos] = useState(null);
	const hasVariables = variableSources.some(
		(source) => (source.tree?.children || []).length > 0
	);

	const updatePopoverPosition = useCallback(() => {
		if (!wrapperRef.current) {
			return;
		}

		const popoverWidth = 300;
		const gap = 8;
		const maxHeight = Math.min(420, window.innerHeight - 24);
		const rect = wrapperRef.current.getBoundingClientRect();

		// Prefer left of the field. Fall back below — never open off-screen right.
		let left = rect.left - popoverWidth - gap;
		let top = rect.top;

		if (left < 12) {
			left = Math.max(
				12,
				Math.min(rect.left, window.innerWidth - popoverWidth - 12)
			);
			top = rect.bottom + gap;
		}

		if (top + maxHeight > window.innerHeight - 12) {
			top = Math.max(12, window.innerHeight - maxHeight - 12);
		}

		setPopoverPos({
			top,
			left,
			width: popoverWidth,
			maxHeight,
		});
	}, []);

	const syncFromEditor = useCallback(() => {
		const editor = editorRef.current;

		if (!editor) {
			return;
		}

		const next = serializeEditor(editor);
		lastSerializedRef.current = next;
		onChange(next);
	}, [onChange]);

	const insertToken = useCallback(
		(token, path) => {
			const editor = editorRef.current;

			if (!editor) {
				const current = value || '';
				const next = `${current}${current && !current.endsWith(' ') ? ' ' : ''}${token}`;
				lastSerializedRef.current = next;
				onChange(next);
				return;
			}

			insertTokenAtCursor(editor, token, path, nodeLabels);
			syncFromEditor();
			editor.focus();
		},
		[value, onChange, syncFromEditor, nodeLabels]
	);

	const openPicker = useCallback(() => {
		if (hasVariables) {
			updatePopoverPosition();
			setPickerOpen(true);
		}
	}, [hasVariables, updatePopoverPosition]);

	useEffect(() => {
		const editor = editorRef.current;

		if (!editor) {
			return;
		}

		const current = value || '';
		const inEditor = serializeEditor(editor);

		if (current === inEditor) {
			lastSerializedRef.current = current;
			return;
		}

		populateEditor(editor, current, nodeLabels);
		lastSerializedRef.current = current;
	}, [value, nodeLabels]);

	useEffect(() => {
		if (!pickerOpen) {
			return undefined;
		}

		const onReposition = () => updatePopoverPosition();

		window.addEventListener('resize', onReposition);
		window.addEventListener('scroll', onReposition, true);

		return () => {
			window.removeEventListener('resize', onReposition);
			window.removeEventListener('scroll', onReposition, true);
		};
	}, [pickerOpen, updatePopoverPosition]);

	useEffect(() => {
		if (!pickerOpen) {
			return undefined;
		}

		const onPointerDown = (event) => {
			const inField =
				wrapperRef.current &&
				wrapperRef.current.contains(event.target);
			const inPopover =
				popoverRef.current &&
				popoverRef.current.contains(event.target);

			if (!inField && !inPopover) {
				setPickerOpen(false);
			}
		};

		document.addEventListener('mousedown', onPointerDown);

		return () => {
			document.removeEventListener('mousedown', onPointerDown);
		};
	}, [pickerOpen]);

	const popover =
		pickerOpen && hasVariables && popoverPos
			? createPortal(
					<div
						ref={popoverRef}
						className="dragwyb-af-token-field__popover"
						style={{
							position: 'fixed',
							top: `${popoverPos.top}px`,
							left: `${popoverPos.left}px`,
							width: `${popoverPos.width}px`,
							height: `${popoverPos.maxHeight}px`,
							maxHeight: `${popoverPos.maxHeight}px`,
						}}
					>
						<VariablePicker
							sources={variableSources}
							nodeLabels={nodeLabels}
							onSelect={insertToken}
							onClose={() => setPickerOpen(false)}
							embedded
							popover
							showSearch
						/>
					</div>,
					document.body
			  )
			: null;

	return (
		<div
			ref={wrapperRef}
			className={`dragwyb-af-token-field${pickerOpen ? ' dragwyb-af-token-field--picker-open' : ''}`}
		>
			<div className="dragwyb-af-token-field__header">
				<label className="dragwyb-af-token-field__label">
					{label}
					{required && (
						<span
							className="dragwyb-af-token-field__required"
							aria-hidden="true"
						>
							{' '}
							*
						</span>
					)}
				</label>
				{hasVariables && (
					<button
						type="button"
						className="dragwyb-af-token-field__insert"
						onClick={openPicker}
					>
						{__('Insert variable', 'dragwyb-agentflow')}
					</button>
				)}
			</div>

			<div
				ref={editorRef}
				className="dragwyb-af-token-field__editor"
				contentEditable
				role="textbox"
				aria-multiline="true"
				suppressContentEditableWarning
				onInput={syncFromEditor}
				onFocus={openPicker}
				onClick={openPicker}
			/>

			{popover}

			{!hasVariables && (
				<p className="dragwyb-af-token-field__hint">
					{__(
						'Add steps above this node, or use Test Flow → Listen to load trigger variables.',
						'dragwyb-agentflow'
					)}
				</p>
			)}
		</div>
	);
}

/**
 * Whether an action string field should offer the variable picker.
 *
 * Defaults to true so WordPress / integration actions can map trigger fields.
 * Opt out with `supports_variables: false`, or via the sensitive-name blocklist.
 *
 * @param {string} fieldName
 * @param {Object} fieldSchema
 * @return {boolean}
 */
export function fieldSupportsVariables(fieldName, fieldSchema) {
	if (fieldSchema.supports_variables === false) {
		return false;
	}

	if (fieldSchema.supports_variables === true) {
		return true;
	}

	if (
		/^(password|secret|api_key|api_secret|access_token|refresh_token|client_secret|private_key)$/i.test(
			fieldName
		)
	) {
		return false;
	}

	return true;
}
