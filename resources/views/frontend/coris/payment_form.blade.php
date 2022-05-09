@extends('frontend.layouts.app')

@section('content')
    <section class="pt-5 mb-4">
        <div class="container">
            <div class="row">
                <div class="col-xl-8 mx-auto">
                    <div class="row aiz-steps arrow-divider">
                        <div class="col done">
                            <div class="text-center text-success">
                                <i class="la-3x mb-2 las la-shopping-cart"></i>
                                <h3 class="fs-14 fw-600 d-none d-lg-block">{{ translate('1. My Cart')}}</h3>
                            </div>
                        </div>
                        <div class="col done">
                            <div class="text-center text-success">
                                <i class="la-3x mb-2 las la-map"></i>
                                <h3 class="fs-14 fw-600 d-none d-lg-block">{{ translate('2. Shipping info')}}</h3>
                            </div>
                        </div>
                        <div class="col done">
                            <div class="text-center text-success">
                                <i class="la-3x mb-2 las la-truck"></i>
                                <h3 class="fs-14 fw-600 d-none d-lg-block">{{ translate('3. Delivery info')}}</h3>
                            </div>
                        </div>
                        <div class="col active">
                            <div class="text-center text-primary">
                                <i class="la-3x mb-2 las la-credit-card"></i>
                                <h3 class="fs-14 fw-600 d-none d-lg-block">{{ translate('4. Payment')}}</h3>
                            </div>
                        </div>
                        <div class="col">
                            <div class="text-center">
                                <i class="la-3x mb-2 opacity-50 las la-check-circle"></i>
                                <h3 class="fs-14 fw-600 d-none d-lg-block opacity-50">{{ translate('5. Confirmation')}}</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="mb-4">
        <div class="container text-left">
            <div class="row">
                <div class="col-lg-12">
                    <form action="{{ route('coris.pay') }}" class="form-default" role="form" method="POST" id="payment-form">
                        @csrf
                        <div class="card shadow-sm border-0 rounded">
                            <div class="card-header p-3">
                                <h3 class="fs-16 fw-600 mb-0">
                                    {{ translate('Pay by')}} Coris money
                                </h3>
                            </div>
                            <div class="card-body">
                                <h2>{{ translate('How to pay by') }} Coris money</h2>
                                <p>
                                    {{ str_replace("{amount}", number_format($order->grand_total, 0, '', ' '), translate("You owe {amount} FCFA, pay easily with Coris money")) }}
                                </p>
                                <div class="alert alert-block alert-danger" id="error-msg">
                                    {{ translate("Please enter a valid phone number") }}
                                </div>
                                <form action="{{ route('coris.pay') }}"
                                      method="post" class = "form-horizontal"
                                      id="payment-form">
                                    <div id="init-part">
                                        <div class = "form-group" style = "width:40%;display:inline-block">
                                            <label>{{ translate("Your Coris money account phone number(no spaces or dashes)") }}</label>
                                            <input type = "text" name = "phone_number" id = "phone_number" class = "form-control"><br/>
                                        </div>
                                        <div class = "form-group" style = "width:40%;display:inline-block">
                                            <label>{{ translate("Your Coris money PIN code") }}</label>
                                            <input type = "password" name = "code_pin" id = "code_pin" class = "form-control"><br/>
                                        </div>
                                        <div>
                                            <button class = "btn btn-primary fw-600" id="init-btn" disabled type="button">
                                                {{ translate('Validate') }}
                                            </button>
                                        </div>
                                    </div>
                                    <div id = "payment-part" style="display:none">
                                        <p style='font-weight:bold;color:red;font-size:.9rem'>
                                            {{ translate('You will receive a message containing a code called "operation code or OTP", please enter it in the field below') }}
                                        </p>
                                        <div class = "form-group" style = "width:40%;display:inline-block">
                                            <label>{{ translate('OTP code (received by SMS)') }}</label>
                                            <input type = "password" name = "otp" id = "otp" class = "form-control"><br/>
                                        </div>
                                        <div>
                                            <button type="submit" class = "btn btn-primary fw-600" id = "validate_button" disabled>
                                                {{ translate('Complete order') }}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="row align-items-center pt-3">
                            <div class="col-6">
                                <a href="{{ route('home') }}" class="link link--style-3">
                                    <i class="las la-arrow-left"></i>
                                    {{ translate('Return to shop')}}
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('script')
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        var initMode = true;
        var invalidCode = "{{ translate('The code is invalid') }}"
        var invalidPhone = "{{ translate('Phone number is not valid') }}"

        $('#validate_button').attr('disabled' , 'disabled');

        $('#code_pin,#phone_number,#otp').keyup(function(event) {
            validate_code();
        })

        function validate_code(){
            if(initMode) {
                var userInput = $('#code_pin').val();
                var errorMessage = null;
                var regexp = /^[0-9]{4}$/;

                if (!userInput.match(regexp)) {
                    errorMessage = invalidCode;
                }
                else {
                    errorMessage = null;
                }
                if(!errorMessage) {
                    userInput = $('#phone_number').val();
                    var regexp = /^[0-9]{8}$/;
                    if (!userInput.match(regexp)) {
                        errorMessage = invalidPhone;
                    }
                    else {
                        errorMessage = null;
                    }
                }
            }
            else {
                userInput = $('#otp').val();
                var regexp = /^[0-9]{8}$/;
                if (!userInput.match(regexp)) {
                    errorMessage = invalidCode;
                }
                else {
                    errorMessage = null;
                }
            }
            handleMessageVisibility(errorMessage);
        }

        function handleMessageVisibility(errorMessage) {
            if(errorMessage) {
                $('#error-msg').html(errorMessage);
                $('#validate_button').attr('disabled' , 'disabled');
                $('#init-btn').attr('disabled' , 'disabled');
                $('#error-msg').show();
            }
            else {
                $('#validate_button').removeAttr('disabled');
                $('#init-btn').removeAttr('disabled');
                $('#error-msg').hide();
            }
        }

        $('#init-btn').click(function(e) {
            e.preventDefault();
            HoldOn.open({
                theme: "sk-circle",
                message: "<h4>{{ translate('Please wait, do not close or refresh the page') }}</h4>"
            });
            var url = "{{ route('coris.init') }}";
            $.ajax({
                url: url,
                data: $('#payment-form').serialize(),
                method: 'POST'
            })
                .then(function(data) {
                    data = jQuery.parseJSON(data);
                    HoldOn.close();
                    if(!data.success) {
                        Swal.fire({
                            title: 'Erreur',
                            text: data.message,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        })
                    }
                    else {
                        $('#payment-part').show();
                        $('#init-part').hide();
                        initMode = false;
                        $('#error-msg').html('{{ translate("Please enter a valid operation code number") }}');
                        $('#error-msg').show();
                    }
                })
                .fail(function() {
                    HoldOn.close();
                    Swal.fire({
                        title: 'Erreur',
                        text: "{{ translate('An unexpected error occurred, please try again later') }}",
                        icon: 'error',
                        confirmButtonText: 'OK'
                    })
                });
        })

        $('#payment-form').submit(function(e) {
            e.preventDefault();
            if(initMode) {
                return;
            }
            HoldOn.open({
                theme: "sk-circle",
                message: "<h4>{{ translate('Please wait, do not close or refresh the page') }}</h4>"
            });
            var url = $(this).attr('action');
            $.ajax({
                url: url,
                data: $(this).serialize(),
                method: 'POST'
            })
                .then(function(data) {
                    data = jQuery.parseJSON(data);
                    HoldOn.close();
                    if(!data.success) {
                        Swal.fire({
                            title: 'Erreur',
                            text: data.message,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        })
                    }
                    else {
                        document.location.href = data.url;
                    }
                })
                .fail(function() {
                    HoldOn.close();
                    Swal.fire({
                        title: 'Erreur',
                        text: "{{ translate('An unexpected error occurred, please try again later') }}",
                        icon: 'error',
                        confirmButtonText: 'OK'
                    })
                });
        })
    </script>
@endsection
