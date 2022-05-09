@extends('frontend.layouts.app')

@section('content')

<section class="gry-bg py-4 profile">
    <div class="container-fluid">
        <form class="" action="" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="row gutters-10">
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-body">
                            <div class="mb-2">
                                <input class="form-control form-control-sm" type="text" name="keyword" placeholder="Search by Product Name/Barcode" onkeyup="filterProducts()">
                            </div>
                            <div class="row gutters-5">
                                <div class="col-6">
                                    <div class="">
                                        <div class="form-group">
                                            <select name="poscategory" class="form-control form-control-sm aiz-selectpicker" data-live-search="true" onchange="filterProducts()">
                                                <option value="">{{ translate('All Categories') }}</option>
                                                @foreach (\App\Category::all() as $key => $category)
                                                    <option value="category-{{ $category->id }}">{{ $category->getTranslation('name') }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="">
                                        <div class="form-group">
                                            <select name="brand" class="form-control form-control-sm aiz-selectpicker" data-live-search="true" onchange="filterProducts()">
                                                <option value="">{{ translate('All Brands') }}</option>
                                                @foreach (\App\Brand::all() as $key => $brand)
                                                    <option value="{{ $brand->id }}">{{ $brand->getTranslation('name') }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="aiz-pos-product-list c-scrollbar-light">
                                <div class="row gutters-5" id="product-list">

                                </div>
                                <div id="load-more">
                                    <p class="text-center fs-14 fw-600 p-2 bg-soft-primary c-pointer" onclick="loadMoreProduct()">{{ translate('Load More') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex">
                                <div class="flex-grow-1">
                                    <select name="user_id" class="form-control form-control-sm pos-customer aiz-selectpicker" data-live-search="true" onchange="getShippingAddress()">
                                        <option value="">{{translate('Walk In Customer')}}</option>
                                        @foreach (\App\Customer::all() as $key => $customer)
                                            @if ($customer->user)
                                                <option value="{{ $customer->user->id }}" data-contact="{{ $customer->user->email }}">{{ $customer->user->name }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" class="btn btn-icon btn-soft-dark ml-3" data-target="#new-customer" data-toggle="modal">
									<i class="las la-truck"></i>
								</button>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-3" id="cart-details">
                        <div class="card-body">
                            <div class="aiz-pos-cart-list c-scrollbar">
                                <table class="table aiz-table mb-0" cellspacing="0" width="100%">
                                    <thead>
                                        <tr>
                                            <th width="50%">{{translate('Product')}}</th>
                                            <th width="15%">{{translate('QTY')}}</th>
                                            <th>{{translate('Price')}}</th>
                                            <th>{{translate('Subtotal')}}</th>
                                            <th class="text-right">{{translate('Remove')}}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $subtotal = 0;
                                            $tax = 0;
                                            $shipping = 0;
                                        @endphp
                                        @if (Session::has('posCart'))
                                            @forelse (Session::get('posCart') as $key => $cartItem)
                                                @php
                                                    $subtotal += $cartItem['price']*$cartItem['quantity'];
                                                    $tax += $cartItem['tax']*$cartItem['quantity'];
                                                    $shipping += $cartItem['shipping']*$cartItem['quantity'];
                                                    if(Session::get('shipping', 0) == 0){
                                                        $shipping = 0;
                                                    }
                                                @endphp
                                                <tr>
                                                    <td>
                                                        <span class="media">
                                                            <img class="mr-3" height="60" src="{{ uploaded_asset(\App\Product::find($cartItem['id'])->thumbnail_img) }}" >
                                                            <div class="media-body">
                                                                {{ \App\Product::find($cartItem['id'])->name }} ({{ $cartItem['variant'] }})
                                                            </div>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="input-group mb-3">
                                                            <input type="number" class="form-control px-0 text-center" placeholder="1" id="qty-{{ $key }}" value="{{ $cartItem['quantity'] }}" onchange="updateQuantity({{ $key }})" min="1">
                                                        </div>
                                                    </td>
                                                    <td>{{ single_price($cartItem['price']) }}</td>
                                                    <td>{{ single_price($cartItem['price']*$cartItem['quantity']) }}</td>
                                                    <td class="text-right">
                                                        <button class="btn btn-circle btn-danger btn-xs" type="button" onclick="removeFromCart({{ $key }})">
                                                            <i class="las la-trash-alt"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center">
                                                        <img src="{{ asset('img/nothing-found.jpg') }}" class="img-fit" height="150">
                                                        <p>{{ translate('No Product Added') }}</p>
                                                    </td>
                                                </tr>
                                            @endforelse
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer bord-top">
                            <table class="table aiz-table mb-0" cellspacing="0" width="100%">
                                <thead>
                                    <tr>
                                        <th class="text-center">{{translate('Sub Total')}}</th>
                                        <th class="text-center">{{translate('Total Tax')}}</th>
                                        <th class="text-center">{{translate('Total Shipping')}}</th>
                                        <th class="text-center">{{translate('Discount')}}</th>
                                        <th class="text-center">{{translate('Total')}}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="text-center">{{ single_price($subtotal) }}</td>
                                        <td class="text-center">{{ single_price($tax) }}</td>
                                        <td class="text-center">{{ single_price($shipping) }}</td>
                                        <td class="text-center">{{ single_price(Session::get('pos_discount', 0)) }}</td>
                                        <td class="text-center">{{ single_price($subtotal+$tax+$shipping - Session::get('pos_discount', 0)) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="pos-footer mar-btm">
                        <div class="d-flex justify-content-between">
                            <div class="d-flex">
                                <div class="dropdown mr-3 dropup">
                                    <button class="btn btn-outline-primary btn-styled dropdown-toggle" type="button" data-toggle="dropdown">
                                        {{translate('Shipping')}}
                                    </button>
                                    <div class="dropdown-menu p-3 dropdown-menu-lg">
                                        <div class="radio radio-inline">
                                            <input type="radio" name="shipping" id="radioExample_2a" value="0" checked onchange="setShipping()">
                                            <label for="radioExample_2a">{{translate('Without Shipping Charge')}}</label>
                                        </div>

                                        <div class="radio radio-inline">
                                            <input type="radio" name="shipping" id="radioExample_2b" value="1" onchange="setShipping()">
                                            <label for="radioExample_2b">{{translate('With Shipping Charge')}}</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="dropdown dropup">
                                    <button class="btn btn-outline-primary btn-styled dropdown-toggle" type="button" data-toggle="dropdown">
                                        {{translate('Discount')}}
                                    </button>
                                    <div class="dropdown-menu p-3 dropdown-menu-lg">
                                        <div class="input-group">
                                            <input type="number" min="0" placeholder="Amount" name="discount" class="form-control" value="{{ Session::get('pos_discount', 0) }}" required onchange="setDiscount()">
                                            <div class="input-group-append">
                                                <span class="input-group-text">{{ translate('Flat') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="">
                                <button type="button" class="btn btn-styled btn-success" data-target="#order-confirm" data-toggle="modal">{{translate('Pay With Cash')}}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

@endsection

@section('modal')
    <!-- Address Modal -->
    <div id="new-customer" class="modal fade" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-dialog-zoom" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title h6">{{ translate('Shipping Address') }}</h4>
                    <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body" id="shipping_address">


                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal" id="close-button">{{translate('Close')}}</button>
                    <button type="button" class="btn btn-primary" data-dismiss="modal">{{translate('Confirm')}}</button>
                </div>
            </div>
        </div>
    </div>

    <!-- new address modal -->
    <div id="new-address-modal" class="modal fade" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-dialog-zoom" role="document">
            <div class="modal-content">
                <div class="modal-header bord-btm">
                    <h4 class="modal-title h6">{{translate('Shipping Address')}}</h4>
                    <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>
                </div>
                <form class="form-horizontal" action="{{ route('addresses.store') }}" method="POST" enctype="multipart/form-data">
                	@csrf
                    <div class="modal-body">
                        <input type="hidden" name="customer_id" id="set_customer_id" value="">
                        <div class="form-group">
                            <div class=" row">
                                <label class="col-sm-2 control-label" for="address">{{translate('Address')}}</label>
                                <div class="col-sm-10">
                                    <textarea placeholder="{{translate('Address')}}" id="address" name="address" class="form-control" required></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class=" row">
                                <label class="col-sm-2 control-label" for="email">{{translate('Country')}}</label>
                                <div class="col-sm-10">
                                    <select name="country" id="country" class="form-control selectpicker" data-live-search="true" required data-placeholder="{{translate('Select country')}}">
                                        @foreach (\App\Country::where('status',1)->get() as $key => $country)
                                            <option value="{{ $country->name }}">{{ $country->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class=" row">
                                <label class="col-sm-2 control-label" for="city">{{translate('City')}}</label>
                                <div class="col-sm-10">
                                    <input type="text" placeholder="{{translate('City')}}" id="city" name="city" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class=" row">
                                <label class="col-sm-2 control-label" for="postal_code">{{translate('Postal code')}}</label>
                                <div class="col-sm-10">
                                    <input type="number" min="0" placeholder="{{translate('Postal code')}}" id="postal_code" name="postal_code" class="form-control" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                                <div class=" row">
                                    <label class="col-sm-2 control-label" for="phone">{{translate('Phone')}}</label>
                                    <div class="col-sm-10">
                                        <input type="number" min="0" placeholder="{{translate('Phone')}}" id="phone" name="phone" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-dismiss="modal">{{translate('Close')}}</button>
                        <button type="submit" class="btn btn-primary">{{translate('Save')}}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="product-variation" class="modal fade">
        <div class="modal-dialog modal-dialog-centered modal-dialog-zoom modal-lg">
            <div class="modal-content" id="variants">

            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->

    <div id="order-confirm" class="modal fade">
        <div class="modal-dialog modal-dialog-centered modal-dialog-zoom">
            <div class="modal-content" id="variants"><div class="modal-header">
                    <h4 class="modal-title h6">{{translate('Order Confirmation')}}</h4>
                    <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">×</span></button>
                </div>
                <div class="modal-body">
                    <h4>{{translate('Are you sure to confirm this order?')}}</h4>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">{{translate('Close')}}</button>
                    <button type="button" onclick="submitOrder('cash')" class="btn btn-primary">{{translate('Comfirm Order')}}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script type="text/javascript">

        var products = null;

        $(document).ready(function(){
            $('#product-list').on('click','.product-card',function(){
                var id = $(this).data('id');
                $.get('{{ route('variants') }}', {id:id}, function(data){
                    if (data == 0) {
                        addToCart(id, null, 1);
                    }
                    else {
                        $('#variants').html(data);
                        $('#product-variation').modal('show');
                    }
                });
            });
            filterProducts();
            getShippingAddress();
        });

        function filterProducts(){
            var keyword = $('input[name=keyword]').val();
            var category = $('select[name=poscategory]').val();
            var brand = $('select[name=brand]').val();
            $.get('{{ route('pos.search_product') }}',{keyword:keyword, category:category, brand:brand}, function(data){
                products = data;
                $('#product-list').html(null);
                setProductList(data);
            });
        }

        function loadMoreProduct(){
            if(products != null && products.links.next != null){
                $.get(products.links.next,{}, function(data){
                    products = data;
                    setProductList(data);
                });
            }
        }

        function setProductList(data){
            for (var i = 0; i < data.data.length; i++) {
                $('#product-list').append('<div class="col-3">' +
                    '<div class="card bg-light c-pointer mb-2 product-card" data-id="'+data.data[i].id+'" >'+
                        '<span class="absolute-top-left bg-dark text-white px-1">'+data.data[i].price +'</span>'+
                        '<img src="'+ data.data[i].thumbnail_image +'" class="card-img-top img-fit h-100px mw-100 mx-auto">'+
                        '<div class="card-body p-2">'+
                            '<div class="text-truncate-2 small">'+ data.data[i].name +'</div>'+
                        '</div>'+
                    '</div>'+
                '</div>');
            }
            if (data.links.next != null) {
                $('#load-more').find('.text-center').html('Load More');
            }
            else {
                $('#load-more').find('.text-center').html('Nothing more found');
            }
            $('[data-toggle="tooltip"]').tooltip();
        }

        function removeFromCart(key){
            $.post('{{ route('pos.removeFromCart') }}', {_token:'{{ csrf_token() }}', key:key}, function(data){
                $('#cart-details').html(data);
                $('#product-variation').modal('hide');
            });
        }

        function addToCart(product_id, variant, quantity){
            $.post('{{ route('pos.addToCart') }}',{_token:'{{ csrf_token() }}', product_id:product_id, variant:variant, quantity, quantity}, function(data){
                $('#cart-details').html(data);
                $('#product-variation').modal('hide');
            });
        }

        function addVariantProductToCart(id){
            var variant = $('input[name=variant]:checked').val();
            addToCart(id, variant, 1);
        }

        function updateQuantity(key){
            $.post('{{ route('pos.updateQuantity') }}',{_token:'{{ csrf_token() }}', key:key, quantity: $('#qty-'+key).val()}, function(data){
                $('#cart-details').html(data);
                $('#product-variation').modal('hide');
            });
        }

        function setDiscount(){
            var discount = $('input[name=discount]').val();
            $.post('{{ route('pos.setDiscount') }}',{_token:'{{ csrf_token() }}', discount:discount}, function(data){
                $('#cart-details').html(data);
                $('#product-variation').modal('hide');
            });
        }

        function setShipping(){
            var shipping = $('input[name=shipping]:checked').val();
            $.post('{{ route('pos.setShipping') }}',{_token:'{{ csrf_token() }}', shipping:shipping}, function(data){
                $('#cart-details').html(data);
                $('#product-variation').modal('hide');
            });
        }

        function getShippingAddress(){
            $.post('{{ route('pos.getShippingAddressForSeller') }}',{_token:'{{ csrf_token() }}', id:$('select[name=user_id]').val()}, function(data){
                $('#shipping_address').html(data);
            });
        }

        function add_new_address(){
             var customer_id = $('#customer_id').val();
            $('#set_customer_id').val(customer_id);
            $('#new-address-modal').modal('show');
            $("#close-button").click();
        }

        function submitOrder(payment_type){
            var user_id = $('select[name=user_id]').val();
            var name = $('input[name=name]').val();
            var email = $('input[name=email]').val();
            var address = $('input[name=address]').val();
            var country = $('select[name=country]').val();
            var city = $('input[name=city]').val();
            var postal_code = $('input[name=postal_code]').val();
            var phone = $('input[name=phone]').val();
            var shipping = $('input[name=shipping]:checked').val();
            var discount = $('input[name=discount]').val();
            var address = $('input[name=address_id]:checked').val();

            $.post('{{ route('pos.order_place') }}',{_token:'{{ csrf_token() }}', user_id:user_id, name:name, email:email, address:address, country:country, postal_code:postal_code, phone:phone, payment_type:payment_type, shipping:shipping, discount:discount, shipping_address:address }, function(data){
                if(data == 1){
                    AIZ.plugins.notify('success', '{{ translate('Order Completed Successfully.') }}');
                    location.reload();
                }
                else{
                    AIZ.plugins.notify('danger', '{{ translate('Something went wrong') }}');
                }
            });
        }
    </script>
@endsection
