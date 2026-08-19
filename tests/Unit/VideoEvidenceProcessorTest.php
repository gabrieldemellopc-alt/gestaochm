<?php

namespace Tests\Unit;

use App\Services\VideoEvidenceProcessor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VideoEvidenceProcessorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.video_processor', ['driver' => 'remote', 'url' => 'https://video.example.test', 'token' => 'test-token', 'timeout' => 90]);
    }

    public function test_it_reports_when_ffprobe_is_unavailable(): void
    {
        config()->set('services.video_processor.driver', 'local');
        $processor = new class extends VideoEvidenceProcessor {
            public function availability(): array { return ['ffmpeg' => false, 'ffprobe' => false]; }
        };

        $result = $processor->process(__FILE__, sys_get_temp_dir().DIRECTORY_SEPARATOR.'unused-video.mp4');

        $this->assertSame('unavailable', $result['status']);
        $this->assertStringContainsString('FFprobe', $result['message']);
    }

    public function test_remote_processor_saves_mp4_and_uses_duration_header(): void
    {
        Http::fake(['https://video.example.test/process' => Http::response('processed-mp4', 200, ['X-Video-Duration' => '4.25'])]);
        $output = $this->outputPath();

        $result = app(VideoEvidenceProcessor::class)->process($this->inputPath(), $output);

        $this->assertSame(['status' => 'ready', 'duration' => 4.25], $result);
        $this->assertSame('processed-mp4', file_get_contents($output));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://video.example.test/process'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->hasFile('video'));
    }

    #[DataProvider('remoteErrorResponses')]
    public function test_remote_processor_maps_errors(int $status, array|string $body, string $message): void
    {
        Http::fake(['https://video.example.test/process' => Http::response($body, $status)]);
        $result = app(VideoEvidenceProcessor::class)->process($this->inputPath(), $this->outputPath());
        $this->assertSame('failed', $result['status']);
        $this->assertSame($message, $result['message']);
    }

    public static function remoteErrorResponses(): array
    {
        return [
            'unauthorized' => [401, '', 'Serviço de processamento de vídeo não autorizado.'],
            'too large' => [413, '', 'O vídeo excede o tamanho máximo permitido.'],
            'unprocessable' => [422, ['message' => 'O vídeo é inválido.'], 'O vídeo é inválido.'],
            'server error' => [500, '', 'Serviço de processamento de vídeo temporariamente indisponível.'],
        ];
    }

    public function test_remote_processor_maps_connection_failures(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection failed'));
        $result = app(VideoEvidenceProcessor::class)->process($this->inputPath(), $this->outputPath());
        $this->assertSame('failed', $result['status']);
        $this->assertSame('Não foi possível processar o vídeo no momento. Tente novamente.', $result['message']);
    }

    private function inputPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'video-input-');
        file_put_contents($path, 'raw-video');
        return $path;
    }

    private function outputPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'video-output-');
        @unlink($path);
        return $path.'.mp4';
    }
}
