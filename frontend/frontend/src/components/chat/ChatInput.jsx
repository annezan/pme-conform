/**
 * Composant ChatInput premium — Zone de saisie.
 */

import { useState } from 'react';
import { PaperAirplaneIcon } from '@heroicons/react/24/solid';

export default function ChatInput({ onSend, disabled }) {
    const [text, setText] = useState('');

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!text.trim() || disabled) return;
        onSend(text.trim());
        setText('');
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSubmit(e);
        }
    };

    return (
        <div className="border-t border-gray-200 bg-white px-6 py-4">
            <form onSubmit={handleSubmit} className="max-w-3xl mx-auto">
                <div className="flex items-end gap-3">
                    <div className="flex-1 relative">
                        <textarea
                            value={text}
                            onChange={(e) => setText(e.target.value)}
                            onKeyDown={handleKeyDown}
                            placeholder="Écrivez votre message... (Shift+Enter pour nouvelle ligne)"
                            disabled={disabled}
                            rows={1}
                            className="w-full px-4 py-3 rounded-xl border border-gray-300 text-sm resize-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 outline-none transition-all disabled:opacity-50 placeholder:text-gray-400 max-h-32 overflow-auto"
                            style={{ minHeight: '46px' }}
                            onInput={(e) => { e.target.style.height = 'auto'; e.target.style.height = Math.min(e.target.scrollHeight, 128) + 'px'; }}
                        />
                    </div>
                    <button
                        type="submit"
                        disabled={disabled || !text.trim()}
                        className="shrink-0 w-11 h-11 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white rounded-xl flex items-center justify-center shadow-sm shadow-blue-500/25 disabled:opacity-40 disabled:cursor-not-allowed transition-all"
                    >
                        <PaperAirplaneIcon className="w-5 h-5" />
                    </button>
                </div>
            </form>
        </div>
    );
}
