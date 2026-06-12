import path from "node:path";
import MiniCssExtractPlugin from "mini-css-extract-plugin";
import { fileURLToPath } from "node:url";
import {  globSync } from 'glob';
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const projectRoot = path.resolve(__dirname, '../../../');
const componentPartials = globSync(`${projectRoot}/components/**/_*.scss`)
  .sort()
  .map(file => `@import "${file}";`)
  .join('\n');


function getEntries() {
  const files = globSync("./components/**/*.js", {
      ignore: [
        "**/*.stories.js",
        "**/*.test.js",
        "**/*.component.js"
      ]
    });

  return Object.fromEntries(
    files.map(file => {
      const name = path
        .relative("./components", file)
        .replace(/\.js$/, "");


      const normalizedPath = file.startsWith("./")
        ? file
        : `./${file}`;

      return [name, normalizedPath];
    })
  );
}

const entry = {
  ...getEntries(),           // all JS files
  style: ["./components/style.scss"] // single CSS entry
};

export default (env, argv) => {
  return {
    mode: "production",
    devtool: false,
    entry: entry,
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
                  silenceDeprecations: ["global-builtin", "color-functions", "import"]
                },
                additionalData: `
                @import "${projectRoot}/node_modules/breakpoint-sass/stylesheets/_breakpoint.scss";
                @import "${projectRoot}/../../../../vendor/twbs/bootstrap/scss/bootstrap-grid.scss";
                @import "${projectRoot}/../../../../vendor/twbs/bootstrap/scss/bootstrap.scss";
                ${componentPartials}
                `
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
              configFile: "./babel.config.js"
            }
          }
        }
      ]
    },
    output: {
      path: path.resolve(projectRoot, "dist"),
      filename: "components/[name].js",
      publicPath: "/assets/"
    },
    plugins: [
      new MiniCssExtractPlugin({filename: "components/style.css"})
    ]
  };
};
