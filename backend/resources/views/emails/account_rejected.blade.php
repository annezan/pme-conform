<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Demande d'inscription non validée</title>
</head>
<body style="font-family: Arial, sans-serif; background:#f4f6f8; margin:0; padding:24px;">
    <div style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:12px; padding:32px; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
        <h1 style="color:#b91c1c; margin-top:0;">Bonjour {{ $user->prenom }} {{ $user->nom }},</h1>

        <p style="color:#374151; line-height:1.6;">
            Nous avons bien reçu votre demande d'inscription à la plateforme
            <strong>PME-CONFORM</strong>. Après examen de votre dossier, AS Consulting
            n'a pas pu valider la création de votre compte.
        </p>

        <div style="background:#fef2f2; border-left:4px solid #dc2626; padding:16px 20px; border-radius:6px; margin:24px 0;">
            <p style="color:#7f1d1d; font-weight:600; margin:0 0 8px 0; font-size:14px;">
                Motif du refus :
            </p>
            <p style="color:#7f1d1d; margin:0; line-height:1.6; white-space:pre-wrap;">{{ $motif }}</p>
        </div>

        <p style="color:#374151; line-height:1.6;">
            Si vous pensez que ce refus est une erreur ou souhaitez apporter des
            précisions complémentaires, vous pouvez contacter directement notre équipe.
            Une nouvelle demande d'inscription peut aussi être effectuée après correction
            des points mentionnés ci-dessus.
        </p>

        <p style="text-align:center; margin:28px 0;">
            <a href="mailto:{{ $contactEmail }}"
               style="display:inline-block; background:#1e40af; color:#ffffff; padding:12px 24px;
                      border-radius:8px; text-decoration:none; font-weight:600;">
                Contacter AS Consulting
            </a>
        </p>

        <hr style="border:none; border-top:1px solid #e5e7eb; margin:24px 0;">

        <p style="color:#9ca3af; font-size:12px;">
            PME-CONFORM — Plateforme de conformité DCP en Côte d'Ivoire (Loi n°2013-450).<br>
            Cet e-mail vous est adressé automatiquement par le système suite à la décision
            manuelle d'un administrateur. Merci de ne pas y répondre : contactez-nous à
            <a href="mailto:{{ $contactEmail }}" style="color:#1e40af;">{{ $contactEmail }}</a>.
        </p>
    </div>
</body>
</html>
