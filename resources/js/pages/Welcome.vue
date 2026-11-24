<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import { Activity, Receipt, WandSparkles } from 'lucide-vue-next';

const features = [
    {
        icon: WandSparkles,
        title: 'Describe it, get it',
        body: 'Write a prompt, pick a size and quality, and add reference images if you like.',
    },
    {
        icon: Receipt,
        title: 'Know the price first',
        body: 'You see the most it can cost before you submit. You pay the actual cost, which is usually less. Failed images are free.',
    },
    {
        icon: Activity,
        title: 'Watch it happen',
        body: 'Every image moves from queued to processing to done in real time, with its cost recorded.',
    },
];
</script>

<template>
    <Head title="AI image generation" />

    <div class="relative flex min-h-screen flex-col overflow-hidden bg-neutral-100 text-foreground dark:bg-background">
        <!-- soft glow -->
        <div aria-hidden="true" class="pointer-events-none absolute -top-40 left-1/2 h-[520px] w-[900px] -translate-x-1/2 rounded-full bg-gradient-to-br from-teal-400/20 via-sky-400/10 to-violet-500/20 blur-3xl" />

        <header class="relative z-10 mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-5">
            <Link :href="route('home')" class="flex items-center gap-1">
                <AppLogo />
            </Link>
            <nav class="flex items-center gap-2">
                <Button v-if="$page.props.auth.user" as-child>
                    <Link :href="route('generations.create')">Open app</Link>
                </Button>
                <template v-else>
                    <Button variant="ghost" as-child>
                        <Link :href="route('login')">Log in</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="route('register')">Sign up</Link>
                    </Button>
                </template>
            </nav>
        </header>

        <main class="relative z-10 mx-auto flex w-full max-w-6xl flex-1 flex-col items-center px-6 pb-20 pt-16 text-center lg:pt-24">
            <div class="mb-8 flex size-16 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-400 via-blue-500 to-violet-600 shadow-lg shadow-blue-500/25">
                <AppLogoIcon class="size-9 text-white" />
            </div>

            <h1 class="max-w-3xl text-4xl font-semibold tracking-tight sm:text-6xl">
                Turn words into
                <span class="bg-gradient-to-r from-teal-500 via-blue-600 to-violet-600 bg-clip-text text-transparent">images</span>.
            </h1>
            <p class="mt-6 max-w-xl text-lg text-muted-foreground">
                Generate images from a prompt and pay only for what each one actually costs. No subscriptions, no surprises.
            </p>

            <div class="mt-10 flex flex-wrap justify-center gap-3">
                <Button v-if="$page.props.auth.user" size="lg" as-child>
                    <Link :href="route('generations.create')">Start generating</Link>
                </Button>
                <template v-else>
                    <Button size="lg" as-child>
                        <Link :href="route('register')">Create an account</Link>
                    </Button>
                    <Button size="lg" variant="outline" as-child>
                        <Link :href="route('login')">Log in</Link>
                    </Button>
                </template>
            </div>

            <section class="mt-24 grid w-full gap-4 text-left sm:grid-cols-3">
                <div v-for="f in features" :key="f.title" class="rounded-2xl border bg-card/60 p-6 backdrop-blur">
                    <div class="mb-4 flex size-10 items-center justify-center rounded-lg bg-gradient-to-br from-teal-400/15 to-violet-600/15 text-blue-700 dark:text-sky-400">
                        <component :is="f.icon" class="size-5" />
                    </div>
                    <h2 class="font-medium">{{ f.title }}</h2>
                    <p class="mt-2 text-sm text-muted-foreground">{{ f.body }}</p>
                </div>
            </section>
        </main>

        <footer class="relative z-10 border-t">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-6 py-6 text-sm text-muted-foreground">
                <span>&copy; {{ new Date().getFullYear() }} {{ $page.props.name }}</span>
                <span>Powered by the Pixelforge API</span>
            </div>
        </footer>
    </div>
</template>
