<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Nouvelle demande de rendez-vous</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fa;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;background:#f5f7fa;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" style="width:600px;max-width:96%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#2563eb,#4f46e5);padding:24px;color:#ffffff;">
                            <h1 style="margin:0;font-size:20px;font-weight:700;">PME-CONFORM</h1>
                            <p style="margin:6px 0 0;font-size:13px;opacity:.9;">Nouvelle demande issue d'un audit flash</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <h2 style="margin:0 0 12px;font-size:18px;color:#111827;">
                                {{ $rdv->type_demande === 'audit_complet' ? 'Demande d\'audit complet' : 'Demande d\'accompagnement' }}
                            </h2>
                            <p style="margin:0 0 18px;line-height:1.6;font-size:14px;color:#374151;">
                                Un prospect a soumis une demande de prise de rendez-vous a la suite de son audit flash.
                            </p>
                            <table cellpadding="6" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px;">
                                <tr style="background:#f9fafb;">
                                    <td style="font-weight:600;width:160px;">Nom</td>
                                    <td>{{ $rdv->nom }}</td>
                                </tr>
                                <tr>
                                    <td style="font-weight:600;">Email</td>
                                    <td><a href="mailto:{{ $rdv->email }}">{{ $rdv->email }}</a></td>
                                </tr>
                                @if ($rdv->telephone)
                                <tr style="background:#f9fafb;">
                                    <td style="font-weight:600;">Telephone</td>
                                    <td>{{ $rdv->telephone }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="font-weight:600;">Type de demande</td>
                                    <td>{{ $rdv->type_demande === 'audit_complet' ? 'Audit complet' : 'Accompagnement' }}</td>
                                </tr>
                                @if ($rdv->creneau_libelle || $rdv->creneau_souhaite)
                                <tr style="background:#f9fafb;">
                                    <td style="font-weight:600;">Creneau souhaite</td>
                                    <td>
                                        {{ $rdv->creneau_libelle }}
                                        @if ($rdv->creneau_souhaite)
                                            ({{ $rdv->creneau_souhaite->format('d/m/Y H:i') }})
                                        @endif
                                    </td>
                                </tr>
                                @endif
                                @if ($rdv->message)
                                <tr>
                                    <td style="font-weight:600;vertical-align:top;">Message</td>
                                    <td style="white-space:pre-wrap;">{{ $rdv->message }}</td>
                                </tr>
                                @endif
                                <tr style="background:#f9fafb;">
                                    <td style="font-weight:600;">Soumis le</td>
                                    <td>{{ $rdv->created_at->format('d/m/Y H:i') }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f9fafb;padding:16px 28px;text-align:center;font-size:11px;color:#9ca3af;">
                            PME-CONFORM &copy; {{ date('Y') }} AS Consulting.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
