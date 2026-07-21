/**
 * Composant ChatWindow premium — Zone d'affichage des messages.
 */

import { useRef, useEffect } from 'react';
import MessageBubble from './MessageBubble';
import StreamingMessage from './StreamingMessage';
import { CpuChipIcon } from '@heroicons/react/24/outline';

export default function ChatWindow({ messages, streamingContent, isStreaming }) {
    const bottomRef = useRef(null);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, streamingContent]);

    return (
        <div className="flex-1 overflow-y-auto px-6 py-6 bg-gradient-to-b from-slate-50 to-white">
            {messages.length === 0 && !isStreaming && (
                <div className="flex flex-col items-center justify-center h-full text-center py-20">
                    <div className="w-16 h-16 bg-blue-50 rounded-2xl flex items-center justify-center mb-4">
                        <CpuChipIcon className="w-8 h-8 text-blue-500" />
                    </div>
                    <h3 className="text-lg font-semibold text-gray-800">Commencez la conversation</h3>
                    <p className="text-sm text-gray-400 mt-1 max-w-sm">Posez votre question à l'agent IA. Il répondra en se basant sur ses connaissances et les documents disponibles.</p>
                </div>
            )}

            <div className="max-w-3xl mx-auto space-y-1">
                {messages.map((msg, index) => (
                    <MessageBubble key={msg.id || index} message={msg} />
                ))}
                {isStreaming && <StreamingMessage content={streamingContent} />}
            </div>

            <div ref={bottomRef} />
        </div>
    );
}
