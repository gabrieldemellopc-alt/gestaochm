<?php

namespace Tests\Unit;

use App\Models\VehicleReadingCorrectionEvidence;
use Tests\TestCase;

class ReadingCorrectionPhotoUxViewTest extends TestCase
{
    public function test_mobile_photo_view_contains_progress_and_success_states(): void
    {
        $html=view('vehicle.reading-correction-photo-evidence', [
            'token' => 'test-token', 'mode' => 'pending', 'evidence' => new VehicleReadingCorrectionEvidence(),
        ])->render();

        $this->assertStringContainsString('xhr.upload.onprogress', $html);
        $this->assertStringContainsString('Fotos enviadas com sucesso', $html);
        $this->assertStringContainsString('capture="environment"', $html);
    }
}
