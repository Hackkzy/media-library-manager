/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const modalWrapClass = 'blp-mlm-modal-layer';
const backdropClass =
	'absolute inset-0 bg-slate-900/60 backdrop-blur-[1px] transition-opacity';
const panelClass =
	'relative w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl';

const Modal = ({ setProUpgradeOpen, upgradeDescription }) => {
	return (
		<div
			className={modalWrapClass}
			role="presentation"
			onClick={(e) => {
				if (e.target === e.currentTarget) {
					setProUpgradeOpen(false);
				}
			}}
		>
			<div className={backdropClass} aria-hidden />
			<div
				className={panelClass}
				role="dialog"
				aria-modal="true"
				aria-labelledby="blp-mlm-pro-upgrade-title"
				aria-describedby="blp-mlm-pro-upgrade-desc"
			>
				<h2
					id="blp-mlm-pro-upgrade-title"
					className="text-lg font-semibold text-slate-900"
				>
					{__('Upgrade to Pro', 'media-library-manager')}
				</h2>
				<p
					id="blp-mlm-pro-upgrade-desc"
					className="mt-3 text-sm leading-relaxed text-slate-600"
				>
					{upgradeDescription}
				</p>
				<div className="mt-6 flex justify-end gap-3">
					<a
						href="https://www.medialibrarymanager.com/"
						target="_blank"
						rel="noopener noreferrer"
						className="inline-flex items-center justify-center cursor-pointer rounded-lg border border-transparent bg-wp-blue px-4 py-2 text-sm font-medium text-white shadow-sm no-underline visited:text-white hover:bg-wp-blue-hover hover:text-white hover:no-underline focus:text-white focus:outline-none focus:ring-2 focus:ring-wp-blue focus:ring-offset-2 focus:no-underline active:text-white"
					>
						{__('Go Pro', 'media-library-manager')}
					</a>
					<button
						type="button"
						className="inline-flex items-center justify-center cursor-pointer rounded-lg border border-wp-blue bg-white px-4 py-2 text-sm font-medium text-wp-blue transition-colors hover:bg-wp-blue/10 hover:text-wp-blue-hover focus:outline-none focus:ring-2 focus:ring-wp-blue focus:ring-offset-2"
						onClick={() => setProUpgradeOpen(false)}
					>
						{__('Cancel', 'media-library-manager')}
					</button>
				</div>
			</div>
		</div>
	);
};

export default Modal;
