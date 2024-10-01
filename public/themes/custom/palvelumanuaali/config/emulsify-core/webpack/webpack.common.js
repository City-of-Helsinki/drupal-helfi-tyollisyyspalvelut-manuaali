const path = require('path');
const glob = require('glob');
const rootDir = path.resolve(__dirname, '../../../');
const webpackDir = path.resolve(rootDir, './config/emulsify-core/webpack');
const loaders = require('./loaders');
const plugins = require('./plugins');
const distDir = path.resolve(rootDir, './dist');

function getEntries(jsPattern) {
  const entries = {};

  glob.sync(jsPattern).forEach((file) => {
    const filePath = file.split('components/')[1];
    const newFilePath = `js/${filePath.replace('.js', '')}`;
    entries[newFilePath] = file;
  });

  glob.sync(`${webpackDir}/css/*js`).forEach((file) => {
    const baseFileName = path.basename(file);
    const newFilePath = `css/${baseFileName.replace('.js', '')}`;
    entries[newFilePath] = file;
  });

  entries.svgSprite = path.resolve(webpackDir, 'svgSprite.js');

  return entries;
}

module.exports = {
  stats: {
    errorDetails: true,
  },
  entry: getEntries(
    path.resolve(
      rootDir,
      'components/**/!(*.stories|*.component|*.min|*.test).js',
    ),
  ),
  module: {
    rules: [
      loaders.CSSLoader,
      loaders.SVGSpriteLoader,
      loaders.ImageLoader,
      loaders.JSLoader,
    ],
  },
  plugins: [
    plugins.MiniCssExtractPlugin,
    plugins.ImageminPlugin,
    plugins.SpriteLoaderPlugin,
    plugins.ProgressPlugin,
    plugins.CleanWebpackPlugin,
  ],
  output: {
    path: distDir,
    filename: '[name].js',
  },
};
