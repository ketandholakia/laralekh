import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'Liberation Mono', 'monospace'],
            },
            colors: {
                // LaraLekh brand tokens. The teal scale intentionally mirrors
                // Tailwind's own so existing utility usage migrates 1:1.
                brand: {
                    50: '#F0FDFA',
                    100: '#CCFBF1',
                    600: '#0D9488',
                    700: '#0F766E',
                    800: '#115E59',
                    900: '#134E4A',
                },
                navy: {
                    DEFAULT: '#172554',
                },
                saffron: {
                    DEFAULT: '#F59E0B',
                    600: '#D97706',
                    700: '#B45309',
                },
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.05)',
                pop: '0 4px 12px 0 rgb(15 23 42 / 0.10), 0 2px 4px 0 rgb(15 23 42 / 0.06)',
            },
        },
    },
    plugins: [],
};
