import { useRef, useEffect } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const SAVE_STATUS_LABELS = {
	idle: '',
	dirty: __('Unsaved changes', 'dragwyb-ai-agent-workflows'),
	saving: __('Saving…', 'dragwyb-ai-agent-workflows'),
	saved: __('Saved', 'dragwyb-ai-agent-workflows'),
	error: __(
		'Save failed — check your connection and try again.',
		'dragwyb-ai-agent-workflows'
	),
};

/** @type {Record<number, string>} */
const WORKFLOW_STATUS_LABELS = {
	0: __('Draft', 'dragwyb-ai-agent-workflows'),
	1: __('Active', 'dragwyb-ai-agent-workflows'),
	2: __('Paused', 'dragwyb-ai-agent-workflows'),
};

/**
 * Top bar: back link, editable title, workflow status, import/export, test, save, activate/pause.
 */
export default function Header({
	title,
	onTitleChange,
	status,
	workflowStatus,
	onToggleActive,
	toggleActiveBusy,
	onSave,
	onExport,
	onImportFile,
	listUrl,
	saveDisabled,
	testFlow,
	showChat,
	chatOpen,
	onToggleChat,
}) {
	const isActive = workflowStatus === 1;
	const statusLabel =
		WORKFLOW_STATUS_LABELS[workflowStatus] || WORKFLOW_STATUS_LABELS[0];
	const testWrapRef = useRef(null);
	const importInputRef = useRef(null);

	useEffect(() => {
		if (!testFlow?.menuOpen) {
			return undefined;
		}

		const onPointerDown = (event) => {
			if (
				testWrapRef.current &&
				!testWrapRef.current.contains(event.target)
			) {
				testFlow.setMenuOpen(false);
			}
		};

		document.addEventListener('mousedown', onPointerDown);

		return () => {
			document.removeEventListener('mousedown', onPointerDown);
		};
	}, [testFlow]);

	return (
		<header className="dragaiw-builder-header">
			<div className="dragaiw-builder-header__left">
				{listUrl && (
					<a
						className="dragaiw-builder-header__back"
						href={listUrl}
						aria-label={__(
							'Back to workflows list',
							'dragwyb-ai-agent-workflows'
						)}
					>
						{__('← Workflows', 'dragwyb-ai-agent-workflows')}
					</a>
				)}
				<input
					type="text"
					className="dragaiw-builder-header__title"
					value={title}
					placeholder={__('Untitled workflow', 'dragwyb-ai-agent-workflows')}
					aria-label={__('Workflow title', 'dragwyb-ai-agent-workflows')}
					onChange={(event) => onTitleChange(event.target.value)}
				/>
				<span
					className={`dragaiw-builder-header__workflow-status dragaiw-builder-header__workflow-status--${isActive ? 'active' : workflowStatus === 2 ? 'paused' : 'draft'
						}`}
				>
					{statusLabel}
				</span>
			</div>
			<div className="dragaiw-builder-header__right">
				{testFlow?.statusMessage && (
					<span
						className="dragaiw-builder-header__test-status"
						role="status"
					>
						{testFlow.statusMessage}
					</span>
				)}
				<span
					className={`dragaiw-builder-header__status dragaiw-builder-header__status--${status}`}
					role="status"
				>
					{SAVE_STATUS_LABELS[status] || ''}
				</span>
				{typeof onImportFile === 'function' && (
					<>
						<input
							ref={importInputRef}
							type="file"
							accept="application/json,.json"
							className="dragaiw-builder-header__import-input"
							aria-hidden="true"
							tabIndex={-1}
							onChange={(event) => {
								const file = event.target.files?.[0] || null;
								event.target.value = '';

								if (file) {
									onImportFile(file);
								}
							}}
						/>
						<Button
							isSecondary
							onClick={() => importInputRef.current?.click()}
							aria-label={__(
								'Import workflow from JSON',
								'dragwyb-ai-agent-workflows'
							)}
						>
							{__('Import', 'dragwyb-ai-agent-workflows')}
						</Button>
					</>
				)}
				{typeof onExport === 'function' && (
					<Button
						isSecondary
						onClick={onExport}
						aria-label={__(
							'Export workflow as JSON',
							'dragwyb-ai-agent-workflows'
						)}
					>
						{__('Export', 'dragwyb-ai-agent-workflows')}
					</Button>
				)}
				{testFlow && (
					<div
						className="dragaiw-builder-header__test-wrap"
						ref={testWrapRef}
					>
						<Button
							isSecondary
							onClick={() => testFlow.setMenuOpen(!testFlow.menuOpen)}
							aria-expanded={testFlow.menuOpen}
							disabled={testFlow.listening}
						>
							{testFlow.listening
								? __('Listening…', 'dragwyb-ai-agent-workflows')
								: __('Test Flow', 'dragwyb-ai-agent-workflows')}
						</Button>
						{testFlow.menuOpen && (
							<div className="dragaiw-builder-header__test-menu">
								<button
									type="button"
									className="dragaiw-builder-header__test-menu-item"
									onClick={testFlow.listenNew}
								>
									{__(
										'Listen new response',
										'dragwyb-ai-agent-workflows'
									)}
								</button>
								<button
									type="button"
									className="dragaiw-builder-header__test-menu-item"
									onClick={testFlow.useExisting}
								>
									{__(
										'Use existing data',
										'dragwyb-ai-agent-workflows'
									)}
								</button>
							</div>
						)}
					</div>
				)}
				{showChat && (
					<Button
						isSecondary={chatOpen}
						isPrimary={!chatOpen}
						onClick={onToggleChat}
						aria-pressed={chatOpen}
					>
						{__('Chat', 'dragwyb-ai-agent-workflows')}
					</Button>
				)}
				<Button
					isPrimary={!isActive}
					isSecondary={isActive}
					onClick={onToggleActive}
					disabled={toggleActiveBusy}
					aria-label={
						isActive
							? __('Pause workflow', 'dragwyb-ai-agent-workflows')
							: __('Activate workflow', 'dragwyb-ai-agent-workflows')
					}
				>
					{toggleActiveBusy
						? __('Updating…', 'dragwyb-ai-agent-workflows')
						: isActive
							? __('Pause', 'dragwyb-ai-agent-workflows')
							: __('Activate', 'dragwyb-ai-agent-workflows')}
				</Button>
				<Button isPrimary onClick={onSave} disabled={saveDisabled}>
					{__('Save', 'dragwyb-ai-agent-workflows')}
				</Button>
			</div>
		</header>
	);
}
