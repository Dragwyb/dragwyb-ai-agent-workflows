import { useState, useCallback, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import {
	startTestListen,
	stopTestListen,
	fetchTestStatus,
	runWorkflow,
} from '../api';

const POLL_MS = 2000;
const LISTEN_TIMEOUT_MS = 120000;

/**
 * Test flow: listen for a new trigger payload or run with saved sample data.
 *
 * @param {number} workflowId
 * @param {Object} options
 * @param {Function} options.persistBeforeTest Save title/graph before testing.
 * @param {Function} [options.onSampleCaptured]
 */
export default function useTestFlow(workflowId, { persistBeforeTest, onSampleCaptured }) {
	const [menuOpen, setMenuOpen] = useState(false);
	const [listening, setListening] = useState(false);
	const [statusMessage, setStatusMessage] = useState('');
	const pollRef = useRef(null);
	const timeoutRef = useRef(null);

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

				if (!status.listening && status.has_sample) {
					clearTimers();
					setListening(false);
					setStatusMessage(
						__('Sample captured. You can use existing data to test.', 'workflow-automate')
					);

					if (onSampleCaptured) {
						onSampleCaptured(status.sample_payload);
					}
				}
			} catch (error) {
				clearTimers();
				setListening(false);
				setStatusMessage(
					error && error.message
						? error.message
						: __('Could not check listen status.', 'workflow-automate')
				);
			}
		}, POLL_MS);

		timeoutRef.current = setTimeout(async () => {
			clearTimers();
			setListening(false);

			try {
				await stopTestListen(workflowId);
			} catch (error) {
				// Ignore stop errors after timeout.
			}

			setStatusMessage(
				__('Listen timed out. Fire your trigger and try again.', 'workflow-automate')
			);
		}, LISTEN_TIMEOUT_MS);
	}, [workflowId, clearTimers, onSampleCaptured]);

	const listenNew = useCallback(async () => {
		setMenuOpen(false);

		if (!workflowId) {
			return;
		}

		setStatusMessage(__('Saving…', 'workflow-automate'));

		try {
			await persistBeforeTest();
			setStatusMessage(
				__('Listening for the next trigger response…', 'workflow-automate')
			);
			setListening(true);
			await startTestListen(workflowId);
			pollUntilCaptured();
		} catch (error) {
			setListening(false);
			setStatusMessage(
				error && error.message
					? error.message
					: __('Could not start listening.', 'workflow-automate')
			);
		}
	}, [workflowId, persistBeforeTest, pollUntilCaptured]);

	const useExisting = useCallback(async () => {
		setMenuOpen(false);

		if (!workflowId) {
			return;
		}

		setStatusMessage(__('Running workflow with saved data…', 'workflow-automate'));

		try {
			await persistBeforeTest();
			const status = await fetchTestStatus(workflowId);

			if (!status.has_sample) {
				setStatusMessage(
					__(
						'No saved sample yet. Use “Listen new response” first.',
						'workflow-automate'
					)
				);
				return;
			}

			await runWorkflow(workflowId);
			setStatusMessage(__('Test run completed.', 'workflow-automate'));
		} catch (error) {
			setStatusMessage(
				error && error.message
					? error.message
					: __('Test run failed.', 'workflow-automate')
			);
		}
	}, [workflowId, persistBeforeTest]);

	const stopListening = useCallback(async () => {
		clearTimers();
		setListening(false);

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
