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

const RemoveDuplicatesControl = () => {
	const [proUpgradeOpen, setProUpgradeOpen] = useState(false);

	return (
		<div className="blp-mlm-admin inline-block max-w-full font-sans text-slate-800 antialiased">
			<button
				type="button"
				className="button button-primary relative inline-flex min-w-[140px] cursor-pointer items-center justify-center overflow-hidden border-0 bg-wp-blue text-white hover:bg-wp-blue-hover"
				onClick={() => setProUpgradeOpen(true)}
			>
				<span className="relative z-10 px-1">
					{__('Remove duplicates', 'media-library-manager')}
				</span>
			</button>

			{proUpgradeOpen && (
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
							{__(
								'Duplicate removal is available in the Pro version of Media Library Manager. Upgrade to unlock this feature and more.',
								'media-library-manager'
							)}
						</p>
						<div className="mt-6 flex justify-end">
							<button
								type="button"
								className="cursor-pointer rounded-lg border border-transparent bg-slate-800 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2"
								onClick={() => setProUpgradeOpen(false)}
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

export default RemoveDuplicatesControl;
