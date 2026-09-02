<?php

namespace App\Http\Controllers;

use App\Models\FuelFilling;
use App\Models\Vehicle;
use App\Models\VehicleReadingCorrection;
use App\Models\VehicleReadingCorrectionEvidence;
use App\Models\VehicleUpdateLog;
use App\Services\ActiveContextService;
use App\Services\AuditLogService;
use App\Services\Permissions\ProfilePermissionService;
use App\Services\ReadingCorrectionVerificationMode;
use App\Services\VehicleReadingReconciliationService;
use App\Services\VideoEvidenceProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

class VehicleReadingCorrectionController extends Controller
{
    public function createEvidence(Request $request, Vehicle $vehicle)
    {
        $this->authorizeCorrection($vehicle);
        VehicleReadingCorrectionEvidence::query()->where('vehicle_id',$vehicle->id)->where('initiated_by',$request->user()->id)->whereIn('status',['pending','processing'])->update(['status'=>'expired']);
        $token = Str::random(64);
        $url = route('reading-corrections.evidence.show',$token);
        $qr = (new Builder(writer: new PngWriter(), data: $url, size: 180))->build();
        $evidence = VehicleReadingCorrectionEvidence::create(['tenant_id'=>$vehicle->tenant_id,'vehicle_id'=>$vehicle->id,'initiated_by'=>$request->user()->id,'token_hash'=>hash('sha256',$token),'expires_at'=>now()->addMinutes(20),'status'=>'pending']);
        return response()->json(['id'=>$evidence->id,'url'=>$url,'qr'=>'data:image/png;base64,'.base64_encode($qr->getString())]);
    }

    public function evidenceStatus(Request $request, Vehicle $vehicle, VehicleReadingCorrectionEvidence $evidence)
    {
        $this->authorizeCorrection($vehicle); abort_unless($evidence->vehicle_id === $vehicle->id, 404);
        if ($evidence->status === 'pending' && $evidence->expires_at->isPast()) $evidence->update(['status'=>'expired']);
        return response()->json(['status'=>$evidence->fresh()->status,'available'=>$evidence->isAvailable(),'uploaded_at'=>optional($evidence->used_at)->format('d/m/Y H:i'),'duration'=>$evidence->duration_seconds,'view_url'=>$evidence->isAvailable()?route('vehicles.reading-correction.evidence.download',[$vehicle,$evidence]):null]);
    }

    public function publicEvidence(string $token)
    {
        $e = $this->byToken($token);
        $mode = match (true) {
            $e->expires_at->isPast() || $e->status === 'expired' => 'expired',
            $e->status === 'pending' => 'pending',
            $e->status === 'processing' => 'processing',
            $e->status === 'ready' => 'ready',
            default => 'failed',
        };

        return response()->view('vehicle.reading-correction-evidence', ['token' => $token, 'evidence' => $e, 'mode' => $mode], in_array($mode, ['expired', 'failed'], true) ? 410 : 200);
    }
    public function uploadEvidence(Request $request, string $token, VideoEvidenceProcessor $processor)
    {
        $e=$this->byToken($token); abort_if($e->status !== 'pending'||$e->expires_at->isPast(),410);
        $request->validate(['video'=>['required','file','mimetypes:video/mp4,video/quicktime,video/webm,video/3gpp','max:15360']]); // 15 MB bruto
        $f=$request->file('video'); $tempOut=tempnam(sys_get_temp_dir(),'reading-evidence-').'.mp4'; $e->update(['status'=>'processing']);
        $result=$processor->process($f->getRealPath(),$tempOut);
        if($result['status'] !== 'ready') { @unlink($tempOut); $e->update(['status'=>$result['status']==='unavailable'?'pending':'failed']); throw ValidationException::withMessages(['video'=>$result['message']]); }
        $path='protected/vehicle-reading-evidence/'.$e->vehicle_id.'/'.Str::uuid().'.mp4'; Storage::disk('local')->put($path,file_get_contents($tempOut)); @unlink($tempOut);
        $e->update(['status'=>'ready','used_at'=>now(),'disk'=>'local','path'=>$path,'original_name'=>$f->getClientOriginalName(),'mime_type'=>'video/mp4','size_bytes'=>Storage::disk('local')->size($path),'duration_seconds'=>$result['duration'],'checksum'=>hash_file('sha256',Storage::disk('local')->path($path))]);
        return back()->with('success','Vídeo recebido. Volte ao computador para concluir a correção.');
    }

    public function preview(Request $r, Vehicle $vehicle) { $this->authorizeCorrection($vehicle); $d=$this->validated($r,false); return response()->json(['impacts'=>$this->impacts($vehicle,$d),'target'=>$this->target($vehicle,$d)]); }
    public function store(Request $r, Vehicle $vehicle)
    {
        $this->authorizeCorrection($vehicle); $d=$this->validated($r,true);
        DB::transaction(function() use($r,$vehicle,$d) {
            $t=$this->target($vehicle,$d, true); $impacts=$this->impacts($vehicle,$d);
            $mode=app(ReadingCorrectionVerificationMode::class)->current();
            $e=null;
            if ($mode === ReadingCorrectionVerificationMode::VIDEO) {
                $e=VehicleReadingCorrectionEvidence::query()->whereKey($d['evidence_id'])->where('vehicle_id',$vehicle->id)->lockForUpdate()->firstOrFail();
                if(!$e->isAvailable()) throw ValidationException::withMessages(['evidence_id'=>'A evidência em vídeo pronta é obrigatória.']);
            }
            $c=VehicleReadingCorrection::create(['tenant_id'=>$vehicle->tenant_id,'division_id'=>$vehicle->division_id,'location_id'=>$vehicle->location_id,'vehicle_id'=>$vehicle->id,'user_id'=>$r->user()->id,'original_log_id'=>$t['log']?->id,'original_fuel_filling_id'=>$t['filling']?->id,'new_km'=>$d['new_km']??null,'new_hours'=>$d['new_hours']??null,'reason'=>$d['reason'],'verification_mode'=>$mode,'effective_at'=>$t['date'],'impacts'=>$impacts,'ip_address'=>$r->ip(),'user_agent'=>Str::limit((string)$r->userAgent(),2000)]);
            foreach(['km'=>'new_km','hours'=>'new_hours'] as $type=>$field) if(isset($d[$field])) {
                if($t['log'] && $t['log']->type===$type) $t['log']->update(['reading_status'=>'ignored','reading_issue'=>'Substituída por correção administrativa #'.$c->id,'reviewed_by'=>$r->user()->id,'reviewed_at'=>now()]);
                if($type==='km' && $t['filling']) $t['filling']->update(['vehicle_km_status'=>'ignored','vehicle_km_issue'=>'Substituída por correção administrativa #'.$c->id,'vehicle_km_reviewed_by'=>$r->user()->id,'vehicle_km_reviewed_at'=>now()]);
                $log=VehicleUpdateLog::create(['vehicle_id'=>$vehicle->id,'user_id'=>$r->user()->id,'division_id'=>$vehicle->division_id,'location_id'=>$vehicle->location_id,'type'=>$type,'source'=>'administrative_correction','read_at'=>$t['date'],'old_value'=>$t['value'][$type]??null,'new_value'=>$d[$field],'observation'=>'Correção administrativa #'.$c->id.'. Motivo: '.$d['reason']]); if($type==='km')$c->update(['corrected_log_id'=>$log->id]);
            }
            $evidenceIds=[];
            if ($mode === ReadingCorrectionVerificationMode::PHOTO) {
                $evidenceIds[]=$this->storePhotoEvidence($d['plate_photo'], $c, $vehicle, $r->user()->id, 'identification');
                $evidenceIds[]=$this->storePhotoEvidence($d['reading_photo'], $c, $vehicle, $r->user()->id, 'reading');
            } else { $e->update(['correction_id'=>$c->id]); $evidenceIds[]=$e->id; }
            $latest=app(VehicleReadingReconciliationService::class)->latestValid($vehicle); $vehicle->update(['current_km'=>$latest['km']??null,'last_km_update_at'=>$latest['date']??null]);
            app(AuditLogService::class)->updated($vehicle,['tenant_id'=>$vehicle->tenant_id,'division_id'=>$vehicle->division_id,'location_id'=>$vehicle->location_id,'module'=>'fleet','summary'=>'Correção administrativa de hodômetro #'.$c->id,'metadata'=>['correction_id'=>$c->id,'verification_mode'=>$mode,'evidence_ids'=>$evidenceIds,'impacts'=>$impacts],'reason'=>$d['reason']]);
        }); return back()->with('success','Correção administrativa registrada e leitura reconciliada.');
    }
    public function downloadEvidence(Vehicle $vehicle, VehicleReadingCorrectionEvidence $evidence)
    {
        $this->authorizeCorrection($vehicle);
        abort_unless((int) $evidence->vehicle_id === (int) $vehicle->id && $evidence->status === 'ready' && $evidence->path && Storage::disk($evidence->disk ?: 'local')->exists($evidence->path), 404);
        $path = Storage::disk($evidence->disk ?: 'local')->path($evidence->path);
        return response()->file($path, ['Content-Type' => $evidence->mime_type ?: 'video/mp4', 'Content-Disposition' => 'inline; filename="'.($evidence->original_name ?: 'evidencia.mp4').'"']);
    }
    private function validated(Request $r,bool $confirm):array { $mode=app(ReadingCorrectionVerificationMode::class)->current(); $rules=['target_log_id'=>['nullable','integer'],'target_filling_id'=>['nullable','integer'],'new_km'=>['nullable','numeric','min:0','required_without:new_hours'],'new_hours'=>['nullable','numeric','min:0','required_without:new_km'],'reason'=>['required','string','max:1000', function ($attribute, $value, $fail) { if (count(preg_split('/\s+/u', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY)) < 8) $fail('O motivo da correção deve conter pelo menos 8 palavras.'); }]]; if($mode===ReadingCorrectionVerificationMode::PHOTO) $rules += ['plate_photo'=>['required','file','image','mimes:jpg,jpeg,png,webp','max:10240'],'reading_photo'=>['required','file','image','mimes:jpg,jpeg,png,webp','max:10240']]; else $rules['evidence_id']=['required','integer']; if($confirm)$rules['impact_confirmed']=['accepted']; return $r->validate($rules); }
    private function storePhotoEvidence($file, VehicleReadingCorrection $correction, Vehicle $vehicle, int $userId, string $type): int { $extension=strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg'); $path='protected/vehicle-reading-evidence/'.$vehicle->tenant_id.'/'.$vehicle->id.'/'.$correction->id.'/'.$type.'.'.$extension; Storage::disk('local')->putFileAs(dirname($path), $file, basename($path)); return VehicleReadingCorrectionEvidence::create(['tenant_id'=>$vehicle->tenant_id,'vehicle_id'=>$vehicle->id,'correction_id'=>$correction->id,'initiated_by'=>$userId,'token_hash'=>hash('sha256',Str::uuid()),'expires_at'=>now()->addYears(20),'used_at'=>now(),'status'=>'ready','evidence_type'=>$type,'disk'=>'local','path'=>$path,'original_name'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType(),'size_bytes'=>Storage::disk('local')->size($path),'checksum'=>hash_file('sha256',Storage::disk('local')->path($path))])->id; }
    private function target(Vehicle $v,array $d,bool $lock=false):array { $logs=VehicleUpdateLog::query()->where('vehicle_id',$v->id); $fillings=FuelFilling::query()->where('vehicle_id',$v->id); if($lock){$logs->lockForUpdate();$fillings->lockForUpdate();} $log=isset($d['target_log_id'])?$logs->findOrFail($d['target_log_id']):null; $f=isset($d['target_filling_id'])?$fillings->findOrFail($d['target_filling_id']):null; if(!$f && $log?->fuel_filling_id) $f=$fillings->findOrFail($log->fuel_filling_id); if(!$log&&!$f)throw ValidationException::withMessages(['target_log_id'=>'Selecione a leitura incorreta que será substituída.']); if($lock && (($log && !$log->is_reading_usable) || ($f && !$f->is_km_reading_usable))) throw ValidationException::withMessages(['target_log_id'=>'Esta leitura já foi revisada ou corrigida por outro gestor.']); return ['log'=>$log,'filling'=>$f,'date'=>$log?->read_at??$log?->created_at??$f?->filled_at,'value'=>['km'=>$log?->type==='km'?$log->new_value:$f?->vehicle_km,'hours'=>$log?->type==='hours'?$log->new_value:$f?->vehicle_hours]]; }
    private function impacts(Vehicle $v,array $d):array { $t=$this->target($v,$d); return array_values(array_filter([ $t['log'] ? ['type'=>'Leitura que será corrigida','reference'=>'Registrada em '.($t['date']?->format('d/m/Y H:i') ?? 'data não informada')] : null, $t['filling'] ? ['type'=>'Abastecimento relacionado','reference'=>'O cálculo de consumo poderá ser atualizado.'] : null, ['type'=>'Quilometragem atual do veículo','reference'=>'Será reconciliada utilizando a última leitura válida.'] ])); }
    private function byToken(string $token):VehicleReadingCorrectionEvidence { return VehicleReadingCorrectionEvidence::query()->where('token_hash',hash('sha256',$token))->firstOrFail(); }
    private function authorizeCorrection(Vehicle $v): void
    {
        $user = auth()->user();
        $location = $user ? app(ActiveContextService::class)->activeLocation($user) : null;
        $permission = app(ProfilePermissionService::class)->allows($user, 'vehicles.correct_readings');
        $checks = [
            'authenticated' => (bool) $user,
            'permission' => $permission,
            'tenant_match' => $user && (int) $v->tenant_id === (int) $user->tenant_id,
            'division_match' => (int) $v->division_id === (int) session('active_division_id'),
            'location_present' => (bool) $location,
            'location_match' => $location && (int) $v->location_id === (int) $location->id,
        ];

        abort_unless(! in_array(false, $checks, true), 403);
    }
}
