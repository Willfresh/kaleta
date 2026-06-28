
<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import * as THREE from 'three';

// 1. Réception des données du serveur
const props = withDefaults(defineProps<{
    today?: string;
    dailyMenu?: Array<{
        id: number;
        name: string;
        price: number | string;
        category: string;
        description?: string;
        image_path?: string;
    }>;
    allDishes?: Array<{
        id: number;
        name: string;
        price: number | string;
        category: string;
        description?: string;
        image_path?: string;
    }>;
}>(), {
    today: 'Aujourd\'hui',
    dailyMenu: () => [],
    allDishes: () => []
});

const isDarkMode = ref(true);
const scrollProgress = ref(0);
const showScrollTop = ref(false);

// Navigation flottante
const navLinks = [
    { id: 'accueil', label: 'Accueil', icon: '🏠' },
    { id: 'quotidien', label: 'Agenda', icon: '📅' },
    { id: 'menu', label: 'Carte', icon: '📜' },
    { id: 'galerie', label: 'Galerie', icon: '📸' },
    { id: 'contact', label: 'Contact', icon: '📍' },
];
const activeSection = ref('accueil');

const scrollTo = (id: string) => {
    const el = document.getElementById(id);
    if (el) {
        const offset = 80;
        const y = el.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({ top: y, behavior: 'smooth' });
    }
};

const scrollTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

// Détection de la section active & Reveal on scroll
onMounted(() => {
    const sectionIds = navLinks.map(l => l.id);
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                activeSection.value = entry.target.id;
            }
        });
    }, { rootMargin: '-20% 0px -60% 0px', threshold: 0 });

    nextTick(() => {
        sectionIds.forEach(id => {
            const el = document.getElementById(id);
            if (el) observer.observe(el);
        });

        // Reveal elements
        const revealEls = document.querySelectorAll('.reveal');
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        revealEls.forEach(el => revealObserver.observe(el));
    });

    onUnmounted(() => {
        observer.disconnect();
    });
});

// 2. Gestion WebGL (Three.js)
let animationId: number;
let renderer: THREE.WebGLRenderer;

onMounted(() => {
    const canvas = document.getElementById('webgl-canvas') as HTMLCanvasElement;
    if (!canvas) return;

    const mouse = { x: 0, y: 0, targetX: 0, targetY: 0 };
    const scroll = { current: 0, target: 0 };

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 1000);
    camera.position.z = 7;

    renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

    const ambientLight = new THREE.AmbientLight(0xffffff, 0.4);
    scene.add(ambientLight);

    const amberLight = new THREE.DirectionalLight(0xf59e0b, 3);
    amberLight.position.set(2, 6, 4);
    scene.add(amberLight);

    const greenLight = new THREE.PointLight(0x10b981, 5, 20);
    greenLight.position.set(-4, -2, 2);
    scene.add(greenLight);

    const leafShape = new THREE.Shape();
    leafShape.moveTo(0, 0);
    leafShape.quadraticCurveTo(0.8, 1.2, 0, 2.6);
    leafShape.quadraticCurveTo(-0.8, 1.2, 0, 0);

    const geometry = new THREE.ShapeGeometry(leafShape);
    geometry.center();

    const pos = geometry.attributes.position;
    for (let i = 0; i < pos.count; i++) {
        const x = pos.getX(i);
        const z = -Math.sin((x + 0.8) * Math.PI / 1.6) * 0.25;
        pos.setZ(i, z);
    }
    geometry.computeVertexNormals();

    const material = new THREE.MeshStandardMaterial({
        color: 0x0f5132,
        roughness: 0.35,
        metalness: 0.15,
        side: THREE.DoubleSide
    });

    const leaves: THREE.Mesh[] = [];
    const leafData: Array<any> = [];
    const numLeaves = 22;

    for (let i = 0; i < numLeaves; i++) {
        const mesh = new THREE.Mesh(geometry, material);
        const zDepth = (Math.random() - 0.5) * 6;
        const xPos = (Math.random() - 0.5) * 16;
        const yPos = (Math.random() - 0.5) * 14;

        mesh.position.set(xPos, yPos, zDepth);
        const scale = 0.35 + Math.random() * 0.9;
        mesh.scale.set(scale, scale, scale);
        mesh.rotation.set(Math.random() * Math.PI, Math.random() * Math.PI, Math.random() * Math.PI);

        scene.add(mesh);
        leaves.push(mesh);

        leafData.push({
            baseX: xPos, baseY: yPos, baseZ: zDepth,
            speedY: 0.006 + Math.random() * 0.012,
            speedRotX: 0.003 + Math.random() * 0.007,
            speedRotY: 0.003 + Math.random() * 0.007,
            driftSpeed: 0.001 + Math.random() * 0.002,
            driftOffset: Math.random() * 100,
            parallaxFactor: 1 + (zDepth + 3) / 3
        });
    }

    const orbGeometry = new THREE.SphereGeometry(0.05, 8, 8);
    const orbMaterial = new THREE.MeshBasicMaterial({
        color: 0xfbbf24,
        transparent: true,
        opacity: 0.85
    });

    const orbs: THREE.Mesh[] = [];
    const orbData: Array<any> = [];
    const numOrbs = 30;

    for (let i = 0; i < numOrbs; i++) {
        const orb = new THREE.Mesh(orbGeometry, orbMaterial);
        const zDepth = (Math.random() - 0.5) * 4;
        const xPos = (Math.random() - 0.5) * 16;
        const yPos = (Math.random() - 0.5) * 12;

        orb.position.set(xPos, yPos, zDepth);
        scene.add(orb);
        orbs.push(orb);

        orbData.push({
            baseX: xPos, baseY: yPos, baseZ: zDepth,
            speedY: 0.003 + Math.random() * 0.005,
            pulseSpeed: 1.5 + Math.random() * 2,
            pulseOffset: Math.random() * Math.PI,
            driftSpeed: 0.001 + Math.random() * 0.002
        });
    }

    const clock = new THREE.Clock();

    const onMouseMove = (event: MouseEvent) => {
        mouse.targetX = (event.clientX / window.innerWidth) * 2 - 1;
        mouse.targetY = -(event.clientY / window.innerHeight) * 2 + 1;
    };

    const onScroll = () => {
        scroll.target = window.scrollY;
        const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
        scrollProgress.value = scrollHeight > 0 ? (window.scrollY / scrollHeight) * 100 : 0;
        showScrollTop.value = window.scrollY > 500;
    };

    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('scroll', onScroll);

    const tick = () => {
        const elapsedTime = clock.getElapsedTime();

        mouse.x += (mouse.targetX - mouse.x) * 0.08;
        mouse.y += (mouse.targetY - mouse.y) * 0.08;
        scroll.current += (scroll.target - scroll.current) * 0.08;

        camera.position.x += (mouse.x * 1.5 - camera.position.x) * 0.05;
        camera.position.y += (mouse.y * 1.2 - camera.position.y) * 0.05;
        camera.lookAt(scene.position);

        const mouse3D = new THREE.Vector3(mouse.x * 9, mouse.y * 6, 0);

        leaves.forEach((mesh, index) => {
            const data = leafData[index];
            data.baseY -= data.speedY;
            if (data.baseY < -8) {
                data.baseY = 8;
                data.baseX = (Math.random() - 0.5) * 16;
            }

            const scrollOffset = (scroll.current * 0.012) * data.parallaxFactor;
            const targetX = data.baseX + Math.sin(elapsedTime + data.driftOffset) * (data.driftSpeed * 10);
            const targetY = data.baseY + scrollOffset;

            const leafToMouse = new THREE.Vector3(mesh.position.x, mesh.position.y, 0).sub(mouse3D);
            const distance = leafToMouse.length();

            if (distance < 3.2) {
                const force = (3.2 - distance) / 3.2;
                leafToMouse.normalize();
                mesh.position.x += leafToMouse.x * force * 0.12;
                mesh.position.y += leafToMouse.y * force * 0.12;
                mesh.rotation.x += data.speedRotX * (1 + force * 8);
                mesh.rotation.y += data.speedRotY * (1 + force * 8);
            } else {
                mesh.position.x += (targetX - mesh.position.x) * 0.05;
                mesh.position.y += (targetY - mesh.position.y) * 0.05;
                mesh.rotation.x += data.speedRotX;
                mesh.rotation.y += data.speedRotY;
            }
        });

        orbs.forEach((orb, index) => {
            const data = orbData[index];
            data.baseY += data.speedY;
            if (data.baseY > 7) {
                data.baseY = -7;
                data.baseX = (Math.random() - 0.5) * 16;
            }

            const scrollOffset = (scroll.current * 0.012) * 1.5;
            const targetX = data.baseX + Math.sin(elapsedTime * 0.6 + data.pulseOffset) * 0.4;
            const targetY = data.baseY + scrollOffset;

            const orbToMouse = new THREE.Vector3(orb.position.x, orb.position.y, 0).sub(mouse3D);
            const distance = orbToMouse.length();

            if (distance < 2.5) {
                const force = (2.5 - distance) / 2.5;
                orbToMouse.normalize();
                orb.position.x += orbToMouse.x * force * 0.15;
                orb.position.y += orbToMouse.y * force * 0.15;
            } else {
                orb.position.x += (targetX - orb.position.x) * 0.06;
                orb.position.y += (targetY - orb.position.y) * 0.06;
            }

            const scaleFactor = 0.7 + Math.sin(elapsedTime * data.pulseSpeed + data.pulseOffset) * 0.4;
            orb.scale.set(scaleFactor, scaleFactor, scaleFactor);
        });

        renderer.render(scene, camera);
        animationId = requestAnimationFrame(tick);
    };

    tick();

    const handleResize = () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    };
    window.addEventListener('resize', handleResize);

    onUnmounted(() => {
        cancelAnimationFrame(animationId);
        window.removeEventListener('mousemove', onMouseMove);
        window.removeEventListener('scroll', onScroll);
        window.removeEventListener('resize', handleResize);
        renderer.dispose();
    });
});

// 3. Logique de la carte
const activeCategory = ref('Tous');
const categories = computed(() => {
    const list = new Set(props.allDishes.map(d => d.category));
    return ['Tous', ...Array.from(list)];
});
const filteredDishes = computed(() => {
    if (activeCategory.value === 'Tous') return props.allDishes;
    return props.allDishes.filter(d => d.category === activeCategory.value);
});

// 4. Gestion du panier
const cart = ref<Array<{ id: number; name: string; price: number; quantity: number }>>([]);
const isCartOpen = ref(false);

const addToCart = (dish: any) => {
    const existing = cart.value.find(item => item.id === dish.id);
    if (existing) {
        existing.quantity++;
    } else {
        cart.value.push({
            id: dish.id,
            name: dish.name,
            price: Number(dish.price),
            quantity: 1
        });
    }
};

const removeFromCart = (id: number) => {
    cart.value = cart.value.filter(item => item.id !== id);
};

const updateQuantity = (id: number, delta: number) => {
    const item = cart.value.find(i => i.id === id);
    if (item) {
        item.quantity += delta;
        if (item.quantity <= 0) {
            removeFromCart(id);
        }
    }
};

const cartTotal = computed(() => {
    return cart.value.reduce((total, item) => total + (item.price * item.quantity), 0);
});

const cartCount = computed(() => {
    return cart.value.reduce((count, item) => count + item.quantity, 0);
});

const orderForm = useForm({
    customer_name: '',
    customer_phone: '',
    delivery_address: '',
    items: [] as Array<{ dish_id: number; quantity: number; price: number }>
});

const submitOrder = () => {
    orderForm.items = cart.value.map(item => ({
        dish_id: item.id,
        quantity: item.quantity,
        price: item.price
    }));

    orderForm.post('/orders', {
        onSuccess: () => {
            cart.value = [];
            isCartOpen.value = false;
            orderForm.reset();
            alert('🎉 Félicitations ! Votre commande a bien été validée ! 🛵🍲');
        }
    });
};

// 5. Gestion des réservations
const isReservationOpen = ref(false);
const reservationForm = useForm({
    name: '',
    phone: '',
    guests: 2,
    date: '',
    time: ''
});

const submitReservation = () => {
    reservationForm.post('/reservations', {
        onSuccess: () => {
            isReservationOpen.value = false;
            reservationForm.reset();
            alert('🥂 Votre réservation a bien été enregistrée chez KALETA ! À bientôt ! 🥂');
        },
    });
};

// Features data
const features = [
    { icon: '🍳', title: 'Cuisine Artisanale', description: 'Chaque plat est préparé avec passion par notre chef, à partir d\'ingrédients frais et locaux.', gradient: 'from-amber-500 to-orange-600' },
    { icon: '🌿', title: 'Cadre Enchanteur', description: 'Notre terrasse verdoyante offre une évasion au cœur de la ville, entre nature et élégance.', gradient: 'from-emerald-500 to-green-600' },
    { icon: '⚡', title: 'Service Soigné', description: 'De l\'accueil à la livraison, notre équipe veille à chaque détail pour une expérience sans faille.', gradient: 'from-cyan-500 to-blue-600' },
    { icon: '📱', title: 'Commande Express', description: 'Réservez votre table ou commandez vos plats en quelques clics, simplement et rapidement.', gradient: 'from-purple-500 to-violet-600' },
];

const stats = [
    { icon: '⭐', title: 'Clients Satisfaits', value: '5 000+' },
    { icon: '🏆', title: 'Plats Signature', value: '50+' },
    { icon: '🥂', title: 'Expériences Vécues', value: '12 000+' },
];

// Nouvelles données : Galerie & Avis
const galleryImages = [
    { url: 'images/terasse.png', span: 'md:col-span-2 md:row-span-2', title: '' },
    { url: 'images/photo_4_2026-06-21_10-49-48.jpg', span: '', title: '' },
    { url: 'images/photo_3_2026-06-21_10-49-48.jpg', span: '', title: '' },
    { url: 'images/photo_2_2026-06-21_10-49-48.jpg', span: '', title: '' },
    { url: 'images/photo_2026-06-21_10-49-18.jpg', span: '', title: '' }
];

</script>

<template>
    <Head title="KALETA - Terrasse & Lounge" />



    <div
        :class="[
            isDarkMode ? 'bg-gradient-to-br from-neutral-950 via-stone-950 to-emerald-950' : 'bg-gradient-to-br from-[#f0f4f1] via-white to-emerald-50',
            'min-h-screen font-sans selection:bg-emerald-600 selection:text-white relative overflow-hidden transition-colors duration-500'
        ]"
    >
        <!-- Barre de progression de scroll -->
        <div
            class="fixed top-0 left-0 h-[3px] bg-gradient-to-r from-amber-500 via-emerald-500 to-purple-500 z-50 transition-all duration-150 ease-out"
            :style="{ width: scrollProgress + '%' }"
        ></div>

        <!-- IMAGE DE FOND DU HEADER -->
        <div
            class="absolute top-0 left-0 w-full h-[680px] md:h-[780px] bg-cover bg-center pointer-events-none transition-all duration-500 z-0"
            :class="isDarkMode ? 'opacity-40' : 'opacity-20'"
            style="background-image: url('/images/facade.png');"
        >
            <div
                class="absolute inset-0 bg-gradient-to-b transition-colors duration-500"
                :class="isDarkMode ? 'from-black/10 to-[#0c0c0d]' : 'from-white/10 to-[#f0f4f1]'"
            ></div>
        </div>

        <!-- CANVAS WEBGL -->
        <canvas id="webgl-canvas" class="fixed inset-0 w-full h-full pointer-events-none z-10"></canvas>

        <!-- MENU FLOTTANT -->
        <nav
            class="fixed top-5 left-1/2 -translate-x-1/2 z-50 flex items-center gap-0.5 md:gap-1 px-1.5 md:px-2.5 py-1.5 md:py-2 rounded-full backdrop-blur-2xl border shadow-2xl transition-all duration-500"
            :class="isDarkMode ? 'bg-neutral-900/70 border-white/[0.08] shadow-black/40' : 'bg-white/75 border-emerald-200/80 shadow-emerald-900/5'"
        >
            <a
                v-for="link in navLinks"
                :key="link.id"
                :href="'#' + link.id"
                @click.prevent="scrollTo(link.id)"
                :class="[
                    'px-2 md:px-3 py-1 md:py-1.5 rounded-full text-[10px] md:text-[11px] font-bold uppercase tracking-wider transition-all duration-300 whitespace-nowrap',
                    activeSection === link.id
                        ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-stone-950 shadow-lg shadow-amber-500/25 scale-105'
                        : isDarkMode ? 'text-stone-400 hover:text-stone-100 hover:bg-white/5' : 'text-stone-500 hover:text-stone-900 hover:bg-emerald-500/5'
                ]"
            >
                <span class="hidden md:inline mr-1">{{ link.icon }}</span>
                {{ link.label }}
            </a>

            <div class="w-px h-4 md:h-5 mx-1 md:mx-1.5" :class="isDarkMode ? 'bg-white/10' : 'bg-emerald-300/50'"></div>

            <button
                @click="isDarkMode = !isDarkMode"
                :class="isDarkMode ? 'text-amber-400 hover:bg-amber-500/15' : 'text-amber-600 hover:bg-amber-100'"
                class="p-1.5 md:p-2 rounded-full transition-all duration-300 text-sm md:text-base hover:rotate-12"
                title="Changer de thème"
            >
                {{ isDarkMode ? '☀️' : '🌙' }}
            </button>
        </nav>

        <!-- Bouton flottant panier -->
        <Teleport to="body">
            <Transition name="bounce">
                <button
                    v-if="cartCount > 0"
                    @click="isCartOpen = true"
                    class="fixed bottom-8 right-8 z-40 px-5 py-3.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-400 hover:to-emerald-500 text-white font-bold rounded-full shadow-2xl shadow-emerald-500/40 flex items-center gap-2.5 transition-all transform hover:scale-110"
                >
                    <span class="text-base">🛒</span>
                    <span class="text-sm hidden sm:inline">Panier</span>
                    <span class="min-w-[24px] h-6 flex items-center justify-center px-1.5 bg-amber-400 text-emerald-950 rounded-full text-[11px] font-black shadow-lg">
                        {{ cartCount }}
                    </span>
                </button>
            </Transition>

            <!-- Bouton Scroll to Top -->
            <Transition name="fade">
                <button
                    v-if="showScrollTop"
                    @click="scrollTop"
                    class="fixed bottom-8 left-8 z-40 w-12 h-12 rounded-full backdrop-blur-md border flex items-center justify-center transition-all hover:scale-110"
                    :class="isDarkMode ? 'bg-stone-900/80 border-stone-700 text-amber-400 hover:bg-stone-800' : 'bg-white/80 border-stone-200 text-amber-600 hover:bg-white'"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                </button>
            </Transition>
        </Teleport>

        <!-- Contenu principal -->
        <div class="relative z-20">

            <!-- HERO SECTION -->
            <header id="accueil" class="relative max-w-6xl mx-auto px-6 pt-28 pb-20 text-center">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full border text-[11px] font-semibold uppercase tracking-[0.2em] mb-8 backdrop-blur-md transition-all duration-500"
                    :class="isDarkMode ? 'bg-emerald-950/60 border-emerald-500/30 text-emerald-300' : 'bg-emerald-50/60 border-emerald-300/50 text-emerald-700'"
                >
                    <span class="inline-block animate-spin" style="animation-duration: 8s;">🌿</span>
                    Ambiance Nature & Bois Précieux
                </div>

                <div class="flex justify-center mb-5">
                    <img src="/images/kaleta.png" class="w-28 h-28 md:w-36 md:h-36 object-contain drop-shadow-2xl animate-float select-none pointer-events-none" alt="Logo KALETA" />
                </div>

                <h1 class="text-6xl sm:text-7xl md:text-9xl font-black text-transparent bg-clip-text bg-gradient-to-r from-green-400 via-emerald-500 to-emerald-600 tracking-tighter uppercase font-serif drop-shadow-2xl mb-4">
                    KALETA
                </h1>

                <p class="mt-4 text-lg sm:text-xl md:text-2xl max-w-2xl mx-auto font-light tracking-wide" :class="isDarkMode ? 'text-stone-300' : 'text-stone-700'">
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-cyan-400 font-medium">Terrasse & Lounge</span><br>
                    <span class="italic text-stone-500 text-base sm:text-lg">« Où chaque moment devient un souvenir »</span>
                </p>

                <div class="flex items-center justify-center gap-3 mt-8">
                    <div class="w-16 h-px bg-gradient-to-r from-transparent to-amber-500/50"></div>
                    <div class="w-2 h-2 rounded-full bg-amber-500/60"></div>
                    <div class="w-16 h-px bg-gradient-to-l from-transparent to-amber-500/50"></div>
                </div>

                <p class="mt-6 text-sm text-stone-400 flex items-center justify-center gap-2"><span>📍</span> Deux Lions, Agoë — Lomé, Togo</p>

                <div class="flex flex-col sm:flex-row justify-center gap-4 mt-10">
                    <button @click="isReservationOpen = true" class="group px-8 py-4 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-stone-950 font-bold rounded-2xl transition-all duration-300 transform hover:scale-105 shadow-2xl shadow-amber-500/20 flex items-center justify-center gap-2.5 text-lg">
                        <span class="group-hover:scale-125 transition-transform duration-300">🥂</span> Réserver une Table
                    </button>
                    <button @click="scrollTo('menu')" class="px-8 py-4 font-bold rounded-2xl border transition-all duration-300 backdrop-blur-sm flex items-center justify-center gap-2.5 text-lg hover:scale-105 transform"
                        :class="isDarkMode ? 'bg-stone-800/50 hover:bg-stone-700/50 text-stone-100 border-stone-700/50' : 'bg-white/50 hover:bg-white text-stone-900 border-emerald-200'">
                        <span>📜</span> Consulter la Carte
                    </button>
                </div>
            </header>

            <!-- MENU DU JOUR (AGENDA) -->
            <section id="quotidien" class="max-w-5xl mx-auto px-6 py-12 scroll-mt-24 reveal">
                <div class="bg-gradient-to-br p-8 md:p-12 rounded-3xl border shadow-2xl relative overflow-hidden backdrop-blur-xl transition-all duration-500"
                    :class="isDarkMode ? 'from-emerald-950/30 via-stone-900/30 to-orange-950/15 border-emerald-500/15' : 'from-emerald-50/50 via-white/50 to-amber-50/50 border-emerald-200/60'"
                >
                    <div class="absolute -top-20 -right-20 w-48 h-48 bg-amber-500/[0.04] rounded-full blur-3xl pointer-events-none"></div>
                    <div class="absolute -bottom-20 -left-20 w-48 h-48 bg-emerald-500/[0.04] rounded-full blur-3xl pointer-events-none"></div>

                    <div class="relative">
                        <div class="flex items-start justify-between mb-10">
                            <div>
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="w-8 h-0.5 bg-gradient-to-r from-amber-500 to-transparent rounded-full"></span>
                                    <span class="text-[10px] uppercase tracking-[0.25em] font-bold text-amber-500">Chef's Selection</span>
                                </div>
                                <h2 class="text-3xl md:text-4xl font-black" :class="isDarkMode ? 'text-emerald-400' : 'text-emerald-600'">Spécialités du Jour</h2>
                                <p class="text-sm mt-2 flex items-center gap-2 text-stone-500">📅 {{ today }}</p>
                            </div>
                            <div class="hidden md:block text-5xl opacity-10 select-none">🍝</div>
                        </div>

                        <div v-if="dailyMenu && dailyMenu.length > 0" class="grid md:grid-cols-2 gap-6">
                            <div v-for="dish in dailyMenu" :key="dish.id"
                                class="group relative p-5 rounded-3xl border shadow-xl hover:shadow-2xl transition-all duration-500 hover:-translate-y-1 overflow-hidden"
                                :class="isDarkMode ? 'bg-stone-900/50 border-stone-800/60 hover:border-amber-500/40' : 'bg-white/70 border-emerald-100/80 hover:border-amber-400/60'"
                            >
                                <div class="absolute inset-0 bg-gradient-to-br from-amber-500/0 to-emerald-500/0 group-hover:from-amber-500/[0.03] group-hover:to-emerald-500/[0.03] transition-all duration-500"></div>

                                <div class="relative flex gap-5">
                                    <div class="relative w-28 h-28 rounded-2xl overflow-hidden shrink-0 shadow-lg ring-1 ring-stone-800/30">
                                        <img v-if="dish.image_path" :src="'/storage/' + dish.image_path" class="w-full h-full object-cover transform group-hover:scale-125 transition-transform duration-700" alt="Plat" />
                                        <div v-else class="w-full h-full bg-gradient-to-br flex items-center justify-center" :class="isDarkMode ? 'from-emerald-950/60 to-stone-900' : 'from-emerald-50 to-emerald-100'">
                                            <span class="text-3xl group-hover:scale-125 transition-transform duration-300">🍝</span>
                                        </div>
                                        <span class="absolute top-2 left-2 px-2.5 py-0.5 bg-gradient-to-r from-amber-500 to-orange-500 text-[8px] font-black text-stone-950 uppercase rounded-full shadow-lg tracking-widest">Signature</span>
                                    </div>

                                    <div class="flex-1 flex flex-col justify-between min-w-0">
                                        <div>
                                            <span class="text-[10px] text-emerald-400 uppercase tracking-widest font-black bg-emerald-950/30 px-2 py-0.5 rounded inline-block">{{ dish.category }}</span>
                                            <h3 class="font-black text-lg mt-2 group-hover:text-amber-500 transition-colors duration-300 truncate" :class="isDarkMode ? 'text-stone-100' : 'text-stone-900'">{{ dish.name }}</h3>
                                            <p v-if="dish.description" class="text-xs font-light leading-relaxed mt-1.5 line-clamp-2" :class="isDarkMode ? 'text-stone-400' : 'text-stone-600'">{{ dish.description }}</p>
                                        </div>
                                        <div class="flex items-center justify-between mt-3 pt-3 border-t" :class="isDarkMode ? 'border-stone-700/40' : 'border-emerald-100/80'">
                                            <span class="text-xl font-black text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-orange-500">{{ Number(dish.price).toLocaleString('fr-FR') }} F</span>
                                            <button @click="addToCart(dish)" class="px-4 py-2 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-500 hover:to-emerald-600 text-white font-bold text-xs rounded-xl shadow-lg transition-all transform hover:scale-105 active:scale-95">
                                                + Ajouter
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="text-center py-14">
                            <span class="text-5xl mb-4 block opacity-30">🍽️</span>
                            <p class="text-stone-400 italic text-lg mb-2">Aucune suggestion programmée pour ce jour.</p>
                            <p class="text-stone-500 text-sm">Découvrez notre carte complète ci-dessous 👇</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CARTE COMPLÈTE -->
            <section id="menu" class="max-w-6xl mx-auto px-6 py-20 scroll-mt-24 reveal">
                <div class="text-center mb-14">
                    <div class="flex items-center justify-center gap-3 mb-4">
                        <span class="w-10 h-px bg-gradient-to-r from-transparent to-amber-500/50"></span>
                        <span class="text-[10px] uppercase tracking-[0.3em] font-bold text-amber-500">Notre Sélection</span>
                        <span class="w-10 h-px bg-gradient-to-l from-transparent to-amber-500/50"></span>
                    </div>
                    <h2 class="text-4xl sm:text-5xl md:text-6xl font-black" :class="isDarkMode ? 'text-stone-100' : 'text-stone-900'">Carte Complète</h2>
                    <p class="mt-3 text-sm text-stone-500">Des plats soigneusement élaborés pour ravir vos papilles</p>
                </div>

                <div class="flex flex-wrap justify-center gap-2 mb-12">
                    <button v-for="cat in categories" :key="cat" @click="activeCategory = cat"
                        :class="[
                            'px-5 py-2 rounded-full text-xs font-bold uppercase tracking-wider transition-all duration-300 border',
                            activeCategory === cat
                                ? 'bg-gradient-to-r from-amber-500 to-orange-500 text-stone-950 shadow-lg shadow-amber-500/25 scale-105 border-amber-400'
                                : isDarkMode ? 'bg-stone-900/40 hover:bg-stone-800/60 text-stone-400 border-stone-800/60 hover:border-amber-500/30 hover:text-stone-200' : 'bg-white/50 hover:bg-white text-stone-600 border-emerald-200/60 hover:border-amber-400 hover:text-stone-900'
                        ]"
                    >{{ cat }}</button>
                </div>

                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="dish in filteredDishes" :key="dish.id"
                        class="group relative rounded-3xl border shadow-lg hover:shadow-2xl transition-all duration-500 hover:-translate-y-1.5 overflow-hidden backdrop-blur-sm"
                        :class="isDarkMode ? 'bg-stone-900/30 border-stone-800/40 hover:bg-stone-900/50 hover:border-emerald-500/30' : 'bg-white/50 border-emerald-100/60 hover:bg-white hover:border-emerald-400/60'"
                    >
                        <div class="relative w-full h-48 overflow-hidden">
                            <img v-if="dish.image_path" :src="'/storage/' + dish.image_path" class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-700" alt="Plat" />
                            <div v-else class="w-full h-full bg-gradient-to-br flex items-center justify-center text-5xl" :class="isDarkMode ? 'from-emerald-950/40 to-stone-900/60' : 'from-emerald-50 to-emerald-100/50'">🍽️</div>
                            <div class="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-black/60 to-transparent pointer-events-none"></div>
                            <span class="absolute bottom-3 left-3 px-3 py-1 bg-black/50 backdrop-blur-md text-[10px] font-bold text-emerald-300 uppercase tracking-wider rounded-full border border-emerald-500/30">
                                {{ dish.category }}
                            </span>
                        </div>

                        <div class="p-5 space-y-3 relative">
                            <h3 class="font-black text-lg group-hover:text-amber-500 transition-colors duration-300 line-clamp-2 leading-snug" :class="isDarkMode ? 'text-stone-100' : 'text-stone-900'">{{ dish.name }}</h3>
                            <p v-if="dish.description" class="text-xs font-light leading-relaxed line-clamp-2" :class="isDarkMode ? 'text-stone-400' : 'text-stone-600'">{{ dish.description }}</p>

                            <div class="flex items-center justify-between pt-3 border-t" :class="isDarkMode ? 'border-stone-800/50' : 'border-emerald-100/60'">
                                <div class="flex flex-col">
                                    <span class="text-[10px] uppercase tracking-wider text-stone-500">Prix</span>
                                    <span class="text-xl font-black text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-orange-500">{{ Number(dish.price).toLocaleString('fr-FR') }} F</span>
                                </div>
                                <button @click="addToCart(dish)" class="px-4 py-2.5 flex items-center gap-1.5 rounded-xl bg-gradient-to-br from-emerald-600 to-emerald-700 hover:from-emerald-500 hover:to-emerald-600 text-white text-xs font-bold shadow-md transition-all transform hover:scale-105 active:scale-95">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                    Ajouter
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="filteredDishes.length === 0" class="text-center py-16">
                    <span class="text-5xl mb-4 block opacity-30">🔍</span>
                    <p class="text-stone-400 italic text-lg">Aucun plat dans cette catégorie.</p>
                </div>
            </section>

            <!-- NOUVELLE SECTION : GALERIE -->
            <section id="galerie" class="max-w-6xl mx-auto px-6 py-20 scroll-mt-24 reveal">
                <div class="text-center mb-14">
                    <span class="text-[10px] uppercase tracking-[0.3em] font-bold text-emerald-500">Notre Univers</span>
                    <h2 class="text-4xl sm:text-5xl font-black mt-2" :class="isDarkMode ? 'text-stone-100' : 'text-stone-900'">Galerie & Ambiance</h2>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4 auto-rows-[150px] md:auto-rows-[200px]">
                    <div v-for="(img, index) in galleryImages" :key="index"
                        class="relative rounded-2xl overflow-hidden group cursor-pointer shadow-lg"
                        :class="img.span"
                    >
                        <img :src="img.url" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" alt="Galerie KALETA" />
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent opacity-80 group-hover:opacity-90 transition-opacity flex items-end p-4">
                            <span class="text-white font-bold text-sm opacity-0 group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0 transition-all duration-300">
                                {{ img.title }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>
            <!-- ═══════════════════════════════════════ -->
            <!-- SECTION NOUS TROUVER & GOOGLE MAPS     -->
            <!-- ═══════════════════════════════════════ -->
            <section id="contact" class="max-w-6xl mx-auto px-6 py-20 scroll-mt-24 reveal relative">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

                    <!-- Colonne de gauche : Informations textuelles -->
                    <div class="space-y-8 text-center lg:text-left">
                        <div>
                            <div class="flex items-center justify-center lg:justify-start gap-2 mb-3">
                                <span class="w-8 h-0.5 bg-gradient-to-r from-amber-500 to-transparent rounded-full"></span>
                                <span class="text-[10px] uppercase tracking-[0.25em] font-bold text-amber-500">Nous Trouver</span>
                            </div>
                            <h2 class="text-4xl md:text-5xl font-black italic font-serif leading-tight text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-orange-500">
                                Venez vivre l'expérience
                            </h2>
                        </div>

                        <!-- Détails de contact et d'accès -->
                        <div class="space-y-6">
                            <!-- Adresse -->
                            <div class="space-y-1">
                                <h3 class="text-xs uppercase tracking-widest font-bold text-emerald-500">Adresse</h3>
                                <p class="text-base font-light leading-relaxed" :class="isDarkMode ? 'text-stone-300' : 'text-stone-700'">
                                    Face au lycée d'Agoë, à côté de l'OTR<br>Lomé, Togo
                                </p>
                            </div>

                            <!-- Horaires officiels (Mis à jour d'après l'affiche) -->
                            <div class="space-y-1">
                                <h3 class="text-xs uppercase tracking-widest font-bold text-emerald-500">Horaires</h3>
                                <div class="text-base font-light space-y-1" :class="isDarkMode ? 'text-stone-300' : 'text-stone-700'">
                                    <p class="flex items-center justify-center md:justify-start gap-2">
                                        <span>Lun. — Jeu. :</span> <span class="font-bold">11h00 — 23h00</span>
                                    </p>
                                    <p class="flex items-center justify-center md:justify-start gap-2">
                                        <span>Ven. — Dim. :</span> <span class="font-bold">16h00 — 02h00</span>
                                    </p>
                                </div>
                            </div>

                            <!-- Contact -->
                            <div class="space-y-1">
                                <h3 class="text-xs uppercase tracking-widest font-bold text-emerald-500">Contact</h3>
                                <div class="text-base font-light space-y-1" :class="isDarkMode ? 'text-stone-300' : 'text-stone-700'">
                                    <p class="font-mono font-bold">+228 91 00 84 84</p>
                                    <p>restaurantkaleta@gmail.com</p>
                                </div>
                            </div>
                        </div>

                        <!-- Bouton Itinéraire Google Maps -->
                        <div class="pt-4 flex justify-center lg:justify-start">
                            <a
                                href="https://maps.app.goo.gl/B6bsfPsFmoDmrRKVA?g_st=ic"
                                target="_blank"
                                class="inline-flex items-center gap-2.5 px-8 py-4 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-stone-950 font-bold rounded-xl shadow-lg hover:shadow-amber-500/25 transition-all text-xs uppercase tracking-wider transform hover:scale-105 active:scale-95"
                            >
                                <span>📍</span> Itinéraire Google Maps
                            </a>
                        </div>
                    </div>

                    <!-- Colonne de droite : Carte Google Maps interactive intégrée -->
                    <div
                        class="relative w-full h-[380px] sm:h-[450px] rounded-3xl overflow-hidden shadow-2xl border transition-colors duration-300 border-emerald-100 bg-white"

                    >
                        <iframe
                            src="https://maps.google.com/maps?q=RESTAURANT%20KALETA%20Lome%20Togo&t=&z=15&ie=UTF8&iwloc=&output=embed"
                            class="w-full h-full border-0 opacity-90 transition-all duration-500"

                            allowfullscreen="true"
                            loading="lazy"
                        ></iframe>
                    </div>

                </div>
            </section>

            <!-- PIED DE PAGE -->
            <footer class="scroll-mt-24 border-t py-20 mt-16 relative z-10 transition-colors duration-500"
                :class="isDarkMode ? 'bg-gradient-to-b from-stone-900/30 to-black border-stone-900/60' : 'bg-gradient-to-b from-emerald-50/50 to-white border-emerald-200/60'"
            >
                <div class="max-w-6xl mx-auto px-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-10 mb-14">
                        <div class="space-y-4">
                            <h3 class="text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-amber-500 to-orange-500 uppercase tracking-widest font-serif">KALETA</h3>
                            <p class="text-sm italic font-light leading-relaxed" :class="isDarkMode ? 'text-stone-400' : 'text-stone-600'">Plus qu'un restaurant, une destination de convivialité et de saveurs.</p>
                        </div>
                        <div class="space-y-4">
                            <h4 class="font-bold uppercase tracking-widest text-xs border-b pb-2" :class="isDarkMode ? 'text-stone-200 border-stone-800' : 'text-stone-800 border-stone-200'">Navigation</h4>
                            <nav class="space-y-2.5 text-sm" :class="isDarkMode ? 'text-stone-400' : 'text-stone-600'">
                                <button @click="scrollTo('accueil')" class="hover:text-amber-500 transition-colors block">Accueil</button>
                                <button @click="scrollTo('quotidien')" class="hover:text-amber-500 transition-colors block">Menu du Jour</button>
                                <button @click="scrollTo('menu')" class="hover:text-amber-500 transition-colors block">Notre Carte</button>
                                <button @click="scrollTo('galerie')" class="hover:text-amber-500 transition-colors block">Galerie</button>
                            </nav>
                        </div>
                        <div class="space-y-4">
                            <h4 class="font-bold uppercase tracking-widest text-xs border-b pb-2" :class="isDarkMode ? 'text-stone-200 border-stone-800' : 'text-stone-800 border-stone-200'">Informations</h4>
                            <div class="space-y-3 text-sm" :class="isDarkMode ? 'text-stone-400' : 'text-stone-600'">
                                <p class="flex items-center gap-2.5"><span>⏰</span> <strong class="font-semibold" :class="isDarkMode ? 'text-stone-200' : 'text-stone-800'">16h – 22h</strong> tous les jours</p>
                                <p class="flex items-center gap-2.5"><span>📞</span> <a href="tel:+22891008484" class="hover:text-amber-500 transition-colors font-mono">+228 91 00 84 84</a></p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <h4 class="font-bold uppercase tracking-widest text-xs border-b pb-2" :class="isDarkMode ? 'text-stone-200 border-stone-800' : 'text-stone-800 border-stone-200'">Localisation</h4>
                            <div class="flex items-start gap-2.5 text-sm" :class="isDarkMode ? 'text-stone-400' : 'text-stone-600'">
                                <span class="shrink-0 mt-0.5">📍</span>
                                <span>Deux Lions<br>Agoë, Lomé<br>Togo 🇹🇬</span>
                            </div>
                        </div>
                    </div>

                    <div class="border-t mb-8" :class="isDarkMode ? 'border-stone-800/40' : 'border-stone-200/60'"></div>

                    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                        <p class="text-xs" :class="isDarkMode ? 'text-stone-600' : 'text-stone-400'">© 2026 KALETA Terrasse & Lounge. Tous droits réservés.</p>
                        <div class="flex gap-3">

                            <!-- WhatsApp -->
                            <a
                                href="https://wa.me/22891008484"
                                target="_blank"
                                title="Nous contacter sur WhatsApp"
                                class="w-10 h-10 rounded-xl flex items-center justify-center transition-all duration-300 hover:scale-110 shadow-sm bg-[#25D366] text-white hover:bg-[#20ba5a]"
                            >
                                <i class="fa-brands fa-whatsapp text-xl"></i>
                            </a>

                            <!-- TikTok -->
                            <a
                                href="https://www.tiktok.com/@restaurantkaleta?_r=1&_t=ZS-97P3xQCfYP6"
                                target="_blank"
                                title="Nous suivre sur TikTok"
                                class="w-10 h-10 rounded-xl flex items-center justify-center transition-all duration-300 hover:scale-110 shadow-sm bg-black text-white hover:bg-neutral-900"
                            >
                                <i class="fa-brands fa-tiktok text-lg"></i>
                            </a>

                        </div>
                    </div>
                </div>
            </footer>
        </div>

        <!-- MODAL PANIER -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="isCartOpen" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-md flex items-center justify-center p-4" @click.self="isCartOpen = false">
                    <Transition name="scale">
                        <div :class="['border max-w-lg w-full rounded-3xl p-8 relative max-h-[90vh] overflow-y-auto transition-all duration-500', isDarkMode ? 'bg-gradient-to-br from-stone-900 to-stone-950 border-stone-800 text-stone-100' : 'bg-white border-stone-200 text-stone-900 shadow-2xl']">
                            <button @click="isCartOpen = false" class="absolute top-5 right-5 w-8 h-8 rounded-full flex items-center justify-center text-stone-400 hover:text-amber-500 hover:bg-amber-500/10 transition-all duration-200 text-lg">✕</button>
                            <h3 class="text-2xl font-black text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-cyan-400 mb-8 flex items-center gap-2"><span>🛒</span> Votre Panier</h3>

                            <div v-if="cart.length > 0" class="space-y-6">
                                <div class="space-y-3">
                                    <div v-for="item in cart" :key="item.id" class="flex items-center gap-4 p-3 rounded-xl" :class="isDarkMode ? 'bg-stone-800/40' : 'bg-stone-50'">
                                        <div class="flex-1 min-w-0">
                                            <h4 class="font-bold text-sm truncate" :class="isDarkMode ? 'text-stone-100' : 'text-stone-900'">{{ item.name }}</h4>
                                            <p class="text-xs text-stone-500 mt-0.5">{{ Number(item.price).toLocaleString('fr-FR') }} F / unité</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button @click="updateQuantity(item.id, -1)" class="w-7 h-7 rounded-lg flex items-center justify-center text-sm font-bold" :class="isDarkMode ? 'bg-stone-700 hover:bg-stone-600 text-stone-300' : 'bg-stone-200 hover:bg-stone-300 text-stone-700'">−</button>
                                            <span class="w-6 text-center text-sm font-bold">{{ item.quantity }}</span>
                                            <button @click="updateQuantity(item.id, 1)" class="w-7 h-7 rounded-lg flex items-center justify-center text-sm font-bold" :class="isDarkMode ? 'bg-emerald-600/30 hover:bg-emerald-600/50 text-emerald-400' : 'bg-emerald-100 hover:bg-emerald-200 text-emerald-700'">+</button>
                                        </div>
                                        <span class="text-sm font-black text-amber-500 min-w-[70px] text-right">{{ (item.price * item.quantity).toLocaleString('fr-FR') }} F</span>
                                        <button @click="removeFromCart(item.id)" class="w-7 h-7 rounded-lg flex items-center justify-center text-xs bg-red-500/10 hover:bg-red-500/25 text-red-500">✕</button>
                                    </div>
                                </div>

                                <div class="flex justify-between items-center py-4 border-t" :class="isDarkMode ? 'border-stone-800' : 'border-stone-200'">
                                    <span class="text-stone-400 font-bold">Total :</span>
                                    <span class="text-2xl font-black text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-orange-500">{{ cartTotal.toLocaleString('fr-FR') }} F</span>
                                </div>

                                <form @submit.prevent="submitOrder" class="space-y-4 pt-4 border-t" :class="isDarkMode ? 'border-stone-800' : 'border-stone-200'">
                                    <h4 class="font-bold text-xs uppercase tracking-wider text-stone-400 flex items-center gap-2">📍 Informations de Livraison</h4>
                                    <input v-model="orderForm.customer_name" type="text" required placeholder="Nom complet" class="w-full rounded-xl p-3 text-sm focus:outline-none transition-all border" :class="isDarkMode ? 'bg-stone-950 border-stone-800 text-stone-100 focus:border-amber-500/50' : 'bg-stone-50 border-stone-300 text-stone-900 focus:border-amber-500'" />
                                    <input v-model="orderForm.customer_phone" type="tel" required placeholder="Téléphone" class="w-full rounded-xl p-3 text-sm focus:outline-none transition-all border" :class="isDarkMode ? 'bg-stone-950 border-stone-800 text-stone-100 focus:border-amber-500/50' : 'bg-stone-50 border-stone-300 text-stone-900 focus:border-amber-500'" />
                                    <input v-model="orderForm.delivery_address" type="text" required placeholder="Adresse de Livraison" class="w-full rounded-xl p-3 text-sm focus:outline-none transition-all border" :class="isDarkMode ? 'bg-stone-950 border-stone-800 text-stone-100 focus:border-amber-500/50' : 'bg-stone-50 border-stone-300 text-stone-900 focus:border-amber-500'" />

                                    <button type="submit" :disabled="orderForm.processing" class="w-full py-3.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-400 hover:to-emerald-500 disabled:opacity-50 text-white font-bold rounded-xl transition-all duration-300 mt-4 text-sm flex items-center justify-center gap-2">
                                        {{ orderForm.processing ? '⏳ Traitement...' : '🛵 Confirmer la Commande' }}
                                    </button>
                                </form>
                            </div>

                            <div v-else class="text-center py-14">
                                <span class="text-5xl mb-4 block opacity-30">🛒</span>
                                <p class="text-stone-500 italic text-lg">Votre panier est vide.</p>
                                <button @click="isCartOpen = false" class="mt-6 px-6 py-2.5 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 rounded-xl transition-all duration-200 text-sm font-bold">Continuer</button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>

        <!-- MODAL RÉSERVATION -->
        <Teleport to="body">
            <Transition name="fade">
                <div v-if="isReservationOpen" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-md flex items-center justify-center p-4" @click.self="isReservationOpen = false">
                    <Transition name="scale">
                        <div :class="['border max-w-md w-full rounded-3xl p-8 relative transition-all duration-500', isDarkMode ? 'bg-gradient-to-br from-stone-900 to-stone-950 border-stone-800 text-stone-100' : 'bg-white border-stone-200 text-stone-900 shadow-2xl']">
                            <button @click="isReservationOpen = false" class="absolute top-5 right-5 w-8 h-8 rounded-full flex items-center justify-center text-stone-400 hover:text-amber-500 hover:bg-amber-500/10 transition-all duration-200 text-lg">✕</button>

                            <div class="text-center mb-8">
                                <span class="text-4xl mb-3 block">🥂</span>
                                <h3 class="text-2xl font-black text-transparent bg-clip-text bg-gradient-to-r from-amber-400 to-orange-500">Réserver une Table</h3>
                                <p class="text-xs text-stone-500 mt-2">Réservez votre moment d'exception</p>
                            </div>

                            <form @submit.prevent="submitReservation" class="space-y-4">
                                <input v-model="reservationForm.name" type="text" required placeholder="Nom complet" class="w-full rounded-xl p-3 text-sm focus:outline-none transition-all border" :class="isDarkMode ? 'bg-stone-950 border-stone-800 text-stone-100 focus:border-amber-500/50' : 'bg-stone-50 border-stone-300 text-stone-900 focus:border-amber-500'" />
                                <input v-model="reservationForm.phone" type="tel" required placeholder="Téléphone" class="w-full rounded-xl p-3 text-sm focus:outline-none transition-all border" :class="isDarkMode ? 'bg-stone-950 border-stone-800 text-stone-100 focus:border-amber-500/50' : 'bg-stone-50 border-stone-300 text-stone-900 focus:border-amber-500'" />

                                <div class="flex items-center gap-3">
                                    <button type="button" @click="reservationForm.guests = Math.max(1, reservationForm.guests - 1)" class="w-10 h-10 rounded-xl flex items-center justify-center text-lg font-bold shrink-0" :class="isDarkMode ? 'bg-stone-800 hover:bg-stone-700 text-stone-300' : 'bg-stone-200 hover:bg-stone-300 text-stone-700'">−</button>
                                    <div class="flex-1 text-center">
                                        <span class="text-2xl font-black">{{ reservationForm.guests }}</span>
                                        <span class="text-xs text-stone-500 ml-1">personne(s)</span>
                                    </div>
                                    <button type="button" @click="reservationForm.guests = Math.min(20, reservationForm.guests + 1)" class="w-10 h-10 rounded-xl flex items-center justify-center text-lg font-bold shrink-0" :class="isDarkMode ? 'bg-emerald-600/30 hover:bg-emerald-600/50 text-emerald-400' : 'bg-emerald-100 hover:bg-emerald-200 text-emerald-700'">+</button>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <input v-model="reservationForm.date" type="date" required class="w-full rounded-xl p-3 text-sm focus:outline-none transition-all border" :class="isDarkMode ? 'bg-stone-950 border-stone-800 text-stone-100 [color-scheme:dark]' : 'bg-stone-50 border-stone-300 text-stone-900'" />
                                    <input v-model="reservationForm.time" type="time" required class="w-full rounded-xl p-3 text-sm focus:outline-none transition-all border" :class="isDarkMode ? 'bg-stone-950 border-stone-800 text-stone-100 [color-scheme:dark]' : 'bg-stone-50 border-stone-300 text-stone-900'" />
                                </div>

                                <button type="submit" :disabled="reservationForm.processing" class="w-full py-3.5 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 disabled:opacity-50 text-stone-950 font-bold rounded-xl transition-all duration-300 mt-4 text-sm flex items-center justify-center gap-2">
                                    {{ reservationForm.processing ? '⏳ Enregistrement...' : '🥂 Confirmer' }}
                                </button>
                            </form>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style>
html { scroll-behavior: smooth; }

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-14px); }
}
.animate-float { animation: float 5s ease-in-out infinite; }

/* Transitions modales */
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.scale-enter-active, .scale-leave-active { transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1); }
.scale-enter-from, .scale-leave-to { opacity: 0; transform: scale(0.92) translateY(10px); }

.bounce-enter-active { transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); }
.bounce-leave-active { transition: all 0.2s ease-in; }
.bounce-enter-from { opacity: 0; transform: scale(0.5) translateY(20px); }
.bounce-leave-to { opacity: 0; transform: scale(0.8) translateY(10px); }

/* Scrollbar */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(16, 185, 129, 0.3); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: rgba(16, 185, 129, 0.5); }

/* Reveal effect on scroll */
.reveal {
    opacity: 0;
    transform: translateY(40px);
    transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}
.reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
}

/* Line clamp */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
