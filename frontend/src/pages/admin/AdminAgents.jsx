/**
 * Page AdminAgents premium — Editer les prompts des agents.
 */

import { useState, useEffect } from 'react';
import api, { getCsrfCookie } from '@/api/client';
import { alertSuccess, alertError } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Badge from '@/components/ui/Badge';
import Button from '@/components/ui/Button';
import { Input, Textarea } from '@/components/ui/Input';
import Loader from '@/components/ui/Loader';
import { PencilSquareIcon, XMarkIcon, CheckIcon, SparklesIcon } from '@heroicons/react/24/outline';

const typeVariant = { conversationnel: 'info', analytique: 'purple', generateur: 'success', veille: 'warning', assistant: 'cyan' };

export default function AdminAgents() {
    const [agents, setAgents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [editingId, setEditingId] = useState(null);
    const [editForm, setEditForm] = useState({});
    const [saving, setSaving] = useState(false);

    const charger = () => {
        api.get('/admin/agents').then(r => setAgents(r.data.agents || [])).finally(() => setLoading(false));
    };
    useEffect(() => { charger(); }, []);

    const startEdit = (agent) => {
        setEditingId(agent.id);
        setEditForm({ prompt_systeme: agent.prompt_systeme, temperature: agent.temperature, max_tokens: agent.max_tokens || '', is_active: agent.is_active });
    };

    const saveEdit = async (agentId) => {
        setSaving(true);
        try {
            await getCsrfCookie();
            await api.put(`/admin/agents/${agentId}`, editForm);
            alertSuccess('Agent mis à jour');
            setEditingId(null);
            charger();
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur');
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <Loader />;

    return (
        <div className="p-6 lg:p-8 max-w-5xl mx-auto">
            <PageHeader
                title="Agents IA"
                subtitle="Modifier les prompts et paramètres sans redéploiement"
                eyebrow="Administration"
                icon={SparklesIcon}
                accent="purple"
            />

            <div className="space-y-4">
                {agents.map(agent => (
                    <div key={agent.id} className="bg-white rounded-xl border border-gray-200/60 shadow-sm overflow-hidden">
                        {/* Header */}
                        <div className="px-6 py-4 flex items-center justify-between border-b border-gray-100">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 rounded-xl flex items-center justify-center text-white font-bold text-sm shadow-sm"
                                    style={{ backgroundColor: agent.couleur || '#3b82f6' }}>
                                    {agent.nom.charAt(0)}
                                </div>
                                <div>
                                    <h3 className="font-semibold text-gray-900">{agent.nom}</h3>
                                    <div className="flex items-center gap-2 mt-0.5">
                                        <Badge variant={typeVariant[agent.type]}>{agent.type}</Badge>
                                        <span className="text-xs text-gray-400">{agent.module?.nom || 'Noyau'}</span>
                                        <span className="text-xs text-gray-400">T={agent.temperature}</span>
                                    </div>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Badge variant={agent.is_active ? 'success' : 'danger'}>{agent.is_active ? 'Actif' : 'Inactif'}</Badge>
                                {editingId !== agent.id && (
                                    <button onClick={() => startEdit(agent)} className="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                        <PencilSquareIcon className="w-4 h-4" />
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* Body */}
                        <div className="px-6 py-4">
                            {editingId === agent.id ? (
                                <div className="space-y-4">
                                    <Textarea label="Prompt système" value={editForm.prompt_systeme}
                                        onChange={e => setEditForm({...editForm, prompt_systeme: e.target.value})}
                                        rows={10} className="font-mono" />
                                    <div className="grid grid-cols-3 gap-4">
                                        <Input label="Température" type="number" step="0.1" min="0" max="2" value={editForm.temperature}
                                            onChange={e => setEditForm({...editForm, temperature: parseFloat(e.target.value)})} />
                                        <Input label="Max tokens" type="number" value={editForm.max_tokens} placeholder="Défaut"
                                            onChange={e => setEditForm({...editForm, max_tokens: e.target.value ? parseInt(e.target.value) : ''})} />
                                        <div className="flex items-end pb-1">
                                            <label className="flex items-center gap-2 text-sm cursor-pointer">
                                                <input type="checkbox" checked={editForm.is_active}
                                                    onChange={e => setEditForm({...editForm, is_active: e.target.checked})}
                                                    className="w-4 h-4 rounded border-gray-300 text-blue-600" />
                                                Agent actif
                                            </label>
                                        </div>
                                    </div>
                                    <div className="flex justify-end gap-2 pt-2">
                                        <Button variant="secondary" size="sm" onClick={() => setEditingId(null)}>
                                            <XMarkIcon className="w-4 h-4" /> Annuler
                                        </Button>
                                        <Button size="sm" onClick={() => saveEdit(agent.id)} disabled={saving}>
                                            <CheckIcon className="w-4 h-4" /> {saving ? 'Sauvegarde...' : 'Sauvegarder'}
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <p className="text-sm text-gray-600 whitespace-pre-wrap line-clamp-4">{agent.prompt_systeme}</p>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
