/**
 * Page AdminModules premium — Gestion des modules.
 */

import { useState, useEffect } from 'react';
import api, { getCsrfCookie } from '@/api/client';
import { alertSuccess, alertError, confirmAction } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Badge from '@/components/ui/Badge';
import Loader from '@/components/ui/Loader';
import { CubeIcon, BoltIcon } from '@heroicons/react/24/outline';

export default function AdminModules() {
    const [modules, setModules] = useState([]);
    const [loading, setLoading] = useState(true);

    const charger = () => {
        api.get('/admin/modules').then(r => setModules(r.data.modules || [])).finally(() => setLoading(false));
    };
    useEffect(() => { charger(); }, []);

    const toggleModule = async (module) => {
        const action = module.is_active ? 'désactiver' : 'activer';
        if (await confirmAction(`Voulez-vous ${action} le module "${module.nom}" ?`)) {
            try {
                await getCsrfCookie();
                const res = await api.post(`/admin/modules/${module.id}/toggle`);
                alertSuccess(res.data.message);
                charger();
            } catch (err) {
                alertError(err.response?.data?.message || 'Erreur');
            }
        }
    };

    if (loading) return <Loader />;

    return (
        <div className="p-6 lg:p-8 max-w-5xl mx-auto">
            <PageHeader
                title="Modules"
                subtitle="Gestion des modules métier de la plateforme"
                eyebrow="Administration"
                icon={CubeIcon}
                accent="cyan"
            />

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {modules.map(module => (
                    <div key={module.id} className={`bg-white rounded-xl border-2 shadow-sm p-6 transition-all duration-200 ${module.is_active ? 'border-emerald-200 hover:shadow-md' : 'border-gray-200 opacity-75'}`}>
                        <div className="flex items-start justify-between mb-4">
                            <div className="flex items-center gap-3">
                                <div className={`w-11 h-11 rounded-xl flex items-center justify-center ${module.is_active ? 'bg-emerald-50' : 'bg-gray-50'}`}>
                                    <CubeIcon className={`w-6 h-6 ${module.is_active ? 'text-emerald-600' : 'text-gray-400'}`} />
                                </div>
                                <div>
                                    <h3 className="font-semibold text-gray-900">{module.nom}</h3>
                                    <p className="text-xs text-gray-400">v{module.version}</p>
                                </div>
                            </div>
                            <Badge variant={module.is_active ? 'success' : 'gray'}>
                                {module.is_active ? 'Actif' : 'Inactif'}
                            </Badge>
                        </div>

                        <p className="text-sm text-gray-500 mb-4 line-clamp-2">{module.description}</p>

                        <div className="flex items-center justify-between pt-3 border-t border-gray-100">
                            <span className="text-xs text-gray-400 flex items-center gap-1">
                                <BoltIcon className="w-3.5 h-3.5" /> {module.agents_count || 0} agents
                            </span>
                            {module.is_core ? (
                                <span className="text-xs text-gray-400 font-medium">Module noyau</span>
                            ) : (
                                <button
                                    onClick={() => toggleModule(module)}
                                    className={`text-xs px-3 py-1.5 rounded-lg font-medium transition-colors ${
                                        module.is_active
                                            ? 'text-red-700 bg-red-50 hover:bg-red-100'
                                            : 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100'
                                    }`}
                                >
                                    {module.is_active ? 'Désactiver' : 'Activer'}
                                </button>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
