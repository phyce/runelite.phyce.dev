<template>
    <footer class="app-footer">
        Made by
        <a href="https://phyce.dev" class="app-footer__link" target="_blank" rel="noopener noreferrer">
            Phyce
        </a>
    </footer>
</template>

<style scoped>
@reference "tailwindcss";

.app-footer {
    @apply py-4 text-center text-sm text-gray-500;
}

.app-footer__link {
    @apply text-orange-500 hover:underline;
}
</style>
