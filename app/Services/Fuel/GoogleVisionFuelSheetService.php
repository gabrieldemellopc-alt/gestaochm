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
        $credentials = $this->credentials();
        $token = $this->accessToken($credentials);

        $preparedImage =
            $this->prepareImage(
                $file
            );

        $binary =
            file_get_contents(
                $preparedImage
            );

        if ($binary === false) {
            throw new RuntimeException(
                'Não foi possível ler a imagem enviada.'
            );
        }

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
             * Analisa inicialmente apenas a primeira página.
             * 200 DPI fornece resolução suficiente para OCR
             * sem criar arquivo excessivamente pesado.
             */
            $input =
                $source.'[0]';

            $command = sprintf(
                '/usr/bin/convert '
                .'-density 200 %s '
                .'-background white '
                .'-alpha remove '
                .'-alpha off '
                .'-auto-orient '
                .'-deskew 40%% '
                .'-colorspace sRGB '
                .'-contrast-stretch 0.5%%x0.5%% '
                .'-quality 92 '
                .'%s 2>&1',
                escapeshellarg($input),
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

        exec(
            $command,
            $messages,
            $exitCode
        );

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
                || $nearestDistance
                    > $spacing * .48
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

            if (
                ! isset($anchors[$number])
                || $word['y']
                    < $anchors[$number]
            ) {
                $anchors[$number] =
                    (float) $word['y'];
            }
        }

        /*
         * Três linhas numeradas já são suficientes para
         * estimar com segurança o espaçamento da tabela.
         *
         * Isso é importante para fichas curtas, como a
         * ficha de Barreiras com 8 linhas.
         */
        if (count($anchors) < 3) {
            return null;
        }

        ksort($anchors);

        $rowCount =
            (int) max(
                array_keys($anchors)
            );

        if (
            $rowCount < 1
            || $rowCount > 60
        ) {
            return null;
        }

        $n = count($anchors);

        $sumX = 0.0;
        $sumY = 0.0;
        $sumXY = 0.0;
        $sumXX = 0.0;

        foreach (
            $anchors
            as $row => $y
        ) {
            $x = (float) $row;

            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }

        $denominator =
            ($n * $sumXX)
            - ($sumX * $sumX);

        if (abs($denominator) < .0001) {
            return null;
        }

        $slope =
            (
                ($n * $sumXY)
                - ($sumX * $sumY)
            )
            / $denominator;

        $intercept =
            (
                $sumY
                - ($slope * $sumX)
            )
            / $n;

        if (
            $slope < 8
            || $slope > 150
        ) {
            return null;
        }

        $centers = [];

        for ($row = 1; $row <= $rowCount; $row++) {
            $centers[$row] =
                $intercept
                + ($slope * $row);
        }

        return [
            'centers' =>
                $centers,

            'spacing' =>
                abs($slope),

            'anchors_found' =>
                count($anchors),

            'row_count' =>
                $rowCount,

            'anchor_rows' =>
                array_keys($anchors),
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
