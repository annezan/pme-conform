/**
 * Page de connexion premium — PME-CONFORM Plateforme.
 *
 * Layout split-screen : hero immersif a gauche (orbes degradees, grille,
 * cartes flottantes, badges de confiance) + formulaire ele en glassmorphism
 * a droite.
 */

import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '@/contexts/AuthContext';
import { alertError } from '@/utils/alerts';
import logoPng from '@/assets/logo.png';
import {
    EnvelopeIcon, LockClosedIcon, EyeIcon, EyeSlashIcon,
    ArrowRightIcon, SparklesIcon,
} from '@heroicons/react/24/outline';
import { ShieldCheckIcon, CpuChipIcon, ServerStackIcon, BoltIcon } from '@heroicons/react/24/solid';

export default function Login() {
    const navigate = useNavigate();
    const { login } = useAuth();

    const [form, setForm] = useState({ email: '', password: '', remember: false });
    const [loading, setLoading] = useState(false);
    const [showPassword, setShowPassword] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);

        try {
            const data = await login(form.email, form.password, form.remember);
            const roles = data?.user?.roles ?? [];
            const destination = roles.includes('client') || roles.includes('client_admin')
                ? '/mes-documents'
                : '/';
            navigate(destination);
        } catch (err) {
            alertError(err.response?.data?.message || 'Identifiants incorrects.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen flex bg-slate-950">
            {/* ============================================================
                PANNEAU GAUCHE — Hero immersif
                ============================================================ */}
            <div className="hidden lg:flex lg:w-[55%] relative overflow-hidden bg-gradient-to-br from-slate-950 via-blue-950 to-indigo-950">
                {/* Grille de fond */}
                <div
                    className="absolute inset-0 opacity-[0.07]"
                    style={{
                        backgroundImage: 'linear-gradient(rgba(255,255,255,0.5) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.5) 1px, transparent 1px)',
                        backgroundSize: '48px 48px',
                    }}
                />

                {/* Orbes degradees animees */}
                <div className="absolute -top-32 -left-32 w-[480px] h-[480px] bg-blue-500/30 rounded-full blur-[120px] animate-pulse-slow" />
                <div className="absolute top-1/3 -right-40 w-[520px] h-[520px] bg-indigo-500/25 rounded-full blur-[140px] animate-pulse-slow" style={{ animationDelay: '1s' }} />
                <div className="absolute -bottom-40 left-1/3 w-[420px] h-[420px] bg-cyan-500/20 rounded-full blur-[120px] animate-pulse-slow" style={{ animationDelay: '2s' }} />

                {/* Reflet superieur */}
                <div className="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent" />

                {/* Contenu */}
                <div className="relative z-10 flex flex-col justify-between px-12 xl:px-16 py-12 w-full">
                    {/* Header — logo + badge */}
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="relative">
                                <div className="absolute inset-0 bg-blue-500/40 rounded-2xl blur-xl" />
                                <img src={logoPng} alt="PME-CONFORM" className="relative w-12 h-12 rounded-2xl bg-white/10 backdrop-blur ring-1 ring-white/20 p-1.5 object-contain" />
                            </div>
                            <div>
                                <p className="text-xl font-bold text-white tracking-tight">PME-CONFORM</p>
                                <p className="text-[11px] text-blue-300/80 tracking-wider uppercase">by AS Consulting</p>
                            </div>
                        </div>

                        <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 ring-1 ring-emerald-400/30 backdrop-blur">
                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                            <span className="text-xs font-medium text-emerald-300">Plateforme opérationnelle</span>
                        </div>
                    </div>

                    {/* Bloc central — titre + cartes flottantes */}
                    <div className="space-y-10">
                        <div className="space-y-4 max-w-xl">
                            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 ring-1 ring-white/10 backdrop-blur">
                                <SparklesIcon className="w-4 h-4 text-amber-300" />
                                <span className="text-xs font-medium text-white/80">Conformité augmentée par l'IA</span>
                            </div>
                            <h1 className="text-5xl xl:text-6xl font-bold text-white leading-[1.05] tracking-tight">
                                La conformité,<br />
                                <span className="bg-gradient-to-r from-cyan-300 via-blue-300 to-indigo-300 bg-clip-text text-transparent">
                                    enfin à portée de PME.
                                </span>
                            </h1>
                            <p className="text-lg text-blue-100/70 leading-relaxed max-w-lg">
                                Plateforme tout-en-un pour la protection des données personnelles,
                                la mise en conformité ARTCI et l'audit des risques pénaux du dirigeant.
                            </p>
                        </div>

                        {/* Trois cartes feature flottantes */}
                        <div className="grid grid-cols-3 gap-3 max-w-2xl">
                            <FeatureCard
                                icon={CpuChipIcon}
                                title="Agents IA"
                                subtitle="8 modules"
                                iconBg="from-blue-500/30 to-cyan-500/30"
                                iconColor="text-cyan-300"
                            />
                            <FeatureCard
                                icon={ServerStackIcon}
                                title="On-premise"
                                subtitle="100 % souverain"
                                iconBg="from-indigo-500/30 to-purple-500/30"
                                iconColor="text-indigo-300"
                            />
                            <FeatureCard
                                icon={BoltIcon}
                                title="Audit Flash"
                                subtitle="10 questions, 3 min"
                                iconBg="from-rose-500/30 to-orange-500/30"
                                iconColor="text-rose-300"
                            />
                        </div>
                    </div>

                    {/* Footer hero — badges de confiance */}
                    <div className="space-y-4">
                        <div className="flex items-center gap-2 text-xs text-blue-200/60 uppercase tracking-wider font-medium">
                            <span className="h-px flex-1 bg-blue-200/20" />
                            <span>Certifications et conformité</span>
                            <span className="h-px flex-1 bg-blue-200/20" />
                        </div>
                        <div className="flex items-center flex-wrap gap-2.5">
                            <TrustBadge label="Loi 2013-450" />
                            <TrustBadge label="RGSSI 2025" />
                            <TrustBadge label="Hébergement CEDEAO" />
                            <TrustBadge label="Arrêté MTND" />
                            <TrustBadge label="RGPD compatible" />
                        </div>
                    </div>
                </div>
            </div>

            {/* ============================================================
                PANNEAU DROIT — Formulaire
                ============================================================ */}
            <div className="flex-1 flex items-center justify-center px-6 lg:px-12 py-10 bg-gradient-to-br from-slate-50 via-white to-blue-50/40 relative">
                {/* Decorations sur fond clair */}
                <div className="absolute top-0 right-0 w-64 h-64 bg-blue-200/30 rounded-full blur-3xl pointer-events-none" />
                <div className="absolute bottom-0 left-0 w-64 h-64 bg-indigo-200/30 rounded-full blur-3xl pointer-events-none" />

                <div className="relative w-full max-w-md animate-fadeIn">
                    {/* Logo mobile */}
                    <div className="lg:hidden flex flex-col items-center mb-8">
                        <img src={logoPng} alt="PME-CONFORM" className="w-14 h-14 object-contain mb-2" />
                        <span className="text-xl font-bold text-gray-900">PME-CONFORM</span>
                    </div>

                    {/* Carte formulaire glassmorphism */}
                    <div className="bg-white/80 backdrop-blur-xl rounded-3xl shadow-[0_20px_50px_-12px_rgba(30,64,175,0.18)] ring-1 ring-gray-200/60 p-8 lg:p-10">
                        <div className="mb-7">
                            <div className="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-blue-50 ring-1 ring-blue-100 mb-3">
                                <ShieldCheckIcon className="w-3.5 h-3.5 text-blue-600" />
                                <span className="text-[11px] font-semibold text-blue-700 uppercase tracking-wider">Accès sécurisé</span>
                            </div>
                            <h2 className="text-3xl font-bold text-gray-900 tracking-tight">Connexion</h2>
                            <p className="text-gray-500 mt-1.5 text-sm">Connectez-vous à votre espace de travail PME-CONFORM</p>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-5">
                            <div>
                                <label htmlFor="email" className="block text-sm font-semibold text-gray-700 mb-2">
                                    Adresse email
                                </label>
                                <div className="relative group">
                                    <EnvelopeIcon className="w-5 h-5 text-gray-400 group-focus-within:text-blue-600 absolute left-4 top-1/2 -translate-y-1/2 transition-colors pointer-events-none" />
                                    <input
                                        id="email"
                                        type="email"
                                        value={form.email}
                                        onChange={(e) => setForm({ ...form, email: e.target.value })}
                                        className="w-full pl-11 pr-4 py-3.5 rounded-xl border border-gray-200 bg-white/70 text-sm font-medium text-gray-900 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:bg-white outline-none transition-all placeholder:text-gray-400 placeholder:font-normal"
                                        placeholder="vous@entreprise.com"
                                        required
                                        autoFocus
                                        autoComplete="email"
                                    />
                                </div>
                            </div>

                            <div>
                                <div className="flex items-baseline justify-between mb-2">
                                    <label htmlFor="password" className="block text-sm font-semibold text-gray-700">
                                        Mot de passe
                                    </label>
                                    <Link to="/mot-de-passe-oublie" className="text-xs font-medium text-blue-700 hover:text-blue-900 transition-colors">
                                        Mot de passe oublié ?
                                    </Link>
                                </div>
                                <div className="relative group">
                                    <LockClosedIcon className="w-5 h-5 text-gray-400 group-focus-within:text-blue-600 absolute left-4 top-1/2 -translate-y-1/2 transition-colors pointer-events-none" />
                                    <input
                                        id="password"
                                        type={showPassword ? 'text' : 'password'}
                                        value={form.password}
                                        onChange={(e) => setForm({ ...form, password: e.target.value })}
                                        className="w-full pl-11 pr-12 py-3.5 rounded-xl border border-gray-200 bg-white/70 text-sm font-medium text-gray-900 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:bg-white outline-none transition-all placeholder:text-gray-400 placeholder:font-normal"
                                        placeholder="********"
                                        required
                                        autoComplete="current-password"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPassword(s => !s)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-all"
                                        aria-label={showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                                    >
                                        {showPassword ? <EyeSlashIcon className="w-4 h-4" /> : <EyeIcon className="w-4 h-4" />}
                                    </button>
                                </div>
                            </div>

                            <label className="flex items-center gap-2.5 cursor-pointer select-none w-fit">
                                <input
                                    type="checkbox"
                                    checked={form.remember}
                                    onChange={(e) => setForm({ ...form, remember: e.target.checked })}
                                    className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                                />
                                <span className="text-sm text-gray-600">Garder ma session active</span>
                            </label>

                            <button
                                type="submit"
                                disabled={loading}
                                className="group relative w-full py-3.5 px-4 bg-gradient-to-r from-blue-600 via-blue-700 to-indigo-700 hover:from-blue-700 hover:via-blue-800 hover:to-indigo-800 text-white font-semibold rounded-xl shadow-lg shadow-blue-500/30 hover:shadow-blue-500/40 hover:-translate-y-0.5 active:translate-y-0 transition-all duration-200 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:translate-y-0 text-sm overflow-hidden"
                            >
                                {/* Reflet brillant */}
                                <span className="absolute inset-0 -translate-x-full group-hover:translate-x-full bg-gradient-to-r from-transparent via-white/20 to-transparent transition-transform duration-700" />

                                {loading ? (
                                    <span className="relative flex items-center justify-center gap-2">
                                        <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                        </svg>
                                        Connexion en cours...
                                    </span>
                                ) : (
                                    <span className="relative flex items-center justify-center gap-2">
                                        Se connecter
                                        <ArrowRightIcon className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                                    </span>
                                )}
                            </button>
                        </form>

                        {/* Separateur */}
                        <div className="flex items-center gap-3 my-6">
                            <span className="h-px flex-1 bg-gray-200" />
                            <span className="text-xs text-gray-400 font-medium">ou</span>
                            <span className="h-px flex-1 bg-gray-200" />
                        </div>

                        <Link
                            to="/inscription"
                            className="block w-full py-3 px-4 text-center bg-white hover:bg-gray-50 text-gray-800 font-semibold rounded-xl ring-1 ring-gray-200 hover:ring-blue-400 hover:shadow-md transition-all duration-200 text-sm"
                        >
                            Créer mon compte client
                        </Link>
                    </div>

                    <p className="text-center text-xs text-gray-400 mt-8">
                        &copy; {new Date().getFullYear()} <span className="font-semibold text-gray-500">PME-CONFORM</span> — AS Consulting. Tous droits réservés.
                    </p>
                </div>
            </div>
        </div>
    );
}

/* ============================================================
   Sous-composants premium
   ============================================================ */

function FeatureCard({ icon: Icon, title, subtitle, iconBg, iconColor }) {
    return (
        <div className="group relative">
            <div className="absolute inset-0 bg-white/5 rounded-2xl blur opacity-0 group-hover:opacity-100 transition-opacity" />
            <div className="relative bg-white/[0.04] hover:bg-white/[0.08] ring-1 ring-white/10 hover:ring-white/20 backdrop-blur-xl rounded-2xl p-4 transition-all duration-300 hover:-translate-y-1">
                <div className={`inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br ${iconBg} mb-3`}>
                    <Icon className={`w-5 h-5 ${iconColor}`} />
                </div>
                <p className="text-sm font-bold text-white">{title}</p>
                <p className="text-xs text-blue-200/60 mt-0.5">{subtitle}</p>
            </div>
        </div>
    );
}

function TrustBadge({ label }) {
    return (
        <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/[0.04] ring-1 ring-white/10 backdrop-blur text-xs font-medium text-blue-100/80 hover:bg-white/[0.08] transition-colors">
            <ShieldCheckIcon className="w-3.5 h-3.5 text-emerald-300/80" />
            {label}
        </span>
    );
}
