/**
 * Page PlanActionDetail — Detail d'un plan avec kanban des items.
 *
 * - ASC (consultant) : peut ajouter/modifier/supprimer des items, cloturer
 * - Client/client_admin : peut accepter le plan, mettre a jour le statut des items,
 *   ajouter des notes
 */

import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
    getPlanAction, accepterPlanAction, cloturerPlanAction, supprimerPlanAction,
    ajouterItem, majItem, supprimerItem,
    soumettrePlanAction, rouvrirPlanAction,
    uploadPreuveItem, supprimerPreuveItem, telechargerPreuveItem,
    PRIORITE_LABEL, STATUT_ITEM, STATUT_PLAN, VERDICT_LABEL,
} from '@/api/plansActions';
import { alertSuccess, alertError, confirmAction, confirmDelete } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import StatCard from '@/components/ui/StatCard';
import EmptyState from '@/components/ui/EmptyState';
import Loader from '@/components/ui/Loader';
import { Input, Select, Textarea } from '@/components/ui/Input';
import Modal from '@/components/ui/Modal';
import Drawer from '@/components/ui/Drawer';
import {
    ArrowLeftIcon, CheckCircleIcon, ArchiveBoxIcon, TrashIcon,
    PlusIcon, XMarkIcon, FlagIcon, ChartBarIcon, ClipboardDocumentListIcon,
    UserIcon, CalendarDaysIcon, PaperAirplaneIcon, LockOpenIcon,
    PaperClipIcon, CloudArrowUpIcon, ArrowDownTrayIcon, DocumentIcon,
    SparklesIcon, MinusCircleIcon,
} from '@heroicons/react/24/outline';

const COLONNES = [
    { key: 'a_faire', label: 'À faire', bg: 'bg-gray-50', header: 'bg-gray-100 text-gray-700' },
    { key: 'en_cours', label: 'En cours', bg: 'bg-blue-50', header: 'bg-blue-100 text-blue-700' },
    { key: 'bloque', label: 'Bloqué', bg: 'bg-red-50', header: 'bg-red-100 text-red-700' },
    { key: 'termine', label: 'Terminé', bg: 'bg-emerald-50', header: 'bg-emerald-100 text-emerald-700' },
];

export default function PlanActionDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [itemActif, setItemActif] = useState(null);
    const [showAjout, setShowAjout] = useState(false);
    // Recharge silencieuse (sans loader plein ecran) — pour le polling pendant verification.
    const rechargeSilencieuseRef = useRef(false);

    const charger = async (silencieux = false) => {
        if (!silencieux) setLoading(true);
        try {
            const r = await getPlanAction(id);
            setData(r);
            // Synchronise l'item actif (pour mettre a jour preuves + verdict en temps reel)
            if (itemActif) {
                const rafraichi = r.plan.items?.find(i => i.id === itemActif.id);
                if (rafraichi) setItemActif(rafraichi);
            }
        } catch { if (!silencieux) alertError('Impossible de charger le plan'); }
        finally { if (!silencieux) setLoading(false); }
    };

    useEffect(() => { charger(); }, [id]);

    // Polling pendant la verification LLM (toutes les 4s tant que le job tourne).
    // Une fois termine, le polling s'arrete automatiquement.
    useEffect(() => {
        const statut = data?.plan?.verification_statut;
        if (statut !== 'en_attente' && statut !== 'en_cours') return;
        rechargeSilencieuseRef.current = true;
        const interval = setInterval(() => charger(true), 4000);
        return () => clearInterval(interval);
    }, [data?.plan?.verification_statut]);

    const handleAccepter = async () => {
        if (!(await confirmAction('Accepter ce plan d\'actions ? Vous pourrez commencer à marquer les actions comme réalisées.', 'Acceptation'))) return;
        try {
            await accepterPlanAction(id);
            alertSuccess('Plan accepté');
            charger();
        } catch (err) { alertError(err.response?.data?.message || 'Erreur'); }
    };

    const handleCloturer = async () => {
        if (!(await confirmAction('Clôturer ce plan ? Les actions ne pourront plus être modifiées.', 'Clôture'))) return;
        try {
            await cloturerPlanAction(id, 'Plan clôturé');
            alertSuccess('Plan clôturé');
            charger();
        } catch (err) { alertError(err.response?.data?.message || 'Erreur'); }
    };

    const handleSupprimer = async () => {
        if (!(await confirmDelete(data.plan.reference))) return;
        try {
            await supprimerPlanAction(id);
            alertSuccess('Plan supprimé');
            navigate('/plans-actions');
        } catch (err) { alertError(err.response?.data?.message || 'Erreur'); }
    };

    const handleChangerStatutItem = async (item, nouveauStatut) => {
        try {
            await majItem(id, item.id, { statut: nouveauStatut });
            charger();
        } catch (err) { alertError(err.response?.data?.message || 'Erreur'); }
    };

    const handleSoumettre = async () => {
        const ok = await confirmAction(
            'Le système va comparer chaque preuve aux recommandations associées et produire un verdict. Cette opération peut prendre plusieurs minutes. Le consultant sera notifié à la fin.',
            'Soumettre au consultant'
        );
        if (!ok) return;
        try {
            await soumettrePlanAction(id);
            alertSuccess('Plan soumis. Vérification en cours en arrière-plan.');
            charger();
        } catch (err) { alertError(err.response?.data?.message || 'Erreur'); }
    };

    const handleRouvrir = async () => {
        const ok = await confirmAction(
            'Rouvrir le plan permettra au client de modifier ses preuves et de soumettre à nouveau. Les verdicts actuels seront effacés.',
            'Rouvrir le plan'
        );
        if (!ok) return;
        try {
            await rouvrirPlanAction(id);
            alertSuccess('Plan rouvert');
            charger();
        } catch (err) { alertError(err.response?.data?.message || 'Erreur'); }
    };

    if (loading) return <div className="p-8 flex justify-center"><div className="w-10 h-10 rounded-full border-4 border-blue-600 border-t-transparent animate-spin" /></div>;
    if (!data) {
        return (
            <div className="p-8 max-w-7xl mx-auto">
                <EmptyState icon={FlagIcon} title="Plan introuvable" description="Ce plan d'actions n'existe pas ou vous n'y avez pas accès." accent="rose">
                    <button onClick={() => navigate('/plans-actions')}><Button as="span" variant="primary">Retour aux plans</Button></button>
                </EmptyState>
            </div>
        );
    }

    const { plan, progression, peut_modifier, peut_accepter, peut_cloturer, peut_mettre_a_jour_items, peut_supprimer, peut_soumettre, peut_rouvrir } = data;
    const statutCfg = STATUT_PLAN[plan.statut] || {};
    const verifEnCours = plan.verification_statut === 'en_attente' || plan.verification_statut === 'en_cours';
    const verifTerminee = plan.verification_statut === 'terminee';

    const verdictsCount = (plan.items || []).reduce((acc, it) => {
        const v = it.verdict_correction || 'aucun';
        acc[v] = (acc[v] || 0) + 1;
        return acc;
    }, {});

    const itemsParStatut = COLONNES.reduce((acc, col) => {
        acc[col.key] = (plan.items || []).filter(i => i.statut === col.key);
        return acc;
    }, {});

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <button onClick={() => navigate('/plans-actions')} className="inline-flex items-center gap-1.5 text-sm font-medium text-blue-700 hover:text-blue-900 mb-4 transition-colors">
                <ArrowLeftIcon className="w-4 h-4" /> Retour aux plans
            </button>

            <PageHeader
                title={plan.titre}
                subtitle={`${plan.reference}${plan.client?.raison_sociale ? ' · ' + plan.client.raison_sociale : ''}`}
                eyebrow="Pilotage conformité"
                icon={FlagIcon}
                accent="amber"
            >
                <div className="flex items-center gap-2 flex-wrap">
                    <Badge variant={statutCfg.color || 'gray'} solid size="md" dot>{statutCfg.label || plan.statut}</Badge>
                    {plan.soumis_le && !verifEnCours && (
                        <Badge variant="info" dot>Soumis le {new Date(plan.soumis_le).toLocaleDateString('fr-FR')}</Badge>
                    )}
                    {peut_accepter && (
                        <Button variant="success" size="sm" onClick={handleAccepter}>
                            <CheckCircleIcon className="w-4 h-4" /> Accepter
                        </Button>
                    )}
                    {peut_soumettre && !verifEnCours && (
                        <Button variant="primary" size="sm" onClick={handleSoumettre} title="Le système comparera chaque preuve à la recommandation associée">
                            <PaperAirplaneIcon className="w-4 h-4" /> Soumettre au consultant
                        </Button>
                    )}
                    {peut_rouvrir && (
                        <Button variant="secondary" size="sm" onClick={handleRouvrir} title="Permettre au client de modifier les preuves">
                            <LockOpenIcon className="w-4 h-4" /> Rouvrir
                        </Button>
                    )}
                    {peut_cloturer && plan.statut !== 'cloture' && (
                        <Button variant="secondary" size="sm" onClick={handleCloturer}>
                            <ArchiveBoxIcon className="w-4 h-4" /> Clôturer
                        </Button>
                    )}
                    {peut_supprimer && (
                        <Button variant="danger" size="sm" onClick={handleSupprimer}>
                            <TrashIcon className="w-4 h-4" /> Supprimer
                        </Button>
                    )}
                </div>
            </PageHeader>

            {/* Bandeau verification : en cours ou termine */}
            {(verifEnCours || verifTerminee) && (
                <Card variant="elevated" className={`p-4 mb-6 ${verifEnCours ? 'border-blue-200 bg-blue-50' : 'border-emerald-200 bg-emerald-50'}`}>
                    <div className="flex items-start gap-3">
                        <SparklesIcon className={`w-6 h-6 shrink-0 mt-0.5 ${verifEnCours ? 'text-blue-600' : 'text-emerald-600'}`} />
                        <div className="flex-1 min-w-0">
                            {verifEnCours ? (
                                <>
                                    <p className="font-semibold text-blue-900 text-sm">Vérification des preuves en cours…</p>
                                    <p className="text-xs text-blue-700 mt-0.5">Le système compare chaque preuve à la recommandation associée. Cette page se met à jour automatiquement.</p>
                                    <div className="mt-2 w-full bg-blue-100 rounded-full h-2 overflow-hidden">
                                        <div className="bg-blue-600 h-2 transition-all duration-500" style={{ width: `${plan.verification_progression_pct || 0}%` }} />
                                    </div>
                                    <p className="text-[11px] text-blue-700 mt-1">{plan.verification_progression_pct || 0}%</p>
                                </>
                            ) : (
                                <>
                                    <p className="font-semibold text-emerald-900 text-sm">Vérification terminée</p>
                                    <p className="text-xs text-emerald-800 mt-0.5">
                                        {Object.entries(VERDICT_LABEL).filter(([k]) => verdictsCount[k]).map(([k, v]) => `${verdictsCount[k]} ${v.label.toLowerCase()}`).join(' · ') || 'Aucun verdict'}
                                    </p>
                                </>
                            )}
                        </div>
                    </div>
                </Card>
            )}

            {/* KPI */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <StatCard titre="Progression" valeur={`${progression}%`} icon={ChartBarIcon} couleur={progression === 100 ? 'emerald' : 'amber'} soustitre="Avancement global" />
                <StatCard titre="Actions" valeur={plan.items?.length || 0} icon={ClipboardDocumentListIcon} couleur="blue" soustitre="Total du plan" />
                <StatCard titre="Proposé par" valeur={plan.proposeur ? `${plan.proposeur.prenom?.charAt(0) || ''}. ${plan.proposeur.nom || ''}`.trim() : '—'} icon={UserIcon} couleur="indigo" soustitre={plan.proposeur ? `${plan.proposeur.prenom || ''} ${plan.proposeur.nom || ''}`.trim() : ''} />
                <StatCard titre="Échéance" valeur={plan.date_fin_prevue ? new Date(plan.date_fin_prevue).toLocaleDateString('fr-FR') : '—'} icon={CalendarDaysIcon} couleur="rose" soustitre="Date de fin prévue" />
            </div>

            {plan.objectif && (
                <Card variant="elevated" className="p-5 mb-6">
                    <div className="flex items-start gap-3">
                        <div className="shrink-0 w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center">
                            <FlagIcon className="w-5 h-5 text-white" />
                        </div>
                        <div className="min-w-0">
                            <h3 className="font-bold text-gray-900 text-sm uppercase tracking-wider mb-1">Objectif</h3>
                            <p className="text-sm text-gray-700 leading-relaxed">{plan.objectif}</p>
                        </div>
                    </div>
                </Card>
            )}

            {/* Kanban */}
            <div className="flex items-center justify-between mb-4">
                <h3 className="text-sm font-bold text-gray-900 uppercase tracking-wider">Actions à piloter</h3>
                {peut_modifier && (
                    <Button size="sm" variant="primary" onClick={() => setShowAjout(true)}>
                        <PlusIcon className="w-4 h-4" /> Ajouter une action
                    </Button>
                )}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                {COLONNES.map(col => (
                    <div key={col.key} className={`rounded-2xl ring-1 ring-gray-200/70 overflow-hidden shadow-[0_1px_3px_rgba(15,23,42,0.04)] ${col.bg}`}>
                        <div className={`px-4 py-3 font-bold text-xs uppercase tracking-wider ${col.header} flex items-center justify-between`}>
                            <span>{col.label}</span>
                            <span className="inline-flex items-center justify-center min-w-6 h-6 px-2 rounded-full bg-white/60 text-[11px] font-bold tabular-nums">{itemsParStatut[col.key].length}</span>
                        </div>
                        <div className="p-3 space-y-2.5 min-h-40">
                            {itemsParStatut[col.key].length === 0 ? (
                                <p className="text-xs text-gray-400 italic text-center py-6">Aucune action</p>
                            ) : itemsParStatut[col.key].map(item => (
                                <ItemCard
                                    key={item.id}
                                    item={item}
                                    peutModifierStatut={peut_mettre_a_jour_items}
                                    onOuvrir={() => setItemActif(item)}
                                    onChangerStatut={statut => handleChangerStatutItem(item, statut)}
                                />
                            ))}
                        </div>
                    </div>
                ))}
            </div>

            {showAjout && (
                <ModalAjoutItem
                    planId={id}
                    onClose={() => setShowAjout(false)}
                    onOk={() => { setShowAjout(false); charger(); }}
                />
            )}

            {itemActif && (
                <DrawerItem
                    item={itemActif}
                    planId={id}
                    canEditAsc={peut_modifier}
                    canEditClient={peut_mettre_a_jour_items}
                    planSoumis={!!plan.soumis_le}
                    verifEnCours={verifEnCours}
                    onClose={() => setItemActif(null)}
                    onSaved={() => { setItemActif(null); charger(); }}
                />
            )}
        </div>
    );
}

// ================================================================
// CARTE ITEM
// ================================================================
function ItemCard({ item, peutModifierStatut, onOuvrir, onChangerStatut }) {
    const cfg = PRIORITE_LABEL[item.priorite] || PRIORITE_LABEL.p2;
    const retard = item.echeance && new Date(item.echeance) < new Date() && item.statut !== 'termine';
    const nbPreuves = item.preuves?.length || 0;
    const verdictCfg = item.verdict_correction ? VERDICT_LABEL[item.verdict_correction] : null;

    return (
        <div className="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md transition-shadow cursor-pointer" onClick={onOuvrir}>
            <div className="flex items-center gap-1.5 mb-2 flex-wrap">
                <Badge variant={cfg.color}>{cfg.label}</Badge>
                {retard && <Badge variant="danger">En retard</Badge>}
                {verdictCfg && (
                    <Badge variant={verdictCfg.color} title={item.justification_correction}>
                        {verdictCfg.label}
                    </Badge>
                )}
            </div>
            <p className="text-sm font-medium text-gray-900 line-clamp-2">{item.titre}</p>
            <div className="flex items-center gap-3 mt-2 text-xs text-gray-500">
                {item.echeance && (
                    <span>Échéance : {new Date(item.echeance).toLocaleDateString('fr-FR')}</span>
                )}
                {nbPreuves > 0 && (
                    <span className="inline-flex items-center gap-1 text-blue-700 font-medium">
                        <PaperClipIcon className="w-3.5 h-3.5" />{nbPreuves}
                    </span>
                )}
            </div>
            {peutModifierStatut && (
                <div className="mt-3 pt-3 border-t border-gray-100 flex gap-1 flex-wrap" onClick={e => e.stopPropagation()}>
                    {Object.entries(STATUT_ITEM).filter(([k]) => k !== item.statut).map(([k, v]) => (
                        <button
                            key={k}
                            onClick={() => onChangerStatut(k)}
                            className={`text-[10px] px-2 py-0.5 rounded border hover:bg-gray-50 transition-colors text-gray-600`}
                        >
                            → {v.label}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

// ================================================================
// MODAL AJOUT ITEM (consultant)
// ================================================================
function ModalAjoutItem({ planId, onClose, onOk }) {
    const [form, setForm] = useState({ titre: '', description: '', priorite: 'p2', echeance: '' });
    const [saving, setSaving] = useState(false);

    const submit = async () => {
        if (!form.titre.trim()) return alertError('Titre obligatoire');
        setSaving(true);
        try {
            await ajouterItem(planId, {
                ...form,
                echeance: form.echeance || null,
            });
            alertSuccess('Action ajoutée');
            onOk();
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur');
        } finally { setSaving(false); }
    };

    return (
        <Modal
            open={true}
            onClose={onClose}
            title="Ajouter une action"
            subtitle="Définir une nouvelle tâche du plan d'actions"
            icon={PlusIcon}
            accent="amber"
            size="md"
            footer={(
                <>
                    <Button variant="secondary" onClick={onClose} disabled={saving}>Annuler</Button>
                    <Button variant="primary" onClick={submit} loading={saving}>{saving ? 'Ajout...' : 'Ajouter'}</Button>
                </>
            )}
        >
            <div className="space-y-4">
                <Input label="Titre" required value={form.titre} onChange={e => setForm({...form, titre: e.target.value})} />
                <Textarea label="Description" value={form.description} onChange={e => setForm({...form, description: e.target.value})} rows={3} />
                <div className="grid grid-cols-2 gap-4">
                    <Select label="Priorité" value={form.priorite} onChange={e => setForm({...form, priorite: e.target.value})}>
                        {Object.entries(PRIORITE_LABEL).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
                    </Select>
                    <Input label="Échéance" type="date" value={form.echeance} onChange={e => setForm({...form, echeance: e.target.value})} />
                </div>
            </div>
        </Modal>
    );
}

// ================================================================
// DRAWER DETAIL ITEM
// ================================================================
function DrawerItem({ item, planId, canEditAsc, canEditClient, planSoumis, verifEnCours, onClose, onSaved }) {
    const [notesClient, setNotesClient] = useState(item.notes_client || '');
    const [notesConsultant, setNotesConsultant] = useState(item.notes_consultant || '');
    const [statut, setStatut] = useState(item.statut);
    const [priorite, setPriorite] = useState(item.priorite);
    const [echeance, setEcheance] = useState(item.echeance ? item.echeance.substring(0, 10) : '');
    const [saving, setSaving] = useState(false);
    const [preuves, setPreuves] = useState(item.preuves || []);

    // Resync preuves quand l'item change (apres recharge silencieuse)
    useEffect(() => { setPreuves(item.preuves || []); }, [item.id, item.preuves]);

    // Les preuves sont editables tant que le plan n'est pas soumis ou en cours de verif
    const preuvesEditables = canEditClient && !planSoumis && !verifEnCours;

    const verdictCfg = item.verdict_correction ? VERDICT_LABEL[item.verdict_correction] : null;
    const ecart = item.ecart;

    const submit = async () => {
        setSaving(true);
        try {
            const data = { statut, notes_client: notesClient };
            if (canEditAsc) {
                data.priorite = priorite;
                data.echeance = echeance || null;
                data.notes_consultant = notesConsultant;
            }
            await majItem(planId, item.id, data);
            alertSuccess('Action mise à jour');
            onSaved();
        } catch (err) { alertError(err.response?.data?.message || 'Erreur'); }
        finally { setSaving(false); }
    };

    const supprimer = async () => {
        if (!(await confirmDelete(item.titre))) return;
        try {
            await supprimerItem(planId, item.id);
            alertSuccess('Action supprimée');
            onSaved();
        } catch (err) { alertError(err.response?.data?.message || 'Erreur'); }
    };

    const handleUploadPreuve = async ({ fichier, libelle, description }) => {
        const fd = new FormData();
        fd.append('fichier', fichier);
        fd.append('libelle', libelle);
        if (description) fd.append('description', description);
        const res = await uploadPreuveItem(item.id, fd);
        setPreuves(prev => [res.preuve, ...prev]);
    };

    const handleSupprimerPreuve = async (preuve) => {
        if (!(await confirmAction(`Supprimer la preuve « ${preuve.libelle} » ?`, 'Supprimer la preuve'))) return;
        try {
            await supprimerPreuveItem(preuve.id);
            setPreuves(prev => prev.filter(p => p.id !== preuve.id));
            alertSuccess('Preuve supprimée');
        } catch (err) {
            alertError(err.response?.data?.message || 'Échec de la suppression');
        }
    };

    return (
        <Drawer
            open={true}
            onClose={onClose}
            title={item.titre}
            subtitle={item.statut?.replace(/_/g, ' ')}
            icon={FlagIcon}
            accent="amber"
            size="lg"
            footer={(
                <div className="flex items-center justify-between w-full">
                    {canEditAsc ? (
                        <Button variant="danger" size="sm" onClick={supprimer}>
                            <TrashIcon className="w-4 h-4" /> Supprimer
                        </Button>
                    ) : <div />}
                    <div className="flex gap-2">
                        <Button variant="secondary" onClick={onClose}>Fermer</Button>
                        <Button variant="primary" onClick={submit} loading={saving}>{saving ? 'Enregistrement...' : 'Enregistrer'}</Button>
                    </div>
                </div>
            )}
        >
            <div className="space-y-5">
                {item.description && (
                    <div>
                        <h4 className="font-semibold text-gray-900 text-sm mb-2">Description</h4>
                        <p className="text-sm text-gray-700">{item.description}</p>
                    </div>
                )}

                {/* Recommandation issue de l'ecart lie (read-only) — sert de reference au client
                    pour savoir quelle preuve il doit fournir. */}
                {ecart?.recommandation && (
                    <div className="border-l-4 border-emerald-400 bg-emerald-50 p-3 rounded-r-lg">
                        <h4 className="font-semibold text-emerald-900 text-xs uppercase tracking-wider mb-1">Recommandation à satisfaire</h4>
                        <p className="text-sm text-emerald-900 leading-relaxed whitespace-pre-wrap">{ecart.recommandation}</p>
                    </div>
                )}

                {/* Verdict du LLM apres soumission */}
                {verdictCfg && (
                    <VerdictBlock verdict={item.verdict_correction} justification={item.justification_correction} verifieLe={item.verifie_le} />
                )}

                <div className="grid grid-cols-2 gap-4">
                    <Select label="Statut" value={statut} onChange={e => setStatut(e.target.value)} disabled={!canEditClient}>
                        {Object.entries(STATUT_ITEM).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
                    </Select>
                    <Select label="Priorité" value={priorite} onChange={e => setPriorite(e.target.value)} disabled={!canEditAsc}>
                        {Object.entries(PRIORITE_LABEL).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
                    </Select>
                </div>

                <Input label="Échéance" type="date" value={echeance} onChange={e => setEcheance(e.target.value)} disabled={!canEditAsc} />

                <Textarea label="Notes client (votre avancement)" value={notesClient} onChange={e => setNotesClient(e.target.value)} rows={3} disabled={!canEditClient} />
                {canEditAsc && (
                    <Textarea label="Notes consultant (interne ASC)" value={notesConsultant} onChange={e => setNotesConsultant(e.target.value)} rows={3} />
                )}

                {/* Preuves : upload / liste / suppression. Lecture seule si plan soumis. */}
                <PreuvesItemSection
                    preuves={preuves}
                    editable={preuvesEditables}
                    onUpload={handleUploadPreuve}
                    onSupprimer={handleSupprimerPreuve}
                />
            </div>
        </Drawer>
    );
}

// ================================================================
// VERDICT BLOCK
// ================================================================
function VerdictBlock({ verdict, justification, verifieLe }) {
    const cfg = VERDICT_LABEL[verdict] || VERDICT_LABEL.non_evalue;
    const tone = {
        conforme: 'border-emerald-300 bg-emerald-50 text-emerald-900',
        partielle: 'border-amber-300 bg-amber-50 text-amber-900',
        non_conforme: 'border-rose-300 bg-rose-50 text-rose-900',
        non_evalue: 'border-gray-300 bg-gray-50 text-gray-700',
    }[verdict] || 'border-gray-300 bg-gray-50 text-gray-700';

    const Icon = verdict === 'conforme' ? CheckCircleIcon
        : verdict === 'non_conforme' ? XMarkIcon
        : verdict === 'partielle' ? MinusCircleIcon
        : SparklesIcon;

    return (
        <div className={`border-l-4 ${tone} p-3 rounded-r-lg`}>
            <div className="flex items-center gap-2 mb-1">
                <Icon className="w-5 h-5 shrink-0" />
                <h4 className="font-semibold text-xs uppercase tracking-wider">Verdict IA : {cfg.label}</h4>
            </div>
            <p className="text-sm leading-relaxed whitespace-pre-wrap">{justification || 'Aucune justification fournie.'}</p>
            {verifieLe && (
                <p className="text-[11px] opacity-70 mt-2">Évalué le {new Date(verifieLe).toLocaleString('fr-FR')}</p>
            )}
        </div>
    );
}

// ================================================================
// PREUVES (uploads sur item)
// ================================================================
function PreuvesItemSection({ preuves, editable, onUpload, onSupprimer }) {
    const [drag, setDrag] = useState(false);
    const [pending, setPending] = useState(null);
    const [uploading, setUploading] = useState(false);

    const onFichier = (fichier) => {
        if (!fichier) return;
        setPending({ file: fichier, libelle: fichier.name.replace(/\.[^.]+$/, ''), description: '' });
    };

    const confirmerUpload = async () => {
        if (!pending) return;
        setUploading(true);
        try {
            await onUpload({ fichier: pending.file, libelle: pending.libelle.trim() || pending.file.name, description: pending.description });
            alertSuccess('Preuve ajoutée');
            setPending(null);
        } catch (err) {
            alertError(err.response?.data?.message || 'Échec de l\'upload');
        } finally {
            setUploading(false);
        }
    };

    const handleTelecharger = async (preuve) => {
        try {
            await telechargerPreuveItem(preuve);
        } catch {
            alertError('Échec du téléchargement');
        }
    };

    return (
        <div className="pt-4 border-t border-gray-100">
            <h4 className="font-semibold text-gray-900 text-sm mb-3 flex items-center gap-2">
                <PaperClipIcon className="w-4 h-4 text-gray-500" />
                Preuves justificatives
                <span className="text-xs font-normal text-gray-400">({preuves.length})</span>
            </h4>

            {preuves.length > 0 && (
                <div className="space-y-2 mb-3">
                    {preuves.map(p => (
                        <div key={p.id} className="flex items-center gap-3 p-3 bg-gray-50 border border-gray-200 rounded-lg">
                            <DocumentIcon className="w-5 h-5 text-blue-600 shrink-0" />
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-medium text-gray-900 truncate">{p.libelle}</p>
                                <p className="text-xs text-gray-500 truncate">
                                    {p.nom_fichier_original} · {(p.taille_octets / 1024).toFixed(0)} Ko
                                    {p.uploadeur && ` · ${p.uploadeur.prenom || ''} ${p.uploadeur.nom || ''}`.trim()}
                                </p>
                                {p.description && <p className="text-xs text-gray-600 mt-1">{p.description}</p>}
                            </div>
                            <button type="button" onClick={() => handleTelecharger(p)} className="p-1.5 text-blue-600 hover:bg-blue-50 rounded" title="Télécharger">
                                <ArrowDownTrayIcon className="w-4 h-4" />
                            </button>
                            {editable && (
                                <button type="button" onClick={() => onSupprimer(p)} className="p-1.5 text-red-500 hover:bg-red-50 rounded" title="Supprimer">
                                    <TrashIcon className="w-4 h-4" />
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {editable && !pending && (
                <div
                    onDragOver={e => { e.preventDefault(); setDrag(true); }}
                    onDragLeave={() => setDrag(false)}
                    onDrop={e => { e.preventDefault(); setDrag(false); onFichier(e.dataTransfer.files?.[0]); }}
                    className={`relative border-2 border-dashed rounded-lg p-4 text-center transition-all cursor-pointer ${drag ? 'border-blue-400 bg-blue-50' : 'border-gray-300 hover:border-blue-400 hover:bg-gray-50'}`}
                >
                    <input type="file" onChange={e => onFichier(e.target.files?.[0])} className="absolute inset-0 opacity-0 cursor-pointer" />
                    <CloudArrowUpIcon className="w-6 h-6 text-gray-400 mx-auto mb-1" />
                    <p className="text-xs font-medium text-gray-700">Ajouter une preuve</p>
                    <p className="text-[11px] text-gray-400 mt-0.5">PDF, Word, Excel, PowerPoint, image, texte — 25 Mo max</p>
                </div>
            )}

            {editable && pending && (
                <div className="p-3 bg-blue-50 border border-blue-200 rounded-lg space-y-3">
                    <p className="text-xs font-semibold text-blue-900">
                        Fichier : <span className="font-normal">{pending.file.name}</span> ({(pending.file.size / 1024).toFixed(0)} Ko)
                    </p>
                    <input
                        type="text"
                        value={pending.libelle}
                        onChange={e => setPending(p => ({ ...p, libelle: e.target.value }))}
                        placeholder="Libellé de la preuve *"
                        className="w-full text-sm px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <textarea
                        value={pending.description}
                        onChange={e => setPending(p => ({ ...p, description: e.target.value }))}
                        placeholder="Description (optionnel)"
                        rows={2}
                        className="w-full text-sm px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <div className="flex justify-end gap-2">
                        <Button variant="secondary" size="sm" onClick={() => setPending(null)} disabled={uploading}>Annuler</Button>
                        <Button variant="primary" size="sm" onClick={confirmerUpload} loading={uploading} disabled={!pending.libelle.trim()}>
                            {uploading ? 'Upload...' : 'Ajouter'}
                        </Button>
                    </div>
                </div>
            )}

            {!editable && preuves.length === 0 && (
                <p className="text-xs text-gray-400 italic">Aucune preuve. Le plan est en cours de revue ou pas encore modifiable.</p>
            )}
        </div>
    );
}
