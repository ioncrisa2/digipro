import {createApp, h} from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';
import { createPinia } from 'pinia';
import './bootstrap';
import '../css/app.css';

const pinia = createPinia();
const LOADER_CLASS = 'inertia-loading';
const LOADER_DELAY_MS = 250;
let loaderTimeout = null;

const showGlobalLoader = () => {
    document.documentElement.classList.add(LOADER_CLASS);
};

const hideGlobalLoader = () => {
    document.documentElement.classList.remove(LOADER_CLASS);
};

const stopGlobalLoader = () => {
    if (loaderTimeout) {
        window.clearTimeout(loaderTimeout);
        loaderTimeout = null;
    }

    hideGlobalLoader();
};

router.on('start', () => {
    stopGlobalLoader();
    loaderTimeout = window.setTimeout(() => {
        showGlobalLoader();
    }, LOADER_DELAY_MS);
});

router.on('finish', () => {
    stopGlobalLoader();
});

router.on('invalid', () => {
    stopGlobalLoader();
});

router.on('exception', () => {
    stopGlobalLoader();
});

createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(pinia)
            .mount(el);
    },
    progress: false
})
