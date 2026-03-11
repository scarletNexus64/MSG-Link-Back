<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    /**
     * Liste tous les wallets des utilisateurs
     *
     * GET /admin/wallets
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $role = $request->input('role');
        $perPage = $request->input('per_page', 20);
        $orderBy = $request->input('order_by', 'wallet_balance');
        $orderDirection = $request->input('order_direction', 'desc');

        $query = User::query()
            ->select(['id', 'first_name', 'last_name', 'email', 'phone', 'username', 'wallet_balance', 'created_at'])
            ->orderBy($orderBy, $orderDirection);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        if ($role) {
            $query->where('role', $role);
        }

        $users = $query->paginate($perPage);

        // Calculer les statistiques globales
        $totalBalance = User::sum('wallet_balance');
        $usersWithBalance = User::where('wallet_balance', '>', 0)->count();
        $totalUsers = User::count();
        $totalTransactions = WalletTransaction::count();

        $totalCredits = WalletTransaction::where('type', WalletTransaction::TYPE_CREDIT)
            ->sum('amount');
        $totalDebits = WalletTransaction::where('type', WalletTransaction::TYPE_DEBIT)
            ->sum('amount');

        return view('admin.wallets.index', compact(
            'users',
            'totalBalance',
            'usersWithBalance',
            'totalUsers',
            'totalTransactions',
            'totalCredits',
            'totalDebits',
            'search',
            'role'
        ));
    }

    /**
     * Affiche les détails du wallet d'un utilisateur
     *
     * GET /admin/wallets/{user}
     */
    public function show(User $user)
    {
        // Stats du user
        $stats = [
            'total_credits' => WalletTransaction::where('user_id', $user->id)
                ->where('type', WalletTransaction::TYPE_CREDIT)
                ->sum('amount'),
            'total_debits' => WalletTransaction::where('user_id', $user->id)
                ->where('type', WalletTransaction::TYPE_DEBIT)
                ->sum('amount'),
            'total_transactions' => WalletTransaction::where('user_id', $user->id)->count(),
            'total_withdrawals' => Withdrawal::where('user_id', $user->id)->sum('amount'),
            'pending_withdrawals' => Withdrawal::where('user_id', $user->id)
                ->where('status', Withdrawal::STATUS_PENDING)
                ->sum('amount'),
        ];

        // Transactions récentes
        $transactions = WalletTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Withdrawals récents
        $withdrawals = Withdrawal::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('admin.wallets.show', compact('user', 'stats', 'transactions', 'withdrawals'));
    }

    /**
     * Affiche toutes les transactions (tous utilisateurs)
     *
     * GET /admin/wallets/transactions
     */
    public function transactions(Request $request)
    {
        $type = $request->input('type');
        $userId = $request->input('user_id');
        $perPage = $request->input('per_page', 50);
        $search = $request->input('search');

        $query = WalletTransaction::query()
            ->with(['user', 'transactionable'])
            ->orderBy('created_at', 'desc');

        if ($type) {
            $query->where('type', $type);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $transactions = $query->paginate($perPage);

        // Statistiques des transactions
        $totalCredits = WalletTransaction::where('type', WalletTransaction::TYPE_CREDIT)
            ->sum('amount');
        $totalDebits = WalletTransaction::where('type', WalletTransaction::TYPE_DEBIT)
            ->sum('amount');
        $transactionCount = WalletTransaction::count();

        return view('admin.wallets.transactions', compact(
            'transactions',
            'totalCredits',
            'totalDebits',
            'transactionCount',
            'type',
            'userId',
            'search'
        ));
    }

    /**
     * Affiche toutes les demandes de retrait
     *
     * GET /admin/wallets/withdrawals
     */
    public function withdrawals(Request $request)
    {
        $status = $request->input('status');
        $search = $request->input('search');
        $perPage = $request->input('per_page', 50);

        $query = Withdrawal::query()
            ->with('user')
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            })->orWhere('phone_number', 'like', "%{$search}%");
        }

        $withdrawals = $query->paginate($perPage);

        // Statistiques
        $stats = [
            'total_pending' => Withdrawal::where('status', Withdrawal::STATUS_PENDING)->count(),
            'total_approved' => Withdrawal::where('status', Withdrawal::STATUS_APPROVED)->count(),
            'total_rejected' => Withdrawal::where('status', Withdrawal::STATUS_REJECTED)->count(),
            'total_completed' => Withdrawal::where('status', Withdrawal::STATUS_COMPLETED)->count(),
            'amount_pending' => Withdrawal::where('status', Withdrawal::STATUS_PENDING)->sum('amount'),
            'amount_completed' => Withdrawal::where('status', Withdrawal::STATUS_COMPLETED)->sum('net_amount'),
        ];

        return view('admin.wallets.withdrawals', compact(
            'withdrawals',
            'stats',
            'status',
            'search'
        ));
    }

    /**
     * Approuver une demande de retrait
     *
     * POST /admin/wallets/withdrawals/{withdrawal}/approve
     */
    public function approveWithdrawal(Request $request, Withdrawal $withdrawal)
    {
        if ($withdrawal->status !== Withdrawal::STATUS_PENDING) {
            return redirect()->back()->with('error', 'Cette demande de retrait ne peut pas être approuvée.');
        }

        try {
            DB::beginTransaction();

            // Débiter le wallet du user
            $user = $withdrawal->user;
            $user->decrement('wallet_balance', $withdrawal->amount);

            // Créer une transaction wallet
            WalletTransaction::create([
                'user_id' => $user->id,
                'type' => WalletTransaction::TYPE_DEBIT,
                'amount' => $withdrawal->amount,
                'balance_before' => $user->wallet_balance + $withdrawal->amount,
                'balance_after' => $user->wallet_balance,
                'description' => 'Retrait approuvé - ' . $withdrawal->provider,
                'reference' => $withdrawal->id,
                'transactionable_type' => Withdrawal::class,
                'transactionable_id' => $withdrawal->id,
            ]);

            // Marquer comme approuvé
            $withdrawal->update([
                'status' => Withdrawal::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Demande de retrait approuvée avec succès.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Erreur lors de l\'approbation: ' . $e->getMessage());
        }
    }

    /**
     * Rejeter une demande de retrait
     *
     * POST /admin/wallets/withdrawals/{withdrawal}/reject
     */
    public function rejectWithdrawal(Request $request, Withdrawal $withdrawal)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($withdrawal->status !== Withdrawal::STATUS_PENDING) {
            return redirect()->back()->with('error', 'Cette demande de retrait ne peut pas être rejetée.');
        }

        $withdrawal->update([
            'status' => Withdrawal::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejected_by' => $request->user()->id,
            'rejection_reason' => $request->reason,
        ]);

        return redirect()->back()->with('success', 'Demande de retrait rejetée.');
    }

    /**
     * Marquer un retrait comme complété
     *
     * POST /admin/wallets/withdrawals/{withdrawal}/complete
     */
    public function completeWithdrawal(Request $request, Withdrawal $withdrawal)
    {
        if ($withdrawal->status !== Withdrawal::STATUS_APPROVED) {
            return redirect()->back()->with('error', 'Seules les demandes approuvées peuvent être marquées comme complétées.');
        }

        $withdrawal->update([
            'status' => Withdrawal::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => $request->user()->id,
        ]);

        return redirect()->back()->with('success', 'Retrait marqué comme complété.');
    }

    /**
     * Ajuster manuellement le solde d'un utilisateur
     *
     * POST /admin/wallets/{user}/adjust
     */
    public function adjustBalance(Request $request, User $user)
    {
        $request->validate([
            'amount' => 'required|numeric|not_in:0',
            'description' => 'required|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            $amount = (float) $request->amount;
            $description = $request->description;
            $admin = $request->user();

            $balanceBefore = $user->wallet_balance;

            if ($amount > 0) {
                // Crédit
                $user->increment('wallet_balance', $amount);
                $type = WalletTransaction::TYPE_CREDIT;
            } else {
                // Débit
                $user->decrement('wallet_balance', abs($amount));
                $type = WalletTransaction::TYPE_DEBIT;
            }

            $balanceAfter = $user->fresh()->wallet_balance;

            // Créer une transaction wallet
            WalletTransaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => abs($amount),
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => 'Ajustement manuel: ' . $description,
                'reference' => 'ADJ-' . now()->format('YmdHis'),
                'transactionable_type' => null,
                'transactionable_id' => null,
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Solde ajusté avec succès.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Erreur lors de l\'ajustement: ' . $e->getMessage());
        }
    }
}
