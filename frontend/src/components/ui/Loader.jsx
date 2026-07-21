/**
 * Composant Loader — Indicateur de chargement.
 */

export default function Loader({ text = 'Chargement...', fullPage = false }) {
    const content = (
        <div className="flex flex-col items-center justify-center gap-3">
            <div className="relative">
                <div className="w-10 h-10 rounded-full border-[3px] border-gray-200"></div>
                <div className="absolute top-0 left-0 w-10 h-10 rounded-full border-[3px] border-blue-600 border-t-transparent animate-spin"></div>
            </div>
            {text && <p className="text-sm text-gray-500">{text}</p>}
        </div>
    );

    if (fullPage) {
        return <div className="min-h-screen flex items-center justify-center">{content}</div>;
    }

    return <div className="flex items-center justify-center py-12">{content}</div>;
}
