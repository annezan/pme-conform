/**
 * Composant MessageBubble premium — Message dans le chat.
 */

import { UserCircleIcon, CpuChipIcon } from '@heroicons/react/24/solid';

export default function MessageBubble({ message }) {
    const isUser = message.role === 'user';

    return (
        <div className={`flex gap-3 py-4 animate-fadeIn ${isUser ? 'flex-row-reverse' : ''}`}>
            {/* Avatar */}
            <div className="shrink-0">
                {isUser ? (
                    <div className="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center shadow-sm">
                        <UserCircleIcon className="w-5 h-5 text-white" />
                    </div>
                ) : (
                    <div className="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-sm">
                        <CpuChipIcon className="w-5 h-5 text-white" />
                    </div>
                )}
            </div>

            {/* Contenu */}
            <div className={`max-w-[75%] ${isUser ? 'text-right' : ''}`}>
                <div className={`inline-block rounded-2xl px-4 py-3 text-sm leading-relaxed ${
                    isUser
                        ? 'bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-tr-md'
                        : 'bg-white border border-gray-200 text-gray-800 rounded-tl-md shadow-sm'
                }`}>
                    <div className="whitespace-pre-wrap">{message.contenu}</div>
                </div>

                {/* Sources RAG */}
                {message.sources && message.sources.length > 0 && (
                    <div className="mt-2 flex flex-wrap gap-1">
                        {message.sources.map((source, i) => (
                            <span key={i} className="inline-flex items-center gap-1 text-[11px] bg-blue-50 text-blue-600 rounded-full px-2.5 py-0.5 ring-1 ring-blue-100">
                                {source.document_titre}{source.page && ` p.${source.page}`}
                            </span>
                        ))}
                    </div>
                )}

                {/* Metriques */}
                {message.duree_ms && (
                    <p className={`text-[11px] mt-1 ${isUser ? 'text-gray-400' : 'text-gray-400'}`}>
                        {(message.duree_ms / 1000).toFixed(1)}s
                    </p>
                )}
            </div>
        </div>
    );
}
