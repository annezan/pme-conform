/**
 * Page AgentChat — Interface de chat avec un agent IA.
 *
 * Affiche la liste des conversations a gauche et le chat a droite.
 */

import { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { getAgent } from '@/api/agents';
import { getConversations } from '@/api/conversations';
import { useChat } from '@/hooks/useChat';
import ChatWindow from '@/components/chat/ChatWindow';
import ChatInput from '@/components/chat/ChatInput';

export default function AgentChat() {
    const { slug, conversationId: routeConvId } = useParams();
    const [agent, setAgent] = useState(null);
    const [conversations, setConversations] = useState([]);
    const [loadingAgent, setLoadingAgent] = useState(true);

    const chat = useChat(agent?.id);

    // Charger l'agent
    useEffect(() => {
        getAgent(slug)
            .then((data) => {
                setAgent(data.agent);
            })
            .catch(console.error)
            .finally(() => setLoadingAgent(false));
    }, [slug]);

    // Charger les conversations existantes
    useEffect(() => {
        if (agent?.id) {
            getConversations(agent.id)
                .then((data) => setConversations(data.data || []))
                .catch(console.error);
        }
    }, [agent?.id, chat.conversationId]);

    // Charger une conversation depuis l'URL
    useEffect(() => {
        if (routeConvId) {
            chat.loadConversation(routeConvId);
        }
    }, [routeConvId]);

    if (loadingAgent) {
        return (
            <div className="h-full flex items-center justify-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-800"></div>
            </div>
        );
    }

    if (!agent) {
        return (
            <div className="p-8">
                <div className="p-4 bg-red-50 border border-red-200 text-red-700 rounded">
                    Agent introuvable.
                </div>
            </div>
        );
    }

    const handleSend = (text) => {
        chat.send(text);
    };

    return (
        <div className="h-full flex">
            {/* Sidebar conversations */}
            <div className="w-64 bg-white border-r border-gray-200 flex flex-col">
                <div className="p-4 border-b border-gray-200">
                    <div className="flex items-center">
                        <div
                            className="w-8 h-8 rounded flex items-center justify-center text-white text-sm font-bold"
                            style={{ backgroundColor: agent.couleur || '#3b82f6' }}
                        >
                            {agent.nom.charAt(0)}
                        </div>
                        <div className="ml-3">
                            <h2 className="font-semibold text-gray-800 text-sm">{agent.nom}</h2>
                        </div>
                    </div>
                    <button
                        onClick={chat.reset}
                        className="mt-3 w-full text-xs bg-blue-50 text-blue-700 hover:bg-blue-100 rounded py-1.5 transition-colors"
                    >
                        + Nouvelle conversation
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto">
                    {conversations.map((conv) => (
                        <button
                            key={conv.id}
                            onClick={() => chat.loadConversation(conv.id)}
                            className={`w-full text-left px-4 py-3 text-sm border-b border-gray-100 hover:bg-gray-50 transition-colors ${
                                chat.conversationId === conv.id ? 'bg-blue-50 text-blue-800' : 'text-gray-600'
                            }`}
                        >
                            <p className="truncate">{conv.titre || 'Conversation'}</p>
                            <p className="text-xs text-gray-400 mt-0.5">
                                {new Date(conv.updated_at).toLocaleDateString('fr-FR')}
                            </p>
                        </button>
                    ))}
                </div>
            </div>

            {/* Zone de chat */}
            <div className="flex-1 flex flex-col">
                <ChatWindow
                    messages={chat.messages}
                    streamingContent={chat.streamingContent}
                    isStreaming={chat.isStreaming}
                />
                <ChatInput
                    onSend={handleSend}
                    disabled={chat.loading || chat.isStreaming}
                />
            </div>
        </div>
    );
}
