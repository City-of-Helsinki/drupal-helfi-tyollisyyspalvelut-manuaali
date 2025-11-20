// webpack.prod.js
import { merge } from 'webpack-merge';
import common from '../../../node_modules/@emulsify/core/config/webpack/webpack.common.js';
import { applySassLoaderConfig } from './scss-loader-config.js';
import { applyCleanPluginConfig } from './clean-plugin-config.js';

let config = merge(common, {
  mode: 'production',
});

config = applySassLoaderConfig(config);
config = applyCleanPluginConfig(config);

export default config;
