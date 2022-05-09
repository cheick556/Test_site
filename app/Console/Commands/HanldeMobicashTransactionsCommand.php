<?php

namespace App\Console\Commands;

use App\Http\Controllers\CheckoutController;
use App\WaitingTransaction;
use Illuminate\Console\Command;

class HanldeMobicashTransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'moov:handle-transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Handle moov pending payments';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $transactions = WaitingTransaction::query()->limit(20)->get();
        $checkoutController = new CheckoutController();

        foreach ($transactions as $transaction) {
            $result = json_decode(handleMoovMoneyPayment($transaction->transaction_id));

            if (isset($result->status) && $result->status == '0') {
                $paymentDetails = json_encode([
                    'transaction_id' => $transaction->transaction_id,
                    'phone' => $transaction->phone
                ]);

                $checkoutController->checkout_done($transaction->order_id, $paymentDetails, false);
                $transaction->delete();
            }
        }
    }
}
