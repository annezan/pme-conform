/**
 * Composant StatCard premium — Carte KPI elegante.
 *
 * Props :
 * - titre, valeur, soustitre : textes
 * - icon : composant icone
 * - couleur : 'blue' | 'emerald' | 'purple' | 'cyan' | 'orange' | 'rose' | 'amber'
 * - tendance : nombre (+ = vert, - = rouge) — affiche un pill
 * - href / onClick : rend la carte cliquable
 * - variant : 'default' (icone gradient en coin) | 'filled' (toute la carte coloree)
 */

import { ArrowTrendingUpIcon, ArrowTrendingDownIcon } from '@heroicons/react/24/outline';

const PALETTES = {
    blue: { grad: 'from-blue-500 to-indigo-600', ring: 'ring-blue-500/15', filledBg: 'from-blue-50 to-indigo-50', text: 'text-blue-700' },
    emerald: { grad: 'from-emerald-500 to-teal-600', ring: 'ring-emerald-500/15', filledBg: 'from-emerald-50 to-teal-50', text: 'text-emerald-700' },
    purple: { grad: 'from-purple-500 to-fuchsia-600', ring: 'ring-purple-500/15', filledBg: 'from-purple-50 to-fuchsia-50', text: 'text-purple-700' },
    cyan: { grad: 'from-cyan-500 to-blue-600', ring: 'ring-cyan-500/15', filledBg: 'from-cyan-50 to-blue-50', text: 'text-cyan-700' },
    orange: { grad: 'from-orange-500 to-amber-600', ring: 'ring-orange-500/15', filledBg: 'from-orange-50 to-amber-50', text: 'text-orange-700' },
    rose: { grad: 'from-rose-500 to-red-600', ring: 'ring-rose-500/15', filledBg: 'from-rose-50 to-red-50', text: 'text-rose-700' },
    amber: { grad: 'from-amber-400 to-yellow-600', ring: 'ring-amber-500/15', filledBg: 'from-amber-50 to-yellow-50', text: 'text-amber-700' },
};

export default function StatCard({
    titre,
    valeur,
    soustitre,
    icon: Icon,
    couleur = 'blue',
    tendance,
    variant = 'default',
    onClick,
    className = '',
}) {
    const p = PALETTES[couleur] || PALETTES.blue;
    const clickable = !!onClick;
    const Tag = clickable ? 'button' : 'div';

    const tendanceUp = typeof tendance === 'number' && tendance > 0;
    const tendanceDown = typeof tendance === 'number' && tendance < 0;
    const TrendIcon = tendanceUp ? ArrowTrendingUpIcon : tendanceDown ? ArrowTrendingDownIcon : null;

    const baseCls = variant === 'filled'
        ? `relative overflow-hidden rounded-2xl bg-gradient-to-br ${p.filledBg} ring-1 ${p.ring} p-5 text-left ${clickable ? 'transition-all hover:-translate-y-0.5 hover:shadow-lg cursor-pointer' : ''}`
        : `relative overflow-hidden rounded-2xl bg-white ring-1 ring-gray-200/70 shadow-[0_1px_3px_rgba(15,23,42,0.04)] p-5 text-left ${clickable ? 'transition-all hover:-translate-y-0.5 hover:shadow-[0_18px_40px_-12px_rgba(30,64,175,0.18)] hover:ring-blue-200 cursor-pointer' : 'transition-shadow hover:shadow-md'}`;

    return (
        <Tag onClick={onClick} className={`${baseCls} ${className}`}>
            {/* Glow decoration */}
            <div className={`absolute -top-8 -right-8 w-24 h-24 rounded-full bg-gradient-to-br ${p.grad} opacity-10 blur-2xl pointer-events-none`} />

            <div className="relative flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-semibold uppercase tracking-wider text-gray-500">{titre}</p>
                    <p className="text-3xl font-bold text-gray-900 mt-2 tracking-tight tabular-nums">{valeur}</p>
                    {soustitre && <p className="text-xs text-gray-500 mt-1">{soustitre}</p>}

                    {TrendIcon && (
                        <span className={`inline-flex items-center gap-1 mt-3 px-2 py-0.5 rounded-md text-xs font-semibold ${tendanceUp ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20' : 'bg-red-50 text-red-700 ring-1 ring-red-600/20'}`}>
                            <TrendIcon className="w-3.5 h-3.5" />
                            {tendanceUp ? '+' : ''}{tendance}%
                        </span>
                    )}
                </div>
                {Icon && (
                    <div className={`shrink-0 w-11 h-11 rounded-xl bg-gradient-to-br ${p.grad} flex items-center justify-center shadow-md shadow-blue-500/10`}>
                        <Icon className="w-5 h-5 text-white" />
                    </div>
                )}
            </div>
        </Tag>
    );
}
