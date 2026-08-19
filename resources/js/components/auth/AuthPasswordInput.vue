<script setup lang="ts">
import { ref } from 'vue';

defineOptions({ inheritAttrs: false });

const showPassword = ref(false);
</script>

<template>
    <span class="auth-input-wrap">
        <input
            v-bind="$attrs"
            :type="showPassword ? 'text' : 'password'"
            class="auth-input"
        />
        <button
            type="button"
            class="auth-reveal"
            tabindex="-1"
            @click="showPassword = !showPassword"
        >
            {{ showPassword ? 'Ocultar' : 'Mostrar' }}
        </button>
    </span>
</template>
