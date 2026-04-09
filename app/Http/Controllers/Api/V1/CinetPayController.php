<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Models\User;
use Exception;

class CinetPayController extends Controller
{
    protected $apiKey;
    protected $siteId;
    protected $secretKey;
    protected $notifyUrl;
    protected $returnUrl;

    public function __construct()
    {
        $this->apiKey = Setting::get('cinetpay_api_key', config('cinetpay.api_key'));
        $this->siteId = Setting::get('cinetpay_site_id', config('cinetpay.site_id'));
        $this->secretKey = Setting::get('cinetpay_secret_key', config('cinetpay.secret_key'));
        $this->notifyUrl = Setting::get('cinetpay_notify_url', config('cinetpay.notify_url'));
        $this->returnUrl = config('app.frontend_url', 'http://192.168.1.247:3000') . '/payment/callback';
    }

    /**
     * Initier un paiement pour un dépôt de fonds dans le wallet
     */
    public function initiateDepositPayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
            'phone_number' => 'nullable|string',
        ]);

        $user = $request->user();
        $transactionId = 'DEPOSIT' . time() . random_int(1000, 9999);
        $phoneNumber = $request->phone_number ?? $user->phone ?? '+237600000000';

        // Préparer les données pour CinetPay
        $paymentData = [
            'apikey' => $this->apiKey,
            'site_id' => $this->siteId,
            'transaction_id' => $transactionId,
            'amount' => (int) $request->amount,
            'currency' => 'XAF',
            'description' => 'Depot de fonds wallet Weylo',
            'channels' => 'ALL',
            'notify_url' => $this->notifyUrl,
            'return_url' => $this->returnUrl,
            'customer_id' => (string) $user->id,
            'customer_name' => $user->name ?? 'User',
            'customer_surname' => $user->username ?? 'MSG',
            'customer_email' => $user->email,
            'customer_phone_number' => $phoneNumber,
            'customer_address' => 'Douala',
            'customer_city' => 'Douala',
            'customer_country' => 'CM',
            'customer_state' => 'CM',
            'customer_zip_code' => '00237',
            'metadata' => 'user_' . $user->id,
            'lang' => 'fr'
        ];

        Log::info('CinetPay Request Data', $paymentData);

        try {
            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'Weylo-App/1.0'
            ])->post('https://api-checkout.cinetpay.com/v2/payment', $paymentData);

            Log::info('CinetPay Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de connexion à CinetPay'
                ], 500);
            }

            $responseData = $response->json();

            if (!isset($responseData['code']) || $responseData['code'] !== '201') {
                return response()->json([
                    'success' => false,
                    'message' => $responseData['message'] ?? 'Erreur lors de l\'initialisation du paiement',
                    'debug' => $responseData
                ], 400);
            }

            // Créer une transaction de dépôt en attente
            $transaction = $user->transactions()->create([
                'type' => 'deposit',
                'amount' => $request->amount,
                'description' => 'Dépôt de fonds dans le wallet',
                'status' => 'pending',
                'meta' => json_encode([
                    'transaction_id' => $transactionId,
                    'payment_method' => 'cinetpay',
                    'payment_token' => $responseData['data']['payment_token'] ?? null,
                    'cinetpay_response' => $responseData['data'] ?? null,
                    'created_at' => now(),
                ])
            ]);

            Log::info('✓ Transaction créée avec succès', [
                'db_id' => $transaction->id,
                'transaction_id' => $transactionId,
                'status' => $transaction->status
            ]);

            return response()->json([
                'success' => true,
                'transaction_id' => $transactionId,
                'payment_url' => $responseData['data']['payment_url'] ?? null,
                'payment_token' => $responseData['data']['payment_token'] ?? null
            ]);

        } catch (Exception $e) {
            Log::error('CinetPay Exception', [
                'message' => $e->getMessage(),
                'amount' => $request->amount,
                'phone' => $phoneNumber
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur technique: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Callback de notification de CinetPay avec vérification HMAC
     */
    public function handleNotification(Request $request)
    {
        Log::info('=== NOTIFICATION CINETPAY REÇUE ===', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
            'method' => $request->method()
        ]);

        // Récupérer le token HMAC depuis l'entête
        $receivedToken = $request->header('x-token');

        if (!$receivedToken) {
            Log::error('Token HMAC manquant dans l\'entête');
            return response()->json(['status' => 'error', 'message' => 'Token manquant'], 401);
        }

        // Récupérer les données de la notification
        $cpm_site_id = $request->input('cpm_site_id');
        $cpm_trans_id = $request->input('cpm_trans_id');
        $cpm_trans_date = $request->input('cpm_trans_date');
        $cpm_amount = $request->input('cpm_amount');
        $cpm_currency = $request->input('cpm_currency', '');
        $signature = $request->input('signature', '');
        $payment_method = $request->input('payment_method', '');
        $cel_phone_num = $request->input('cel_phone_num', '');
        $cpm_phone_prefixe = $request->input('cpm_phone_prefixe', '');
        $cpm_language = $request->input('cpm_language', '');
        $cpm_version = $request->input('cpm_version', '');
        $cpm_payment_config = $request->input('cpm_payment_config', '');
        $cpm_page_action = $request->input('cpm_page_action', '');
        $cpm_custom = $request->input('cpm_custom', '');
        $cpm_designation = $request->input('cpm_designation', '');
        $cpm_error_message = $request->input('cpm_error_message', '');

        // Vérifier les données obligatoires
        if (!$cpm_trans_id || !$cpm_site_id) {
            Log::error('Données obligatoires manquantes', [
                'trans_id' => $cpm_trans_id,
                'site_id' => $cpm_site_id
            ]);
            return response()->json(['status' => 'error'], 400);
        }

        // Construire la chaîne pour le token HMAC
        $data = $cpm_site_id . $cpm_trans_id . $cpm_trans_date . $cpm_amount . $cpm_currency .
                $signature . $payment_method . $cel_phone_num . $cpm_phone_prefixe .
                $cpm_language . $cpm_version . $cpm_payment_config . $cpm_page_action .
                $cpm_custom . $cpm_designation . $cpm_error_message;

        if (!empty($this->secretKey)) {
            // Générer le token HMAC avec SHA256
            $generatedToken = hash_hmac('SHA256', $data, $this->secretKey);

            Log::info('Vérification du token HMAC', [
                'received_token' => substr($receivedToken, 0, 20) . '...',
                'generated_token' => substr($generatedToken, 0, 20) . '...',
                'tokens_match' => hash_equals($receivedToken, $generatedToken)
            ]);

            // Vérifier que les tokens correspondent
            if (!hash_equals($receivedToken, $generatedToken)) {
                Log::error('Token HMAC invalide - Notification rejetée');
                return response()->json(['status' => 'error', 'message' => 'Token invalide'], 401);
            }
        }

        Log::info('Token HMAC valide - Traitement de la notification');

        // Vérifier que la transaction existe dans notre base
        $transaction = Transaction::whereJsonContains('meta->transaction_id', $cpm_trans_id)->first();

        if (!$transaction) {
            Log::error('Transaction non trouvée dans la base', ['transaction_id' => $cpm_trans_id]);
            return response()->json(['status' => 'error'], 404);
        }

        Log::info('Transaction trouvée, vérification du statut avec l\'API', [
            'transaction_id' => $cpm_trans_id,
            'current_status' => $transaction->status
        ]);

        // Vérifier le statut avec l'API CinetPay
        $verificationResult = $this->verifyTransaction($cpm_trans_id);

        if ($verificationResult['success']) {
            $status = $verificationResult['data']['status'];

            Log::info('Statut CinetPay obtenu', [
                'transaction_id' => $cpm_trans_id,
                'cinetpay_status' => $status,
                'current_db_status' => $transaction->status
            ]);

            if ($status === 'ACCEPTED' && $transaction->status !== 'completed') {
                $transaction->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'meta' => json_encode(array_merge(
                        json_decode($transaction->meta, true),
                        [
                            'cinetpay_verification' => $verificationResult['data'],
                            'notification_data' => [
                                'payment_method' => $payment_method,
                                'phone' => $cel_phone_num,
                                'error_message' => $cpm_error_message,
                                'verified_at' => now()
                            ]
                        ]
                    ))
                ]);

                $this->processDeposit($transaction);

                Log::info('✓ Transaction marquée comme COMPLÉTÉE', [
                    'transaction_id' => $cpm_trans_id,
                    'amount' => $cpm_amount
                ]);

            } elseif ($status === 'REFUSED' && $transaction->status !== 'failed') {
                $transaction->update([
                    'status' => 'failed',
                    'meta' => json_encode(array_merge(
                        json_decode($transaction->meta, true),
                        [
                            'cinetpay_verification' => $verificationResult['data'],
                            'error_message' => $cpm_error_message
                        ]
                    ))
                ]);

                Log::info('✗ Transaction marquée comme ÉCHOUÉE', [
                    'transaction_id' => $cpm_trans_id,
                    'error' => $cpm_error_message
                ]);

            } elseif (in_array($status, ['CANCELLED', 'CANCELED']) && $transaction->status !== 'cancelled') {
                $transaction->update([
                    'status' => 'cancelled',
                    'meta' => json_encode(array_merge(
                        json_decode($transaction->meta, true),
                        [
                            'cinetpay_verification' => $verificationResult['data'],
                            'error_message' => $cpm_error_message
                        ]
                    ))
                ]);

                Log::info('✗ Transaction marquée comme ANNULÉE', ['transaction_id' => $cpm_trans_id]);
            }
        } else {
            Log::error('Échec de la vérification avec l\'API CinetPay', [
                'transaction_id' => $cpm_trans_id,
                'error' => $verificationResult
            ]);
        }

        // Toujours retourner success pour que CinetPay arrête de réessayer
        return response()->json(['status' => 'success']);
    }

    /**
     * Vérifier le statut d'une transaction avec l'API CinetPay
     */
    public function verifyTransaction($transactionId)
    {
        Log::info('>>> Appel API CinetPay payment/check', [
            'transaction_id' => $transactionId
        ]);

        try {
            $requestData = [
                'apikey' => $this->apiKey,
                'site_id' => $this->siteId,
                'transaction_id' => $transactionId
            ];

            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'Weylo-App/1.0'
            ])->post('https://api-checkout.cinetpay.com/v2/payment/check', $requestData);

            $responseData = $response->json();

            Log::info('Réponse CinetPay reçue', [
                'status_code' => $response->status(),
                'response_code' => $responseData['code'] ?? null,
                'response_message' => $responseData['message'] ?? null,
                'full_response' => $responseData
            ]);

            if ($response->successful() && isset($responseData['code']) && $responseData['code'] === '00') {
                Log::info('✓ Vérification CinetPay réussie', [
                    'transaction_id' => $transactionId,
                    'status' => $responseData['data']['status'] ?? 'UNKNOWN'
                ]);

                return [
                    'success' => true,
                    'data' => $responseData['data']
                ];
            }

            return [
                'success' => false,
                'error' => $responseData['message'] ?? 'Vérification échouée',
                'code' => $responseData['code'] ?? null
            ];

        } catch (Exception $e) {
            Log::error('Exception lors de la vérification CinetPay', [
                'transaction_id' => $transactionId,
                'error_message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Traiter le dépôt de fonds
     */
    private function processDeposit($transaction)
    {
        if ($transaction->type !== 'deposit') {
            return;
        }

        $user = $transaction->user;
        $balanceBefore = $user->wallet_balance;

        // Incrémenter le solde de l'utilisateur (utilise wallet_balance)
        $user->increment('wallet_balance', $transaction->amount);

        $balanceAfter = $user->fresh()->wallet_balance;

        // Créer une entrée dans wallet_transactions
        \App\Models\WalletTransaction::create([
            'user_id' => $user->id,
            'type' => \App\Models\WalletTransaction::TYPE_CREDIT,
            'amount' => $transaction->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => 'Dépôt via CinetPay',
            'reference' => json_decode($transaction->meta, true)['transaction_id'] ?? null,
            'transactionable_type' => Transaction::class,
            'transactionable_id' => $transaction->id,
        ]);

        Log::info('Deposit Processed', [
            'user_id' => $user->id,
            'amount' => $transaction->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter
        ]);
    }

    /**
     * Endpoint de retour pour CinetPay (return_url)
     */
    public function handleReturn(Request $request)
    {
        Log::info('CinetPay Return', $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Retour de CinetPay reçu',
            'data' => $request->all()
        ]);
    }

    /**
     * API pour vérifier le statut d'une transaction spécifique
     */
    public function checkTransactionStatus(Request $request)
    {
        Log::info('=== VÉRIFICATION STATUT TRANSACTION ===', [
            'request_data' => $request->all()
        ]);

        $request->validate([
            'transaction_id' => 'required|string'
        ]);

        $transactionId = $request->transaction_id;

        // Chercher la transaction dans la base
        $transaction = Transaction::whereJsonContains('meta->transaction_id', $transactionId)->first();

        if (!$transaction) {
            // Méthode alternative: where avec LIKE
            $transaction = Transaction::where('meta', 'LIKE', "%{$transactionId}%")
                ->where('type', 'deposit')
                ->first();
        }

        if (!$transaction) {
            Log::error('Transaction non trouvée', ['transaction_id' => $transactionId]);

            return response()->json([
                'success' => false,
                'message' => 'Transaction non trouvée'
            ], 404);
        }

        Log::info('Transaction trouvée', [
            'transaction_id' => $transactionId,
            'current_status' => $transaction->status,
            'amount' => $transaction->amount
        ]);

        // Vérifier le statut avec CinetPay
        $verificationResult = $this->verifyTransaction($transactionId);

        if ($verificationResult['success']) {
            $cinetpayStatus = $verificationResult['data']['status'];
            $currentStatus = $transaction->status;

            Log::info('Comparaison des statuts', [
                'cinetpay_status' => $cinetpayStatus,
                'current_db_status' => $currentStatus
            ]);

            // Mettre à jour le statut si nécessaire
            if ($cinetpayStatus === 'ACCEPTED' && $currentStatus !== 'completed') {
                $transaction->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'meta' => json_encode(array_merge(
                        json_decode($transaction->meta, true),
                        [
                            'cinetpay_verification' => $verificationResult['data'],
                            'verified_at' => now()
                        ]
                    ))
                ]);

                $this->processDeposit($transaction);
                $currentStatus = 'completed';

                Log::info('Transaction marquée comme COMPLÉTÉE', [
                    'transaction_id' => $transactionId
                ]);

            } elseif ($cinetpayStatus === 'REFUSED' && $currentStatus !== 'failed') {
                $transaction->update(['status' => 'failed']);
                $currentStatus = 'failed';

            } elseif (in_array($cinetpayStatus, ['CANCELLED', 'CANCELED']) && $currentStatus !== 'cancelled') {
                $transaction->update(['status' => 'cancelled']);
                $currentStatus = 'cancelled';
            }

            return response()->json([
                'success' => true,
                'status' => $currentStatus,
                'cinetpay_status' => $cinetpayStatus,
                'amount' => $transaction->amount,
                'created_at' => $transaction->created_at
            ]);
        }

        // Si la vérification échoue, mais que la transaction est en PENDING (code 662)
        // Ce n'est pas une erreur, l'utilisateur est en train de payer
        Log::info('Vérification CinetPay non conclusive', [
            'transaction_id' => $transactionId,
            'verification_result' => $verificationResult,
            'current_db_status' => $transaction->status
        ]);

        // Retourner le statut actuel avec succès (pas d'erreur 500)
        // car une transaction pending n'est pas une erreur
        return response()->json([
            'success' => true,
            'status' => $transaction->status,
            'cinetpay_status' => 'PENDING',
            'message' => 'Transaction en cours de traitement',
            'amount' => $transaction->amount,
            'created_at' => $transaction->created_at,
            'verification_code' => $verificationResult['code'] ?? null
        ]);
    }

    /**
     * Initier un retrait via l'API de transfert CinetPay
     * Mode manuel: créer une demande de retrait en attente de validation admin
     */
    public function initiateWithdrawal(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000|multiple_of:5',
            'phone_number' => 'required|string',
            'operator' => 'nullable|string|in:MTN,ORANGE,MOOV'
        ]);

        $operator = $request->input('operator', null);
        $user = $request->user();
        $amount = $request->amount;

        // Vérifier le solde disponible
        $availableForWithdrawal = max(0, $user->wallet_balance);

        if ($amount > $availableForWithdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Solde insuffisant pour ce retrait'
            ], 400);
        }

        try {
            // Créer la transaction en attente de validation admin
            $transaction = $user->transactions()->create([
                'type' => 'withdrawal',
                'amount' => -$amount,
                'description' => "Demande de retrait vers " . ($operator ?: 'Auto') . " - {$request->phone_number}",
                'status' => 'pending',
                'meta' => [
                    'operator' => $operator,
                    'phone_number' => $request->phone_number,
                    'requested_at' => now()->toISOString(),
                    'validation_required' => true,
                    'admin_validated' => false
                ]
            ]);

            Log::info('Demande de retrait créée en attente de validation admin', [
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'phone' => $request->phone_number,
                'operator' => $operator
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Votre demande de retrait a été enregistrée et est en attente de validation par l\'administrateur.',
                'transaction_id' => $transaction->id,
                'status' => 'pending',
                'current_balance' => $user->wallet_balance,
                'processing_mode' => 'manual_validation'
            ]);
        } catch (Exception $e) {
            Log::error('Erreur lors de la création de la demande de retrait', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement de la demande. Veuillez réessayer.'
            ], 500);
        }
    }

    /**
     * Vérifier le statut d'un retrait
     */
    public function checkWithdrawalStatus(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|integer|exists:transactions,id'
        ]);

        $user = $request->user();
        $transaction = Transaction::find($request->transaction_id);

        if (!$transaction || $transaction->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'transaction' => [
                'id' => $transaction->id,
                'amount' => $transaction->amount,
                'status' => $transaction->status,
                'created_at' => $transaction->created_at,
                'updated_at' => $transaction->updated_at,
                'meta' => $transaction->meta
            ]
        ]);
    }
}
