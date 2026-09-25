// clean-plugin-config.js
// Shared CleanWebpackPlugin configuration for webpack
import { CleanWebpackPlugin } from 'clean-webpack-plugin';

/**
 * Apply CleanWebpackPlugin configuration to a webpack config
 * This overrides the default CleanWebpackPlugin to only clean the dist directory
 * and prevent deletion of source files in the components directory
 *
 * @param {Object} config - Webpack configuration object
 * @returns {Object} Modified webpack configuration
 */
export function applyCleanPluginConfig(config) {
  if (config.plugins) {
    config.plugins = config.plugins.map(plugin => {
      if (plugin instanceof CleanWebpackPlugin) {
        return new CleanWebpackPlugin({
          protectWebpackAssets: false,
          cleanOnceBeforeBuildPatterns: [
            'dist/**/*.css',
            'dist/**/*.js',
            '!dist/**/*.png',
            '!dist/**/*.jpg',
            '!dist/**/*.gif',
            '!dist/**/*.svg',
          ],
        });
      }
      return plugin;
    });
  }

  return config;
}
