<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class CorisMoneyController extends Controller
{
    public function showPaymentPage() {
        $order = Order::findOrFail(session('order_id'));
        return view('frontend.coris.payment_form', compact('order'));
    }

    public function initPayment(Request $request) {
        $order = Order::findOrFail(session('order_id'));
        $inputs = $request->all();
        $result = initCorisMoneyPayment((int) $order->grand_total, $inputs['code_pin'], $inputs['phone_number']);
        $index = $result != null ? stripos($result->result, 'success') : -1;
        die(json_encode([
            'success' => $index ? true : false,
            'message' => !$index ? translateCMErrors($result->result) : ''
        ]));
    }

    public function handlePayment(Request $request) {
        $inputs = $request->all();
        $order = Order::findOrFail(session('order_id'));
        $result = sendCorisMoneyPayment($inputs['phone_number'], (int)$order->grand_total, $inputs['otp']);

        if(stripos($result->result, 'success')) {
            $url = handlePayment([
                'transaction' => $result->transactionid,
                'phone' => $result->client
            ]);
            die(json_encode([
                'success' => true,
                'url'     => $url
            ]));
        }
        else {
            die(json_encode([
                'success' => false,
                'message' => translateCMErrors($result->result)
            ]));
        }
    }
}
