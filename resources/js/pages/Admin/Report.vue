<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { exactUsd, usdToNanos } from '@/lib/pricing';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Money {
    charged: string;
    cost: string;
    profit: string;
}

const props = defineProps<{
    start: string;
    end: string;
    days: (Money & { date: string; generations: number; pixelforge_cost: string | null })[];
    users: (Money & { name: string; email: string; generations: number })[];
    totals: Money & { generations: number };
    usage_available: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin report', href: '/admin/report' }];
const start = ref(props.start);
const end = ref(props.end);

const usd = exactUsd;
const matches = (d: { cost: string; pixelforge_cost: string | null }) => d.pixelforge_cost !== null && usdToNanos(d.cost) === usdToNanos(d.pixelforge_cost);

function apply() {
    router.get(route('admin.report'), { start: start.value, end: end.value }, { preserveState: true });
}
</script>

<template>
    <Head title="Admin report" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex w-full flex-col gap-6 p-4">
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="apply">
                <label class="grid gap-1 text-sm">From (UTC)<Input v-model="start" type="date" /></label>
                <label class="grid gap-1 text-sm">To (UTC)<Input v-model="end" type="date" /></label>
                <Button type="submit">Apply</Button>
            </form>

            <div class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-xl border p-4"><div class="text-xs text-muted-foreground">Generations</div><div class="text-2xl font-semibold">{{ totals.generations }}</div></div>
                <div class="rounded-xl border p-4"><div class="text-xs text-muted-foreground">Credit spend</div><div class="text-2xl font-semibold tabular-nums">{{ usd(totals.charged) }}</div></div>
                <div class="rounded-xl border p-4"><div class="text-xs text-muted-foreground">Provider cost</div><div class="text-2xl font-semibold tabular-nums">{{ usd(totals.cost) }}</div></div>
                <div class="rounded-xl border p-4"><div class="text-xs text-muted-foreground">Profit</div><div class="text-2xl font-semibold tabular-nums">{{ usd(totals.profit) }}</div></div>
            </div>

            <section class="overflow-x-auto rounded-xl border">
                <h2 class="border-b p-3 font-medium">By day <span class="text-xs font-normal text-muted-foreground">(UTC day the generation completed, matching Pixelforge billing)</span></h2>
                <table class="w-full text-sm tabular-nums">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr><th class="p-3">Date</th><th class="p-3 text-right">Gens</th><th class="p-3 text-right">Credit spend</th><th class="p-3 text-right">Provider cost</th><th class="p-3 text-right">Pixelforge /v1/usage</th><th class="p-3 text-right">Profit</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in days" :key="d.date" class="border-t">
                            <td class="p-3">{{ d.date }}</td>
                            <td class="p-3 text-right">{{ d.generations }}</td>
                            <td class="p-3 text-right">{{ usd(d.charged) }}</td>
                            <td class="p-3 text-right">{{ usd(d.cost) }}</td>
                            <td class="p-3 text-right" :class="d.pixelforge_cost === null ? 'text-muted-foreground' : matches(d) ? 'text-green-700 dark:text-green-400' : 'text-red-600'">
                                {{ d.pixelforge_cost === null ? 'n/a' : usd(d.pixelforge_cost) }} <span v-if="d.pixelforge_cost !== null">{{ matches(d) ? '✓' : '≠' }}</span>
                            </td>
                            <td class="p-3 text-right">{{ usd(d.profit) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!usage_available" class="border-t p-3 text-xs text-muted-foreground">Couldn't reach Pixelforge's usage endpoint.</p>
            </section>

            <section class="overflow-x-auto rounded-xl border">
                <h2 class="border-b p-3 font-medium">By user</h2>
                <table class="w-full text-sm tabular-nums">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr><th class="p-3">User</th><th class="p-3 text-right">Gens</th><th class="p-3 text-right">Credit spend</th><th class="p-3 text-right">Provider cost</th><th class="p-3 text-right">Profit</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in users" :key="u.email" class="border-t">
                            <td class="p-3">{{ u.name }} <span class="text-muted-foreground">{{ u.email }}</span></td>
                            <td class="p-3 text-right">{{ u.generations }}</td>
                            <td class="p-3 text-right">{{ usd(u.charged) }}</td>
                            <td class="p-3 text-right">{{ usd(u.cost) }}</td>
                            <td class="p-3 text-right">{{ usd(u.profit) }}</td>
                        </tr>
                        <tr v-if="users.length === 0"><td colspan="5" class="p-3 text-center text-muted-foreground">No completed generations in this range.</td></tr>
                    </tbody>
                </table>
            </section>
        </div>
    </AppLayout>
</template>
