<?php

namespace App\Services;

use FFMpeg\FFMpeg;
use FFMpeg\Format\Audio\Aac;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class AudioProcessingService
{
    protected $ffmpeg;

    public function __construct()
    {
        try {
            $this->ffmpeg = FFMpeg::create([
                'ffmpeg.binaries'  => env('FFMPEG_BINARY', '/usr/bin/ffmpeg'),
                'ffprobe.binaries' => env('FFPROBE_BINARY', '/usr/bin/ffprobe'),
                'timeout'          => 3600,
                'ffmpeg.threads'   => 12,
            ]);
        } catch (\Exception $e) {
            Log::error('FFmpeg initialization failed: ' . $e->getMessage());
            throw new \Exception('FFmpeg n\'est pas disponible sur ce serveur.');
        }
    }

    /**
     * Applique un effet vocal sur un fichier audio
     *
     * @param string $inputPath Chemin du fichier audio d'entrée (relatif à storage/app/public)
     * @param string $voiceType Type d'effet vocal (normal, robot, alien, mystery, chipmunk)
     * @return string Chemin du fichier audio traité (relatif à storage/app/public)
     */
    public function applyVoiceEffect(string $inputPath, string $voiceType): string
    {
        // Si c'est une voix normale, pas de traitement
        if ($voiceType === 'normal') {
            return $inputPath;
        }

        // Chemin absolu du fichier d'entrée
        $fullInputPath = Storage::disk('public')->path($inputPath);

        if (!file_exists($fullInputPath)) {
            throw new \Exception("Le fichier audio n'existe pas: {$fullInputPath}");
        }

        // Générer le nom du fichier de sortie
        $pathInfo = pathinfo($inputPath);
        $outputFilename = $pathInfo['filename'] . '_' . $voiceType . '.' . $pathInfo['extension'];
        $outputPath = $pathInfo['dirname'] . '/' . $outputFilename;
        $fullOutputPath = Storage::disk('public')->path($outputPath);

        try {
            // Définir les filtres audio selon le type de voix
            $audioFilters = $this->getAudioFilters($voiceType);

            // Exécuter la commande FFmpeg directement
            $command = sprintf(
                '%s -i %s -af %s -c:a aac -b:a 128k -y %s 2>&1',
                env('FFMPEG_BINARY', '/usr/bin/ffmpeg'),
                escapeshellarg($fullInputPath),
                escapeshellarg($audioFilters),
                escapeshellarg($fullOutputPath)
            );

            Log::info('Executing FFmpeg command', ['command' => $command]);

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                Log::error('FFmpeg command failed', [
                    'return_code' => $returnCode,
                    'output' => implode("\n", $output),
                    'command' => $command
                ]);
                throw new \Exception('Échec du traitement audio FFmpeg');
            }

            if (!file_exists($fullOutputPath)) {
                throw new \Exception('Le fichier audio traité n\'a pas été créé');
            }

            // Supprimer le fichier original
            if (file_exists($fullInputPath)) {
                unlink($fullInputPath);
            }

            Log::info('Audio processing successful', [
                'voice_type' => $voiceType,
                'output_path' => $outputPath
            ]);

            return $outputPath;

        } catch (\Exception $e) {
            Log::error('Audio processing error', [
                'voice_type' => $voiceType,
                'input_path' => $inputPath,
                'error' => $e->getMessage()
            ]);

            // En cas d'erreur, retourner le fichier original
            return $inputPath;
        }
    }

    /**
     * Retourne les filtres audio FFmpeg selon le type de voix
     *
     * @param string $voiceType
     * @return string
     */
    protected function getAudioFilters(string $voiceType): string
    {
        return match($voiceType) {
            // Robot: Voix robotique avec vibrato et chorus pour effet métallique
            // asetrate ralentit de 10%, donc atempo doit compenser exactement
            'robot' => 'asetrate=44100*0.9,aresample=44100,atempo=1.111111,vibrato=f=10:d=0.5,chorus=0.5:0.9:50:0.4:0.25:2',
            // Alien: Voix extraterrestre avec pitch descendant et écho
            'alien' => 'asetrate=44100*0.8,aresample=44100,atempo=1.25,aecho=0.5:0.7:20:0.5',
            // Mystery: Voix grave et mystérieuse
            'mystery' => 'asetrate=44100*0.75,aresample=44100,atempo=1.333333',
            // Chipmunk: Voix aiguë comme un écureuil
            'chipmunk' => 'asetrate=44100*1.5,aresample=44100,atempo=0.666667',
            default => '',
        };
    }

    /**
     * Vérifie si FFmpeg est disponible
     *
     * @return bool
     */
    public function isFFmpegAvailable(): bool
    {
        $command = sprintf('%s -version 2>&1', env('FFMPEG_BINARY', '/usr/bin/ffmpeg'));
        exec($command, $output, $returnCode);

        return $returnCode === 0;
    }
}
