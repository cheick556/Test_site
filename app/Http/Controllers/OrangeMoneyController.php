<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class OrangeMoneyController extends Controller
{
    public function showPaymentPage() {
        $order = Order::findOrFail(session('order_id'));
        return view('frontend.orange.payment_form', compact('order'));
    }

    public function handlePayment(Request $request) {
        $inputs = $request->all();
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
