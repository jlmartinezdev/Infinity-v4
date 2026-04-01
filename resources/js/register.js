import './bootstrap';
// El CSS se carga desde app.css en la vista register.blade.php
import { createApp } from 'vue';
import RegisterPage from '@/components/RegisterPage.vue';

const app = createApp(RegisterPage);
app.mount('#app');
