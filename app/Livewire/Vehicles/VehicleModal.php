<?php

namespace App\Livewire\Vehicles;

use Livewire\Component;

class VehicleModal extends Component
{
    public $open = false;

    public $vehicle = [];

    protected $listeners = [
        'openVehicleModal'
    ];

    public function openVehicleModal($vehicle)
    {
        $this->vehicle = $vehicle;

        $this->open = true;
    }

    public function close()
    {
        $this->open = false;
    }

    public function render()
    {
        return view('livewire.vehicles.vehicle-modal');
    }
}