import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import TestDataTree from './TestDataTree';

/**
 * Shows the result of "Test node" — Input / Output tabs with tree data.
 *
 * @param {Object}      props
 * @param {boolean}     props.success
 * @param {string|null} props.error
 * @param {Object|null} props.input
 * @param {Object|null} props.output
 */
export default function NodeTestResult({
	success,
	error,
	input = null,
	output = null,
}) {
	const [activeTab, setActiveTab] = useState('input');

	useEffect(() => {
		if (output && typeof output === 'object' && Object.keys(output).length > 0) {
			setActiveTab('output');
		}
	}, [output]);

	if (error) {
		return (
			<div
				className="dragaiw-builder-config__test-result dragaiw-builder-config__test-result--error"
				role="alert"
			>
				<h3>{__('Response', 'dragwyb-ai-agent-workflows')}</h3>
				<p>{error}</p>
			</div>
		);
	}

	const hasInput =
		input && typeof input === 'object' && Object.keys(input).length > 0;
	const hasOutput =
		output && typeof output === 'object' && Object.keys(output).length > 0;

	if (!hasInput && !hasOutput) {
		return null;
	}

	const showInput = activeTab === 'input';

	return (
		<div className="dragaiw-builder-config__test-result">
			<div className="dragaiw-builder-config__test-result-header">
				<h3>{__('Response', 'dragwyb-ai-agent-workflows')}</h3>
				<span
					className={
						success
							? 'dragaiw-builder-config__test-badge dragaiw-builder-config__test-badge--success'
							: 'dragaiw-builder-config__test-badge dragaiw-builder-config__test-badge--failed'
					}
				>
					{success
						? __('Success', 'dragwyb-ai-agent-workflows')
						: __('Failed', 'dragwyb-ai-agent-workflows')}
				</span>
			</div>

			<div className="dragaiw-test-io dragaiw-test-io--tabs">
				<div className="dragaiw-test-io__tabs" role="tablist">
					<button
						type="button"
						id="dragaiw-test-tab-input"
						role="tab"
						className={
							showInput
								? 'dragaiw-test-io__tab dragaiw-test-io__tab--active'
								: 'dragaiw-test-io__tab'
						}
						aria-selected={showInput}
						aria-controls="dragaiw-test-tabpanel"
						onClick={() => setActiveTab('input')}
					>
						{__('Input', 'dragwyb-ai-agent-workflows')}
					</button>
					<button
						type="button"
						id="dragaiw-test-tab-output"
						role="tab"
						className={
							!showInput
								? 'dragaiw-test-io__tab dragaiw-test-io__tab--active'
								: 'dragaiw-test-io__tab'
						}
						aria-selected={!showInput}
						aria-controls="dragaiw-test-tabpanel"
						onClick={() => setActiveTab('output')}
					>
						{__('Output', 'dragwyb-ai-agent-workflows')}
					</button>
				</div>

				<div
					id="dragaiw-test-tabpanel"
					className="dragaiw-test-io__body"
					role="tabpanel"
					aria-labelledby={
						showInput ? 'dragaiw-test-tab-input' : 'dragaiw-test-tab-output'
					}
				>
					{showInput ? (
						<TestDataTree data={input} embedded />
					) : (
						<TestDataTree data={output} embedded />
					)}
				</div>
			</div>
		</div>
	);
}
