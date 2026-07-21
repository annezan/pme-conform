<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Compte validé</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f4f6f8; margin:0; padding:24px;">
    <div style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
        <h1 style="color:#1e40af; margin-top:0;">Bonjour {{ $user->prenom }} {{ $user->nom }},</h1>

        <p style="color:#374151; line-height:1.6;">
            Bonne nouvelle ! Votre compte sur <strong>PME-CONFORM</strong> a été validé par AS Consulting.
            Vous pouvez désormais vous connecter à la plateforme et accéder à votre espace client.
        </p>

        <p style="text-align:center; margin:32px 0;">
            <a href="{{ $loginUrl }}"
               style="display:inline-block; background:#1e40af; color:#ffffff; padding:12px 24px;
                      border-radius:8px; text-decoration:none; font-weight:600;">
                Se connecter à PME-CONFORM
            </a>
        </p>

        <p style="color:#6b7280; font-size:13px; line-height:1.5;">
            Si vous n'êtes pas à l'origine de cette inscription, vous pouvez ignorer cet e-mail
            ou contacter <a href="mailto:{{ config('services.asc.contact_email', 'contact@as-consulting.ci') }}" style="color:#1e40af;">AS Consulting</a>.
        </p>

        <hr style="border:none; border-top:1px solid #e5e7eb; margin:24px 0;">

        <p style="color:#9ca3af; font-size:12px;">
            PME-CONFORM — Plateforme de conformité DCP en Côte d'Ivoire (Loi n°2013-450).<br>
            Cet e-mail vous est adressé automatiquement par le système, merci de ne pas y répondre.
        </p>
    </div>
</body>
</html>
