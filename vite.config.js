import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    server: {
        host: '127.0.0.1',
        port: 5173,
        hmr: {
            host: '127.0.0.1',
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                quietDeps: true,
                silenceDeprecations: ['mixed-decls', 'color-functions', 'global-builtin', 'import']
            }
        }
    },
    plugins: [
        laravel({
            input: [
                // Core styles
                'resources/scss/app.scss',
                'resources/scss/icons.scss',
                
                // Core JS
                'resources/js/app.js',
                'resources/js/config.js',
                'resources/js/layout.js',
                
                // Page-specific JS
                'resources/js/pages/dashboard.js',
                'resources/js/pages/dashboard-admin.js',
                'resources/js/pages/app-calendar.js',
                'resources/js/pages/app-chat.js',
                'resources/js/pages/app-email.js',
                'resources/js/pages/app-ecommerce-product.js',
                'resources/js/pages/app-ecommerce-seller.js',
                'resources/js/pages/app-ecommerce-seller-add.js',
                'resources/js/pages/app-ecommerce-seller-detail.js',
                'resources/js/pages/customer-details.js',
                'resources/js/pages/seller-detail.js',
                'resources/js/pages/ecommerce-product.js',
                'resources/js/pages/ecommerce-product-details.js',
                'resources/js/pages/invoice-add.js',
                'resources/js/pages/coupons-add.js',
                'resources/js/pages/coming-soon.js',
                'resources/js/pages/widgets.js',
                'resources/js/pages/toasts.js',
                
                // Component JS - Forms
                'resources/js/components/form-clipboard.js',
                'resources/js/components/form-flatepicker.js',
                'resources/js/components/form-wizard.js',
                'resources/js/components/form-fileupload.js',
                'resources/js/components/form-quilljs.js',
                'resources/js/components/form-slider.js',
                
                // Component JS - Charts (ApexCharts)
                'resources/js/components/apexchart-area.js',
                'resources/js/components/apexchart-bar.js',
                'resources/js/components/apexchart-bubble.js',
                'resources/js/components/apexchart-candlestick.js',
                'resources/js/components/apexchart-column.js',
                'resources/js/components/apexchart-heatmap.js',
                'resources/js/components/apexchart-line.js',
                'resources/js/components/apexchart-mixed.js',
                'resources/js/components/apexchart-timeline.js',
                'resources/js/components/apexchart-boxplot.js',
                'resources/js/components/apexchart-treemap.js',
                'resources/js/components/apexchart-pie.js',
                'resources/js/components/apexchart-radar.js',
                'resources/js/components/apexchart-radialbar.js',
                'resources/js/components/apexchart-scatter.js',
                'resources/js/components/apexchart-polar-area.js',
                
                // Component JS - Extended
                'resources/js/components/extended-rating.js',
                'resources/js/components/extended-sweetalert.js',
                
                // Component JS - Tables & Maps
                'resources/js/components/table-gridjs.js',
                'resources/js/components/maps-google.js',
                'resources/js/components/maps-vector.js',
                'resources/js/components/maps-canada.js',
                'resources/js/components/maps-iraq.js',
                'resources/js/components/maps-russia.js',
                'resources/js/components/maps-spain.js',
                'resources/js/components/maps-us-aea-en.js',
                'resources/js/components/maps-us-lcc-en.js',
                'resources/js/components/maps-us-mill-en.js',
            ],
            refresh: true,
        }),
    ],
});
