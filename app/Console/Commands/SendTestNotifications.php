<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendTestNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:test {user_id} {--count=6}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envoie des notifications de test à un utilisateur pour tester le groupement FCM';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService)
    {
        $userId = $this->argument('user_id');
        $count = $this->option('count');

        $user = User::find($userId);

        if (!$user) {
            $this->error("Utilisateur {$userId} introuvable.");
            return 1;
        }

        if (!$user->fcm_token) {
            $this->error("L'utilisateur {$userId} n'a pas de FCM token.");
            return 1;
        }

        $this->info("Envoi de {$count} notifications de test à {$user->first_name} {$user->last_name} ({$user->id})...");
        $this->info("FCM Token: " . substr($user->fcm_token, 0, 30) . "...");

        $notificationTypes = [
            [
                'title' => 'Nouveau message anonyme',
                'body' => "Quelqu'un vous a envoyé un message anonyme.",
                'type' => 'new_message',
            ],
            [
                'title' => 'Nouvelle confession',
                'body' => "Quelqu'un vous a fait une confession.",
                'type' => 'new_confession',
            ],
            [
                'title' => 'Nouveau message',
                'body' => "Quelqu'un vous a envoyé un message.",
                'type' => 'new_chat_message',
            ],
            [
                'title' => 'Admirateur secret',
                'body' => "Quelqu'un a consulté votre profil.",
                'type' => 'profile_view',
            ],
            [
                'title' => 'Cadeau reçu !',
                'body' => "Vous avez reçu un cadeau mystérieux !",
                'type' => 'gift_received',
            ],
            [
                'title' => 'Réponse à votre story',
                'body' => "Quelqu'un a répondu à votre story !",
                'type' => 'story_reply',
            ],
        ];

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 0; $i < $count; $i++) {
            $notification = $notificationTypes[$i % count($notificationTypes)];

            $success = $notificationService->sendPushNotification(
                $user,
                $notification['title'],
                $notification['body'],
                [
                    'type' => $notification['type'],
                    'test' => true,
                    'timestamp' => now()->timestamp,
                    'index' => $i + 1,
                ]
            );

            if ($success) {
                $bar->advance();
                // Petit délai entre les notifications pour qu'elles soient bien groupées
                usleep(500000); // 0.5 seconde
            } else {
                $this->newLine();
                $this->error("Erreur lors de l'envoi de la notification #" . ($i + 1));
                return 1;
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ {$count} notifications envoyées avec succès !");
        $this->info("Vérifiez maintenant l'appareil de l'utilisateur pour voir le groupement des notifications.");

        return 0;
    }
}
