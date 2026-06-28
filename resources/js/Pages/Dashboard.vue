<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';

// 1. Réception des données typées depuis Laravel
const props = defineProps<{
    reservations: Array<{
        id: number;
        name: string;
        phone: string;
        guests: number;
        date: string;
        time: string;
        status: 'en_attente' | 'confirme' | 'annule';
    }>;
    categories: Array<{ id: number; name: string }>; // Réception dynamique des catégories [6]
    dishes: Array<{
        id: number;
        name: string;
        price: number | string;
        category: string;
        description?: string;
        is_available: boolean;
    }>;
    weeklySchedule: Array<any>;
    orders: Array<{
        id: number;
        customer_name: string;
        customer_phone: string;
        delivery_address: string;
        total_amount: number | string;
        status: 'en_attente' | 'en_preparation' | 'en_livraison' | 'livre' | 'annule';
        created_at: string;
        items: Array<{
            id: number;
            quantity: number;
            price: number;
            dish: { name: string; };
        }>;
    }>;
    todaySales: number;
    reportFilters: { start_date: string; end_date: string; };
    reportOrders: Array<any>;
    reportTotal: number;
}>();

const isDarkMode = ref(true);
const isSidebarOpen = ref(true);
const isMobileMenuOpen = ref(false);
const activeTab = ref('overview');
const daysOfWeek = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

const orderSearch = ref('');
const orderFilter = ref('all');

const filteredOrders = computed(() => {
    return props.orders.filter(order => {
        const matchesSearch = order.customer_name.toLowerCase().includes(orderSearch.value.toLowerCase()) ||
                              order.customer_phone.includes(orderSearch.value) ||
                              order.id.toString() === orderSearch.value;
        const isResto = order.delivery_address.startsWith('Sur place');
        const matchesType = orderFilter.value === 'all' ||
                            (orderFilter.value === 'online' && !isResto) ||
                            (orderFilter.value === 'resto' && isResto);
        return matchesSearch && matchesType;
    });
});

const ordersPage = ref(1);
const ordersPerPage = 10;
const paginatedOrders = computed(() => {
    const start = (ordersPage.value - 1) * ordersPerPage;
    return filteredOrders.value.slice(start, start + ordersPerPage);
});
const totalOrdersPages = computed(() => Math.ceil(filteredOrders.value.length / ordersPerPage));

// Impression ticket — déclenchée au clic uniquement
const printReceipt = (order: any) => {
    const printWindow = window.open('', '_blank', 'width=400,height=700');
    if (!printWindow) { alert('Veuillez autoriser les popups pour imprimer.'); return; }

    const itemsHtml = order.items.map((item: any) =>
        '<tr style="border-bottom:1px dashed #ccc;"><td style="padding:6px 0;font-size:12px;">' + item.quantity + ' x ' + item.dish.name + '</td><td style="padding:6px 0;text-align:right;font-size:12px;font-weight:bold;">' + Number(item.price * item.quantity).toLocaleString('fr-FR') + ' F</td></tr>'
    ).join('');

    const html = '<!DOCTYPE html><html><head><title>Ticket #' + order.id + '</title><style>*{margin:0;padding:0;box-sizing:border-box;}body{font-family:"Courier New",monospace;font-size:12px;color:#000;padding:15px;width:280px;margin:0 auto;}h2{text-align:center;font-size:18px;margin-bottom:2px;}p{text-align:center;font-size:10px;margin-bottom:2px;}.info{text-align:left;margin:8px 0;}.info p{font-size:11px;margin:3px 0;text-align:left;}.sep{border:none;border-top:1px dashed #000;margin:8px 0;}table{width:100%;border-collapse:collapse;}th{text-align:left;padding:4px 0;border-bottom:1px solid #000;font-size:11px;}td{padding:4px 0;}.total{font-size:16px;font-weight:bold;display:flex;justify-content:space-between;margin-top:8px;padding-top:8px;border-top:2px solid #000;}.footer{text-align:center;font-size:9px;margin-top:12px;opacity:0.7;}@media print{body{margin:0;padding:10px;}}</style></head><body><h2>KALETA</h2><p>Terrasse &amp; Lounge</p><p style="font-size:9px;">Agoë Deux Lions, Lomé — Tél: +228 91008484</p><hr class="sep"><div class="info"><p><strong>Facture N°:</strong> #' + order.id + '</p><p><strong>Date:</strong> ' + new Date(order.created_at).toLocaleString('fr-FR') + '</p><p><strong>Client:</strong> ' + order.customer_name + '</p><p><strong>Type:</strong> ' + order.delivery_address + '</p></div><hr class="sep"><table><thead><tr><th>Article</th><th style="text-align:right;">Montant</th></tr></thead><tbody>' + itemsHtml + '</tbody></table><div class="total"><span>TOTAL:</span><span>' + Number(order.total_amount).toLocaleString('fr-FR') + ' F CFA</span></div><hr class="sep"><p class="footer">Merci pour votre confiance ! 🥂<br>KALETA — Agoë Deux Lions</p></body></html>';

    printWindow.document.write(html);
    printWindow.document.close();

    printWindow.onload = function () {
        setTimeout(function () { printWindow.print(); }, 250);
    };
};

const localCart = ref<Array<{ dish_id: number; name: string; price: number; quantity: number }>>([]);
const selectedDishId = ref('');
const selectedQuantity = ref(1);
const tableNumber = ref('Table 1');

const addDishToLocalCart = () => {
    if (!selectedDishId.value) return;
    const dish = props.dishes.find(d => d.id === Number(selectedDishId.value));
    if (!dish) return;
    const existing = localCart.value.find(item => item.dish_id === dish.id);
    if (existing) { existing.quantity += Number(selectedQuantity.value); }
    else { localCart.value.push({ dish_id: dish.id, name: dish.name, price: Number(dish.price), quantity: Number(selectedQuantity.value) }); }
    selectedDishId.value = '';
    selectedQuantity.value = 1;
};

const removeLocalItem = (index: number) => { localCart.value.splice(index, 1); };
const localCartTotal = computed(() => { return localCart.value.reduce((tot, item) => tot + (item.price * item.quantity), 0); });

const inRestoForm = useForm({ table_number: '', items: [] as Array<any> });
const submitInRestaurantOrder = () => {
    if (localCart.value.length === 0) return;
    inRestoForm.table_number = tableNumber.value;
    inRestoForm.items = localCart.value;
    inRestoForm.post('/in-restaurant-orders', {
        onSuccess: () => { localCart.value = []; tableNumber.value = 'Table 1'; alert('Vente sur place enregistrée ! ✅'); }
    });
};

const filterForm = ref({ start_date: props.reportFilters.start_date, end_date: props.reportFilters.end_date });
const applyFilters = () => {
    router.get('/dashboard', filterForm.value, { preserveState: true, preserveScroll: true, onSuccess: () => { activeTab.value = 'reports'; } });
};

const printReport = () => {
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    if (!printWindow) return;
    const rowsHtml = props.reportOrders.map((order: any) =>
        '<tr><td style="padding:8px;">#' + order.id + '</td><td style="padding:8px;">' + new Date(order.created_at).toLocaleDateString('fr-FR') + '</td><td style="padding:8px;">' + order.customer_name + ' (' + order.delivery_address + ')</td><td style="padding:8px;font-weight:bold;text-align:right;">' + Number(order.total_amount).toLocaleString('fr-FR') + ' F</td></tr>'
    ).join('');
    const rHtml = '<!DOCTYPE html><html><head><title>Rapport KALETA</title><style>body{font-family:sans-serif;padding:30px;}h2,h3{text-align:center;}table{width:100%;border-collapse:collapse;margin-top:20px;}th,td{border:1px solid #ddd;padding:10px;text-align:left;}th{background:#f5f5f5;}</style></head><body><h2>KALETA — RAPPORT DES VENTES</h2><p style="text-align:center;">Du ' + new Date(props.reportFilters.start_date).toLocaleDateString('fr-FR') + ' au ' + new Date(props.reportFilters.end_date).toLocaleDateString('fr-FR') + '</p><table><thead><tr><th>ID</th><th>Date</th><th>Détails</th><th style="text-align:right;">Montant</th></tr></thead><tbody>' + rowsHtml + '</tbody></table><h3 style="text-align:right;margin-top:20px;">TOTAL : ' + props.reportTotal.toLocaleString('fr-FR') + ' F CFA</h3></body></html>';
    printWindow.document.write(rHtml);
    printWindow.document.close();
    printWindow.onload = function () { setTimeout(function () { printWindow.print(); }, 250); };
};

const statusForm = useForm({ status: '' });
const updateStatus = (id: number, newStatus: 'confirme' | 'annule') => { statusForm.status = newStatus; statusForm.patch('/reservations/' + id + '/status', { preserveScroll: true }); };

const orderStatusForm = useForm({ status: '' });
const updateOrderStatus = (id: number, newStatus: string) => { orderStatusForm.status = newStatus; orderStatusForm.patch('/orders/' + id + '/status', { preserveScroll: true }); };

// Création d'une catégorie dynamique [6]
const categoryForm = useForm({
    name: ''
});
const submitCategory = () => {
    categoryForm.post('/categories', {
        onSuccess: () => {
            categoryForm.reset();
            alert('Nouvelle catégorie créée avec succès ! ✅');
        }
    });
};

const dishForm = useForm({ name: '', price: '', category: '', description: '', image: null as File | null });
const handleImageUpload = (event: Event) => { const t = event.target as HTMLInputElement; if (t.files && t.files[0]) { dishForm.image = t.files[0]; } };
const submitDish = () => { dishForm.post('/dishes', { onSuccess: () => { dishForm.reset(); alert('Plat ajouté !'); } }); };

const scheduleForm = useForm({ dish_id: '', day_of_week: 'lundi' });
const submitSchedule = () => { scheduleForm.post('/daily-menus', { onSuccess: () => { scheduleForm.reset('dish_id'); alert('Plat planifié !'); } }); };

const removeScheduledDish = (id: number) => { if (confirm('Retirer ce plat du planning ?')) { const f = useForm({}); f.delete('/daily-menus/' + id, { preserveScroll: true }); } };
const toggleForm = useForm({});
const toggleAvailability = (id: number) => { toggleForm.patch('/dishes/' + id + '/toggle', { preserveScroll: true }); };
const getScheduledForDay = (day: string) => { return props.weeklySchedule.filter(item => item.day_of_week === day); };
const logout = () => { router.post('/logout'); };

const getWhatsAppLink = (order: any) => {
    let phone = order.customer_phone.replace(/\D/g, '');
    if (phone.startsWith('00228')) phone = phone.substring(2);
    if (phone.length === 8) phone = '228' + phone;
    const itemsText = order.items.map((item: any) => '*' + item.quantity + 'x ' + item.dish.name + '*').join('%0A');
    return 'https://wa.me/' + phone + '?text=Bonjour *' + order.customer_name + '*,%0A%0AIci KALETA 🛵.%0ACommande *#' + order.id + '* :%0A' + itemsText + '%0A%0ATotal : *' + Number(order.total_amount).toLocaleString('fr-FR') + ' F CFA*.%0A%0AEnvoyez votre *localisation GPS* ?';
};

const playChimeSound = () => {
    try {
        const ctx = new (window.AudioContext || (window as any).webkitAudioContext)();
        const tone = (freq: number, start: number, dur: number) => { const o = ctx.createOscillator(); const g = ctx.createGain(); o.type = 'sine'; o.frequency.setValueAtTime(freq, start); g.gain.setValueAtTime(0.15, start); g.gain.exponentialRampToValueAtTime(0.0001, start + dur); o.connect(g); g.connect(ctx.destination); o.start(start); o.stop(start + dur); };
        tone(523.25, ctx.currentTime, 0.4);
        tone(659.25, ctx.currentTime + 0.1, 0.5);
    } catch (e) { /* silent */ }
};

const pendingOrdersCount = ref(props.orders.filter(o => o.status === 'en_attente').length);
const pendingReservationsCount = ref(props.reservations.filter(r => r.status === 'en_attente').length);
let reloadInterval: number;

onMounted(() => {
    reloadInterval = window.setInterval(() => {
        router.reload({
            only: ['orders', 'reservations', 'todaySales'],
            onSuccess: (page) => {
                const pd = page.props as any;
                const nOC = pd.orders.filter((o: any) => o.status === 'en_attente').length;
                const nRC = pd.reservations.filter((r: any) => r.status === 'en_attente').length;
                if (nOC > pendingOrdersCount.value || nRC > pendingReservationsCount.value) { playChimeSound(); }
                pendingOrdersCount.value = nOC;
                pendingReservationsCount.value = nRC;
            }
        });
    }, 12000);
});

onUnmounted(() => { clearInterval(reloadInterval); });

// Nav items
const navItems = computed(() => [
    { id: 'overview', label: 'Vue d\'ensemble', icon: '📊', badge: null },
    { id: 'orders', label: 'Commandes', icon: '🛵', badge: props.orders.filter(o => o.status === 'en_attente').length },
    { id: 'menu', label: 'Menu & Planning', icon: '📅', badge: null },
    { id: 'reservations', label: 'Réservations', icon: '🥂', badge: props.reservations.filter(r => r.status === 'en_attente').length },
    { id: 'reports', label: 'Rapports', icon: '📈', badge: null },
]);

const orderStatusConfig: Record<string, { label: string; color: string; bg: string }> = {
    en_attente: { label: 'En attente', color: 'text-amber-600', bg: 'bg-amber-500/15 border-amber-500/30' },
    en_preparation: { label: 'En cuisine', color: 'text-blue-600', bg: 'bg-blue-500/15 border-blue-500/30' },
    en_livraison: { label: 'En route', color: 'text-indigo-600', bg: 'bg-indigo-500/15 border-indigo-500/30' },
    livre: { label: 'Livré', color: 'text-emerald-600', bg: 'bg-emerald-500/15 border-emerald-500/30' },
    annule: { label: 'Annulé', color: 'text-red-600', bg: 'bg-red-500/15 border-red-500/30' },
};

const resStatusConfig: Record<string, { label: string; color: string; bg: string }> = {
    en_attente: { label: 'En attente', color: 'text-amber-600', bg: 'bg-amber-500/15 border-amber-500/30' },
    confirme: { label: 'Confirmé', color: 'text-emerald-600', bg: 'bg-emerald-500/15 border-emerald-500/30' },
    annule: { label: 'Annulé', color: 'text-red-600', bg: 'bg-red-500/15 border-red-500/30' },
};

// Classes de carte adaptées au thème — bordures TOUJOURS visibles
const card = computed(() => {
    return isDarkMode.value
        ? 'bg-slate-900/80 border-slate-700/60 shadow-lg shadow-black/20'
        : 'bg-white border-slate-300 text-slate-900 shadow-md shadow-slate-200/50'; // Modifié border-slate-300/80 en border-slate-300 pour le contraste
});

const cardInner = computed(() => {
    return isDarkMode.value
        ? 'bg-slate-950/60 border-slate-700/50'
        : 'bg-slate-50/60 border-slate-200/80';
});

const borderColor = computed(() => isDarkMode.value ? 'border-slate-700/60' : 'border-slate-300'); // Modifié border-slate-300/80 en border-slate-300 pour le contraste
const borderColorLight = computed(() => isDarkMode.value ? 'border-slate-800/60' : 'border-slate-200'); // Modifié border-slate-200/80 en border-slate-200 pour le contraste
const inputClass = computed(() => isDarkMode.value ? 'bg-slate-950 border-slate-600 focus:border-emerald-500 text-slate-100' : 'bg-white border-slate-300 focus:border-emerald-500 text-slate-900');
const mutedText = computed(() => isDarkMode.value ? 'text-slate-400' : 'text-slate-500');
</script>

<template>
    <Head title="Dashboard Premium — KALETA" />

    <div
        :class="[
            isDarkMode ? 'bg-slate-950 text-slate-100' : 'bg-slate-100 text-slate-900', // Modifié bg-slate-50 en bg-slate-100 pour le contraste
            'min-h-screen font-sans antialiased selection:bg-emerald-500/30 selection:text-emerald-900 transition-colors duration-500'
        ]"
    >

        <!-- Barre de progression -->
        <div class="fixed top-0 left-0 h-[3px] bg-gradient-to-r from-amber-500 via-emerald-500 to-purple-500 z-50"></div>

        <!-- HEADER MOBILE -->
        <header
            :class="[
                isDarkMode ? 'bg-slate-900/90 border-slate-700/60' : 'bg-white/90 border-slate-300', // Modifié border-slate-300/80 en border-slate-300
                'md:hidden sticky top-[3px] z-40 backdrop-blur-xl border-b px-4 py-3 flex justify-between items-center'
            ]"
        >
            <div class="flex items-center gap-3">
                <button @click="isMobileMenuOpen = true" class="p-2 rounded-xl hover:bg-slate-200/20 transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center text-white font-bold text-sm shadow-lg shadow-emerald-500/20">K</div>
                <span class="font-bold text-lg tracking-tight">KALETA</span>
            </div>
            <button @click="isDarkMode = !isDarkMode" class="p-2 rounded-xl hover:bg-slate-200/20 transition-colors">
                <span class="text-lg">{{ isDarkMode ? '☀️' : '🌙' }}</span>
            </button>
        </header>

        <div class="flex">

            <!-- ═══════════ SIDEBAR RÉTRACTABLE ═══════════ -->
            <aside
                :class="[
                    isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full',
                    isSidebarOpen ? 'md:translate-x-0' : 'md:-translate-x-full',
                    isDarkMode ? 'bg-slate-900 border-slate-700/60' : 'bg-white border-slate-300', // Modifié border-slate-300/80 en border-slate-300
                    'fixed md:sticky top-0 left-0 h-screen z-50 transition-all duration-300 ease-in-out flex flex-col border-r'
                ]"
                :style="{ width: isSidebarOpen ? '280px' : '280px' }"
            >
                <!-- Logo sidebar -->
                <div class="p-5 border-b" :class="borderColorLight">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center text-white font-bold shadow-lg shadow-emerald-500/20">K</div>
                            <div>
                                <h1 class="font-bold text-xl tracking-tight">KALETA</h1>
                                <p class="text-[10px] uppercase tracking-widest font-semibold text-emerald-500">Administration</p>
                            </div>
                        </div>
                        <button @click="isMobileMenuOpen = false" class="md:hidden p-2 rounded-lg hover:bg-slate-200/10 transition-colors">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>

                <!-- Nav items -->
                <nav class="flex-1 overflow-y-auto p-3 space-y-1">
                    <button
                        v-for="item in navItems" :key="item.id"
                        @click="activeTab = item.id; isMobileMenuOpen = false;"
                        :class="[
                            activeTab === item.id
                                ? 'bg-gradient-to-r from-emerald-500 to-emerald-600 text-white shadow-lg shadow-emerald-500/25'
                                : isDarkMode ? 'text-slate-400 hover:text-slate-100 hover:bg-slate-800/70' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100',
                            'w-full flex items-center justify-between px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 group'
                        ]"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-lg group-hover:scale-110 transition-transform duration-200">{{ item.icon }}</span>
                            <span>{{ item.label }}</span>
                        </div>
                        <span v-if="item.badge && item.badge > 0" class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-amber-400 text-slate-900 shadow-sm">
                            {{ item.badge }}
                        </span>
                    </button>
                </nav>

                <!-- Footer sidebar -->
                <div class="p-3 border-t space-y-2" :class="borderColorLight">
                    <button @click="isDarkMode = !isDarkMode" :class="[isDarkMode ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-300', 'w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all border']">
                        <span class="text-base">{{ isDarkMode ? '☀️' : '🌙' }}</span>
                        <span>{{ isDarkMode ? 'Mode Clair' : 'Mode Sombre' }}</span>
                    </button>
                    <button @click="logout" class="w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-red-400 hover:bg-red-500/10 border border-transparent hover:border-red-500/20 transition-all">
                        <span>👤</span>
                        <span>Mon Profil</span>
                    </button>
                    <button @click="logout" class="w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-red-400 hover:bg-red-500/10 border border-transparent hover:border-red-500/20 transition-all">
                        <span>🚪</span>
                        <span>Déconnexion</span>
                    </button>
                </div>
            </aside>

            <!-- Overlay mobile -->
            <div v-if="isMobileMenuOpen" @click="isMobileMenuOpen = false" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 md:hidden"></div>

            <!-- ═══════════ CONTENU PRINCIPAL ═══════════ -->
            <main class="flex-1 min-w-0">

                <!-- Barre secondaire avec toggle sidebar + titre -->
                <div
                    :class="[
                        isDarkMode ? 'bg-slate-900/60 border-slate-700/40' : 'bg-white/80 border-slate-300/60',
                        'sticky top-[3px] z-30 backdrop-blur-xl border-b px-4 md:px-6 py-3 flex items-center justify-between gap-4'
                    ]"
                >
                    <div class="flex items-center gap-3">
                        <!-- Bouton toggle sidebar (visible desktop) -->
                        <button
                            @click="isSidebarOpen = !isSidebarOpen"
                            :class="[
                                isDarkMode ? 'hover:bg-slate-700 text-slate-400 hover:text-slate-100' : 'hover:bg-slate-200 text-slate-500 hover:text-slate-900',
                                'hidden md:flex w-9 h-9 rounded-xl items-center justify-center transition-all duration-200 border',
                                isDarkMode ? 'border-slate-700' : 'border-slate-300'
                            ]"
                            :title="isSidebarOpen ? 'Réduire le menu' : 'Ouvrir le menu'"
                        >
                            <svg class="w-4 h-4 transition-transform duration-300" :class="{ 'rotate-180': !isSidebarOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" /></svg>
                        </button>
                        <div>
                            <h2 class="text-base md:text-lg font-bold tracking-tight">
                                {{ activeTab === 'overview' ? 'Tableau de bord' : activeTab === 'orders' ? 'Commandes' : activeTab === 'menu' ? 'Menu & Planning' : activeTab === 'reservations' ? 'Réservations' : 'Rapports' }}
                            </h2>
                            <p class="text-[11px]" :class="mutedText">{{ new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div :class="[isDarkMode ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-emerald-50 border-emerald-300 text-emerald-700', 'px-3 py-1.5 rounded-lg border text-[11px] font-semibold flex items-center gap-1.5']">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            En ligne
                        </div>
                    </div>
                </div>

                <!-- Contenu scrollable -->
                <div class="p-4 md:p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">

                    <!-- ═══ OVERVIEW ═══ -->
                    <div v-if="activeTab === 'overview'" class="space-y-6">
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 p-5 text-white shadow-xl shadow-emerald-500/20 col-span-2 lg:col-span-1">
                                <div class="relative z-10">
                                    <p class="text-emerald-100 text-xs font-semibold uppercase tracking-wider mb-1">Recettes</p>
                                    <p class="text-2xl md:text-3xl font-black">{{ todaySales.toLocaleString('fr-FR') }} F</p>
                                    <div class="mt-3 flex items-center gap-1.5 text-[10px] font-bold text-emerald-100 bg-white/10 w-fit px-2 py-1 rounded-lg"><span>📈</span> Aujourd'hui</div>
                                </div>
                                <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-white/5 rounded-full"></div>
                            </div>
                            <div v-for="s in [
                                { icon: '⏳', label: 'En attente', val: orders.filter(o => o.status === 'en_attente').length, color: 'text-amber-500', bg: 'bg-amber-500/10' },
                                { icon: '🍳', label: 'En cuisine', val: orders.filter(o => o.status === 'en_preparation').length, color: 'text-blue-500', bg: 'bg-blue-500/10' },
                                { icon: '🥂', label: 'Réservations', val: reservations.filter(r => r.status === 'en_attente').length, color: 'text-violet-500', bg: 'bg-violet-500/10' }
                            ]" :key="s.label" :class="[card, 'rounded-2xl p-5 border transition-all duration-300 hover:-translate-y-0.5 hover:shadow-xl']">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-xs font-medium mb-1" :class="mutedText">{{ s.label }}</p>
                                        <p class="text-2xl md:text-3xl font-black" :class="s.color">{{ s.val }}</p>
                                    </div>
                                    <div :class="[s.bg, 'w-11 h-11 rounded-xl flex items-center justify-center text-lg']">{{ s.icon }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Caisse rapide -->
                        <div :class="[card, 'rounded-2xl border overflow-hidden']">
                            <div class="p-5 border-b flex items-center gap-3" :class="borderColorLight">
                                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 flex items-center justify-center text-white shadow-lg shadow-amber-500/20">⚡</div>
                                <div><h3 class="font-bold text-sm">Caisse Rapide</h3><p class="text-[11px]" :class="mutedText">Vente sur place</p></div>
                            </div>
                            <div class="p-5 space-y-5">
                                <div class="grid grid-cols-2 md:grid-cols-12 gap-3">
                                    <div class="col-span-2 md:col-span-2">
                                        <label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" :class="mutedText">Table</label>
                                        <input v-model="tableNumber" type="text" :class="[inputClass, 'w-full rounded-xl border px-3 py-2.5 text-sm outline-none transition-all']">
                                    </div>
                                    <div class="col-span-2 md:col-span-7">
                                        <label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" :class="mutedText">Plat</label>
                                        <select v-model="selectedDishId" :class="[inputClass, 'w-full rounded-xl border px-3 py-2.5 text-sm outline-none transition-all']">
                                            <option value="">Sélectionner...</option>
                                            <optgroup v-for="cat in [...new Set(dishes.map(d => d.category))]" :key="cat" :label="cat">
                                                <option v-for="dish in dishes.filter(d => d.category === cat)" :key="dish.id" :value="dish.id">{{ dish.name }} — {{ Number(dish.price).toLocaleString('fr-FR') }} F</option>
                                            </optgroup>
                                        </select>
                                    </div>
                                    <div class="col-span-1 md:col-span-2">
                                        <label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" :class="mutedText">Qté</label>
                                        <input v-model.number="selectedQuantity" type="number" min="1" :class="[inputClass, 'w-full rounded-xl border px-3 py-2.5 text-sm outline-none transition-all text-center font-bold']">
                                    </div>
                                    <div class="col-span-1 md:col-span-1 flex items-end">
                                        <button @click="addDishToLocalCart" :disabled="!selectedDishId" class="w-full py-3 bg-emerald-500 hover:bg-emerald-600 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl font-bold transition-all shadow-lg shadow-emerald-500/20 text-lg leading-none">+</button>
                                    </div>
                                </div>
                                <div v-if="localCart.length > 0" class="space-y-4">
                                    <div :class="[cardInner, 'rounded-xl border p-4 space-y-2']">
                                        <div v-for="(item, idx) in localCart" :key="idx" class="flex items-center justify-between py-2 border-b last:border-0" :class="borderColorLight">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <button @click="removeLocalItem(idx)" class="w-6 h-6 rounded-full bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white flex items-center justify-center text-xs transition-all shrink-0">×</button>
                                                <div class="min-w-0"><p class="font-semibold text-sm truncate">{{ item.name }}</p><p class="text-[11px]" :class="mutedText">{{ item.quantity }} × {{ item.price.toLocaleString('fr-FR') }} F</p></div>
                                            </div>
                                            <p class="font-bold text-emerald-500 text-sm shrink-0 ml-3">{{ (item.price * item.quantity).toLocaleString('fr-FR') }} F</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between pt-3">
                                        <span class="text-xs font-medium" :class="mutedText">{{ localCart.length }} article(s)</span>
                                        <div class="text-right"><p class="text-[10px] uppercase tracking-wider font-bold" :class="mutedText">Total</p><p class="text-2xl font-black text-emerald-500">{{ localCartTotal.toLocaleString('fr-FR') }} F</p></div>
                                    </div>
                                    <button @click="submitInRestaurantOrder" class="w-full py-3.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-400 hover:to-emerald-500 text-white font-bold rounded-xl shadow-lg shadow-emerald-500/25 transition-all hover:scale-[1.01] active:scale-[0.99]">✅ Valider la vente</button>
                                </div>
                                <div v-else class="text-center py-10 opacity-30"><span class="text-4xl block mb-2">🛒</span><p class="text-sm">Sélectionnez des plats</p></div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ COMMANDES ═══ -->
                    <div v-if="activeTab === 'orders'" class="space-y-5">
                        <div :class="[card, 'p-4 rounded-2xl border flex flex-col md:flex-row gap-3']">
                            <div class="relative flex-1">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm opacity-40">🔍</span>
                                <input v-model="orderSearch" type="text" placeholder="Nom, téléphone ou N°..." :class="[inputClass, 'w-full pl-10 pr-4 py-2.5 rounded-xl border text-sm outline-none transition-all']">
                            </div>
                            <div class="flex gap-2">
                                <button v-for="f in [{ val: 'all', label: 'Toutes', icon: '📋' }, { val: 'online', label: 'Livraison', icon: '🛵' }, { val: 'resto', label: 'Sur place', icon: '🍽️' }]" :key="f.val" @click="orderFilter = f.val" :class="['px-4 py-2.5 rounded-xl text-xs font-bold transition-all duration-200 border whitespace-nowrap', orderFilter === f.val ? 'bg-emerald-500 text-white border-emerald-500 shadow-lg shadow-emerald-500/20' : isDarkMode ? 'bg-slate-800 border-slate-700 text-slate-400 hover:border-emerald-500/30 hover:text-slate-200' : 'bg-white border-slate-300 text-slate-600 hover:border-emerald-400']">
                                    {{ f.icon }} {{ f.label }}
                                </button>
                            </div>
                        </div>
                        <div :class="[card, 'rounded-2xl border overflow-hidden']">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead :class="isDarkMode ? 'bg-slate-800/60' : 'bg-slate-100'">
                                        <tr>
                                            <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wider opacity-50">N°</th>
                                            <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wider opacity-50">Client</th>
                                            <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wider opacity-50">Détails</th>
                                            <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wider opacity-50">Total</th>
                                            <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wider opacity-50">Statut</th>
                                            <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-wider opacity-50 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y" :class="borderColorLight">
                                        <tr v-for="order in paginatedOrders" :key="order.id" class="transition-colors duration-150" :class="isDarkMode ? 'hover:bg-emerald-500/5' : 'hover:bg-emerald-50/40'">
                                            <td class="py-3.5 px-5">
                                                <span class="font-bold text-sm">#{{ order.id }}</span>
                                                <p class="text-[10px] mt-0.5 whitespace-nowrap" :class="mutedText">{{ new Date(order.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' }) }} à {{ new Date(order.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}</p>
                                            </td>
                                            <td class="px-5 py-3.5">
                                                <p class="font-semibold text-sm">{{ order.customer_name }}</p>
                                                <div class="flex items-center gap-2 mt-1 flex-wrap">
                                                    <span class="text-[11px] font-mono" :class="mutedText">{{ order.customer_phone }}</span>
                                                    <a v-if="order.customer_phone !== 'N/A'" :href="getWhatsAppLink(order)" target="_blank" class="text-[9px] bg-emerald-500 text-white px-2 py-0.5 rounded-full font-bold hover:bg-emerald-600 transition-colors inline-flex items-center gap-0.5">💬 WhatsApp</a>
                                                </div>
                                                <p class="text-[11px] text-amber-500 mt-1 font-medium">{{ order.delivery_address }}</p>
                                            </td>
                                            <td class="px-5 py-3.5">
                                                <div class="space-y-0.5">
                                                    <div v-for="item in order.items.slice(0, 2)" :key="item.id" class="text-xs" :class="mutedText">{{ item.quantity }}× {{ item.dish.name }}</div>
                                                    <div v-if="order.items.length > 2" class="text-[11px] italic opacity-50">+{{ order.items.length - 2 }} autre(s)</div>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3.5 font-bold text-base">{{ Number(order.total_amount).toLocaleString('fr-FR') }} F</td>
                                            <td class="px-5 py-3.5">
                                                <select :value="order.status" @change="updateOrderStatus(order.id, ($event.target as HTMLSelectElement).value)" :class="['px-2.5 py-1.5 rounded-lg text-[11px] font-bold border outline-none cursor-pointer transition-all', orderStatusConfig[order.status]?.bg, orderStatusConfig[order.status]?.color]">
                                                    <option value="en_attente">⏳ En attente</option>
                                                    <option value="en_preparation">🍳 En cuisine</option>
                                                    <option value="en_livraison">🛵 En route</option>
                                                    <option value="livre">Livré ✅</option>
                                                    <option value="annule">Annulé ❌</option>
                                                </select>
                                            </td>
                                            <td class="px-5 py-3.5 text-right">
                                                <button @click="printReceipt(order)" class="p-2 rounded-lg transition-all opacity-60 hover:opacity-100 text-sm border border-transparent hover:border-slate-400/30" title="Imprimer le ticket">🖨️</button>
                                            </td>
                                        </tr>
                                        <tr v-if="paginatedOrders.length === 0"><td colspan="6" class="text-center py-14 opacity-30"><span class="text-3xl block mb-2">📭</span><p class="text-sm">Aucune commande trouvée</p></td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-if="totalOrdersPages > 1" :class="['px-5 py-3.5 border-t flex justify-between items-center text-xs font-medium', borderColorLight, isDarkMode ? 'bg-slate-800/40 text-slate-400' : 'bg-slate-50 text-slate-500']">
                                <button @click="ordersPage--" :disabled="ordersPage === 1" class="px-4 py-2 rounded-lg bg-emerald-500 text-white disabled:opacity-20 transition-all">← Préc.</button>
                                <div class="flex items-center gap-1.5">
                                    <button v-for="p in totalOrdersPages" :key="p" @click="ordersPage = p" :class="['w-8 h-8 rounded-lg text-xs font-bold transition-all', ordersPage === p ? 'bg-emerald-500 text-white shadow-lg' : isDarkMode ? 'hover:bg-slate-700 text-slate-400' : 'hover:bg-slate-200 text-slate-500']">{{ p }}</button>
                                </div>
                                <button @click="ordersPage++" :disabled="ordersPage === totalOrdersPages" class="px-4 py-2 rounded-lg bg-emerald-500 text-white disabled:opacity-20 transition-all">Suiv. →</button>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ MENU & PLANNING ═══ -->
                    <div v-if="activeTab === 'menu'" class="space-y-6">
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <div class="lg:col-span-2 space-y-6">
                                <div :class="[card, 'rounded-2xl border overflow-hidden']">
                                    <div class="p-5 border-b flex items-center gap-3" :class="borderColorLight">
                                        <div class="w-9 h-9 rounded-xl bg-emerald-500/10 flex items-center justify-center text-base">📅</div>
                                        <h3 class="font-bold text-sm">Planning Hebdomadaire</h3>
                                    </div>
                                    <div class="divide-y max-h-[480px] overflow-y-auto" :class="borderColorLight">
                                        <div v-for="day in daysOfWeek" :key="day" class="p-4 transition-colors" :class="isDarkMode ? 'hover:bg-emerald-500/5' : 'hover:bg-emerald-50/60'">
                                            <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-500 mb-2.5">{{ day }}</h4>
                                            <div class="flex flex-wrap gap-2">
                                                <span v-for="item in getScheduledForDay(day)" :key="item.id" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 hover:border-red-500/30 hover:text-red-500 transition-all">
                                                    {{ item.dish.name }}
                                                    <button @click="removeScheduledDish(item.id)" class="hover:scale-125 transition-transform">×</button>
                                                </span>
                                                <span v-if="getScheduledForDay(day).length === 0" class="text-[11px] italic opacity-30">Aucun plat programmé</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div :class="[card, 'rounded-2xl border overflow-hidden']">
                                    <div class="p-5 border-b flex items-center gap-3" :class="borderColorLight">
                                        <div class="w-9 h-9 rounded-xl bg-blue-500/10 flex items-center justify-center text-base">📦</div>
                                        <h3 class="font-bold text-sm">Disponibilité des Plats</h3>
                                    </div>
                                    <div class="max-h-[300px] overflow-y-auto">
                                        <table class="w-full text-sm">
                                            <thead :class="isDarkMode ? 'bg-slate-800/60' : 'bg-slate-100'">
                                                <tr>
                                                    <th class="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-wider opacity-50">Plat</th>
                                                    <th class="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-wider opacity-50">Catégorie</th>
                                                    <th class="px-6 py-3 text-left text-[10px] font-bold uppercase tracking-wider opacity-50">Prix</th>
                                                    <th class="px-6 py-3 text-right text-[10px] font-bold uppercase tracking-wider opacity-50">Statut</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y" :class="borderColorLight">
                                                <tr v-for="dish in dishes" :key="dish.id" class="transition-colors" :class="isDarkMode ? 'hover:bg-emerald-500/5' : 'hover:bg-emerald-50/60'">
                                                    <td class="px-5 py-3 font-semibold text-sm">{{ dish.name }}</td>
                                                    <td class="px-5 py-3"><span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md border" :class="isDarkMode ? 'bg-slate-800 border-slate-700 text-slate-400' : 'bg-slate-100 border-slate-200 text-slate-500'">{{ dish.category }}</span></td>
                                                    <td class="px-5 py-3 text-sm font-bold text-amber-500">{{ Number(dish.price).toLocaleString('fr-FR') }} F</td>
                                                    <td class="px-5 py-3 text-right">
                                                        <button @click="toggleAvailability(dish.id)" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-300" :class="dish.is_available ? 'bg-emerald-500' : isDarkMode ? 'bg-slate-700' : 'bg-slate-300'">
                                                            <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform duration-300" :class="dish.is_available ? 'translate-x-6' : 'translate-x-1'"></span>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-6">
                                <!-- Formulaire : Créer une Catégorie -->
                                <div :class="[card, 'rounded-2xl border p-5']">
                                    <div class="flex items-center gap-2.5 mb-5">
                                        <div class="w-9 h-9 rounded-xl bg-emerald-500/10 flex items-center justify-center text-base">📁</div>
                                        <h3 class="font-bold text-sm">Créer une Catégorie</h3>
                                    </div>
                                    <form @submit.prevent="submitCategory" class="space-y-3.5">
                                        <div>
                                            <label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" :class="mutedText">Nom de la catégorie</label>
                                            <input v-model="categoryForm.name" required placeholder="Ex: Desserts, Grillades..." :class="[inputClass, 'w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none transition-all']">
                                        </div>
                                        <button type="submit" :disabled="categoryForm.processing" class="w-full py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-400 hover:to-emerald-500 disabled:opacity-50 text-white font-bold rounded-xl text-sm shadow-lg shadow-emerald-500/20 transition-all">
                                            {{ categoryForm.processing ? '⏳...' : '📁 Créer la Catégorie' }}
                                        </button>
                                    </form>
                                </div>
                                <div :class="[card, 'rounded-2xl border p-5']">
                                    <div class="flex items-center gap-2.5 mb-5"><div class="w-9 h-9 rounded-xl bg-amber-500/10 flex items-center justify-center text-base">🍳</div><h3 class="font-bold text-sm">Ajouter un plat</h3></div>
                                    <form @submit.prevent="submitDish" class="space-y-3.5">
                                        <div><label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" :class="mutedText">Nom</label><input v-model="dishForm.name" required :class="[inputClass, 'w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none transition-all']"></div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div><label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" :class="mutedText">Prix (F)</label><input v-model="dishForm.price" type="number" required :class="[inputClass, 'w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none transition-all']"></div>
                                            <div>
                                                <label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" :class="mutedText">Catégorie</label>
                                                <select v-model="dishForm.category" :class="[inputClass, 'w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none transition-all']">
                                                    <option value="">Sélectionner...</option>
                                                    <option v-for="cat in categories" :key="cat.id" :value="cat.name">
                                                        {{ cat.name }}
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                        <div><label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" :class="mutedText">Description</label><textarea v-model="dishForm.description" rows="2" :class="[inputClass, 'w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none transition-all resize-none']"></textarea></div>
                                        <div><label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" :class="mutedText">Image</label><input type="file" accept="image/*" @change="handleImageUpload" :class="[inputClass, 'w-full rounded-xl border text-sm outline-none transition-all file:cursor-pointer file:border-0 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:text-[10px] file:font-bold file:uppercase file:tracking-wider', isDarkMode ? 'file:bg-amber-500/10 file:text-amber-400' : 'file:bg-amber-50 file:text-amber-600']"></div>
                                        <div v-if="dishForm.errors && Object.keys(dishForm.errors).length > 0" class="p-2.5 rounded-xl bg-red-500/10 border border-red-500/20"><p v-for="(error, key) in dishForm.errors" :key="key" class="text-red-400 text-[11px]">{{ error }}</p></div>
                                        <button type="submit" :disabled="dishForm.processing" class="w-full py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 disabled:opacity-50 text-white font-bold rounded-xl text-sm shadow-lg shadow-amber-500/20 transition-all">{{ dishForm.processing ? '⏳...' : '🔑 Ajouter' }}</button>
                                    </form>
                                </div>
                                <div :class="[card, 'rounded-2xl border p-5']">
                                    <div class="flex items-center gap-2.5 mb-5"><div class="w-9 h-9 rounded-xl bg-violet-500/10 flex items-center justify-center text-base">📌</div><h3 class="font-bold text-sm">Planifier au menu du jour</h3></div>
                                    <form @submit.prevent="submitSchedule" class="space-y-3.5">
                                        <div><label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" :class="mutedText">Plat</label><select v-model="scheduleForm.dish_id" required :class="[inputClass, 'w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none transition-all']"><option value="">Sélectionner...</option><optgroup v-for="cat in [...new Set(dishes.map(d => d.category))]" :key="cat" :label="cat"><option v-for="dish in dishes.filter(d => d.category === cat)" :key="dish.id" :value="dish.id">{{ dish.name }}</option></optgroup></select></div>
                                        <div><label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" :class="mutedText">Jour</label><select v-model="scheduleForm.day_of_week" :class="[inputClass, 'w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none transition-all']"><option v-for="day in daysOfWeek" :key="day" :value="day" class="capitalize">{{ day }}</option></select></div>
                                        <button type="submit" :disabled="scheduleForm.processing" class="w-full py-2.5 bg-gradient-to-r from-violet-500 to-purple-500 hover:from-violet-400 hover:to-purple-400 disabled:opacity-50 text-white font-bold rounded-xl text-sm shadow-lg shadow-violet-500/20 transition-all">{{ scheduleForm.processing ? '⏳...' : '📌 Planifier' }}</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ═══ RÉSERVATIONS ═══ -->
                    <div v-if="activeTab === 'reservations'" class="space-y-6">
                        <div class="grid grid-cols-3 gap-4">
                            <div v-for="s in [
                                { label: 'En attente', val: reservations.filter(r => r.status === 'en_attente').length, color: 'text-amber-500' },
                                { label: 'Confirmées', val: reservations.filter(r => r.status === 'confirme').length, color: 'text-emerald-500' },
                                { label: 'Annulées', val: reservations.filter(r => r.status === 'annule').length, color: 'text-red-500' }
                            ]" :key="s.label" :class="[card, 'rounded-2xl border p-4 text-center transition-all duration-300 hover:-translate-y-0.5']">
                                <p class="text-2xl font-black" :class="s.color">{{ s.val }}</p>
                                <p class="text-[10px] font-bold uppercase tracking-wider mt-1" :class="mutedText">{{ s.label }}</p>
                            </div>
                        </div>
                        <div :class="[card, 'rounded-2xl border overflow-hidden']">
                            <div v-if="reservations.length > 0" class="divide-y" :class="borderColorLight">
                                <div v-for="res in reservations" :key="res.id" class="p-4 md:p-5 transition-colors" :class="isDarkMode ? 'hover:bg-emerald-500/5' : 'hover:bg-emerald-50/60'">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-3 mb-2">
                                                <h4 class="font-bold text-base truncate">{{ res.name }}</h4>
                                                <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-bold border', resStatusConfig[res.status]?.bg, resStatusConfig[res.status]?.color]">{{ resStatusConfig[res.status]?.label || res.status }}</span>
                                            </div>
                                            <div class="flex flex-wrap gap-x-5 gap-y-1.5 text-xs" :class="mutedText">
                                                <span class="flex items-center gap-1.5"><span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] border" :class="isDarkMode ? 'bg-slate-800 border-slate-700' : 'bg-slate-100 border-slate-200'">📞</span>{{ res.phone }}</span>
                                                <span class="flex items-center gap-1.5"><span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] border" :class="isDarkMode ? 'bg-slate-800 border-slate-700' : 'bg-slate-100 border-slate-200'">👥</span>{{ res.guests }} pers.</span>
                                                <span class="flex items-center gap-1.5"><span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] border" :class="isDarkMode ? 'bg-slate-800 border-slate-700' : 'bg-slate-100 border-slate-200'">📅</span>{{ res.date }}</span>
                                                <span class="flex items-center gap-1.5"><span class="w-5 h-5 rounded-md flex items-center justify-center text-[10px] border" :class="isDarkMode ? 'bg-slate-800 border-slate-700' : 'bg-slate-100 border-slate-200'">⏰</span>{{ res.time }}</span>
                                            </div>
                                        </div>
                                        <div v-if="res.status === 'en_attente'" class="flex items-center gap-2 shrink-0">
                                            <button @click="updateStatus(res.id, 'confirme')" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl text-xs font-bold transition-all shadow-lg shadow-emerald-500/20">✓ Confirmer</button>
                                            <button @click="updateStatus(res.id, 'annule')" class="px-4 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-500 rounded-xl text-xs font-bold transition-all border border-red-500/20">✕ Annuler</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-16 opacity-30"><span class="text-4xl block mb-2">🥂</span><p class="text-sm">Aucune réservation</p></div>
                        </div>
                    </div>

                    <!-- ═══ RAPPORTS ═══ -->
                    <div v-if="activeTab === 'reports'" class="space-y-6">
                        <div :class="[card, 'p-5 rounded-2xl border']">
                            <div class="flex items-center gap-2.5 mb-4"><div class="w-9 h-9 rounded-xl bg-emerald-500/10 flex items-center justify-center text-base">📈</div><h3 class="font-bold text-sm">Filtrer par période</h3></div>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <div class="flex-1"><label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" :class="mutedText">Début</label><input v-model="filterForm.start_date" type="date" :class="[inputClass, 'w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none transition-all', isDarkMode ? '[color-scheme:dark]' : '']"></div>
                                <div class="flex-1"><label class="block text-[10px] font-bold uppercase tracking-wider mb-1.5" :class="mutedText">Fin</label><input v-model="filterForm.end_date" type="date" :class="[inputClass, 'w-full rounded-xl border px-3.5 py-2.5 text-sm outline-none transition-all', isDarkMode ? '[color-scheme:dark]' : '']"></div>
                                <div class="flex items-end gap-2">
                                    <button @click="applyFilters" class="px-6 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl text-xs font-bold transition-all shadow-lg shadow-emerald-500/20 whitespace-nowrap">📊 Filtrer</button>
                                    <button @click="printReport" class="px-4 py-2.5 border rounded-xl text-xs font-bold transition-all whitespace-nowrap" :class="isDarkMode ? 'border-slate-600 text-slate-300 hover:bg-slate-800' : 'border-slate-300 text-slate-600 hover:bg-slate-100'">🖨️ Imprimer</button>
                                </div>
                            </div>
                        </div>
                        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-600 to-emerald-800 p-6 md:p-8 text-white shadow-xl shadow-emerald-500/20">
                            <p class="text-emerald-200 text-xs font-bold uppercase tracking-wider mb-2">Chiffre d'affaires</p>
                            <p class="text-3xl md:text-4xl font-black">{{ reportTotal.toLocaleString('fr-FR') }} F</p>
                            <p class="text-emerald-200/70 text-xs mt-2">Du {{ new Date(reportFilters.start_date).toLocaleDateString('fr-FR') }} au {{ new Date(reportFilters.end_date).toLocaleDateString('fr-FR') }} — {{ reportOrders.length }} commande(s)</p>
                            <div class="absolute -right-8 -bottom-8 w-40 h-40 bg-white/5 rounded-full"></div>
                        </div>
                        <div :class="[card, 'rounded-2xl border overflow-hidden']">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead :class="isDarkMode ? 'bg-slate-800/60' : 'bg-slate-100'">
                                        <tr>
                                            <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider opacity-50">N°</th>
                                            <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider opacity-50">Date</th>
                                            <th class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wider opacity-50">Détails</th>
                                            <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-wider opacity-50">Montant</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y" :class="borderColorLight">
                                        <tr v-for="order in reportOrders" :key="order.id" class="transition-colors" :class="isDarkMode ? 'hover:bg-emerald-500/5' : 'hover:bg-emerald-50/60'">
                                            <td class="px-5 py-3 font-bold text-sm">#{{ order.id }}</td>
                                            <td class="px-5 py-3 text-xs" :class="mutedText">{{ new Date(order.created_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }) }}</td>
                                            <td class="px-5 py-3 text-xs" :class="mutedText">{{ order.customer_name }} <span class="opacity-50">({{ order.delivery_address }})</span></td>
                                            <td class="px-5 py-3 text-right font-bold text-emerald-500">{{ Number(order.total_amount).toLocaleString('fr-FR') }} F</td>
                                        </tr>
                                        <tr v-if="reportOrders.length === 0"><td colspan="4" class="text-center py-14 opacity-30"><span class="text-3xl block mb-2">📊</span><p class="text-sm">Aucune donnée</p></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>
</template>

<style>
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(16, 185, 129, 0.2); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: rgba(16, 185, 129, 0.4); }
select option { background-color: #1e293b; color: #e2e8f0; }
@keyframes tabIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
main > div > div > div { animation: tabIn 0.3s ease-out; }
</style>
