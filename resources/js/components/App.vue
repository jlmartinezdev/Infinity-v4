<template>
    <div>
        <!-- Mostrar Login si no está autenticado -->
        <Login v-if="!isAuthenticated" @login-success="handleLoginSuccess" />
        
        <!-- Mostrar Home si está autenticado -->
        <Home v-else />
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import Login from './Login.vue';
import Home from './Home.vue';
import axios from 'axios';

const isAuthenticated = ref(false);

const checkAuth = async () => {
    try {
        const response = await axios.get('/api/user');
        if (response.data.user) {
            isAuthenticated.value = true;
        }
    } catch (error) {
        isAuthenticated.value = false;
    }
};

const handleLoginSuccess = () => {
    isAuthenticated.value = true;
};

onMounted(() => {
    checkAuth();
});
</script>

<style scoped>
/* Estilos adicionales si los necesitas */
</style>
