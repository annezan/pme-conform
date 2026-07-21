/**
 * Page TraitementsList — Liste des fiches de traitement.
 *
 * Visible pour :
 *   - client/client_admin : leurs propres traitements
 *   - consultant : traitements de ses clients
 *   - admin/manager : tous les traitements
 */

import { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/contexts/AuthContext';
import { listTraitements, supprimerTraitement, creerTraitementsDepuisQuestionnaires } from '@/api/traitements';
import { genererRegistre, telechargerRegistre } from '@/api/registreKyc';
import api from '@/api/client';
import { alertSuccess, alertError, confirmDelete, confirmAction } from '@/utils/alerts';
import DataTableWrapper from '@/components/ui/DataTableWrapper';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import { Select, Input } from '@/components/ui/Input';
import StatCard from '@/components/ui/StatCard';
import TableActions, { ViewAction, DeleteAction } from '@/components/ui/TableActions';
import Modal from '@/components/ui/Modal';
import {
    PlusIcon, ClipboardDocumentListIcon, CheckCircleIcon, ClockIcon, MagnifyingGlassIcon,
    DocumentArrowDownIcon, ShieldExclamationIcon, GlobeAltIcon, BuildingOffice2Icon,
    SparklesIcon, ExclamationTriangleIcon, ChevronDownIcon, ChevronRightIcon,
} from '@heroicons/react/24/outline';

const statutVariant = { brouillon: 'gray', valide: 'success', archive: 'purple' };
const statutLabel = { brouillon: 'Brouillon', valide: 'Validé', archive: 'Archivé' };

export default function TraitementsList() {
    const navigate = useNavigate();
    const { hasPermission } = useAuth();
    // "estInterne" = utilisateur cote ASC (admin/manager/consultant). Detection par
    // permission view-portefeuille : presente chez les internes, absente chez les clients.
    const estInterne = hasPermission('view-portefeuille');
    const peutCreer = hasPermission('create-traitements');

    const [traitements, setTraitements] = useState([]);
    const [loading, setLoading] = useState(true);
    const [filtreStatut, setFiltreStatut] = useState('');
    const [filtreClient, setFiltreClient] = useState('');
    const [filterText, setFilterText] = useState('');
    const [clients, setClients] = useState([]);
    const [generation, setGeneration] = useState(false);
    const [showAutoModal, setShowAutoModal] = useState(false);
    const [autoClientId, setAutoClientId] = useState('');
    const [autoLoading, setAutoLoading] = useState(false);
    const [autoResult, setAutoResult] = useState(null);
    const [groupesOuverts, setGroupesOuverts] = useState({});

    const charger = async () => {
        setLoading(true);
        try {
            // On charge TOUS les traitements (per_page = 200) du client choisi
            // SANS filtrer par statut — le filtrage statut est fait cote front
            // pour que les stats (Total/Brouillons/Validés/Sensibles/Hors CEDEAO)
            // restent coherentes meme quand un filtre est actif.
            const params = { per_page: 200 };
            if (filtreClient) params.client_id = filtreClient;
            const r = await listTraitements(params);
            setTraitements(r.data || []);
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur de chargement');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { charger(); }, [filtreClient]);

    // Liste des clients pour le filtre (ASC uniquement — le client lui-meme
    // n'a qu'un client, son filtre est superflu).
    useEffect(() => {
        if (estInterne) {
            api.get('/clients?per_page=200').then(r => setClients(r.data.data || [])).catch(() => {});
        }
    }, [estInterne]);

    // Choisit le client cible pour la generation du registre :
    // - si un filtre client est actif, on l'utilise
    // - sinon, si tous les traitements partagent le meme client, on l'utilise
    // - sinon, on demande a l'utilisateur (premier client present)
    const clientCibleRegistre = useMemo(() => {
        if (filtreClient) return parseInt(filtreClient, 10);
        const ids = [...new Set(traitements.map(t => t.client?.id).filter(Boolean))];
        if (ids.length === 1) return ids[0];
        return null;
    }, [filtreClient, traitements]);

    const handleGenererRegistre = async () => {
        if (!clientCibleRegistre) {
            alertError('Choisissez d\'abord un client dans le filtre pour générer son registre.');
            return;
        }
        const nbValides = traitements.filter(t => t.statut === 'valide' && t.client?.id === clientCibleRegistre).length;
        if (nbValides === 0) {
            alertError('Aucun traitement « validé » pour ce client. Validez au moins une fiche avant de générer le registre.');
            return;
        }
        const ok = await confirmAction(
            `Générer le registre MOBISOFT à partir de ${nbValides} traitement(s) validé(s) ?`,
            'Génération du registre'
        );
        if (!ok) return;
        setGeneration(true);
        try {
            const r = await genererRegistre(clientCibleRegistre);
            alertSuccess(`Registre ${r.registre.reference} généré (${r.registre.nb_traitements} fiches).`);
            await telechargerRegistre(r.registre);
        } catch (err) {
            alertError(err.response?.data?.message || 'Échec de la génération du registre');
        } finally {
            setGeneration(false);
        }
    };

    const ouvrirAutoModal = () => {
        // Pre-selection du client : filtre actif > unique client de la liste > vide
        setAutoClientId(filtreClient || (clientCibleRegistre ? String(clientCibleRegistre) : ''));
        setAutoResult(null);
        setShowAutoModal(true);
    };

    const lancerAutoCreation = async () => {
        if (!autoClientId) { alertError('Sélectionnez un client'); return; }
        setAutoLoading(true);
        try {
            const r = await creerTraitementsDepuisQuestionnaires(autoClientId);
            setAutoResult(r);
            if (r.nb_crees > 0) {
                alertSuccess(r.message);
                charger();
            }
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur lors de la génération');
        } finally {
            setAutoLoading(false);
        }
    };

    const handleDelete = async (t) => {
        if (await confirmDelete(`${t.reference} - ${t.designation || t.nom}`)) {
            try {
                await supprimerTraitement(t.id);
                alertSuccess('Traitement supprimé');
                charger();
            } catch (err) {
                alertError(err.response?.data?.message || 'Erreur');
            }
        }
    };

    const stats = useMemo(() => ({
        total: traitements.length,
        brouillon: traitements.filter(t => t.statut === 'brouillon').length,
        valide: traitements.filter(t => t.statut === 'valide').length,
        sensibles: traitements.filter(t => t.contient_donnees_sensibles).length,
        transferts: traitements.filter(t => t.transfert_hors_cedeao).length,
    }), [traitements]);

    const columns = useMemo(() => [
        {
            name: 'Référence',
            width: '120px',
            selector: row => row.reference,
            sortable: true,
            cell: row => <span className="font-mono text-xs font-semibold text-blue-700">{row.reference}</span>,
        },
        {
            name: 'Désignation',
            grow: 3,
            sortable: true,
            selector: row => row.designation,
            cell: row => (
                <div className="py-2 min-w-0">
                    <p className="font-medium text-gray-900 text-sm truncate">{row.designation || row.nom}</p>
                    <div className="flex items-center gap-2 mt-1 flex-wrap">
                        {row.direction_pole && (
                            <span className="text-[11px] text-gray-500 truncate max-w-45">{row.direction_pole}</span>
                        )}
                        {row.contient_donnees_sensibles && (
                            <span className="inline-flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded bg-rose-50 text-rose-700 ring-1 ring-rose-200 uppercase">
                                <ShieldExclamationIcon className="w-3 h-3" />
                                Sensible
                            </span>
                        )}
                        {row.transfert_hors_cedeao && (
                            <span className="inline-flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 ring-1 ring-amber-200 uppercase">
                                <GlobeAltIcon className="w-3 h-3" />
                                Hors CEDEAO
                            </span>
                        )}
                    </div>
                </div>
            ),
        },
        // Colonne entreprise retiree : le nom du client est desormais affiche
        // dans l'entete du groupe parent (regroupement par client).
        {
            name: 'Volumes',
            width: '160px',
            cell: row => (
                <div className="flex items-center gap-2 text-[11px] text-gray-600">
                    <span title="Catégories de données" className="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-blue-50 text-blue-700">
                        {row.categories_donnees_count ?? 0} cat.
                    </span>
                    <span title="Supports" className="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-gray-100 text-gray-700">
                        {row.supports_count ?? 0} sup.
                    </span>
                    <span title="Transferts" className="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-purple-50 text-purple-700">
                        {row.transferts_count ?? 0} tr.
                    </span>
                </div>
            ),
        },
        {
            name: 'Statut',
            width: '110px',
            selector: row => row.statut,
            cell: row => <Badge variant={statutVariant[row.statut]}>{statutLabel[row.statut]}</Badge>,
        },
        {
            name: 'MàJ le',
            width: '100px',
            selector: row => row.updated_at,
            cell: row => <span className="text-xs text-gray-500">{new Date(row.updated_at).toLocaleDateString('fr-FR')}</span>,
        },
        {
            name: 'Actions',
            width: '110px',
            right: true,
            cell: row => (
                <TableActions>
                    <ViewAction onClick={() => navigate(`/traitements/${row.id}`)} />
                    {row.statut === 'brouillon' && <DeleteAction onClick={() => handleDelete(row)} />}
                </TableActions>
            ),
        },
    ].filter(Boolean), [navigate, estInterne]);

    const filtered = traitements.filter(t =>
        (!filtreStatut || t.statut === filtreStatut) &&
        ((t.designation || t.nom || '')?.toLowerCase().includes(filterText.toLowerCase()) ||
        t.reference?.toLowerCase().includes(filterText.toLowerCase()) ||
        (t.description || t.direction_pole || '')?.toLowerCase().includes(filterText.toLowerCase()))
    );

    // Regroupement par client : 1 Card par entreprise, depliable. Ordonnance
    // alphabetique sur la raison sociale ; les items d'un meme client triés
    // par reference (anti-chronologique, le plus recent en haut).
    const traitementsParClient = useMemo(() => {
        const map = new Map();
        for (const t of filtered) {
            const id = t.client?.id || 0;
            const nom = t.client?.raison_sociale || 'Sans entreprise rattachée';
            if (!map.has(id)) map.set(id, { id, raison_sociale: nom, items: [] });
            map.get(id).items.push(t);
        }
        for (const g of map.values()) {
            g.items.sort((a, b) => (b.reference || '').localeCompare(a.reference || ''));
        }
        return Array.from(map.values()).sort((a, b) => a.raison_sociale.localeCompare(b.raison_sociale));
    }, [filtered]);

    // Au premier chargement, on ouvre tous les groupes par defaut.
    useEffect(() => {
        if (traitementsParClient.length > 0 && Object.keys(groupesOuverts).length === 0) {
            const init = {};
            for (const g of traitementsParClient) init[g.id] = true;
            setGroupesOuverts(init);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [traitementsParClient.length]);

    const toggleGroupe = (id) => setGroupesOuverts(o => ({ ...o, [id]: !o[id] }));
    const toutOuvrir = () => setGroupesOuverts(Object.fromEntries(traitementsParClient.map(g => [g.id, true])));
    const toutFermer = () => setGroupesOuverts(Object.fromEntries(traitementsParClient.map(g => [g.id, false])));

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <PageHeader
                title="Traitements de données"
                subtitle="Registre des traitements de données personnelles de votre entreprise"
                eyebrow="Registre MOBISOFT"
                icon={ClipboardDocumentListIcon}
                accent="indigo"
            >
                <div className="flex items-center gap-2 flex-wrap">
                    <Button
                        onClick={handleGenererRegistre}
                        variant="secondary"
                        disabled={generation || stats.valide === 0 || !clientCibleRegistre}
                        title={
                            !clientCibleRegistre
                                ? 'Sélectionnez un client dans le filtre pour générer son registre'
                                : stats.valide === 0
                                    ? 'Au moins une fiche « validé » est requise'
                                    : 'Générer le registre MOBISOFT (.xlsx)'
                        }
                    >
                        <DocumentArrowDownIcon className="w-4 h-4" />
                        {generation ? 'Génération...' : 'Générer le registre MOBISOFT'}
                    </Button>
                    {peutCreer && (
                        <Button onClick={ouvrirAutoModal} variant="secondary" title="Créer automatiquement des fiches de traitements depuis les finalités saisies dans les questionnaires">
                            <SparklesIcon className="w-4 h-4" /> Auto-créer depuis questionnaires
                        </Button>
                    )}
                    {peutCreer && (
                        <Button onClick={() => navigate('/traitements/nouveau')} variant="primary">
                            <PlusIcon className="w-4 h-4" /> Nouveau traitement
                        </Button>
                    )}
                </div>
            </PageHeader>

            <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <StatCard titre="Total" valeur={stats.total} icon={ClipboardDocumentListIcon} couleur="blue" soustitre="Fiches registres" />
                <StatCard titre="Brouillons" valeur={stats.brouillon} icon={ClockIcon} couleur="amber" soustitre="En cours de saisie" />
                <StatCard titre="Validés" valeur={stats.valide} icon={CheckCircleIcon} couleur="emerald" soustitre="Prêtes pour le registre" />
                <StatCard titre="Sensibles" valeur={stats.sensibles} icon={ShieldExclamationIcon} couleur="rose" soustitre="Données sensibles" />
                <StatCard titre="Hors CEDEAO" valeur={stats.transferts} icon={GlobeAltIcon} couleur="purple" soustitre="Transferts internationaux" />
            </div>

            {/* Barre de filtres globaux */}
            <Card variant="elevated" className="overflow-hidden mb-4">
                <div className="px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div className="flex items-center gap-3 flex-wrap">
                        <p className="text-sm text-gray-600">
                            <span className="font-bold text-gray-900">{filtered.length}</span> traitement{filtered.length > 1 ? 's' : ''}
                            {' '}sur <span className="font-bold text-gray-900">{traitementsParClient.length}</span> entreprise{traitementsParClient.length > 1 ? 's' : ''}
                        </p>
                        {traitementsParClient.length > 1 && (
                            <div className="flex items-center gap-1 text-xs">
                                <button onClick={toutOuvrir} className="px-2 py-1 rounded hover:bg-blue-50 text-blue-700 font-medium">Tout déplier</button>
                                <span className="text-gray-300">|</span>
                                <button onClick={toutFermer} className="px-2 py-1 rounded hover:bg-blue-50 text-blue-700 font-medium">Tout replier</button>
                            </div>
                        )}
                    </div>
                    <div className="flex items-center gap-2 flex-wrap">
                        {estInterne && (
                            <Select value={filtreClient} onChange={e => setFiltreClient(e.target.value)} className="w-56" icon={BuildingOffice2Icon}>
                                <option value="">Tous les clients</option>
                                {clients.map(c => <option key={c.id} value={c.id}>{c.raison_sociale}</option>)}
                            </Select>
                        )}
                        <Select value={filtreStatut} onChange={e => setFiltreStatut(e.target.value)} className="w-44">
                            <option value="">Tous les statuts</option>
                            <option value="brouillon">Brouillons</option>
                            <option value="valide">Validés</option>
                            <option value="archive">Archivés</option>
                        </Select>
                        <Input
                            icon={MagnifyingGlassIcon}
                            value={filterText}
                            onChange={e => setFilterText(e.target.value)}
                            placeholder="Rechercher..."
                            className="w-64"
                        />
                    </div>
                </div>
            </Card>

            {/* Groupes par client : 1 Card par entreprise, depliable */}
            {loading && (
                <Card className="p-8 text-center">
                    <p className="text-sm text-gray-500">Chargement...</p>
                </Card>
            )}
            {!loading && traitementsParClient.length === 0 && (
                <Card className="p-10 text-center">
                    <ClipboardDocumentListIcon className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                    <p className="text-sm text-gray-500">Aucun traitement ne correspond à vos critères.</p>
                </Card>
            )}
            <div className="space-y-3">
                {!loading && traitementsParClient.map(groupe => {
                    const ouvert = groupesOuverts[groupe.id] !== false;
                    const stat = {
                        total: groupe.items.length,
                        brouillon: groupe.items.filter(t => t.statut === 'brouillon').length,
                        valide: groupe.items.filter(t => t.statut === 'valide').length,
                    };
                    return (
                        <Card key={groupe.id} variant="elevated" className="overflow-hidden">
                            <button
                                type="button"
                                onClick={() => toggleGroupe(groupe.id)}
                                className="w-full flex items-center justify-between gap-3 px-5 py-3 bg-gradient-to-r from-blue-50/60 via-indigo-50/40 to-white hover:from-blue-100 transition"
                            >
                                <div className="flex items-center gap-3 min-w-0">
                                    {ouvert
                                        ? <ChevronDownIcon className="w-4 h-4 text-blue-700 shrink-0" />
                                        : <ChevronRightIcon className="w-4 h-4 text-blue-700 shrink-0" />}
                                    <BuildingOffice2Icon className="w-5 h-5 text-blue-700 shrink-0" />
                                    <span className="font-semibold text-gray-900 truncate">{groupe.raison_sociale}</span>
                                    <Badge variant="info">{stat.total}</Badge>
                                </div>
                                <div className="flex items-center gap-2 text-xs text-gray-600 shrink-0">
                                    {stat.brouillon > 0 && <Badge variant="gray">{stat.brouillon} brouillon{stat.brouillon > 1 ? 's' : ''}</Badge>}
                                    {stat.valide > 0 && <Badge variant="success">{stat.valide} validé{stat.valide > 1 ? 's' : ''}</Badge>}
                                </div>
                            </button>
                            {ouvert && (
                                <DataTableWrapper
                                    columns={columns}
                                    data={groupe.items}
                                    loading={false}
                                    onRowClicked={row => navigate(`/traitements/${row.id}`)}
                                    pagination={groupe.items.length > 10}
                                />
                            )}
                        </Card>
                    );
                })}
            </div>

            <Modal
                open={showAutoModal}
                onClose={() => !autoLoading && setShowAutoModal(false)}
                title="Auto-créer des traitements depuis les questionnaires"
                icon={SparklesIcon}
                size="md"
            >
                <div className="space-y-4">
                    <p className="text-sm text-gray-600">
                        Pour chaque questionnaire <strong>publié et rempli</strong> du client choisi,
                        l'application va isoler les réponses à la question « <em>Quelles sont les finalités
                        de la collecte de ces données ?</em> », découper chaque finalité saisie sur une ligne séparée,
                        et créer automatiquement une fiche de traitement en mode brouillon.
                    </p>

                    {estInterne ? (
                        <Select
                            label="Client"
                            value={autoClientId}
                            onChange={e => setAutoClientId(e.target.value)}
                            disabled={autoLoading || !!autoResult}
                            required
                        >
                            <option value="">— Sélectionner un client —</option>
                            {clients.map(c => (
                                <option key={c.id} value={c.id}>{c.raison_sociale}</option>
                            ))}
                        </Select>
                    ) : (
                        <p className="text-xs text-gray-500 italic">
                            La génération sera faite pour votre entreprise.
                        </p>
                    )}

                    <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 flex items-start gap-2">
                        <ExclamationTriangleIcon className="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
                        <p className="text-xs text-amber-900">
                            Les traitements dont la désignation existe déjà (insensible à la casse) seront
                            ignorés pour éviter les doublons. Vous pourrez ensuite éditer et valider chaque
                            brouillon créé.
                        </p>
                    </div>

                    {autoResult && (
                        <div className="bg-emerald-50 border border-emerald-200 rounded-lg p-3 space-y-2">
                            <p className="text-sm text-emerald-900 font-semibold">
                                <CheckCircleIcon className="w-4 h-4 inline -mt-0.5 mr-1" />
                                {autoResult.message}
                            </p>
                            {autoResult.crees?.length > 0 && (
                                <ul className="text-xs text-gray-700 space-y-0.5 max-h-40 overflow-y-auto pl-4 list-disc">
                                    {autoResult.crees.map(t => (
                                        <li key={t.id}>
                                            <span className="font-medium">{t.designation}</span>
                                            {t.pole && <span className="text-gray-500"> — {t.pole}</span>}
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {autoResult.nb_sautes > 0 && (
                                <p className="text-xs text-gray-600 italic">
                                    {autoResult.nb_sautes} finalité(s) ignorée(s) (doublons).
                                </p>
                            )}
                        </div>
                    )}

                    <div className="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <Button variant="ghost" onClick={() => setShowAutoModal(false)} disabled={autoLoading}>
                            {autoResult ? 'Fermer' : 'Annuler'}
                        </Button>
                        {!autoResult && (
                            <Button onClick={lancerAutoCreation} disabled={autoLoading || (estInterne && !autoClientId)}>
                                <SparklesIcon className="w-4 h-4" />
                                {autoLoading ? 'Génération…' : 'Lancer la génération'}
                            </Button>
                        )}
                    </div>
                </div>
            </Modal>
        </div>
    );
}
