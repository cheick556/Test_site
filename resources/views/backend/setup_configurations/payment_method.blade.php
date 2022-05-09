    @extends('backend.layouts.app')

    @section('content')

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0 h6 ">{{translate('Orange money Credential')}}</h5>
                </div>
                <div class="card-body">
                    <form class="form-horizontal" action="{{ route('payment_method.update') }}" method="POST">
                        <input type="hidden" name="payment_method" value="orange">
                        @csrf
                        <div class="form-group row">
                            <input type="hidden" name="types[]" value="ORANGE_MONEY_MERCHANT_ID">
                            <div class="col-md-4">
                                <label class="col-from-label">{{translate('Merchant ID')}}</label>
                            </div>
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="ORANGE_MONEY_MERCHANT_ID" value="{{  env('ORANGE_MONEY_MERCHANT_ID') }}" placeholder="{{ translate('Merchant ID') }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <input type="hidden" name="types[]" value="ORANGE_MONEY_MERCHANT_NUMBER">
                            <div class="col-md-4">
                                <label class="col-from-label">{{translate('Merchant number')}}</label>
                            </div>
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="ORANGE_MONEY_MERCHANT_NUMBER" value="{{  env('ORANGE_MONEY_MERCHANT_NUMBER') }}" placeholder="{{ translate('Merchant Number') }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <input type="hidden" name="types[]" value="ORANGE_MONEY_MERCHANT_PASSWORD">
                            <div class="col-md-4">
                                <label class="col-from-label">{{translate('Merchant password')}}</label>
                            </div>
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="ORANGE_MONEY_MERCHANT_PASSWORD" value="{{  env('ORANGE_MONEY_MERCHANT_PASSWORD') }}" placeholder="{{ translate('Merchant password') }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <input type="hidden" name="types[]" value="ORANGE_MONEY_IS_TEST_MODE">
                            <div class="col-md-4">
                                <label class="col-from-label">{{translate('Sandbox Mode')}}</label>
                            </div>
                            <div class="col-md-8">
                                <select name="ORANGE_MONEY_IS_TEST_MODE" class="form-control">
                                    <option value="1">{{translate('Yes')}}</option>
                                    <option @if(env('ORANGE_MONEY_IS_TEST_MODE') == 0) selected @endif value="0">{{translate('No')}}</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group mb-0 text-right">
                            <button type="submit" class="btn btn-sm btn-primary">{{translate('Save')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0 h6 ">{{translate('Coris money Credential')}}</h5>
                </div>
                <div class="card-body">
                    <form class="form-horizontal" action="{{ route('payment_method.update') }}" method="POST">
                        <input type="hidden" name="payment_method" value="coris">
                        @csrf
                        <div class="form-group row">
                            <input type="hidden" name="types[]" value="CORIS_MONEY_MERCHANT_ID">
                            <div class="col-md-4">
                                <label class="col-from-label">{{translate('Merchant ID')}}</label>
                            </div>
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="CORIS_MONEY_MERCHANT_ID" value="{{  env('CORIS_MONEY_MERCHANT_ID') }}" placeholder="{{ translate('Merchant ID') }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <input type="hidden" name="types[]" value="CORIS_MONEY_MERCHANT_PASSWORD">
                            <div class="col-md-4">
                                <label class="col-from-label">{{translate('Merchant password')}}</label>
                            </div>
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="CORIS_MONEY_MERCHANT_PASSWORD" value="{{  env('CORIS_MONEY_MERCHANT_PASSWORD') }}" placeholder="{{ translate('Merchant password') }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <input type="hidden" name="types[]" value="CORIS_MONEY_IS_TEST_MODE">
                            <div class="col-md-4">
                                <label class="col-from-label">{{translate('Sandbox Mode')}}</label>
                            </div>
                            <div class="col-md-8">
                                <select name="CORIS_MONEY_IS_TEST_MODE" class="form-control">
                                    <option value="1">{{translate('Yes')}}</option>
                                    <option @if(env('CORIS_MONEY_IS_TEST_MODE') == 0) selected @endif value="0">{{translate('No')}}</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group mb-0 text-right">
                            <button type="submit" class="btn btn-sm btn-primary">{{translate('Save')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0 h6 ">{{translate('Moov money Credential')}}</h5>
                </div>
                <div class="card-body">
                    <form class="form-horizontal" action="{{ route('payment_method.update') }}" method="POST">
                        <input type="hidden" name="payment_method" value="moov">
                        @csrf
                        <div class="form-group row">
                            <input type="hidden" name="types[]" value="MOOV_MONEY_MERCHANT_ID">
                            <div class="col-md-4">
                                <label class="col-from-label">{{translate('Merchant ID')}}</label>
                            </div>
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="MOOV_MONEY_MERCHANT_ID" value="{{  env('MOOV_MONEY_MERCHANT_ID') }}" placeholder="{{ translate('Merchant ID') }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <input type="hidden" name="types[]" value="MOOV_MONEY_MERCHANT_PASSWORD">
                            <div class="col-md-4">
                                <label class="col-from-label">{{translate('Merchant password')}}</label>
                            </div>
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="MOOV_MONEY_MERCHANT_PASSWORD" value="{{  env('MOOV_MONEY_MERCHANT_PASSWORD') }}" placeholder="{{ translate('Merchant password') }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <input type="hidden" name="types[]" value="MOOV_MONEY_IS_TEST_MODE">
                            <div class="col-md-4">
                                <label class="col-from-label">{{translate('Sandbox Mode')}}</label>
                            </div>
                            <div class="col-md-8">
                                <select name="MOOV_MONEY_IS_TEST_MODE" class="form-control">
                                    <option value="1">{{translate('Yes')}}</option>
                                    <option @if(env('MOOV_MONEY_IS_TEST_MODE') == 0) selected @endif value="0">{{translate('No')}}</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group mb-0 text-right">
                            <button type="submit" class="btn btn-sm btn-primary">{{translate('Save')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0 h6 ">{{translate('Paypal Credential')}}</h5>
                </div>
                <div class="card-body">
                    <form class="form-horizontal" action="{{ route('payment_method.update') }}" method="POST">
                        <input type="hidden" name="payment_method" value="paypal">
                        @csrf
                        <div class="form-group row">
                            <input type="hidden" name="types[]" value="PAYPAL_CLIENT_ID">
                            <div class="col-md-4">
                                <label class="col-from-label">{{translate('Paypal Client Id')}}</label>
                            </div>
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="PAYPAL_CLIENT_ID" value="{{  env('PAYPAL_CLIENT_ID') }}" placeholder="{{ translate('Paypal Client ID') }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <input type="hidden" name="types[]" value="PAYPAL_CLIENT_SECRET">
                            <div class="col-md-4">
                                <label class="col-from-label">{{translate('Paypal Client Secret')}}</label>
                            </div>
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="PAYPAL_CLIENT_SECRET" value="{{  env('PAYPAL_CLIENT_SECRET') }}" placeholder="{{ translate('Paypal Client Secret') }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-4">
                                <label class="col-from-label">{{translate('Paypal Sandbox Mode')}}</label>
                            </div>
                            <div class="col-md-8">
                                <label class="aiz-switch aiz-switch-success mb-0">
                                    <input value="1" name="paypal_sandbox" type="checkbox" @if (get_setting('paypal_sandbox') == 1)
                                        checked
                                    @endif>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>
                        <div class="form-group mb-0 text-right">
                            <button type="submit" class="btn btn-sm btn-primary">{{translate('Save')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0 h6 ">{{translate('Stripe Credential')}}</h5>
                </div>
                <div class="card-body">
                    <form class="form-horizontal" action="{{ route('payment_method.update') }}" method="POST">
                        @csrf
                        <input type="hidden" name="payment_method" value="stripe">
                        <div class="form-group row">
                            <input type="hidden" name="types[]" value="STRIPE_KEY">
                            <div class="col-md-4">
                                <label class="col-from-label">{{translate('Stripe Key')}}</label>
                            </div>
                            <div class="col-md-8">
                            <input type="text" class="form-control" name="STRIPE_KEY" value="{{  env('STRIPE_KEY') }}" placeholder="{{ translate('STRIPE KEY') }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <input type="hidden" name="types[]" value="STRIPE_SECRET">
                            <div class="col-md-4">
                                <label class="col-from-label">{{translate('Stripe Secret')}}</label>
                            </div>
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="STRIPE_SECRET" value="{{  env('STRIPE_SECRET') }}" placeholder="{{ translate('STRIPE SECRET') }}" required>
                            </div>
                        </div>
                        <div class="form-group mb-0 text-right">
                            <button type="submit" class="btn btn-sm btn-primary">{{translate('Save')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @endsection
