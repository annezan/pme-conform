/**
 * Layout principal premium — Sidebar sombre + Header bleu + Contenu.
 */

import { useState, useRef, useEffect } from 'react';
import { Link, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '@/contexts/AuthContext';
import { confirmAction } from '@/utils/alerts';
import {
    HomeIcon,
    CpuChipIcon,
    DocumentTextIcon,
    CloudArrowUpIcon,
    BuildingOffice2Icon,
    ClipboardDocumentListIcon,
    UsersIcon,
    CubeIcon,
    WrenchScrewdriverIcon,
    ShieldCheckIcon,
    ArrowLeftOnRectangleIcon,
    Bars3Icon,
    XMarkIcon,
    ChevronRightIcon,
    ChevronDownIcon,
    UserCircleIcon,
    Cog6ToothIcon,
    BookOpenIcon,
    MagnifyingGlassCircleIcon,
    FolderOpenIcon,
    DocumentDuplicateIcon,
    FlagIcon,
    RectangleGroupIcon,
    TagIcon,
    KeyIcon,
    BoltIcon,
} from '@heroicons/react/24/outline';
import { ShieldCheckIcon as ShieldSolid } from '@heroicons/react/24/solid';
import logoPng from '@/assets/logo.png';
import NotificationsBell from '@/components/NotificationsBell';

// Navigation generale — chaque item est gate par UNE permission backend.
// L'utilisateur ne voit que les items pour lesquels il a la permission. Pas de
// hardcoding de roles : un role dynamique "Superviseur" avec view-portefeuille
// verra automatiquement l'entree "Portefeuille ASC".
const navigation = [
    { nom: 'Tableau de bord', chemin: '/', icon: HomeIcon, permission: 'view-dashboard' },

    // Vue ASC
    { nom: 'Portefeuille ASC', chemin: '/asc/portefeuille', icon: RectangleGroupIcon, permission: 'view-portefeuille' },
    { nom: 'Clients', chemin: '/clients', icon: BuildingOffice2Icon, permission: 'view-clients' },
    { nom: 'Missions', chemin: '/missions', icon: ClipboardDocumentListIcon, permission: 'view-missions' },
    { nom: 'Audit Flash (ASC)', chemin: '/asc/audit-flash', icon: BoltIcon, permission: 'view-portefeuille' },

    // Espace client
    { nom: 'Mes documents', chemin: '/mes-documents', icon: FolderOpenIcon, permission: 'view-client-documents' },
    // Reserve au client_admin : on exclut les utilisateurs ASC (view-portefeuille)
    // qui gerent les users via /admin/utilisateurs.
    { nom: 'Mes utilisateurs', chemin: '/mes-utilisateurs', icon: UsersIcon, permission: 'manage-client-users', excludePermission: 'view-portefeuille' },
    // Reserve au client : les ASC consultent la matrice depuis /missions/{id}/matrice.
    { nom: 'Cartographie initiale', chemin: '/mes-matrices', icon: RectangleGroupIcon, permission: 'view-matrice', excludePermission: 'view-portefeuille' },
    // Reserve aux clients : on exclut les utilisateurs ASC (view-portefeuille)
    // qui consultent les questionnaires via /missions/{id}/questionnaires et /asc/questionnaires/{id}.
    { nom: 'Mes formulaires', chemin: '/mes-formulaires', icon: ClipboardDocumentListIcon, permission: 'view-client-questionnaires', excludePermission: 'view-portefeuille' },
    // Reserve au client_admin : les ASC ont leur propre entree "Audit Flash (ASC)" plus haut.
    { nom: 'Audit Flash', chemin: '/audit-flash', icon: BoltIcon, permission: 'access-audit-flash', excludePermission: 'view-portefeuille' },

    // Partage : pages communes ASC/client (le backend applique le scope)
    { nom: 'Analyses d\'écarts', chemin: '/analyses', icon: MagnifyingGlassCircleIcon, anyPermission: ['view-analyses', 'view-ecarts'] },
    { nom: 'Traitements', chemin: '/traitements', icon: ClipboardDocumentListIcon, permission: 'view-traitements' },
    { nom: 'Plans d\'actions', chemin: '/plans-actions', icon: FlagIcon, permission: 'view-plans-actions' },
    { nom: 'Registre de traitement', chemin: '/registre-kyc', icon: DocumentDuplicateIcon, permission: 'view-registres-kyc' },
    { nom: 'Chartes', chemin: '/chartes', icon: ShieldCheckIcon, permission: 'view-chartes' },

    // Outils ASC
    { nom: 'Référentiels', chemin: '/referentiels', icon: BookOpenIcon, permission: 'view-referentiels' },
    { nom: 'Upload document', chemin: '/documents/upload', icon: CloudArrowUpIcon, permission: 'upload-documents' },
    { nom: 'Agents IA', chemin: '/agents', icon: CpuChipIcon, permission: 'view-agents' },
    { nom: 'Générer document', chemin: '/documents/generer', icon: DocumentTextIcon, permission: 'generate-documents' },
];

const navigationAdmin = [
    { nom: 'Comptes en attente', chemin: '/admin/comptes-en-attente', icon: UserCircleIcon, permission: 'validate-accounts' },
    { nom: 'Utilisateurs', chemin: '/admin/utilisateurs', icon: UsersIcon, permission: 'manage-users' },
    { nom: 'Rôles', chemin: '/admin/roles', icon: KeyIcon, permission: 'manage-roles' },
    { nom: 'Permissions par rôle', chemin: '/admin/permissions', icon: ShieldCheckIcon, permission: 'manage-permissions' },
    { nom: 'Secteurs d\'activité', chemin: '/admin/secteurs', icon: TagIcon, permission: 'view-all-secteurs' },
    { nom: 'Modules', chemin: '/admin/modules', icon: CubeIcon, permission: 'manage-modules' },
    { nom: 'Agents IA', chemin: '/admin/agents', icon: WrenchScrewdriverIcon, permission: 'view-all-agents-admin' },
    { nom: 'Journal d\'audit', chemin: '/admin/audit', icon: ShieldCheckIcon, permission: 'view-audit-logs' },
];

export default function AppLayout() {
    const { user, logout, hasPermission } = useAuth();

    // Filtre un item de navigation :
    //   - excludePermission : item cache si l'utilisateur a cette permission (utile
    //     pour les items reserves cote client uniquement, masques aux ASC)
    //   - permission / anyPermission : item visible si l'utilisateur a (au moins une)
    //     des permissions listees
    const peutVoirItem = (item) => {
        if (item.excludePermission && hasPermission(item.excludePermission)) return false;
        if (item.permission) return hasPermission(item.permission);
        if (item.anyPermission) return hasPermission(item.anyPermission);
        return true;
    };

    const itemsVisibles = navigation.filter(peutVoirItem);
    const itemsAdminVisibles = navigationAdmin.filter(peutVoirItem);
    const location = useLocation();
    const navigate = useNavigate();
    const [sidebarOpen, setSidebarOpen] = useState(true);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [profileOpen, setProfileOpen] = useState(false);
    const profileRef = useRef(null);

    const handleLogout = async () => {
        setProfileOpen(false);
        const confirmed = await confirmAction('Voulez-vous vraiment vous déconnecter ?', 'Déconnexion');
        if (confirmed) {
            await logout();
            navigate('/login');
        }
    };

    // Fermer le dropdown profil au clic exterieur
    useEffect(() => {
        const handleClick = (e) => {
            if (profileRef.current && !profileRef.current.contains(e.target)) {
                setProfileOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClick);
        return () => document.removeEventListener('mousedown', handleClick);
    }, []);

    const isActive = (chemin) => {
        if (chemin === '/') return location.pathname === '/';
        return location.pathname.startsWith(chemin);
    };

    const NavLink = ({ item }) => {
        const active = isActive(item.chemin);
        const Icon = item.icon;
        return (
            <Link
                to={item.chemin}
                onClick={() => setMobileOpen(false)}
                className={`group flex items-center gap-3 px-3 py-2.5 rounded-lg text-[13px] font-medium transition-all duration-200 ${
                    active
                        ? 'bg-white/10 text-white'
                        : 'text-slate-400 hover:bg-white/5 hover:text-slate-200'
                }`}
            >
                <Icon className={`w-5 h-5 shrink-0 ${active ? 'text-blue-400' : 'text-slate-500 group-hover:text-slate-300'}`} />
                {sidebarOpen && <span>{item.nom}</span>}
            </Link>
        );
    };

    const sidebarContent = (
        <>
            {/* Logo */}
            <div className="px-4 py-5 border-b border-white/10 flex items-center justify-between">
                {sidebarOpen ? (
                    <Link to="/" className="flex items-center gap-2.5">
                        <img src={logoPng} alt="PME-CONFORM" className="w-9 h-9 rounded-lg bg-white/10 p-1 object-contain" />
                        <span className="text-lg font-bold text-white tracking-tight">PME-CONFORM</span>
                    </Link>
                ) : (
                    <img src={logoPng} alt="PME-CONFORM" className="w-9 h-9 rounded-lg bg-white/10 p-1 object-contain mx-auto" />
                )}
                <button onClick={() => setSidebarOpen(!sidebarOpen)} className="hidden lg:block text-slate-500 hover:text-slate-300 p-1">
                    <ChevronRightIcon className={`w-4 h-4 transition-transform ${sidebarOpen ? 'rotate-180' : ''}`} />
                </button>
            </div>

            {/* Navigation — filtree dynamiquement par permissions backend */}
            <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                {itemsVisibles.map((item) => <NavLink key={item.chemin} item={item} />)}

                {itemsAdminVisibles.length > 0 && (
                    <>
                        {sidebarOpen && (
                            <p className="px-3 pt-6 pb-1 text-[11px] uppercase tracking-wider text-slate-500 font-semibold">
                                Administration
                            </p>
                        )}
                        {!sidebarOpen && <div className="border-t border-white/10 my-3"></div>}
                        {itemsAdminVisibles.map((item) => <NavLink key={item.chemin} item={item} />)}
                    </>
                )}
            </nav>
        </>
    );

    return (
        // h-screen + overflow-hidden : on borne la hauteur a la viewport pour que
        // ni le header ni la sidebar ne scrollent. Seul <main> (overflow-auto)
        // gere le defilement quand le contenu deborde.
        <div className="h-screen flex bg-slate-50 overflow-hidden">
            {/* Overlay mobile */}
            {mobileOpen && (
                <div className="fixed inset-0 bg-black/40 z-40 lg:hidden" onClick={() => setMobileOpen(false)} />
            )}

            {/* Sidebar mobile */}
            <aside className={`fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 shadow-2xl flex flex-col transform transition-transform lg:hidden ${mobileOpen ? 'translate-x-0' : '-translate-x-full'}`}>
                <button onClick={() => setMobileOpen(false)} className="absolute top-4 right-4 text-slate-500 hover:text-slate-300">
                    <XMarkIcon className="w-5 h-5" />
                </button>
                {sidebarContent}
            </aside>

            {/* Sidebar desktop : h-screen herite du parent h-screen, ne scrolle pas */}
            <aside className={`hidden lg:flex flex-col bg-slate-900 transition-all duration-300 shrink-0 ${sidebarOpen ? 'w-60' : 'w-16'}`}>
                {sidebarContent}
            </aside>

            {/* Contenu principal */}
            <div className="flex-1 flex flex-col min-w-0 h-screen">
                {/* Header bleu — fixe en haut, ne scrolle pas */}
                <header className="bg-gradient-to-r from-blue-700 to-blue-800 shadow-md shadow-blue-900/10 px-4 lg:px-6 py-0 flex items-center justify-between h-14 shrink-0 z-10">
                    {/* Gauche : burger mobile + titre page */}
                    <div className="flex items-center gap-3">
                        <button onClick={() => setMobileOpen(true)} className="lg:hidden text-blue-200 hover:text-white">
                            <Bars3Icon className="w-6 h-6" />
                        </button>
                        <span className="text-sm font-medium text-blue-100 hidden sm:block">
                            PME-CONFORM — AS Consulting
                        </span>
                    </div>

                    {/* Droite : notifications + profil */}
                    <div className="flex items-center gap-2">
                        <NotificationsBell />

                        {/* Profil dropdown */}
                        <div className="relative" ref={profileRef}>
                            <button
                                onClick={() => setProfileOpen(!profileOpen)}
                                className="flex items-center gap-2.5 pl-2 pr-3 py-1.5 rounded-lg hover:bg-white/10 transition-colors"
                            >
                                <div className="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-white font-semibold text-xs">
                                    {user?.prenom?.charAt(0)}{user?.nom?.charAt(0)}
                                </div>
                                <div className="hidden sm:block text-left">
                                    <p className="text-sm font-medium text-white leading-tight">{user?.nom_complet}</p>
                                    <p className="text-[11px] text-blue-200 capitalize">{user?.roles?.[0]}</p>
                                </div>
                                <ChevronDownIcon className={`w-4 h-4 text-blue-200 hidden sm:block transition-transform ${profileOpen ? 'rotate-180' : ''}`} />
                            </button>

                            {/* Dropdown menu */}
                            {profileOpen && (
                                <div className="absolute right-0 top-full mt-2 w-56 bg-white rounded-xl shadow-xl border border-gray-200/60 py-2 animate-fadeIn z-50">
                                    <div className="px-4 py-3 border-b border-gray-100">
                                        <p className="text-sm font-semibold text-gray-900">{user?.nom_complet}</p>
                                        <p className="text-xs text-gray-500">{user?.email}</p>
                                        <p className="text-xs text-blue-600 capitalize mt-0.5">{user?.roles?.[0]}</p>
                                    </div>
                                    <div className="py-1">
                                        <Link to="/profil" onClick={() => setProfileOpen(false)}
                                            className="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                            <UserCircleIcon className="w-4 h-4 text-gray-400" />
                                            Mon profil
                                        </Link>
                                        <Link to="/parametres" onClick={() => setProfileOpen(false)}
                                            className="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                            <Cog6ToothIcon className="w-4 h-4 text-gray-400" />
                                            Paramètres
                                        </Link>
                                    </div>
                                    <div className="border-t border-gray-100 pt-1">
                                        <button
                                            onClick={handleLogout}
                                            className="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors"
                                        >
                                            <ArrowLeftOnRectangleIcon className="w-4 h-4" />
                                            Se déconnecter
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </header>

                <main className="flex-1 overflow-auto">
                    <div className="animate-fadeIn">
                        <Outlet />
                    </div>
                </main>
            </div>
        </div>
    );
}
