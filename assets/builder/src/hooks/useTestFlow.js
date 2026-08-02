import { useState, useCallback, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import {
	startTestListen,
	stopTestListen,
	fetchTestStatus,
	runWorkflow,
} from '../api';
import { sampleMatchesTrigger } from '../utils/testSample';

const POLL_MS = 2000;
const LISTEN_TIMEOUT_MS = 120000;

/**
 * @param {string|null|undefined} capturedAt
 * @param {string|null|undefined} startedAt
 * @return {boolean}
 */
function isCaptureAfterListenStart(capturedAt, startedAt) {
	if (!capturedAt || !startedAt) {
		return false;
	}

	return String(capturedAt) >= String(startedAt);
}

/**
 * Test flow: listen for a new trigger payload or run with saved sample data.
 *
 * @param {number} workflowId
 * @param {Object} options
 * @param {Function} options.persistBeforeTest Save title/graph before testing.
 * @param {Function} [options.hasTrigger]      Returns true when graph has a trigger node.
 * @param {Function} [options.getTriggerType]  Returns current trigger slug.
 * @param {Function} [options.onSampleCaptured]
 */
export default function useTestFlow(
	workflowId,
	{ persistBeforeTest, hasTrigger, getTriggerType, onSampleCaptured }
) {
	const [menuOpen, setMenuOpen] = useState(false);
	const [listening, setListening] = useState(false);
	const [statusMessage, setStatusMessage] = useState('');
	const pollRef = useRef(null);
	const timeoutRef = useRef(null);
	const listenStartedAtRef = useRef(null);

	const clearTimers = useCallback(() => {
		if (pollRef.current) {
			clearInterval(pollRef.current);
			pollRef.current = null;
		}

		if (timeoutRef.current) {
			clearTimeout(timeoutRef.current);
			timeoutRef.current = null;
		}
	}, []);

	useEffect(() => () => clearTimers(), [clearTimers]);

	const pollUntilCaptured = useCallback(() => {
		clearTimers();

		pollRef.current = setInterval(async () => {
			try {
				const status = await fetchTestStatus(workflowId);
				const startedAt = listenStartedAtRef.current;

				if (status.listening) {
					return;
				}

				if (
					status.has_sample &&
					isCaptureAfterListenStart(status.captured_at, startedAt) &&
					sampleMatchesTrigger(
						status,
						getTriggerType ? getTriggerType() : null
					)
				) {
					clearTimers();
					setListening(false);
					setStatusMessage(
						__(
							'Sample captured. You can use existing data to test.',
							'ai-agent-workflow-automation'
						)
					);

					if (onSampleCaptured) {
						onSampleCaptured(status.sample_payload, status);
					}
				}
			} catch (error) {
				clearTimers();
				setListening(false);
				setStatusMessage(
					error && error.message
						? error.message
						: __('Could not check listen status.', 'ai-agent-workflow-automation')
				);
			}
		}, POLL_MS);

		timeoutRef.current = setTimeout(async () => {
			clearTimers();
			setListening(false);
			listenStartedAtRef.current = null;

			try {
				await stopTestListen(workflowId);
			} catch (error) {
				// Ignore stop errors after timeout.
			}

			setStatusMessage(
				__(
					'Listen timed out. Fire your trigger and try again.',
					'ai-agent-workflow-automation'
				)
			);
		}, LISTEN_TIMEOUT_MS);
	}, [workflowId, clearTimers, onSampleCaptured, getTriggerType]);

	const beginListening = useCallback(
		(startedAt) => {
			listenStartedAtRef.current = startedAt || null;
			setListening(true);
			setStatusMessage(
				__('Listening for the next trigger response…', 'ai-agent-workflow-automation')
			);
			pollUntilCaptured();
		},
		[pollUntilCaptured]
	);

	// Resume polling when the builder reloads while a listen session is active.
	useEffect(() => {
		if (!workflowId) {
			return undefined;
		}

		let cancelled = false;

		fetchTestStatus(workflowId)
			.then((status) => {
				if (cancelled || !status.listening) {
					return;
				}

				beginListening(status.started_at || null);
			})
			.catch(() => {});

		return () => {
			cancelled = true;
		};
	}, [workflowId, beginListening]);

	const listenNew = useCallback(async () => {
		setMenuOpen(false);

		if (!workflowId) {
			return;
		}

		if (hasTrigger && !hasTrigger()) {
			setStatusMessage(
				__(
					'Add a trigger block first, then listen again.',
					'ai-agent-workflow-automation'
				)
			);
			return;
		}

		setStatusMessage(__('Saving…', 'ai-agent-workflow-automation'));

		try {
			await persistBeforeTest();

			const status = await startTestListen(workflowId);

			if (!status.listening) {
				throw new Error(
					__('Server did not enter listen mode.', 'ai-agent-workflow-automation')
				);
			}

			beginListening(status.started_at || null);
		} catch (error) {
			setListening(false);
			listenStartedAtRef.current = null;
			setStatusMessage(
				error && error.message
					? error.message
					: __('Could not start listening.', 'ai-agent-workflow-automation')
			);
		}
	}, [workflowId, persistBeforeTest, hasTrigger, beginListening]);

	const useExisting = useCallback(async () => {
		setMenuOpen(false);

		if (!workflowId) {
			return;
		}

		setStatusMessage(__('Running workflow with saved data…', 'ai-agent-workflow-automation'));

		try {
			await persistBeforeTest();
			const status = await fetchTestStatus(workflowId);

			if (
				!sampleMatchesTrigger(
					status,
					getTriggerType ? getTriggerType() : null
				)
			) {
				setStatusMessage(
					__(
						'No saved sample for this trigger. Use “Listen new response” first.',
						'ai-agent-workflow-automation'
					)
				);
				return;
			}

			if (!status.has_sample) {
				setStatusMessage(
					__(
						'No saved sample yet. Use “Listen new response” first.',
						'ai-agent-workflow-automation'
					)
				);
				return;
			}

			await runWorkflow(workflowId);
			setStatusMessage(__('Test run completed.', 'ai-agent-workflow-automation'));
		} catch (error) {
			setStatusMessage(
				error && error.message
					? error.message
					: __('Test run failed.', 'ai-agent-workflow-automation')
			);
		}
	}, [workflowId, persistBeforeTest, getTriggerType]);

	const stopListening = useCallback(async () => {
		clearTimers();
		setListening(false);
		listenStartedAtRef.current = null;

		if (workflowId) {
			try {
				await stopTestListen(workflowId);
			} catch (error) {
				// Ignore.
			}
		}

		setStatusMessage('');
	}, [workflowId, clearTimers]);

	return {
		menuOpen,
		setMenuOpen,
		listening,
		statusMessage,
		listenNew,
		useExisting,
		stopListening,
	};
}
