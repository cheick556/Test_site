<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Carbon\Traits\Creator;
use Illuminate\Console\Command;

class OrangeSmsApiAlert extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orange_sms:alert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
     * @return int
     */
    public function handle()
    {
        $data = availableSMSApiSMSCount();

        $smsCount = (int) $data[0];
        /** @var Creator $expirationDate */
        $expirationDate = $data[1];
        $numbers = ['65286745', '70209088'];
        $message = null;

        if ($smsCount < 1000) {
            $message = 'Urgent !! Le solde API SMS est a un niveau bas ($smsCount SMS). Merci recharger au plus vite. ';
        }

        if ((Carbon::now())->diffInDays($expirationDate) <= 3) {
            $message .= "Urgent!!! Le solde API SMS expire le ".$expirationDate->format('d/m/Y H:i');
        }

        if ($message != null) {
            foreach ($numbers as $number) {
                sendOrangeSMS($number, $message);
            }
        }

        return 0;
    }
}
