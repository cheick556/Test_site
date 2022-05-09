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
                    <form action="{{ route('orange.pay') }}" class="form-default" role="form" method="POST" id="payment-form">
                        @csrf
                        <div class="card shadow-sm border-0 rounded">
                            <div class="card-header p-3">
                                <h3 class="fs-16 fw-600 mb-0">
                                    {{ translate('Pay by')}} Orange money
                                </h3>
                            </div>
                            <div class="card-body">
                                <h2>{{ translate('How to pay by') }} Orange money</h2>
                                <p>
                                    {{ str_replace("{amount}", number_format($order->grand_total, 0, '', ' '), translate("You owe {amount} FCFA, pay by Orange money by doing:")) }}
                                    <strong style="color: red; font-size: 1.2em;">
                                        {{ str_replace("{amount}", (int)$order->grand_total, translate("*144*4*6*{amount}*secret_code#")) }}
                                    </strong>
                                </p>
                                <p style="color:red;font-size: 1.2em;">
                                    {{ translate("(If you don't have an account you can go to an Orange money agent)") }}
                                </p>
                                <p style='font-weight:bold'>
                                    {{ translate("You will then receive a message containing a code called OTP, please enter it and the phone number in the fields below") }}
                                </p>
                                <div class="alert alert-block alert-danger" id="error-msg">
                                    {{ translate("Please enter a valid OTP number") }}
                                </div>
                                <form action="index.php?fc=module&module=orangemoneypayment&controller=validation"
                                      method="post" class = "form-horizontal"
                                      id="payment-form">
                                    <div>
                                        <div class = "form-group" style = "width:40%;display:inline-block">
                                            <label>{{ translate('OTP code (received by SMS)') }}</label>
                                            <input type = "password" name = "otp" id = "otp" class = "form-control"><br/>
                                        </div>
                                        <div class = "form-group" style = "width:40%;display:inline-block">
                                            <label>{{ translate("Telephone number used to create the OTP (no spaces or dashes)") }}</label>
                                            <input type = "text" name = "phone_number" id = "phone_number" class = "form-control"><br/>
                                        </div>
                                        <div>
                                            <button class = "btn btn-primary fw-600" id = "validate_button">
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
        $('#validate_button').attr('disabled' , 'disabled');

        $('#otp,#phone_number').keyup(function(event) {
            validate_code();
        })

        function validate_code(){
            var invalidOtp = "{{ translate('OTP is invalid') }}"
            var invalidPhone = "{{ translate('Phone number is not valid') }}"
            var userInput = $('#otp').val();
            var errorMessage = null;
            var regexp = /^[0-9]{6}$/;
            if (!userInput.match(regexp)) {
                errorMessage = invalidOtp;
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
            handleMessageVisibility(errorMessage);
        }

        function handleMessageVisibility(errorMessage) {
            if(errorMessage) {
                $('#error-msg').html(errorMessage);
                $('#validate_button').attr('disabled' , 'disabled');
                $('#error-msg').show();
            }
            else {
                $('#validate_button').removeAttr('disabled');
                $('#error-msg').hide();
            }
        }

        $('#payment-form').submit(function(e) {
            e.preventDefault();
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
                    console.log(data);
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
                .fail(function(err) {
                    console.log(err)
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
