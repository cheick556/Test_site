<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Utility\Osms;
use App\Utils\Util;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSMSJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $campaignId;
    private $message;
    private $phone;
    private $senderId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($message, $phone, $senderId = null)
    {
        if($senderId === null) {
            $senderId = getenv('SMS_SENDER_NAME');
        }
        $this->message = $message;
        $this->phone = $phone;
        $this->senderId = $senderId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $config = [
            'clientId' => getenv('ORANGE_CLIENT_ID'),
            'clientSecret' => getenv('ORANGE_CLIENT_SECRET')
        ];
        $osms = new Osms($config);
        // retrieve an access token
        $osms->getTokenFromConsumerKey();
        $senderAddress = 'tel:+22600000000';
        $receiverAddress = 'tel:'.normalizeNumber($this->phone);
        $response = $osms->sendSMS($senderAddress, $receiverAddress, $this->message, $this->senderId);

        if (isset($response['requestError'])) {
            throw new \Exception(json_encode($response));
        }
    }
}
