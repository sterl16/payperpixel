<script setup lang="ts">
import BuyCredits from '@/components/BuyCredits.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { estimate, formatUsd, usdToNanos, type PricingTable } from '@/lib/pricing';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    pricing: PricingTable;
    markup: string;
    sizes: string[];
    qualities: string[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Generate', href: '/generate' }];
const page = usePage<SharedData>();

const form = useForm({
    prompt: '',
    size: '1024x1024',
    quality: 'medium',
    reference_images: [] as string[],
});

const refs = computed(() => form.reference_images.filter((u) => u.trim() !== ''));

const quote = computed(() => estimate(props.pricing, props.markup, form.prompt, form.size, form.quality, refs.value.length));

const balance = computed(() => usdToNanos(page.props.balance ?? '0'));
const canAfford = computed(() => balance.value >= quote.value.charge);

function addRef() {
    if (form.reference_images.length < 4) {
        form.reference_images.push('');
    }
}

function submit() {
    form.transform((d) => ({ ...d, reference_images: refs.value })).post(route('generations.store'));
}
</script>

<template>
    <Head title="Generate" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4">
            <BuyCredits />

            <form class="flex flex-col gap-5" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="prompt">Prompt</Label>
                    <textarea
                        id="prompt"
                        v-model="form.prompt"
                        rows="5"
                        maxlength="4000"
                        required
                        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        placeholder="A lighthouse at dusk, oil painting"
                    />
                    <div class="text-right text-xs text-muted-foreground">{{ [...form.prompt].length }} / 4000</div>
                    <InputError :message="form.errors.prompt" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="size">Size</Label>
                        <select id="size" v-model="form.size" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm">
                            <option v-for="s in sizes" :key="s" :value="s">{{ s }}</option>
                        </select>
                        <InputError :message="form.errors.size" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="quality">Quality</Label>
                        <select id="quality" v-model="form.quality" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm capitalize">
                            <option v-for="q in qualities" :key="q" :value="q">{{ q }}</option>
                        </select>
                        <InputError :message="form.errors.quality" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label>Reference image URLs <span class="text-muted-foreground">(optional, up to 4)</span></Label>
                    <div v-for="(_, i) in form.reference_images" :key="i" class="flex gap-2">
                        <Input v-model="form.reference_images[i]" type="url" placeholder="https://…" />
                        <Button type="button" variant="ghost" size="sm" @click="form.reference_images.splice(i, 1)">Remove</Button>
                    </div>
                    <InputError v-for="(msg, key) in form.errors" v-show="String(key).startsWith('reference_images')" :key="key" :message="msg" />
                    <div>
                        <Button type="button" variant="outline" size="sm" :disabled="form.reference_images.length >= 4" @click="addRef">
                            Add reference image
                        </Button>
                    </div>
                </div>

                <div class="rounded-xl border bg-muted/40 p-4 text-sm">
                    <div class="flex items-baseline justify-between">
                        <span class="font-medium">Estimated cost</span>
                        <span class="text-xl font-semibold tabular-nums">up to {{ formatUsd(quote.charge) }}</span>
                    </div>
                    <p class="mt-2 text-muted-foreground">
                        This is the most you'll pay. Reasoning tokens and the exact prompt token count are only known once the image is done, so you're
                        charged the actual cost then, which is usually less. Failed generations are free.
                    </p>
                    <p v-if="!canAfford" class="mt-2 font-medium text-destructive">Not enough credits. Buy credits above to continue.</p>
                </div>

                <div>
                    <Button type="submit" :disabled="form.processing || !canAfford || form.prompt.trim() === ''">Generate</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
