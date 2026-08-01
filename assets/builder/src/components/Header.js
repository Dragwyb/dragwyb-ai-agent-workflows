import { useRef, useEffect } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const SAVE_STATUS_LABELS = {
	idle: '',
	dirty: __('Unsaved changes', 'workflow-automate'),
	saving: __('Saving…', 'workflow-automate'),
	saved: __('Saved', 'workflow-automate'),
	error: __(
		'Save failed — check your connection and try again.',
		'workflow-automate'
	),
};

/** @type {Record<number, string>} */
const WORKFLOW_STATUS_LABELS = {
	0: __('Draft', 'workflow-automate'),
	1: __('Active', 'workflow-automate'),
	2: __('Paused', 'workflow-automate'),
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
		<header className="aiawa-builder-header">
			<div className="aiawa-builder-header__left">
				{listUrl && (
					<a
						className="aiawa-builder-header__back"
						href={listUrl}
						aria-label={__(
							'Back to workflows list',
							'workflow-automate'
						)}
					>
						{__('← Workflows', 'workflow-automate')}
					</a>
				)}
				<input
					type="text"
					className="aiawa-builder-header__title"
					value={title}
					placeholder={__('Untitled workflow', 'workflow-automate')}
					aria-label={__('Workflow title', 'workflow-automate')}
					onChange={(event) => onTitleChange(event.target.value)}
				/>
				<span
					className={`aiawa-builder-header__workflow-status aiawa-builder-header__workflow-status--${isActive ? 'active' : workflowStatus === 2 ? 'paused' : 'draft'
						}`}
				>
					{statusLabel}
				</span>
			</div>
			<div className="aiawa-builder-header__right">
				{testFlow?.statusMessage && (
					<span
						className="aiawa-builder-header__test-status"
						role="status"
					>
						{testFlow.statusMessage}
					</span>
				)}
				<span
					className={`aiawa-builder-header__status aiawa-builder-header__status--${status}`}
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
							className="aiawa-builder-header__import-input"
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
								'workflow-automate'
							)}
						>
							{__('Import', 'workflow-automate')}
						</Button>
					</>
				)}
				{typeof onExport === 'function' && (
					<Button
						isSecondary
						onClick={onExport}
						aria-label={__(
							'Export workflow as JSON',
							'workflow-automate'
						)}
					>
						{__('Export', 'workflow-automate')}
					</Button>
				)}
				{testFlow && (
					<div
						className="aiawa-builder-header__test-wrap"
						ref={testWrapRef}
					>
						<Button
							isSecondary
							onClick={() => testFlow.setMenuOpen(!testFlow.menuOpen)}
							aria-expanded={testFlow.menuOpen}
							disabled={testFlow.listening}
						>
							{testFlow.listening
								? __('Listening…', 'workflow-automate')
								: __('Test Flow', 'workflow-automate')}
						</Button>
						{testFlow.menuOpen && (
							<div className="aiawa-builder-header__test-menu">
								<button
									type="button"
									className="aiawa-builder-header__test-menu-item"
									onClick={testFlow.listenNew}
								>
									{__(
										'Listen new response',
										'workflow-automate'
									)}
								</button>
								<button
									type="button"
									className="aiawa-builder-header__test-menu-item"
									onClick={testFlow.useExisting}
								>
									{__(
										'Use existing data',
										'workflow-automate'
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
						{__('Chat', 'workflow-automate')}
					</Button>
				)}
				<Button
					isPrimary={!isActive}
					isSecondary={isActive}
					onClick={onToggleActive}
					disabled={toggleActiveBusy}
					aria-label={
						isActive
							? __('Pause workflow', 'workflow-automate')
							: __('Activate workflow', 'workflow-automate')
					}
				>
					{toggleActiveBusy
						? __('Updating…', 'workflow-automate')
						: isActive
							? __('Pause', 'workflow-automate')
							: __('Activate', 'workflow-automate')}
				</Button>
				<Button isPrimary onClick={onSave} disabled={saveDisabled}>
					{__('Save', 'workflow-automate')}
				</Button>
			</div>
		</header>
	);
}
