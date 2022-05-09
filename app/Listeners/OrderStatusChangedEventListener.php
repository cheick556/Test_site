<?php

namespace App\Listeners;


use App\Events\OrderStatusChangedEvent;
use App\Models\Order;
use App\Models\Shop;

class OrderStatusChangedEventListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  OrderStatusChangedEvent  $event
     * @return void
     */
    public function handle($event)
    {
        /** @var Order $order */
        $order = Order::find($event->getOrderId());
        $messagesToSend = $this->getSMSData($order, $event->getStatus());

        foreach ($messagesToSend as $messageToSend) {
            if (!empty($messageToSend['recipient'])) {
                sendOrangeSMS(
                    normalizeNumber($messageToSend['recipient']),
                    $messageToSend['body'],
                    getenv('SMS_SENDER_NAME')
                );
            }
        }
    }

    private function getSMSData(Order $order, string $status): array {
        $sms = sprintf('Votre commande %s ', $order->code);
        $shippingAddress = json_decode($order->shipping_address);
        $seller = Shop::where('user_id', $order->seller_id)->first();

        switch ($status) {
            case 'delivered':
                return [
                    ['body' => $sms .'a été livrée avec succès.', 'recipient' => $shippingAddress->phone]
                ];
            case 'picked':
                return [
                    ['body' => $sms .'a été prélévée par le livreur.', 'recipient' => $shippingAddress->phone]
                ];
            case 'on_the_way':
                return [
                    ['body' => $sms.'est en cours de livraison.', 'recipient' => $shippingAddress->phone]
                ];
            case 'paid':
                $smsData = [
                    ['body' => $sms.' a été validée avec succès', 'recipient' => $shippingAddress->phone]
                ];
                if ($seller !== null) {
                    $smsData[] = [
                        'body' => 'Bonjour. Vous avez reçu une nouvelle commande dans votre boutique, veuillez vous connecter pour en prendre connaissance et la préparer.',
                        'recipient' => $seller->phone
                    ];
                }
                return $smsData;
            default:
                return [];
        }
    }

    private function getSMSContent(Order $order, string $status): ?string {
        $sms = sprintf('Votre commande %s ', $order->code);
        switch ($status) {
            case 'delivered':
                return $sms .'a été livrée avec succès.';
            case 'picked':
                return $sms .'a été prélévée par le livreur.';
            case 'on_the_way':
                return $sms.'est en cours de livraison.';
            default:
                return null;
        }
    }
}
