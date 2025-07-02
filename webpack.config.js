const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
  ...defaultConfig,
  entry: {
    main: './src/js/main.js',
    admin: './src/js/admin.js',
    'cf7-components': './src/js/cf7-alpine-components.js'
  },
  output: {
    ...defaultConfig.output,
    filename: '[name].js'
  }
};