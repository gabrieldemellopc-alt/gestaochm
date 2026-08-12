<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Enviar fotos da manutenção</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;padding:22px 14px;background:linear-gradient(145deg,#192334,#28364b);font-family:Arial,sans-serif;color:#edf3fb}.box{max-width:540px;margin:auto;background:#263247;border:1px solid #46556e;border-radius:18px;padding:24px;box-shadow:0 18px 48px #0b122077}.brand{display:flex;align-items:center;gap:13px}.logo-wrap{padding:7px;background:#fff;border-radius:10px}.logo{display:block;height:42px}.brand span{color:#aebbd0;font-size:.8rem;text-transform:uppercase;letter-spacing:.12em;font-weight:700}h1{margin:23px 0 9px;font-size:1.65rem}.intro{margin:0;color:#c4cfdd;line-height:1.55}.info{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:20px 0}.info-card{padding:13px;background:#1d283a;border:1px solid #3e4d65;border-radius:10px}.info-card span{display:block;color:#9eadc2;font-size:.78rem;margin-bottom:5px}.info-card strong{color:#fff}.rules{padding:14px 15px;border-left:3px solid #62a5dd;background:#202d41;border-radius:8px;color:#d7e0eb;line-height:1.5}.ok,.err,.selection-error{padding:12px 14px;border-radius:9px}.ok{background:#234c3d;color:#9ae4bb}.err,.selection-error{background:#512e36;color:#ffb7c0}.selection-error[hidden]{display:none}.upload{margin-top:20px;padding:15px;background:#1e293b;border:1px solid #40506a;border-radius:12px}.upload-title{margin:0 0 5px;color:#fff;font-weight:800}.upload-help{margin:0 0 14px;color:#aebbd0;font-size:.9rem;line-height:1.45}.file-input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}.file-picker{display:flex;align-items:center;justify-content:center;min-height:54px;padding:14px;border:2px dashed #6f819d;border-radius:11px;background:#28364c;color:#fff;font-weight:700;cursor:pointer}.file-picker:hover{background:#32435d}.file-label{display:block;margin:10px 2px 0;color:#becadd;font-size:.9rem;font-weight:700}.submit{width:100%;margin-top:16px;border:1px solid #536176;border-radius:10px;padding:14px;background:#313c4f;color:#8996a8;font-weight:800;font-size:16px;cursor:not-allowed;transition:background .18s,border-color .18s}.submit.is-ready{border-color:#2f93cf;background:#2582c4;color:#fff;cursor:pointer;box-shadow:0 6px 16px #12679b44}.submit.is-ready:hover{background:#3293d6}.submit.is-loading{background:#3b4658;color:#d2dae5}.submit:disabled{cursor:not-allowed}.limit{color:#ffcf7d}@media(max-width:480px){body{padding:10px}.box{padding:19px}.info{grid-template-columns:1fr}h1{font-size:1.4rem}}
    </style>
</head>
<body>
<main class="box">
    <div class="brand"><div class="logo-wrap"><img class="logo" src="{{ asset('images/logo-chm.png') }}" alt="CHM"></div><span>Manutenção · Envio seguro</span></div>
    <h1>Envie as fotos da manutenção</h1>
    <p class="intro">As imagens serão anexadas diretamente a esta ordem. Inclua pelo menos 2 fotos e, quando houver mais de um problema, envie uma imagem de cada item.</p>

    <div class="info">
        <div class="info-card"><span>Ordem</span><strong>#{{ $maintenance->id }} · aberta</strong></div>
        <div class="info-card"><span>Veículo</span><strong>{{ $maintenance->vehicle?->plate ?? $maintenance->vehicle?->name ?? 'Identificado' }}</strong></div>
        <div class="info-card"><span>Fotos enviadas</span><strong>{{ $photoCount }}/{{ $maxPhotos }}</strong></div>
        <div class="info-card"><span>Disponível neste link</span><strong class="{{ $remaining === 0 ? 'limit' : '' }}">{{ $remaining }} envio(s)</strong></div>
    </div>

    <p class="rules"><strong>Mínimo para encerramento: {{ $minPhotos }} fotos.</strong><br>Envie uma imagem de cada problema ou serviço realizado.</p>
    @if(session('success'))
        @php($uploadResult = session('photo_upload_result'))
        <div class="ok">
            <strong>Fotos enviadas com sucesso</strong><br>
            As imagens foram anexadas à ordem de manutenção.
            @if($uploadResult)
                <br>Enviadas agora: {{ $uploadResult['uploadedNow'] }} foto(s). Fotos da ordem: {{ $uploadResult['photoCount'] }}/{{ $uploadResult['maxPhotos'] }}.
                <br>{{ $uploadResult['minimumMet'] ? 'Mínimo obrigatório atendido.' : 'Envie pelo menos mais '.$uploadResult['missingForMinimum'].' foto(s) para permitir o encerramento da ordem.' }}
                @if($uploadResult['maintenanceLimitReached'])<br>Esta ordem já atingiu o limite de {{ $uploadResult['maxPhotos'] }} fotos.
                @elseif($uploadResult['tokenLimitReached'])<br>Limite de envio atingido para este link.@endif
            @endif
        </div>
    @endif
    @if($errors->any())<p class="err">{{ $errors->first() }}</p>@endif

    @if($remaining > 0)
        <form class="upload" id="public-photo-upload" method="POST" enctype="multipart/form-data" action="{{ route('public.maintenance-photos.store', $token) }}" data-remaining="{{ $remaining }}">
            @csrf
            <p class="upload-title">Escolha fotos da galeria ou tire novas fotos pelo celular.</p>
            <p class="upload-help">Você pode selecionar mais de uma imagem antes de enviar.</p>
            <label class="file-picker" for="photos">Escolher fotos</label>
            <input class="file-input" id="photos" name="photos[]" type="file" accept="image/*" multiple required>
            <span class="file-label" id="file-label">Nenhum arquivo selecionado</span>
            <p class="selection-error" id="selection-error" role="alert" hidden></p>
            <button class="submit" id="photo-submit" type="submit" disabled>Selecione fotos para enviar</button>
        </form>
    @else
        <button class="submit" type="button" disabled>Limite de {{ $maxPhotos }} fotos atingido</button>
    @endif
</main>
<script>
    (() => {
        const form = document.getElementById('public-photo-upload');
        if (!form) return;

        const input = document.getElementById('photos');
        const label = document.getElementById('file-label');
        const submit = document.getElementById('photo-submit');
        const error = document.getElementById('selection-error');
        const remaining = Number(form.dataset.remaining);
        let selectedCount = 0;
        let isSubmitting = false;

        const update = () => {
            const exceedsCapacity = selectedCount > remaining;
            label.textContent = selectedCount === 0
                ? 'Nenhum arquivo selecionado'
                : selectedCount === 1 ? '1 foto selecionada' : `${selectedCount} fotos selecionadas`;
            error.hidden = !exceedsCapacity;
            error.textContent = exceedsCapacity
                ? `Este link permite mais ${remaining} envio(s). Selecione uma quantidade menor.`
                : '';
            submit.disabled = selectedCount === 0 || exceedsCapacity || isSubmitting;
            submit.classList.toggle('is-ready', !submit.disabled);
            submit.classList.toggle('is-loading', isSubmitting);
            submit.textContent = isSubmitting
                ? 'Enviando...'
                : selectedCount === 0 ? 'Selecione fotos para enviar'
                : selectedCount === 1 ? 'Enviar 1 foto' : `Enviar ${selectedCount} fotos`;
        };

        input.addEventListener('change', () => {
            selectedCount = input.files.length;
            update();
        });
        form.addEventListener('submit', event => {
            if (selectedCount === 0 || selectedCount > remaining || isSubmitting) {
                event.preventDefault();
                return;
            }
            isSubmitting = true;
            update();
        });
        update();
    })();
</script>
</body>
</html>
