import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Segoe UI', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Edunova asosiy rangi - qizil
                brand: {
                    50: '#fff1f2',
                    100: '#ffe1e4',
                    200: '#ffc7cd',
                    300: '#ff9da7',
                    400: '#ff6474',
                    500: '#f53548',
                    600: '#e11d34',
                    700: '#be1227',
                    800: '#9d1325',
                    900: '#821625',
                    950: '#470810',
                },
                ink: {
                    50: '#f8f8f9',
                    100: '#f0f0f2',
                    200: '#e3e3e7',
                    300: '#c9c9d0',
                    400: '#9a9aa5',
                    500: '#6f6f7b',
                    600: '#52525e',
                    700: '#3b3b46',
                    800: '#26262e',
                    900: '#17171d',
                    950: '#0e0e12',
                },
            },
            boxShadow: {
                card: '0 1px 2px rgba(23,23,29,.04), 0 4px 16px rgba(23,23,29,.05)',
                pop: '0 10px 40px rgba(23,23,29,.15)',
            },
        },
    },
    plugins: [forms],
};
