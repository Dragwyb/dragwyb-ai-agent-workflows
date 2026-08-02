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
				className="dragwyb-af-builder-config__test-result dragwyb-af-builder-config__test-result--error"
				role="alert"
			>
				<h3>{__('Response', 'dragwyb-agentflow')}</h3>
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
		<div className="dragwyb-af-builder-config__test-result">
			<div className="dragwyb-af-builder-config__test-result-header">
				<h3>{__('Response', 'dragwyb-agentflow')}</h3>
				<span
					className={
						success
							? 'dragwyb-af-builder-config__test-badge dragwyb-af-builder-config__test-badge--success'
							: 'dragwyb-af-builder-config__test-badge dragwyb-af-builder-config__test-badge--failed'
					}
				>
					{success
						? __('Success', 'dragwyb-agentflow')
						: __('Failed', 'dragwyb-agentflow')}
				</span>
			</div>

			<div className="dragwyb-af-test-io dragwyb-af-test-io--tabs">
				<div className="dragwyb-af-test-io__tabs" role="tablist">
					<button
						type="button"
						id="dragwyb-af-test-tab-input"
						role="tab"
						className={
							showInput
								? 'dragwyb-af-test-io__tab dragwyb-af-test-io__tab--active'
								: 'dragwyb-af-test-io__tab'
						}
						aria-selected={showInput}
						aria-controls="dragwyb-af-test-tabpanel"
						onClick={() => setActiveTab('input')}
					>
						{__('Input', 'dragwyb-agentflow')}
					</button>
					<button
						type="button"
						id="dragwyb-af-test-tab-output"
						role="tab"
						className={
							!showInput
								? 'dragwyb-af-test-io__tab dragwyb-af-test-io__tab--active'
								: 'dragwyb-af-test-io__tab'
						}
						aria-selected={!showInput}
						aria-controls="dragwyb-af-test-tabpanel"
						onClick={() => setActiveTab('output')}
					>
						{__('Output', 'dragwyb-agentflow')}
					</button>
				</div>

				<div
					id="dragwyb-af-test-tabpanel"
					className="dragwyb-af-test-io__body"
					role="tabpanel"
					aria-labelledby={
						showInput ? 'dragwyb-af-test-tab-input' : 'dragwyb-af-test-tab-output'
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
