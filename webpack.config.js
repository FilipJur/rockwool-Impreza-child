const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
  ...defaultConfig,
  entry: {
    main: './src/js/main.js',
    admin: './src/js/admin.js'
  },
  output: {
    ...defaultConfig.output,
    filename: '[name].js'
  }
};