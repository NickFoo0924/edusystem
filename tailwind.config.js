import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        /*
         * Some class names are decided in PHP rather than written in a
         * template -- the grade-letter colours in App\Support\GradeScale and
         * the avatar palette on the User model. Without this line Tailwind
         * never sees them and purges them from the build, which is exactly
         * what happened to bg-orange-100: D-grade badges were rendering with
         * no background at all.
         */
        './app/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
