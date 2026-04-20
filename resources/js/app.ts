import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import '../css/app.css';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

function getLegacyInitialPage() {
    if (typeof document === 'undefined') {
        return undefined;
    }

    const appEl = document.getElementById('app');
    const legacyPage = appEl?.getAttribute('data-page');

    if (!legacyPage) {
        return undefined;
    }

    try {
        return JSON.parse(legacyPage);
    } catch {
        return undefined;
    }
}

createInertiaApp({
    // Inertia v3 reads initial page from <script data-page="app">.
    // This fallback keeps the app working if a stale cached HTML page still
    // contains the older <div id="app" data-page="..."> markup.
    page: getLegacyInitialPage(),
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
