<?php

namespace App\Jobs;

use App\Models\ConfessionComment;
use App\Models\ChatMessage;
use App\Services\AudioProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessVoiceEffect implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $modelId,
        public string $audioPath,
        public string $voiceType,
        public string $modelClass = \App\Models\ConfessionComment::class
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AudioProcessingService $audioService): void
    {
        try {
            Log::info('Starting voice effect processing', [
                'model_id' => $this->modelId,
                'model_class' => $this->modelClass,
                'voice_type' => $this->voiceType,
                'audio_path' => $this->audioPath
            ]);

            // Appliquer l'effet vocal
            $processedPath = $audioService->applyVoiceEffect($this->audioPath, $this->voiceType);

            // Mettre à jour le modèle avec le nouveau chemin
            $model = $this->modelClass::find($this->modelId);

            if ($model) {
                $model->update([
                    'media_url' => $processedPath
                ]);

                Log::info('Voice effect processing completed', [
                    'model_id' => $this->modelId,
                    'model_class' => $this->modelClass,
                    'processed_path' => $processedPath
                ]);
            } else {
                Log::warning('Model not found during voice processing', [
                    'model_id' => $this->modelId,
                    'model_class' => $this->modelClass
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Voice effect processing failed', [
                'model_id' => $this->modelId,
                'model_class' => $this->modelClass,
                'voice_type' => $this->voiceType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Ne pas relancer le job en cas d'erreur
            // Le fichier original sera utilisé
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Voice effect job failed permanently', [
            'model_id' => $this->modelId,
            'model_class' => $this->modelClass,
            'voice_type' => $this->voiceType,
            'error' => $exception->getMessage()
        ]);
    }
}
