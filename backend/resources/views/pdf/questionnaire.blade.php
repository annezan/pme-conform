<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $questionnaire->titre }}</title>
    <style>
        @page { margin: 1.6cm 1.4cm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.45; }
        .header { border-bottom: 2px solid #1e40af; padding-bottom: 12px; margin-bottom: 18px; }
        .eyebrow { font-size: 9px; letter-spacing: 1px; text-transform: uppercase; color: #1e40af; font-weight: bold; margin: 0; }
        h1 { font-size: 18px; margin: 4px 0 8px; color: #111827; }
        .meta { font-size: 10px; color: #6b7280; }
        .meta strong { color: #1f2937; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 8px; font-size: 9px; font-weight: bold; }
        .badge-publie { background: #dcfce7; color: #166534; }
        .badge-brouillon { background: #f3f4f6; color: #4b5563; }
        .badge-rempli { background: #fef3c7; color: #92400e; }
        .badge-valide { background: #dbeafe; color: #1e40af; }
        .description { background: #f0f9ff; border-left: 3px solid #1e40af; padding: 10px 12px; margin: 14px 0 18px; color: #1e3a8a; font-size: 11px; }
        .question { border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; margin-bottom: 12px; page-break-inside: avoid; }
        .question-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .q-num { font-weight: bold; color: #1e40af; font-size: 12px; }
        .q-type { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
        .q-texte { font-weight: 600; color: #111827; margin-bottom: 8px; font-size: 12px; }
        .q-themes { margin: 4px 0; }
        .theme { display: inline-block; background: #eef2ff; color: #3730a3; padding: 1px 6px; border-radius: 4px; font-size: 9px; margin-right: 4px; }
        .reponse { background: #f9fafb; border-left: 3px solid #10b981; padding: 8px 10px; font-size: 11px; color: #1f2937; white-space: pre-wrap; }
        .reponse-vide { background: #fef2f2; border-left: 3px solid #ef4444; padding: 8px 10px; font-style: italic; color: #991b1b; font-size: 11px; }
        .options { margin-top: 6px; padding-left: 16px; font-size: 10px; color: #4b5563; }
        .options li { margin-bottom: 2px; }
        .footer { position: fixed; bottom: -1cm; left: 0; right: 0; text-align: center; font-size: 9px; color: #9ca3af; padding-top: 6px; border-top: 1px solid #e5e7eb; }
        .signataire { margin-top: 30px; padding-top: 10px; border-top: 1px dashed #d1d5db; font-size: 10px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <p class="eyebrow">PME-CONFORM · Audit Conformité ARTCI (Loi N°2013-450)</p>
        <h1>{{ $questionnaire->titre }}</h1>
        <p class="meta">
            <strong>Entreprise :</strong> {{ $questionnaire->mission->client->raison_sociale ?? '—' }}
            &nbsp; · &nbsp;
            <strong>Mission :</strong> {{ $questionnaire->mission->reference ?? '—' }}{{ $questionnaire->mission ? ' — ' . $questionnaire->mission->titre : '' }}
            <br>
            <strong>Pôle :</strong> {{ $questionnaire->pole ?? '—' }}
            @if($questionnaire->service)
                &nbsp; · &nbsp; <strong>Service :</strong> {{ $questionnaire->service }}
            @endif
            <br>
            <strong>Statut :</strong>
            @php
                $cfgStatut = [
                    'brouillon' => ['Brouillon', 'badge-brouillon'],
                    'envoye' => ['Envoyé', 'badge-publie'],
                    'rempli' => ['Rempli', 'badge-rempli'],
                    'valide' => ['Validé', 'badge-valide'],
                ];
                [$lbl, $cls] = $cfgStatut[$questionnaire->statut] ?? ['—', 'badge-brouillon'];
            @endphp
            <span class="badge {{ $cls }}">{{ $lbl }}</span>
            @if($questionnaire->est_publie)
                <span class="badge badge-publie">Publié</span>
            @endif
            &nbsp; · &nbsp;
            <strong>Source :</strong> {{ $questionnaire->source === 'ia' ? 'Généré par IA' : ($questionnaire->source === 'manuel' ? 'Modèle générique' : 'Modèle AS Consulting') }}
            &nbsp; · &nbsp;
            <strong>Généré le :</strong> {{ optional($questionnaire->created_at)->format('d/m/Y H:i') ?? '—' }}
        </p>
    </div>

    @if($questionnaire->description)
        <div class="description">{{ $questionnaire->description }}</div>
    @endif

    @php
        $reponses = collect($questionnaire->reponses ?? []);
        $repIndex = $reponses->mapWithKeys(fn ($r) => [(int) ($r['numero'] ?? 0) => $r]);
    @endphp

    @foreach($questionnaire->questions ?? [] as $q)
        @php
            $numero = (int) ($q['numero'] ?? $loop->iteration);
            $rep = $repIndex->get($numero);
            $reponseTxt = trim((string) ($rep['reponse'] ?? ''));
            $aRepondu = $reponseTxt !== '';
        @endphp
        <div class="question">
            <div class="question-header">
                <span class="q-num">Question {{ $numero }}</span>
                <span class="q-type">{{ strtoupper($q['type'] ?? 'ouverte') }}</span>
            </div>
            <p class="q-texte">{{ $q['texte'] ?? '' }}</p>
            @if(!empty($q['themes']))
                <p class="q-themes">
                    @foreach($q['themes'] as $theme)
                        <span class="theme">{{ $theme }}</span>
                    @endforeach
                </p>
            @endif
            @if(!empty($q['options']))
                <ul class="options">
                    @foreach($q['options'] as $opt)
                        <li>{{ $opt }}</li>
                    @endforeach
                </ul>
            @endif

            @if($aRepondu)
                <div class="reponse"><strong>Réponse :</strong> {!! nl2br(e($reponseTxt)) !!}</div>
            @else
                <div class="reponse-vide">(Non répondu)</div>
            @endif
        </div>
    @endforeach

    @if($questionnaire->repondu_par)
        <div class="signataire">
            Rempli par : <strong>{{ $questionnaire->repondu_par }}</strong>
            @if($questionnaire->repondu_a)
                — {{ \Carbon\Carbon::parse($questionnaire->repondu_a)->format('d/m/Y H:i') }}
            @endif
        </div>
    @endif

    <div class="footer">
        PME-CONFORM — AS Consulting · {{ $questionnaire->titre }} · Page <span class="pagenum"></span>
    </div>
</body>
</html>
