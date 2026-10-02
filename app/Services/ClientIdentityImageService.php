<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class ClientIdentityImageService
{
    private const TEMP_DIRECTORY =
        'identity-scans/tmp';

    private const CLIENT_DIRECTORY =
        'client-identity';

    public function stage(
        UploadedFile $file
    ): string {
        $this->cleanupOldTemporaryFiles();

        $token =
            (string) Str::uuid();

        $disk =
            Storage::disk('local');

        $disk->makeDirectory(
            self::TEMP_DIRECTORY
        );

        $outputRelative =
            self::TEMP_DIRECTORY
            . '/'
            . $token
            . '.webp';

        $output =
            $disk->path(
                $outputRelative
            );

        $workingDirectory =
            sys_get_temp_dir()
            . '/mikropanel-id-'
            . $token;

        if (
            !is_dir(
                $workingDirectory
            )
            && !mkdir(
                $workingDirectory,
                0700,
                true
            )
            && !is_dir(
                $workingDirectory
            )
        ) {
            throw new RuntimeException(
                'Could not create image working directory.'
            );
        }

        $source =
            $file->getRealPath();

        if (
            !$source
            || !is_file(
                $source
            )
        ) {
            throw new RuntimeException(
                'Uploaded identity image is unavailable.'
            );
        }

        $pdfPreview = null;

        try {
            $extension =
                strtolower(
                    $file->getClientOriginalExtension()
                );

            $mime =
                strtolower(
                    (string) $file->getMimeType()
                );

            if (
                $extension === 'pdf'
                || $mime === 'application/pdf'
            ) {
                if (
                    !is_executable(
                        '/usr/bin/pdftoppm'
                    )
                ) {
                    throw new RuntimeException(
                        'PDF preview tool is unavailable.'
                    );
                }

                $prefix =
                    $workingDirectory
                    . '/page';

                $this->run([
                    '/usr/bin/pdftoppm',
                    '-f',
                    '1',
                    '-singlefile',
                    '-jpeg',
                    '-r',
                    '150',
                    $source,
                    $prefix,
                ], 40);

                $pdfPreview =
                    $prefix
                    . '.jpg';

                if (
                    !is_file(
                        $pdfPreview
                    )
                ) {
                    throw new RuntimeException(
                        'Could not render the identity PDF.'
                    );
                }

                $source =
                    $pdfPreview;
            }

            if (
                !is_executable(
                    '/usr/bin/convert'
                )
            ) {
                throw new RuntimeException(
                    'ImageMagick is unavailable.'
                );
            }

            /*
             * Permanent document copy:
             * - WebP
             * - max 1400 px
             * - metadata removed
             * - moderate quality
             *
             * OCR still uses the original upload.
             */
            $this->run([
                '/usr/bin/convert',
                $source,
                '-auto-orient',
                '-strip',
                '-colorspace',
                'sRGB',
                '-filter',
                'Lanczos',
                '-resize',
                '1400x1400>',
                '-quality',
                '58',
                $output,
            ], 40);

            if (
                !is_file(
                    $output
                )
                || filesize(
                    $output
                ) < 100
            ) {
                throw new RuntimeException(
                    'Compressed identity image was not created.'
                );
            }

            /*
             * Keep unexpectedly complex photos small.
             */
            if (
                filesize(
                    $output
                ) > 450 * 1024
            ) {
                $smaller =
                    $workingDirectory
                    . '/smaller.webp';

                $this->run([
                    '/usr/bin/convert',
                    $output,
                    '-strip',
                    '-resize',
                    '1100x1100>',
                    '-quality',
                    '48',
                    $smaller,
                ], 35);

                if (
                    is_file(
                        $smaller
                    )
                    && filesize(
                        $smaller
                    ) > 100
                ) {
                    if (
                        !copy(
                            $smaller,
                            $output
                        )
                    ) {
                        throw new RuntimeException(
                            'Could not finalize compressed image.'
                        );
                    }
                }
            }

            @chmod(
                $output,
                0640
            );

            /*
             * Extract only the portrait from the
             * compressed document candidate.
             *
             * Failure is intentionally non-fatal:
             * OCR/document storage must still work.
             */
            $faceRelative =
                self::TEMP_DIRECTORY
                . '/'
                . $token
                . '-face.webp';

            $faceOutput =
                $disk->path(
                    $faceRelative
                );

            try {
                $process =
                    new Process([
                        '/usr/bin/python3',
                        base_path(
                            'scripts/identity_face_crop.py'
                        ),
                        $output,
                        $faceOutput,
                    ]);

                /*
                 * FACE_PROCESS_BUDGET_V27
                 *
                 * Face extraction is CPU-bound on the
                 * 1 GB VPS. Five seconds was too short
                 * for real scanned Qatar IDs/passports.
                 */
                $process->setTimeout(
                    15
                );

                $process->run();

                if (
                    !$process->isSuccessful()
                ) {
                    $disk->delete(
                        $faceRelative
                    );
                } elseif (
                    !$disk->exists(
                        $faceRelative
                    )
                    || $disk->size(
                        $faceRelative
                    ) < 100
                ) {
                    $disk->delete(
                        $faceRelative
                    );
                }
            } catch (\Throwable) {
                $disk->delete(
                    $faceRelative
                );
            }

            return $token;
        } catch (\Throwable $exception) {
            $disk->delete(
                $outputRelative
            );

            throw $exception;
        } finally {
            if (
                $pdfPreview
                && is_file(
                    $pdfPreview
                )
            ) {
                @unlink(
                    $pdfPreview
                );
            }

            if (
                is_dir(
                    $workingDirectory
                )
            ) {
                foreach (
                    glob(
                        $workingDirectory
                        . '/*'
                    ) ?: []
                    as $temporary
                ) {
                    if (
                        is_file(
                            $temporary
                        )
                    ) {
                        @unlink(
                            $temporary
                        );
                    }
                }

                @rmdir(
                    $workingDirectory
                );
            }
        }
    }

    public function faceExists(
        string $token
    ): bool {
        if (
            !$this->validToken(
                $token
            )
        ) {
            return false;
        }

        return Storage::disk('local')
            ->exists(
                self::TEMP_DIRECTORY
                . '/'
                . $token
                . '-face.webp'
            );
    }

    public function finalizeTokens(
        Client $client,
        array $tokens
    ): void {
        $disk =
            Storage::disk('local');

        $mapping = [
            'qatar_id_front' => [
                'column' =>
                    'qatar_id_front_image_path',

                'filename' =>
                    'qatar-id-front.webp',
            ],

            'qatar_id_back' => [
                'column' =>
                    'qatar_id_back_image_path',

                'filename' =>
                    'qatar-id-back.webp',
            ],

            'passport' => [
                'column' =>
                    'passport_image_path',

                'filename' =>
                    'passport.webp',
            ],
        ];

        $updates = [];

        $qidStored = false;
        $passportStored = false;

        foreach (
            $mapping
            as $key => $definition
        ) {
            $token =
                trim(
                    (string) (
                        $tokens[
                            $key
                        ]
                        ?? ''
                    )
                );

            if (
                !$this->validToken(
                    $token
                )
            ) {
                continue;
            }

            $source =
                self::TEMP_DIRECTORY
                . '/'
                . $token
                . '.webp';

            if (
                !$disk->exists(
                    $source
                )
            ) {
                continue;
            }

            $directory =
                self::CLIENT_DIRECTORY
                . '/'
                . $client->id;

            $disk->makeDirectory(
                $directory
            );

            $target =
                $directory
                . '/'
                . $definition[
                    'filename'
                ];

            /*
             * One current image per document side.
             * Rescanning replaces the previous copy.
             */
            if (
                $disk->exists(
                    $target
                )
            ) {
                $disk->delete(
                    $target
                );
            }

            if (
                !$disk->move(
                    $source,
                    $target
                )
            ) {
                throw new RuntimeException(
                    'Could not attach identity image to client.'
                );
            }

            $oldPath =
                $client->getAttribute(
                    $definition[
                        'column'
                    ]
                );

            if (
                is_string(
                    $oldPath
                )
                && $oldPath !== ''
                && $oldPath !== $target
            ) {
                $this->deleteClientPath(
                    $client,
                    $oldPath
                );
            }

            $updates[
                $definition[
                    'column'
                ]
            ] = $target;

            /*
             * Qatar ID front and passport bio-page
             * are allowed to become the client face
             * source. Qatar ID back never becomes
             * a profile photo.
             */
            if (
                in_array(
                    $key,
                    [
                        'qatar_id_front',
                        'passport',
                    ],
                    true
                )
            ) {
                $faceSource =
                    self::TEMP_DIRECTORY
                    . '/'
                    . $token
                    . '-face.webp';

                $profileTarget =
                    $directory
                    . '/profile-face.webp';

                $oldProfile =
                    $client->profile_image_path;

                if (
                    is_string(
                        $oldProfile
                    )
                    && $oldProfile !== ''
                ) {
                    $this->deleteClientPath(
                        $client,
                        $oldProfile
                    );
                }

                if (
                    $disk->exists(
                        $profileTarget
                    )
                ) {
                    $disk->delete(
                        $profileTarget
                    );
                }

                if (
                    $disk->exists(
                        $faceSource
                    )
                ) {
                    if (
                        !$disk->move(
                            $faceSource,
                            $profileTarget
                        )
                    ) {
                        throw new RuntimeException(
                            'Could not attach client face photo.'
                        );
                    }

                    $updates[
                        'profile_image_path'
                    ] = $profileTarget;
                } else {
                    /*
                     * Do not keep a stale portrait if a
                     * newly scanned identity document
                     * did not produce a valid face.
                     */
                    $updates[
                        'profile_image_path'
                    ] = null;
                }
            }

            if (
                $key === 'passport'
            ) {
                $passportStored = true;
            } else {
                $qidStored = true;
            }
        }

        /*
         * Switching document type removes stale
         * images from the previous identity type.
         */
        if ($passportStored) {
            foreach (
                [
                    'qatar_id_front_image_path',
                    'qatar_id_back_image_path',
                ]
                as $column
            ) {
                $this->deleteClientPath(
                    $client,
                    $client->getAttribute(
                        $column
                    )
                );

                $updates[
                    $column
                ] = null;
            }
        }

        if ($qidStored) {
            $this->deleteClientPath(
                $client,
                $client->passport_image_path
            );

            $updates[
                'passport_image_path'
            ] = null;
        }

        if ($updates !== []) {
            $client
                ->forceFill(
                    $updates
                )
                ->saveQuietly();
        }
    }

    public function cleanupOldTemporaryFiles(): void
    {
        $disk =
            Storage::disk('local');

        $cutoff =
            time()
            - 24 * 60 * 60;

        foreach (
            $disk->files(
                self::TEMP_DIRECTORY
            )
            as $path
        ) {
            try {
                if (
                    $disk->lastModified(
                        $path
                    ) < $cutoff
                ) {
                    $disk->delete(
                        $path
                    );
                }
            } catch (\Throwable) {
                /*
                 * Cleanup must never block a scan.
                 */
            }
        }
    }

    private function validToken(
        string $token
    ): bool {
        return preg_match(
            '/^[0-9a-f]{8}-'
            . '[0-9a-f]{4}-'
            . '[1-5][0-9a-f]{3}-'
            . '[89ab][0-9a-f]{3}-'
            . '[0-9a-f]{12}$/i',
            $token
        ) === 1;
    }

    private function deleteClientPath(
        Client $client,
        mixed $path
    ): void {
        if (
            !is_string(
                $path
            )
            || $path === ''
        ) {
            return;
        }

        $prefix =
            self::CLIENT_DIRECTORY
            . '/'
            . $client->id
            . '/';

        if (
            !str_starts_with(
                $path,
                $prefix
            )
        ) {
            return;
        }

        Storage::disk('local')
            ->delete(
                $path
            );
    }

    private function run(
        array $command,
        int $timeout
    ): void {
        /*
         * IMAGE_STAGE_PROCESS_CAP_V7
         * FACE_PREVIEW_PROCESS_BUDGET_V25B
         *
         * Preview generation is optional. Never let
         * it hold the OCR response for many seconds.
         */
        $timeout =
            min(
                $timeout,
                8
            );

        $process =
            new Process(
                $command
            );

        $process->setTimeout(
            $timeout
        );

        $process->run();

        if (
            !$process->isSuccessful()
        ) {
            throw new RuntimeException(
                trim(
                    $process->getErrorOutput()
                )
                ?: 'Identity image processing failed.'
            );
        }
    }
}
