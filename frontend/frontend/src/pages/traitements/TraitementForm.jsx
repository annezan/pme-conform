/**
 * TraitementForm — Fiche de traitement modele MOBISOFT.
 *
 * Sections en accordeon, chacune avec un tableau editable :
 *   1. Identification (designation, code, description, pole, dates...)
 *   2. Supports (materiel/logiciel/papier)
 *   3. Actes & bases legales
 *   4. Personnes concernees
 *   5. Categories de donnees (avec origine direct/indirect + sensible)
 *   6. Transferts hors CEDEAO
 *   7. Mesures de securite (par categorie)
 */

import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import api, { getCsrfCookie } from '@/api/client';
import { alertSuccess, alertError } from '@/utils/alerts';
import PageHeader from '@/components/ui/PageHeader';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import Badge from '@/components/ui/Badge';
import { Input, Textarea } from '@/components/ui/Input';
import { ChevronDownIcon, ChevronRightIcon, PlusIcon, TrashIcon, ArrowLeftIcon, CheckIcon } from '@heroicons/react/24/outline';

const FORM_INITIAL = {
    code_finalite: '',
    designation: '',
    description: '',
    direction_pole: '',
    services_charges: [],
    sources: [],
    date_creation_fiche: new Date().toISOString().slice(0, 10),
    date_maj_fiche: new Date().toISOString().slice(0, 10),
    supports: [],
    actes: [],
    personnes: [],
    categoriesDonnees: [],
    transferts: [],
    mesuresSecurite: [],
};

const SUPPORT_CATS = [
    { value: '', label: '— Sélectionner —' },
    { value: 'materiel', label: 'Matériels' },
    { value: 'logiciel', label: 'Logiciel' },
    { value: 'papier', label: 'Papier' },
    { value: 'autre', label: 'Autres (Précisez)' },
];

// Mapping catégorie → liste de types proposés, repris du modèle MOBISOFT
// (feuilles Fiche T1-T5, section SUPPORTS DU TRAITEMENT, L21-L34).
// Le type "Autres (Précisez)" ouvre un champ libre via la colonne Précision.
const SUPPORT_TYPES_PAR_CAT = {
    materiel: ['Ordinateur Portable', 'Ordinateur Fixe', 'Tablette', 'Téléphone', 'Autres'],
    logiciel: ['Logiciel Métier', 'Logiciel Bureautique', 'Application', 'Autres (Précisez)'],
    papier: ['Registre', 'Papier (simple)', 'Papier (Préimprimé)', 'Autres (Précisez)'],
    autre: ['Autres (Précisez)'],
};

// Helper qui transforme un tableau de strings en options [{value, label}]
// avec une entrée placeholder en tête.
const optionsTypeSupport = (categorie) => {
    const liste = SUPPORT_TYPES_PAR_CAT[categorie] || [];
    if (!categorie) {
        return [{ value: '', label: '— Choisissez d\'abord une catégorie —' }];
    }
    return [{ value: '', label: '— Sélectionner un type —' }, ...liste.map(t => ({ value: t, label: t }))];
};
const ORIGINES = [
    { value: 'direct', label: 'Directe' },
    { value: 'indirect', label: 'Indirecte' },
];
const MESURE_CATS = [
    { value: 'controle_acces', label: "Contrôle d'accès des utilisateurs" },
    { value: 'tracabilite', label: 'Mesures de traçabilité' },
    { value: 'protection_logiciels', label: 'Protection des logiciels' },
    { value: 'sauvegarde', label: 'Sauvegarde des données' },
    { value: 'chiffrement', label: 'Chiffrement des données' },
    { value: 'controle_sous_traitants', label: 'Contrôle des sous-traitants' },
    { value: 'autres', label: 'Autres mesures' },
];

// Actes de traitement canoniques (terminologie standard CNIL / ARTCI).
const ACTES = [
    { value: 'Création, copie, Enregistrement', label: 'Création, copie, Enregistrement' },
    { value: 'Conservation / Stockage', label: 'Conservation / Stockage' },
    { value: 'Modification', label: 'Modification' },
    { value: 'Extraction / Consultation', label: 'Extraction / Consultation' },
    { value: 'Utilisation', label: 'Utilisation' },
    { value: 'Communication par transmission', label: 'Communication par transmission' },
    { value: 'Interconnexion / Rapprochement', label: 'Interconnexion / Rapprochement' },
    { value: 'Verrouillage', label: 'Verrouillage' },
    { value: 'Effacement', label: 'Effacement' },
    { value: 'Destruction', label: 'Destruction' },
];

// Bases légales (Loi 2013-450 / RGPD art. 6).
const BASES_LEGALES = [
    { value: 'Consentement', label: 'Consentement' },
    { value: 'Contrat', label: 'Contrat' },
    { value: 'Obligation légale', label: 'Obligation légale' },
    { value: 'Intérêt légitime', label: 'Intérêt légitime' },
    { value: 'Mission de service public', label: 'Mission de service public' },
    { value: 'Sauvegarde d\'intérêts vitaux', label: 'Sauvegarde d\'intérêts vitaux' },
];

// Catalogue des catégories de données personnelles (modèle MOBISOFT).
// Mirroir du backend RegistreMobisoftExporter::catalogueDonnees(). Permet de
// pré-remplir le Select "Catégorie principale" + de proposer les sous-items
// (Détail) dépendants. Le flag `sensible` auto-coche est_sensible.
const CATEGORIES_DONNEES_CATALOG = [
    {
        titre: "État-civil, Identité, Données d'identification",
        sensible: false,
        items: ['Nom et Prénom', 'Âge', 'Date de naissance', 'Lieu de naissance', 'Genre (M/F)',
            'Adresse postale', 'Adresse Fiscale', 'Adresse mail', 'Photographie', 'Signature', 'Nationalité'],
    },
    {
        titre: 'Vie personnelle',
        sensible: false,
        items: ['Habitude de vie', 'Situation familiale', "Nombre d'enfants"],
    },
    {
        titre: 'Vie professionnelle',
        sensible: false,
        items: ["Date d'embauche", 'Situation Professionnelle', 'Curriculum Vitae (CV)',
            'Scolarité', 'Formation Distinction', 'Numéro matricule'],
    },
    {
        titre: "Informations d'ordre économique et financier",
        sensible: false,
        items: ['Revenus', 'Salaire', 'Situation financière', "Relevé d'identité bancaire (RIB)"],
    },
    {
        titre: 'Données de connexion (Adresse IP, logs, etc.)',
        sensible: false,
        items: ['Identifiants des terminaux', 'Identifiants de connexions', "Information d'horodatage"],
    },
    {
        titre: 'Données de localisation (déplacements, données GPS, GSM, etc.)',
        sensible: false,
        items: ['Localisation par satellite', 'Localisation par whatsapp',
            'Localisation par téléphone mobile', 'Données GPS collectées de façon directe ou autre'],
    },
    {
        titre: "Numéro national d'identification / (ou autre identifiant de la même nature)",
        sensible: false,
        items: ['Numéro téléphone', "Carte nationale d'identité (CNI)", 'Passeport',
            'Titre de séjour', 'Permis de conduire', 'Numéro de sécurité sociale',
            'Numéro CMU', 'Numéro extrait de naissance'],
    },
    {
        titre: 'Données biométriques',
        sensible: true,
        items: ['Contour de la main', 'Empreintes digitales', 'Reconnaissance vocale',
            'Reconnaissance faciale', "Iris de l'œil"],
    },
    {
        titre: 'Données de santé (Données sensibles)',
        sensible: true,
        items: ['Pathologie', 'Affection', 'Antécédents familiaux', 'Données relatives aux soins'],
    },
    {
        titre: 'Autres données sensibles (Données sensibles)',
        sensible: true,
        items: ['Origines raciales ou ethniques', 'Opinions politiques', 'Opinions religieuses',
            'Appartenance syndicale', 'Vie sexuelle'],
    },
    {
        titre: 'Infractions, condamnations, mesures de sûreté (Données sensibles)',
        sensible: true,
        items: ['Infractions', 'Condamnations', 'Mesures de sûreté'],
    },
    {
        titre: 'Appréciation sur les difficultés sociales des personnes (Données sensibles)',
        sensible: true,
        items: ['Précisez :'],
    },
];

// Options du Select "Catégorie principale" — placeholder + 12 catégories.
const CATEGORIES_PRINCIPALES_OPTIONS = [
    { value: '', label: '— Sélectionner une catégorie —' },
    ...CATEGORIES_DONNEES_CATALOG.map(c => ({ value: c.titre, label: c.titre })),
    { value: 'Autre', label: 'Autre (saisie libre)' },
];

// Options du Select "Détail" en fonction de la catégorie choisie.
const optionsDetailDonnees = (categoriePrincipale) => {
    const cat = CATEGORIES_DONNEES_CATALOG.find(c => c.titre === categoriePrincipale);
    if (!cat) return [{ value: '', label: '— Choisissez d\'abord une catégorie —' }];
    return [
        { value: '', label: '— Sélectionner un détail —' },
        ...cat.items.map(i => ({ value: i, label: i })),
        { value: 'Autre', label: 'Autre (saisie libre)' },
    ];
};

// Indique si une catégorie principale est marquée sensible dans MOBISOFT.
const categorieEstSensible = (titre) => {
    const cat = CATEGORIES_DONNEES_CATALOG.find(c => c.titre === titre);
    return cat ? cat.sensible : false;
};

// Liste alphabétique des pays (ISO 3166, noms officiels en français).
// Réutilisée pour le champ Pays de la section Transferts hors CEDEAO.
const PAYS = [
    'Afghanistan', 'Afrique du Sud', 'Albanie', 'Algérie', 'Allemagne', 'Andorre',
    'Angola', 'Antigua-et-Barbuda', 'Arabie saoudite', 'Argentine', 'Arménie',
    'Australie', 'Autriche', 'Azerbaïdjan', 'Bahamas', 'Bahreïn', 'Bangladesh',
    'Barbade', 'Belgique', 'Belize', 'Bénin', 'Bermudes', 'Bhoutan', 'Biélorussie',
    'Birmanie', 'Bolivie', 'Bosnie-Herzégovine', 'Botswana', 'Brésil', 'Brunei',
    'Bulgarie', 'Burkina Faso', 'Burundi', 'Cambodge', 'Cameroun', 'Canada',
    'Cap-Vert', 'Chili', 'Chine', 'Chypre', 'Colombie', 'Comores', 'Congo',
    'Corée du Nord', 'Corée du Sud', 'Costa Rica', "Côte d'Ivoire", 'Croatie',
    'Cuba', 'Danemark', 'Djibouti', 'Dominique', 'Égypte', 'Émirats arabes unis',
    'Équateur', 'Érythrée', 'Espagne', 'Estonie', 'États-Unis', 'Éthiopie',
    'Fidji', 'Finlande', 'France', 'Gabon', 'Gambie', 'Géorgie', 'Ghana',
    'Gibraltar', 'Grèce', 'Grenade', 'Groenland', 'Guatemala', 'Guernesey',
    'Guinée', 'Guinée équatoriale', 'Guinée-Bissau', 'Guyana', 'Haïti',
    'Honduras', 'Hong-Kong', 'Hongrie', 'Île de Man', 'Îles Féroé', 'Inde',
    'Indonésie', 'Iran', 'Iraq', 'Irlande', 'Islande', 'Israël', 'Italie',
    'Jamaïque', 'Japon', 'Jersey', 'Jordanie', 'Kazakhstan', 'Kenya',
    'Kirghizistan', 'Kiribati', 'Kosovo', 'Koweït', 'Laos', 'Lesotho',
    'Lettonie', 'Liban', 'Libéria', 'Libye', 'Liechtenstein', 'Lituanie',
    'Luxembourg', 'Macao', 'Macédoine du Nord', 'Madagascar', 'Malaisie',
    'Malawi', 'Maldives', 'Mali', 'Malte', 'Maroc', 'Maurice', 'Mauritanie',
    'Mexique', 'Micronésie', 'Moldavie', 'Monaco', 'Mongolie', 'Monténégro',
    'Mozambique', 'Namibie', 'Nauru', 'Népal', 'Nicaragua', 'Niger', 'Nigéria',
    'Norvège', 'Nouvelle-Zélande', 'Oman', 'Ouganda', 'Ouzbékistan', 'Pakistan',
    'Palaos', 'Palestine', 'Panama', 'Papouasie-Nouvelle-Guinée', 'Paraguay',
    'Pays-Bas', 'Pérou', 'Philippines', 'Pologne', 'Portugal', 'Qatar',
    'République centrafricaine', 'République dominicaine', 'République tchèque',
    'République démocratique du Congo', 'Roumanie', 'Royaume-Uni', 'Russie',
    'Rwanda', 'Saint-Kitts-et-Nevis', 'Saint-Marin', 'Saint-Vincent-et-les-Grenadines',
    'Sainte-Lucie', 'Salomon', 'Salvador', 'Samoa', 'São Tomé-et-Principe',
    'Sénégal', 'Serbie', 'Seychelles', 'Sierra Leone', 'Singapour', 'Slovaquie',
    'Slovénie', 'Somalie', 'Soudan', 'Soudan du Sud', 'Sri Lanka', 'Suède',
    'Suisse', 'Suriname', 'Syrie', 'Tadjikistan', 'Taïwan', 'Tanzanie', 'Tchad',
    'Thaïlande', 'Timor oriental', 'Togo', 'Tonga', 'Trinité-et-Tobago',
    'Tunisie', 'Turkménistan', 'Turquie', 'Tuvalu', 'Ukraine', 'Uruguay',
    'Vanuatu', 'Vatican', 'Venezuela', 'Vietnam', 'Yémen', 'Zambie', 'Zimbabwe',
].map(p => ({ value: p, label: p }));

// Generateur de slug pour code_finalite (11 caracteres alphanumeriques minuscules,
// pattern proche de MOBISOFT : type "nj8tdzhllyk").
function genererCodeFinalite() {
    const alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let code = '';
    for (let i = 0; i < 11; i++) {
        code += alphabet[Math.floor(Math.random() * alphabet.length)];
    }
    return code;
}

const SECTIONS = [
    { id: 'identification', titre: 'Identification' },
    { id: 'supports', titre: 'Supports du traitement' },
    { id: 'actes', titre: 'Actes & base légale' },
    { id: 'personnes', titre: 'Personnes concernées' },
    { id: 'donnees', titre: 'Catégories de données' },
    { id: 'transferts', titre: 'Transferts hors CEDEAO' },
    { id: 'securite', titre: 'Mesures de sécurité' },
];

export default function TraitementForm() {
    const { id } = useParams();
    const navigate = useNavigate();
    const editing = !!id;
    // Code finalite auto-genere immediatement a l'ouverture du formulaire de
    // creation, pour qu'il soit visible des le rendu initial (et donc envoye
    // au backend meme si l'utilisateur ne touche pas au champ).
    const [form, setForm] = useState({ ...FORM_INITIAL, code_finalite: genererCodeFinalite() });
    const [clientId, setClientId] = useState('');
    const [clients, setClients] = useState([]);
    const [openSection, setOpenSection] = useState({ identification: true });
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        api.get('/clients?per_page=200').then(r => setClients(r.data.data || []));
        if (editing) {
            api.get(`/traitements/${id}`).then(r => {
                const t = r.data.traitement;
                setClientId(t.client_id);
                setForm({
                    // Auto-generation en edition si le traitement n'a pas (encore)
                    // de code finalite (ancienne donnee, import, etc.).
                    code_finalite: t.code_finalite || genererCodeFinalite(),
                    designation: t.designation || '',
                    description: t.description || '',
                    direction_pole: t.direction_pole || '',
                    services_charges: t.services_charges || [],
                    sources: t.sources || [],
                    date_creation_fiche: t.date_creation_fiche || new Date().toISOString().slice(0, 10),
                    date_maj_fiche: t.date_maj_fiche || new Date().toISOString().slice(0, 10),
                    supports: t.supports || [],
                    actes: t.actes || [],
                    personnes: t.personnes || [],
                    // Mapping inverse pour les categories de donnees : si la
                    // valeur stockee n'est pas dans le catalogue MOBISOFT, on
                    // l'affiche comme "Autre (saisie libre)" et on met la
                    // valeur originale dans le champ transient *_autre pour
                    // qu'elle reste editable.
                    categoriesDonnees: (t.categories_donnees || []).map(d => {
                        const out = { ...d };
                        const cat = CATEGORIES_DONNEES_CATALOG.find(c => c.titre === d.categorie_principale);
                        if (d.categorie_principale && !cat) {
                            out.categorie_principale_autre = d.categorie_principale;
                            out.categorie_principale = 'Autre';
                        }
                        // Pour le detail : on regarde dans la categorie matchee
                        // (apres le mapping ci-dessus, c'est l'eventuelle categorie
                        // canonique) — si le detail n'y est pas, c'est un Autre.
                        const catFinale = CATEGORIES_DONNEES_CATALOG.find(c => c.titre === (cat?.titre || out.categorie_principale));
                        if (d.detail && catFinale && !catFinale.items.includes(d.detail)) {
                            out.detail_autre = d.detail;
                            out.detail = 'Autre';
                        }
                        return out;
                    }),
                    transferts: t.transferts || [],
                    mesuresSecurite: t.mesures_securite || [],
                });
            }).catch(() => alertError('Impossible de charger ce traitement'));
        } else {
            // Phase 5 : pré-remplissage automatique des champs depuis le profil
            // client. Echec silencieux : si l'endpoint refuse (ex. user sans
            // entreprise rattachée), le formulaire reste vide.
            api.get('/traitements/preremplir')
                .then(r => {
                    const p = r.data?.preremplissage;
                    if (!p) return;
                    if (p.client_id) setClientId(p.client_id);
                    setForm(f => ({
                        ...f,
                        direction_pole: p.direction_pole || f.direction_pole,
                        services_charges: p.services_charges?.length ? p.services_charges : f.services_charges,
                        date_creation_fiche: p.date_creation_fiche || f.date_creation_fiche,
                    }));
                })
                .catch(() => { /* user interne sans entreprise = pas de prefill, normal */ });
        }
    }, [id, editing]);

    const toggleSection = (sid) => setOpenSection(s => ({ ...s, [sid]: !s[sid] }));
    const ajouterLigne = (champ, modele) => setForm(f => ({ ...f, [champ]: [...(f[champ] || []), modele] }));
    const modifierLigne = (champ, idx, patch) => setForm(f => ({ ...f, [champ]: f[champ].map((l, i) => i === idx ? { ...l, ...patch } : l) }));
    const supprimerLigne = (champ, idx) => setForm(f => ({ ...f, [champ]: f[champ].filter((_, i) => i !== idx) }));

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!form.designation.trim()) {
            alertError('La désignation est obligatoire');
            return;
        }
        setSaving(true);
        try {
            await getCsrfCookie();
            // Auto-calculs : derives systematiquement des relations enfants
            // pour eviter les incoherences (case cochee mais aucune ligne).
            const contientSensibles = (form.categoriesDonnees || []).some(d => d.est_sensible === true || d.est_sensible === 'true');
            const transfertHorsCedeao = (form.transferts || []).filter(t => (t.organe || t.pays || '').trim() !== '').length > 0;

            // Auto-generation du code_finalite a la creation s'il n'a pas
            // ete saisi par l'utilisateur (pattern MOBISOFT : 11 chars).
            const code = (form.code_finalite || '').trim() || (editing ? '' : genererCodeFinalite());

            // Mapping des champs "Autre (saisie libre)" : on remplace la valeur
            // "Autre" par le contenu du champ _autre correspondant, puis on
            // supprime ces champs transients avant l'envoi au backend (qui
            // n'a pas de colonnes categorie_principale_autre / detail_autre).
            const categoriesDonneesPayload = (form.categoriesDonnees || []).map(d => {
                const out = { ...d };
                if (out.categorie_principale === 'Autre' && out.categorie_principale_autre) {
                    out.categorie_principale = out.categorie_principale_autre;
                }
                if (out.detail === 'Autre' && out.detail_autre) {
                    out.detail = out.detail_autre;
                }
                delete out.categorie_principale_autre;
                delete out.detail_autre;
                return out;
            });

            const payload = {
                ...form,
                categoriesDonnees: categoriesDonneesPayload,
                code_finalite: code,
                contient_donnees_sensibles: contientSensibles,
                transfert_hors_cedeao: transfertHorsCedeao,
            };
            if (!editing) payload.client_id = clientId;
            const res = editing
                ? await api.put(`/traitements/${id}`, payload)
                : await api.post('/traitements', payload);
            alertSuccess(res.data.message || 'Enregistré');
            navigate(`/traitements/${res.data.traitement.id}`);
        } catch (err) {
            alertError(err.response?.data?.message || 'Erreur');
        } finally {
            setSaving(false);
        }
    };

    const compteur = (sid) => ({
        supports: form.supports.length,
        actes: form.actes.length,
        personnes: form.personnes.length,
        donnees: form.categoriesDonnees.length,
        transferts: form.transferts.length,
        securite: form.mesuresSecurite.length,
    }[sid] || 0);

    return (
        <div className="p-6 lg:p-8 max-w-6xl mx-auto">
            <Button variant="ghost" onClick={() => navigate('/traitements')} className="mb-3">
                <ArrowLeftIcon className="w-4 h-4" /> Retour
            </Button>
            <PageHeader
                title={editing ? 'Modifier le traitement' : 'Nouvelle fiche de traitement'}
                subtitle="Modèle MOBISOFT - registre des activités de traitement"
            />

            <form onSubmit={handleSubmit} className="space-y-3">
                {!editing && (
                    <Card className="p-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">Client *</label>
                        <select value={clientId} onChange={e => setClientId(e.target.value)} required className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">Sélectionnez un client</option>
                            {clients.map(c => <option key={c.id} value={c.id}>{c.raison_sociale}</option>)}
                        </select>
                    </Card>
                )}

                {SECTIONS.map(sec => (
                    <Card key={sec.id} className="overflow-hidden">
                        <button type="button" onClick={() => toggleSection(sec.id)} className="w-full flex items-center justify-between px-5 py-3 bg-gradient-to-r from-blue-50 to-indigo-50 hover:from-blue-100">
                            <div className="flex items-center gap-2">
                                {openSection[sec.id]
                                    ? <ChevronDownIcon className="w-5 h-5 text-blue-700" />
                                    : <ChevronRightIcon className="w-5 h-5 text-blue-700" />}
                                <span className="font-semibold text-gray-900">{sec.titre}</span>
                                {sec.id !== 'identification' && (
                                    <Badge variant="info">{compteur(sec.id)}</Badge>
                                )}
                            </div>
                        </button>
                        {openSection[sec.id] && (
                            <div className="p-5">
                                {sec.id === 'identification' && (
                                    <div className="space-y-3">
                                        <div className="grid grid-cols-2 gap-3">
                                            <Input label="Désignation *" value={form.designation} onChange={e => setForm({ ...form, designation: e.target.value })} required />
                                            <Input
                                                label="Code finalité"
                                                value={form.code_finalite}
                                                readOnly
                                                disabled
                                                helper="Code généré automatiquement — non modifiable."
                                                className="bg-gray-50"
                                            />
                                        </div>
                                        <Textarea label="Description de la finalité" value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} rows={3} />
                                        <div className="grid grid-cols-3 gap-3">
                                            <Input label="Direction / Pôle" value={form.direction_pole} onChange={e => setForm({ ...form, direction_pole: e.target.value })} placeholder="Ex: Pôle RH" />
                                            <Input label="Date de création de la fiche" type="date" value={form.date_creation_fiche} onChange={e => setForm({ ...form, date_creation_fiche: e.target.value })} />
                                            <Input
                                                label="Date de mise à jour de la fiche"
                                                type="date"
                                                value={form.date_maj_fiche || ''}
                                                readOnly
                                                disabled
                                                helper="Mise à jour automatique à chaque enregistrement."
                                                className="bg-gray-50"
                                            />
                                        </div>
                                        <Input label="Services chargés (séparés par virgule)" value={(form.services_charges || []).join(', ')} onChange={e => setForm({ ...form, services_charges: e.target.value.split(',').map(s => s.trim()).filter(Boolean) })} />
                                        <Input label="Sources (séparées par virgule)" value={(form.sources || []).join(', ')} onChange={e => setForm({ ...form, sources: e.target.value.split(',').map(s => s.trim()).filter(Boolean) })} />
                                    </div>
                                )}

                                {sec.id === 'supports' && (
                                    <TableEditable
                                        lignes={form.supports}
                                        colonnes={[
                                            {
                                                key: 'categorie',
                                                label: 'Catégorie',
                                                type: 'select',
                                                options: SUPPORT_CATS,
                                                // Au changement de categorie, on reinitialise le type
                                                // (les types disponibles dependent de la categorie).
                                                onChange: (row, value) => ({ categorie: value, type: '' }),
                                                // Si categorie = 'autre', un champ texte apparait pour
                                                // saisir la categorie personnalisee (ecrit dans precision).
                                                autreInputKey: 'precision',
                                                autrePlaceholder: 'Précisez la catégorie…',
                                            },
                                            {
                                                key: 'type',
                                                label: 'Type',
                                                type: 'select',
                                                // Options dynamiques calculees a partir de row.categorie
                                                options: (row) => optionsTypeSupport(row.categorie),
                                                // Si type contient "Autres", un champ texte apparait
                                                // pour saisir le type personnalise (ecrit dans precision).
                                                autreInputKey: 'precision',
                                                autrePlaceholder: 'Précisez le type…',
                                            },
                                            { key: 'marque_version', label: 'Marque/Version', type: 'text' },
                                            { key: 'precision', label: 'Précision', type: 'text' },
                                        ]}
                                        onAjouter={() => ajouterLigne('supports', { categorie: 'logiciel', type: '', marque_version: '', precision: '' })}
                                        onModifier={(i, p) => modifierLigne('supports', i, p)}
                                        onSupprimer={(i) => supprimerLigne('supports', i)}
                                    />
                                )}

                                {sec.id === 'actes' && (
                                    <TableEditable
                                        lignes={form.actes}
                                        colonnes={[
                                            { key: 'acte', label: 'Acte de traitement', type: 'select', options: [{ value: '', label: '— Sélectionner —' }, ...ACTES] },
                                            { key: 'base_legale', label: 'Base légale', type: 'select', options: [{ value: '', label: '— Sélectionner —' }, ...BASES_LEGALES] },
                                            { key: 'precision', label: 'Précision', type: 'text' },
                                        ]}
                                        onAjouter={() => ajouterLigne('actes', { acte: '', base_legale: '', precision: '' })}
                                        onModifier={(i, p) => modifierLigne('actes', i, p)}
                                        onSupprimer={(i) => supprimerLigne('actes', i)}
                                    />
                                )}

                                {sec.id === 'personnes' && (
                                    <TableEditable
                                        lignes={form.personnes}
                                        colonnes={[
                                            { key: 'categorie', label: 'Catégorie de personnes', type: 'text', placeholder: 'Ex: Salariés, Clients, Mineurs...' },
                                            { key: 'documentation_source', label: 'Documentation source', type: 'text' },
                                        ]}
                                        onAjouter={() => ajouterLigne('personnes', { categorie: '', documentation_source: '' })}
                                        onModifier={(i, p) => modifierLigne('personnes', i, p)}
                                        onSupprimer={(i) => supprimerLigne('personnes', i)}
                                    />
                                )}

                                {sec.id === 'donnees' && (
                                    <TableEditable
                                        lignes={form.categoriesDonnees}
                                        colonnes={[
                                            {
                                                key: 'categorie_principale',
                                                label: 'Catégorie principale',
                                                type: 'select',
                                                options: CATEGORIES_PRINCIPALES_OPTIONS,
                                                // Au changement : reset du detail (les sous-items
                                                // dependent de la categorie) ET auto-coche
                                                // est_sensible si la categorie est marquee sensible
                                                // (biometrie, sante, opinions, infractions, etc.).
                                                onChange: (row, value) => ({
                                                    categorie_principale: value,
                                                    detail: '',
                                                    est_sensible: categorieEstSensible(value),
                                                }),
                                                autreInputKey: 'categorie_principale_autre',
                                                autrePlaceholder: 'Précisez la catégorie…',
                                            },
                                            {
                                                key: 'detail',
                                                label: 'Détail',
                                                type: 'select',
                                                options: (row) => optionsDetailDonnees(row.categorie_principale),
                                                autreInputKey: 'detail_autre',
                                                autrePlaceholder: 'Précisez le détail…',
                                            },
                                            { key: 'origine', label: 'Origine', type: 'select', options: ORIGINES },
                                            { key: 'est_sensible', label: 'Sensible', type: 'checkbox' },
                                        ]}
                                        onAjouter={() => ajouterLigne('categoriesDonnees', { categorie_principale: '', detail: '', origine: 'direct', est_sensible: false })}
                                        onModifier={(i, p) => modifierLigne('categoriesDonnees', i, p)}
                                        onSupprimer={(i) => supprimerLigne('categoriesDonnees', i)}
                                    />
                                )}

                                {sec.id === 'transferts' && (
                                    <TableEditable
                                        lignes={form.transferts}
                                        colonnes={[
                                            { key: 'organe', label: 'Organe', type: 'text', placeholder: 'Ex: CONTABO' },
                                            { key: 'pays', label: 'Pays', type: 'select', options: [{ value: '', label: '— Sélectionner —' }, ...PAYS] },
                                            { key: 'garantie', label: 'Garantie', type: 'text' },
                                            { key: 'sens_groupe', label: 'Sens / Groupe', type: 'text' },
                                        ]}
                                        onAjouter={() => ajouterLigne('transferts', { organe: '', pays: '', garantie: '', sens_groupe: '' })}
                                        onModifier={(i, p) => modifierLigne('transferts', i, p)}
                                        onSupprimer={(i) => supprimerLigne('transferts', i)}
                                    />
                                )}

                                {sec.id === 'securite' && (
                                    <TableEditable
                                        lignes={form.mesuresSecurite}
                                        colonnes={[
                                            { key: 'categorie', label: 'Catégorie de mesure', type: 'select', options: MESURE_CATS },
                                            { key: 'description', label: 'Description', type: 'textarea' },
                                        ]}
                                        onAjouter={() => ajouterLigne('mesuresSecurite', { categorie: 'controle_acces', description: '' })}
                                        onModifier={(i, p) => modifierLigne('mesuresSecurite', i, p)}
                                        onSupprimer={(i) => supprimerLigne('mesuresSecurite', i)}
                                    />
                                )}
                            </div>
                        )}
                    </Card>
                ))}

                <div className="flex justify-end gap-3 pt-4">
                    <Button variant="secondary" type="button" onClick={() => navigate('/traitements')}>Annuler</Button>
                    <Button type="submit" disabled={saving}>
                        <CheckIcon className="w-4 h-4" />
                        {saving ? 'Enregistrement...' : (editing ? 'Mettre à jour' : 'Créer le traitement')}
                    </Button>
                </div>
            </form>
        </div>
    );
}

function TableEditable({ lignes, colonnes, onAjouter, onModifier, onSupprimer }) {
    return (
        <div>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="bg-gray-50 text-xs text-gray-600 uppercase">
                        <tr>
                            {colonnes.map(c => <th key={c.key} className="px-3 py-2 text-left">{c.label}</th>)}
                            <th className="px-3 py-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {lignes.length === 0 && (
                            <tr><td colSpan={colonnes.length + 1} className="px-3 py-6 text-center text-gray-500 italic">Aucune ligne. Cliquez sur "Ajouter" ci-dessous.</td></tr>
                        )}
                        {lignes.map((l, i) => (
                            <tr key={i}>
                                {colonnes.map(c => (
                                    <td key={c.key} className="px-2 py-1.5 align-top">
                                        {c.type === 'select' && (() => {
                                            // c.options peut etre soit un tableau statique, soit une
                                            // fonction (row) => options[] pour des choix dependant
                                            // d'une autre colonne de la meme ligne (ex: type qui
                                            // depend de categorie dans la section Supports).
                                            const options = typeof c.options === 'function' ? c.options(l) : c.options;
                                            const handleChange = (value) => {
                                                // c.onChange permet de modifier plusieurs champs d'un
                                                // coup (ex: changer categorie reinitialise type).
                                                const patch = c.onChange ? c.onChange(l, value) : { [c.key]: value };
                                                onModifier(i, patch);
                                            };
                                            // c.autreInputKey : si la valeur courante est un "Autre*",
                                            // on affiche un champ texte sous le select pour permettre
                                            // a l'utilisateur de saisir la valeur libre. Cette valeur
                                            // est ecrite dans le champ indique par autreInputKey
                                            // (typiquement 'precision' ou 'description').
                                            const valeurActuelle = l[c.key] || '';
                                            const estAutre = c.autreInputKey && /autres?\b|autre$/i.test(valeurActuelle);
                                            return (
                                                <div className="space-y-1">
                                                    <select
                                                        value={valeurActuelle}
                                                        onChange={e => handleChange(e.target.value)}
                                                        className="w-full px-2 py-1 border border-gray-300 rounded text-sm"
                                                    >
                                                        {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                                                    </select>
                                                    {estAutre && (
                                                        <input
                                                            type="text"
                                                            value={l[c.autreInputKey] || ''}
                                                            onChange={e => onModifier(i, { [c.autreInputKey]: e.target.value })}
                                                            placeholder={c.autrePlaceholder || 'Précisez la valeur…'}
                                                            className="w-full px-2 py-1 border border-amber-300 bg-amber-50/40 rounded text-sm text-amber-900 placeholder:text-amber-600"
                                                        />
                                                    )}
                                                </div>
                                            );
                                        })()}
                                        {c.type === 'checkbox' && (
                                            <input type="checkbox" checked={!!l[c.key]} onChange={e => onModifier(i, { [c.key]: e.target.checked })} className="w-4 h-4 mt-2" />
                                        )}
                                        {c.type === 'textarea' && (
                                            <textarea value={l[c.key] || ''} onChange={e => onModifier(i, { [c.key]: e.target.value })} className="w-full px-2 py-1 border border-gray-300 rounded text-sm" rows={2} placeholder={c.placeholder} />
                                        )}
                                        {(c.type === 'text' || !c.type) && (
                                            <input type="text" value={l[c.key] || ''} onChange={e => onModifier(i, { [c.key]: e.target.value })} className="w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder={c.placeholder} />
                                        )}
                                    </td>
                                ))}
                                <td className="px-2 py-1.5 text-right align-top">
                                    <button type="button" onClick={() => onSupprimer(i)} className="text-red-600 hover:bg-red-50 rounded p-1.5">
                                        <TrashIcon className="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="mt-3">
                <Button type="button" variant="secondary" onClick={onAjouter}>
                    <PlusIcon className="w-4 h-4" /> Ajouter une ligne
                </Button>
            </div>
        </div>
    );
}
