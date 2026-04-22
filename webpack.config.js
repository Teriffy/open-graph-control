const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
  ...defaultConfig,
  entry: {
    'admin/settings': './assets/admin/settings/index.jsx',
    'admin/metabox': './assets/admin/metabox/index.jsx',
    'admin/archive': './assets/admin/archive/index.jsx',
    'admin/card-template': './assets/admin/card-template/index.jsx',
    'admin/field-sources': './assets/admin/field-sources/index.jsx',
  },
};
