/**
 * Composant Card premium — Conteneur a fond clair ou en glassmorphism.
 *
 * Props :
 * - variant : 'default' | 'elevated' | 'glass' | 'gradient' | 'flat'
 * - hover   : applique un lift + glow au survol
 * - padded  : ajoute un padding interne (sinon le caller gere)
 */

const variants = {
    default: 'bg-white ring-1 ring-gray-200/70 shadow-[0_1px_3px_rgba(15,23,42,0.04)]',
    elevated: 'bg-white ring-1 ring-gray-200/60 shadow-[0_10px_30px_-12px_rgba(15,23,42,0.12)]',
    glass: 'bg-white/70 backdrop-blur-xl ring-1 ring-white/60 shadow-[0_10px_40px_-12px_rgba(30,64,175,0.18)]',
    gradient: 'bg-gradient-to-br from-white to-blue-50/40 ring-1 ring-blue-100/80 shadow-[0_4px_20px_-8px_rgba(59,130,246,0.18)]',
    flat: 'bg-gray-50/60 ring-1 ring-gray-200/60',
};

export default function Card({
    children,
    className = '',
    variant = 'default',
    hover = false,
    padded = false,
    as: Tag = 'div',
    ...props
}) {
    const hoverCls = hover
        ? 'transition-all duration-200 hover:-translate-y-0.5 hover:shadow-[0_18px_40px_-12px_rgba(30,64,175,0.22)] hover:ring-blue-200/80'
        : '';
    const padding = padded ? 'p-6' : '';

    return (
        <Tag className={`relative rounded-2xl ${variants[variant] || variants.default} ${hoverCls} ${padding} ${className}`} {...props}>
            {children}
        </Tag>
    );
}

export function CardHeader({ children, className = '', tight = false }) {
    return (
        <div className={`${tight ? 'px-5 py-3' : 'px-6 py-5'} border-b border-gray-100/80 ${className}`}>
            {children}
        </div>
    );
}

export function CardBody({ children, className = '', tight = false }) {
    return (
        <div className={`${tight ? 'px-5 py-4' : 'px-6 py-6'} ${className}`}>
            {children}
        </div>
    );
}

export function CardFooter({ children, className = '' }) {
    return (
        <div className={`px-6 py-4 border-t border-gray-100/80 bg-gray-50/40 rounded-b-2xl ${className}`}>
            {children}
        </div>
    );
}
