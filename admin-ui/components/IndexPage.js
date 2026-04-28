/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { TbReload } from 'react-icons/tb';

const IndexPage = () => {
	const [indexed, setIndexed] = useState(0);
	const [total, setTotal] = useState(0);
	const [isIndexing, setIsIndexing] = useState(false);
	const [isLoading, setIsLoading] = useState(true);
	const [error, setError] = useState(null);
	const [isComplete, setIsComplete] = useState(false);
	const [isRotating, setIsRotating] = useState(false);

	const fetchProgress = async () => {
		try {
			const res = await apiFetch({
				path: '/blp-mlm/indexing/progress',
			});

			setIndexed(res.indexed);
			setTotal(res.total);
			if (res.media_in_queue <= 0) {
				setIsIndexing(false);
			}
			setIsComplete(res.total > 0 && res.indexed >= res.total);
		} catch (err) {
			setError(
				err?.message ||
					__(
						'Failed to fetch indexing progress.',
						'media-library-manager'
					)
			);
		} finally {
			setIsLoading(false);
		}
	};

	const startIndexing = async () => {
		setError(null);

		try {
			setIsIndexing(true);

			await apiFetch({
				path: '/blp-mlm/indexing/start',
				method: 'POST',
			});

			fetchProgress();
		} catch (err) {
			setError(
				err?.message ||
					__('Failed to start indexing.', 'media-library-manager')
			);
		}
	};

	const triggerRefresh = () => {
		setIsRotating(true);
		fetchProgress().finally(() => {
			window.setTimeout(() => setIsRotating(false), 600);
		});
	};

	useEffect(() => {
		fetchProgress();
	}, []);

	/* Auto-refresh every 10 seconds; button rotates on each refresh */
	useEffect(() => {
		const interval = setInterval(triggerRefresh, 10000);
		return () => clearInterval(interval);
	}, []);

	return (
		<div className="blp-mlm-admin relative font-sans text-slate-800 antialiased">
			{/* Alerts */}
			{error && (
				<div
					className="mb-6 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800"
					role="alert"
				>
					<span className="text-red-500" aria-hidden>
						<svg
							className="h-5 w-5"
							fill="currentColor"
							viewBox="0 0 20 20"
						>
							<path
								fillRule="evenodd"
								d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z"
								clipRule="evenodd"
							/>
						</svg>
					</span>
					<div className="flex-1 text-sm">{error}</div>
					<button
						type="button"
						onClick={() => setError(null)}
						className="rounded-md p-1 text-red-600 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500"
						aria-label={__('Dismiss', 'media-library-manager')}
					>
						<svg
							className="h-4 w-4"
							fill="currentColor"
							viewBox="0 0 20 20"
						>
							<path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
						</svg>
					</button>
				</div>
			)}

			{!error && (
				<div
					className={`mb-6 flex items-center gap-3 rounded-xl border px-4 py-3 text-sm ${
						isIndexing
							? 'border-wp-blue/30 bg-wp-blue/10 text-wp-blue'
							: total === 0
								? 'border-slate-200 bg-slate-50 text-slate-600'
								: isComplete
									? 'border-emerald-200 bg-emerald-50 text-emerald-800'
									: 'border-amber-200 bg-amber-50 text-amber-800'
					}`}
					role="status"
				>
					{isIndexing && (
						<div className="h-4 w-4 animate-spin rounded-full border-2 border-wp-blue/30 border-t-wp-blue" />
					)}
					{isComplete && (
						<svg
							className="h-5 w-5 text-emerald-500"
							fill="currentColor"
							viewBox="0 0 20 20"
						>
							<path
								fillRule="evenodd"
								d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z"
								clipRule="evenodd"
							/>
						</svg>
					)}
					<span>
						{isIndexing &&
							__(
								'Indexing is in progress…',
								'media-library-manager'
							)}
						{!isIndexing &&
							total === 0 &&
							__(
								'No media items found.',
								'media-library-manager'
							)}
						{!isIndexing &&
							total > 0 &&
							isComplete &&
							__(
								'All media are indexed.',
								'media-library-manager'
							)}
						{!isIndexing &&
							total > 0 &&
							!isComplete &&
							__(
								'Some medias are not indexed.',
								'media-library-manager'
							)}
					</span>
				</div>
			)}

			{/* Main card */}
			<div className="relative rounded-2xl border border-slate-200 bg-white shadow-sm">
				{/* Reload button - top right */}
				<button
					type="button"
					onClick={triggerRefresh}
					disabled={isLoading}
					className={`blp-mlm-reload-btn absolute top-4 right-4 flex h-10 w-10 items-center justify-center rounded-lg bg-transparent text-wp-blue transition hover:text-wp-blue-hover focus:outline-none focus:ring-2 focus:ring-wp-blue disabled:opacity-50 ${isRotating ? 'blp-mlm-reload-btn--rotating' : ''} border-none cursor-pointer`}
					aria-label={__('Refresh', 'media-library-manager')}
				>
					<TbReload className="h-5 w-5" aria-hidden />
				</button>
				<div className="border-b border-slate-100 px-6 py-5">
					<h2 className="text-base font-medium text-slate-900">
						{__('Media index', 'media-library-manager')}
					</h2>
				</div>

				<div className="p-6">
					{/* Stat + progress */}
					<div className="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
						<div className="flex items-baseline gap-2">
							<span className="text-3xl font-semibold tabular-nums text-wp-blue">
								{indexed.toLocaleString()}
							</span>
							<span className="text-slate-400">/</span>
							<span className="text-xl text-slate-500">
								{total.toLocaleString()}
							</span>
							<span className="text-sm text-slate-500">
								{__('indexed', 'media-library-manager')}
							</span>
						</div>
					</div>

					{/* Action */}
					<div className="mt-8 pt-6 border-t border-slate-100">
						<button
							type="button"
							onClick={startIndexing}
							disabled={isIndexing || isComplete}
							className="inline-flex items-center justify-center gap-2 rounded-xl bg-wp-blue px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-wp-blue-hover focus:outline-none focus:ring-2 focus:ring-wp-blue focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 border-none cursor-pointer"
						>
							{isIndexing
								? __('Indexing…', 'media-library-manager')
								: __('Start indexing', 'media-library-manager')}
						</button>
					</div>
				</div>
			</div>
		</div>
	);
};

export default IndexPage;
