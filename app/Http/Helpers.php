<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ClubPointController;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\CustomerPackageController;
use App\Http\Controllers\WalletController;
use App\Utility\Osms;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use App\Notifications\OrderNotification;

use App\Currency;
use App\BusinessSetting;
use App\Product;
use App\ProductStock;
use App\Address;
use App\SubSubCategory;
use App\FlashDealProduct;
use App\CustomerPackage;
use App\FlashDeal;
use App\Models\OtpConfiguration;
use App\Upload;
use App\Translation;
use App\City;
use App\CommissionHistory;
use App\Utility\TranslationUtility;
use App\Utility\CategoryUtility;
use App\Utility\MimoUtility;
use Twilio\Rest\Client;
use App\Wallet;
use App\Order;
use App\User;

function normalizeNumber($number) {
    if(str_starts_with($number, '226')) {
        $number = '+'.$number;
    }

    if(!str_starts_with($number, '+')) {
        $number = '+226'.$number;
    }
    return $number;
}

function translateStatus($status) {
    switch ($status) {
        case 'delivered':
            return 'Livrée';
        case 'picked':
            return 'Ramassée par le livreur';
        case 'on_the_way':
            return 'En cours de livraison';
        case 'pending':
            return 'En attente de livraison';
        default:
            return null;
    }
}

function sendOrangeSMS($receiver, $message, $senderName = null) {
    \App\Jobs\SendSMSJob::dispatch($message, $receiver, $senderName);
}

function availableSMSApiSMSCount() {
    $config = [
        'clientId' => getenv('ORANGE_CLIENT_ID'),
        'clientSecret' => getenv('ORANGE_CLIENT_SECRET')
    ];
    $osms = new Osms($config);
    $osms->getTokenFromConsumerKey();
    $response = $osms->getAdminContracts('BFA');
    $smsCount = 0;
    $expiationDate = new \DateTime();
    array_walk_recursive($response, function ($value, $key) use (&$smsCount, &$expiationDate) {
        if($key === 'availableUnits') {
            $smsCount = $value;
        }
        if($key === 'expires') {
            $expiationDate = $value;
        }
    });
    return [$smsCount, Carbon::createFromTimeString($expiationDate)];
}

if(!function_exists('handlePayment')) {
    function handlePayment($payment_details) {
        if(Session::has('payment_type')){
            if(Session::get('payment_type') == 'cart_payment'){
                $checkoutController = new CheckoutController;
                return $checkoutController->checkout_done(Session::get('order_id'), $payment_details, false);
            }
            elseif (Session::get('payment_type') == 'wallet_payment') {
                $walletController = new WalletController;
                return $walletController->wallet_payment_done(Session::get('payment_data'), $payment_details);
            }
            elseif (Session::get('payment_type') == 'customer_package_payment') {
                $customer_package_controller = new CustomerPackageController;
                return $customer_package_controller->purchase_payment_done(Session::get('payment_data'), $payment_details);
            }
        }
    }
}

if(!function_exists('handleMobileMoneyPayment')) {
    function handleMobileMoneyPayment($payment_type, $payment_details, $orderId) {
        if($payment_type == 'cart_payment'){
            $checkoutController = new CheckoutController;
            return $checkoutController->checkout_done($orderId, $payment_details, false);
        }
        /*elseif ($payment_type == 'wallet_payment') {
            $walletController = new WalletController;
            return $walletController->wallet_payment_done(Session::get('payment_data'), $payment_details);
        }*/
    }
}
if(!function_exists('initMoovMoneyPayment')) {
    function initMoovMoneyPayment($amount, $customerNumber) {
        $token = base64_encode(getenv('MOOV_MONEY_MERCHANT_ID') .':' .getenv('MOOV_MONEY_MERCHANT_PASSWORD'));
        $time = time();
        $url =
            getenv('MOOV_MONEY_IS_TEST_MODE') == 1 ?
            "https://196.28.245.227/tlcfzc_gw/api/gateway/3pp/transaction/process" :
            "https://196.28.245.227/tlcfzc_gw_prod/mbs-gateway/gateway/3pp/transaction/process";
        $result = shell_exec(
            <<< HEREDOC
            curl --location --request POST '$url' \
			--header 'command-id: mror-transaction-ussd' \
			--header 'Authorization: Basic $token' \
			--header 'Content-Type: application/json' \
			--data-raw '{
			    "request-id": "DAKWARI-$time",
			    "destination": "226{$customerNumber}",
			    "amount": $amount,
			    "remarks": "",
			    "message": "",
			    "extended-data": {}
			}' -k
HEREDOC
        );
        return $result;
    }
}

if(!function_exists('handleMoovMoneyPayment')) {
    function handleMoovMoneyPayment($transactionId) {
        //return '{"request-id":"DAKAWRI-002022-12","trans-id":"CHKTRANS210905.1739.J00001","status":"0","message":"OK","extended-data":{"data":{"reference-id":"WMRCH210829.1650.J00003"}}}';
        $token = base64_encode(getenv('MOOV_MONEY_MERCHANT_ID') .':' .getenv('MOOV_MONEY_MERCHANT_PASSWORD'));
        $url =
            getenv('MOOV_MONEY_IS_TEST_MODE') == 1 ?
            "https://196.28.245.227/tlcfzc_gw/api/gateway/3pp/transaction/process" :
            "https://196.28.245.227/tlcfzc_gw_prod/mbs-gateway/gateway/3pp/transaction/process";
        $result = shell_exec(
            <<< HEREDOC
            curl --location --request POST '$url' \
			--header 'command-id: process-check-transaction' \
			--header 'Authorization: Basic $token' \
			--header 'Content-Type: application/json' \
			--data-raw '{
			    "request-id": "$transactionId"
			}' -k
HEREDOC
        );
        return $result;
    }
}

if(!function_exists('initCorisMoneyPayment')) {
    function initCorisMoneyPayment($amount, $customerPin, $customerNumber) {
        $corisMoneyurl = getenv('CORIS_MONEY_IS_TEST_MODE') == 1 ? "https://22610.tagpay.fr/api" : "https://bf.corismoney.com/api";
        $session = curl_init($corisMoneyurl . '/tpgetcode.php');
        $params = [
            'client' => $customerNumber,
            'pin' => $customerPin,
            'maxamount' => $amount,
            'merchantid' => getenv('CORIS_MONEY_MERCHANT_ID'),
            'password' => getenv('CORIS_MONEY_MERCHANT_PASSWORD'),
            'currency' =>'952'
        ];
        $url = $corisMoneyurl . '/tpgetcode.php?' . http_build_query($params);
        curl_setopt($session, CURLOPT_URL, $url);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($session);
        if($response) {
            $xml = simplexml_load_string($response);
            $json = json_decode(json_encode($xml));
            return $json;
        }
        return $response;
    }
}

if(!function_exists('sendCorisMoneyPayment')) {
    function sendCorisMoneyPayment($customerNumber, $amount, $otp) {
        $corisMoneyurl = getenv('CORIS_MONEY_IS_TEST_MODE') == 1 ? "https://22610.tagpay.fr/api" : "https://bf.corismoney.com/api";
        $session = curl_init($corisMoneyurl . '/tpdebit.php');
        $params = [
            'trxcode' => $otp,
            'amount' => $amount,
            'merchantid' => getenv('CORIS_MONEY_MERCHANT_ID'),
            'password' => getenv('CORIS_MONEY_MERCHANT_PASSWORD'),
            'currency' =>'952'
        ];
        curl_setopt($session, CURLOPT_POSTFIELDS, $params);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($session);
        if($response) {
            $xml = simplexml_load_string($response);
            $json = json_decode(json_encode($xml));
            return $json;
        }
        return $response;
    }

    if(!function_exists('translateCMErrors')) {
        function translateCMErrors($result) {
            $defaultMessage = 'An error has occurred, please try again later';
            if(stripos($result, "010")) {
                return translate('It appears that you do not have a Coris money account');
            }
            if(stripos($result, "client pincode error")) {
                return translate('Your pin code is incorrect');
            }
            if(stripos($result, "010")) {
                return translate('Incorrect Pin code');
            }
            if(stripos($result, "510") || stripos($result, "099")) {
                return translate("Incorrect or expired OTP code");
            }
            return $defaultMessage;
        }
    }
}

if(!function_exists('sendOrangeMoneyPayment')) {
    function sendOrangeMoneyPayment($customerNumber, $amount, $otp) {
        $params = '
            <?xml version="1.0" encoding="UTF-8"?>
            <COMMAND>
                <TYPE>OMPREQ</TYPE>
                <customer_msisdn>'.$customerNumber.'</customer_msisdn>
                <merchant_msisdn>'.getenv('ORANGE_MONEY_MERCHANT_NUMBER').'</merchant_msisdn>
                <api_username>'.getenv('ORANGE_MONEY_MERCHANT_ID').'</api_username>
                <api_password>'.getenv('ORANGE_MONEY_MERCHANT_PASSWORD').'</api_password>
                <amount>'.$amount.'</amount>
                <PROVIDER>101</PROVIDER>
                <PROVIDER2>101</PROVIDER2>
                <PAYID>12</PAYID>
                <PAYID2>12</PAYID2>
                <otp>'.$otp.'</otp>
            </COMMAND>
        ';
        $url = getenv('ORANGE_MONEY_IS_TEST_MODE') == 1
            ? "https://testom.orange.bf:9008/payment"
            : "https://apiom.orange.bf:9007/payment";
        $session = curl_init($url);
        curl_setopt($session, CURLOPT_POSTFIELDS, $params);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($session);
        $response = '<response>'.$response.'</response>';
        $xml = simplexml_load_string($response);
        $json = json_decode(json_encode($xml));
        return $json;
    }

    if(!function_exists('translateOMErrors')) {
        function translateOMErrors($result) {
            switch($result->status) {
                case '08':
                    return translate('The amount does not match the amount to be paid');
                case 'OTPINV':
                    return translate('OTP is invalid');
                case '990422':
                case '00066':
                    return translate('The number is invalid, check that it is linked to an Orange Money account');
                case '990418':
                    return translate('The OTP code has already been used');
                default:
                    return $result->message;
            }
        }
    }
}
//highlights the selected navigation on admin panel
if (! function_exists('sendSMS')) {
    function sendSMS($to, $from, $text, $template_id)
    {
        sendOrangeSMS(
            normalizeNumber($to),
            $text,
            getenv('SMS_SENDER_NAME')
        );
    }
}

//highlights the selected navigation on admin panel
if (! function_exists('areActiveRoutes')) {
    function areActiveRoutes(Array $routes, $output = "active")
    {
        foreach ($routes as $route) {
            if (Route::currentRouteName() == $route) return $output;
        }

    }
}

//highlights the selected navigation on frontend
if (! function_exists('areActiveRoutesHome')) {
    function areActiveRoutesHome(Array $routes, $output = "active")
    {
        foreach ($routes as $route) {
            if (Route::currentRouteName() == $route) return $output;
        }

    }
}

//highlights the selected navigation on frontend
if (! function_exists('default_language')) {
    function default_language()
    {
        return env("DEFAULT_LANGUAGE");
    }
}

/**
 * Save JSON File
 * @return Response
 */
if (! function_exists('convert_to_usd')) {
    function convert_to_usd($amount) {
        $business_settings = BusinessSetting::where('type', 'system_default_currency')->first();
        if($business_settings!=null){
            $currency = Currency::find($business_settings->value);
            return (floatval($amount) / floatval($currency->exchange_rate)) * Currency::where('code', 'USD')->first()->exchange_rate;
        }
    }
}

if (! function_exists('convert_to_kes')) {
    function convert_to_kes($amount) {
        $business_settings = BusinessSetting::where('type', 'system_default_currency')->first();
        if($business_settings!=null){
            $currency = Currency::find($business_settings->value);
            return (floatval($amount) / floatval($currency->exchange_rate)) * Currency::where('code', 'KES')->first()->exchange_rate;
        }
    }
}

//filter products based on vendor activation system
if (! function_exists('filter_products')) {
    function filter_products($products) {
        $verified_sellers = verified_sellers_id();
        if(BusinessSetting::where('type', 'vendor_system_activation')->first()->value == 1){
            return $products->where('approved', '1')->where('published', '1')->orderBy('created_at', 'desc')->where(function($p) use ($verified_sellers){
                $p->where('added_by', 'admin')->orWhere(function($q) use ($verified_sellers){
                    $q->whereIn('user_id', $verified_sellers);
                });
            });
        }
        else{
            return $products->where('published', '1')->where('added_by', 'admin');
        }
    }
}

//cache products based on category
if (! function_exists('get_cached_products')) {
    function get_cached_products($category_id = null) {
        $products = \App\Product::where('published', 1)->where('approved', '1');
        $verified_sellers = verified_sellers_id();
        if(BusinessSetting::where('type', 'vendor_system_activation')->first()->value == 1){
            $products =  $products->where(function($p) use ($verified_sellers){
                $p->where('added_by', 'admin')->orWhere(function($q) use ($verified_sellers){
                    $q->whereIn('user_id', $verified_sellers);
                });
            });
        }
        else{
            $products = $products->where('added_by', 'admin');
        }

        if ($category_id != null) {
            return Cache::remember('products-category-'.$category_id, 86400, function () use ($category_id, $products) {
                $category_ids = CategoryUtility::children_ids($category_id);
                $category_ids[] = $category_id;
                return $products->whereIn('category_id', $category_ids)->latest()->take(12)->get();
            });
        }
        else {
            return Cache::remember('products', 86400, function () use ($products) {
                return $products->latest()->get();
            });
        }
    }
}

if (! function_exists('verified_sellers_id')) {
    function verified_sellers_id() {
        return App\Seller::where('verification_status', 1)->get()->pluck('user_id')->toArray();
    }
}

//converts currency to home default currency
if (! function_exists('convert_price')) {
    function convert_price($price)
    {
        $business_settings = BusinessSetting::where('type', 'system_default_currency')->first();
        if($business_settings != null){
            $currency = Currency::find($business_settings->value);
            $price = floatval($price) / floatval($currency->exchange_rate);
        }

        $code = \App\Currency::findOrFail(get_setting('system_default_currency'))->code;
        if(Session::has('currency_code')){
            $currency = Currency::where('code', Session::get('currency_code', $code))->first();
        }
        else{
            $currency = Currency::where('code', $code)->first();
        }

        $price = floatval($price) * floatval($currency->exchange_rate);

        return $price;
    }
}

//formats currency
if (! function_exists('format_price')) {
    function format_price($price)
    {
        if (get_setting('decimal_separator') == 1) {
            $fomated_price = number_format($price, get_setting('no_of_decimals'));
        }
        else {
            $fomated_price = number_format($price, get_setting('no_of_decimals'), ',' , ' ');
        }

        if(get_setting('symbol_format') == 1){
            return currency_symbol().$fomated_price;
        } else if(get_setting('symbol_format') == 3){
            return currency_symbol().' '.$fomated_price;
        } else if(get_setting('symbol_format') == 4) {
            return $fomated_price.' '.currency_symbol();
        }
        return $fomated_price.currency_symbol();

    }
}

//formats price to home default price with convertion
if (! function_exists('single_price')) {
    function single_price($price)
    {
        return format_price(convert_price($price));
    }
}

//Shows Price on page based on low to high
if (! function_exists('home_price')) {
    function home_price($product)
    {
        $lowest_price = $product->unit_price;
        $highest_price = $product->unit_price;

        if ($product->variant_product) {
            foreach ($product->stocks as $key => $stock) {
                if($lowest_price > $stock->price){
                    $lowest_price = $stock->price;
                }
                if($highest_price < $stock->price){
                    $highest_price = $stock->price;
                }
            }
        }

        foreach ($product->taxes as $product_tax) {
            if($product_tax->tax_type == 'percent'){
                $lowest_price += ($lowest_price * $product_tax->tax) / 100;
                $highest_price += ($highest_price * $product_tax->tax) / 100;
            }
            elseif($product_tax->tax_type == 'amount'){
                $lowest_price += $product_tax->tax;
                $highest_price += $product_tax->tax;
            }
        }

        $lowest_price = convert_price($lowest_price);
        $highest_price = convert_price($highest_price);

        if($lowest_price == $highest_price){
            return format_price($lowest_price);
        }
        else{
            return format_price($lowest_price).' - '.format_price($highest_price);
        }
    }
}

//Shows Price on page based on low to high with discount
if (! function_exists('home_discounted_price')) {
    function home_discounted_price($product)
    {
        $lowest_price = $product->unit_price;
        $highest_price = $product->unit_price;

        if ($product->variant_product) {
            foreach ($product->stocks as $key => $stock) {
                if($lowest_price > $stock->price){
                    $lowest_price = $stock->price;
                }
                if($highest_price < $stock->price){
                    $highest_price = $stock->price;
                }
            }
        }

        $discount_applicable = false;

        if ($product->discount_start_date == null) {
            $discount_applicable = true;
        }
        elseif (strtotime(date('d-m-Y H:i:s')) >= $product->discount_start_date &&
            strtotime(date('d-m-Y H:i:s')) <= $product->discount_end_date) {
            $discount_applicable = true;
        }

        if ($discount_applicable) {
            if($product->discount_type == 'percent'){
                $lowest_price -= ($lowest_price*$product->discount)/100;
                $highest_price -= ($highest_price*$product->discount)/100;
            }
            elseif($product->discount_type == 'amount'){
                $lowest_price -= $product->discount;
                $highest_price -= $product->discount;
            }
        }

        foreach ($product->taxes as $product_tax) {
            if($product_tax->tax_type == 'percent'){
                $lowest_price += ($lowest_price * $product_tax->tax) / 100;
                $highest_price += ($highest_price * $product_tax->tax) / 100;
            }
            elseif($product_tax->tax_type == 'amount'){
                $lowest_price += $product_tax->tax;
                $highest_price += $product_tax->tax;
            }
        }

        $lowest_price = convert_price($lowest_price);
        $highest_price = convert_price($highest_price);

        if($lowest_price == $highest_price){
            return format_price($lowest_price);
        }
        else{
            return format_price($lowest_price).' - '.format_price($highest_price);
        }
    }
}

//Shows Base Price
if (! function_exists('home_base_price_by_stock_id')) {
    function home_base_price_by_stock_id($id)
    {
        $product_stock = ProductStock::findOrFail($id);
        $price = $product_stock->price;
        $tax = 0;

        foreach ($product_stock->product->taxes as $product_tax) {
            if($product_tax->tax_type == 'percent'){
                $tax += ($price * $product_tax->tax) / 100;
            }
            elseif($product_tax->tax_type == 'amount'){
                $tax += $product_tax->tax;
            }
        }
        $price += $tax;
        return format_price(convert_price($price));
    }
}
if (! function_exists('home_base_price')) {
    function home_base_price($product)
    {
        $price = $product->unit_price;
        $tax = 0;

        foreach ($product->taxes as $product_tax) {
            if($product_tax->tax_type == 'percent'){
                $tax += ($price * $product_tax->tax) / 100;
            }
            elseif($product_tax->tax_type == 'amount'){
                $tax += $product_tax->tax;
            }
        }
        $price += $tax;
        return format_price(convert_price($price));
    }
}

//Shows Base Price with discount
if (! function_exists('home_discounted_base_price_by_stock_id')) {
    function home_discounted_base_price_by_stock_id($id)
    {
        $product_stock = ProductStock::findOrFail($id);
        $product = $product_stock->product;
        $price = $product_stock->price;
        $tax = 0;

        $discount_applicable = false;

        if ($product->discount_start_date == null) {
            $discount_applicable = true;
        }
        elseif (strtotime(date('d-m-Y H:i:s')) >= $product->discount_start_date &&
            strtotime(date('d-m-Y H:i:s')) <= $product->discount_end_date) {
            $discount_applicable = true;
        }

        if ($discount_applicable) {
            if($product->discount_type == 'percent'){
                $price -= ($price*$product->discount)/100;
            }
            elseif($product->discount_type == 'amount'){
                $price -= $product->discount;
            }
        }

        foreach ($product->taxes as $product_tax) {
            if($product_tax->tax_type == 'percent'){
                $tax += ($price * $product_tax->tax) / 100;
            }
            elseif($product_tax->tax_type == 'amount'){
                $tax += $product_tax->tax;
            }
        }
        $price += $tax;

        return format_price(convert_price($price));
    }
}
//Shows Base Price with discount
if (! function_exists('home_discounted_base_price')) {
    function home_discounted_base_price($product)
    {
        $price = $product->unit_price;
        $tax = 0;

        $discount_applicable = false;

        if ($product->discount_start_date == null) {
            $discount_applicable = true;
        }
        elseif (strtotime(date('d-m-Y H:i:s')) >= $product->discount_start_date &&
            strtotime(date('d-m-Y H:i:s')) <= $product->discount_end_date) {
            $discount_applicable = true;
        }

        if ($discount_applicable) {
            if($product->discount_type == 'percent'){
                $price -= ($price*$product->discount)/100;
            }
            elseif($product->discount_type == 'amount'){
                $price -= $product->discount;
            }
        }

        foreach ($product->taxes as $product_tax) {
            if($product_tax->tax_type == 'percent'){
                $tax += ($price * $product_tax->tax) / 100;
            }
            elseif($product_tax->tax_type == 'amount'){
                $tax += $product_tax->tax;
            }
        }
        $price += $tax;

        return format_price(convert_price($price));
    }
}

if (! function_exists('currency_symbol')) {
    function currency_symbol()
    {
        $code = \App\Currency::findOrFail(get_setting('system_default_currency'))->code;
        if(Session::has('currency_code')){
            $currency = Currency::where('code', Session::get('currency_code', $code))->first();
        }
        else{
            $currency = Currency::where('code', $code)->first();
        }
        return $currency->symbol;
    }
}

if(! function_exists('renderStarRating')){
    function renderStarRating($rating,$maxRating=5) {
        $fullStar = "<i class = 'las la-star active'></i>";
        $halfStar = "<i class = 'las la-star half'></i>";
        $emptyStar = "<i class = 'las la-star'></i>";
        $rating = $rating <= $maxRating?$rating:$maxRating;

        $fullStarCount = (int)$rating;
        $halfStarCount = ceil($rating)-$fullStarCount;
        $emptyStarCount = $maxRating -$fullStarCount-$halfStarCount;

        $html = str_repeat($fullStar,$fullStarCount);
        $html .= str_repeat($halfStar,$halfStarCount);
        $html .= str_repeat($emptyStar,$emptyStarCount);
        echo $html;
    }
}


//Api
if (! function_exists('homeBasePrice')) {
    function homeBasePrice($product)
    {
        $price = $product->unit_price;
        $tax = 0;
        foreach ($product->taxes as $product_tax) {
            if($product_tax->tax_type == 'percent'){
                $tax += ($price * $product_tax->tax) / 100;
            }
            elseif($product_tax->tax_type == 'amount'){
                $tax += $product_tax->tax;
            }
        }

        $price += $tax;
//        if ($product->tax_type == 'percent') {
//            $price += ($price * $product->tax) / 100;
//        } elseif ($product->tax_type == 'amount') {
//            $price += $product->tax;
//        }
        return $price;
    }
}

if (! function_exists('homeDiscountedBasePrice')) {
    function homeDiscountedBasePrice($product)
    {
        $price = $product->unit_price;
        $tax = 0;

        $discount_applicable = false;

        if ($product->discount_start_date == null) {
            $discount_applicable = true;
        }
        elseif (strtotime(date('d-m-Y H:i:s')) >= $product->discount_start_date &&
            strtotime(date('d-m-Y H:i:s')) <= $product->discount_end_date) {
            $discount_applicable = true;
        }

        if ($discount_applicable) {
            if($product->discount_type == 'percent'){
                $price -= ($price*$product->discount)/100;
            }
            elseif($product->discount_type == 'amount'){
                $price -= $product->discount;
            }
        }

        foreach ($product->taxes as $product_tax) {
            if($product_tax->tax_type == 'percent'){
                $tax += ($price * $product_tax->tax) / 100;
            }
            elseif($product_tax->tax_type == 'amount'){
                $tax += $product_tax->tax;
            }
        }
        $price += $tax;
        return $price;
    }
}

if (! function_exists('homePrice')) {
    function homePrice($product)
    {
        $lowest_price = $product->unit_price;
        $highest_price = $product->unit_price;
        $tax = 0;

        if ($product->variant_product) {
            foreach ($product->stocks as $key => $stock) {
                if($lowest_price > $stock->price){
                    $lowest_price = $stock->price;
                }
                if($highest_price < $stock->price){
                    $highest_price = $stock->price;
                }
            }
        }

//        if ($product->tax_type == 'percent') {
//            $lowest_price += ($lowest_price*$product->tax)/100;
//            $highest_price += ($highest_price*$product->tax)/100;
//        }
//        elseif ($product->tax_type == 'amount') {
//            $lowest_price += $product->tax;
//            $highest_price += $product->tax;
//        }
        foreach ($product->taxes as $product_tax) {
            if($product_tax->tax_type == 'percent'){
                $lowest_price += ($lowest_price * $product_tax->tax) / 100;
                $highest_price += ($highest_price * $product_tax->tax) / 100;
            }
            elseif($product_tax->tax_type == 'amount'){
                $lowest_price += $product_tax->tax;
                $highest_price += $product_tax->tax;
            }
        }

        $lowest_price = convertPrice($lowest_price);
        $highest_price = convertPrice($highest_price);

        return $lowest_price.' - '.$highest_price;
    }
}

if (! function_exists('homeDiscountedPrice')) {
    function homeDiscountedPrice($product)
    {
        $lowest_price = $product->unit_price;
        $highest_price = $product->unit_price;

        if ($product->variant_product) {
            foreach ($product->stocks as $key => $stock) {
                if($lowest_price > $stock->price){
                    $lowest_price = $stock->price;
                }
                if($highest_price < $stock->price){
                    $highest_price = $stock->price;
                }
            }
        }

        $discount_applicable = false;

        if ($product->discount_start_date == null) {
            $discount_applicable = true;
        }
        elseif (strtotime(date('d-m-Y H:i:s')) >= $product->discount_start_date &&
            strtotime(date('d-m-Y H:i:s')) <= $product->discount_end_date) {
            $discount_applicable = true;
        }

        if ($discount_applicable) {
            if($product->discount_type == 'percent'){
                $lowest_price -= ($lowest_price*$product->discount)/100;
                $highest_price -= ($highest_price*$product->discount)/100;
            }
            elseif($product->discount_type == 'amount'){
                $lowest_price -= $product->discount;
                $highest_price -= $product->discount;
            }
        }

        foreach ($product->taxes as $product_tax) {
            if($product_tax->tax_type == 'percent'){
                $lowest_price += ($lowest_price * $product_tax->tax) / 100;
                $highest_price += ($highest_price * $product_tax->tax) / 100;
            }
            elseif($product_tax->tax_type == 'amount'){
                $lowest_price += $product_tax->tax;
                $highest_price += $product_tax->tax;
            }
        }

        $lowest_price = convertPrice($lowest_price);
        $highest_price = convertPrice($highest_price);

        return $lowest_price.' - '.$highest_price;
    }
}

if (! function_exists('brandsOfCategory')) {
    function brandsOfCategory($category_id)
    {
        $brands = [];
        $subCategories = SubCategory::where('category_id', $category_id)->get();
        foreach ($subCategories as $subCategory) {
            $subSubCategories = SubSubCategory::where('sub_category_id', $subCategory->id)->get();
            foreach ($subSubCategories as $subSubCategory) {
                $brand = json_decode($subSubCategory->brands);
                foreach ($brand as $b) {
                    if (in_array($b, $brands)) continue;
                    array_push($brands, $b);
                }
            }
        }
        return $brands;
    }
}

if (! function_exists('convertPrice')) {
    function convertPrice($price)
    {
        $business_settings = BusinessSetting::where('type', 'system_default_currency')->first();
        if ($business_settings != null) {
            $currency = Currency::find($business_settings->value);
            $price = floatval($price) / floatval($currency->exchange_rate);
        }
        $code = Currency::findOrFail(BusinessSetting::where('type', 'system_default_currency')->first()->value)->code;
        if (Session::has('currency_code')) {
            $currency = Currency::where('code', Session::get('currency_code', $code))->first();
        } else {
            $currency = Currency::where('code', $code)->first();
        }
        $price = floatval($price) * floatval($currency->exchange_rate);
        return $price;
    }
}


function translate($key, $lang = null){
    if($lang == null){
        $lang = App::getLocale();
    }

    $translation_def = Translation::where('lang', env('DEFAULT_LANGUAGE', 'en'))->where('lang_key', $key)->first();
    if($translation_def == null){
        $translation_def = new Translation;
        $translation_def->lang = env('DEFAULT_LANGUAGE', 'en');
        $translation_def->lang_key = $key;
        $translation_def->lang_value = $key;
        $translation_def->save();
    }

    //Check for session lang
    $translation_locale = Translation::where('lang_key', $key)->where('lang', $lang)->first();
    if($translation_locale != null && $translation_locale->lang_value != null){
        return $translation_locale->lang_value;
    }
    elseif($translation_def->lang_value != null){
        return $translation_def->lang_value;
    }
    else{
        return $key;
    }
}

function remove_invalid_charcaters($str)
{
    $str = str_ireplace(array("\\"), '', $str);
    return str_ireplace(array('"'), '\"', $str);
}

function getShippingCost($carts, $index){
    $admin_products = array();
    $seller_products = array();
    $calculate_shipping = 0;

    foreach ($carts as $key => $cartItem) {
        $product = \App\Product::find($cartItem['product_id']);
        if($product->added_by == 'admin'){
            array_push($admin_products, $cartItem['product_id']);
        }
        else{
            $product_ids = array();
            if(array_key_exists($product->user_id, $seller_products)){
                $product_ids = $seller_products[$product->user_id];
            }
            array_push($product_ids, $cartItem['product_id']);
            $seller_products[$product->user_id] = $product_ids;
        }
    }

    //Calculate Shipping Cost
    if (get_setting('shipping_type') == 'flat_rate') {
        $calculate_shipping = get_setting('flat_rate_shipping_cost');
    }
    elseif (get_setting('shipping_type') == 'seller_wise_shipping') {
        if(!empty($admin_products)){
            $calculate_shipping = get_setting('shipping_cost_admin');
        }
        if(!empty($seller_products)){
            foreach ($seller_products as $key => $seller_product) {
                $calculate_shipping += \App\Shop::where('user_id', $key)->first()->shipping_cost;
            }
        }
    }
    elseif (get_setting('shipping_type') == 'area_wise_shipping') {
        $shipping_info = Address::where('id', $carts[0]['address_id'])->first();
        $city = City::where('name', $shipping_info->city)->first();
        if($city != null){
            $calculate_shipping = $city->cost;
        }
    }

    $cartItem = $carts[$index];
    $product = \App\Product::find($cartItem['product_id']);

    if($product->digital == 1) {
        return $calculate_shipping = 0;
    }

    if (get_setting('shipping_type') == 'flat_rate') {
        return $calculate_shipping/count($carts);
    }
    elseif (get_setting('shipping_type') == 'seller_wise_shipping') {
        if($product->added_by == 'admin'){
            return get_setting('shipping_cost_admin')/count($admin_products);
        }
        else {
            return \App\Shop::where('user_id', $product->user_id)->first()->shipping_cost/count($seller_products[$product->user_id]);
        }
    }
    elseif (get_setting('shipping_type') == 'area_wise_shipping') {
        if($product->added_by == 'admin'){
            return $calculate_shipping/count($admin_products);
        }
        else {
            return $calculate_shipping/count($seller_products[$product->user_id]);
        }
    }
    else{
        return \App\Product::find($cartItem['product_id'])->shipping_cost;
    }
}

function timezones(){
    return Timezones::timezonesToArray();
}

if (!function_exists('app_timezone')) {
    function app_timezone()
    {
        return config('app.timezone');
    }
}

if (!function_exists('api_asset')) {
    function api_asset($id)
    {
        if (($asset = \App\Upload::find($id)) != null) {
            return $asset->file_name;
        }
        return "";
    }
}

//return file uploaded via uploader
if (!function_exists('uploaded_asset')) {
    function uploaded_asset($id)
    {
        if (($asset = \App\Upload::find($id)) != null) {
            return my_asset($asset->file_name);
        }
        return null;
    }
}

if (! function_exists('my_asset')) {
    /**
     * Generate an asset path for the application.
     *
     * @param  string  $path
     * @param  bool|null  $secure
     * @return string
     */
    function my_asset($path, $secure = null)
    {
        if(env('FILESYSTEM_DRIVER') == 's3'){
            return Storage::disk('s3')->url($path);
        }
        else {
            return app('url')->asset('public/'.$path, $secure);
        }
    }
}

if (! function_exists('static_asset')) {
    /**
     * Generate an asset path for the application.
     *
     * @param  string  $path
     * @param  bool|null  $secure
     * @return string
     */
    function static_asset($path, $secure = null)
    {
        return app('url')->asset('public/'.$path, $secure);
    }
}



if (!function_exists('isHttps')) {
    function isHttps()
    {
        if (!array_key_exists('HTTPS', $_SERVER)) {
            return false;
        }
        return !empty($_SERVER['HTTPS']) && ('on' == $_SERVER['HTTPS']);
    }
}

if (!function_exists('getBaseURL')) {
    function getBaseURL()
    {
        if (!array_key_exists('SCRIPT_NAME', $_SERVER) || !array_key_exists('HTTP_HOST', $_SERVER)) {
            return '';
        }
        $root = (isHttps() ? "https://" : "http://").$_SERVER['HTTP_HOST'];
        $root .= str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);

        return $root;
    }
}


if (!function_exists('getFileBaseURL')) {
    function getFileBaseURL()
    {
        if(env('FILESYSTEM_DRIVER') == 's3'){
            return env('AWS_URL').'/';
        }
        else {
            return getBaseURL().'public/';
        }
    }
}


if (! function_exists('isUnique')) {
    /**
     * Generate an asset path for the application.
     *
     * @param  string  $path
     * @param  bool|null  $secure
     * @return string
     */
    function isUnique($email)
    {
        $user = \App\User::where('email', $email)->first();

        if($user == null) {
            return '1'; // $user = null means we did not get any match with the email provided by the user inside the database
        } else {
            return '0';
        }
    }
}

if (!function_exists('get_setting')) {
    function get_setting($key, $default = null,$lang = false)
    {
        $settings = Cache::remember('business_settings', 86400, function(){
            return BusinessSetting::all();
        });

        if($lang == false){
            $setting = $settings->where('type', $key)->first();
        }else{
            $setting = $settings->where('type', $key)->where('lang',$lang)->first();
            $setting = !$setting ? $settings->where('type', $key)->first() : $setting;
        }
        return $setting == null ? $default : $setting->value;
    }
}

function hex2rgba($color, $opacity = false) {
    return Colorcodeconverter::convertHexToRgba($color, $opacity);
}

if (!function_exists('isAdmin')) {
    function isAdmin()
    {
        if (Auth::check() && (Auth::user()->user_type == 'admin' || Auth::user()->user_type == 'staff')) {
            return true;
        }
        return false;
    }
}

if (!function_exists('isSeller')) {
    function isSeller()
    {
        if (Auth::check() && Auth::user()->user_type == 'seller') {
            return true;
        }
        return false;
    }
}

if (!function_exists('isCustomer')) {
    function isCustomer()
    {
        if (Auth::check() && Auth::user()->user_type == 'customer') {
            return true;
        }
        return false;
    }
}

if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        // Uncomment one of the following alternatives
        $bytes /= pow(1024, $pow);
        // $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

// duplicates m$ excel's ceiling function
if( !function_exists('ceiling') )
{
    function ceiling($number, $significance = 1)
    {
        return ( is_numeric($number) && is_numeric($significance) ) ? (ceil($number/$significance)*$significance) : false;
    }
}

if (!function_exists('get_images')) {
    function get_images($given_ids, $with_trashed = false)
    {
        $ids = (is_array($given_ids)
            ? $given_ids
            : is_null($given_ids)) ? [] : explode(",", $given_ids);

        return $with_trashed
            ? Upload::withTrashed()->whereIn('id', $ids)->get()
            : Upload::whereIn('id', $ids)->get();
    }
}

//for api
if (!function_exists('get_images_path')) {
    function get_images_path($given_ids, $with_trashed = false)
    {
        $paths = [];
        $images = get_images($given_ids, $with_trashed);
        if (!$images->isEmpty()) {
            foreach ($images as $image) {
                $paths[] = !is_null($image) ? $image->file_name :"";
            }
        }

        return $paths;

    }
}

//for api
if (!function_exists('checkout_done')) {
    function checkout_done($order_id, $payment)
    {
        $order = Order::findOrFail($order_id);
        $order->payment_status = 'paid';
        $order->payment_details = $payment;
        $order->save();

        if (\App\Addon::where('unique_identifier', 'affiliate_system')->first() != null && \App\Addon::where('unique_identifier', 'affiliate_system')->first()->activated) {
            $affiliateController = new AffiliateController;
            $affiliateController->processAffiliatePoints($order);
        }

        if (\App\Addon::where('unique_identifier', 'club_point')->first() != null && \App\Addon::where('unique_identifier', 'club_point')->first()->activated) {
            if (Auth::check()) {
                $clubpointController = new ClubPointController;
                $clubpointController->processClubPoints($order);
            }
        }
        $vendor_commission_activation = true;
        if(\App\Addon::where('unique_identifier', 'seller_subscription')->first() != null
            && \App\Addon::where('unique_identifier', 'seller_subscription')->first()->activated
            && !get_setting('vendor_commission_activation')){
            $vendor_commission_activation = false;
        }

        if ($vendor_commission_activation) {
            if (BusinessSetting::where('type', 'category_wise_commission')->first()->value != 1) {
                $commission_percentage = BusinessSetting::where('type', 'vendor_commission')->first()->value;
                foreach ($order->orderDetails as $key => $orderDetail) {
                    $orderDetail->payment_status = 'paid';
                    $orderDetail->save();
                    if ($orderDetail->product->user->user_type == 'seller') {
                        $seller = $orderDetail->product->user->seller;
                        $seller->admin_to_pay = $seller->admin_to_pay + ($orderDetail->price * (100 - $commission_percentage)) / 100 + $orderDetail->tax + $orderDetail->shipping_cost;
                        $seller->save();
                    }
                }
            }
            else {
                foreach ($order->orderDetails as $key => $orderDetail) {
                    $orderDetail->payment_status = 'paid';
                    $orderDetail->save();
                    if ($orderDetail->product->user->user_type == 'seller') {
                        $commission_percentage = $orderDetail->product->category->commision_rate;
                        $seller = $orderDetail->product->user->seller;
                        $seller->admin_to_pay = $seller->admin_to_pay + ($orderDetail->price * (100 - $commission_percentage)) / 100 + $orderDetail->tax + $orderDetail->shipping_cost;
                        $seller->save();
                    }
                }
            }
        }
        else {
            foreach ($order->orderDetails as $key => $orderDetail) {
                $orderDetail->payment_status = 'paid';
                $orderDetail->save();
                if ($orderDetail->product->user->user_type == 'seller') {
                    $seller = $orderDetail->product->user->seller;
                    $seller->admin_to_pay = $seller->admin_to_pay + $orderDetail->price + $orderDetail->tax + $orderDetail->shipping_cost;
                    $seller->save();
                }
            }
        }

        $order->commission_calculated = 1;
        $order->save();
    }
}

//for api
if (!function_exists('wallet_payment_done')) {
    function wallet_payment_done($user_id, $amount, $payment_method, $payment_details)
    {
        $user = \App\User::find($user_id);
        $user->balance = $user->balance + $amount;
        $user->save();

        $wallet = new Wallet;
        $wallet->user_id = $user->id;
        $wallet->amount = $amount;
        $wallet->payment_method = $payment_method;
        $wallet->payment_details = $payment_details;
        $wallet->save();

    }
}

if (!function_exists('purchase_payment_done')) {
    function purchase_payment_done($user_id, $package_id)
    {
        $user = User::findOrFail($user_id);
        $user->customer_package_id = $package_id;
        $customer_package = CustomerPackage::findOrFail($package_id);
        $user->remaining_uploads += $customer_package->product_upload;
        $user->save();

        return 'success';

    }
}

//Commission Calculation
if (!function_exists('commission_calculation')) {
    function commission_calculation($order)
    {
        $vendor_commission_activation = true;
        if(\App\Addon::where('unique_identifier', 'seller_subscription')->first() != null
            && \App\Addon::where('unique_identifier', 'seller_subscription')->first()->activated
            && !get_setting('vendor_commission_activation')){
            $vendor_commission_activation = false;
        }

        if($vendor_commission_activation){
            if ($order->payment_type == 'cash_on_delivery') {
                foreach ($order->orderDetails as $key => $orderDetail) {
                    $orderDetail->payment_status = 'paid';
                    $orderDetail->save();
                    $commission_percentage = 0;
                    if (get_setting('category_wise_commission') != 1) {
                        $commission_percentage = get_setting('vendor_commission');
                    } else if ($orderDetail->product->user->user_type == 'seller') {
                        $commission_percentage = $orderDetail->product->category->commision_rate;
                    }
                    if ($orderDetail->product->user->user_type == 'seller') {
                        $seller = $orderDetail->product->user->seller;
                        $admin_commission = ($orderDetail->price * $commission_percentage) / 100;

                        if (get_setting('product_manage_by_admin') == 1) {
                            $seller_earning = ($orderDetail->tax + $orderDetail->price) - $admin_commission;
                            $seller->admin_to_pay += $seller_earning;
                        } else {
                            $seller_earning = ($orderDetail->tax + $orderDetail->shipping_cost + $orderDetail->price) - $admin_commission;
                            $seller->admin_to_pay = $seller->admin_to_pay - $admin_commission;
                        }

                        $seller->save();

                        $commission_history = new CommissionHistory;
                        $commission_history->order_id = $order->id;
                        $commission_history->order_detail_id = $orderDetail->id;
                        $commission_history->seller_id = $orderDetail->seller_id;
                        $commission_history->admin_commission = $admin_commission;
                        $commission_history->seller_earning = $seller_earning;

                        $commission_history->save();
                    }
                }
            }
            elseif ($order->manual_payment) {
                foreach ($order->orderDetails as $key => $orderDetail) {
                    $orderDetail->payment_status = 'paid';
                    $orderDetail->save();
                    $commission_percentage = 0;
                    if (get_setting('category_wise_commission') != 1) {
                        $commission_percentage = BusinessSetting::where('type', 'vendor_commission')->first()->value;
                    } else if ($orderDetail->product->user->user_type == 'seller') {
                        $commission_percentage = $orderDetail->product->category->commision_rate;
                    }
                    if ($orderDetail->product->user->user_type == 'seller') {
                        $seller = $orderDetail->product->user->seller;
                        $admin_commission = ($orderDetail->price * $commission_percentage) / 100;

                        if (get_setting('product_manage_by_admin') == 1) {
                            $seller_earning = ($orderDetail->tax + $orderDetail->price) - $admin_commission;
                            $seller->admin_to_pay += $seller_earning;
                        } else {
                            $seller_earning = ($orderDetail->tax + $orderDetail->shipping_cost + $orderDetail->price) - $admin_commission;
                            $seller->admin_to_pay += $seller_earning;
                        }

                        $seller->save();

                        $commission_history = new CommissionHistory;
                        $commission_history->order_id = $order->id;
                        $commission_history->order_detail_id = $orderDetail->id;
                        $commission_history->seller_id = $orderDetail->seller_id;
                        $commission_history->admin_commission = $admin_commission;
                        $commission_history->seller_earning = $seller_earning;

                        $commission_history->save();
                    }
                }
            }
        }

        if (\App\Addon::where('unique_identifier', 'affiliate_system')->first() != null && \App\Addon::where('unique_identifier', 'affiliate_system')->first()->activated) {
            $affiliateController = new AffiliateController;
            $affiliateController->processAffiliatePoints($order);
        }

        if (\App\Addon::where('unique_identifier', 'club_point')->first() != null && \App\Addon::where('unique_identifier', 'club_point')->first()->activated) {
            if ($order->user != null) {
                $clubpointController = new ClubPointController;
                $clubpointController->processClubPoints($order);
            }
        }
    }
}

//Send Notification
if (!function_exists('send_notification')) {
    function send_notification($order, $order_status) {
        if($order->seller_id == \App\User::where('user_type', 'admin')->first()->id) {
            $users = User::findMany([Auth::user()->id, $order->seller_id]);
        } else {
            $users = User::findMany([Auth::user()->id, $order->seller_id, \App\User::where('user_type', 'admin')->first()->id]);
        }

        $order_notification = array();
        $order_notification['order_id']     = $order->id;
        $order_notification['order_code']   = $order->code;
        $order_notification['user_id']      = $order->user_id;
        $order_notification['seller_id']    = $order->seller_id;
        $order_notification['status']       = $order_status;

        Notification::send($users, new OrderNotification($order_notification));
    }
}

if (!function_exists('send_firebase_notification')) {
    function send_firebase_notification($req) {
        $url = 'https://fcm.googleapis.com/fcm/send';
        $dataArr = array(
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'id' => $req->id,
            'status' => "done"
        );
        $notification = array(
            'title' => $req->title,
            'text' => $req->body,
            'image' => $req->img,
            'sound' => 'default',
            'badge' => '1',
        );
        $arrayToSend = array(
            'to' => "/topics/all",
            'notification' => $notification,
            'data' => $dataArr,
            'priority' => 'high'
        );

        $fields = json_encode($arrayToSend);
        $headers = array(
            'Authorization: key=' . env('FCM_SERVER_KEY'),
            'Content-Type: application/json'
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

        $result = curl_exec($ch);
        //var_dump($result);
        curl_close($ch);
        return $result;
    }
}

?>
