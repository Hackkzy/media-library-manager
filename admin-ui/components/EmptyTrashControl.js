/**
 * WordPress dependencies
 */
import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const modalWrapClass = 'blp-mlm-modal-layer';
const backdropClass =
	'absolute inset-0 bg-slate-900/60 backdrop-blur-[1px] transition-opacity';
const panelClass =
	'relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl';

const EmptyTrashControl = () => {
	const [confirmOpen, setConfirmOpen] = useState(false);
	const [errorOpen, setErrorOpen] = useState(false);
	const [errorMessage, setErrorMessage] = useState('');
	const [isSubmitting, setIsSubmitting] = useState(false);
	const confirmPrimaryRef = useRef(null);

	useEffect(() => {
		if (confirmOpen && confirmPrimaryRef.current) {
			confirmPrimaryRef.current.focus();
		}
	}, [confirmOpen]);

	useEffect(() => {
		if (!confirmOpen && !errorOpen) {
			return;
		}
		const onKey = (e) => {
			if (e.key === 'Escape') {
				if (errorOpen) {
					setErrorOpen(false);
				} else if (!isSubmitting) {
					setConfirmOpen(false);
				}
			}
		};
		document.addEventListener('keydown', onKey);
		return () => document.removeEventListener('keydown', onKey);
	}, [confirmOpen, errorOpen, isSubmitting]);

	const handleEmptyTrash = async () => {
		setIsSubmitting(true);
		setConfirmOpen(false);
		try {
			await apiFetch({
				path: '/blp-mlm/trash/empty',
				method: 'POST',
			});
			window.location.reload();
		} catch (err) {
			setIsSubmitting(false);
			setErrorMessage(
				err?.message ||
					__(
						'Could not empty trash. Please try again.',
						'media-library-manager'
					)
			);
			setErrorOpen(true);
		}
	};

	return (
		<div className="blp-mlm-admin inline-block max-w-full font-sans text-slate-800 antialiased">
			<button
				type="button"
				className="button cursor-pointer disabled:cursor-not-allowed"
				onClick={() => setConfirmOpen(true)}
				disabled={isSubmitting}
			>
				{isSubmitting
					? __('Emptying…', 'media-library-manager')
					: __('Empty Trash', 'media-library-manager')}
			</button>

			{confirmOpen && (
				<div
					className={modalWrapClass}
					role="presentation"
					onClick={(e) => {
						if (e.target === e.currentTarget && !isSubmitting) {
							setConfirmOpen(false);
						}
					}}
				>
					<div className={backdropClass} aria-hidden />
					<div
						className={panelClass}
						role="dialog"
						aria-modal="true"
						aria-labelledby="blp-mlm-empty-trash-confirm-title"
						aria-describedby="blp-mlm-empty-trash-confirm-desc"
					>
						<h2
							id="blp-mlm-empty-trash-confirm-title"
							className="text-lg font-semibold text-slate-900"
						>
							{__('Empty trash?', 'media-library-manager')}
						</h2>
						<p
							id="blp-mlm-empty-trash-confirm-desc"
							className="mt-3 text-sm leading-relaxed text-slate-600"
						>
							{__(
								'This will permanently delete all media in the plugin trash. This cannot be undone.',
								'media-library-manager'
							)}
						</p>
						<div className="mt-6 flex flex-wrap justify-end gap-2">
							<button
								type="button"
								className="cursor-pointer rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
								onClick={() => setConfirmOpen(false)}
								disabled={isSubmitting}
							>
								{__('Cancel', 'media-library-manager')}
							</button>
							<button
								ref={confirmPrimaryRef}
								type="button"
								className="cursor-pointer rounded-lg border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
								onClick={handleEmptyTrash}
								disabled={isSubmitting}
							>
								{__('Empty trash', 'media-library-manager')}
							</button>
						</div>
					</div>
				</div>
			)}

			{errorOpen && (
				<div
					className={modalWrapClass}
					role="presentation"
					onClick={(e) => {
						if (e.target === e.currentTarget) {
							setErrorOpen(false);
						}
					}}
				>
					<div className={backdropClass} aria-hidden />
					<div
						className={panelClass}
						role="alertdialog"
						aria-modal="true"
						aria-labelledby="blp-mlm-empty-trash-error-title"
						aria-describedby="blp-mlm-empty-trash-error-desc"
					>
						<h2
							id="blp-mlm-empty-trash-error-title"
							className="text-lg font-semibold text-slate-900"
						>
							{__('Something went wrong', 'media-library-manager')}
						</h2>
						<p
							id="blp-mlm-empty-trash-error-desc"
							className="mt-3 text-sm leading-relaxed text-slate-600"
						>
							{errorMessage}
						</p>
						<div className="mt-6 flex justify-end">
							<button
								type="button"
								className="cursor-pointer rounded-lg border border-transparent bg-slate-800 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
								onClick={() => setErrorOpen(false)}
							>
								{__('OK', 'media-library-manager')}
							</button>
						</div>
					</div>
				</div>
			)}
		</div>
	);
};

export default EmptyTrashControl;
