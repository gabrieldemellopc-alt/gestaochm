<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Fotos enviadas</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;padding:22px 14px;background:linear-gradient(145deg,#192334,#28364b);font-family:Arial,sans-serif;color:#edf3fb}.box{max-width:520px;margin:auto;background:#263247;border:1px solid #46556e;border-radius:18px;padding:25px;box-shadow:0 18px 48px #0b122077}.logo-wrap{display:inline-block;padding:7px;background:#fff;border-radius:10px}.logo{display:block;height:42px}.success{margin-top:22px;padding:18px;border:1px solid #397158;border-radius:12px;background:#234c3d}.success h1{margin:0 0 8px;font-size:1.5rem;color:#a3e8c1}.success p{margin:0;color:#d8f4e4;line-height:1.5}.summary{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:18px 0}.item{padding:13px;background:#1d283a;border:1px solid #3e4d65;border-radius:10px}.item span{display:block;color:#9eadc2;font-size:.78rem;margin-bottom:5px}.notice{padding:13px;border-radius:9px;background:#202d41;color:#d7e0eb;line-height:1.5}.footer{margin-top:18px;color:#aebbd0;text-align:center}@media(max-width:430px){.summary{grid-template-columns:1fr}}
    </style>
</head>
<body><main class="box">
    <div class="logo-wrap"><img class="logo" src="{{ asset('images/logo-chm.png') }}" alt="CHM"></div>
    <section class="success"><h1>Fotos enviadas com sucesso</h1><p>As imagens foram anexadas à ordem de manutenção.</p></section>
    <div class="summary"><div class="item"><span>Enviadas agora</span><strong>{{ $uploadedNow }} foto(s)</strong></div><div class="item"><span>Fotos da ordem</span><strong>{{ $photoCount }}/{{ $maxPhotos }}</strong></div></div>
    <p class="notice">@if($minimumMet)Mínimo obrigatório atendido.@else Envie pelo menos mais {{ $missingForMinimum }} foto(s) para permitir o encerramento da ordem.@endif</p>
    @if($maintenanceLimitReached)<p class="notice">Esta ordem já atingiu o limite de {{ $maxPhotos }} fotos.</p>
    @elseif($tokenLimitReached)<p class="notice">Limite de envio atingido para este link. Novos envios exigem um novo QR Code.</p>@endif
    <p class="footer">Você pode fechar esta página.</p>
</main></body></html>
