<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    class?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-teal-400 via-blue-500 to-violet-600 shadow-sm">
        <AppLogoIcon class="size-5 text-white" />
    </div>
    <div class="ml-1 grid flex-1 text-left">
        <span class="truncate text-[15px] font-semibold leading-none tracking-tight">
            Pay<span class="bg-gradient-to-r from-teal-500 via-blue-600 to-violet-600 bg-clip-text text-transparent">PerPixel</span>
        </span>
    </div>
</template>
