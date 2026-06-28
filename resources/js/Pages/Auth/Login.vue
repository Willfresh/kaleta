<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <!-- Thème d'arrière-plan luxueux pour la page de connexion de KALETA -->
    <div class="min-h-screen bg-[#070708] text-stone-100 font-sans flex flex-col items-center justify-center p-4 relative overflow-hidden">

        <!-- Orbes de lueur d'ambiance en arrière-plan -->
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[450px] h-[400px] bg-emerald-500/10 blur-[100px] rounded-full pointer-events-none"></div>
        <div class="absolute top-10 left-10 w-[200px] h-[200px] bg-amber-500/5 blur-[80px] rounded-full pointer-events-none"></div>

        <Head title="Connexion - KALETA" />

        <div class="w-full sm:max-w-md bg-slate-900/60 border border-slate-800/80 backdrop-blur-xl p-8 rounded-3xl shadow-2xl relative z-10 space-y-6">

            <!-- Logo Officiel KALETA en suspension -->
            <div class="text-center space-y-3">
                <div class="flex justify-center">
                    <img
                        src="/images/kaleta.png"
                        class="w-24 h-24 object-contain drop-shadow-2xl animate-float select-none pointer-events-none"
                        alt="Logo KALETA"
                    />
                </div>
                <div>
                    <h1 class="text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-green-400 via-emerald-500 to-emerald-600 tracking-wider uppercase font-serif">
                        KALETA
                    </h1>
                    <p class="text-xs text-stone-500 uppercase tracking-widest font-semibold mt-1">Espace d'Administration</p>
                </div>
            </div>

            <!-- Message d'état -->
            <div v-if="status" class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-sm font-semibold text-emerald-400 text-center">
                {{ status }}
            </div>

            <!-- Formulaire de Connexion -->
            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider mb-2 text-stone-400">Adresse Email</label>
                    <input
                        id="email"
                        type="email"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="votre@email.com"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-stone-100 focus:border-emerald-500 focus:outline-none transition-all outline-none"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider mb-2 text-stone-400">Mot de passe</label>
                    <input
                        id="password"
                        type="password"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-stone-100 focus:border-emerald-500 focus:outline-none transition-all outline-none"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between pt-2">
                    <label class="flex items-center cursor-pointer select-none">
                        <Checkbox name="remember" v-model:checked="form.remember" class="border-slate-800 bg-slate-950 text-emerald-500 focus:ring-emerald-500/50 rounded" />
                        <span class="ms-2 text-xs text-stone-400">Se souvenir de moi</span>
                    </label>

                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="text-xs text-stone-400 hover:text-amber-500 underline focus:outline-none transition-colors"
                    >
                        Mot de passe oublié ?
                    </Link>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-3.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-400 hover:to-emerald-500 disabled:opacity-50 text-white font-bold rounded-xl transition-all shadow-lg shadow-emerald-500/20 hover:shadow-emerald-500/40 text-sm mt-4 flex items-center justify-center gap-2"
                >
                    {{ form.processing ? 'Connexion en cours... ⏳' : 'Se Connecter 🔑' }}
                </button>
            </form>
        </div>
    </div>
</template>

<style scoped>
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}
.animate-float {
    animation: float 4s ease-in-out infinite;
}
</style>
