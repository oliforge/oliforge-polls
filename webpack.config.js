const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
    ...defaultConfig,
    entry: {
        'admin-editor': path.resolve( process.cwd(), 'src/admin-editor/index.js' ),
        'admin-list': path.resolve( process.cwd(), 'src/admin-list/index.js' ),
        frontend: path.resolve( process.cwd(), 'src/frontend/index.js' ),
        'block-editor': path.resolve( process.cwd(), 'src/block-editor/index.js' ),
    },
    output: {
        ...defaultConfig.output,
        path: path.resolve( process.cwd(), 'build' ),
        filename: '[name].js',
    },
};
