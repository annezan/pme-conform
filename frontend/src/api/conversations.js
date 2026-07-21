/**
 * Client API — Conversations (Chat).
 */

import api, { getCsrfCookie } from './client';

export async function getConversations(agentId = null) {
    const params = agentId ? { agent_id: agentId } : {};
    const response = await api.get('/conversations', { params });
    return response.data;
}

export async function getConversation(id) {
    const response = await api.get(`/conversations/${id}`);
    return response.data.conversation;
}

export async function createConversation(agentId, message, missionId = null) {
    await getCsrfCookie();
    const response = await api.post('/conversations', {
        agent_id: agentId,
        message,
        mission_id: missionId,
    });
    return response.data;
}

export async function sendMessage(conversationId, message) {
    await getCsrfCookie();
    const response = await api.post(`/conversations/${conversationId}/messages`, {
        message,
    });
    return response.data;
}

/**
 * Envoie un message avec streaming SSE.
 * Retourne un objet avec un reader et une methode pour consommer les tokens.
 */
export async function sendMessageStream(conversationId, message, onToken, onDone) {
    await getCsrfCookie();

    const response = await fetch(`/api/conversations/${conversationId}/stream`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'text/event-stream',
            'X-XSRF-TOKEN': getCsrfTokenFromCookie(),
        },
        credentials: 'include',
        body: JSON.stringify({ message }),
    });

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop(); // Garder la derniere ligne incomplete

        for (const line of lines) {
            if (line.startsWith('data: ')) {
                try {
                    const data = JSON.parse(line.slice(6));
                    if (data.done) {
                        onDone?.(data);
                    } else if (data.token) {
                        onToken?.(data.token);
                    }
                } catch {
                    // Ignorer les lignes mal formees
                }
            }
        }
    }
}

function getCsrfTokenFromCookie() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
