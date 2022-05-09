<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\WaitingTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;

class MoovMoneyController extends Controller
{
    public function showPaymentPage() {
        $order = Order::findOrFail(session('order_id'));
        return view('frontend.moov.payment_form', compact('order'));
    }

    public function initPayment(Request $request)
    {
        $phone = $request->phone_number;
        $order = Order::findOrFail(session('order_id'));
        $result = initMoovMoneyPayment((int)$order->grand_total, $phone);
        $resultJson = json_decode($result);
        $resultArray = (array) $resultJson;

        if(!isset($resultJson->status)) {
            return response()->json(['success' => false, 'message' => translate('The service is temporary unavailable, try later please')]);
        }

        if($resultJson->status == '0') {
            $transaction = new WaitingTransaction;
            $transaction->order_id = $order->id;
            $transaction->phone = $phone;
            $transaction->transaction_id = $resultArray['trans-id'];
            $transaction->save();
            Session::forget('club_point');
            $url = route('order_to_confirm', [$order->id]);

            return response()->json([
                'success' => true,
                'url' => $url,
                'message' => translate('The transaction has been initiated with success, you can now complete the order and leave this page')
            ]);

        }

        else {
            if(isset($resultJson->message) && $resultJson->message == 'NOT SUBSCRIBED') {
                $message = 'You don\'t have a Moov money account';
            }
            else {
                $message = 'The service is temporary unavailable, try later please';
            }
            return response()->json(['success' => false, 'message' => translate($message)]);
        }
    }

    public function handlePayment(Request $request) {
        $inputs = $request->all();
        //Http
        $order = Order::findOrFail(session('order_id'));
        $result = sendOrangeMoneyPayment($inputs['phone_number'], (int)$order->grand_total, $inputs['otp']);

        if($result->status == '200') {
            $url = handlePayment($result->transID);
            die(json_encode([
                'success' => true,
                'url' => $url
            ]));
        }
        else {
            die(json_encode([
                'success' => false,
                'message' => translateOMErrors($result)
            ]));
        }
    }
}
