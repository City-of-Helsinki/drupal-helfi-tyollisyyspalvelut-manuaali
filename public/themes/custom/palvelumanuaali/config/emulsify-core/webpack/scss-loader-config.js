// scss-loader-config.js
// Shared SCSS loader configuration for webpack
import path from 'path';
import { fileURLToPath } from 'url';
import { globSync } from 'glob';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '../../..');

/**
 * Apply SCSS loader configuration to a webpack config
 * This adds additionalData to sass-loader to prepend all base SCSS partials
 *
 * @param {Object} config - Webpack configuration object
 * @returns {Object} Modified webpack configuration
 */
export function applySassLoaderConfig(config) {
  // Find and modify the sass-loader configuration to add additionalData
  if (config.module && config.module.rules) {
    const scssRule = config.module.rules.find(rule =>
      rule.test && rule.test.toString().includes('s[ac]ss')
    );

    if (scssRule && scssRule.use) {
      const sassLoader = scssRule.use.find(loader =>
        loader.loader && loader.loader.includes('sass-loader')
      );

      if (sassLoader && sassLoader.options) {
        // Find all underscore-prefixed SCSS files in components directory
        // Exclude _style.scss and _print.scss as they are entry files, not partials
        const componentPartials = globSync(`${projectRoot}/components/**/_*.scss`)
          .filter(file => !file.endsWith('/_style.scss') && !file.endsWith('/_print.scss'))
          .sort() // Sort to ensure consistent order (00-base comes first, then 01-atoms, etc.)
          .map(file => `@import "${file}";`)
          .join('\n        ');

        // Silence deprecation warnings from Emulsify dependencies.
        const existingDeprecations = sassLoader.options.sassOptions?.silenceDeprecations || [];
        sassLoader.options.sassOptions = {
          ...sassLoader.options.sassOptions,
          silenceDeprecations: [
            ...existingDeprecations,
            'global-builtin',
            'color-functions',
            'import',
          ]
        };

        sassLoader.options.additionalData = `
        @import "~normalize.css/normalize";
        @import "~breakpoint-sass/stylesheets/breakpoint";
        @import "${projectRoot}/../../../../vendor/twbs/bootstrap/scss/bootstrap-grid.scss";
        @import '${projectRoot}/../../../libraries/bootstrap-dist/css/bootstrap.min.css';
        ${componentPartials}
      `;
      }
    }
  }

  return config;
}
