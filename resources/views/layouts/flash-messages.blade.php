@if(session('success'))

    <div class="flash-message success">

        <div class="flash-icon">

            <i data-lucide="circle-check-big"></i>

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

            <i data-lucide="circle-alert"></i>

        </div>

        <div class="flash-content">

            <strong>Erro</strong>

            <span>
                {{ session('error') }}
            </span>

        </div>

    </div>

@endif