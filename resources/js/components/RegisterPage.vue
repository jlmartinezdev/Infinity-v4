<template>
    <Register @register-success="handleRegisterSuccess" />
</template>

<script setup>
import Register from './Register.vue';

const handleRegisterSuccess = () => {
    // La redirección se maneja en el componente Register
    // Este método se puede usar para lógica adicional si es necesario
};
</script>
