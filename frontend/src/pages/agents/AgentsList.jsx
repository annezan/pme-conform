/**
 * Page AgentsList premium — Grille des agents IA.
 */

import { Link } from 'react-router-dom';
import { useAgents } from '@/hooks/useAgents';
import PageHeader from '@/components/ui/PageHeader';
import Badge from '@/components/ui/Badge';
import Loader from '@/components/ui/Loader';
import { ArrowRightIcon, SparklesIcon } from '@heroicons/react/24/outline';

const typeConfig = {
    conversationnel: { label: 'Chat', variant: 'info' },
    analytique: { label: 'Analyse', variant: 'purple' },
    generateur: { label: 'Génération', variant: 'success' },
    veille: { label: 'Veille', variant: 'warning' },
    assistant: { label: 'Assistant', variant: 'cyan' },
};

export default function AgentsList() {
    const { agents, loading, error } = useAgents();

    if (loading) return <Loader />;
    if (error) return <div className="p-8"><div className="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl">{error}</div></div>;

    return (
        <div className="p-6 lg:p-8 max-w-7xl mx-auto">
            <PageHeader
                title="Agents IA"
                subtitle="Sélectionnez un agent pour démarrer une conversation"
                eyebrow="Assistants spécialisés"
                icon={SparklesIcon}
                accent="purple"
            />

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                {agents.map((agent) => {
                    const type = typeConfig[agent.type] || typeConfig.assistant;
                    return (
                        <Link
                            key={agent.id}
                            to={`/agents/${agent.slug}/chat`}
                            className="group bg-white rounded-xl border border-gray-200/60 shadow-sm p-6 hover:shadow-lg hover:border-blue-300/60 transition-all duration-300"
                        >
                            <div className="flex items-start justify-between mb-4">
                                <div
                                    className="w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg font-bold shadow-sm"
                                    style={{ backgroundColor: agent.couleur || '#3b82f6' }}
                                >
                                    {agent.nom.charAt(0)}
                                </div>
                                <Badge variant={type.variant}>{type.label}</Badge>
                            </div>
                            <h3 className="font-semibold text-gray-900 group-hover:text-blue-700 transition-colors mb-1">
                                {agent.nom}
                            </h3>
                            <p className="text-sm text-gray-500 line-clamp-2 mb-4">{agent.description}</p>
                            <div className="flex items-center justify-between">
                                <span className="text-xs text-gray-400">{agent.module?.nom || 'Noyau'}</span>
                                <span className="text-xs text-blue-600 font-medium flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    Ouvrir <ArrowRightIcon className="w-3 h-3" />
                                </span>
                            </div>
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}
