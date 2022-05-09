<?php


namespace App\Http\Controllers\Api\V2;


use App\Address;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\User;
use App\WaitingTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class MobileMoneyController extends Controller
{
    public function initPayment(Request $request) {
        $inputs = $request->all();
        $result = initCorisMoneyPayment($inputs['amount'], $inputs['pincode'], $inputs['phone']);
        $index = $result != null ? stripos($result->result, 'success') : -1;

        return response()->json([
            'success' => $index ? true : false,
            'message' => !$index ? translateCMErrors($result->result) : 'OTP généré avec succès, vous allez recevoir un SMS contenant le code à utiliser'
        ]);
    }

    public function makePayment(Request $request, $set_paid = true) {

        $inputs = $request->all();

        $cartItems = Cart::where('user_id', $request->user_id)->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'order_id' => 0,
                'result' => false,
                'message' => 'Cart is Empty'
            ]);
        }

        $user = User::find($request->user_id);

        $address = Address::where('id', $cartItems->first()->address_id)->first();
        $shippingAddress = [];
        if ($address != null) {
            $shippingAddress['name']        = $user->name;
            $shippingAddress['email']       = $user->email;
            $shippingAddress['address']     = $address->address;
            $shippingAddress['country']     = $address->country;
            $shippingAddress['city']        = $address->city;
            $shippingAddress['postal_code'] = $address->postal_code;
            $shippingAddress['phone']       = $address->phone;
            if($address->latitude || $address->longitude) {
                $shippingAddress['lat_lang'] = $address->latitude.','.$address->longitude;
            }
        }

        $sum = 0.00;
        foreach ($cartItems as $cartItem) {
            $item_sum = 0;
            $item_sum += ($cartItem->price + $cartItem->tax) * $cartItem->quantity;
            $item_sum += $cartItem->shipping_cost - $cartItem->discount;
            $sum += $item_sum;   //// 'grand_total' => $request->g
        }

        switch ($inputs['payment_type']) {
            case 'moov':
                $set_paid = false;
                $result = initMoovMoneyPayment($sum, $inputs['phone']);
                $resultJson = json_decode($result);
                $resultArray = (array) $resultJson;

                if(!isset($resultJson->status)) {
                    return response()->json(['success' => false, 'message' => 'Le service est temporairement indisponible, veuillez réessayer plus tard']);
                }

                if($resultJson->status == '0') {
                    $order = $this->createOrder($request, $set_paid, $cartItems, $sum, $shippingAddress);
                    $transaction = new WaitingTransaction;
                    $transaction->order_id = $order->id;
                    $transaction->phone = $inputs['phone'];
                    $transaction->transaction_id = $resultArray['trans-id'];
                    $transaction->save();

                    return $this->sendSuccessResponse($order);
                }
                else {
                    if(isset($resultJson->message) && $resultJson->message == 'NOT SUBSCRIBED') {
                        $message = 'Vous n\'avez pas de compte Mobicash';
                    }
                    else {
                        $message = 'Le service est temporairement indisponible, veuillez réessayer plus tard';
                    }
                    return response()->json(['success' => false, 'message' => $message]);
                }
            case 'orange':
                $result = sendOrangeMoneyPayment($inputs['phone'], (int)$sum, $inputs['otp']);

                if($result->status == '200') {
                    $order = $this->createOrder($request, $set_paid, $cartItems, $sum, $shippingAddress);
                    handleMobileMoneyPayment('cart_payment' ,$result->transID, $order->id);
                    return $this->sendSuccessResponse($order);
                }
                else {
                    return response()->json([
                        'success' => false,
                        'message' => translateOMErrors($result)
                    ]);
                }
            case 'coris':
                $result = sendCorisMoneyPayment($inputs['phone'], (int)$sum, $inputs['otp']);

                if(stripos($result->result, 'success')) {
                    $order = $this->createOrder($request, $set_paid, $cartItems, $sum, $shippingAddress);
                    handleMobileMoneyPayment('cart_payment', [
                        'transaction' => $result->transactionid,
                        'phone' => $result->client
                    ], $order->id);
                    return $this->sendSuccessResponse($order);
                }
                else {
                    return response()->json([
                        'success' => false,
                        'message' => translateCMErrors($result->result)
                    ]);
                }
        }
    }

    private function sendSuccessResponse(Order $order) {
        return response()->json([
            'order_id' => $order->id,
            'success' => true,
            'message' => 'Votre commande a été effectuée avec succès'
        ]);
    }
    private function createOrder(Request $request, $set_paid, $cartItems, $sum, $shippingAddress): Order {
        $order = Order::create([
            'user_id' => $request->user_id,
            'seller_id' =>$request->owner_id,
            'shipping_address' => json_encode($shippingAddress),
            'payment_type' => $request->payment_type,
            'payment_status' => $set_paid ? 'paid' : 'unpaid',
            'grand_total' => $sum,
            'coupon_discount' => $cartItems->sum('discount'),
            'code' => date('Ymd-his'),
            'date' => strtotime('now')
        ]);

        foreach ($cartItems as $cartItem) {
            $product = Product::find($cartItem->product_id);

            $product_stocks = $product->stocks->where('variant', $cartItem->variation)->first();
            $product_stocks->qty -= $cartItem->quantity;
            $product_stocks->save();

            /*if ($cartItem->variation) {
                $product_stocks = $product->stocks->where('variant', $cartItem->variation)->first();
                $product_stocks->qty -= $cartItem->quantity;
                $product_stocks->save();
            } else {
                $product->update([
                    'current_stock' => DB::raw('current_stock - ' . $cartItem->quantity)
                ]);
            }*/

            // save order details
            OrderDetail::create([
                'order_id' => $order->id,
                'seller_id' => $product->user_id,
                'product_id' => $product->id,
                'variation' => $cartItem->variation,
                'price' => $cartItem->price * $cartItem->quantity,
                'tax' => $cartItem->tax * $cartItem->quantity,
                'shipping_cost' => $cartItem->shipping_cost,
                'quantity' => $cartItem->quantity,
                'payment_status' => $set_paid ? 'paid' : 'unpaid'
            ]);
            $product->update([
                'num_of_sale' => DB::raw('num_of_sale + ' . $cartItem->quantity)
            ]);
        }
        if ($cartItems->first()->coupon_code != '') {
            CouponUsage::create([
                'user_id' => $request->user_id,
                'coupon_id' => Coupon::where('code', $cartItems->first()->coupon_code)->first()->id
            ]);
        }

        Cart::where('user_id', $request->user_id)->where('owner_id', $request->owner_id)->delete();
        return $order;
    }
}
