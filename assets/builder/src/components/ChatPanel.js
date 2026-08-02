/**
 * n8n-style builder Chat panel: type a message, run the workflow, show the reply.
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { Button, TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * @param {{
 *   open: boolean,
 *   onClose: () => void,
 *   messages: Array<{ id: string, role: 'user'|'assistant'|'system', content: string }>,
 *   sending: boolean,
 *   error: string,
 *   onSend: (text: string) => void,
 *   title?: string,
 *   initialMessages?: string[],
 * }} props
 */
export default function ChatPanel({
	open,
	onClose,
	messages,
	sending,
	error,
	onSend,
	title,
	initialMessages = [],
}) {
	const [draft, setDraft] = useState('');
	const listRef = useRef(null);

	useEffect(() => {
		if (!open || !listRef.current) {
			return;
		}

		listRef.current.scrollTop = listRef.current.scrollHeight;
	}, [open, messages, sending]);

	if (!open) {
		return null;
	}

	const handleSubmit = (event) => {
		event.preventDefault();
		const text = draft.trim();

		if (!text || sending) {
			return;
		}

		setDraft('');
		onSend(text);
	};

	const displayMessages =
		messages.length > 0
			? messages
			: initialMessages.map((content, index) => ({
					id: `welcome-${index}`,
					role: 'assistant',
					content,
			  }));

	return (
		<aside className="dragwyb-af-builder-chat" aria-label={__('Chat', 'dragwyb-agentflow')}>
			<div className="dragwyb-af-builder-chat__header">
				<div>
					<strong>{title || __('Chat', 'dragwyb-agentflow')}</strong>
					<p className="dragwyb-af-builder-chat__subtitle">
						{__(
							'Send a message to run this workflow (same as n8n’s Chat button).',
							'dragwyb-agentflow'
						)}
					</p>
				</div>
				<Button isSmall isSecondary onClick={onClose}>
					{__('Close', 'dragwyb-agentflow')}
				</Button>
			</div>

			<div className="dragwyb-af-builder-chat__messages" ref={listRef}>
				{displayMessages.map((message) => (
					<div
						key={message.id}
						className={`dragwyb-af-builder-chat__bubble dragwyb-af-builder-chat__bubble--${message.role}`}
					>
						{message.content}
					</div>
				))}
				{sending && (
					<div className="dragwyb-af-builder-chat__bubble dragwyb-af-builder-chat__bubble--assistant dragwyb-af-builder-chat__bubble--pending">
						{__('Thinking…', 'dragwyb-agentflow')}
					</div>
				)}
			</div>

			{error && (
				<p className="dragwyb-af-builder-chat__error" role="alert">
					{error}
				</p>
			)}

			<form className="dragwyb-af-builder-chat__composer" onSubmit={handleSubmit}>
				<TextareaControl
					label={__('Message', 'dragwyb-agentflow')}
					hideLabelFromVision
					value={draft}
					onChange={setDraft}
					placeholder={__('Type a message…', 'dragwyb-agentflow')}
					rows={2}
					disabled={sending}
				/>
				<Button isPrimary type="submit" disabled={sending || !draft.trim()}>
					{sending
						? __('Sending…', 'dragwyb-agentflow')
						: __('Send', 'dragwyb-agentflow')}
				</Button>
			</form>
		</aside>
	);
}
