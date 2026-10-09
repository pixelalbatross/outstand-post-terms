const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		'js/editor': path.resolve( __dirname, 'src/js/editor/index.js' ),
	},
};
