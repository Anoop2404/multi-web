import { createApp } from 'vue';
import App from './App.vue';
import { app, boot, showLogin } from './app';
import { currentRoute } from './lib/router';
import './styles.css';

createApp(App).mount('#app');
document.documentElement.classList.add('booted');
boot();

// Back/forward between "/" and "/{code}" before signing in.
window.addEventListener('popstate', () => {
    if (app.screen !== 'code' && app.screen !== 'login') return;
    app.code = currentRoute().code;
    app.sahodaya = null;
    showLogin();
});
