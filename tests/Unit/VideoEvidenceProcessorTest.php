<?php

namespace Tests\Unit;

use App\Services\VideoEvidenceProcessor;
use PHPUnit\Framework\TestCase;

class VideoEvidenceProcessorTest extends TestCase
{
    public function test_it_reports_when_ffprobe_is_unavailable(): void
    {
        $processor = new class extends VideoEvidenceProcessor {
            public function availability(): array { return ['ffmpeg' => false, 'ffprobe' => false]; }
        };

        $result = $processor->process(__FILE__, sys_get_temp_dir().DIRECTORY_SEPARATOR.'unused-video.mp4');

        $this->assertSame('unavailable', $result['status']);
        $this->assertStringContainsString('FFprobe', $result['message']);
    }
}
