<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <!-- pixel grid that gets brighter toward a spark -->
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" :class="className" v-bind="$attrs">
        <g fill="currentColor">
            <rect x="2" y="2" width="5.5" height="5.5" rx="1.2" opacity="0.35" />
            <rect x="9.25" y="2" width="5.5" height="5.5" rx="1.2" opacity="0.55" />
            <rect x="16.5" y="2" width="5.5" height="5.5" rx="1.2" opacity="0.75" />
            <rect x="2" y="9.25" width="5.5" height="5.5" rx="1.2" opacity="0.55" />
            <rect x="9.25" y="9.25" width="5.5" height="5.5" rx="1.2" opacity="0.75" />
            <rect x="16.5" y="9.25" width="5.5" height="5.5" rx="1.2" />
            <rect x="2" y="16.5" width="5.5" height="5.5" rx="1.2" opacity="0.75" />
            <rect x="9.25" y="16.5" width="5.5" height="5.5" rx="1.2" />
            <path d="M19.25 15.2c.25 1.6 1.05 2.4 2.65 2.65-1.6.25-2.4 1.05-2.65 2.65-.25-1.6-1.05-2.4-2.65-2.65 1.6-.25 2.4-1.05 2.65-2.65Z" />
        </g>
    </svg>
</template>
