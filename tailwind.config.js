/** @type {import('tailwindcss').Config} */
const defaultTheme = require('tailwindcss/defaultTheme');

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
    // User Progress Guide specific classes
    'progress-step-completed',
    'progress-step-pending',
    'progress-step-in-progress',
    'bg-brand-red',
    'text-brand-red',
    'bg-brand-gray-light',
    'text-brand-gray-medium',
  ],
  theme: {
    extend: {
      // Brand Colors (from SCSS variables + Figma design)
      colors: {
        'brand-red': '#DB0626',        // Primary brand color
        'brand-red-dark': '#D20014',   // Dark variant
        'brand-black': '#000000',      // Main text
        'brand-white': '#FFFFFF',      // Backgrounds
        'brand-gray': {
          'extralight': '#F6F6F6',     // Light card backgrounds
          'light': '#DADADA',          // Progress bar backgrounds
          'medium': '#909090',         // Disabled/muted text
          'border': '#CCCCCC',         // Borders
          'bg': '#F2F2F2',            // Card backgrounds
        },
        'brand-accent': '#33A7CC',     // Links and accents
        'brand-success': '#28a745',
        'brand-danger': '#D32F2F',
        'brand-info': '#007bff',
      },
      // Typography (Avenir Next as primary)
      fontFamily: {
        'sans': ['Avenir Next', ...defaultTheme.fontFamily.sans],
        'primary': ['Avenir Next', 'sans-serif'],
        'secondary': ['General Sans', 'sans-serif'],
      },
      // Font sizes matching design tokens
      fontSize: {
        'xs': ['12px', '1.2'],
        'sm': ['14px', '1.286'],
        'base': ['16px', '1.5'],
        'lg': ['18px', '1.333'],
        'xl': ['20px', '1.2'],
        '2xl': ['24px', '1.2'],
        '3xl': ['30px', '1.2'],
        'hero': ['48px', '1.1'],
      },
      // Spacing scale from design tokens
      spacing: {
        'xs': '5px',
        'sm': '10px',
        'md': '20px',
        'lg': '30px',
        'xl': '40px',
        '2xl': '60px',
        '3xl': '80px',
        'form': '30px',
        'form-field': '20px',
        'hero': '40px',
        'content': '80px',
      },
      // Box shadows for cards and components
      boxShadow: {
        'card': '0px 0px 20px 0px rgba(0, 0, 0, 0.08)',
        'soft': '0px 2px 8px 0px rgba(0, 0, 0, 0.05)',
      },
      // Border radius
      borderRadius: {
        'sm': '4px',
        'md': '6px',
        'lg': '8px',
        'xl': '12px',
      },
      // Transitions
      transitionDuration: {
        'fast': '200ms',
        'base': '300ms',
      },
      // Custom widths for specific components
      maxWidth: {
        'guide': '1420px',
        'card': '400px',
      },
    },
  },
  plugins: [
    // Clean plugin list - only add what you actually need
  ],
  corePlugins: {
    // Disable preflight to avoid conflicts with WordPress/parent theme styles
    preflight: false,
  },
}