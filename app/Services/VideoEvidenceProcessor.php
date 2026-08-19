<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

class VideoEvidenceProcessor
{
    public const MAX_SECONDS = 10;

    public function availability(): array
    {
        if ($this->driver() === 'remote') return ['remote' => true];
        return ['ffmpeg' => $this->available('ffmpeg'), 'ffprobe' => $this->available('ffprobe')];
    }

    public function process(string $input, string $output): array
    {
        return $this->driver() === 'remote' ? $this->processRemotely($input, $output) : $this->processLocally($input, $output);
    }

    private function processLocally(string $input, string $output): array
    {
        $tools = $this->availability();
        if (! $tools['ffprobe']) return ['status' => 'unavailable', 'message' => 'Validação de vídeo indisponível neste servidor. O FFprobe precisa estar instalado para utilizar evidências em vídeo.'];
        $duration = $this->duration($input);
        if ($duration === null) return ['status' => 'invalid', 'message' => 'O arquivo enviado não é um vídeo válido.'];
        if ($duration > self::MAX_SECONDS) return ['status' => 'too_long', 'message' => 'O vídeo deve ter no máximo 10 segundos.'];
        if (! $tools['ffmpeg']) return ['status' => 'unavailable', 'message' => 'Processamento de vídeo indisponível neste servidor. O FFmpeg precisa estar instalado para utilizar evidências em vídeo.'];
        $process = new Process(['ffmpeg', '-y', '-i', $input, '-t', '10', '-vf', 'scale=-2:480:force_original_aspect_ratio=decrease', '-r', '24', '-an', '-c:v', 'libx264', '-preset', 'veryfast', '-b:v', '900k', '-movflags', '+faststart', $output]);
        $process->setTimeout(60); $process->run();
        if (! $process->isSuccessful() || ! is_file($output)) return ['status' => 'failed', 'message' => 'Não foi possível normalizar o vídeo enviado.'];
        $finalDuration = $this->duration($output);
        if ($finalDuration === null || $finalDuration > self::MAX_SECONDS) { @unlink($output); return ['status' => 'failed', 'message' => 'O vídeo normalizado não passou na validação final.']; }
        return ['status' => 'ready', 'duration' => $finalDuration];
    }

    public function health(): array
    {
        if ($this->driver() !== 'remote') return $this->availability();
        try {
            $response = Http::withToken((string) config('services.video_processor.token'))->timeout($this->timeout())->get($this->remoteUrl('/health'));
            return $response->successful() ? (array) $response->json() : ['status' => 'unavailable'];
        } catch (Throwable $exception) {
            Log::warning('Remote video processor health check failed.', ['exception' => $exception::class]);
            return ['status' => 'unavailable'];
        }
    }

    private function processRemotely(string $input, string $output): array
    {
        if (! is_file($input) || ! config('services.video_processor.url') || ! config('services.video_processor.token')) return ['status' => 'unavailable', 'message' => 'Serviço de processamento de vídeo indisponível neste servidor.'];
        try {
            $response = Http::withToken((string) config('services.video_processor.token'))->timeout($this->timeout())->attach('video', fopen($input, 'r'), basename($input))->post($this->remoteUrl('/process'));
        } catch (ConnectionException $exception) {
            Log::warning('Remote video processor connection failed.', ['exception' => $exception::class]);
            return ['status' => 'failed', 'message' => 'Não foi possível processar o vídeo no momento. Tente novamente.'];
        } catch (Throwable $exception) {
            Log::warning('Remote video processor request failed.', ['exception' => $exception::class]);
            return ['status' => 'failed', 'message' => 'Não foi possível processar o vídeo no momento. Tente novamente.'];
        }
        if ($response->successful()) {
            if (@file_put_contents($output, $response->body()) === false) {
                Log::warning('Remote video processor output could not be saved.');
                return ['status' => 'failed', 'message' => 'Não foi possível processar o vídeo no momento. Tente novamente.'];
            }
            $duration = $response->header('X-Video-Duration');
            return ['status' => 'ready', 'duration' => is_numeric($duration) ? (float) $duration : null];
        }
        Log::warning('Remote video processor returned an error.', ['status' => $response->status()]);
        return match ($response->status()) {
            401, 403 => ['status' => 'failed', 'message' => 'Serviço de processamento de vídeo não autorizado.'],
            413 => ['status' => 'failed', 'message' => 'O vídeo excede o tamanho máximo permitido.'],
            422 => ['status' => 'failed', 'message' => $this->safeRemoteMessage($response->json('message'))],
            default => ['status' => 'failed', 'message' => $response->serverError() ? 'Serviço de processamento de vídeo temporariamente indisponível.' : 'Não foi possível processar o vídeo no momento. Tente novamente.'],
        };
    }

    private function safeRemoteMessage(mixed $message): string
    {
        if (is_string($message)) {
            $message = trim(strip_tags($message));
            if ($message !== '' && mb_strlen($message) <= 300) return $message;
        }
        return 'O vídeo enviado não pôde ser processado.';
    }

    private function driver(): string { return config('services.video_processor.driver') === 'remote' ? 'remote' : 'local'; }
    private function remoteUrl(string $path): string { return rtrim((string) config('services.video_processor.url'), '/').$path; }
    private function timeout(): int { return max(1, (int) config('services.video_processor.timeout', 90)); }

    private function duration(string $file): ?float
    {
        $process = new Process(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', $file]);
        $process->setTimeout(20); $process->run(); $value = trim($process->getOutput());
        return $process->isSuccessful() && is_numeric($value) ? (float) $value : null;
    }
    private function available(string $binary): bool { $p = new Process([$binary, '-version']); $p->setTimeout(5); $p->run(); return $p->isSuccessful(); }
}
