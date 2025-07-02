module.exports = {
  plugins: {
    '@tailwindcss/postcss': {
      // Explicitly point to Tailwind config for v4
      config: './tailwind.config.js'
    },
    autoprefixer: {},
  },
}