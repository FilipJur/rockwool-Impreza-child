/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./src/**/*.{js,jsx,ts,tsx,php}",
    "./templates/**/*.php",
    "./*.php",
    "./includes/**/*.php",
  ],
  safelist: [
    // Grid column spanning classes for dynamic shortcode layouts
    'md:col-span-2',
    'md:grid-cols-2',
    'grid-cols-1',
    'grid',
    'gap-4',
  ],
  theme: {
    // Use Tailwind's default theme - no customizations
  },
  plugins: [
    // Clean plugin list - only add what you actually need
  ],
  corePlugins: {
    // Disable preflight to avoid conflicts with WordPress/parent theme styles
    preflight: false,
  },
}