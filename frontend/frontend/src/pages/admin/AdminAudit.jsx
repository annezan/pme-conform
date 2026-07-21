/**
 * Page AdminAudit premium — Journal d'audit avec DataTable.
 */

import { useState, useEffect, useMemo } from 'react';
import api from '@/api/client';
import DataTableWrapper from '@/components/ui/DataTableWrapper';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import PageHeader from '@/components/ui/PageHeader';
import { Select } from '@/components/ui/Input';
import { ShieldCheckIcon } from '@heroicons/react/24/outline';

const resultatVariant = { succes: 'success', echec: 'danger', erreur: 'warning' };
const categorieLabels = { auth: 'Auth', document: 'Document', agent: 'Agent', admin: 'Admin', general: 'Général' };

export default function AdminAudit() {
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [categorie, setCategorie] = useState('');
    const [filterText, setFilterText] = useState('');

    useEffect(() => {
        setLoading(true);
        const params = { per_page: 200 };
        if (categorie) params.categorie = categorie;
        api.get('/admin/audit-logs', { params })
            .then(r => setLogs(r.data.data || []))
            .finally(() => setLoading(false));
    }, [categorie]);

    const columns = useMemo(() => [
        {
            name: 'Date', width: '170px', sortable: true,
            selector: row => row.created_at,
            cell: row => <span className="text-xs text-gray-500 whitespace-nowrap">{new Date(row.created_at).toLocaleString('fr-FR')}</span>,
        },
        {
            name: 'Utilisateur', width: '160px',
            cell: row => row.user ? (
                <div className="flex items-center gap-2">
                    <div className="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center text-[10px] font-semibold text-gray-600 shrink-0">
                        {row.user.prenom?.charAt(0)}{row.user.nom?.charAt(0)}
                    </div>
                    <span className="text-sm truncate">{row.user.prenom} {row.user.nom}</span>
                </div>
            ) : <span className="text-gray-400 text-xs">Système</span>,
        },
        { name: 'Action', selector: row => row.action, sortable: true, cell: row => <code className="text-xs bg-gray-50 px-2 py-0.5 rounded">{row.action}</code> },
        {
            name: 'Catégorie', width: '110px',
            cell: row => <Badge variant="gray">{categorieLabels[row.categorie] || row.categorie}</Badge>,
        },
        { name: 'Description', grow: 2, selector: row => row.description || '-', cell: row => <span className="text-sm text-gray-600 truncate">{row.description || '-'}</span> },
        { name: 'IP', width: '120px', selector: row => row.ip_address, cell: row => <span className="font-mono text-xs text-gray-400">{row.ip_address}</span> },
        {
            name: 'Résultat', width: '90px', center: true,
            cell: row => <Badge variant={resultatVariant[row.resultat]}>{row.resultat}</Badge>,
        },
    ], []);

    const filteredData = logs.filter(l =>
        l.action?.toLowerCase().includes(filterText.toLowerCase()) ||
        l.description?.toLowerCase().includes(filterText.toLowerCase())
    );

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <PageHeader
                title="Journal d'audit"
                subtitle="Historique complet des actions sur la plateforme"
                eyebrow="Traçabilité et sécurité"
                icon={ShieldCheckIcon}
                accent="emerald"
            >
                <Badge variant="emerald" solid dot size="md">{logs.length} entrées</Badge>
            </PageHeader>

            <Card>
                <div className="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center gap-4">
                    <select value={categorie} onChange={e => setCategorie(e.target.value)}
                        className="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:border-blue-500 outline-none">
                        <option value="">Toutes catégories</option>
                        <option value="auth">Authentification</option>
                        <option value="document">Documents</option>
                        <option value="agent">Agents IA</option>
                        <option value="admin">Administration</option>
                        <option value="general">Général</option>
                    </select>
                    <input type="text" value={filterText} onChange={e => setFilterText(e.target.value)} placeholder="Filtrer par action..."
                        className="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:border-blue-500 outline-none flex-1 min-w-48" />
                </div>
                <DataTableWrapper columns={columns} data={filteredData} loading={loading} />
            </Card>
        </div>
    );
}
