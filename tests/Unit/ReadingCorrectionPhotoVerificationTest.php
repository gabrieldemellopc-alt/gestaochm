<?php

namespace Tests\Unit;

use App\Http\Controllers\VehicleReadingCorrectionController;
use App\Services\ReadingCorrectionVerificationMode;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReadingCorrectionPhotoVerificationTest extends TestCase
{
    public function test_photo_is_the_temporary_default_mode(): void
    {
        $this->assertSame(ReadingCorrectionVerificationMode::PHOTO, app(ReadingCorrectionVerificationMode::class)->current());
    }

    public function test_both_photos_are_required_and_must_be_images(): void
    {
        $this->expectException(ValidationException::class);
        $this->validate(['plate_photo' => UploadedFile::fake()->create('plate.txt', 10, 'text/plain')]);
    }

    public function test_two_valid_photos_are_accepted(): void
    {
        $data=$this->validate([
            'plate_photo' => UploadedFile::fake()->image('plate.jpg'),
            'reading_photo' => UploadedFile::fake()->image('reading.webp'),
        ]);
        $this->assertArrayHasKey('plate_photo', $data);
        $this->assertArrayHasKey('reading_photo', $data);
    }

    private function validate(array $files): array
    {
        $request=Request::create('/', 'POST', [
            'target_log_id' => 1, 'new_km' => 123, 'reason' => 'Motivo administrativo com oito palavras para corrigir esta leitura agora',
        ], [], $files);
        $controller=app(VehicleReadingCorrectionController::class);
        return \Closure::bind(fn () => $this->validated($request, false), $controller, $controller)();
    }
}
