const defaultConfig = require('@wordpress/scripts/config/webpack.config');
module.exports = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry(),
		admin: './admin-ui/index.js',
	},
};
