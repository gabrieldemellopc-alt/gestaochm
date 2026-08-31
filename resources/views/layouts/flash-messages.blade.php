@if(session('success'))

    <div class="flash-message success">

        <div class="flash-icon">

            <i class="bi bi-check-circle"></i>

        </div>

        <div class="flash-content">

            <strong>Sucesso</strong>

            <span>
                {{ session('success') }}
            </span>

        </div>

    </div>

@endif

@if(session('error'))

    <div class="flash-message error">

        <div class="flash-icon">

            <i class="bi bi-exclamation-circle"></i>

        </div>

        <div class="flash-content">

            <strong>Erro</strong>

            <span>
                {{ session('error') }}
            </span>

        </div>

    </div>

@endif