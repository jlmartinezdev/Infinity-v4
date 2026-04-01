import './bootstrap';
// El CSS se carga desde app.css en la vista login.blade.php
import { createApp } from 'vue';
import LoginPage from '@/components/LoginPage.vue';

const app = createApp(LoginPage);
app.mount('#app');
