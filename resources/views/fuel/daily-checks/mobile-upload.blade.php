<!doctype html>
<html lang="pt-BR">

<head>
<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width,initial-scale=1,viewport-fit=cover"
>

<title>Enviar {{ $documentTypeLabel }}</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    padding: 18px;
    background: #172236;
    color: #edf3fb;
    font: 16px system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
}

main {
    max-width: 540px;
    margin: auto;
    padding: 22px;
    border: 1px solid #43536c;
    border-radius: 18px;
    background: #253248;
}

.kicker {
    color: #ff747a;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: .12em;
}

h1 {
    margin: 8px 0 7px;
    font-size: 1.5rem;
}

.intro {
    margin: 0;
    color: #aebbd0;
    line-height: 1.5;
}

.date {
    margin: 18px 0;
    padding: 13px 14px;
    border: 1px solid #40506a;
    border-radius: 10px;
    background: #1d293d;
}

.date span {
    display: block;
    margin-bottom: 3px;
    color: #9eadc2;
    font-size: .75rem;
    text-transform: uppercase;
    font-weight: 700;
}

.pick {
    display: flex;
    align-items: center;
    justify-content: center;

    min-height: 62px;
    margin-top: 18px;
    padding: 15px;

    border: 2px dashed #7184a0;
    border-radius: 12px;

    background: #1e293b;

    text-align: center;
    font-weight: 800;

    cursor: pointer;
}

.pick input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
}

.help {
    margin: 9px 2px 0;
    color: #9eadc2;
    font-size: .78rem;
    line-height: 1.4;
}

.preview-list {
    display: grid;
    gap: 9px;
    margin-top: 14px;
}

.preview-item {
    display: grid;
    grid-template-columns: 68px minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;

    padding: 9px;

    border: 1px solid #43536c;
    border-radius: 10px;

    background: #1d293d;
}

.preview-thumb {
    width: 68px;
    height: 68px;

    display: grid;
    place-items: center;

    overflow: hidden;

    border-radius: 8px;

    background: #121b2d;

    color: #aebbd0;
    font-size: 11px;
    font-weight: 800;
}

.preview-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.preview-info {
    min-width: 0;
}

.preview-info strong {
    display: block;

    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;

    font-size: .86rem;
}

.preview-info small {
    display: block;
    margin-top: 4px;
    color: #9eadc2;
}

.remove {
    width: 34px;
    height: 34px;

    border: 0;
    border-radius: 8px;

    background: #3a465a;
    color: #fff;

    font-weight: 800;
}

.submit {
    width: 100%;
    margin-top: 16px;
    padding: 15px;

    border: 1px solid #536176;
    border-radius: 10px;

    background: #313c4f;
    color: #8996a8;

    font-size: 16px;
    font-weight: 800;

    cursor: not-allowed;
}

.submit.is-ready {
    background: #b93b44;
    border-color: #cb4d55;
    color: #fff;
    cursor: pointer;
}

.submit:disabled {
    cursor: wait;
    opacity: .8;
}

.upload-state {
    display: none;
    margin-top: 16px;
    padding: 14px;

    border: 1px solid #43536c;
    border-radius: 10px;

    background: #1d293d;
}

.upload-state.is-visible {
    display: block;
}

.upload-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;

    font-size: .86rem;
    font-weight: 800;
}

.progress {
    height: 10px;
    margin-top: 11px;

    overflow: hidden;

    border-radius: 999px;
    background: #111b2b;
}

.progress span {
    display: block;
    width: 0;
    height: 100%;

    border-radius: inherit;

    background: #73b490;

    transition: width .15s linear;
}

.success {
    margin-top: 18px;
    padding: 18px;

    border: 1px solid #4a8a69;
    border-radius: 12px;

    background: #244438;
    color: #c8f0d6;

    text-align: center;
}

.success i {
    display: block;
    margin-bottom: 6px;
    font-size: 2rem;
    font-style: normal;
}

.error {
    margin-top: 14px;
    padding: 12px;

    border: 1px solid #8e4a55;
    border-radius: 9px;

    background: #512e36;
    color: #ffb7c0;
}

[hidden] {
    display: none !important;
}
</style>
</head>

<body>

<main>

    <span class="kicker">
        CHM · COMBUSTÍVEL
    </span>

    <h1>
        Enviar {{ $documentTypeLabel }}
    </h1>

    <p class="intro">
        Tire uma foto agora ou escolha uma imagem/PDF
        já salvo no celular para anexar como
        <strong>{{ $documentTypeLabel }}</strong>.
    </p>


    <div class="date">
        <span>Data da operação</span>

        <strong>
            {{ $check->operation_date->format('d/m/Y') }}
        </strong>
    </div>


    <form
        id="dailyUploadForm"
        method="POST"
        enctype="multipart/form-data"
        action="{{ route(
            'public.fuel-daily-check.store',
            $token
        ) }}"
    >
        @csrf

        <label class="pick" for="dailyFiles">
            <span>
                📷 Tirar foto ou escolher arquivo
            </span>

            <input
                id="dailyFiles"
                type="file"
                name="files[]"
                accept="image/*,application/pdf"
                multiple
            >
        </label>

        <p class="help">
            No iPhone, escolha entre Câmera, Fototeca ou Arquivos.
            Você pode enviar até 5 arquivos.
        </p>


        <div
            id="previewList"
            class="preview-list"
        ></div>


        <button
            id="sendButton"
            class="submit"
            type="submit"
            disabled
        >
            Selecione uma foto ou arquivo
        </button>


        <div
            id="uploadState"
            class="upload-state"
            aria-live="polite"
        >
            <div class="upload-head">
                <span id="uploadTitle">
                    Enviando para o CHM...
                </span>

                <span id="uploadPercent">
                    0%
                </span>
            </div>

            <div class="progress">
                <span id="uploadBar"></span>
            </div>
        </div>


        <div
            id="uploadError"
            class="error"
            hidden
        ></div>

    </form>


    <div
        id="uploadSuccess"
        class="success"
        hidden
    >
        <i>✓</i>

        <strong>
            Arquivo enviado com sucesso
        </strong>

        <p>
            O arquivo foi enviado ao computador.
            Agora confira os dados no CHM e conclua
            o arquivamento por lá.
            Você pode fechar esta página.
        </p>
    </div>

</main>


<script>
(() => {
    const form = document.getElementById('dailyUploadForm');
    const input = document.getElementById('dailyFiles');
    const previews = document.getElementById('previewList');
    const send = document.getElementById('sendButton');

    const state = document.getElementById('uploadState');
    const title = document.getElementById('uploadTitle');
    const percent = document.getElementById('uploadPercent');
    const bar = document.getElementById('uploadBar');

    const error = document.getElementById('uploadError');
    const success = document.getElementById('uploadSuccess');

    let selectedFiles = [];
    let objectUrls = [];
    let sending = false;

    function formatSize(bytes) {
        if (!bytes) return '0 KB';

        if (bytes < 1024 * 1024) {
            return (bytes / 1024).toFixed(0) + ' KB';
        }

        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function clearUrls() {
        objectUrls.forEach(url => URL.revokeObjectURL(url));
        objectUrls = [];
    }

    function syncInput() {
        const transfer = new DataTransfer();

        selectedFiles.forEach(file => {
            transfer.items.add(file);
        });

        input.files = transfer.files;
    }

    function render() {
        clearUrls();
        previews.innerHTML = '';

        selectedFiles.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'preview-item';

            const thumb = document.createElement('div');
            thumb.className = 'preview-thumb';

            if (file.type.startsWith('image/')) {
                const url = URL.createObjectURL(file);
                objectUrls.push(url);

                const img = document.createElement('img');
                img.src = url;
                img.alt = 'Prévia';

                thumb.appendChild(img);
            } else {
                thumb.textContent = 'PDF';
            }

            const info = document.createElement('div');
            info.className = 'preview-info';

            const name = document.createElement('strong');
            name.textContent = file.name;

            const size = document.createElement('small');
            size.textContent =
                formatSize(file.size)
                + ' · pronto para enviar';

            info.append(name, size);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'remove';
            remove.textContent = '×';
            remove.setAttribute(
                'aria-label',
                'Remover ' + file.name
            );

            remove.onclick = () => {
                if (sending) return;

                selectedFiles.splice(index, 1);
                syncInput();
                render();
            };

            item.append(
                thumb,
                info,
                remove
            );

            previews.appendChild(item);
        });

        const ready = selectedFiles.length > 0;

        send.disabled = !ready;

        send.classList.toggle(
            'is-ready',
            ready
        );

        send.textContent = ready
            ? selectedFiles.length === 1
                ? 'Enviar para o CHM'
                : 'Enviar '
                    + selectedFiles.length
                    + ' arquivos para o CHM'
            : 'Selecione uma foto ou arquivo';
    }


    input.addEventListener('change', () => {
        const files = Array.from(input.files || []);

        if (!files.length) {
            selectedFiles = [];
            render();
            return;
        }

        selectedFiles = files.slice(0, 5);

        syncInput();
        render();
    });


    form.addEventListener('submit', event => {
        event.preventDefault();

        if (sending || !selectedFiles.length) {
            return;
        }

        sending = true;

        error.hidden = true;

        send.disabled = true;
        send.textContent = 'Enviando...';

        state.classList.add('is-visible');

        title.textContent = 'Enviando para o CHM...';

        percent.textContent = '0%';
        bar.style.width = '0%';

        const data = new FormData(form);

        const xhr = new XMLHttpRequest();

        xhr.open(
            'POST',
            form.action
        );

        xhr.timeout = 180000;

        xhr.setRequestHeader(
            'Accept',
            'application/json'
        );

        xhr.setRequestHeader(
            'X-CSRF-TOKEN',
            form.querySelector('[name="_token"]').value
        );


        xhr.upload.onprogress = event => {
            if (!event.lengthComputable) {
                return;
            }

            const value = Math.min(
                100,
                Math.round(
                    event.loaded / event.total * 100
                )
            );

            percent.textContent = value + '%';
            bar.style.width = value + '%';

            send.textContent =
                'Enviando... ' + value + '%';
        };


        xhr.upload.onload = () => {
            percent.textContent = '100%';
            bar.style.width = '100%';

            title.textContent =
                'Upload concluído. Salvando no CHM...';
        };


        xhr.onload = () => {
            let body = {};

            try {
                body = JSON.parse(xhr.responseText);
            } catch (_) {}

            if (xhr.status >= 200 && xhr.status < 300) {
                sending = false;

                form.hidden = true;
                state.classList.remove('is-visible');

                success.hidden = false;

                return;
            }

            sending = false;

            send.disabled = false;
            send.classList.add('is-ready');
            send.textContent = 'Tentar novamente';

            state.classList.remove('is-visible');

            error.textContent =
                Object.values(body.errors || {})
                    .flat()
                    .join(' ')
                || body.message
                || 'Não foi possível concluir o envio.';

            error.hidden = false;
        };


        xhr.onerror = xhr.ontimeout = () => {
            sending = false;

            send.disabled = false;
            send.classList.add('is-ready');
            send.textContent = 'Tentar novamente';

            state.classList.remove('is-visible');

            error.textContent =
                'Falha de conexão durante o envio. Tente novamente.';

            error.hidden = false;
        };


        xhr.send(data);
    });


    window.addEventListener(
        'pagehide',
        clearUrls
    );
})();
</script>

</body>
</html>
