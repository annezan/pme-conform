/**
 * Composant Button premium.
 *
 * Variants : primary | secondary | danger | success | warning | ghost | outline | dark
 * Sizes    : xs | sm | md | lg
 * Props    : loading (bool), iconOnly (bool), as (Component pour le polymorphisme)
 */

const variants = {
    primary: 'bg-gradient-to-r from-blue-600 via-blue-700 to-indigo-700 text-white hover:from-blue-700 hover:via-blue-800 hover:to-indigo-800 shadow-md shadow-blue-500/25 hover:shadow-lg hover:shadow-blue-500/30 hover:-translate-y-0.5 active:translate-y-0 ring-1 ring-blue-700/10',
    secondary: 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 hover:ring-gray-300 hover:shadow-sm',
    outline: 'bg-transparent text-blue-700 ring-1 ring-blue-600/40 hover:bg-blue-50 hover:ring-blue-600',
    danger: 'bg-gradient-to-r from-red-600 to-rose-700 text-white hover:from-red-700 hover:to-rose-800 shadow-md shadow-red-500/25 hover:-translate-y-0.5 active:translate-y-0',
    success: 'bg-gradient-to-r from-emerald-600 to-teal-700 text-white hover:from-emerald-700 hover:to-teal-800 shadow-md shadow-emerald-500/25 hover:-translate-y-0.5 active:translate-y-0',
    warning: 'bg-gradient-to-r from-amber-500 to-orange-600 text-white hover:from-amber-600 hover:to-orange-700 shadow-md shadow-amber-500/25 hover:-translate-y-0.5 active:translate-y-0',
    dark: 'bg-gradient-to-r from-slate-800 to-slate-900 text-white hover:from-slate-900 hover:to-black shadow-md shadow-slate-800/25 hover:-translate-y-0.5 active:translate-y-0',
    ghost: 'bg-transparent text-gray-600 hover:bg-gray-100 hover:text-gray-900',
};

const sizes = {
    xs: 'px-2.5 py-1 text-[11px] gap-1',
    sm: 'px-3 py-1.5 text-xs gap-1.5',
    md: 'px-4 py-2 text-sm gap-2',
    lg: 'px-6 py-2.5 text-sm gap-2',
    xl: 'px-7 py-3 text-base gap-2.5',
};

const iconOnlySizes = {
    xs: 'p-1',
    sm: 'p-1.5',
    md: 'p-2',
    lg: 'p-2.5',
    xl: 'p-3',
};

export default function Button({
    children,
    variant = 'primary',
    size = 'md',
    iconOnly = false,
    loading = false,
    className = '',
    disabled,
    type = 'button',
    as: Tag = 'button',
    ...props
}) {
    const isDisabled = disabled || loading;
    const sizeCls = iconOnly ? iconOnlySizes[size] || iconOnlySizes.md : sizes[size] || sizes.md;
    return (
        <Tag
            type={Tag === 'button' ? type : undefined}
            className={`group relative inline-flex items-center justify-center font-semibold rounded-xl transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0 focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-500/20 ${variants[variant] || variants.primary} ${sizeCls} ${className}`}
            disabled={isDisabled}
            aria-busy={loading || undefined}
            {...props}
        >
            {loading && (
                <svg className="animate-spin h-4 w-4 -ml-0.5" viewBox="0 0 24 24" aria-hidden="true">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
            )}
            {children}
        </Tag>
    );
}
