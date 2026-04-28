/** @type {import('tailwindcss').Config} */
module.exports = {
	content: ['./admin-ui/**/*.{js,jsx}'],
	theme: {
		extend: {
			colors: {
				'wp-blue': '#2271b1',
				'wp-blue-hover': '#1a5a8a',
			},
		},
	},
	plugins: [],
	// Preflight off: WP admin has its own base styles.
	// `fixed` utility MUST be off: WP_List_Table uses class `fixed` for table-layout, not position.
	corePlugins: {
		preflight: false,
		fixed: false,
	},
};
