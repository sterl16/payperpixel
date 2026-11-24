<script setup lang="ts">
import BuyCredits from '@/components/BuyCredits.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { exactUsd, formatUsd, usdToNanos } from '@/lib/pricing';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface Generation {
    id: number;
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
    status: 'pending' | 'queued' | 'processing' | 'succeeded' | 'failed';
    estimated_charge: string;
    charge: string | null;
    image_url: string | null;
    image_url_expires_at: string | null;
    error: string | null;
    created_at: string;
    completed_at: string | null;
}

const props = defineProps<{
    generations: { data: Generation[]; links: { url: string | null; label: string; active: boolean }[] };
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'History', href: '/generations' }];
const page = usePage<SharedData>();

const inFlight = computed(() => props.generations.data.some((g) => g.status !== 'succeeded' && g.status !== 'failed'));

// only poll while something is still running
const { start, stop } = usePoll(3000, { only: ['generations', 'balance'] }, { autoStart: false });
watch(
    inFlight,
    (running) => {
        if (running) {
            start();
            return;
        }
        stop();
    },
    { immediate: true },
);

const badge: Record<Generation['status'], string> = {
    pending: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    queued: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    processing: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    succeeded: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
    failed: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
};

const label = (s: Generation['status']) => (s === 'pending' ? 'submitting' : s);
// estimate is a max so rounding it up is fine, actual charge shows exact
const estimateUsd = (v: string) => formatUsd(usdToNanos(v));
const expired = (g: Generation) => g.image_url_expires_at !== null && new Date(g.image_url_expires_at) < new Date();
</script>

<template>
    <Head title="History" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4">
            <BuyCredits />

            <div v-if="page.props.flash.success" class="rounded-md border border-green-300 bg-green-50 px-4 py-2 text-sm text-green-800 dark:bg-green-950 dark:text-green-200">
                {{ page.props.flash.success }}
            </div>

            <div v-if="generations.data.length === 0" class="rounded-xl border p-10 text-center text-muted-foreground">
                No generations yet.
                <Link :href="route('generations.create')" class="underline">Create one</Link>.
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="g in generations.data" :key="g.id" class="flex flex-col overflow-hidden rounded-xl border">
                    <div class="flex aspect-square items-center justify-center bg-muted/50">
                        <img v-if="g.image_url && !expired(g)" :src="g.image_url" :alt="g.prompt" class="h-full w-full object-contain" />
                        <span v-else-if="g.status === 'succeeded'" class="text-sm text-muted-foreground">Image link expired</span>
                        <span v-else-if="g.status === 'failed'" class="px-4 text-center text-sm text-destructive">{{ g.error ?? 'Failed' }}</span>
                        <span v-else class="animate-pulse text-sm text-muted-foreground">{{ label(g.status) }}…</span>
                    </div>
                    <div class="flex flex-1 flex-col gap-2 p-3 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="badge[g.status]">{{ label(g.status) }}</span>
                            <span class="text-xs text-muted-foreground">{{ g.size }} · {{ g.quality }}</span>
                        </div>
                        <p class="line-clamp-3" :title="g.prompt">{{ g.prompt }}</p>
                        <dl class="mt-auto grid grid-cols-2 gap-1 text-xs tabular-nums">
                            <dt class="text-muted-foreground">Estimated</dt>
                            <dd class="text-right">up to {{ estimateUsd(g.estimated_charge) }}</dd>
                            <dt class="text-muted-foreground">Actual</dt>
                            <dd class="text-right font-medium">{{ g.charge === null ? '—' : exactUsd(g.charge) }}</dd>
                        </dl>
                        <div class="text-xs text-muted-foreground">{{ new Date(g.created_at).toLocaleString() }}</div>
                    </div>
                </div>
            </div>

            <div v-if="generations.links.length > 3" class="flex flex-wrap gap-1">
                <template v-for="l in generations.links" :key="l.label">
                    <Button v-if="l.url" as-child :variant="l.active ? 'default' : 'outline'" size="sm">
                        <Link :href="l.url" preserve-scroll><span v-html="l.label" /></Link>
                    </Button>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
