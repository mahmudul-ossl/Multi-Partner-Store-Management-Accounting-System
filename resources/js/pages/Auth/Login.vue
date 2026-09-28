<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <div class="grid min-h-screen lg:grid-cols-2">
        <Head title="Sign in" />
        <section class="hidden flex-col justify-between bg-slate-950 p-12 text-white lg:flex">
            <div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-teal-500 text-xl font-bold">৳</div>
                <h1 class="mt-8 max-w-md text-4xl font-semibold tracking-tight">{{ page.props.app.name }}</h1>
                <p class="mt-4 max-w-md text-lg text-slate-300">
                    Partner investments, withdrawals, inventory, and accounts for a multi-partner store. Phase 1 covers people, access, and the audit trail those records will stand on.
                </p>
            </div>
            <p class="text-sm text-slate-400">Asia/Dhaka · Bangladeshi Taka</p>
        </section>

        <section class="flex items-center justify-center px-6 py-12">
            <form class="w-full max-w-md" @submit.prevent="submit">
                <h2 class="text-2xl font-semibold text-slate-900">Sign in</h2>
                <p class="mt-2 text-sm text-slate-500">Use the account an administrator created for you.</p>

                <label class="label mt-8" for="email">Email</label>
                <input id="email" v-model="form.email" class="field" type="email" autocomplete="username" required>
                <p v-if="form.errors.email" class="error">{{ form.errors.email }}</p>

                <label class="label mt-4" for="password">Password</label>
                <input id="password" v-model="form.password" class="field" type="password" autocomplete="current-password" required>
                <p v-if="form.errors.password" class="error">{{ form.errors.password }}</p>

                <label class="mt-4 flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-teal-700">
                    Remember this browser
                </label>

                <button class="btn btn-primary mt-6 w-full" type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Signing in…' : 'Sign in' }}
                </button>
            </form>
        </section>
    </div>
</template>
