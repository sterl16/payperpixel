<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const page = usePage<SharedData>();
const busy = ref(false);
const packs = [5, 10, 25];

function buy(amount: number) {
    busy.value = true;
    router.post(route('credits.store'), { amount }, { preserveScroll: true, onFinish: () => (busy.value = false) });
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-3 rounded-xl border p-4">
        <div>
            <div class="text-xs uppercase text-muted-foreground">Balance</div>
            <div class="text-2xl font-semibold tabular-nums">${{ Number(page.props.balance ?? 0).toFixed(4) }}</div>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <span class="text-sm text-muted-foreground">Buy credits</span>
            <Button v-for="p in packs" :key="p" variant="outline" size="sm" :disabled="busy" @click="buy(p)">+${{ p }}</Button>
        </div>
    </div>
</template>
