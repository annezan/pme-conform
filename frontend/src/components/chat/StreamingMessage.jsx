/**
 * Composant StreamingMessage premium — Reponse en cours.
 */

import { CpuChipIcon } from '@heroicons/react/24/solid';

export default function StreamingMessage({ content }) {
    if (!content) return null;

    return (
        <div className="flex gap-3 py-4 animate-fadeIn">
            <div className="shrink-0">
                <div className="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-sm">
                    <CpuChipIcon className="w-5 h-5 text-white" />
                </div>
            </div>
            <div className="max-w-[75%]">
                <div className="inline-block rounded-2xl rounded-tl-md px-4 py-3 bg-white border border-blue-200 text-gray-800 shadow-sm text-sm leading-relaxed">
                    <div className="whitespace-pre-wrap">
                        {content}
                        <span className="inline-block w-1.5 h-4 bg-blue-500 ml-0.5 rounded-sm animate-pulse" />
                    </div>
                </div>
            </div>
        </div>
    );
}
