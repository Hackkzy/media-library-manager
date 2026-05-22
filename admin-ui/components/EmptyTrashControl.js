/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Modal from './Modal';

const EmptyTrashControl = () => {
	const [proUpgradeOpen, setProUpgradeOpen] = useState(false);

	return (
		<div className="blp-mlm-admin inline-block max-w-full font-sans text-slate-800 antialiased">
			<button
				type="button"
				className="button cursor-pointer disabled:cursor-not-allowed"
				onClick={() => setProUpgradeOpen(true)}
			>
				{__('Empty Trash', 'media-library-manager')}
			</button>

			{proUpgradeOpen && (
				<Modal
					setProUpgradeOpen={setProUpgradeOpen}
					upgradeDescription={__(
						'Empty Trash is available in Pro. No worries - you can still remove duplicates from the trash individually in the free version. Upgrade anytime to clean everything in one go.',
						'media-library-manager'
					)}
				/>
			)}
		</div>
	);
};

export default EmptyTrashControl;
