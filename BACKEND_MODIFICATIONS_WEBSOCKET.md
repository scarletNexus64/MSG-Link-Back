# Modifications Backend - Système de Notification WebSocket

## 📋 Vue d'ensemble

Ces modifications permettent de broadcaster les messages de chat sur **deux canaux WebSocket** :
1. **Canal de conversation** (`private-conversation.{conversationId}`) : Pour les utilisateurs dans la conversation
2. **Canal global utilisateur** (`private-user.{userId}`) : Pour le badge count et les notifications globales

---

## 📝 Fichiers Modifiés

### 1. `app/Events/ChatMessageSent.php`

#### Changements

**Avant :**
```php
public ChatMessage $message;

public function __construct(ChatMessage $message)
{
    $this->message = $message;
}

public function broadcastOn(): array
{
    return [
        new PrivateChannel('conversation.' . $this->message->conversation_id),
    ];
}
```

**Après :**
```php
public ChatMessage $message;
public int $receiverId;  // ← NOUVEAU

public function __construct(ChatMessage $message, int $receiverId)  // ← NOUVEAU paramètre
{
    $this->message = $message;
    $this->receiverId = $receiverId;  // ← NOUVEAU

    \Log::info('🔊 [EVENT] ChatMessageSent créé', [
        'message_id' => $message->id,
        'conversation_id' => $message->conversation_id,
        'sender_id' => $message->sender_id,
        'receiver_id' => $receiverId,  // ← NOUVEAU
        'type' => $message->type,
    ]);
}

public function broadcastOn(): array
{
    return [
        // Canal de la conversation spécifique (pour les utilisateurs dans la conversation)
        new PrivateChannel('conversation.' . $this->message->conversation_id),

        // ✨ NOUVEAU: Canal global du destinataire (pour le badge count et notifications globales)
        new PrivateChannel('user.' . $this->receiverId),
    ];
}
```

#### Impact
- L'événement broadcaste maintenant sur **2 canaux** au lieu d'un seul
- Le `receiverId` est maintenant **requis** lors de la création de l'événement
- Les logs incluent maintenant le `receiver_id` pour faciliter le débogage

---

### 2. `app/Http/Controllers/Api/V1/ChatController.php`

#### Changements

**Ligne 280-300** (méthode `sendMessage`)

**Avant :**
```php
try {
    \Log::info('📤 [CHAT] Broadcasting ChatMessageSent', [
        'message_id' => $message->id,
        'conversation_id' => $conversation->id,
        'sender_id' => $user->id,
        'channel' => 'conversation.' . $conversation->id,
    ]);

    broadcast(new ChatMessageSent($message))->toOthers();

    \Log::info('✅ [CHAT] ChatMessageSent broadcasted successfully');
}
```

**Après :**
```php
try {
    \Log::info('📤 [CHAT] Broadcasting ChatMessageSent', [
        'message_id' => $message->id,
        'conversation_id' => $conversation->id,
        'sender_id' => $user->id,
        'receiver_id' => $otherUser->id,  // ← NOUVEAU
        'channels' => [  // ← NOUVEAU
            'conversation.' . $conversation->id,
            'user.' . $otherUser->id,
        ],
    ]);

    broadcast(new ChatMessageSent($message, $otherUser->id))->toOthers();  // ← NOUVEAU paramètre

    \Log::info('✅ [CHAT] ChatMessageSent broadcasted successfully');
}
```

#### Impact
- Passe maintenant le `receiverId` à l'événement
- Les logs montrent clairement les deux canaux utilisés
- Facilite le débogage en cas de problème

---

### 3. `app/Http/Controllers/Api/V1/GiftController.php`

#### Changements

**Lignes 248 et 395** (méthodes `send` et `sendInConversation`)

**Avant :**
```php
// Diffuser le message dans la conversation via WebSocket
$chatMessage->load(['sender', 'giftTransaction.gift']);
event(new ChatMessageSent($chatMessage));
```

**Après :**
```php
// Diffuser le message dans la conversation via WebSocket
$chatMessage->load(['sender', 'giftTransaction.gift']);
event(new ChatMessageSent($chatMessage, $recipient->id));  // ← NOUVEAU paramètre
```

#### Impact
- Les messages de cadeaux sont maintenant également broadcastés sur le canal global utilisateur
- Le destinataire recevra les notifications de cadeaux même s'il n'est pas dans la conversation

---

### 4. `routes/channels.php`

#### Vérification

Le canal global utilisateur existe **déjà** dans le fichier :

```php
/**
 * Canal privé pour les notifications utilisateur
 */
Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});
```

#### Impact
- ✅ Aucune modification nécessaire
- Le canal `private-user.{userId}` est déjà autorisé
- Un utilisateur peut seulement s'abonner à son propre canal

---

## 🔍 Détails Techniques

### Architecture du système

```
┌─────────────────────────────────────────────────────────────┐
│                    User A envoie un message                 │
└─────────────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────┐
│  ChatController::sendMessage()                              │
│    - Crée le message dans la DB                            │
│    - Détermine le receiverId ($otherUser->id)              │
│    - Dispatch ChatMessageSent($message, $receiverId)        │
└─────────────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────┐
│  Event: ChatMessageSent                                     │
│    - Broadcaste sur 2 canaux:                              │
│      1. private-conversation.{conversationId}               │
│      2. private-user.{receiverId}                           │
└─────────────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────┐
│                    Reverb/Pusher                            │
│    - Distribue l'événement aux clients connectés           │
└─────────────────────────────────────────────────────────────┘
                         ↓
┌─────────────────────────────────────────────────────────────┐
│                Frontend Flutter (User B)                     │
│                                                             │
│  ┌───────────────────────────────────────────────────┐    │
│  │ RealtimeService                                    │    │
│  │  - Reçoit l'événement sur les 2 canaux            │    │
│  └───────────────────────────────────────────────────┘    │
│                         ↓                                   │
│  ┌───────────────────────────────────────────────────┐    │
│  │ Si dans conversation:                              │    │
│  │  - ChatDetailController affiche le message        │    │
│  │  - Pas d'incrémentation du badge                  │    │
│  └───────────────────────────────────────────────────┘    │
│                         ↓                                   │
│  ┌───────────────────────────────────────────────────┐    │
│  │ Si ailleurs dans l'app:                            │    │
│  │  - ConversationStateService incrémente le badge   │    │
│  │  - Met à jour la liste des conversations          │    │
│  └───────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

---

## 🧪 Tests à Effectuer

### Test 1: Broadcasting sur les deux canaux

1. **Envoyer un message** de User A à User B
2. **Vérifier les logs Laravel** :
   ```
   🔊 [EVENT] ChatMessageSent créé
   📤 [CHAT] Broadcasting ChatMessageSent
      - receiver_id: {user_b_id}
      - channels: [
          "conversation.{conversation_id}",
          "user.{user_b_id}"
        ]
   ✅ [CHAT] ChatMessageSent broadcasted successfully
   ```

### Test 2: Réception sur le canal global

**Côté Flutter**, User B devrait voir dans les logs :
```
📨 ÉVÉNEMENT CANAL GLOBAL
🎯 Event: message.sent
💬 Message pour la conversation: {conversation_id}
📊 Badge counts recalculés:
   - Total unread messages: 1
   - Unread conversations: 1
```

### Test 3: Badge count en temps réel

1. User B est dans **Feeds** (pas dans Chat)
2. User A envoie **3 messages** à User B
3. User B devrait voir le badge count s'incrémenter : **1 → 2 → 3**

### Test 4: Pas d'incrémentation si conversation ouverte

1. User B ouvre la conversation avec User A
2. User A envoie un message
3. Le badge count **ne doit PAS** s'incrémenter (reste à 0)

---

## 📊 Résumé des Modifications

| Fichier | Modifications | Raison |
|---------|--------------|--------|
| `ChatMessageSent.php` | Ajout du paramètre `receiverId` + broadcast sur 2 canaux | Permettre le broadcast sur le canal global utilisateur |
| `ChatController.php` | Passer le `receiverId` lors du broadcast | Fournir l'ID du destinataire à l'événement |
| `GiftController.php` | Passer le `receiverId` lors du broadcast | Fournir l'ID du destinataire pour les cadeaux |
| `channels.php` | ✅ Aucune (déjà existant) | Le canal `user.{userId}` existe déjà |

---

## 🚀 Déploiement

### Étapes de déploiement

1. **Tester en local** :
   ```bash
   # Backend
   php artisan reverb:start

   # Frontend Flutter
   flutter run
   ```

2. **Vérifier les logs** :
   - Côté Laravel : Vérifier que les 2 canaux sont bien utilisés
   - Côté Flutter : Vérifier que les événements sont bien reçus

3. **Tester les scénarios** :
   - Badge count s'incrémente
   - Fonctionne même si User B est ailleurs
   - Pas d'incrémentation si conversation ouverte

4. **Déployer en production** :
   ```bash
   git add .
   git commit -m "feat: broadcast messages sur canal global utilisateur pour badge count"
   git push
   ```

---

## 🔧 Debugging

### Logs à surveiller

**Backend Laravel :**
```php
🔊 [EVENT] ChatMessageSent créé
📤 [CHAT] Broadcasting ChatMessageSent
✅ [CHAT] ChatMessageSent broadcasted successfully
```

**Frontend Flutter :**
```dart
📨 ÉVÉNEMENT CANAL GLOBAL
💬 Message pour la conversation: X
📊 Badge counts recalculés: Total unread messages: Y
```

### Problèmes courants

| Problème | Cause | Solution |
|----------|-------|----------|
| Badge count ne s'incrémente pas | Canal global non souscrit | Vérifier que `ConversationStateService` s'abonne au canal `private-user.{userId}` |
| Événement non reçu | Reverb/Pusher non démarré | Lancer `php artisan reverb:start` |
| Erreur d'authentification canal | `channels.php` mal configuré | Vérifier que le canal `user.{userId}` autorise l'utilisateur |
| Doublons de messages | Abonnement multiple | Vérifier qu'on n'est pas abonné 2 fois au même canal |

---

## 📚 Références

- Documentation Laravel Broadcasting: https://laravel.com/docs/broadcasting
- Documentation Reverb: https://laravel.com/docs/reverb
- Documentation Pusher: https://pusher.com/docs

---

**Date**: 2026-03-11
**Version**: 1.0
**Auteur**: Claude Code
