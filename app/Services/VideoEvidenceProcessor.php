<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class VideoEvidenceProcessor
{
    public const MAX_SECONDS = 10;

    public function availability(): array
    {
        return ['ffmpeg' => $this->available('ffmpeg'), 'ffprobe' => $this->available('ffprobe')];
    }

    public function process(string $input, string $output): array
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

    private function duration(string $file): ?float
    {
        $process = new Process(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', $file]);
        $process->setTimeout(20); $process->run(); $value = trim($process->getOutput());
        return $process->isSuccessful() && is_numeric($value) ? (float) $value : null;
    }
    private function available(string $binary): bool { $p = new Process([$binary, '-version']); $p->setTimeout(5); $p->run(); return $p->isSuccessful(); }
}
