<div>

    @if($open)

        <div class="modal-overlay">

            <div class="vehicle-modal">

                <div class="modal-header">

                    <div>

                        <h2>
                            {{ $vehicle['name'] }}
                        </h2>

                        <span>
                            {{ $vehicle['plate'] }}
                        </span>

                    </div>

                    <button wire:click="close">
                        ✕
                    </button>

                </div>

                <div class="modal-body">

                    <div class="modal-grid">

                        <div class="modal-card">

                            <small>KM Atual</small>

                            <strong>
                                {{ number_format($vehicle['km']) }}
                            </strong>

                        </div>

                        <div class="modal-card">

                            <small>Horímetro</small>

                            <strong>
                                {{ $vehicle['hours'] }}h
                            </strong>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    @endif

</div>