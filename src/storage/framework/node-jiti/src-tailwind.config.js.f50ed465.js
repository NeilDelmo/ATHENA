"use strict";Object.defineProperty(exports, "__esModule", {value: true}); function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }var _defaultTheme = require('tailwindcss/defaultTheme'); var _defaultTheme2 = _interopRequireDefault(_defaultTheme);
var _forms = require('@tailwindcss/forms'); var _forms2 = _interopRequireDefault(_forms);

/** @type {import('tailwindcss').Config} */
exports. default = {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/research-assistant-markdown.js',
        './resources/js/proposal-conflict-recovery.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Plus Jakarta Sans', ..._defaultTheme2.default.fontFamily.sans],
            },
            colors: {
                brand: {
                    DEFAULT: '#7A0019',
                    soft: '#9F1239',
                    wash: '#FEF2F4',
                },
            },
            boxShadow: {
                bubble: '0 24px 48px -28px rgba(122, 0, 25, 0.30)',
                'bubble-sm': '0 12px 28px -18px rgba(122, 0, 25, 0.28)',
                'bubble-lg': '0 32px 64px -32px rgba(122, 0, 25, 0.35)',
            },
            keyframes: {
                'pop-in': {
                    '0%': { transform: 'scale(0.92)', opacity: '0.4' },
                    '100%': { transform: 'scale(1)', opacity: '1' },
                },
            },
            animation: {
                'pop-in': 'pop-in 0.25s ease-out',
            },
        },
    },

    plugins: [_forms2.default],
};
 /* v7-90dc345d8051211b */