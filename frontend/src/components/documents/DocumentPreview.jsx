/**
 * Composant DocumentPreview — Affiche le contenu genere par le LLM.
 */

export default function DocumentPreview({ contenu }) {
    if (!contenu) return null;

    return (
        <div className="bg-white border border-gray-200 rounded-lg p-6 mt-4">
            <div className="flex justify-between items-center mb-4">
                <h3 className="font-semibold text-gray-800">Document généré</h3>
                <button
                    onClick={() => window.print()}
                    className="text-sm text-blue-600 hover:text-blue-800"
                >
                    Imprimer
                </button>
            </div>
            <div className="prose prose-sm max-w-none whitespace-pre-wrap text-gray-700 border-t pt-4">
                {contenu}
            </div>
        </div>
    );
}
