<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\MpesaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('payments:recover')]
#[Description('Query Daraja for pending payments older than 60 seconds and reconcile them')]
class RecoverPendingPayments extends Command
{
    public function handle(MpesaService $mpesa): int
    {
        $payments = Payment::where('status', Payment::STATUS_PENDING)
            ->where('created_at', '<=', now()->subSeconds(60))
            ->orderBy('id')
            ->limit(100)
            ->get();

        $failures = 0;

        foreach ($payments as $payment) {
            try {
                $mpesa->reconcilePendingPayment($payment);
            } catch (Throwable $e) {
                $failures++;

                // One bad payment must not stop the rest of the batch.
                Log::error('Pending payment recovery failed', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Checked {$payments->count()} pending payment(s).");

        if ($failures > 0) {
            $this->warn("{$failures} payment(s) could not be reconciled; they will be retried.");
        }

        return self::SUCCESS;
    }
}
