/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./app/**/*.php', './public/**/*.php'],
  theme: {
    extend: {
      colors: {
        ios: '#f5f5f7',
        system: {
          50: '#eff6ff',
          100: '#dbeafe',
          500: '#0a84ff',
          600: '#0878e5',
          700: '#0969da'
        }
      },
      fontFamily: {
        sans: ['-apple-system', 'BlinkMacSystemFont', 'SF Pro Display', 'SF Pro Text', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif']
      },
      boxShadow: {
        ios: '0 1px 2px rgba(15,23,42,.025), 0 8px 30px rgba(15,23,42,.045)',
        'ios-lg': '0 12px 40px rgba(15,23,42,.09)'
      }
    }
  },
  plugins: []
};
