<?php

namespace App\Services\Fuel;

use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleVisionFuelSheetService
{
    public function analyze(
        UploadedFile $file,
        array $context
    ): array {
        $startedAt = microtime(true);

        $credentials = $this->credentials();

        $tokenStartedAt = microtime(true);
        $token = $this->accessToken($credentials);
        $tokenSeconds = microtime(true) - $tokenStartedAt;

        $prepareStartedAt = microtime(true);

        $preparedImage =
            $this->prepareImage(
                $file
            );

        $prepareSeconds =
            microtime(true) - $prepareStartedAt;

        $binary =
            file_get_contents(
                $preparedImage
            );

        if ($binary === false) {
            throw new RuntimeException(
                'Não foi possível ler a imagem enviada.'
            );
        }

        $visionStartedAt = microtime(true);

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(90)
            ->post(
                'https://vision.googleapis.com/v1/images:annotate',
                [
                    'requests' => [
                        [
                            'image' => [
                                'content' => base64_encode($binary),
                            ],
                            'features' => [
                                [
                                    'type' =>
                                        'DOCUMENT_TEXT_DETECTION',
                                ],
                            ],
                            'imageContext' => [
                                'languageHints' => ['pt-BR'],
                            ],
                        ],
                    ],
                ]
            );

        $visionSeconds =
            microtime(true) - $visionStartedAt;

        \Log::info('Fuel photo timing', [
            'token_seconds' =>
                round($tokenSeconds, 3),

            'prepare_seconds' =>
                round($prepareSeconds, 3),

            'vision_seconds' =>
                round($visionSeconds, 3),

            'prepared_bytes' =>
                is_file($preparedImage)
                    ? filesize($preparedImage)
                    : null,

            'total_until_vision_seconds' =>
                round(
                    microtime(true) - $startedAt,
                    3
                ),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Google Vision retornou HTTP '
                .$response->status().'.'
            );
        }

        $json = $response->json();

        if (
            isset($preparedImage)
            && $preparedImage !== $file->getRealPath()
            && is_file($preparedImage)
        ) {
            @unlink($preparedImage);
        }

        if ($error = data_get($json, 'responses.0.error')) {
            throw new RuntimeException(
                data_get(
                    $error,
                    'message',
                    'Falha ao processar a imagem.'
                )
            );
        }

        $annotation = data_get(
            $json,
            'responses.0.fullTextAnnotation'
        );

        if (! is_array($annotation)) {
            throw new RuntimeException(
                'Nenhum texto foi encontrado na imagem.'
            );
        }

        $rawText = trim(
            (string) ($annotation['text'] ?? '')
        );

        $words = $this->extractWords(
            $annotation
        );

        if ($words->isEmpty()) {
            throw new RuntimeException(
                'Nenhuma palavra foi identificada na folha.'
            );
        }

        $vehicles = Vehicle::query()
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division_id'])
            ->where('location_id', $context['location_id'])
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'asset_code',
                'plate',
                'current_km',
                'current_hours',
                'km_control_enabled',
                'hours_control_enabled',
            ]);

        $columns = $this->detectColumns(
            $words
        );

        $rows = $this->reconstructRows(
            $words,
            $columns,
            $vehicles
        );

        $header = $this->parseHeader(
            $rawText
        );

        $sum = round(
            collect($rows)->sum(
                fn ($row) =>
                    (float) ($row['liters'] ?? 0)
            ),
            3
        );

        $declared =
            $header['declared_total_liters'];

        return [
            'header' => $header,

            'rows' => $rows,

            'validation' => [
                'rows_count' => count($rows),

                'rows_liters_sum' => $sum,

                'declared_total_liters' =>
                    $declared,

                'total_difference' =>
                    $declared !== null
                        ? round(
                            $sum - $declared,
                            3
                        )
                        : null,

                'total_matches' =>
                    $declared !== null
                    && abs(
                        $sum - $declared
                    ) <= 0.01,
            ],

            'raw_text' => $rawText,

            'engine' => 'google_cloud_vision',
        ];
    }


    private function prepareImage(
        UploadedFile $file
    ): string {
        $source =
            $file->getRealPath();

        if (
            ! $source
            || ! is_file($source)
        ) {
            throw new RuntimeException(
                'Arquivo temporário não encontrado.'
            );
        }

        $extension =
            strtolower(
                $file->getClientOriginalExtension()
            );

        $mime =
            strtolower(
                (string) $file->getMimeType()
            );

        $isPdf =
            $extension === 'pdf'
            || $mime === 'application/pdf';

        $temp =
            tempnam(
                sys_get_temp_dir(),
                'chm_fuel_vision_'
            );

        if ($temp === false) {
            throw new RuntimeException(
                'Não foi possível criar arquivo temporário.'
            );
        }

        $output =
            $temp.'.jpg';

        @unlink($temp);

        if ($isPdf) {
            /*
             * PDF:
             * 1) Ghostscript rasteriza somente a primeira página.
             * 2) ImageMagick faz apenas o pós-processamento.
             *
             * Isso evita que o convert delegue toda a leitura do PDF
             * ao Ghostscript dentro de um único pipeline pesado.
             */
            $raster =
                $temp.'_page1.jpg';

            $gsCommand = sprintf(
                '/usr/bin/gs '
                .'-q '
                .'-dSAFER '
                .'-dBATCH '
                .'-dNOPAUSE '
                .'-dFirstPage=1 '
                .'-dLastPage=1 '
                .'-sDEVICE=jpeg '
                .'-r150 '
                .'-dJPEGQ=88 '
                .'-sOutputFile=%s '
                .'%s 2>&1',
                escapeshellarg($raster),
                escapeshellarg($source)
            );

            $gsStartedAt =
                microtime(true);

            exec(
                $gsCommand,
                $gsMessages,
                $gsExitCode
            );

            $gsSeconds =
                microtime(true)
                - $gsStartedAt;

            if (
                $gsExitCode !== 0
                || ! is_file($raster)
                || filesize($raster) <= 0
            ) {
                @unlink($raster);
                @unlink($output);

                throw new RuntimeException(
                    'Não foi possível converter a primeira página do PDF para leitura.'
                );
            }

            $command = sprintf(
                '/usr/bin/convert %s '
                .'-auto-orient '
                .'-resize "3000x3000>" '
                .'-deskew 40%% '
                .'-colorspace sRGB '
                .'-contrast-stretch 0.5%%x0.5%% '
                .'-strip '
                .'-quality 88 '
                .'%s 2>&1',
                escapeshellarg($raster),
                escapeshellarg($output)
            );
        } else {
            $command = sprintf(
                '/usr/bin/convert %s '
                .'-auto-orient '
                .'-deskew 40%% '
                .'-colorspace sRGB '
                .'-contrast-stretch 0.5%%x0.5%% '
                .'-quality 92 '
                .'%s 2>&1',
                escapeshellarg($source),
                escapeshellarg($output)
            );
        }

        $postStartedAt =
            microtime(true);

        exec(
            $command,
            $messages,
            $exitCode
        );

        $postSeconds =
            microtime(true)
            - $postStartedAt;

        if ($isPdf) {
            \Log::info('Fuel photo prepare timing', [
                'ghostscript_seconds' =>
                    round($gsSeconds ?? 0, 3),

                'imagemagick_seconds' =>
                    round($postSeconds, 3),

                'raster_bytes' =>
                    isset($raster)
                    && is_file($raster)
                        ? filesize($raster)
                        : null,

                'output_bytes' =>
                    is_file($output)
                        ? filesize($output)
                        : null,
            ]);
        }

        if (
            $isPdf
            && isset($raster)
            && is_file($raster)
        ) {
            @unlink($raster);
        }

        if (
            $exitCode !== 0
            || ! is_file($output)
            || filesize($output) <= 0
        ) {
            @unlink($output);

            if ($isPdf) {
                throw new RuntimeException(
                    'Não foi possível converter a primeira página do PDF para leitura. Tente enviar JPG ou PNG.'
                );
            }

            /*
             * Para imagem comum ainda podemos usar o original.
             */
            return $source;
        }

        return $output;
    }


    private function credentials(): array
    {
        $path = storage_path(
            'app/private/google/chm-vision.json'
        );

        if (! is_file($path)) {
            throw new RuntimeException(
                'Credencial Google Vision não encontrada.'
            );
        }

        $credentials = json_decode(
            file_get_contents($path),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        foreach (
            ['client_email', 'private_key']
            as $field
        ) {
            if (blank($credentials[$field] ?? null)) {
                throw new RuntimeException(
                    'Credencial Google inválida.'
                );
            }
        }

        return $credentials;
    }


    private function accessToken(
        array $credentials
    ): string {
        $now = time();

        $header = $this->b64url(
            json_encode([
                'alg' => 'RS256',
                'typ' => 'JWT',
            ])
        );

        $claims = $this->b64url(
            json_encode([
                'iss' =>
                    $credentials['client_email'],

                'scope' =>
                    'https://www.googleapis.com/auth/cloud-platform',

                'aud' =>
                    'https://oauth2.googleapis.com/token',

                'iat' => $now,
                'exp' => $now + 3600,
            ])
        );

        $unsigned =
            $header.'.'.$claims;

        $signature = '';

        if (! openssl_sign(
            $unsigned,
            $signature,
            $credentials['private_key'],
            OPENSSL_ALGO_SHA256
        )) {
            throw new RuntimeException(
                'Falha ao autenticar no Google Vision.'
            );
        }

        $jwt =
            $unsigned.'.'
            .$this->b64url($signature);

        $response = Http::asForm()
            ->timeout(30)
            ->post(
                'https://oauth2.googleapis.com/token',
                [
                    'grant_type' =>
                        'urn:ietf:params:oauth:grant-type:jwt-bearer',

                    'assertion' => $jwt,
                ]
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Não foi possível autenticar no Google Vision.'
            );
        }

        $token =
            $response->json('access_token');

        if (! $token) {
            throw new RuntimeException(
                'Google não retornou token de acesso.'
            );
        }

        return $token;
    }


    private function b64url(
        string $data
    ): string {
        return rtrim(
            strtr(
                base64_encode($data),
                '+/',
                '-_'
            ),
            '='
        );
    }


    private function extractWords(
        array $annotation
    ): Collection {
        $words = collect();

        foreach (
            $annotation['pages'] ?? []
            as $page
        ) {
            foreach (
                $page['blocks'] ?? []
                as $block
            ) {
                foreach (
                    $block['paragraphs'] ?? []
                    as $paragraph
                ) {
                    foreach (
                        $paragraph['words'] ?? []
                        as $word
                    ) {
                        $text = '';

                        foreach (
                            $word['symbols'] ?? []
                            as $symbol
                        ) {
                            $text .=
                                $symbol['text'] ?? '';
                        }

                        $text = trim($text);

                        if ($text === '') {
                            continue;
                        }

                        $vertices =
                            data_get(
                                $word,
                                'boundingBox.vertices',
                                []
                            );

                        $xs = collect($vertices)
                            ->pluck('x')
                            ->map(
                                fn ($v) =>
                                    (float) ($v ?? 0)
                            );

                        $ys = collect($vertices)
                            ->pluck('y')
                            ->map(
                                fn ($v) =>
                                    (float) ($v ?? 0)
                            );

                        if (
                            $xs->isEmpty()
                            || $ys->isEmpty()
                        ) {
                            continue;
                        }

                        $minX = $xs->min();
                        $maxX = $xs->max();
                        $minY = $ys->min();
                        $maxY = $ys->max();

                        $words->push([
                            'text' => $text,
                            'norm' =>
                                $this->normalizeText(
                                    $text
                                ),
                            'x' =>
                                ($minX + $maxX) / 2,
                            'y' =>
                                ($minY + $maxY) / 2,
                            'width' =>
                                max(1, $maxX - $minX),
                            'height' =>
                                max(1, $maxY - $minY),
                        ]);
                    }
                }
            }
        }

        return $words;
    }


    private function detectColumns(
        Collection $words
    ): array {
        $pageWidth = max(
            1,
            (float) $words->max(
                fn ($word) =>
                    $word['x']
                    + ($word['width'] / 2)
            )
        );

        $find = function (
            array $terms,
            string $mode = 'first'
        ) use ($words) {
            $found = $words
                ->filter(
                    fn ($word) =>
                        in_array(
                            $word['norm'],
                            $terms,
                            true
                        )
                );

            if ($found->isEmpty()) {
                return null;
            }

            if ($mode === 'lowest') {
                return $found
                    ->sortByDesc('y')
                    ->first();
            }

            return $found
                ->sortBy('y')
                ->first();
        };

        $code = $find([
            'CODIGO',
        ], 'lowest');

        $plate = $find([
            'PLACA',
        ], 'lowest');

        $liters = $find([
            'LITROS',
        ], 'lowest');

        $previous = $find([
            'ULTIMO',
        ], 'lowest');

        $newKm = $find([
            'ABASTECIMENTO',
        ], 'lowest');

        $arla = $find([
            'ARLA',
        ], 'lowest');

        $centers = [
            'code' =>
                $code['x'] ?? $pageWidth * .20,

            'plate' =>
                $plate['x'] ?? $pageWidth * .34,

            'liters' =>
                $liters['x'] ?? $pageWidth * .46,

            'previous_km' =>
                $previous['x'] ?? $pageWidth * .60,

            'new_km' =>
                $newKm['x'] ?? $pageWidth * .76,

            'arla' =>
                $arla['x'] ?? $pageWidth * .90,
        ];

        asort($centers);

        $headerWords = collect([
            $code,
            $plate,
            $liters,
            $previous,
            $newKm,
            $arla,
        ])->filter();

        $headerY = $headerWords->isNotEmpty()
            ? (float) $headerWords->max('y')
            : (float) $words->min('y')
                + 100;

        return [
            'centers' => $centers,
            'header_y' => $headerY,
            'page_width' => $pageWidth,
        ];
    }


    private function reconstructRows(
        Collection $words,
        array $columns,
        Collection $vehicles
    ): array {
        $headerY =
            (float) $columns['header_y'];

        $grid =
            $this->fixedRowGrid(
                $words,
                $columns
            );

        if (! $grid) {
            throw new RuntimeException(
                'Não foi possível identificar com segurança '
                .'as linhas numeradas da ficha.'
            );
        }

        $centers =
            $grid['centers'];

        $spacing =
            $grid['spacing'];

        $rowCount =
            (int) (
                $grid['row_count']
                ?? count($centers)
            );

        $pageWidth =
            (float) $columns['page_width'];

        /*
         * Limites verticais reais da área de dados.
         *
         * Dentro dessa faixa, toda palavra é atribuída
         * à linha fisicamente mais próxima. Não existe
         * mais uma pequena zona morta entre duas linhas.
         */
        $gridTop =
            min($centers)
            - ($spacing * .52);

        $gridBottom =
            max($centers)
            + ($spacing * .52);

        $numberColumnLimit =
            min(
                (float) $columns['centers']['code'] * .75,
                $pageWidth * .18
            );

        $rows = [];

        for ($row = 1; $row <= $rowCount; $row++) {
            $rows[$row] = [
                'code' => [],
                'plate' => [],
                'liters' => [],
                'previous_km' => [],
                'new_km' => [],
                'arla' => [],
            ];
        }

        foreach ($words as $word) {
            if (
                $word['y'] <= $headerY + 5
            ) {
                continue;
            }

            if (
                $word['x'] < $numberColumnLimit
                && preg_match(
                    '/^\d{1,2}$/',
                    $word['norm']
                )
            ) {
                $number =
                    (int) $word['norm'];

                if (
                    $number >= 1
                    && $number <= 60
                ) {
                    continue;
                }
            }

            $nearestRow = null;
            $nearestDistance = INF;

            foreach (
                $centers
                as $rowNumber => $centerY
            ) {
                $distance =
                    abs(
                        $word['y']
                        - $centerY
                    );

                if (
                    $distance < $nearestDistance
                ) {
                    $nearestDistance =
                        $distance;

                    $nearestRow =
                        $rowNumber;
                }
            }

            if (
                $nearestRow === null
                || $word['y'] < $gridTop
                || $word['y'] > $gridBottom
            ) {
                continue;
            }

            if (
                $word['x'] < $numberColumnLimit
            ) {
                continue;
            }

            $column =
                $this->nearestColumn(
                    $word['x'],
                    $columns['centers']
                );

            $rows[$nearestRow][$column][]
                = $word;
        }

        $result = [];

        foreach (
            $rows
            as $rowNumber => $cellWords
        ) {
            $cells = [];

            foreach (
                $cellWords
                as $column => $parts
            ) {
                $cells[$column] =
                    collect($parts)
                        ->sortBy('x')
                        ->pluck('text')
                        ->implode(' ');

                $cells[$column] =
                    trim(
                        $cells[$column]
                    );
            }

            $codeRead =
                $this->cleanOperationalCode(
                    $cells['code'] ?? '',
                    $rowNumber
                );

            $plateRead =
                $this->cleanCellText(
                    $cells['plate'] ?? ''
                );

            $liters =
                $this->parseDecimal(
                    $cells['liters'] ?? null
                );

            $previousKm =
                $this->parseInteger(
                    $cells['previous_km'] ?? null
                );

            $newKm =
                $this->parseInteger(
                    $cells['new_km'] ?? null
                );

            $arla =
                $this->normalizeOcrArla(
                    $this->parseDecimal(
                        $cells['arla'] ?? null
                    )
                );

            $hasContent =
                $codeRead !== ''
                || $plateRead !== ''
                || $liters !== null
                || $previousKm !== null
                || $newKm !== null
                || $arla !== null;

            if (! $hasContent) {
                continue;
            }

            $match =
                $this->matchVehicle(
                    $codeRead,
                    $plateRead,
                    $vehicles
                );

            $vehicle =
                $match['vehicle'];

            $warnings =
                $match['warnings'];

            $distanceKm = null;

            if (
                $previousKm !== null
                && $newKm !== null
            ) {
                $distanceKm =
                    $newKm - $previousKm;
            }

            $kmStats =
                $vehicle
                    ? $this->vehicleKmStatistics(
                        $vehicle
                    )
                    : [
                        'samples' => 0,
                        'average_km' => null,
                        'median_km' => null,
                    ];

            if (
                $previousKm !== null
                && $newKm !== null
                && $newKm < $previousKm
            ) {
                $warnings[] =
                    'Novo KM é menor que o último KM impresso.';
            }

            $kmReferenceNote = null;
            $kmReferenceLevel = 'ok';

            if (
                $vehicle
                && $vehicle->km_control_enabled
                && $previousKm !== null
                && $vehicle->current_km !== null
            ) {
                $currentKm =
                    (int) $vehicle->current_km;

                if ($previousKm > $currentKm) {
                    $kmReferenceNote =
                        'Último KM da folha está acima do último KM registrado no CHM. Pode haver abastecimentos anteriores ainda não lançados.';

                    $kmReferenceLevel =
                        'info';

                } elseif ($previousKm < $currentKm) {
                    $kmReferenceNote =
                        'Último KM da folha está abaixo do último KM registrado no CHM. Revise se a folha foi preenchida com uma leitura antiga.';

                    $kmReferenceLevel =
                        'warning';
                }
            }

            if (
                $vehicle
                && $vehicle->km_control_enabled
                && $newKm !== null
                && $vehicle->current_km !== null
                && $newKm < (int) $vehicle->current_km
            ) {
                $warnings[] =
                    'Novo KM é menor que o último KM registrado no CHM.';
            }

            if (
                $distanceKm !== null
                && $distanceKm >= 0
                && $kmStats['samples'] >= 3
                && $kmStats['average_km'] > 0
            ) {
                $ratio =
                    $distanceKm
                    / $kmStats['average_km'];

                if (
                    $ratio >= 2.5
                    || $ratio <= .25
                ) {
                    $warnings[] =
                        'KM rodado está muito diferente da média '
                        .'histórica entre abastecimentos.';
                }
            }

            if (
                $arla !== null
                && $arla > 150
            ) {
                $warnings[] =
                    'Valor de ARLA parece elevado; revisar a leitura.';
            }

            if (
                $liters !== null
                && $liters <= 0
            ) {
                $warnings[] =
                    'Quantidade de litros inválida.';
            }

            $confidence = 1.0;

            $confidence -= min(
                .60,
                count($warnings) * .15
            );

            if (! $vehicle) {
                $confidence -= .25;
            }

            $reviewLevel =
                $match['review_level'];

            if (
                $reviewLevel === 'ok'
                && count($warnings)
            ) {
                $reviewLevel = 'warning';
            }

            if (
                in_array(
                    'Novo KM é menor que o último KM impresso.',
                    $warnings,
                    true
                )
                || in_array(
                    'Novo KM é menor que o último KM registrado no CHM.',
                    $warnings,
                    true
                )
            ) {
                $reviewLevel =
                    'critical';
            } elseif (
                ($kmReferenceLevel ?? 'ok')
                    === 'warning'
                && $reviewLevel === 'ok'
            ) {
                $reviewLevel =
                    'warning';
            }

            $result[] = [
                'line' =>
                    $rowNumber,

                'code_read' =>
                    $codeRead ?: null,

                'plate_read' =>
                    $plateRead ?: null,

                'liters' =>
                    $liters,

                'previous_km_printed' =>
                    $previousKm,

                'km_at_filling' =>
                    $newKm,

                'distance_km' =>
                    $distanceKm,

                'arla_liters' =>
                    $arla,

                'suggested_vehicle_id' =>
                    $vehicle?->id,

                'matched_vehicle' =>
                    $vehicle
                        ? [
                            'id' =>
                                $vehicle->id,

                            'name' =>
                                $vehicle->name,

                            'asset_code' =>
                                $vehicle->asset_code,

                            'plate' =>
                                $vehicle->plate,

                            'current_km' =>
                                $vehicle->current_km,

                            'current_hours' =>
                                $vehicle->current_hours,
                        ]
                        : null,

                'historical_km' => [
                    'samples' =>
                        $kmStats['samples'],

                    'average_km' =>
                        $kmStats['average_km'],

                    'median_km' =>
                        $kmStats['median_km'],
                ],

                'km_reference' => [
                    'level' =>
                        $kmReferenceLevel ?? 'ok',

                    'message' =>
                        $kmReferenceNote ?? null,

                    'current_km' =>
                        $vehicle?->current_km,
                ],

                'match_type' =>
                    $match['match_type'],

                'plate_score' =>
                    $match['plate_score'],

                'review_level' =>
                    $reviewLevel,

                'confidence' =>
                    max(
                        0,
                        round(
                            $confidence,
                            2
                        )
                    ),

                'warnings' =>
                    array_values(
                        array_unique(
                            $warnings
                        )
                    ),
            ];
        }

        return $result;
    }


    private function fixedRowGrid(
        Collection $words,
        array $columns
    ): ?array {
        $pageWidth =
            (float) $columns['page_width'];

        $headerY =
            (float) $columns['header_y'];

        $leftLimit =
            min(
                (float) $columns['centers']['code'] * .75,
                $pageWidth * .18
            );

        /*
         * Âncoras são os números impressos da primeira
         * coluna da ficha.
         */
        $anchors = [];

        foreach ($words as $word) {
            if (
                $word['y'] <= $headerY
                || $word['x'] >= $leftLimit
            ) {
                continue;
            }

            if (
                ! preg_match(
                    '/^\d{1,2}$/',
                    $word['norm']
                )
            ) {
                continue;
            }

            $number =
                (int) $word['norm'];

            if (
                $number < 1
                || $number > 60
            ) {
                continue;
            }

            /*
             * Se o Vision encontrou o mesmo número
             * mais de uma vez, preserva o que estiver
             * mais acima.
             */
            if (
                ! isset($anchors[$number])
                || $word['y']
                    < $anchors[$number]
            ) {
                $anchors[$number] =
                    (float) $word['y'];
            }
        }

        if (count($anchors) < 3) {
            return $this->contentBasedRowGrid(
                $words,
                $columns
            );
        }

        ksort($anchors);


        /*
         * -------------------------------------------------
         * PASSO 1
         * Estima o espaçamento por MEDIANA das inclinações
         * entre pares de linhas.
         *
         * Isso é muito menos sensível a um número de linha
         * que o OCR tenha lido incorretamente.
         * -------------------------------------------------
         */

        $pairSlopes = [];

        $anchorNumbers =
            array_keys($anchors);

        $anchorCount =
            count($anchorNumbers);

        for (
            $i = 0;
            $i < $anchorCount;
            $i++
        ) {
            for (
                $j = $i + 1;
                $j < $anchorCount;
                $j++
            ) {
                $rowA =
                    $anchorNumbers[$i];

                $rowB =
                    $anchorNumbers[$j];

                $rowDelta =
                    $rowB - $rowA;

                if ($rowDelta <= 0) {
                    continue;
                }

                $yDelta =
                    $anchors[$rowB]
                    - $anchors[$rowA];

                if ($yDelta <= 0) {
                    continue;
                }

                $candidate =
                    $yDelta
                    / $rowDelta;

                if (
                    $candidate >= 8
                    && $candidate <= 150
                ) {
                    $pairSlopes[] =
                        $candidate;
                }
            }
        }

        if (! $pairSlopes) {
            return $this->contentBasedRowGrid(
                $words,
                $columns
            );
        }

        sort($pairSlopes);

        $median = function (
            array $values
        ): float {
            sort($values);

            $count =
                count($values);

            $middle =
                intdiv(
                    $count,
                    2
                );

            if ($count % 2) {
                return (float)
                    $values[$middle];
            }

            return (
                (float) $values[$middle - 1]
                + (float) $values[$middle]
            ) / 2;
        };

        $slope =
            $median(
                $pairSlopes
            );

        if (
            $slope < 8
            || $slope > 150
        ) {
            return $this->contentBasedRowGrid(
                $words,
                $columns
            );
        }


        /*
         * -------------------------------------------------
         * PASSO 2
         * Calcula vários interceptos:
         *
         * Y = intercept + slope * número_da_linha
         *
         * e usa novamente a mediana.
         * -------------------------------------------------
         */

        $intercepts = [];

        foreach (
            $anchors
            as $row => $y
        ) {
            $intercepts[] =
                $y
                - ($slope * $row);
        }

        $intercept =
            $median(
                $intercepts
            );


        /*
         * -------------------------------------------------
         * PASSO 3
         * Remove âncoras incompatíveis com a grade.
         *
         * Um número mal reconhecido pelo OCR deixa de
         * distorcer toda a ficha.
         * -------------------------------------------------
         */

        $residualTolerance =
            max(
                5.0,
                $slope * .38
            );

        $inliers = [];

        foreach (
            $anchors
            as $row => $y
        ) {
            $expected =
                $intercept
                + ($slope * $row);

            if (
                abs(
                    $y - $expected
                )
                <= $residualTolerance
            ) {
                $inliers[$row] =
                    $y;
            }
        }

        /*
         * Se houve pelo menos 3 âncoras coerentes,
         * refina slope/intercept com regressão somente
         * sobre essas âncoras confiáveis.
         */
        if (count($inliers) >= 3) {

            $n =
                count($inliers);

            $sumX = 0.0;
            $sumY = 0.0;
            $sumXY = 0.0;
            $sumXX = 0.0;

            foreach (
                $inliers
                as $row => $y
            ) {
                $x =
                    (float) $row;

                $sumX += $x;
                $sumY += $y;
                $sumXY += $x * $y;
                $sumXX += $x * $x;
            }

            $denominator =
                ($n * $sumXX)
                - ($sumX * $sumX);

            if (
                abs($denominator)
                >= .0001
            ) {
                $refinedSlope =
                    (
                        ($n * $sumXY)
                        - ($sumX * $sumY)
                    )
                    / $denominator;

                $refinedIntercept =
                    (
                        $sumY
                        - (
                            $refinedSlope
                            * $sumX
                        )
                    )
                    / $n;

                if (
                    $refinedSlope >= 8
                    && $refinedSlope <= 150
                ) {
                    $slope =
                        $refinedSlope;

                    $intercept =
                        $refinedIntercept;
                }
            }
        } else {
            /*
             * Conservador: se a filtragem robusta deixou
             * poucas âncoras, mantém todas para determinar
             * até onde a tabela vai.
             */
            $inliers =
                $anchors;
        }


        /*
         * Não usa um possível número OCR aberrante
         * para determinar o tamanho da ficha.
         */
        $rowCount =
            (int) max(
                array_keys(
                    $inliers
                )
            );

        if (
            $rowCount < 1
            || $rowCount > 60
        ) {
            return $this->contentBasedRowGrid(
                $words,
                $columns
            );
        }

        $centers = [];

        for (
            $row = 1;
            $row <= $rowCount;
            $row++
        ) {
            $centers[$row] =
                $intercept
                + (
                    $slope
                    * $row
                );
        }

        return [
            'centers' =>
                $centers,

            'spacing' =>
                abs($slope),

            'anchors_found' =>
                count($anchors),

            'anchors_inlier' =>
                count($inliers),

            'row_count' =>
                $rowCount,

            'anchor_rows' =>
                array_keys($anchors),

            'inlier_rows' =>
                array_keys($inliers),
        ];
    }


    /*
     * Fallback para fichas em que o OCR não reconhece
     * corretamente a coluna impressa Nº.
     *
     * Em vez de desistir da análise, usa a geometria
     * vertical das células preenchidas para reconstruir
     * as linhas físicas da tabela.
     */
    private function contentBasedRowGrid(
        Collection $words,
        array $columns
    ): ?array {
        $headerY =
            (float) $columns['header_y'];

        $pageWidth =
            (float) $columns['page_width'];

        $numberColumnLimit =
            min(
                (float) $columns['centers']['code'] * .75,
                $pageWidth * .18
            );

        /*
         * Somente palavras pertencentes à área útil
         * das colunas da tabela.
         */
        $candidates =
            $words
                ->filter(
                    function ($word) use (
                        $headerY,
                        $numberColumnLimit,
                        $pageWidth
                    ) {
                        return
                            $word['y'] > $headerY + 5
                            && $word['x'] >= $numberColumnLimit
                            && $word['x'] <= $pageWidth * .97;
                    }
                )
                ->sortBy('y')
                ->values();

        if ($candidates->count() < 6) {
            \Log::warning('Fuel photo content grid failed', [
                'stage' => 'candidates',
                'candidate_count' => $candidates->count(),
                'header_y' => $headerY,
                'page_width' => $pageWidth,
                'number_column_limit' => $numberColumnLimit,
                'words_total' => $words->count(),
            ]);

            return null;
        }

        /*
         * Altura típica de uma palavra reconhecida.
         * Serve somente para agrupar palavras que estão
         * visualmente na mesma linha.
         */
        $heights =
            $candidates
                ->pluck('height')
                ->filter(
                    fn ($value) =>
                        is_numeric($value)
                        && $value > 0
                )
                ->map(
                    fn ($value) =>
                        (float) $value
                )
                ->sort()
                ->values()
                ->all();

        if (! $heights) {
            \Log::warning('Fuel photo content grid failed', [
                'stage' => 'heights',
                'candidate_count' => $candidates->count(),
            ]);

            return null;
        }

        $median = function (
            array $values
        ): float {
            sort($values);

            $count =
                count($values);

            $middle =
                intdiv(
                    $count,
                    2
                );

            if ($count % 2) {
                return (float)
                    $values[$middle];
            }

            return (
                (float) $values[$middle - 1]
                + (float) $values[$middle]
            ) / 2;
        };

        $medianHeight =
            $median($heights);

        $clusterTolerance =
            max(
                10.0,
                min(
                    34.0,
                    $medianHeight * 1.35
                )
            );

        /*
         * Agrupa palavras pela coordenada Y.
         */
        $clusters = [];

        foreach ($candidates as $word) {
            $y =
                (float) $word['y'];

            $bestIndex =
                null;

            $bestDistance =
                INF;

            foreach (
                $clusters
                as $index => $cluster
            ) {
                $distance =
                    abs(
                        $y
                        - $cluster['center']
                    );

                if (
                    $distance <= $clusterTolerance
                    && $distance < $bestDistance
                ) {
                    $bestIndex =
                        $index;

                    $bestDistance =
                        $distance;
                }
            }

            if ($bestIndex === null) {
                $clusters[] = [
                    'ys' => [$y],
                    'center' => $y,
                    'words' => 1,
                ];

                continue;
            }

            $clusters[$bestIndex]['ys'][]
                = $y;

            $clusters[$bestIndex]['words']++;

            $clusters[$bestIndex]['center'] =
                array_sum(
                    $clusters[$bestIndex]['ys']
                )
                / count(
                    $clusters[$bestIndex]['ys']
                );
        }

        usort(
            $clusters,
            fn ($a, $b) =>
                $a['center']
                <=> $b['center']
        );

        /*
         * Não elimina clusters de uma única palavra.
         *
         * Em fichas manuscritas o Vision pode reconhecer
         * somente um valor de determinada linha. Excluir
         * esses grupos cria grandes buracos artificiais
         * na grade.
         */

        if (count($clusters) < 3) {
            \Log::warning('Fuel photo content grid failed', [
                'stage' => 'clusters',
                'cluster_count' => count($clusters),
                'median_height' => $medianHeight,
                'cluster_tolerance' => $clusterTolerance,
                'cluster_centers' => array_map(
                    fn ($cluster) => [
                        'y' => round($cluster['center'], 2),
                        'words' => $cluster['words'],
                    ],
                    $clusters
                ),
            ]);

            return null;
        }


        /*
         * Distâncias entre grupos consecutivos.
         *
         * A maioria delas corresponde a uma única
         * altura de linha. Uma linha vazia produz
         * aproximadamente 2x o espaçamento, o que
         * não prejudica a mediana.
         */
        $differences = [];

        for (
            $i = 1;
            $i < count($clusters);
            $i++
        ) {
            $difference =
                $clusters[$i]['center']
                - $clusters[$i - 1]['center'];

            if (
                $difference >= 8
                && $difference <= 600
            ) {
                $differences[] =
                    $difference;
            }
        }

        if (count($differences) < 2) {
            \Log::warning('Fuel photo content grid failed', [
                'stage' => 'differences',
                'cluster_count' => count($clusters),
                'cluster_centers' => array_map(
                    fn ($cluster) => round($cluster['center'], 2),
                    $clusters
                ),
                'differences' => $differences,
            ]);

            return null;
        }

        /*
         * Usa a metade inferior das diferenças.
         * Isso evita que uma linha vazia (2x spacing)
         * aumente artificialmente o espaçamento.
         */
        sort($differences);

        $baseCount =
            max(
                2,
                (int) ceil(
                    count($differences) * .65
                )
            );

        $baseDifferences =
            array_slice(
                $differences,
                0,
                $baseCount
            );

        $spacing =
            $median(
                $baseDifferences
            );

        if (
            $spacing < 8
            || $spacing > 600
        ) {
            \Log::warning('Fuel photo content grid failed', [
                'stage' => 'spacing',
                'spacing' => $spacing,
                'differences' => $differences,
                'base_differences' => $baseDifferences,
            ]);

            return null;
        }


        /*
         * Primeiro centro preenchido = primeira linha
         * física da ficha. Em fichas CHM a tabela sempre
         * começa na linha 1; lacunas posteriores são
         * preservadas pelo cálculo abaixo.
         */
        $firstCenter =
            (float) $clusters[0]['center'];

        $mappedRows = [];

        foreach ($clusters as $cluster) {
            $row =
                1
                + (int) round(
                    (
                        $cluster['center']
                        - $firstCenter
                    )
                    / $spacing
                );

            if (
                $row < 1
                || $row > 60
            ) {
                continue;
            }

            $mappedRows[$row] =
                (float) $cluster['center'];
        }

        if (count($mappedRows) < 3) {
            \Log::warning('Fuel photo content grid failed', [
                'stage' => 'mapped_rows',
                'spacing' => $spacing,
                'first_center' => $firstCenter,
                'mapped_rows' => $mappedRows,
            ]);

            return null;
        }


        /*
         * Remove clusters muito distantes após o final
         * da tabela (rodapé, assinatura etc.).
         */
        ksort($mappedRows);

        $cleanRows = [];
        $previousRow = null;

        foreach (
            $mappedRows
            as $row => $center
        ) {
            if (
                $previousRow !== null
                && ($row - $previousRow) > 3
            ) {
                break;
            }

            $cleanRows[$row] =
                $center;

            $previousRow =
                $row;
        }

        if (count($cleanRows) < 3) {
            \Log::warning('Fuel photo content grid failed', [
                'stage' => 'clean_rows',
                'spacing' => $spacing,
                'mapped_rows' => $mappedRows,
                'clean_rows' => $cleanRows,
            ]);

            return null;
        }

        $rowCount =
            (int) max(
                array_keys(
                    $cleanRows
                )
            );


        /*
         * Grade regular final.
         */
        $centers = [];

        for (
            $row = 1;
            $row <= $rowCount;
            $row++
        ) {
            $centers[$row] =
                $firstCenter
                + (
                    ($row - 1)
                    * $spacing
                );
        }

        return [
            'centers' =>
                $centers,

            'spacing' =>
                $spacing,

            'anchors_found' =>
                0,

            'anchors_inlier' =>
                0,

            'row_count' =>
                $rowCount,

            'anchor_rows' =>
                [],

            'inlier_rows' =>
                [],

            'grid_source' =>
                'content_geometry',
        ];
    }


    private function cleanOperationalCode(
        ?string $value,
        int $rowNumber
    ): string {
        $value =
            $this->cleanCellText(
                $value
            );

        /*
         * O Vision às vezes inclui o número impresso
         * da própria linha dentro da coluna código.
         *
         * Ex:
         * "1 16"  => "16"
         * "4 02"  => "02"
         * "10 17" => "17"
         */
        $value = preg_replace(
            '/^\s*'
            .preg_quote(
                (string) $rowNumber,
                '/'
            )
            .'\s*[\.\-_:]?\s+/u',
            '',
            $value
        );

        return trim(
            $value,
            " \t\n\r\0\x0B._-:;"
        );
    }


    private function cleanCellText(
        ?string $value
    ): string {
        $value =
            trim(
                (string) $value
            );

        $value =
            preg_replace(
                '/[_]{2,}/u',
                ' ',
                $value
            );

        $value =
            preg_replace(
                '/\s+/u',
                ' ',
                $value
            );

        return trim($value);
    }


    private function nearestColumn(
        float $x,
        array $centers
    ): string {
        $best = null;
        $distance = INF;

        foreach (
            $centers
            as $column => $center
        ) {
            $candidate =
                abs($x - $center);

            if ($candidate < $distance) {
                $distance = $candidate;
                $best = $column;
            }
        }

        return $best ?: 'code';
    }


    private function matchVehicle(
        ?string $codeRead,
        ?string $plateRead,
        Collection $vehicles
    ): array {
        $warnings = [];

        $code =
            $this->normalizeText(
                $codeRead
            );

        $identifier =
            $this->normalizePlate(
                $plateRead
            );

        $codeVehicle = null;
        $identifierVehicle = null;
        $identifierScore = null;
        $matchType = 'none';

        /*
         * Exemplo operacional:
         * 09 => VCA009
         * 10 => VCA010
         * 16 => VCA016
         */
        if (
            preg_match(
                '/^\d{1,3}$/',
                $code
            )
        ) {
            $target =
                'VCA'
                .str_pad(
                    (string) ((int) $code),
                    3,
                    '0',
                    STR_PAD_LEFT
                );

            $codeVehicle =
                $vehicles->first(
                    fn ($candidate) =>
                        $this->normalizeText(
                            $candidate->asset_code
                        ) === $target
                        || $this->normalizeText(
                            $candidate->name
                        ) === $target
                );

            if ($codeVehicle) {
                $matchType =
                    'operational_code_exact';
            }
        }

        if (
            ! $codeVehicle
            && $code !== ''
        ) {
            $codeVehicle =
                $vehicles->first(
                    function ($candidate) use ($code) {
                        return in_array(
                            $code,
                            [
                                $this->normalizeText(
                                    $candidate->asset_code
                                ),
                                $this->normalizeText(
                                    $candidate->name
                                ),
                            ],
                            true
                        );
                    }
                );

            if ($codeVehicle) {
                $matchType =
                    'code_exact';
            }
        }

        if ($identifier !== '') {
            $identifierVehicle =
                $vehicles->first(
                    function ($candidate) use ($identifier) {
                        return in_array(
                            $identifier,
                            [
                                $this->normalizePlate(
                                    $candidate->plate
                                ),
                                $this->normalizePlate(
                                    $candidate->asset_code
                                ),
                                $this->normalizePlate(
                                    $candidate->name
                                ),
                            ],
                            true
                        );
                    }
                );

            if ($identifierVehicle) {
                $identifierScore = 1.0;
            }
        }

        if (
            ! $identifierVehicle
            && $identifier !== ''
        ) {
            $bestVehicle = null;
            $bestScore = 0.0;

            foreach ($vehicles as $candidate) {
                $candidateIdentifiers =
                    collect([
                        $candidate->plate,
                        $candidate->asset_code,
                        $candidate->name,
                    ])
                    ->filter()
                    ->map(
                        fn ($value) =>
                            $this->normalizePlate(
                                $value
                            )
                    )
                    ->filter()
                    ->unique();

                foreach (
                    $candidateIdentifiers
                    as $candidateIdentifier
                ) {
                    $score =
                        $this->plateSimilarity(
                            $identifier,
                            $candidateIdentifier
                        );

                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestVehicle = $candidate;
                    }
                }
            }

            if (
                $bestVehicle
                && $bestScore >= .72
            ) {
                $identifierVehicle =
                    $bestVehicle;

                $identifierScore =
                    $bestScore;
            }
        }

        if ($codeVehicle) {
            $vehicle =
                $codeVehicle;

            if (
                $identifierVehicle
                && (int) $identifierVehicle->id
                    !== (int) $codeVehicle->id
            ) {
                return [
                    'vehicle' => null,

                    'warnings' => [
                        'Código e placa/identificador apontam para veículos diferentes. Selecione manualmente o veículo correto.',
                    ],

                    'match_type' =>
                        'signal_conflict',

                    'plate_score' =>
                        $identifierScore,

                    'review_level' =>
                        'critical',
                ];
            }

            $vehicleIdentifiers =
                collect([
                    $vehicle->plate,
                    $vehicle->asset_code,
                    $vehicle->name,
                ])
                ->filter()
                ->map(
                    fn ($value) =>
                        $this->normalizePlate(
                            $value
                        )
                )
                ->filter()
                ->unique();

            if (
                $identifier !== ''
                && ! $vehicleIdentifiers
                    ->contains($identifier)
            ) {
                $bestSelectedScore = 0.0;

                foreach (
                    $vehicleIdentifiers
                    as $candidateIdentifier
                ) {
                    $bestSelectedScore = max(
                        $bestSelectedScore,
                        $this->plateSimilarity(
                            $identifier,
                            $candidateIdentifier
                        )
                    );
                }

                $identifierScore =
                    $bestSelectedScore;

                $warnings[] =
                    'Placa/identificador lido diverge do cadastro sugerido pelo código.';
            }

            return [
                'vehicle' =>
                    $vehicle,

                'warnings' =>
                    array_values(
                        array_unique(
                            $warnings
                        )
                    ),

                'match_type' =>
                    $matchType,

                'plate_score' =>
                    $identifierScore,

                'review_level' =>
                    count($warnings)
                        ? 'warning'
                        : 'ok',
            ];
        }

        if ($identifierVehicle) {
            if (
                $identifierScore !== null
                && $identifierScore < .999
            ) {
                $warnings[] =
                    'Veículo associado por aproximação; confirme o cadastro.';
            }

            return [
                'vehicle' =>
                    $identifierVehicle,

                'warnings' =>
                    $warnings,

                'match_type' =>
                    $identifierScore >= .999
                        ? 'identifier_exact'
                        : 'identifier_fuzzy',

                'plate_score' =>
                    $identifierScore,

                'review_level' =>
                    $identifierScore >= .999
                        ? 'ok'
                        : 'warning',
            ];
        }

        return [
            'vehicle' => null,

            'warnings' => [
                'Nenhum veículo cadastrado foi identificado com segurança.',
            ],

            'match_type' =>
                'none',

            'plate_score' =>
                null,

            'review_level' =>
                'critical',
        ];
    }


    private function normalizePlate(
        ?string $value
    ): string {
        if (blank($value)) {
            return '';
        }

        return preg_replace(
            '/[^A-Z0-9]/',
            '',
            strtoupper(
                Str::ascii($value)
            )
        );
    }


    private function plateSimilarity(
        string $read,
        string $candidate
    ): float {
        $read = $this->normalizePlate($read);
        $candidate = $this->normalizePlate($candidate);

        if ($read === '' || $candidate === '') {
            return 0.0;
        }

        if ($read === $candidate) {
            return 1.0;
        }

        /*
         * Para placas de mesmo tamanho, usamos distância ponderada.
         * Confusões visuais comuns recebem penalidade pequena.
         */
        if (
            strlen($read)
            === strlen($candidate)
        ) {
            $cost = 0.0;
            $length = strlen($read);

            for ($i = 0; $i < $length; $i++) {
                $a = $read[$i];
                $b = $candidate[$i];

                if ($a === $b) {
                    continue;
                }

                $cost +=
                    $this->plateCharacterCost(
                        $a,
                        $b
                    );
            }

            return max(
                0,
                1 - ($cost / $length)
            );
        }

        $distance =
            levenshtein(
                $read,
                $candidate
            );

        $maxLength = max(
            strlen($read),
            strlen($candidate)
        );

        return $maxLength > 0
            ? max(
                0,
                1 - ($distance / $maxLength)
            )
            : 0.0;
    }


    private function plateCharacterCost(
        string $a,
        string $b
    ): float {
        $groups = [
            ['I', '1'],
            ['O', '0', 'Q'],
            ['B', '8'],
            ['S', '5'],
            ['Z', '2'],
            ['G', '6'],
        ];

        foreach ($groups as $group) {
            if (
                in_array($a, $group, true)
                && in_array($b, $group, true)
            ) {
                return 0.25;
            }
        }

        return 1.0;
    }


    private function parseHeader(
        string $text
    ): array {
        $date = null;
        $count = null;
        $total = null;
        $operator = null;

        if (
            preg_match(
                '/\b(\d{2}\/\d{2}\/\d{4})\b/',
                $text,
                $match
            )
        ) {
            $date = $match[1];
        }

        if (
            preg_match(
                '/QTD\.\s*ABASTECIMENTOS\s*[\r\n]+\s*(\d+)/iu',
                $text,
                $match
            )
        ) {
            $count = (int) $match[1];
        }

        if (
            preg_match(
                '/TOTAL\s+ABASTECIDO(?:\s*\(LITROS\))?\s*[\r\n]+\s*([\d.,]+)/iu',
                $text,
                $match
            )
        ) {
            $total =
                $this->parseDecimal(
                    $match[1]
                );
        }

        $lines = preg_split(
            '/\R/u',
            $text
        );

        foreach ($lines as $index => $line) {
            if (
                $this->normalizeText($line)
                !== 'OPERADOR'
            ) {
                continue;
            }

            for (
                $i = $index + 1;
                $i < min(
                    count($lines),
                    $index + 4
                );
                $i++
            ) {
                $candidate =
                    trim($lines[$i]);

                if (
                    $candidate === ''
                    || in_array(
                        $this->normalizeText(
                            $candidate
                        ),
                        [
                            'CHM',
                            'PLACA',
                            'LITROS',
                        ],
                        true
                    )
                ) {
                    continue;
                }

                $operator = $candidate;
                break;
            }

            break;
        }

        return [
            'date' => $date,

            'declared_fillings_count' =>
                $count,

            'declared_total_liters' =>
                $total,

            'operator' => $operator,
        ];
    }


    private function parseDecimal(
        ?string $value
    ): ?float {
        if (blank($value)) {
            return null;
        }

        $value = preg_replace(
            '/[^0-9,.]/',
            '',
            $value
        );

        if ($value === '') {
            return null;
        }

        if (
            str_contains($value, ',')
            && str_contains($value, '.')
        ) {
            $value =
                str_replace('.', '', $value);

            $value =
                str_replace(',', '.', $value);
        } elseif (
            str_contains($value, ',')
        ) {
            $value =
                str_replace(',', '.', $value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        return round(
            (float) $value,
            3
        );
    }


    private function parseInteger(
        ?string $value
    ): ?int {
        if (blank($value)) {
            return null;
        }

        $digits =
            preg_replace(
                '/\D/',
                '',
                $value
            );

        return $digits !== ''
            ? (int) $digits
            : null;
    }


    private function vehicleKmStatistics(
        Vehicle $vehicle
    ): array {
        $readings = \App\Models\FuelFilling::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereNull('cancelled_at')
            ->whereNotNull('vehicle_km')
            ->where(function ($query) {
                $query
                    ->whereNull('vehicle_km_status')
                    ->orWhere(
                        'vehicle_km_status',
                        \App\Models\FuelFilling::KM_STATUS_VALID
                    );
            })
            ->orderByDesc('filled_at')
            ->limit(12)
            ->pluck('vehicle_km')
            ->map(fn ($value) => (float) $value)
            ->reverse()
            ->values();

        $distances = collect();

        for (
            $i = 1;
            $i < $readings->count();
            $i++
        ) {
            $distance =
                $readings[$i]
                - $readings[$i - 1];

            if (
                $distance > 0
                && $distance <= 5000
            ) {
                $distances->push(
                    $distance
                );
            }
        }

        if ($distances->isEmpty()) {
            return [
                'samples' => 0,
                'average_km' => null,
                'median_km' => null,
            ];
        }

        $sorted =
            $distances
                ->sort()
                ->values();

        $count =
            $sorted->count();

        if ($count % 2 === 1) {
            $median =
                $sorted[
                    intdiv(
                        $count,
                        2
                    )
                ];
        } else {
            $middle =
                intdiv(
                    $count,
                    2
                );

            $median =
                (
                    $sorted[$middle - 1]
                    + $sorted[$middle]
                ) / 2;
        }

        return [
            'samples' => $count,

            'average_km' =>
                round(
                    $distances->avg(),
                    1
                ),

            'median_km' =>
                round(
                    $median,
                    1
                ),
        ];
    }


    /**
     * Corrige erros típicos do OCR na coluna ARLA.
     *
     * Operacionalmente o abastecimento individual de ARLA
     * normalmente fica abaixo de 100 litros.
     *
     * Exemplos:
     * 304   -> 30.4
     * 2038  -> 20.38
     * 263.8 -> 26.38
     *
     * Esta normalização acontece somente na leitura inicial
     * do OCR. O campo continua livre para edição manual.
     */
    private function normalizeOcrArla(
        ?float $value
    ): ?float {
        if ($value === null) {
            return null;
        }

        if ($value < 0) {
            return $value;
        }

        while ($value >= 100) {
            $value /= 10;
        }

        return round(
            $value,
            2
        );
    }


    private function normalizeText(
        ?string $value
    ): string {
        if (blank($value)) {
            return '';
        }

        return preg_replace(
            '/[^A-Z0-9]/',
            '',
            strtoupper(
                Str::ascii($value)
            )
        );
    }
}
