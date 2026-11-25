/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./index.php', './frontend/index.php', './backend/index.php'],
  theme: {
    extend: {
      fontFamily: {
        serif: ['Barlow Condensed', 'Arial Narrow', 'Impact', 'sans-serif'],
        sans: ['Source Sans 3', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      colors: {
        ink: '#1d2420',
        cream: '#efe6d2',
        terracotta: '#b6402c',
        moss: '#1d4a38',
      },
    },
  },
  plugins: [],
};
