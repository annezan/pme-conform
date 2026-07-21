/**
 * Page d'inscription publique premium — PME-CONFORM.
 *
 * Layout 2 colonnes :
 *  - Gauche : hero immersif avec etapes, badges de confiance et garanties.
 *  - Droite : formulaire premium (Card glassmorphism, sections claires, CTA gradient).
 *
 * Cree simultanement le Client (avec contact principal) et le User
 * (role client_admin). Le contact principal sert d'identite a l'utilisateur.
 */

import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import Select from 'react-select';
import api from '@/api/client';
import { useAuth } from '@/contexts/AuthContext';
import { alertError, alertSuccess } from '@/utils/alerts';
import logoPng from '@/assets/logo.png';
import Button from '@/components/ui/Button';
import {
    BuildingOffice2Icon, LockClosedIcon, ArrowLeftIcon, ArrowRightIcon,
    XMarkIcon, DocumentTextIcon, CheckCircleIcon, ShieldCheckIcon,
    SparklesIcon, CpuChipIcon, BoltIcon, ChevronRightIcon,
} from '@heroicons/react/24/outline';

const CLIENT_INITIAL = {
    raison_sociale: '',
    secteurs_activite_ids: [],
    pays: 'Côte d\'Ivoire',
    adresse: '',
    ville: '',
    email: '',
    telephone: '',
    contact_principal_nom: '',
    contact_principal_email: '',
};
const USER_INITIAL = { password: '', password_confirmation: '' };

const BENEFICES = [
    { icon: ShieldCheckIcon, label: 'Conformité Loi 2013-450', desc: 'Cartographie complète des traitements de données personnelles.' },
    { icon: BoltIcon, label: 'Audit Flash en 3 min', desc: '10 questions C-Level pour mesurer votre exposition pénale.' },
    { icon: CpuChipIcon, label: '8 agents IA on-premise', desc: 'Vos données ne quittent jamais la zone CEDEAO.' },
    { icon: SparklesIcon, label: 'Génération documentaire', desc: 'Registres, chartes, plans d\'actions produits en un clic.' },
];

export default function Register() {
    const navigate = useNavigate();
    const { register } = useAuth();

    const [secteursOptions, setSecteursOptions] = useState([]);
    const [paysOptions, setPaysOptions] = useState([]);
    const [loadingRefs, setLoadingRefs] = useState(true);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState({});
    // Si non-null, on affiche l'ecran "compte en attente de validation" au lieu du formulaire.
    const [successInfo, setSuccessInfo] = useState(null);

    const [client, setClient] = useState(CLIENT_INITIAL);
    const [user, setUser] = useState(USER_INITIAL);
    const [accepteTermes, setAccepteTermes] = useState(false);
    const [showTermes, setShowTermes] = useState(false);

    useEffect(() => {
        Promise.all([
            api.get('/public/secteurs-activite').then(r => r.data.data || []).catch(() => []),
            api.get('/public/pays').then(r => r.data.data || []).catch(() => []),
        ]).then(([secteurs, pays]) => {
            setSecteursOptions(secteurs.map(s => ({ value: s.id, label: s.nom })));
            setPaysOptions(pays.map(p => ({ value: p, label: p })));
        }).finally(() => setLoadingRefs(false));
    }, []);

    const updateClient = (k, v) => setClient(c => ({ ...c, [k]: v }));
    const updateUser = (k, v) => setUser(u => ({ ...u, [k]: v }));

    const handleSubmit = async (e) => {
        e.preventDefault();
        setErrors({});

        if (!client.secteurs_activite_ids?.length) {
            setErrors({ 'client.secteurs_activite_ids': 'Sélectionnez au moins un secteur d\'activité.' });
            alertError('Veuillez sélectionner au moins un secteur d\'activité.');
            return;
        }
        if (user.password !== user.password_confirmation) {
            setErrors({ 'user.password_confirmation': 'Les mots de passe ne correspondent pas.' });
            return;
        }
        if (user.password.length < 8) {
            setErrors({ 'user.password': 'Le mot de passe doit contenir au moins 8 caractères.' });
            return;
        }
        if (!accepteTermes) {
            alertError('Vous devez accepter les termes et conditions pour créer votre compte.');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                client: {
                    ...client,
                    secteurs_activite_ids: (client.secteurs_activite_ids || []).map(o => o.value),
                },
                user,
            };
            const data = await register(payload);
            // Le backend renvoie compte_en_attente=true et NE connecte PAS l'utilisateur.
            // On affiche un ecran de confirmation explicite plutot qu'une redirection.
            setSuccessInfo({
                emailContact: data?.email_contact || user.email,
                raisonSociale: data?.client?.raison_sociale || client.raison_sociale,
            });
        } catch (err) {
            const respErrors = err.response?.data?.errors;
            if (respErrors) {
                const flat = {};
                Object.entries(respErrors).forEach(([k, msgs]) => { flat[k] = Array.isArray(msgs) ? msgs[0] : msgs; });
                setErrors(flat);
                alertError('Veuillez corriger les erreurs du formulaire.');
            } else {
                alertError(err.response?.data?.message || 'Erreur lors de la création du compte.');
            }
        } finally {
            setSaving(false);
        }
    };

    const err = (key) => errors[key];

    const inputCls = (key) => `w-full px-3.5 py-2.5 rounded-lg border bg-white/80 text-sm font-medium text-gray-900 outline-none transition-all placeholder:text-gray-400 placeholder:font-normal ${err(key) ? 'border-red-300 focus:border-red-500 focus:ring-4 focus:ring-red-500/10' : 'border-gray-200 hover:border-gray-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 focus:bg-white'}`;

    const labelReq = (txt) => (<>{txt} <span className="text-red-500">*</span></>);

    // Ecran de confirmation post-inscription : compte en attente de validation par ASC.
    if (successInfo) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-950 via-blue-950 to-indigo-950 px-4">
                <div className="max-w-lg w-full bg-white rounded-2xl shadow-2xl p-8 text-center">
                    <div className="w-16 h-16 rounded-full bg-amber-100 mx-auto mb-5 flex items-center justify-center">
                        <svg className="w-8 h-8 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h1 className="text-2xl font-bold text-gray-900 mb-2">Compte en attente de validation</h1>
                    <p className="text-gray-600 leading-relaxed">
                        Votre demande d'inscription pour <strong>{successInfo.raisonSociale}</strong> a bien été enregistrée.
                    </p>
                    <p className="text-gray-600 leading-relaxed mt-3">
                        AS Consulting va examiner votre dossier sous peu. Vous recevrez un e-mail à
                        l'adresse <strong className="text-blue-700">{successInfo.emailContact}</strong> dès
                        que votre compte sera validé.
                    </p>
                    <div className="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-lg text-left text-sm text-amber-900">
                        <p className="font-semibold mb-1">⚠️ Important</p>
                        <p>Vous ne pouvez pas vous connecter à la plateforme tant que votre compte n'a pas été validé par AS Consulting.</p>
                    </div>
                    <button
                        onClick={() => navigate('/login')}
                        className="mt-6 inline-flex items-center justify-center px-6 py-2.5 rounded-lg bg-blue-700 text-white font-semibold hover:bg-blue-800 transition-colors"
                    >
                        Retour à la page de connexion
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen flex bg-slate-950">
            {/* ============================================================
                PANNEAU GAUCHE — Hero immersif (desktop only)
                ============================================================ */}
            <aside className="hidden lg:flex lg:w-[42%] xl:w-[44%] relative overflow-hidden bg-gradient-to-br from-slate-950 via-blue-950 to-indigo-950">
                {/* Grille subtile */}
                <div
                    className="absolute inset-0 opacity-[0.07] pointer-events-none"
                    style={{
                        backgroundImage: 'linear-gradient(rgba(255,255,255,0.5) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.5) 1px, transparent 1px)',
                        backgroundSize: '48px 48px',
                    }}
                />
                {/* Orbes */}
                <div className="absolute -top-32 -left-32 w-[440px] h-[440px] bg-blue-500/30 rounded-full blur-[120px] animate-pulse-slow" />
                <div className="absolute bottom-0 -right-40 w-[520px] h-[520px] bg-indigo-500/25 rounded-full blur-[140px] animate-pulse-slow" style={{ animationDelay: '1.5s' }} />

                <div className="relative z-10 flex flex-col justify-between px-10 xl:px-14 py-10 w-full">
                    {/* Header */}
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

                    {/* Titre + benefices */}
                    <div className="space-y-7">
                        <div className="space-y-3">
                            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 ring-1 ring-white/10 backdrop-blur">
                                <SparklesIcon className="w-4 h-4 text-amber-300" />
                                <span className="text-xs font-medium text-white/80">Accès gratuit à la plateforme</span>
                            </div>
                            <h1 className="text-4xl xl:text-5xl font-bold text-white leading-[1.05] tracking-tight">
                                Mettez-vous en{' '}
                                <span className="bg-gradient-to-r from-cyan-300 via-blue-300 to-indigo-300 bg-clip-text text-transparent">conformité</span>{' '}
                                en quelques minutes.
                            </h1>
                            <p className="text-base text-blue-100/70 max-w-md leading-relaxed">
                                Rejoignez les PME ivoiriennes qui pilotent leur conformité RGPD / Loi 2013-450 avec PME-CONFORM.
                            </p>
                        </div>

                        <div className="space-y-3">
                            {BENEFICES.map(b => {
                                const Icon = b.icon;
                                return (
                                    <div key={b.label} className="group flex items-start gap-3 p-3 rounded-xl bg-white/[0.04] ring-1 ring-white/10 backdrop-blur hover:bg-white/[0.08] transition-colors">
                                        <div className="shrink-0 w-9 h-9 rounded-lg bg-gradient-to-br from-blue-500/30 to-indigo-500/30 flex items-center justify-center">
                                            <Icon className="w-4.5 h-4.5 text-cyan-300" />
                                        </div>
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold text-white">{b.label}</p>
                                            <p className="text-xs text-blue-200/60 mt-0.5">{b.desc}</p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Footer */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-3 text-xs text-blue-200/60">
                            <CheckCircleIcon className="w-4 h-4 text-emerald-300" />
                            <span>Hébergement souverain CEDEAO</span>
                        </div>
                        <div className="flex items-center gap-3 text-xs text-blue-200/60">
                            <CheckCircleIcon className="w-4 h-4 text-emerald-300" />
                            <span>Aucune carte bancaire requise</span>
                        </div>
                        <div className="flex items-center gap-3 text-xs text-blue-200/60">
                            <CheckCircleIcon className="w-4 h-4 text-emerald-300" />
                            <span>Annulation possible à tout moment</span>
                        </div>
                    </div>
                </div>
            </aside>

            {/* ============================================================
                PANNEAU DROIT — Formulaire premium
                ============================================================ */}
            <main className="flex-1 bg-gradient-to-br from-slate-50 via-white to-blue-50/40 overflow-y-auto relative">
                {/* Decorations */}
                <div className="absolute top-0 right-0 w-72 h-72 bg-blue-200/30 rounded-full blur-3xl pointer-events-none" />
                <div className="absolute bottom-0 left-0 w-72 h-72 bg-indigo-200/30 rounded-full blur-3xl pointer-events-none" />

                <div className="relative max-w-2xl mx-auto px-6 lg:px-8 py-8">
                    {/* Top bar */}
                    <div className="flex items-center justify-between mb-6">
                        <Link to="/login" className="inline-flex items-center gap-1.5 text-sm font-medium text-blue-700 hover:text-blue-900 transition-colors">
                            <ArrowLeftIcon className="w-4 h-4" /> Retour à la connexion
                        </Link>
                        <div className="lg:hidden flex items-center gap-2">
                            <img src={logoPng} alt="PME-CONFORM" className="w-8 h-8 object-contain" />
                            <span className="font-bold text-gray-900 text-sm">PME-CONFORM</span>
                        </div>
                    </div>

                    {/* Titre */}
                    <div className="mb-7">
                        <div className="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-blue-50 ring-1 ring-blue-100 mb-3">
                            <BuildingOffice2Icon className="w-3.5 h-3.5 text-blue-600" />
                            <span className="text-[11px] font-semibold text-blue-700 uppercase tracking-wider">Inscription gratuite</span>
                        </div>
                        <h2 className="text-3xl font-bold text-gray-900 tracking-tight">Créer mon compte</h2>
                        <p className="text-gray-500 mt-1.5 text-sm">Renseignez votre entreprise et votre compte administrateur en une seule étape.</p>
                    </div>

                    {/* Carte formulaire */}
                    <div className="bg-white/80 backdrop-blur-xl rounded-3xl shadow-[0_20px_50px_-12px_rgba(30,64,175,0.12)] ring-1 ring-gray-200/60 p-6 lg:p-8">
                        <form onSubmit={handleSubmit} className="space-y-7">
                            {/* SECTION ENTREPRISE */}
                            <section>
                                <div className="flex items-center gap-2 mb-5">
                                    <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                                        <BuildingOffice2Icon className="w-4 h-4 text-white" />
                                    </div>
                                    <div>
                                        <h3 className="text-base font-bold text-gray-900">Informations de l'entreprise</h3>
                                        <p className="text-xs text-gray-500">Ces informations identifient votre entreprise dans PME-CONFORM.</p>
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-semibold text-gray-700 mb-1.5">{labelReq('Raison sociale')}</label>
                                        <input type="text" value={client.raison_sociale} onChange={e => updateClient('raison_sociale', e.target.value)} required placeholder="Nom de l'entreprise" className={inputCls('client.raison_sociale')} />
                                        {err('client.raison_sociale') && <p className="text-xs text-red-600 mt-1">{err('client.raison_sociale')}</p>}
                                    </div>

                                    <div>
                                        <label className="block text-sm font-semibold text-gray-700 mb-1.5">{labelReq('Secteur(s) d\'activité')}</label>
                                        <Select
                                            isMulti
                                            options={secteursOptions}
                                            value={client.secteurs_activite_ids}
                                            onChange={(val) => updateClient('secteurs_activite_ids', val || [])}
                                            placeholder={loadingRefs ? 'Chargement...' : 'Choisissez un ou plusieurs secteurs'}
                                            isLoading={loadingRefs}
                                            classNamePrefix="rs"
                                            noOptionsMessage={() => 'Aucun secteur disponible'}
                                        />
                                        {err('client.secteurs_activite_ids') && <p className="text-xs text-red-600 mt-1">{err('client.secteurs_activite_ids')}</p>}
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">{labelReq('Pays')}</label>
                                            <Select
                                                options={paysOptions}
                                                value={paysOptions.find(p => p.value === client.pays) || null}
                                                onChange={(val) => updateClient('pays', val?.value || '')}
                                                placeholder={loadingRefs ? 'Chargement...' : 'Choisissez un pays'}
                                                isLoading={loadingRefs}
                                                classNamePrefix="rs"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">Ville</label>
                                            <input type="text" value={client.ville} onChange={e => updateClient('ville', e.target.value)} placeholder="Ex: Abidjan" className={inputCls('client.ville')} />
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-semibold text-gray-700 mb-1.5">Adresse</label>
                                        <textarea value={client.adresse} onChange={e => updateClient('adresse', e.target.value)} rows={2} placeholder="Rue, quartier, code postal..." className={inputCls('client.adresse')} />
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">Email entreprise</label>
                                            <input type="email" value={client.email} onChange={e => updateClient('email', e.target.value)} placeholder="contact@entreprise.ci" className={inputCls('client.email')} />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">Telephone</label>
                                            <input type="text" value={client.telephone} onChange={e => updateClient('telephone', e.target.value)} placeholder="+225 XX XX XX XX" className={inputCls('client.telephone')} />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">{labelReq('Contact principal')}</label>
                                            <input type="text" value={client.contact_principal_nom} onChange={e => updateClient('contact_principal_nom', e.target.value)} required placeholder="Prenom Nom" className={inputCls('client.contact_principal_nom')} />
                                            {err('client.contact_principal_nom') && <p className="text-xs text-red-600 mt-1">{err('client.contact_principal_nom')}</p>}
                                            <p className="text-xs text-gray-500 mt-1">Servira d'identité à votre compte administrateur.</p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-semibold text-gray-700 mb-1.5">{labelReq('Email')}</label>
                                            <input type="email" value={client.contact_principal_email} onChange={e => updateClient('contact_principal_email', e.target.value)} required placeholder="email@contact.ci" className={inputCls('client.contact_principal_email')} />
                                            {err('client.contact_principal_email') && <p className="text-xs text-red-600 mt-1">{err('client.contact_principal_email')}</p>}
                                            <p className="text-xs text-gray-500 mt-1">Identifiant de connexion.</p>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            {/* SEPARATEUR */}
                            <div className="flex items-center gap-3">
                                <span className="h-px flex-1 bg-gray-200" />
                                <span className="text-[11px] font-semibold uppercase tracking-wider text-gray-400">Sécurité</span>
                                <span className="h-px flex-1 bg-gray-200" />
                            </div>

                            {/* SECTION MOTS DE PASSE */}
                            <section className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-semibold text-gray-700 mb-1.5">{labelReq('Mot de passe')}</label>
                                    <div className="relative">
                                        <LockClosedIcon className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                                        <input type="password" value={user.password} onChange={e => updateUser('password', e.target.value)} required minLength={8} className={`${inputCls('user.password')} pl-9`} placeholder="8 caractères minimum" />
                                    </div>
                                    {err('user.password') && <p className="text-xs text-red-600 mt-1">{err('user.password')}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-semibold text-gray-700 mb-1.5">{labelReq('Confirmer le mot de passe')}</label>
                                    <div className="relative">
                                        <LockClosedIcon className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                                        <input type="password" value={user.password_confirmation} onChange={e => updateUser('password_confirmation', e.target.value)} required minLength={8} className={`${inputCls('user.password_confirmation')} pl-9`} />
                                    </div>
                                    {err('user.password_confirmation') && <p className="text-xs text-red-600 mt-1">{err('user.password_confirmation')}</p>}
                                </div>
                            </section>

                            {/* ACCEPTATION */}
                            <div className="pt-4 border-t border-gray-100 space-y-4">
                                <label className="flex items-start gap-3 cursor-pointer group">
                                    <input
                                        type="checkbox"
                                        checked={accepteTermes}
                                        onChange={e => setAccepteTermes(e.target.checked)}
                                        className="mt-0.5 w-5 h-5 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 shrink-0"
                                    />
                                    <span className="text-sm text-gray-700 leading-relaxed">
                                        J'accepte les{' '}
                                        <button type="button" onClick={() => setShowTermes(true)} className="text-blue-700 font-semibold hover:text-blue-900 underline underline-offset-2">
                                            termes et conditions
                                        </button>{' '}
                                        de PME-CONFORM, ainsi que la politique de protection des données conforme à la Loi 2013-450 et au RGPD.
                                    </span>
                                </label>

                                <Button
                                    type="submit"
                                    size="xl"
                                    variant="primary"
                                    loading={saving}
                                    disabled={!accepteTermes}
                                    className="w-full justify-center"
                                >
                                    {saving ? 'Création en cours...' : (
                                        <>
                                            Créer mon compte <ArrowRightIcon className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                                        </>
                                    )}
                                </Button>

                                <p className="text-center text-sm text-gray-600">
                                    Déjà inscrit ? <Link to="/login" className="text-blue-700 font-semibold hover:text-blue-900">Se connecter</Link>
                                </p>
                            </div>
                        </form>
                    </div>

                    <p className="text-center text-xs text-gray-400 mt-8 mb-4">
                        &copy; {new Date().getFullYear()} <span className="font-semibold text-gray-500">PME-CONFORM</span> — AS Consulting.
                    </p>
                </div>
            </main>

            {/* ============================================================
                Modal Termes & Conditions
                ============================================================ */}
            {showTermes && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 animate-fadeIn">
                    <div className="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" onClick={() => setShowTermes(false)} />
                    <div className="relative w-full max-w-3xl max-h-[92vh] flex flex-col bg-white rounded-3xl shadow-2xl ring-1 ring-black/5 overflow-hidden" onClick={(e) => e.stopPropagation()}>
                        <div className="relative px-6 lg:px-8 py-5 bg-gradient-to-r from-blue-600 via-blue-700 to-indigo-700 text-white">
                            <div className="absolute -top-12 -right-12 w-40 h-40 rounded-full bg-white/10 blur-2xl pointer-events-none" />
                            <div className="relative flex items-start justify-between gap-4">
                                <div className="flex items-start gap-3">
                                    <div className="shrink-0 w-10 h-10 rounded-xl bg-white/15 backdrop-blur ring-1 ring-white/20 flex items-center justify-center">
                                        <DocumentTextIcon className="w-5 h-5" />
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-bold">Termes et Conditions d'utilisation</h3>
                                        <p className="text-xs text-blue-100 mt-0.5">PME-CONFORM — AS Consulting · Version en vigueur au {new Date().toLocaleDateString('fr-FR')}</p>
                                    </div>
                                </div>
                                <button onClick={() => setShowTermes(false)} className="shrink-0 p-1.5 rounded-lg text-white/80 hover:text-white hover:bg-white/10 transition-colors" aria-label="Fermer">
                                    <XMarkIcon className="w-5 h-5" />
                                </button>
                            </div>
                        </div>

                        <div className="flex-1 overflow-y-auto px-6 lg:px-8 py-6 text-sm text-gray-700 leading-relaxed space-y-5">
                            <div className="p-4 rounded-xl bg-blue-50 border border-blue-100 flex items-start gap-3">
                                <ShieldCheckIcon className="w-6 h-6 text-blue-700 shrink-0 mt-0.5" />
                                <p className="text-xs text-blue-900">
                                    PME-CONFORM est une plateforme opérée par <strong>AS Consulting</strong> destinée à accompagner
                                    les PME ivoiriennes et de la zone CEDEAO dans la mise en conformité à la
                                    <strong> Loi 2013-450</strong>, au <strong>RGSSI 2025</strong> et aux <strong>Arrêtés MTND</strong>.
                                    En créant un compte, vous reconnaissez avoir lu, compris et accepté les présentes conditions.
                                </p>
                            </div>

                            <Article num={1} titre="Objet et périmètre du service">
                                Plateforme SaaS de gouvernance couvrant : registres de traitements (MOBISOFT), déclarations ARTCI,
                                analyses d'écarts juridiques, plans d'actions, chartes RGPD, registres KYC, cartographie initiale,
                                audits flash et génération documentaire assistée par IA.
                            </Article>
                            <Article num={2} titre="Compte utilisateur et responsabilités">
                                Vous êtes responsable de l'exactitude des informations fournies, de la confidentialité de votre mot de passe
                                et de tout accès réalisé depuis votre compte (rôle <strong>client_admin</strong>). Tout usage frauduleux doit être signalé à
                                <a href="mailto:support@asc-consulting.ci" className="text-blue-700 underline mx-1">support@asc-consulting.ci</a>.
                            </Article>
                            <Article num={3} titre="Protection des données personnelles">
                                AS Consulting agit comme <strong>sous-traitant</strong> (art. 4 Loi 2013-450). Données stockées en zone CEDEAO,
                                chiffrement TLS 1.3 + AES-256, mots de passe hachés avec bcrypt. Vos droits (accès, rectification, effacement, portabilité)
                                s'exercent par email à <a href="mailto:dpo@asc-consulting.ci" className="text-blue-700 underline">dpo@asc-consulting.ci</a>.
                            </Article>
                            <Article num={4} titre="Utilisation de l'IA">
                                Modèles exécutés <strong>on-premise</strong> (Ollama/pgvector) — aucune donnée transmise à un tiers IA.
                                Les contenus générés sont des recommandations professionnelles et ne se substituent pas à un conseil juridique.
                            </Article>
                            <Article num={5} titre="Propriété intellectuelle">
                                Modèles, référentiels, matrices et code source restent propriété d'AS Consulting. Vos contenus déposés (registres,
                                traitements, chartes) restent votre propriété.
                            </Article>
                            <Article num={6} titre="Disponibilité et responsabilité">
                                SLA cible 99,5 % hors maintenance. Responsabilité limitée aux dommages directs et plafonnée aux 12 derniers mois de redevances.
                                Sanctions ARTCI exclues.
                            </Article>
                            <Article num={7} titre="Modification, suspension et résiliation">
                                Modifications notifiées 30 jours à l'avance. Suspension immédiate en cas d'usage frauduleux.
                                Vous pouvez résilier à tout moment et récupérer vos données dans un format lisible.
                            </Article>
                            <Article num={8} titre="Droit applicable et juridiction">
                                Droit ivoirien. Tribunaux d'Abidjan après tentative préalable de résolution amiable.
                            </Article>

                            <p className="text-xs text-gray-500 italic pt-2 border-t border-gray-100">
                                Pour toute question, contactez <a href="mailto:legal@asc-consulting.ci" className="text-blue-700 underline">legal@asc-consulting.ci</a>.
                            </p>
                        </div>

                        <div className="px-6 lg:px-8 py-4 border-t border-gray-100 bg-gray-50/60 flex flex-col sm:flex-row items-center justify-between gap-3">
                            <p className="text-xs text-gray-500">Cochez la case d'acceptation après avoir lu les termes.</p>
                            <div className="flex items-center gap-2">
                                <Button variant="secondary" size="md" onClick={() => setShowTermes(false)}>Fermer</Button>
                                <Button variant="primary" size="md" onClick={() => { setAccepteTermes(true); setShowTermes(false); }}>
                                    <CheckCircleIcon className="w-4 h-4" /> J'accepte
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

function Article({ num, titre, children }) {
    return (
        <section>
            <h4 className="text-base font-bold text-gray-900 mb-1.5 flex items-center gap-2">
                <span className="inline-flex items-center justify-center w-6 h-6 rounded-md bg-blue-100 text-blue-700 text-xs font-bold">{num}</span>
                {titre}
            </h4>
            <p className="pl-8">{children}</p>
        </section>
    );
}
