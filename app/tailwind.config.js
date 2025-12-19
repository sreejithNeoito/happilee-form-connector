/** @type {import('tailwindcss').Config} */
module.exports = {
  prefix: "wphfc-",
  content: ["./src/**/*.{js,jsx,ts,tsx}", "../**/*.php"],
  theme: {
    extend: {
      colors: {
        primary: "#0B3966",
      },
    },
  },
  plugins: [],
  corePlugins: {
    preflight: false,
  },
};
