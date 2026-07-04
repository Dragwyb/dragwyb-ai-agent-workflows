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
 * Top bar: back link, editable title, workflow status, save, activate/pause.
 *
 * @param {Object}   props
 * @param {string}   props.title
 * @param {Function} props.onTitleChange
 * @param {string}   props.status              Save status (idle/dirty/saving/saved/error).
 * @param {number}   props.workflowStatus      Workflow lifecycle status (0 draft, 1 active, 2 paused).
 * @param {Function} props.onToggleActive      Activate when draft/paused, pause when active.
 * @param {boolean}  props.toggleActiveBusy    True while a status change request is in flight.
 * @param {Function} props.onSave
 * @param {string}   props.listUrl
 * @param {boolean}  props.saveDisabled
 */
export default function Header({
	title,
	onTitleChange,
	status,
	workflowStatus,
	onToggleActive,
	toggleActiveBusy,
	onSave,
	listUrl,
	saveDisabled,
}) {
	const isActive = workflowStatus === 1;
	const statusLabel =
		WORKFLOW_STATUS_LABELS[workflowStatus] || WORKFLOW_STATUS_LABELS[0];

	return (
		<header className="wfa-builder-header">
			<div className="wfa-builder-header__left">
				{listUrl && (
					<a
						className="wfa-builder-header__back"
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
					className="wfa-builder-header__title"
					value={title}
					placeholder={__('Untitled workflow', 'workflow-automate')}
					aria-label={__('Workflow title', 'workflow-automate')}
					onChange={(event) => onTitleChange(event.target.value)}
				/>
				<span
					className={`wfa-builder-header__workflow-status wfa-builder-header__workflow-status--${
						isActive ? 'active' : workflowStatus === 2 ? 'paused' : 'draft'
					}`}
				>
					{statusLabel}
				</span>
			</div>
			<div className="wfa-builder-header__right">
				<span
					className={`wfa-builder-header__status wfa-builder-header__status--${status}`}
					role="status"
				>
					{SAVE_STATUS_LABELS[status] || ''}
				</span>
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
