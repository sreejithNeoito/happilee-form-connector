const path = require("path");
const BrowserSyncPlugin = require("browser-sync-webpack-plugin");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
// const { CleanWebpackPlugin } = require("clean-webpack-plugin");

module.exports = {
  entry: "./src/index.js",
  output: {
    filename: "bundle.js",
    path: path.resolve(__dirname, "../assets/js"),
    publicPath: "/wp-content/plugins/happilee-forms-connect/assets/js",
    hotUpdateChunkFilename: ".hot/[id].[fullhash].hot-update.js",
    hotUpdateMainFilename: ".hot/[runtime].[fullhash].hot-update.json",
  },
  devServer: {
    hot: true,
    liveReload: true,
    port: 3000,
    devMiddleware: {
      writeToDisk: true,
    },
    headers: {
      "Access-Control-Allow-Origin": "*",
    },
  },
  resolve: {
    extensions: [".js", ".jsx"],
  },
  module: {
    rules: [
      {
        test: /\.(js|jsx)$/,
        exclude: /node_modules/,
        use: "babel-loader",
      },
      {
        test: /\.css$/,
        use: [
          MiniCssExtractPlugin.loader,
          "css-loader",
          {
            loader: "postcss-loader",
            options: {
              postcssOptions: {
                plugins: [require("tailwindcss"), require("autoprefixer")],
              },
            },
          },
        ],
      },
    ],
  },
  plugins: [
    new MiniCssExtractPlugin({
      filename: "../css/[name].css",
    }),
    new BrowserSyncPlugin(
      {
        proxy: "http://localhost/happilee-connect/",
        files: [
          "**/*.php",
          "build/*.js",
          "src/**/*.js",
          "src/**/*.css",
          "../assets/**/*.css",
        ],
        open: false,
        notify: false,
      },
      {
        reload: true,
      },
    ),
  ],
};
