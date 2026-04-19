const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
  ...defaultConfig,
  entry: {
    'admin/settings': './assets/admin/settings/index.jsx',
    'admin/metabox': './assets/admin/metabox/index.jsx',
  },
};
