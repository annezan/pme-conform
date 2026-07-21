/**
 * Composant Badge premium.
 *
 * Props :
 * - variant : success | danger | warning | info | gray | purple | cyan | rose | amber | indigo
 * - size    : xs | sm | md
 * - dot     : affiche un point colore en prefixe
 * - solid   : version pleine (texte clair sur fond colore vif)
 */

const variants = {
    success: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    danger: 'bg-red-50 text-red-700 ring-red-600/20',
    warning: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    info: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    gray: 'bg-gray-50 text-gray-600 ring-gray-500/20',
    purple: 'bg-purple-50 text-purple-700 ring-purple-600/20',
    cyan: 'bg-cyan-50 text-cyan-700 ring-cyan-600/20',
    rose: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    amber: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    indigo: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
};

const dotColors = {
    success: 'bg-emerald-500',
    danger: 'bg-red-500',
    warning: 'bg-amber-500',
    info: 'bg-blue-500',
    gray: 'bg-gray-400',
    purple: 'bg-purple-500',
    cyan: 'bg-cyan-500',
    rose: 'bg-rose-500',
    amber: 'bg-amber-500',
    indigo: 'bg-indigo-500',
};

const solids = {
    success: 'bg-gradient-to-r from-emerald-500 to-teal-600 text-white shadow-sm shadow-emerald-500/20',
    danger: 'bg-gradient-to-r from-red-500 to-rose-600 text-white shadow-sm shadow-red-500/20',
    warning: 'bg-gradient-to-r from-amber-500 to-orange-600 text-white shadow-sm shadow-amber-500/20',
    info: 'bg-gradient-to-r from-blue-500 to-indigo-600 text-white shadow-sm shadow-blue-500/20',
    gray: 'bg-gradient-to-r from-gray-500 to-slate-600 text-white shadow-sm',
    purple: 'bg-gradient-to-r from-purple-500 to-fuchsia-600 text-white shadow-sm shadow-purple-500/20',
    cyan: 'bg-gradient-to-r from-cyan-500 to-blue-600 text-white shadow-sm shadow-cyan-500/20',
    rose: 'bg-gradient-to-r from-rose-500 to-red-600 text-white shadow-sm shadow-rose-500/20',
    amber: 'bg-gradient-to-r from-amber-400 to-yellow-500 text-white shadow-sm shadow-amber-500/20',
    indigo: 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-sm shadow-indigo-500/20',
};

const sizes = {
    xs: 'px-1.5 py-0.5 text-[10px]',
    sm: 'px-2 py-0.5 text-[11px]',
    md: 'px-2.5 py-1 text-xs',
};

export default function Badge({
    children,
    variant = 'gray',
    size = 'md',
    dot = false,
    solid = false,
    className = '',
}) {
    const v = solid ? (solids[variant] || solids.gray) : (variants[variant] || variants.gray);
    const ringCls = solid ? '' : 'ring-1';
    const dotCls = dotColors[variant] || dotColors.gray;
    return (
        <span className={`inline-flex items-center gap-1.5 rounded-md font-semibold ${ringCls} ${v} ${sizes[size] || sizes.md} ${className}`}>
            {dot && <span className={`w-1.5 h-1.5 rounded-full ${solid ? 'bg-white/80' : dotCls}`} />}
            {children}
        </span>
    );
}
