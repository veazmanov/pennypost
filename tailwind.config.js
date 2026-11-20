import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                // One serif everywhere, so `font-sans` and the default stack are ET Book too.
                sans: ['et-book', 'Palatino', '"Palatino Linotype"', '"Book Antiqua"', 'Georgia', 'serif'],
            },
        },
    },

    plugins: [forms],
};
