const { globSync } = require("glob"); // Corrected import
const path = require("path");

const getPartialComponents = () => {
    return globSync("src/partials/**/*.tsx").reduce((acc, filePath) => {
        const name = path.basename(filePath, path.extname(filePath)); // Extract file name without extension
        acc[name] = {
            import: [filePath],
            dependOn: "shared"
        };
        return acc;
    }, {});
};

module.exports = {
    entry: {
        shared: ["react", "react-dom"],
        index: {
            import: ["./src/index.tsx"]
        },
        ...getPartialComponents(), // Corrected syntax
    },
    output: {
        filename: "[name].bundle.js",
        path: path.resolve(__dirname, "dist", "scripts"),
    },
    module: {
        rules: [
            {
                test: /\.tsx?$/,
                use: "ts-loader",
                exclude: /node_modules/,
            },
        ],
    },
    resolve: {
        extensions: [".tsx", ".ts", ".js"],
    },
};
