<?php

namespace Tests\Feature;

use App\Models\VehicleReadingCorrectionEvidence;
use App\Services\ReadingCorrectionTemporaryEvidenceCleanupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReadingCorrectionTemporaryEvidenceCleanupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']); DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('vehicle_reading_correction_evidences', function (Blueprint $t) { $t->id();$t->unsignedBigInteger('tenant_id');$t->unsignedBigInteger('vehicle_id');$t->unsignedBigInteger('correction_id')->nullable();$t->unsignedBigInteger('initiated_by');$t->string('token_hash');$t->dateTime('expires_at');$t->dateTime('used_at')->nullable();$t->string('status');$t->string('evidence_type')->nullable();$t->string('disk')->nullable();$t->string('path')->nullable();$t->string('original_name')->nullable();$t->string('mime_type')->nullable();$t->unsignedBigInteger('size_bytes')->nullable();$t->decimal('duration_seconds',6,2)->nullable();$t->string('checksum')->nullable();$t->timestamps(); });
    }
    public function test_only_expired_temporary_paths_are_eligible(): void
    {
        $session=VehicleReadingCorrectionEvidence::create(['tenant_id'=>1,'vehicle_id'=>1,'initiated_by'=>1,'token_hash'=>str_repeat('a',64),'expires_at'=>now()->subDay()->subMinute(),'status'=>'expired','evidence_type'=>'mobile_photo_session']);
        $temp=VehicleReadingCorrectionEvidence::create(['tenant_id'=>1,'vehicle_id'=>1,'initiated_by'=>1,'token_hash'=>str_repeat('b',64),'expires_at'=>now()->subDay(),'status'=>'ready','evidence_type'=>'identification','path'=>'protected/vehicle-reading-evidence/tmp/a/photo.jpg','size_bytes'=>1024,'checksum'=>$session->token_hash]);
        VehicleReadingCorrectionEvidence::create(['tenant_id'=>2,'vehicle_id'=>2,'correction_id'=>9,'initiated_by'=>1,'token_hash'=>str_repeat('c',64),'expires_at'=>now()->subDay(),'status'=>'ready','evidence_type'=>'reading','path'=>'protected/vehicle-reading-evidence/tmp/a/keep.jpg','checksum'=>$session->token_hash]);
        $plan=app(ReadingCorrectionTemporaryEvidenceCleanupService::class)->plan();
        $this->assertCount(1,$plan['files']); $this->assertSame($temp->id,$plan['files']->first()->id); $this->assertTrue($plan['safe']);
    }
}
