const { fileURLToPath } = require('url');
const { globSync } = require('glob');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const path = require('path');
const projectRoot = path.resolve(__dirname, './');
const componentPartials = globSync(`${projectRoot}/components/**/_*.scss`)
  .sort()
  .map(file => `@import "${file}";`)
  .join('\n');

module.exports = (env, argv) => {
  return {
    mode: "production",
    devtool: false,
    entry: {
      main: ["./components/style.scss"]
    },
    module: {
      rules: [
        {
          test: /\.scss$/,
          use: [
            {
              loader: MiniCssExtractPlugin.loader
            },
            {
              loader: "css-loader",
              options: {
                sourceMap: true,
                modules: false
              }
            },
            {
              loader: "postcss-loader",
              options: {
                sourceMap: true
              }
            },
            {
              loader: "sass-loader",
              options: {
                sourceMap: true,
                sassOptions: {
                  silenceDeprecations: ['global-builtin','color-functions','import'],
                },
                additionalData:`
                 @import "${projectRoot}/../../../../vendor/twbs/bootstrap/scss/bootstrap-grid.scss";
                 @import '${projectRoot}/../../../libraries/bootstrap-dist/css/bootstrap.min.css';
                 ${componentPartials}`,
              }
            }
          ]
        },
        {
          test: /\.js$/,
          exclude: /(node_modules|bower_components)/,
          use: {
            loader: "babel-loader",
            options: {
              configFile: './babel.config.js',
            }
          }
        }
      ]
    },
    output: {
      path:  path.resolve(__dirname, "dist"),
      filename: "[name].min.js",
      publicPath: "/assets/"
    },
    plugins: [
      new MiniCssExtractPlugin(),
    ]
  };
};
