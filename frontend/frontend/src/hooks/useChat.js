/**
 * Hook useChat — Gere l'etat du chat avec un agent IA.
 *
 * Supporte l'envoi de messages en mode synchrone et streaming.
 */

import { useState, useCallback } from 'react';
import {
    createConversation,
    sendMessage,
    sendMessageStream,
    getConversation,
} from '@/api/conversations';

export function useChat(agentId) {
    const [messages, setMessages] = useState([]);
    const [conversationId, setConversationId] = useState(null);
    const [isStreaming, setIsStreaming] = useState(false);
    const [streamingContent, setStreamingContent] = useState('');
    const [sources, setSources] = useState([]);
    const [loading, setLoading] = useState(false);

    /**
     * Charge une conversation existante.
     */
    const loadConversation = useCallback(async (id) => {
        setLoading(true);
        try {
            const conversation = await getConversation(id);
            setConversationId(conversation.id);
            setMessages(conversation.messages || []);
        } catch (err) {
            console.error('Erreur chargement conversation:', err);
        } finally {
            setLoading(false);
        }
    }, []);

    /**
     * Envoie un message (mode synchrone).
     */
    const send = useCallback(async (text) => {
        // Ajouter le message utilisateur immediatement (optimistic UI)
        const userMsg = { role: 'user', contenu: text, id: Date.now() };
        setMessages((prev) => [...prev, userMsg]);
        setLoading(true);

        try {
            let data;
            if (conversationId) {
                data = await sendMessage(conversationId, text);
            } else {
                data = await createConversation(agentId, text);
                setConversationId(data.conversation.id);
            }

            // Ajouter la reponse de l'assistant
            setMessages((prev) => [...prev, data.message]);
            setSources(data.sources || []);
        } catch (err) {
            // Ajouter un message d'erreur
            setMessages((prev) => [
                ...prev,
                { role: 'assistant', contenu: 'Erreur : impossible de générer une réponse.', id: Date.now() + 1 },
            ]);
        } finally {
            setLoading(false);
        }
    }, [agentId, conversationId]);

    /**
     * Envoie un message (mode streaming).
     */
    const sendStream = useCallback(async (text) => {
        // Si pas encore de conversation, creer d'abord en mode sync
        if (!conversationId) {
            await send(text);
            return;
        }

        const userMsg = { role: 'user', contenu: text, id: Date.now() };
        setMessages((prev) => [...prev, userMsg]);
        setIsStreaming(true);
        setStreamingContent('');

        try {
            let fullContent = '';

            await sendMessageStream(
                conversationId,
                text,
                // onToken
                (token) => {
                    fullContent += token;
                    setStreamingContent(fullContent);
                },
                // onDone
                (meta) => {
                    setMessages((prev) => [
                        ...prev,
                        {
                            id: meta.message_id,
                            role: 'assistant',
                            contenu: fullContent,
                        },
                    ]);
                    setSources(meta.sources || []);
                    setStreamingContent('');
                    setIsStreaming(false);
                }
            );
        } catch (err) {
            setMessages((prev) => [
                ...prev,
                { role: 'assistant', contenu: 'Erreur : streaming interrompu.', id: Date.now() + 1 },
            ]);
            setIsStreaming(false);
            setStreamingContent('');
        }
    }, [conversationId, agentId, send]);

    /**
     * Reinitialise le chat (nouvelle conversation).
     */
    const reset = useCallback(() => {
        setMessages([]);
        setConversationId(null);
        setSources([]);
        setStreamingContent('');
        setIsStreaming(false);
    }, []);

    return {
        messages,
        conversationId,
        isStreaming,
        streamingContent,
        sources,
        loading,
        send,
        sendStream,
        loadConversation,
        reset,
    };
}
