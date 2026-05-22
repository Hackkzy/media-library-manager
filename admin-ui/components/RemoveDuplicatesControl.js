/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Modal from './Modal';

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
				<Modal
					setProUpgradeOpen={setProUpgradeOpen}
					upgradeDescription={__(
						'Bulk duplicate removal is available in Pro. No worries - you can still remove duplicates individually in the free version. Upgrade anytime to clean everything in one go.',
						'media-library-manager'
					)}
				/>
			)}
		</div>
	);
};

export default RemoveDuplicatesControl;
