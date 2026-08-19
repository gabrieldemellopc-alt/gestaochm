<?php

namespace Tests\Feature;

use App\Http\Controllers\VehicleReadingCorrectionController;
use App\Models\VehicleReadingCorrectionEvidence;
use App\Services\VideoEvidenceProcessor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class VehicleReadingEvidenceSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']); DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('vehicle_reading_correction_evidences', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('vehicle_id'); $t->unsignedBigInteger('correction_id')->nullable(); $t->unsignedBigInteger('initiated_by'); $t->char('token_hash',64); $t->dateTime('expires_at'); $t->dateTime('used_at')->nullable(); $t->string('status'); $t->string('disk')->nullable(); $t->string('path')->nullable(); $t->string('original_name')->nullable(); $t->string('mime_type')->nullable(); $t->unsignedBigInteger('size_bytes')->nullable(); $t->decimal('duration_seconds',6,2)->nullable(); $t->char('checksum',64)->nullable(); $t->timestamps(); });
    }

    public function test_ready_session_shows_success_and_blocks_a_second_post(): void
    {
        $token = 'ready-token';
        VehicleReadingCorrectionEvidence::create(['tenant_id'=>1,'vehicle_id'=>1,'initiated_by'=>1,'token_hash'=>hash('sha256',$token),'expires_at'=>now()->addMinutes(10),'status'=>'ready','path'=>'protected/video.mp4','duration_seconds'=>8.2]);
        $controller = app(VehicleReadingCorrectionController::class);
        $response = $controller->publicEvidence($token);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Vídeo enviado com sucesso', $response->getContent());
        $this->expectException(HttpException::class);
        $controller->uploadEvidence(Request::create('/', 'POST'), $token, app(VideoEvidenceProcessor::class));
    }
}
