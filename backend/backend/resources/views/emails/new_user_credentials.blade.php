<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Vos identifiants PME-CONFORM</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f4f6f8; margin:0; padding:24px;">
    <div style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
        <h1 style="color:#1e40af; margin-top:0; font-size:22px;">Bienvenue {{ $user->prenom }} {{ $user->nom }},</h1>

        @if($nomEntreprise)
            <p style="color:#374151; line-height:1.6;">
                Un compte a été créé pour vous sur la plateforme <strong>PME-CONFORM</strong>
                par {{ $createPar ?? 'un administrateur' }} de l'entreprise <strong>{{ $nomEntreprise }}</strong>.
            </p>
        @else
            <p style="color:#374151; line-height:1.6;">
                Un compte a été créé pour vous sur la plateforme <strong>PME-CONFORM</strong>.
            </p>
        @endif

        <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:18px; margin:24px 0;">
            <p style="margin:0 0 12px 0; color:#0c4a6e; font-weight:600; font-size:14px;">Vos identifiants de connexion :</p>
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="padding:6px 0; color:#374151; font-size:14px;">Adresse e-mail :</td>
                    <td style="padding:6px 0; font-family:monospace; color:#1e40af; font-weight:600;">{{ $user->email }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0; color:#374151; font-size:14px;">Mot de passe temporaire :</td>
                    <td style="padding:6px 0; font-family:monospace; color:#9d174d; font-weight:700; font-size:15px; background:#fef2f2; padding:6px 10px; border-radius:4px;">{{ $motDePasseTemporaire }}</td>
                </tr>
            </table>
        </div>

        <div style="background:#fef3c7; border-left:4px solid #f59e0b; padding:14px 16px; border-radius:6px; margin:20px 0; color:#78350f; font-size:14px; line-height:1.5;">
            <strong>⚠️ Important :</strong> ce mot de passe est <strong>temporaire</strong>.
            Vous devrez le changer obligatoirement lors de votre première connexion.
            @if($expiration)
                <br><strong>Validité :</strong> jusqu'au {{ $expiration }}.
            @endif
        </div>

        <p style="text-align:center; margin:28px 0;">
            <a href="{{ $loginUrl }}"
               style="display:inline-block; background:#1e40af; color:#ffffff; padding:13px 28px;
                      border-radius:8px; text-decoration:none; font-weight:600; font-size:14px;">
                Me connecter à PME-CONFORM
            </a>
        </p>

        <p style="color:#6b7280; font-size:12px; line-height:1.5; margin-top:24px;">
            Pour des raisons de sécurité, ne partagez jamais ces identifiants.
            Si vous n'êtes pas à l'origine de cette demande, contactez immédiatement
            <a href="mailto:{{ config('services.asc.contact_email', 'contact@as-consulting.ci') }}" style="color:#1e40af;">AS Consulting</a>.
        </p>

        <hr style="border:none; border-top:1px solid #e5e7eb; margin:24px 0;">

        <p style="color:#9ca3af; font-size:11px;">
            PME-CONFORM — Plateforme de conformité DCP en Côte d'Ivoire (Loi n°2013-450).<br>
            Cet e-mail vous est adressé automatiquement par le système, merci de ne pas y répondre.
        </p>
    </div>
</body>
</html>
