import { useRef, useState, useEffect, useCallback } from '@wordpress/element';
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
function createPillElement(token, path) {
	const span = document.createElement('span');
	span.className = 'wfa-token-field__pill';
	span.contentEditable = 'false';
	span.dataset.token = token;
	span.textContent = pathToDisplayLabel(path);
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
function populateEditor(editor, value) {
	editor.innerHTML = '';

	segmentValueWithTokens(value).forEach((segment) => {
		if (segment.type === 'token') {
			editor.appendChild(
				createPillElement(segment.value, segment.path || '')
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
function insertTokenAtCursor(editor, token, path) {
	editor.focus();
	const selection = window.getSelection();
	const pill = createPillElement(token, path);
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
 * Prompt/message field with friendly variable pills; stores {{trigger.*}} tokens.
 */
export default function TokenField({
	label,
	value,
	required,
	payload,
	sourceLabel,
	onChange,
}) {
	const wrapperRef = useRef(null);
	const editorRef = useRef(null);
	const popoverRef = useRef(null);
	const lastSerializedRef = useRef(value || '');
	const [pickerOpen, setPickerOpen] = useState(false);
	const [popoverPos, setPopoverPos] = useState(null);
	const hasPayload =
		payload !== null &&
		payload !== undefined &&
		typeof payload === 'object' &&
		Object.keys(payload).length > 0;

	const updatePopoverPosition = useCallback(() => {
		if (!wrapperRef.current) {
			return;
		}

		const popoverWidth = 260;
		const gap = 8;
		const maxHeight = Math.min(360, window.innerHeight - 24);
		const rect = wrapperRef.current.getBoundingClientRect();

		let left = rect.left - popoverWidth - gap;
		let top = rect.top;

		if (left < 12) {
			left = rect.right + gap;
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

			insertTokenAtCursor(editor, token, path);
			syncFromEditor();
			editor.focus();
		},
		[value, onChange, syncFromEditor]
	);

	const openPicker = useCallback(() => {
		if (hasPayload) {
			updatePopoverPosition();
			setPickerOpen(true);
		}
	}, [hasPayload, updatePopoverPosition]);

	useEffect(() => {
		const editor = editorRef.current;

		if (!editor) {
			return;
		}

		const current = value || '';

		if (current === lastSerializedRef.current) {
			return;
		}

		populateEditor(editor, current);
		lastSerializedRef.current = current;
	}, [value]);

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

	return (
		<div
			ref={wrapperRef}
			className={`wfa-token-field${pickerOpen ? ' wfa-token-field--picker-open' : ''}`}
		>
			<label className="wfa-token-field__label">
				{label}
				{required && (
					<span className="wfa-token-field__required" aria-hidden="true">
						{' '}
						*
					</span>
				)}
			</label>

			<div
				ref={editorRef}
				className="wfa-token-field__editor"
				contentEditable
				role="textbox"
				aria-multiline="true"
				suppressContentEditableWarning
				onInput={syncFromEditor}
				onFocus={openPicker}
				onClick={openPicker}
			/>

			{pickerOpen && hasPayload && popoverPos && (
				<div
					ref={popoverRef}
					className="wfa-token-field__popover"
					style={{
						position: 'fixed',
						top: `${popoverPos.top}px`,
						left: `${popoverPos.left}px`,
						width: `${popoverPos.width}px`,
						maxHeight: `${popoverPos.maxHeight}px`,
					}}
				>
					<VariablePicker
						payload={payload}
						sourceLabel={sourceLabel}
						onSelect={insertToken}
						onClose={() => setPickerOpen(false)}
						embedded
						popover
					/>
				</div>
			)}

			{!hasPayload && (
				<p className="wfa-token-field__hint">
					{__(
						'Use Test Flow → Listen new response to load trigger variables.',
						'workflow-automate'
					)}
				</p>
			)}
		</div>
	);
}

/**
 * @param {string} fieldName
 * @param {Object} fieldSchema
 * @return {boolean}
 */
export function fieldSupportsVariables(fieldName, fieldSchema) {
	if (fieldSchema.supports_variables) {
		return true;
	}

	return /^(prompt|message|system_prompt|user_prompt)$/i.test(fieldName);
}
