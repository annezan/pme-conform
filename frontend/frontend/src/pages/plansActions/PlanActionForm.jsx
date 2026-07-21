/**
 * Page PlanActionForm — Creation d'un plan d'action par un consultant ASC.
 * Le client est selectionnable ; des items initiaux peuvent etre ajoutes.
 */

import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '@/api/client';
import { creerPlanAction, PRIORITE_LABEL } from '@/api/plansActions';
import { getAnalyse } from '@/api/analyses';
import { alertSuccess, alertError } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import { Input, Select, Textarea } from '@/components/ui/Input';
import { ArrowLeftIcon, PlusIcon, TrashIcon, FlagIcon, SparklesIcon } from '@heroicons/react/24/outline';

// Mappe la gravite d'un ecart sur la priorite de l'action correspondante.
const PRIORITE_PAR_GRAVITE = {
    critique: 'p1',
    majeur: 'p2',
    mineur: 'p3',
    observation: 'p4',
};

// Echeance suggeree (en jours) selon la priorite, alignee avec PRIORITE_LABEL.
const DELAI_PAR_PRIORITE = { p1: 30, p2: 90, p3: 180, p4: 365 };

function nettoyer(txt) {
    return String(txt || '')
        .replace(/&amp;/g, '&').replace(/&#039;/g, "'").replace(/&quot;/g, '"')
        .replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&nbsp;/g, ' ')
        .replace(/[ \t ]+/g, ' ').trim();
}

function dateEcheance(priorite) {
    const d = new Date();
    d.setDate(d.getDate() + (DELAI_PAR_PRIORITE[priorite] || 90));
    return d.toISOString().slice(0, 10);
}

export default function PlanActionForm() {
    const navigate = useNavigate();
    const [clients, setClients] = useState([]);
    const [analyses, setAnalyses] = useState([]);
    const [form, setForm] = useState({
        client_id: '',
        analyse_id: '',
        titre: '',
        objectif: '',
        date_debut_prevue: '',
        date_fin_prevue: '',
    });
    const [items, setItems] = useState([]);
    const [saving, setSaving] = useState(false);
    const [generation, setGeneration] = useState(false);

    useEffect(() => {
        api.get('/clients?per_page=200').then(r => setClients(r.data.data || [])).catch(() => {});
    }, []);

    useEffect(() => {
        if (form.client_id) {
            api.get(`/analyses?client_id=${form.client_id}&per_page=50`)
                .then(r => setAnalyses((r.data.data || []).filter(a => a.statut === 'terminee')))
                .catch(() => setAnalyses([]));
        } else {
            setAnalyses([]);
        }
    }, [form.client_id]);

    const ajouterItem = () => {
        setItems(prev => [...prev, { titre: '', description: '', priorite: 'p2', echeance: '' }]);
    };

    // Genere automatiquement les actions a partir des ecarts de l'analyse selectionnee.
    // Chaque ecart non resolu devient une action ; gravite -> priorite ; recommandation
    // (si presente) est reportee en description, sinon le constat. Les actions deja
    // generees pour un ecart (par ecart_id) ne sont pas recreees.
    const genererDepuisEcarts = async () => {
        if (!form.analyse_id) {
            alertError('Sélectionnez d\'abord une analyse d\'écarts.');
            return;
        }
        setGeneration(true);
        try {
            const r = await getAnalyse(form.analyse_id);
            const ecarts = (r.analyse?.ecarts || []).filter(e =>
                e.statut_correction !== 'traite' && e.statut_correction !== 'accepte_par_client'
            );
            if (ecarts.length === 0) {
                alertError('Aucun écart à traiter sur cette analyse.');
                return;
            }
            const existants = new Set(items.map(i => i.ecart_id).filter(Boolean));
            const nouveaux = ecarts
                .filter(e => !existants.has(e.id))
                .map(e => {
                    const priorite = PRIORITE_PAR_GRAVITE[e.gravite] || 'p2';
                    const recommandation = nettoyer(e.recommandation);
                    const constat = nettoyer(e.description_ecart);
                    const ref = e.referentiel?.code ? `[${e.referentiel.code}${e.article_reference ? ' · ' + e.article_reference : ''}] ` : '';
                    return {
                        ecart_id: e.id,
                        titre: ref + nettoyer(e.titre),
                        description: recommandation || constat,
                        priorite,
                        echeance: dateEcheance(priorite),
                        gravite: e.gravite,
                    };
                });
            if (nouveaux.length === 0) {
                alertSuccess('Toutes les actions sont déjà générées.');
                return;
            }
            setItems(prev => [...prev, ...nouveaux]);
            alertSuccess(`${nouveaux.length} action(s) générée(s) à partir des écarts.`);
        } catch (err) {
            alertError(err.response?.data?.message || 'Échec de la génération');
        } finally {
            setGeneration(false);
        }
    };

    const majItem = (index, patch) => {
        setItems(prev => prev.map((it, i) => i === index ? { ...it, ...patch } : it));
    };

    const retirerItem = (index) => {
        setItems(prev => prev.filter((_, i) => i !== index));
    };

    const submit = async () => {
        if (!form.client_id || !form.titre.trim()) {
            alertError('Client et titre obligatoires.');
            return;
        }
        setSaving(true);
        try {
            const payload = {
                ...form,
                client_id: parseInt(form.client_id),
                analyse_id: form.analyse_id ? parseInt(form.analyse_id) : null,
                date_debut_prevue: form.date_debut_prevue || null,
                date_fin_prevue: form.date_fin_prevue || null,
                items: items.filter(i => i.titre.trim()).map(i => ({
                    titre: i.titre,
                    description: i.description,
                    priorite: i.priorite,
                    echeance: i.echeance || null,
                    ecart_id: i.ecart_id ?? null,
                })),
            };
            const r = await creerPlanAction(payload);
            alertSuccess('Plan créé et proposé au client');
            navigate(`/plans-actions/${r.plan.id}`);
        } catch (err) {
            alertError(err.response?.data?.errors
                ? Object.values(err.response.data.errors).flat().join(' ')
                : (err.response?.data?.message || 'Erreur'));
        } finally { setSaving(false); }
    };

    return (
        <div className="p-6 lg:p-8 max-w-4xl mx-auto">
            <button onClick={() => navigate('/plans-actions')} className="text-sm text-gray-500 hover:text-gray-700 mb-4 flex items-center gap-1">
                <ArrowLeftIcon className="w-4 h-4" /> Retour aux plans
            </button>

            <PageHeader title="Nouveau plan d'action" subtitle="Proposez un plan au client après une analyse d'écarts" />

            <Card className="p-8 space-y-5">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <Select label="Client *" value={form.client_id} onChange={e => setForm({...form, client_id: e.target.value, analyse_id: ''})}>
                        <option value="">-- Sélectionner --</option>
                        {clients.map(c => <option key={c.id} value={c.id}>{c.raison_sociale}</option>)}
                    </Select>
                    <Select label="Analyse d'écarts associée (optionnel)" value={form.analyse_id} onChange={e => setForm({...form, analyse_id: e.target.value})}>
                        <option value="">Aucune analyse liée</option>
                        {analyses.map(a => <option key={a.id} value={a.id}>{a.reference} - Score {a.score_conformite}%</option>)}
                    </Select>
                </div>

                <Input label="Titre du plan *" value={form.titre} onChange={e => setForm({...form, titre: e.target.value})} placeholder="Ex: Plan de mise en conformité ARTCI 2026" />
                <Textarea label="Objectif" value={form.objectif} onChange={e => setForm({...form, objectif: e.target.value})} rows={3} placeholder="Ex: Résoudre les 7 écarts critiques détectés et obtenir la certification ARTCI d'ici fin 2026." />

                <div className="grid grid-cols-2 gap-4">
                    <Input label="Date début prévue" type="date" value={form.date_debut_prevue} onChange={e => setForm({...form, date_debut_prevue: e.target.value})} />
                    <Input label="Date fin prévue" type="date" value={form.date_fin_prevue} onChange={e => setForm({...form, date_fin_prevue: e.target.value})} />
                </div>

                <div className="pt-4 border-t border-gray-100">
                    <div className="flex items-center justify-between mb-3 flex-wrap gap-2">
                        <h3 className="font-semibold text-gray-900 flex items-center gap-2">
                            <FlagIcon className="w-5 h-5 text-gray-500" />
                            Actions initiales ({items.length})
                        </h3>
                        <div className="flex items-center gap-2">
                            {form.analyse_id && (
                                <Button
                                    size="sm"
                                    variant="primary"
                                    onClick={genererDepuisEcarts}
                                    disabled={generation}
                                    title="Générer une action par écart non résolu, avec priorité calée sur la gravité"
                                >
                                    <SparklesIcon className="w-4 h-4" />
                                    {generation ? 'Génération...' : 'Générer depuis les écarts'}
                                </Button>
                            )}
                            <Button size="sm" variant="secondary" onClick={ajouterItem}>
                                <PlusIcon className="w-4 h-4" /> Ajouter une action
                            </Button>
                        </div>
                    </div>

                    {form.analyse_id && items.length === 0 && (
                        <div className="mb-3 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-900">
                            <SparklesIcon className="w-4 h-4 inline mr-1" />
                            Astuce : cliquez sur « Générer depuis les écarts » pour créer automatiquement une action par écart détecté
                            (priorité P1 pour les écarts critiques, P2 majeurs, P3 mineurs, P4 observations).
                        </div>
                    )}

                    {items.length === 0 ? (
                        <p className="text-sm text-gray-400 italic text-center py-6">Aucune action initiale. Vous pourrez en ajouter après la création.</p>
                    ) : (
                        <div className="space-y-3">
                            {items.map((item, idx) => (
                                <Card key={idx} className={`p-4 ${item.ecart_id ? 'bg-purple-50 border-purple-200' : 'bg-gray-50 border-gray-200'}`}>
                                    <div className="flex items-start gap-3">
                                        <div className="flex-1 space-y-3">
                                            {item.ecart_id && (
                                                <div className="flex items-center gap-2 text-xs font-semibold text-purple-700">
                                                    <SparklesIcon className="w-3.5 h-3.5" />
                                                    Généré depuis l'écart #{item.ecart_id}
                                                    {item.gravite && <span className="px-2 py-0.5 rounded bg-purple-100 text-purple-800 uppercase tracking-wide">{item.gravite}</span>}
                                                </div>
                                            )}
                                            <Input placeholder="Titre de l'action *" value={item.titre} onChange={e => majItem(idx, { titre: e.target.value })} />
                                            <Textarea placeholder="Description (optionnel)" value={item.description} onChange={e => majItem(idx, { description: e.target.value })} rows={2} />
                                            <div className="grid grid-cols-2 gap-3">
                                                <Select value={item.priorite} onChange={e => majItem(idx, { priorite: e.target.value })}>
                                                    {Object.entries(PRIORITE_LABEL).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
                                                </Select>
                                                <Input type="date" placeholder="Échéance" value={item.echeance} onChange={e => majItem(idx, { echeance: e.target.value })} />
                                            </div>
                                        </div>
                                        <button onClick={() => retirerItem(idx)} className="text-red-500 hover:text-red-700 p-1">
                                            <TrashIcon className="w-4 h-4" />
                                        </button>
                                    </div>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>

                <div className="flex justify-end gap-2 pt-4 border-t border-gray-100">
                    <Button variant="secondary" onClick={() => navigate('/plans-actions')} disabled={saving}>Annuler</Button>
                    <Button onClick={submit} disabled={saving}>
                        {saving ? 'Création...' : 'Créer et proposer au client'}
                    </Button>
                </div>
            </Card>
        </div>
    );
}
