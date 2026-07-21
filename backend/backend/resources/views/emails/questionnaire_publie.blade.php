<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nouveau formulaire à remplir — PME-CONFORM</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f4f6f8; margin:0; padding:24px;">
    <div style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
        <h1 style="color:#1e40af; margin-top:0; font-size:22px;">Bonjour {{ $destinataire->prenom }} {{ $destinataire->nom }},</h1>

        <p style="color:#374151; line-height:1.6;">
            {{ $publiePar ?? 'AS Consulting' }} vient de publier un nouveau formulaire d'audit conformité
            @if($nomEntreprise)pour l'entreprise <strong>{{ $nomEntreprise }}</strong>@endif
            sur la plateforme <strong>PME-CONFORM</strong>.
        </p>

        <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:18px; margin:24px 0;">
            <p style="margin:0 0 8px 0; color:#0c4a6e; font-weight:700; font-size:16px;">
                {{ $questionnaire->titre }}
            </p>
            @if($questionnaire->description)
                <p style="margin:0 0 12px 0; color:#374151; font-size:14px; line-height:1.5;">
                    {{ $questionnaire->description }}
                </p>
            @endif
            <table style="width:100%; border-collapse:collapse; margin-top:12px;">
                <tr>
                    <td style="padding:4px 0; color:#374151; font-size:13px;">Pôle :</td>
                    <td style="padding:4px 0; color:#1e40af; font-weight:600; font-size:13px;">{{ $questionnaire->pole }}</td>
                </tr>
                @if($questionnaire->service)
                <tr>
                    <td style="padding:4px 0; color:#374151; font-size:13px;">Service :</td>
                    <td style="padding:4px 0; color:#1e40af; font-weight:600; font-size:13px;">{{ $questionnaire->service }}</td>
                </tr>
                @endif
                <tr>
                    <td style="padding:4px 0; color:#374151; font-size:13px;">Nombre de questions :</td>
                    <td style="padding:4px 0; color:#1e40af; font-weight:600; font-size:13px;">{{ $nbQuestions }}</td>
                </tr>
            </table>
        </div>

        <div style="text-align:center; margin:28px 0;">
            <a href="{{ $lienFormulaire }}"
               style="display:inline-block; background:#1e40af; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:600; font-size:15px;">
                Accéder au formulaire
            </a>
        </div>

        <p style="color:#6b7280; font-size:13px; line-height:1.5; margin-top:24px;">
            Vos réponses contribuent directement à l'analyse de conformité de votre entreprise
            au regard de la Loi N°2013-450 (Côte d'Ivoire). Vous pouvez sauvegarder votre progression
            et revenir compléter le formulaire à tout moment.
        </p>

        <hr style="border:none; border-top:1px solid #e5e7eb; margin:28px 0;">

        <p style="color:#9ca3af; font-size:12px; text-align:center; margin:0;">
            PME-CONFORM — AS Consulting<br>
            Cet e-mail est généré automatiquement, merci de ne pas y répondre.
        </p>
    </div>
</body>
</html>
