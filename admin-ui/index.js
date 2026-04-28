/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

/**
 * Admin UI styles (Tailwind)
 */
import './admin.css';

/**
 * Internal dependencies
 */
import SettingsPage from './components/IndexPage';
import RemoveDuplicatesControl from './components/RemoveDuplicatesControl';
import EmptyTrashControl from './components/EmptyTrashControl';

domReady(() => {
	const indexTab = document.getElementById('blp-mlm-index-duplicates-tab');
	if (indexTab) {
		const root = createRoot(indexTab);
		root.render(<SettingsPage />);
	}

	const removeDuplicatesRoot = document.getElementById(
		'blp-mlm-remove-duplicates-root'
	);
	if (removeDuplicatesRoot) {
		const root = createRoot(removeDuplicatesRoot);
		root.render(<RemoveDuplicatesControl />);
	}

	const emptyTrashRoot = document.getElementById('blp-mlm-empty-trash-root');
	if (emptyTrashRoot) {
		const root = createRoot(emptyTrashRoot);
		root.render(<EmptyTrashControl />);
	}
});
