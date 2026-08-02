import { useRef, useEffect } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const SAVE_STATUS_LABELS = {
	idle: '',
	dirty: __('Unsaved changes', 'dragwyb-agentflow'),
	saving: __('Saving…', 'dragwyb-agentflow'),
	saved: __('Saved', 'dragwyb-agentflow'),
	error: __(
		'Save failed — check your connection and try again.',
		'dragwyb-agentflow'
	),
};

/** @type {Record<number, string>} */
const WORKFLOW_STATUS_LABELS = {
	0: __('Draft', 'dragwyb-agentflow'),
	1: __('Active', 'dragwyb-agentflow'),
	2: __('Paused', 'dragwyb-agentflow'),
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
		<header className="dragwyb-af-builder-header">
			<div className="dragwyb-af-builder-header__left">
				{listUrl && (
					<a
						className="dragwyb-af-builder-header__back"
						href={listUrl}
						aria-label={__(
							'Back to workflows list',
							'dragwyb-agentflow'
						)}
					>
						{__('← Workflows', 'dragwyb-agentflow')}
					</a>
				)}
				<input
					type="text"
					className="dragwyb-af-builder-header__title"
					value={title}
					placeholder={__('Untitled workflow', 'dragwyb-agentflow')}
					aria-label={__('Workflow title', 'dragwyb-agentflow')}
					onChange={(event) => onTitleChange(event.target.value)}
				/>
				<span
					className={`dragwyb-af-builder-header__workflow-status dragwyb-af-builder-header__workflow-status--${isActive ? 'active' : workflowStatus === 2 ? 'paused' : 'draft'
						}`}
				>
					{statusLabel}
				</span>
			</div>
			<div className="dragwyb-af-builder-header__right">
				{testFlow?.statusMessage && (
					<span
						className="dragwyb-af-builder-header__test-status"
						role="status"
					>
						{testFlow.statusMessage}
					</span>
				)}
				<span
					className={`dragwyb-af-builder-header__status dragwyb-af-builder-header__status--${status}`}
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
							className="dragwyb-af-builder-header__import-input"
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
								'dragwyb-agentflow'
							)}
						>
							{__('Import', 'dragwyb-agentflow')}
						</Button>
					</>
				)}
				{typeof onExport === 'function' && (
					<Button
						isSecondary
						onClick={onExport}
						aria-label={__(
							'Export workflow as JSON',
							'dragwyb-agentflow'
						)}
					>
						{__('Export', 'dragwyb-agentflow')}
					</Button>
				)}
				{testFlow && (
					<div
						className="dragwyb-af-builder-header__test-wrap"
						ref={testWrapRef}
					>
						<Button
							isSecondary
							onClick={() => testFlow.setMenuOpen(!testFlow.menuOpen)}
							aria-expanded={testFlow.menuOpen}
							disabled={testFlow.listening}
						>
							{testFlow.listening
								? __('Listening…', 'dragwyb-agentflow')
								: __('Test Flow', 'dragwyb-agentflow')}
						</Button>
						{testFlow.menuOpen && (
							<div className="dragwyb-af-builder-header__test-menu">
								<button
									type="button"
									className="dragwyb-af-builder-header__test-menu-item"
									onClick={testFlow.listenNew}
								>
									{__(
										'Listen new response',
										'dragwyb-agentflow'
									)}
								</button>
								<button
									type="button"
									className="dragwyb-af-builder-header__test-menu-item"
									onClick={testFlow.useExisting}
								>
									{__(
										'Use existing data',
										'dragwyb-agentflow'
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
						{__('Chat', 'dragwyb-agentflow')}
					</Button>
				)}
				<Button
					isPrimary={!isActive}
					isSecondary={isActive}
					onClick={onToggleActive}
					disabled={toggleActiveBusy}
					aria-label={
						isActive
							? __('Pause workflow', 'dragwyb-agentflow')
							: __('Activate workflow', 'dragwyb-agentflow')
					}
				>
					{toggleActiveBusy
						? __('Updating…', 'dragwyb-agentflow')
						: isActive
							? __('Pause', 'dragwyb-agentflow')
							: __('Activate', 'dragwyb-agentflow')}
				</Button>
				<Button isPrimary onClick={onSave} disabled={saveDisabled}>
					{__('Save', 'dragwyb-agentflow')}
				</Button>
			</div>
		</header>
	);
}
