/**
 * Page AdminPermissionsParRole — Gestion des permissions par role (API AUDREY).
 *
 * Reproduit le pattern DLMS :
 *  1. Selectionner un role dans une dropdown
 *  2. Le tableau des permissions affiche une colonne "Acces" avec une case a cocher
 *     pre-cochee selon les permissions deja accordees au role
 *  3. Le bouton "Enregistrer" calcule le diff (toAdd / toRemove) et lance en parallele :
 *       POST /admin/roles/{id}/attach-permissions   { permissions: [ids] }
 *       POST /admin/roles/{id}/detach-permissions   { permissions: [ids] }
 *
 * Endpoints utilises :
 *  - GET  /admin/roles-liste              (liste pour dropdown)
 *  - GET  /admin/permissions              (paginé : per_page, active_only, group, search)
 *  - GET  /admin/roles/{id}               (permissions actuelles du role)
 *  - POST /admin/roles/{id}/attach-permissions
 *  - POST /admin/roles/{id}/detach-permissions
 */

import { useState, useEffect, useMemo } from 'react';
import api, { getCsrfCookie } from '@/api/client';
import { alertSuccess, alertError, alertInfo } from '@/utils/alerts';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import PageHeader from '@/components/ui/PageHeader';
import {
    ShieldCheckIcon, ArrowLeftIcon, InformationCircleIcon, CheckIcon,
    ChevronDownIcon, ChevronRightIcon, MagnifyingGlassIcon,
} from '@heroicons/react/24/outline';

// Libelle plus lisible + icone/couleur par groupe de fonctionnalite.
// Non exhaustif : les groupes non listes fallback sur un style neutre.
const GROUPES_META = {
    admin:                { label: 'Administration', color: 'slate',   emoji: '🛡️' },
    dashboard:            { label: 'Tableau de bord', color: 'blue',    emoji: '📊' },
    clients:              { label: 'Clients',        color: 'indigo',   emoji: '🏢' },
    missions:             { label: 'Missions',       color: 'purple',   emoji: '🎯' },
    'client-documents':   { label: 'Documents client', color: 'cyan',   emoji: '📁' },
    documents:            { label: 'Documents',      color: 'cyan',     emoji: '📄' },
    traitements:          { label: 'Traitements DCP', color: 'emerald', emoji: '⚙️' },
    analyses:             { label: 'Analyses d\'ecarts', color: 'rose', emoji: '🔍' },
    ecarts:               { label: 'Ecarts',         color: 'rose',     emoji: '⚠️' },
    'plans-actions':      { label: 'Plans d\'actions', color: 'amber',  emoji: '📋' },
    referentiels:         { label: 'Referentiels',   color: 'violet',   emoji: '📚' },
    'registres-kyc':      { label: 'Registres KYC',  color: 'teal',     emoji: '📇' },
    chartes:              { label: 'Chartes & signatures', color: 'sky',emoji: '✍️' },
    questionnaires:       { label: 'Questionnaires', color: 'lime',     emoji: '📝' },
    matrice:              { label: 'Matrice de collecte', color: 'fuchsia', emoji: '🗂️' },
    organigramme:         { label: 'Organigramme',   color: 'orange',   emoji: '🏛️' },
    portefeuille:         { label: 'Portefeuille ASC', color: 'blue',   emoji: '📁' },
    'ref-data':           { label: 'Referentiels de donnees', color: 'gray', emoji: '🗄️' },
    secteurs:             { label: 'Secteurs d\'activite', color: 'gray', emoji: '🗄️' },
    taches:               { label: 'Taches',         color: 'gray',     emoji: '☑️' },
    agents:               { label: 'Agents IA',      color: 'purple',   emoji: '🤖' },
    llm:                  { label: 'LLM',            color: 'purple',   emoji: '🤖' },
    conversations:        { label: 'Conversations',  color: 'cyan',     emoji: '💬' },
    signatures:           { label: 'Signatures',     color: 'sky',      emoji: '✒️' },
};

const badgeClasses = (color) => ({
    slate: 'bg-slate-100 text-slate-700 ring-slate-200',
    blue:  'bg-blue-100 text-blue-700 ring-blue-200',
    indigo:'bg-indigo-100 text-indigo-700 ring-indigo-200',
    purple:'bg-purple-100 text-purple-700 ring-purple-200',
    cyan:  'bg-cyan-100 text-cyan-700 ring-cyan-200',
    emerald:'bg-emerald-100 text-emerald-700 ring-emerald-200',
    rose:  'bg-rose-100 text-rose-700 ring-rose-200',
    amber: 'bg-amber-100 text-amber-800 ring-amber-200',
    violet:'bg-violet-100 text-violet-700 ring-violet-200',
    teal:  'bg-teal-100 text-teal-700 ring-teal-200',
    sky:   'bg-sky-100 text-sky-700 ring-sky-200',
    lime:  'bg-lime-100 text-lime-700 ring-lime-200',
    fuchsia:'bg-fuchsia-100 text-fuchsia-700 ring-fuchsia-200',
    orange:'bg-orange-100 text-orange-700 ring-orange-200',
    gray:  'bg-gray-100 text-gray-700 ring-gray-200',
}[color] || 'bg-gray-100 text-gray-700 ring-gray-200');

export default function AdminPermissionsParRole() {
    const [roles, setRoles] = useState([]);
    const [permissions, setPermissions] = useState([]); // [{id, name, group, is_active}]
    const [selectedRoleId, setSelectedRoleId] = useState('');
    const [rolePermissionIds, setRolePermissionIds] = useState([]); // ids cochees actuellement
    const [originalPermissionIds, setOriginalPermissionIds] = useState([]); // au chargement
    const [loading, setLoading] = useState(true);
    const [chargementRole, setChargementRole] = useState(false);
    const [saving, setSaving] = useState(false);
    const [filterText, setFilterText] = useState('');

    useEffect(() => {
        (async () => {
            setLoading(true);
            try {
                const [rolesRes, permsRes] = await Promise.all([
                    api.get('/admin/roles-liste'),
                    api.get('/admin/permissions', { params: { per_page: 200, active_only: true } }),
                ]);
                setRoles(rolesRes.data.data || []);
                setPermissions(permsRes.data.data || []);
            } catch {
                alertError('Erreur lors du chargement');
            } finally {
                setLoading(false);
            }
        })();
    }, []);

    useEffect(() => {
        if (!selectedRoleId) {
            setRolePermissionIds([]);
            setOriginalPermissionIds([]);
            return;
        }
        setChargementRole(true);
        api.get(`/admin/roles/${selectedRoleId}`)
            .then(r => {
                const ids = (r.data.data?.permissions || []).map(p => p.id);
                setRolePermissionIds(ids);
                setOriginalPermissionIds(ids);
            })
            .catch(() => {
                setRolePermissionIds([]);
                setOriginalPermissionIds([]);
                alertError('Impossible de charger les permissions du rôle');
            })
            .finally(() => setChargementRole(false));
    }, [selectedRoleId]);

    const togglePermission = (id) => {
        if (!selectedRoleId) return;
        setRolePermissionIds(prev =>
            prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]
        );
    };

    const aChange = useMemo(() => {
        if (rolePermissionIds.length !== originalPermissionIds.length) return true;
        const set = new Set(originalPermissionIds);
        return rolePermissionIds.some(id => !set.has(id));
    }, [rolePermissionIds, originalPermissionIds]);

    const enregistrer = async () => {
        if (!selectedRoleId) return;
        if (!aChange) {
            alertInfo('Aucun changement détecté');
            return;
        }
        setSaving(true);
        try {
            await getCsrfCookie();
            const toAdd = rolePermissionIds.filter(id => !originalPermissionIds.includes(id));
            const toRemove = originalPermissionIds.filter(id => !rolePermissionIds.includes(id));

            const promises = [];
            if (toAdd.length > 0) {
                promises.push(api.post(`/admin/roles/${selectedRoleId}/attach-permissions`, { permissions: toAdd }));
            }
            if (toRemove.length > 0) {
                promises.push(api.post(`/admin/roles/${selectedRoleId}/detach-permissions`, { permissions: toRemove }));
            }
            await Promise.all(promises);

            setOriginalPermissionIds([...rolePermissionIds]);
            alertSuccess('Permissions mises à jour');
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur lors de la sauvegarde');
        } finally {
            setSaving(false);
        }
    };

    const roleSelectionne = roles.find(r => String(r.id) === String(selectedRoleId));

    const filtered = useMemo(() => permissions.filter(p =>
        (p.name || '').toLowerCase().includes(filterText.toLowerCase()) ||
        (p.description || '').toLowerCase().includes(filterText.toLowerCase()) ||
        (p.group || '').toLowerCase().includes(filterText.toLowerCase())
    ), [permissions, filterText]);

    // Regroupement des permissions filtrees par groupe de fonctionnalite.
    // On calcule aussi pour chaque groupe : nb total, nb cochees, si tout coche.
    const groupes = useMemo(() => {
        const map = new Map();
        for (const p of filtered) {
            const g = p.group || 'autre';
            if (!map.has(g)) map.set(g, []);
            map.get(g).push(p);
        }
        // Trie par label lisible
        const arr = Array.from(map.entries()).map(([g, perms]) => ({
            groupe: g,
            meta: GROUPES_META[g] || { label: g.charAt(0).toUpperCase() + g.slice(1), color: 'gray', emoji: '🔹' },
            permissions: perms.slice().sort((a, b) => (a.name || '').localeCompare(b.name || '')),
            nbCochees: perms.filter(p => rolePermissionIds.includes(p.id)).length,
            nbTotal: perms.length,
        }));
        arr.sort((a, b) => a.meta.label.localeCompare(b.meta.label));
        return arr;
    }, [filtered, rolePermissionIds]);

    const [groupesReplies, setGroupesReplies] = useState(new Set());
    const toggleGroupe = (g) => {
        setGroupesReplies(prev => {
            const next = new Set(prev);
            if (next.has(g)) next.delete(g); else next.add(g);
            return next;
        });
    };

    // Cocher / decocher toutes les permissions d'un groupe en une fois.
    const toutCocherGroupe = (perms) => {
        if (!selectedRoleId) return;
        const ids = perms.map(p => p.id);
        setRolePermissionIds(prev => Array.from(new Set([...prev, ...ids])));
    };
    const toutDecocherGroupe = (perms) => {
        if (!selectedRoleId) return;
        const setIds = new Set(perms.map(p => p.id));
        setRolePermissionIds(prev => prev.filter(id => !setIds.has(id)));
    };

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <PageHeader
                title="Gestion des permissions par rôle"
                subtitle="Sélectionnez un rôle pour attribuer ou retirer ses permissions"
                eyebrow="Administration"
                icon={ShieldCheckIcon}
                accent="emerald"
            >
                {selectedRoleId && (
                    <Button onClick={enregistrer} disabled={saving || !aChange}>
                        {saving ? (
                            <>
                                <div className="w-4 h-4 rounded-full border-2 border-white border-t-transparent animate-spin" />
                                Enregistrement...
                            </>
                        ) : (
                            <>
                                <CheckIcon className="w-4 h-4" />
                                Enregistrer
                            </>
                        )}
                    </Button>
                )}
            </PageHeader>

            {/* Bandeau de selection du role */}
            <Card className="mb-6">
                <div className="p-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                        <div>
                            <label className="block text-sm font-bold text-gray-700 mb-2">
                                1. Sélectionner un rôle :
                            </label>
                            <select
                                value={selectedRoleId}
                                onChange={(e) => setSelectedRoleId(e.target.value)}
                                className="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white shadow-sm text-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 outline-none"
                            >
                                <option value="">-- Choisir un rôle --</option>
                                {roles.map(r => (
                                    <option key={r.id} value={r.id}>
                                        {r.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="md:text-right">
                            {selectedRoleId ? (
                                <div className="inline-flex items-start gap-2 px-4 py-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-900 shadow-sm">
                                    <InformationCircleIcon className="w-5 h-5 shrink-0 mt-0.5" />
                                    <span>
                                        Cochez les permissions à accorder au rôle{' '}
                                        <strong>{roleSelectionne?.name}</strong>
                                    </span>
                                </div>
                            ) : (
                                <span className="inline-flex items-center gap-2 text-sm text-gray-500 italic">
                                    <ArrowLeftIcon className="w-4 h-4" />
                                    Veuillez choisir un rôle pour commencer l'attribution
                                </span>
                            )}
                        </div>
                    </div>
                </div>
            </Card>

            {/* Bandeau referentiel + recherche */}
            <Card className="mb-4">
                <div className="px-6 py-4 flex items-center justify-between flex-wrap gap-3">
                    <div className="flex items-center gap-2 text-sm text-gray-500">
                        <ShieldCheckIcon className="w-4 h-4" />
                        Référentiel des permissions
                        <span className="text-gray-300">•</span>
                        <span>{filtered.length} permission(s) dans {groupes.length} groupe(s)</span>
                        {selectedRoleId && (
                            <>
                                <span className="text-gray-300">•</span>
                                <span className="text-blue-700 font-medium">
                                    {rolePermissionIds.length} cochée(s){aChange ? ' (modifications non enregistrées)' : ''}
                                </span>
                            </>
                        )}
                    </div>
                    <div className="relative">
                        <MagnifyingGlassIcon className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                        <input
                            type="text"
                            value={filterText}
                            onChange={e => setFilterText(e.target.value)}
                            placeholder="Rechercher dans la liste..."
                            className="pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 outline-none w-64"
                        />
                    </div>
                </div>
            </Card>

            {/* Groupes de permissions */}
            {loading || chargementRole ? (
                <Card>
                    <div className="p-10 text-center text-sm text-gray-500">Chargement...</div>
                </Card>
            ) : groupes.length === 0 ? (
                <Card>
                    <div className="p-10 text-center text-sm text-gray-500 italic">
                        Aucune permission ne correspond a la recherche.
                    </div>
                </Card>
            ) : (
                <div className="space-y-3">
                    {groupes.map(({ groupe, meta, permissions: perms, nbCochees, nbTotal }) => {
                        const replie = groupesReplies.has(groupe);
                        const toutesCochees = selectedRoleId && nbCochees === nbTotal && nbTotal > 0;
                        const partiellementCochees = selectedRoleId && nbCochees > 0 && nbCochees < nbTotal;

                        return (
                            <Card key={groupe} className="overflow-hidden">
                                {/* Header du groupe */}
                                <div
                                    className={`px-5 py-3 flex items-center justify-between gap-3 cursor-pointer transition-colors hover:bg-gray-50 ${toutesCochees ? 'bg-emerald-50/40' : ''}`}
                                    onClick={() => toggleGroupe(groupe)}
                                >
                                    <div className="flex items-center gap-3 min-w-0">
                                        <button className="p-1 text-gray-400 hover:text-gray-700">
                                            {replie ? <ChevronRightIcon className="w-4 h-4" /> : <ChevronDownIcon className="w-4 h-4" />}
                                        </button>
                                        <span className="text-xl">{meta.emoji}</span>
                                        <div className="min-w-0">
                                            <h3 className="font-bold text-gray-900 text-sm">{meta.label}</h3>
                                            <p className="text-[11px] text-gray-500 font-mono">{groupe}</p>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2 flex-wrap">
                                        {selectedRoleId ? (
                                            <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-md ring-1 text-xs font-semibold ${partiellementCochees ? 'bg-amber-100 text-amber-800 ring-amber-200' : toutesCochees ? 'bg-emerald-100 text-emerald-700 ring-emerald-200' : 'bg-gray-100 text-gray-600 ring-gray-200'}`}>
                                                {nbCochees}/{nbTotal} cochée(s)
                                            </span>
                                        ) : (
                                            <span className={`inline-flex items-center px-2.5 py-1 rounded-md ring-1 text-xs font-semibold ${badgeClasses(meta.color)}`}>
                                                {nbTotal} permission(s)
                                            </span>
                                        )}
                                        {selectedRoleId && (
                                            <div className="flex items-center gap-1" onClick={e => e.stopPropagation()}>
                                                <button
                                                    onClick={() => toutCocherGroupe(perms)}
                                                    className="text-[11px] font-medium px-2.5 py-1 rounded-md bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors"
                                                    title="Tout cocher dans ce groupe"
                                                >
                                                    Tout
                                                </button>
                                                <button
                                                    onClick={() => toutDecocherGroupe(perms)}
                                                    className="text-[11px] font-medium px-2.5 py-1 rounded-md bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors"
                                                    title="Tout decocher dans ce groupe"
                                                >
                                                    Aucun
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* Liste des permissions du groupe */}
                                {!replie && (
                                    <div className="border-t border-gray-100 divide-y divide-gray-50">
                                        {perms.map(p => {
                                            const coche = rolePermissionIds.includes(p.id);
                                            return (
                                                <label
                                                    key={p.id}
                                                    className={`flex items-start gap-3 px-5 py-2.5 hover:bg-blue-50/30 transition-colors ${selectedRoleId ? 'cursor-pointer' : 'cursor-default'} ${coche ? 'bg-blue-50/30' : ''}`}
                                                >
                                                    {selectedRoleId && (
                                                        <input
                                                            type="checkbox"
                                                            checked={coche}
                                                            onChange={() => togglePermission(p.id)}
                                                            className="mt-1 w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer shrink-0"
                                                        />
                                                    )}
                                                    <div className="min-w-0 flex-1">
                                                        <p className="font-semibold text-gray-900 text-sm font-mono">{p.name}</p>
                                                        {p.description && (
                                                            <p className="text-xs text-gray-500 mt-0.5">{p.description}</p>
                                                        )}
                                                    </div>
                                                </label>
                                            );
                                        })}
                                    </div>
                                )}
                            </Card>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
