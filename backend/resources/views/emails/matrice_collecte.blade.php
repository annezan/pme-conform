<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Matrice de collecte initiale</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fa;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;background:#f5f7fa;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" style="width:600px;max-width:96%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#2563eb,#4f46e5);padding:24px;color:#ffffff;">
                            <h1 style="margin:0;font-size:20px;font-weight:700;">PME-CONFORM</h1>
                            <p style="margin:6px 0 0;font-size:13px;opacity:.9;">Programme de mise en conformite ARTCI</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px 28px 8px;">
                            <h2 style="margin:0 0 12px;font-size:18px;color:#111827;">Bonjour {{ $client->raison_sociale }},</h2>
                            <p style="margin:0 0 12px;line-height:1.6;font-size:14px;">
                                AS Consulting demarre votre mission de conformite
                                <strong>{{ $mission->reference }} - {{ $mission->titre }}</strong>.
                            </p>
                            <p style="margin:0 0 12px;line-height:1.6;font-size:14px;">
                                Premiere etape : <strong>la matrice de collecte initiale</strong>. Elle nous
                                permettra de cartographier vos processus, identifier les services qui manipulent
                                des donnees personnelles et obtenir l'organigramme de l'entreprise.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px;">
                            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px;">
                                <p style="margin:0 0 8px;font-weight:700;color:#1d4ed8;">Vous avez deux options :</p>
                                <ol style="margin:0;padding-left:20px;font-size:14px;line-height:1.6;color:#1e40af;">
                                    <li>Repondre directement en ligne via votre espace client.</li>
                                    <li>Telecharger la matrice ci-jointe, la remplir, puis l'uploader.</li>
                                </ol>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:24px 28px;">
                            <a href="{{ $urlEspaceClient }}" style="display:inline-block;padding:12px 28px;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;font-size:14px;">
                                Acceder a la matrice en ligne
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 28px;font-size:12px;color:#6b7280;line-height:1.5;">
                            <p style="margin:0;">
                                <strong>Delai :</strong> au plus tard 72h avant le demarrage des ateliers.
                            </p>
                            <p style="margin:8px 0 0;">
                                Pour toute question, contactez votre consultant : {{ $mission->responsable->prenom ?? '' }} {{ $mission->responsable->nom ?? '' }}{{ $mission->responsable?->email ? ' - ' . $mission->responsable->email : '' }}.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f9fafb;padding:16px 28px;text-align:center;font-size:11px;color:#9ca3af;">
                            PME-CONFORM &copy; {{ date('Y') }} AS Consulting. Cet email est genere automatiquement.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
