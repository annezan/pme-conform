<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Réinitialisation du mot de passe</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f4f6f8; margin:0; padding:24px;">
    <div style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
        <h1 style="color:#1e40af; margin-top:0; font-size:22px;">Bonjour {{ $user->prenom }} {{ $user->nom }},</h1>

        <p style="color:#374151; line-height:1.6;">
            Vous avez demandé la réinitialisation du mot de passe associé à votre compte
            <strong>{{ $user->email }}</strong> sur la plateforme <strong>PME-CONFORM</strong>.
        </p>

        <p style="text-align:center; margin:28px 0;">
            <a href="{{ $resetUrl }}"
               style="display:inline-block; background:#1e40af; color:#ffffff; padding:13px 28px;
                      border-radius:8px; text-decoration:none; font-weight:600; font-size:14px;">
                Réinitialiser mon mot de passe
            </a>
        </p>

        <div style="background:#fef3c7; border-left:4px solid #f59e0b; padding:14px 16px; border-radius:6px; margin:20px 0; color:#78350f; font-size:13px; line-height:1.5;">
            <strong>⏱️ Validité du lien :</strong> {{ $expirationMinutes }} minutes.<br>
            Passé ce délai, vous devrez en demander un nouveau.
        </div>

        <p style="color:#6b7280; font-size:13px; line-height:1.5;">
            Si le bouton ne fonctionne pas, copiez-collez ce lien dans votre navigateur :<br>
            <a href="{{ $resetUrl }}" style="color:#1e40af; word-break:break-all;">{{ $resetUrl }}</a>
        </p>

        <p style="color:#6b7280; font-size:12px; line-height:1.5; margin-top:24px;">
            Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet e-mail —
            votre mot de passe restera inchangé. Si vous avez des doutes, contactez
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
