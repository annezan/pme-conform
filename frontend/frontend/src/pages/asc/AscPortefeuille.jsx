/**
 * Page AscPortefeuille — Vue consultant du portefeuille clients avec stats.
 */

import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { listPortefeuille } from '@/api/ascPortefeuille';
import { alertError } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Badge from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import {
    BuildingOffice2Icon, ClipboardDocumentListIcon, ShieldCheckIcon,
    DocumentDuplicateIcon, MagnifyingGlassCircleIcon, FlagIcon, MagnifyingGlassIcon,
    RectangleGroupIcon,
} from '@heroicons/react/24/outline';

const typeLabel = {
    pme: 'PME',
    grande_entreprise: 'Grande entreprise',
    administration: 'Administration',
    association: 'Association',
    autre: 'Autre',
};

export default function AscPortefeuille() {
    const navigate = useNavigate();
    const [clients, setClients] = useState([]);
    const [loading, setLoading] = useState(true);
    const [filterText, setFilterText] = useState('');

    useEffect(() => {
        listPortefeuille({ per_page: 50 })
            .then(r => setClients(r.data || []))
            .catch(err => alertError(err.response?.data?.message || 'Erreur'))
            .finally(() => setLoading(false));
    }, []);

    const filtered = clients.filter(c =>
        c.raison_sociale?.toLowerCase().includes(filterText.toLowerCase()) ||
        c.secteur_activite?.toLowerCase().includes(filterText.toLowerCase())
    );

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <PageHeader
                title="Portefeuille ASC"
                subtitle="Vue consolidée des entreprises clientes et de leur conformité"
                eyebrow="Pilotage consultant"
                icon={RectangleGroupIcon}
                accent="blue"
            />

            <div className="mb-4 flex items-center justify-between gap-3 flex-wrap">
                <p className="text-sm text-gray-600">
                    <span className="font-bold text-gray-900">{filtered.length}</span> entreprise{filtered.length > 1 ? 's' : ''}
                </p>
                <Input
                    icon={MagnifyingGlassIcon}
                    value={filterText}
                    onChange={e => setFilterText(e.target.value)}
                    placeholder="Rechercher une entreprise..."
                    className="w-72"
                />
            </div>

            {loading ? (
                <div className="p-8 flex justify-center"><div className="w-10 h-10 rounded-full border-4 border-blue-600 border-t-transparent animate-spin" /></div>
            ) : filtered.length === 0 ? (
                <Card className="p-12 text-center text-gray-500">Aucun client dans votre portefeuille.</Card>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {filtered.map(c => (
                        <Card key={c.id} hover className="p-5 cursor-pointer" onClick={() => navigate(`/asc/portefeuille/${c.id}`)}>
                            <div className="flex items-start gap-3 mb-4">
                                <div className="w-12 h-12 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center shrink-0">
                                    <BuildingOffice2Icon className="w-6 h-6" />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <h3 className="font-semibold text-gray-900 truncate">{c.raison_sociale}</h3>
                                    <div className="flex flex-wrap gap-1 mt-1">
                                        {c.type_structure && <Badge variant="info">{typeLabel[c.type_structure] || c.type_structure}</Badge>}
                                        {c.secteur_activite && <span className="text-xs text-gray-500">{c.secteur_activite}</span>}
                                    </div>
                                </div>
                            </div>

                            <div className="grid grid-cols-3 gap-2 pt-3 border-t border-gray-100">
                                <StatMini icon={ClipboardDocumentListIcon} value={c.stats.traitements_valides} total={c.stats.traitements_total} label="Trait." color="blue" />
                                <StatMini icon={ShieldCheckIcon} value={c.stats.signatures_actives} label="Signat." color="emerald" />
                                <StatMini icon={DocumentDuplicateIcon} value={c.stats.registres_kyc} label="KYC" color="purple" />
                                <StatMini icon={MagnifyingGlassCircleIcon} value={c.stats.analyses} label="Analyses" color="cyan" />
                                <StatMini icon={FlagIcon} value={c.stats.plans_actions_actifs} label="Plans" color="orange" />
                            </div>
                        </Card>
                    ))}
                </div>
            )}
        </div>
    );
}

function StatMini({ icon: Icon, value, total, label, color }) {
    const colors = {
        blue: 'text-blue-600',
        emerald: 'text-emerald-600',
        purple: 'text-purple-600',
        cyan: 'text-cyan-600',
        orange: 'text-orange-600',
    };
    return (
        <div className="text-center">
            <Icon className={`w-4 h-4 mx-auto mb-1 ${colors[color]}`} />
            <p className="text-sm font-bold text-gray-900">
                {value}{total !== undefined && <span className="text-xs text-gray-400 font-normal">/{total}</span>}
            </p>
            <p className="text-[10px] text-gray-500 uppercase">{label}</p>
        </div>
    );
}
