@extends('backend.layout.top-head')

@push('css')
<style type="text/css">
    body{font-family:'Inter',sans-serif}
    .bootstrap-select-sm .btn {font-size: 13px;padding: 3px 25px 3px 10px;height: 30px !important}
    .minus,.plus {padding: .35rem .75rem}
    .numkey.qty {font-size: 13px;padding: 0 0;max-width: 50px;text-align: center}
    .sub-total{font-weight:500;}
    .pos-page .container-fluid {padding: 0 15px}
    .pos-page .side-navbar {top: 0}
    section.pos-section {padding: 10px 0}
    .pos-page .table-fixed {margin-bottom: 0}
    .pos-text {line-height: 1.8}
    .pos-page section header {padding: 0 0}
    .pos .bootstrap-select button {padding-right: 21px !important}
    .pos .bootstrap-select.form-control:not([class*=col-]) {width: 100px}
    .pos-page .order-list .btn {padding: 2px 5px}
    .pos-page [class=row] {margin-left:-10px;margin-right:-10px}
    .pos-page [class*=col-] {padding: 0 10px}
    .pos-page #myTable [class*=col-] {padding: .5rem}
    .pos-page #myTable tr th {background: #f5f6f7;color:#5e5873}
    .product-btns{margin:0 -5px}
    .edit-product{white-space: break-spaces;font-size:13px;font-weight:500;text-align:left;padding:0 0!important}
    .edit-product i{color:#00cec9}
    .product-title span{font-size:12px}
    .more-options{box-shadow: -5px 0px 10px 0px rgba(44,44,44,0.3);font-size:12px;margin:10px 0;padding-left:3px;padding-right:3px}
    label{font-size:13px}
    #tbody-id tr td {font-size:13px;padding: 0}
    table,tr,td {border-collapse: collapse;}
    .top-fields{margin-top:10px;position: relative;}
    .top-fields label {background:#FFF;font-size:11px;margin-left:10px;padding:0 3px;position:absolute;top:-8px;z-index:9;}
    .top-fields input,.top-fields .btn{font-size:13px;height:37px}
    .product-grid{align-items: center; display: flex;flex-wrap: wrap;padding: 0;margin: 0;width: 100%;}
    .product-grid > div {border: 1px solid #e4e6fc;overflow: hidden;padding:.5rem;position: relative;max-width: 300px;min-width: 100px;vertical-align: top;width: calc(100%/4);}
    .product-grid > div p {color: #5e5873;font-size:12px;font-weight: 500;margin: 10px 0 0;min-height: 36px;display: -webkit-box;-webkit-line-clamp: 2;overflow: hidden;text-overflow: ellipsis;-webkit-box-orient: vertical}
    .product-grid > div span {font-size: 12px}
    .more-payment-options.column-5{margin:0;padding:0}
    #print-layout{padding: 0 0;margin: 0 0;}
    .category-img p,.brand-img p{color: #5e5873;font-size:12px;font-weight:500}
    .brand-img,.category-img{display:flex;flex-direction:column;justify-content:center;align-items:center;}
    .brand-img img{max-width:70%}
    .load-more{margin-top:15px}
    .load-more:disabled{opacity:0.5}
    .ui-helper-hidden-accessible{display:none!important}
    .btn-custom{font-size:13px;}
    @media (max-width: 500px) {
        .product-grid > div {width: calc(100%/3);}
    }
    @media (max-width: 375px) {
        .product-grid > div {width: calc(100%/2);}
    }
    @media all and (max-width:767px){
        section.pos-section {padding: 0 5px}
        nav.navbar{margin: 0 -10px}
        .pos-form{padding:0 0 !important}
        .payment-options {padding: 5px 0}
        .payment-options .column-5{margin:5px 0;}
        .payment-options .btn-sm{font-size:12px;}
        .more-payment-options, .more-payment-options .btn-group{width:100%}
        .more-payment-options.column-5{padding:0 5px;}
        .product-btns{margin:0 -15px 10px -15px}
        .product-btns .btn{font-size: 12px;}
        .more-options{margin-top: 0;}
        .transaction-list {height: 35vh;}
        .filter-window{position:fixed;}
    }

    @media print {
        .hidden-print {display: none !important;}
    }

    #print-layout * {font-size: 10px;line-height: 20px;font-family: 'Ubuntu', sans-serif;text-transform: capitalize;}
    #print-layout .btn {padding: 7px 10px;text-decoration: none;border: none;display: block;text-align: center;margin: 7px;cursor:pointer;}

    #print-layout .btn-info {background-color: #999;color: #FFF;}

    #print-layout .btn-primary {background-color: #6449e7;color: #FFF;width: 100%;}
    #print-layout td,
    #print-layout th,
    #print-layout tr,
    #print-layout table {border-collapse: collapse;}
    #print-layout tr {border-bottom: 1px dotted #ddd;}
    #print-layout td,#print-layout th {padding: 7px 0;width: 50%;}

    #print-layout table {width: 100%;}

    #print-layout .centered {display: block;text-align: center;align-content: center;}
    #print-layout small{font-size:10px;}

    @media print {
        #print-layout * {font-size:10px!important;line-height: 20px;}
        #print-layout table {width: 100%;margin: 0 0;}
        #print-layout td,#print-layout th {padding: 5px 0;}
        #print-layout .hidden-print {display: none !important;}
    }
    .loader{display: block;max-width: 100% !important; min-width: 100% !important;text-align: center;vertical-align: middle;width: 100% !important; margin-top: 50px}
    .product-grid .loader{margin-top: 25%;}

    .loader svg path,.loader svg rect{fill: #7c5cc4;}
    nav.navbar a.menu-btn {display: flex;justify-content: center;align-items: center;}
    nav.navbar a {align-items: center;display: flex;}
    .right-sidebar li a svg{margin-right: 10px}
    .nav-menu svg {width: 20px;height: 20px; stroke: #7c5cc4;vertical-align: middle}
    .btn svg {vertical-align: middle; width: 16px}
    button.close svg {vertical-align: middle; width: 26px}
    .bootstrap-select.btn-group > .dropdown-toggle{height: 37px}

    .dropdown-toggle-no-arrow::after{display:none!important}
    .calculator{background-color:#fff;border-radius:10px;box-shadow:0 0 10px rgba(0,0,0,.2);width:240px}
    .calculator .display{width:100%;height:50px;background-color:#f5f5f5;border:2px solid #7c5cc4;font-size:1.5em;text-align:right;padding:0 10px;margin-bottom:10px;border-radius:5px}
    .calculator .buttons{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
    .calculator .btn{height:40px;font-size:1em;border:none;border-radius:5px;cursor:pointer;transition:background-color .2s}
    .calculator .btn.number{background-color:#fff;color:#000;border:1px solid #ddd}
    .calculator .btn.operator{background-color:#f0f0f0;color:#000}
    .calculator .btn.action.ac{background-color:#d63031;color:#fff}
    .calculator .btn.action.ce{background-color:#e28d02;color:#fff}
    .calculator .btn.equals{background-color:#7c5cc4;color:#fff;grid-column:span 2}
    #product-results-container{background:#f5f6f7;position: absolute;overflow: hidden;max-height: 300px;overflow-y: auto;top:40px;width:100%;z-index:999}
    #product-results-container .product-img{border-radius: 3px; color: #7c5cc4;font-size:13px;padding-top:7px;padding-bottom:7px;text-align:left}
    #product-results-container .product-img:hover{background-color: #7c5cc4;color: #FFF}
</style>
@endpush
@section('content')

@php
    $handle_discount_active = $role_has_permissions_list->where('name', 'handle_discount')->first();
@endphp

<x-success-message key="message" />
<x-error-message key="phone_number" />
<x-error-message key="not_permitted" />
<x-error-message key="error" />

<section id="pos-layout" class="forms pos-section hidden-print">
    <div class="container-fluid">
        <div class="row">
            <!-- product list -->
            <div class="col-md-5 order-first order-md-2">
                <!-- navbar-->
                <header>
                    <nav class="navbar">

                        <a class="btn menu-btn" href="{{url('/dashboard')}}"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg></a>
                        <ul class="nav-menu list-unstyled d-flex flex-md-row align-items-md-center">
                            <li class="nav-item ml-4 d-md-none"><a  data-toggle="collapse" href="#collapseProducts" role="button" aria-expanded="false" aria-controls="collapseProducts"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="m7.875 14.25 1.214 1.942a2.25 2.25 0 0 0 1.908 1.058h2.006c.776 0 1.497-.4 1.908-1.058l1.214-1.942M2.41 9h4.636a2.25 2.25 0 0 1 1.872 1.002l.164.246a2.25 2.25 0 0 0 1.872 1.002h2.092a2.25 2.25 0 0 0 1.872-1.002l.164-.246A2.25 2.25 0 0 1 16.954 9h4.636M2.41 9a2.25 2.25 0 0 0-.16.832V12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 12V9.832c0-.287-.055-.57-.16-.832M2.41 9a2.25 2.25 0 0 1 .382-.632l3.285-3.832a2.25 2.25 0 0 1 1.708-.786h8.43c.657 0 1.281.287 1.709.786l3.284 3.832c.163.19.291.404.382.632M4.5 20.25h15A2.25 2.25 0 0 0 21.75 18v-2.625c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125V18a2.25 2.25 0 0 0 2.25 2.25Z" /></svg></a></li>
                            <li class="nav-item ml-4 d-none d-lg-block dropdown">
                                <a  class="dropdown-toggle-no-arrow" type="button" data-toggle="dropdown" aria-expanded="false" role="button"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V13.5Zm0 2.25h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V18Zm2.498-6.75h.007v.008h-.007v-.008Zm0 2.25h.007v.008h-.007V13.5Zm0 2.25h.007v.008h-.007v-.008Zm0 2.25h.007v.008h-.007V18Zm2.504-6.75h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V13.5Zm0 2.25h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V18Zm2.498-6.75h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V13.5ZM8.25 6h7.5v2.25h-7.5V6ZM12 2.25c-1.892 0-3.758.11-5.593.322C5.307 2.7 4.5 3.65 4.5 4.757V19.5a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25V4.757c0-1.108-.806-2.057-1.907-2.185A48.507 48.507 0 0 0 12 2.25Z" /></svg></a>
                                <div class="dropdown-menu calculator p-3" onclick="event.stopPropagation();">
                                    <input type="text" class="display" readonly>
                                    <div class="buttons">
                                        <button class="btn action ac">AC</button>
                                        <button class="btn action ce">CE</button>
                                        <button class="btn operator">%</button>
                                        <button class="btn operator">÷</button>

                                        <button class="btn number">7</button>
                                        <button class="btn number">8</button>
                                        <button class="btn number">9</button>
                                        <button class="btn operator">x</button>

                                        <button class="btn number">4</button>
                                        <button class="btn number">5</button>
                                        <button class="btn number">6</button>
                                        <button class="btn operator">-</button>

                                        <button class="btn number">1</button>
                                        <button class="btn number">2</button>
                                        <button class="btn number">3</button>
                                        <button class="btn operator">+</button>

                                        <button class="btn number">0</button>
                                        <button class="btn number">.</button>
                                        <button class="btn equals">=</button>
                                    </div>
                                </div>
                            </li>
                            <li class="nav-item ml-4 d-none d-lg-block"><a id="btnFullscreen" data-toggle="tooltip" title="{{ __('db.Full Screen') }}"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" /></svg></a></li>
                            <?php
                            $general_setting_permission = $permission_list->where('name', 'general_setting')->first();
                            $general_setting_permission_active = DB::table('role_has_permissions')->where([
                                ['permission_id', $general_setting_permission->id],
                                ['role_id', Auth::user()->role_id]
                            ])->first();

                            $pos_setting_permission = $permission_list->where('name', 'pos_setting')->first();

                            $pos_setting_permission_active = DB::table('role_has_permissions')->where([
                                ['permission_id', $pos_setting_permission->id],
                                ['role_id', Auth::user()->role_id]
                            ])->first();

                            $authUser = Auth::user()->role_id;
                            ?>
                            @if($pos_setting_permission_active)
                            <li class="nav-item"><a class="dropdown-item" data-toggle="tooltip" href="{{route('setting.pos')}}" title="{{__('db.POS Setting')}}"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg></a> </li>
                            @endif
                            <li class="nav-item">
                                <a href="{{route('sales.printLastReciept')}}" data-toggle="tooltip" title="{{__('db.Print Last Reciept')}}"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" /></svg></a>
                            </li>
                            <li class="nav-item d-none d-lg-block">
                                <a href="" id="register-details-btn" data-id="" data-toggle="tooltip" title="{{__('db.Cash Register Details')}}"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" /></svg></a>
                            </li>
                            <?php
                            $today_sale_permission = $permission_list->where('name', 'today_sale')->first();
                            $today_sale_permission_active = DB::table('role_has_permissions')->where([
                                ['permission_id', $today_sale_permission->id],
                                ['role_id', Auth::user()->role_id]
                            ])->first();

                            $today_profit_permission = $permission_list->where('name', 'today_profit')->first();
                            $today_profit_permission_active = DB::table('role_has_permissions')->where([
                                ['permission_id', $today_profit_permission->id],
                                ['role_id', Auth::user()->role_id]
                            ])->first();
                            ?>

                            @if($today_sale_permission_active)
                            <li class="nav-item d-none d-lg-block">
                                <a href="" id="today-sale-btn" data-toggle="tooltip" title="{{__('db.Today Sale')}}"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg></a>
                            </li>
                            @endif
                            @if($today_profit_permission_active)
                            <li class="nav-item d-none d-lg-block">
                                <a href="" id="today-profit-btn" data-toggle="tooltip" title="{{__('db.Today Profit')}}"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></a>
                            </li>
                            @endif
                            @if(($alert_product + count(\Auth::user()->unreadNotifications)) > 0)
                            <li class="nav-item d-none d-lg-block" id="notification-icon">
                                <a rel="nofollow" data-toggle="tooltip" title="{{__('db.Notifications')}}" class="nav-link dropdown-item"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg><span class="badge badge-danger notification-number">{{$alert_product + count(\Auth::user()->unreadNotifications)}}</span>
                                    <span class="caret"></span>
                                    <span class="sr-only">Toggle Dropdown</span>
                                </a>
                                <ul class="right-sidebar" user="menu">
                                    <li class="notifications">
                                        <a href="{{route('report.qtyAlert')}}" class="btn btn-link">{{$alert_product}} product exceeds alert quantity</a>
                                    </li>
                                    @foreach(\Auth::user()->unreadNotifications as $key => $notification)
                                    <li class="notifications">
                                        <a href="#" class="btn btn-link">{{ $notification->data['message'] }}</a>
                                    </li>
                                    @endforeach
                                </ul>
                            </li>
                            @endif
                            <li class="nav-item">
                                <a rel="nofollow" data-toggle="tooltip" class="nav-link dropdown-item"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg> <span>{{ucfirst(Auth::user()->name)}}</span> <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                                </a>
                                <ul class="right-sidebar">
                                    <li>
                                        <a href="{{route('user.profile', ['id' => Auth::id()])}}"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg> {{__('db.profile')}}</a>
                                    </li>
                                    <?php
                                    $add_expense_permission = $role_has_permissions_list->where('name', 'expenses-add')->first();
                                    ?>
                                    @if($add_expense_permission)
                                    <li>
                                        <a href="" data-toggle="modal" data-target="#expense-modal"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 0 0-2.25-2.25H15a3 3 0 1 1-6 0H5.25A2.25 2.25 0 0 0 3 12m18 0v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 9m18 0V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v3" /></svg> {{__('db.Add Expense')}}</a>
                                    </li>
                                    @endif
                                    <?php
                                    $add_payment_permission = $role_has_permissions_list->where('name', 'purchase-payment-add')->first();
                                    ?>
                                    @if($add_payment_permission)
                                    <li>
                                        <a href="" class="add-supplier-payment" data-toggle="modal" data-target="#add-supplier-payment"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg> {{__('db.Add Payment')}}</a>
                                    </li>
                                    @endif
                                    @if($general_setting_permission_active)
                                    <li>
                                        <a href="{{route('setting.general')}}"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg> {{__('db.settings')}}</a>
                                    </li>
                                    @endif
                                    <li>
                                        <a href="{{url('my-transactions/'.date('Y').'/'.date('m'))}}"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg> {{__('db.My Transaction')}}</a>
                                    </li>
                                    @if(Auth::user()->role_id != 5)
                                    <li>
                                        <a href="{{url('holidays/my-holiday/'.date('Y').'/'.date('m'))}}">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.182 15.182a4.5 4.5 0 0 1-6.364 0M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM9.75 9.75c0 .414-.168.75-.375.75S9 10.164 9 9.75 9.168 9 9.375 9s.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Zm5.625 0c0 .414-.168.75-.375.75s-.375-.336-.375-.75.168-.75.375-.75.375.336.375.75Zm-.375 0h.008v.015h-.008V9.75Z" /></svg> {{__('db.My Holiday')}}</a>
                                    </li>
                                    @endif
                                    <li>
                                        <a href="{{ route('logout') }}"
                                            onclick="event.preventDefault();
                                                        document.getElementById('logout-form').submit();"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1 0 12.728 0M12 3v9" /></svg>

                                            {{__('db.logout')}}
                                        </a>
                                        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                                            @csrf
                                        </form>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </nav>
                </header>

                <div class="filter-window">
                    <div class="category mt-3">
                        <div class="row ml-2 mr-2 px-2">
                            <div class="col-7">Choose category</div>
                            <div class="col-5 text-right">
                                <span class="btn btn-default btn-sm btn-close">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                </span>
                            </div>
                        </div>
                        <div class="row ml-2 mt-3">
                            @foreach($lims_category_list as $category)
                            <div class="col-md-3 col-6 category-img text-center" data-category="{{$category->id}}">
                                @if($category->image)
                                <img src="{{url('images/category', $category->image)}}" />
                                @else
                                <img src="{{url('/images/product/zummXD2dvAtI.png')}}" />
                                @endif
                                <p class="text-center">{{$category->name}}</p>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="brand mt-3">
                        <div class="row ml-2 mr-2 px-2">
                            <div class="col-7">Choose brand</div>
                            <div class="col-5 text-right">
                                <span class="btn btn-default btn-sm btn-close">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                </span>
                            </div>
                        </div>
                        <div class="row ml-2 mt-3">
                            @foreach($lims_brand_list as $brand)
                            <div class="col-md-3 col-6 brand-img text-center" data-brand="{{$brand->id}}">
                                @if($brand->image)
                                <img src="{{url('images/brand',$brand->image)}}" />
                                @else
                                <img src="{{url('/images/product/zummXD2dvAtI.png')}}" />
                                @endif
                                <p class="text-center">{{$brand->title}}</p>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="products-m mt-3">
                        <div class="row ml-2 mr-2 px-2">
                            <div class="col-7"></div>
                            <div class="col-5 text-right">
                                <span class="btn btn-default btn-sm btn-close">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                </span>
                            </div>
                        </div>
                        <div class="product_list_mobile table-container row mt-3" data-cat="" data-brand="">

                        </div>
                    </div>
                </div>
                <div id="collapseProducts" class="">
                    <div class="d-flex justify-content-between product-btns">

                        <button class="btn btn-block btn-primary mt-0 ml-1 mr-1" id="category-filter">{{__('db.category')}}</button>

                        <button class="btn btn-block btn-info mt-0 ml-1 mr-1" id="brand-filter">{{__('db.Brand')}}</button>

                        <button class="btn btn-block btn-danger mt-0 ml-1 mr-1" id="featured-filter">{{__('db.Featured')}}</button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="alert alert-primary alert-dismissible fade show mb-0 pt-1 pb-1 loading-message"><span class="small">{{ __('db.Loading products for selected warehouse') }}</span>
                            <button type="button" id="closeButtonUpgrade" class="close pt-1 pb-1" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-12 table-container main mt-2" data-cat="" data-brand="">
                        
                        <div class="product-grid text-center">
                            
                            <div class="loader " title="4" style="border:none">
                                <svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="24px" height="30px" viewBox="0 0 24 30" style="enable-background:new 0 0 50 50;" xml:space="preserve">
                                    <rect x="0" y="0" width="4" height="10" fill="#333">
                                    <animateTransform attributeType="xml" attributeName="transform" type="translate" values="0 0; 0 20; 0 0" begin="0" dur="0.6s" repeatCount="indefinite"></animateTransform>
                                    </rect>
                                    <rect x="10" y="0" width="4" height="10" fill="#333">
                                    <animateTransform attributeType="xml" attributeName="transform" type="translate" values="0 0; 0 20; 0 0" begin="0.2s" dur="0.6s" repeatCount="indefinite"></animateTransform>
                                    </rect>
                                    <rect x="20" y="0" width="4" height="10" fill="#333">
                                    <animateTransform attributeType="xml" attributeName="transform" type="translate" values="0 0; 0 20; 0 0" begin="0.4s" dur="0.6s" repeatCount="indefinite"></animateTransform>
                                    </rect>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-7 pos-form">
                {!! Form::open(['route' => 'sales.store', 'method' => 'post', 'files' => true, 'class' => 'payment-form']) !!}

                @php
                if($lims_pos_setting_data)
                $keybord_active = $lims_pos_setting_data->keybord_active;
                else
                $keybord_active = 0;

                $customer_active = DB::table('permissions')
                ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where([
                ['permissions.name', 'customers-add'],
                ['role_id', \Auth::user()->role_id] ])->first();
                @endphp
                <div class="row">
                    <div class="col-md-11 col-12">
                        <div class="row">
                            <div class="col-md-3 col-6">
                                <div class="form-group top-fields">
                                    <label>{{__('db.date')}}</label>
                                    <div class="input-group">
                                        <input type="text" name="created_at" class="form-control date" value="{{date($general_setting->date_format,strtotime('now'))}}"  />
                                    </div>
                                </div>
                            </div>
                            @if(isset(auth()->user()->warehouse_id))
                            <input type="hidden" id="warehouse_id" name="warehouse_id" value="{{auth()->user()->warehouse_id}}" />
                            @else
                            <div class="col-md-3 col-6">
                                <div class="form-group top-fields">
                                    <label>{{__('db.Warehouse')}}</label>
                                    @php
                                    if(isset($lims_sale_data) && !empty($lims_sale_data) && $lims_sale_data->warehouse_id) {
                                        $warehouse_id = $lims_sale_data->warehouse_id;
                                    }
                                    elseif($lims_pos_setting_data) {
                                        $warehouse_id = $lims_pos_setting_data->warehouse_id;
                                    }
                                    else{
                                        $warehouse_id = $lims_warehouse_list[0]->id;
                                    }
                                    @endphp
                                    <select required id="warehouse_id" name="warehouse_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="{{ __('db.Select warehouse...') }}">
                                        @foreach($lims_warehouse_list as $warehouse)
                                        <option value="{{$warehouse->id}}" @if($warehouse->id == $warehouse_id) selected @endif>{{$warehouse->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @endif
                            <x-validation-error fieldName="warehouse_id" />
                            @if(isset(auth()->user()->biller_id))
                            <input type="hidden" id="biller_id" name="biller_id" value="{{auth()->user()->biller_id}}" />
                            @else
                            <div class="col-md-3 col-6">
                                <div class="form-group top-fields">
                                    <label>{{__('db.Biller')}}</label>
                                    @php
                                    if(isset($lims_sale_data) && !empty($lims_sale_data) && $lims_sale_data->biller_id) {
                                        $biller_id = $lims_sale_data->biller_id;
                                    }
                                    elseif($lims_pos_setting_data) {
                                        $biller_id = $lims_pos_setting_data->biller_id;
                                    }
                                    else{
                                        $biller_id = $lims_biller_list[0]->id;
                                    }
                                    @endphp
                                    <select required id="biller_id" name="biller_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="{{ __('db.Select Biller...') }}">
                                        @foreach($lims_biller_list as $biller)
                                        <option value="{{$biller->id}}" @if($biller->id == $biller_id) selected @endif>{{$biller->name . ' (' . $biller->company_name . ')'}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @endif
                            <div class="col-md-3 col-6">
                                <div class="form-group top-fields">
                                    <label>{{__('db.customer')}}</label>
                                    <div class="input-group pos">
                                        @php
                                        $deposit = [];
                                        $points = [];
                                        if(isset($lims_sale_data) && !empty($lims_sale_data) && $lims_sale_data->customer_id) {
                                            $customer_id = $lims_sale_data->customer_id;
                                        }
                                        elseif($lims_pos_setting_data) {
                                            $customer_id = $lims_pos_setting_data->customer_id;
                                        }
                                        else{
                                        $customer_id = $lims_customer_list[0]->id;
                                        }
                                        @endphp
                                        <select required name="customer_id" id="customer_id" class="selectpicker form-control" data-live-search="true" title="{{ __('db.Select customer...') }}" style="width: 100px">
                                            @foreach($lims_customer_list as $customer)
                                                @php
                                                $deposit[$customer->id] = $customer->deposit - $customer->expense;

                                                $points[$customer->id] = $customer->points;
                                                @endphp
                                                <option value="{{$customer->id}}" @if($customer->id == $customer_id) selected @endif>{{$customer->name}} @if($customer->phone_number)({{$customer->phone_number}})@endif</option>
                                            @endforeach
                                        </select>
                                        @if($customer_active)
                                        <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#addCustomer"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg></button>
                                        @endif
                                        <x-validation-error fieldName="customer_id" />
                                    </div>
                                </div>
                            </div>
                            @if(in_array('restaurant',explode(',',$general_setting->modules)))
                            <div class="col-md-3 col-6">
                                <div class="form-group top-fields">
                                    <label>{{__('db.Service')}}</label>
                                    @php
                                    if(isset($lims_sale_data) && !empty($lims_sale_data) && $lims_sale_data->service_id) {
                                        $service_id = $lims_sale_data->service_id;
                                    }
                                    @endphp
                                    @if(!empty($service_id))
                                    <div class="input-group pos">
                                        <select required id="service_id" name="service_id" class="selectpicker form-control" title="{{ __('db.Select service...') }}">
                                            <option value="1" @if($service_id == 1) selected @endif>{{__('db.Dine In')}}</option>
                                            <option value="2" @if($service_id == 2) selected @endif>{{__('db.Take Away')}}</option>
                                            <option value="3" @if($service_id == 3) selected @endif>{{__('db.Delivery')}}</option>
                                        </select>
                                    </div>
                                    @else
                                    <div class="input-group pos">
                                        <select required id="service_id" name="service_id" class="selectpicker form-control" title="{{ __('db.Select service...') }}">
                                            <option value="1" selected>{{__('db.Dine In')}}</option>
                                            <option value="2">{{__('db.Take Away')}}</option>
                                            <option value="3">{{__('db.Delivery')}}</option>
                                        </select>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-3 col-6">
                                <div class="form-group top-fields">
                                    <label>{{__('db.table')}}</label>
                                    <div class="input-group pos">
                                        @php
                                        if(isset($lims_sale_data) && !empty($lims_sale_data) && !empty($lims_sale_data->table_id)) {
                                            $table_id = $lims_sale_data->table_id;
                                        }
                                        @endphp
                                        <select required id="table_id" name="table_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="{{ __('db.Select table...') }}">
                                            @foreach($lims_table_list as $table)
                                            <option value="{{$table->id}}" @if(!empty($table_id) && $table->id == $table_id) selected @endif>
                                                {{$table->name}} at {{$table->floor}} ( ðŸ‘¤ {{$table->number_of_person}})
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 col-6">
                                <div class="form-group top-fields">
                                    <label>{{__('db.Waiter')}}</label>
                                    <div class="input-group pos">
                                        @php
                                        if(isset($lims_sale_data) && !empty($lims_sale_data) && !empty($lims_sale_data->waiter_id)) {
                                            $waiter_id = $lims_sale_data->waiter_id;
                                        }
                                        @endphp
                                        <select required id="waiter_id" name="waiter_id" class="selectpicker form-control" title="{{ __('db.Select waiter...') }}">
                                            @if(auth()->user()->service_staff == 1)
                                            <option value="{{auth()->user()->id}}" selected >{{auth()->user()->name}}</option>
                                            @else
                                                @foreach($waiter_list as $waiter)
                                                <option value="{{$waiter->id}}" @if(!empty($waiter_id) && $waiter->id == $waiter_id) selected @endif>
                                                    {{$waiter->name}}
                                                </option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-1 col-12">
                        <a class="btn btn-primary btn-block more-options" data-toggle="collapse" href="#moreOptions" role="button" aria-expanded="false" aria-controls="moreOptions"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM18.75 12a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg></a>
                    </div>
                </div>
                <div>
                    <div class="collapse" id="moreOptions">
                        <div class="card card-body">
                            <div class="row">
                                <?php
                                    $accountSelection = $role_has_permissions_list->where('name', 'account-selection')->first();
                                ?>
                                @if ($accountSelection)
                                    <!-- New Account Selection Field -->
                                    <div class="col-md-3 col-6">
                                        <div class="form-group">
                                            <label>{{__('db.Account')}}</label>
                                            <select required name="account_id" id="account_id" class="selectpicker form-control" data-live-search="true">
                                                <option value="0" selected style="color: #A7B49E;">Select an Account</option>
                                                @foreach($lims_account_list as $account)
                                                    <option value="{{$account->id}}">{{$account->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                @endif
                                <div class="col-md-3">
                                    <label>{{__('db.Sale Reference No')}}</label>
                                    <div class="form-group">
                                        <input type="text" id="reference-no" name="reference_no" class="form-control" placeholder="{{ __('db.Type reference number') }}"  />
                                    </div>
                                    <x-validation-error fieldName="reference_no" />
                                </div>
                                <div class="col-md-4">
                                    <label>{{__('db.Currency')}} & {{__('db.Exchange Rate')}}</label>
                                    <div class="form-group d-flex">
                                        <div class="input-group-prepend">
                                            <select name="currency_id" id="currency" class="form-control selectpicker" data-toggle="tooltip" title="" data-original-title="{{ __('db.Sale currency') }}">
                                                @foreach($currency_list as $currency_data)
                                                <option value="{{$currency_data->id}}" data-rate="{{$currency_data->exchange_rate}}">{{$currency_data->code}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <input class="form-control" type="text" id="exchange_rate" name="exchange_rate" value="{{$currency->exchange_rate}}">
                                        <div class="input-group-append">
                                            <span class="input-group-text" data-toggle="tooltip" title="" data-original-title="currency exchange rate">i</span>
                                        </div>
                                    </div>
                                </div>
                                @foreach($custom_fields as $field)
                                @if(!$field->is_admin || \Auth::user()->role_id == 1)
                                <div class="{{'col-md-'.$field->grid_value}}">
                                    <div class="form-group">
                                        <label>{{$field->name}}</label>
                                        @if($field->type == 'text')
                                        <input type="text" name="{{str_replace(' ', '_', strtolower($field->name))}}" value="{{$field->default_value}}" class="form-control" @if($field->is_required){{'required'}}@endif>
                                        @elseif($field->type == 'number')
                                        <input type="number" name="{{str_replace(' ', '_', strtolower($field->name))}}" value="{{$field->default_value}}" class="form-control" @if($field->is_required){{'required'}}@endif>
                                        @elseif($field->type == 'textarea')
                                        <textarea rows="5" name="{{str_replace(' ', '_', strtolower($field->name))}}" value="{{$field->default_value}}" class="form-control" @if($field->is_required){{'required'}}@endif></textarea>
                                        @elseif($field->type == 'checkbox')
                                        <br>
                                        <?php $option_values = explode(",", $field->option_value); ?>
                                        @foreach($option_values as $value)
                                        <label>
                                            <input type="checkbox" name="{{str_replace(' ', '_', strtolower($field->name))}}[]" value="{{$value}}" @if($value==$field->default_value){{'checked'}}@endif @if($field->is_required){{'required'}}@endif> {{$value}}
                                        </label>
                                        &nbsp;
                                        @endforeach
                                        @elseif($field->type == 'radio_button')
                                        <br>
                                        <?php $option_values = explode(",", $field->option_value); ?>
                                        @foreach($option_values as $value)
                                        <label class="radio-inline">
                                            <input type="radio" name="{{str_replace(' ', '_', strtolower($field->name))}}" value="{{$value}}" @if($value==$field->default_value){{'checked'}}@endif @if($field->is_required){{'required'}}@endif> {{$value}}
                                        </label>
                                        &nbsp;
                                        @endforeach
                                        @elseif($field->type == 'select')
                                        <?php $option_values = explode(",", $field->option_value); ?>
                                        <select class="form-control" name="{{str_replace(' ', '_', strtolower($field->name))}}" @if($field->is_required){{'required'}}@endif>
                                            @foreach($option_values as $value)
                                            <option value="{{$value}}" @if($value==$field->default_value){{'selected'}}@endif>{{$value}}</option>
                                            @endforeach
                                        </select>
                                        @elseif($field->type == 'multi_select')
                                        <?php $option_values = explode(",", $field->option_value); ?>
                                        <select class="form-control" name="{{str_replace(' ', '_', strtolower($field->name))}}[]" @if($field->is_required){{'required'}}@endif multiple>
                                            @foreach($option_values as $value)
                                            <option value="{{$value}}" @if($value==$field->default_value){{'selected'}}@endif>{{$value}}</option>
                                            @endforeach
                                        </select>
                                        @elseif($field->type == 'date_picker')
                                        <input type="text" name="{{str_replace(' ', '_', strtolower($field->name))}}" value="{{$field->default_value}}" class="form-control date" @if($field->is_required){{'required'}}@endif>
                                        @endif
                                    </div>
                                </div>
                                @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @if($lims_pos_setting_data->is_table && !in_array('restaurant',explode(',',$general_setting->modules)))
                    <div class="col-12 pl-0 pr-0">
                        <div class="form-group">
                            <select required id="table_id" name="table_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="{{ __('db.Select table...') }}">
                                @foreach($lims_table_list as $table)
                                <option value="{{$table->id}}">{{$table->name}}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @endif
                    <div class="col-12 pl-0 pr-0">
                        <div class="search-box form-group mb-2">
                            <div class="input-group pos">
                                <input style="border: 1px solid #7c5cc4;" type="text" name="product_code_name" id="product-search-input" placeholder="{{ __('db.Scan/Search product by name/code/IMEI') }}" class="form-control" autofocus />
                                <button type="button" class="btn btn-primary" onclick="barcode()"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-upc" viewBox="0 0 16 16"><path d="M3 4.5a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0zm2 0a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0zm2 0a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0zm2 0a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v7a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm3 0a.5.5 0 0 1 1 0v7a.5.5 0 0 1-1 0z"/></svg></button>
                            </div>
                            <div id="product-results-container">
                                
                            </div>
                            <div id="no-results-message" style="background-color: #f5f6f7;color: #666; margin-top: 5px;padding: 3px 5px; display: none;">No results found</div>
                        </div>
                    </div>
                    <div class="table-responsive transaction-list">
                        <table id="myTable" class="table table-hover table-striped order-list table-fixed">
                            <thead class="d-none d-md-block">
                                <tr>
                                    <th class="col-sm-5 col-6">{{__('db.product')}}</th>
                                    <th class="col-sm-2">{{__('db.Price')}}</th>
                                    <th class="col-sm-3">{{__('db.Quantity')}}</th>
                                    <th class="col-sm-2">{{__('db.Subtotal')}}</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-id">

                            </tbody>
                        </table>
                    </div>
                    <div class="row" style="display: none;">
                        <div class="col-md-2">
                            <div class="form-group">
                                <input type="hidden" name="total_qty" value="0" />
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <input type="hidden" name="total_discount" value="{{number_format(0, $general_setting->decimal, '.', '')}}" />
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <input type="hidden" name="total_tax" value="{{number_format(0, $general_setting->decimal, '.', '')}}" />
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <input type="hidden" name="total_price" value="{{number_format(0, $general_setting->decimal, '.', '')}}" />
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <input type="hidden" name="item" value="0" />
                                <input type="hidden" name="order_tax" value="{{number_format(0, $general_setting->decimal, '.', '')}}" />
                            </div>
                            <x-validation-error fieldName="item" />
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <input type="hidden" name="grand_total" value="{{number_format(0, $general_setting->decimal, '.', '')}}"/>
                                <input type="hidden" name="used_points" />

                                @if(in_array('restaurant',explode(',',$general_setting->modules)))
                                <input type="hidden" name="sale_status" value="5" />
                                @else
                                <input type="hidden" name="sale_status" value="1" />
                                @endif
                                <x-validation-error fieldName="sale_status" />

                                <input type="hidden" name="coupon_active">
                                <input type="hidden" name="coupon_id" value="">
                                <input type="hidden" name="coupon_discount" value="0"/>

                                <input type="hidden" name="pos" value="1" />

                                @if(isset($lims_sale_data) && !empty($lims_sale_data))
                                <input type="hidden" name="sale_id" value="{{$lims_sale_data->id}}" />
                                <input type="hidden" name="draft" value="1" />
                                @else
                                <input type="hidden" name="draft" value="0" />
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-12 totals" style="background-color:#f5f6f7;border-top: 2px solid #ebe9f1;padding-bottom: 7px;padding-top: 7px;">
                        <div class="row">
                            <div class="col-sm-4 col-6">
                                <strong class="totals-title">{{__('db.Items')}}</strong><strong id="item">0 (0)</strong>
                            </div>
                            <div class="col-sm-4 col-6">
                                <strong class="totals-title">{{__('db.Delivered')}}</strong><strong id="total-dqty">0</strong>
                            </div>
                            <div class="col-sm-4 col-6">
                                <strong class="totals-title">{{__('db.D.Status')}}</strong><strong id="total-dstatus">0/0</strong>
                            </div>
                            <div class="col-sm-4 col-6">
                                <strong class="totals-title">{{__('db.Total')}}</strong><strong id="subtotal">{{number_format(0, $general_setting->decimal, '.', '')}}</strong>
                            </div>
                            @if ($handle_discount_active)
                                <div class="col-sm-4 col-6">
                                    <strong class="totals-title">{{__('db.Discount')}} <button type="button" class="btn btn-link btn-sm" data-toggle="modal" data-target="#order-discount-modal"> <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg></button></strong><strong id="discount">{{number_format(0, $general_setting->decimal, '.', '')}}</strong>
                                </div>
                            @endif
                            <div class="col-sm-4 col-6">
                                <strong class="totals-title">{{__('db.Coupon')}} <button type="button" class="btn btn-link btn-sm" data-toggle="modal" data-target="#coupon-modal"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg></button></strong><strong id="coupon-text">{{number_format(0, $general_setting->decimal, '.', '')}}</strong>
                            </div>
                            <div class="col-sm-4 col-6">
                                <strong class="totals-title">{{__('db.Tax')}} <button type="button" class="btn btn-link btn-sm" data-toggle="modal" data-target="#order-tax"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg></button></strong><strong id="tax">{{number_format(0, $general_setting->decimal, '.', '')}}</strong>
                            </div>
                            <div class="col-sm-4 col-6">
                                <strong class="totals-title">{{__('db.Shipping')}} <button type="button" class="btn btn-link btn-sm" data-toggle="modal" data-target="#shipping-cost-modal"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg></button></strong><strong id="shipping-cost">{{number_format(0, $general_setting->decimal, '.', '')}}</strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="payment-amount d-none d-md-block">
                    <h2>{{__('db.grand total')}} <span id="grand-total">{{number_format(0, $general_setting->decimal, '.', '')}}</span></h2>
                </div>
                <div class="payment-options">
                    <div class="column-5 more-payment-options">
                        <div class="btn-group dropup">
                            <button type="button" class="btn btn-primary btn-custom  dropdown-toggle d-md-none" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" /></svg> Pay <span id="grand-total-m"></span>
                            </button>
                            <div class="">
                                @if(in_array("card",$options))
                                <div class="column-5">
                                    <button style="background: #0984e3" type="button" class="btn btn-sm btn-custom payment-btn" data-toggle="modal" data-target="#add-payment" id="credit-card-btn" disabled="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" /></svg> {{__('db.Card')}}</button>
                                </div>
                                @endif
                                @if(in_array("cash",$options))
                                <div class="column-5">
                                    <button style="background: #00cec9" type="button" class="btn btn-sm btn-custom payment-btn" data-toggle="modal" data-target="#add-payment" id="cash-btn" disabled="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg> {{__('db.Cash')}}</button>
                                </div>
                                @endif
                                <div class="column-5">
                                    <button style="background: #010429" type="button" class="btn btn-sm btn-custom payment-btn" data-toggle="modal" data-target="#add-payment" id="multiple-payment-btn" disabled="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg> {{__('db.Multiple Payment')}}</button>
                                </div>
                                {{-- @if(in_array("paypal",$options) && $lims_pos_setting_data && (strlen($lims_pos_setting_data->paypal_live_api_username)>0) && (strlen($lims_pos_setting_data->paypal_live_api_password)>0) && (strlen($lims_pos_setting_data->paypal_live_api_secret)>0))
                                <div class="column-5">
                                    <button style="background-color: #213170" type="button" class="btn btn-sm btn-block btn-custom payment-btn" data-toggle="modal" data-target="#add-payment" id="paypal-btn" disabled="true"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-paypal" viewBox="0 0 16 16"><path d="M14.06 3.713c.12-1.071-.093-1.832-.702-2.526C12.628.356 11.312 0 9.626 0H4.734a.7.7 0 0 0-.691.59L2.005 13.509a.42.42 0 0 0 .415.486h2.756l-.202 1.28a.628.628 0 0 0 .62.726H8.14c.429 0 .793-.31.862-.731l.025-.13.48-3.043.03-.164.001-.007a.35.35 0 0 1 .348-.297h.38c1.266 0 2.425-.256 3.345-.91q.57-.403.993-1.005a4.94 4.94 0 0 0 .88-2.195c.242-1.246.13-2.356-.57-3.154a2.7 2.7 0 0 0-.76-.59l-.094-.061ZM6.543 8.82a.7.7 0 0 1 .321-.079H8.3c2.82 0 5.027-1.144 5.672-4.456l.003-.016q.326.186.548.438c.546.623.679 1.535.45 2.71-.272 1.397-.866 2.307-1.663 2.874-.802.57-1.842.815-3.043.815h-.38a.87.87 0 0 0-.863.734l-.03.164-.48 3.043-.024.13-.001.004a.35.35 0 0 1-.348.296H5.595a.106.106 0 0 1-.105-.123l.208-1.32z"/></svg> {{__('db.PayPal')}}</button>
                                </div>
                                @endif --}}
                                @if(in_array("cheque",$options))
                                <div class="column-5">
                                    <button style="background-color: #fd7272" type="button" class="btn btn-sm btn-block btn-custom payment-btn" data-toggle="modal" data-target="#add-payment" id="cheque-btn" disabled="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg> {{__('db.Cheque')}}</button>
                                </div>
                                @endif
                                @if(in_array("gift_card",$options))
                                <div class="column-5">
                                    <button style="background-color: #5f27cd" type="button" class="btn btn-sm btn-block btn-custom payment-btn" data-toggle="modal" data-target="#add-payment" id="gift-card-btn" disabled="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" /></svg> {{__('db.Gift Card')}}</button>
                                </div>
                                @endif
                                @if(in_array("deposit",$options))
                                <div class="column-5">
                                    <button style="background-color: #b33771" type="button" class="btn btn-sm btn-block btn-custom payment-btn" data-toggle="modal" data-target="#add-payment" id="deposit-btn" disabled="true"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-bank" viewBox="0 0 16 16"><path d="m8 0 6.61 3h.89a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.5.5H15v7a.5.5 0 0 1 .485.38l.5 2a.498.498 0 0 1-.485.62H.5a.498.498 0 0 1-.485-.62l.5-2A.5.5 0 0 1 1 13V6H.5a.5.5 0 0 1-.5-.5v-2A.5.5 0 0 1 .5 3h.89zM3.777 3h8.447L8 1zM2 6v7h1V6zm2 0v7h2.5V6zm3.5 0v7h1V6zm2 0v7H12V6zM13 6v7h1V6zm2-1V4H1v1zm-.39 9H1.39l-.25 1h13.72z"/></svg> {{__('db.Deposit')}}</button>
                                </div>
                                @endif
                                @if($lims_reward_point_setting_data && $lims_reward_point_setting_data->is_active)
                                <div class="column-5">
                                    <button style="background-color: #319398" type="button" class="btn btn-sm btn-block btn-custom payment-btn" data-toggle="modal" data-target="#add-payment" id="point-btn" disabled="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" /></svg> {{__('db.Points')}}</button>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="column-5">
                        <button style="background-color: #e28d02" type="button" class="btn btn-sm btn-custom" id="draft-btn"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5" /></svg> {{__('db.Draft')}}</button>
                    </div>
                    <div class="column-5">
                        <button style="background-color: #d63031;" type="button" class="btn btn-sm btn-custom" id="cancel-btn" onclick="return confirmCancel()"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg> {{__('db.Cancel')}}</button>
                    </div>
                    <div class="column-5">
                        <button style="background-color: #ffc107;" type="button" class="btn btn-sm btn-custom" data-toggle="modal" data-target="#recentTransaction"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg> {{__('db.Recent Transaction')}}</button>
                    </div>
                    <?php
                        $fixed_methods = ['cash', 'card', 'cheque', 'gift_card', 'deposit', 'pesapal'];
                        $payment_methods = explode(',', $lims_pos_setting_data->payment_options);

                        $payment_methods = array_diff($payment_methods, $fixed_methods);
                        $payment_methods = array_values($payment_methods);
                    ?>
                    @if (count($payment_methods))
                        <div class="column-5">
                            <div class="btn-group" role="group">
                                <button id="btn-more" type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    More
                                </button>
                                <div class="dropdown-menu" aria-labelledby="btnGroupDrop1">
                                    @foreach ($payment_methods as $method)
                                        <button id="pay-method" class="dropdown-item pay-options payment-btn" type="button"  data-toggle="modal" data-target="#add-payment" value="{{ $method }}" disabled="true">{{ ucfirst($method) }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- payment modal -->
                <div id="add-payment" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
                    <div role="document" class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 id="exampleModalLabel" class="modal-title">{{__('db.Finalize Sale')}}</h5>
                                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></span></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-10" id="payment-select-row">
                                        <div class="row">
                                            <div class="col-md-3 col-6 mt-1 paying-amount-container">
                                                <label>{{__('db.Paying Amount')}} *</label>
                                                <input type="text" name="paid_amount[]" value="0" class="form-control paid_amount numkey" step="any">
                                            </div>
                                            <div class="col-md-3 col-6 mt-1">
                                                <input type="hidden" name="paid_by_id[]">
                                                <label>{{__('db.Paid By')}}</label>
                                                <select name="paid_by_id_select[]" class="form-control selectpicker">
                                                    @if(in_array("cash",$options))
                                                    <option value="1">Cash</option>
                                                    @endif
                                                    @if(in_array("gift_card",$options))
                                                    <option value="2">Gift Card</option>
                                                    @endif
                                                    @if(in_array("card",$options))
                                                    <option value="3">Credit Card</option>
                                                    @endif
                                                    @if(in_array("cheque",$options))
                                                    <option value="4">Cheque</option>
                                                    @endif
                                                    @if(in_array("deposit",$options))
                                                    <option value="6">Deposit</option>
                                                    @endif
                                                    @if($lims_reward_point_setting_data && $lims_reward_point_setting_data->is_active)
                                                    <option value="7">Points</option>
                                                    @endif
                                                    @foreach($options as $option)
                                                        @if($option !== 'cash' && $option !== 'card' && $option !== 'card' && $option !== 'cheque' && $option !== 'gift_card' && $option !== 'deposit' && $option !== 'paypal' && $option !== 'pesapal')
                                                            <option value="{{$option}}">{{ucfirst($option)}}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-3 col-6 mt-1 cash-received-container">
                                                <label id="received-paying">{{__('db.Cash Received')}} *</label>
                                                <input type="text" name="paying_amount[]" class="form-control paying_amount numkey" required step="any">
                                            </div>
                                        </div>
                                        <div class="row add-more-row mt-2">
                                            <div class="col-md-12 text-center"><button class="btn btn-info add-more">+ {{__('db.Add More Payment')}}</button></div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12 mt-1">
                                                <label>{{__('db.Payment Receiver')}}</label>
                                                <input type="text" name="payment_receiver" class="form-control">
                                            </div>
                                            <div class="form-group col-md-12">
                                                <label>{{__('db.Payment Note')}}</label>
                                                <textarea id="payment_note" rows="2" class="form-control" name="payment_note"></textarea>
                                            </div>
                                            <div class="col-md-6 form-group">
                                                <label>{{__('db.Sale Note')}}</label>
                                                <textarea rows="3" class="form-control" name="sale_note"></textarea>
                                            </div>
                                            <div class="col-md-6 form-group">
                                                <label>{{__('db.Staff Note')}}</label>
                                                <textarea rows="3" class="form-control" name="staff_note"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2 p-2 bg-info text-light pt-4 pb-4">
                                        <div class="mt-4">
                                            <h2>{{__('db.Total Payable')}}</h2>
                                            <p class="total_payable text-light"></p>
                                        </div>
                                        <div class="mt-4">
                                            <h2>{{__('db.Total Paying')}}</h2>
                                            <p class="total_paying text-light">0.00</p>
                                        </div>
                                        <div class="mt-4">
                                            <h2>{{__('db.Change')}}</h2>
                                            <p class="change text-light">0.00</p>
                                        </div>
                                        <div class="mt-4">
                                            <h2>{{__('db.Due')}}</h2>
                                            <p class="due text-light">0.00</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mt-3">
                                            <button id="submit-btn" type="button" class="btn btn-primary">{{__('db.submit')}}</button>
                                            <div class="form-check d-inline-block ml-3">
                                                <input class="form-check-input" type="checkbox" name="not_print_invoice" id="not_print_invoice">
                                                <label style="color:rgb(136, 136, 136);" class="form-check-label" for="not_print_invoice">
                                                    {{ __('db.Do not print Invoice') }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- order_discount modal -->
                <div id="order-discount-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
                    <div role="document" class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">{{__('db.Order Discount')}}</h5>
                                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></span></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        @php
                                            $selected_discount_type = old('order_discount_type_select') 
                                                ?? ($lims_sale_data->order_discount_type ?? 'Flat');
                                        @endphp
                                        <label>{{__('db.Order Discount Type')}}</label>
                                        <select id="order-discount-type" name="order_discount_type_select" class="form-control">
                                            <option value="Flat" {{ $selected_discount_type == 'Flat' ? 'selected' : '' }}>
                                                {{ __('db.Flat') }}
                                            </option>
                                            <option value="Percentage" {{ $selected_discount_type == 'Percentage' ? 'selected' : '' }}>
                                                {{ __('db.Percentage') }}
                                            </option>
                                        </select>
                                        <input type="hidden" name="order_discount_type">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>{{__('db.Value')}}</label>
                                        <input type="text" name="order_discount_value" class="form-control numkey" id="order-discount-val" >
                                        <input type="hidden" name="order_discount" class="form-control" id="order-discount" >
                                    </div>
                                </div>
                                <button type="button" name="order_discount_btn" class="btn btn-primary" data-dismiss="modal">{{__('db.submit')}}</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- coupon modal -->
                <div id="coupon-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
                    <div role="document" class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">{{__('db.Coupon Code')}}</h5>
                                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></span></button>
                            </div>
                            @php
                                $coupon_code = '';
                                if (isset($lims_sale_data)) {
                                    $lims_coupon_data = $lims_coupon_list->where('id', $lims_sale_data->coupon_id)->first();
                                    $coupon_code = $lims_coupon_data ? $lims_coupon_data->code : '';
                                }
                            @endphp
                            <div class="modal-body">
                                <div class="form-group">
                                    <input type="text" id="coupon-code" class="form-control" placeholder="{{ __('db.Type Coupon Code...') }}" value="{{ $coupon_code }}">
                                </div>
                                <button type="button" class="btn btn-primary coupon-check" data-dismiss="modal">{{__('db.submit')}}</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- order_tax modal -->
                <div id="order-tax" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
                    <div role="document" class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">{{__('db.Order Tax')}}</h5>
                                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></span></button>
                            </div>
                            <div class="modal-body">
                                <div class="form-group">
                                    <input type="hidden" name="order_tax_rate">
                                    <select class="form-control" name="order_tax_rate_select" id="order-tax-rate-select">
                                        <option value="0">No Tax</option>
                                        @foreach($lims_tax_list as $tax)
                                        <option value="{{$tax->rate}}">{{$tax->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" name="order_tax_btn" class="btn btn-primary" data-dismiss="modal">{{__('db.submit')}}</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- shipping_cost modal -->
                <div id="shipping-cost-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
                    <div role="document" class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">{{__('db.Shipping Cost')}}</h5>
                                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></span></button>
                            </div>
                            <div class="modal-body">
                                <div class="form-group">
                                    <input type="text" name="shipping_cost" class="form-control numkey" id="shipping-cost-val" step="any" >
                                </div>
                                <button type="button" name="shipping_cost_btn" class="btn btn-primary" data-dismiss="modal">{{__('db.submit')}}</button>
                            </div>
                        </div>
                    </div>
                </div>

                {!! Form::close() !!}

                {{-- invoice modal start --}}
                <div id="invoice-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
                    <div role="document" class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></span></button>
                            </div>
                            <div id="invoice-modal-content" class="modal-body">
                            </div>
                        </div>
                    </div>
                </div>
                {{-- invoice modal end --}}

                <!-- product edit modal -->
                <div id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
                    <div role="document" class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 id="modal_header" class="modal-title"></h5>
                                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></span></button>
                            </div>
                            <div class="modal-body">
                                <form>
                                    <div class="row modal-element">
                                        <div class="col-md-4 form-group">
                                            <label>{{__('db.Quantity')}}</label>
                                            <input type="text" name="edit_qty" class="form-control numkey">
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>{{__('db.Delivered quantity')}}</label>
                                            <input type="number" step="any" name="edit_d_qty" class="form-control numkey">
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>{{__('db.Unit Discount')}}</label>
                                            <input type="text" name="edit_discount" class="form-control numkey">
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>{{__('db.Price Option')}}</strong> </label>
                                                <div class="input-group">
                                                    <select class="form-control selectpicker" name="price_option" class="price-option">
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>{{__('db.Unit Price')}}</label>
                                            <input type="text" name="edit_unit_price" class="form-control numkey" step="any">
                                        </div>
                                        <?php
                                        $tax_name_all[] = 'No Tax';
                                        $tax_rate_all[] = 0;
                                        foreach ($lims_tax_list as $tax) {
                                            $tax_name_all[] = $tax->name;
                                            $tax_rate_all[] = $tax->rate;
                                        }
                                        ?>
                                        <div class="col-md-4 form-group">
                                            <label>{{__('db.Tax Rate')}}</label>
                                            <select name="edit_tax_rate" class="form-control selectpicker">
                                                @foreach($tax_name_all as $key => $name)
                                                <option value="{{$key}}">{{$name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div id="edit_unit" class="col-md-4 form-group">
                                            <label>{{__('db.Product Unit')}}</label>
                                            <select name="edit_unit" class="form-control selectpicker">
                                            </select>
                                        </div>
                                        <div class="col-md-4 form-group">
                                            <label>{{__('db.Cost')}}</label>
                                            <p id="product-cost"></p>
                                        </div>
                                    </div>
                                    <button type="button" name="update_btn" class="btn btn-primary">{{__('db.update')}}</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- add customer modal -->
                <div id="addCustomer" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
                    <div role="document" class="modal-dialog">
                        <div class="modal-content">
                            {!! Form::open(['route' => 'customer.store', 'method' => 'post', 'files' => true, 'id' => 'customer-form']) !!}
                            <div class="modal-header">
                                <h5 id="exampleModalLabel" class="modal-title">{{__('db.Add Customer')}}</h5>
                                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></span></button>
                            </div>
                            <div class="modal-body">
                                <p class="italic"><small>{{__('db.The field labels marked with * are required input fields')}}.</small></p>
                                <div class="form-group">
                                    <label>{{__('db.Customer Group')}} *</strong> </label>
                                    <select required class="form-control selectpicker" name="customer_group_id">
                                        @foreach($lims_customer_group_all as $customer_group)
                                        <option value="{{$customer_group->id}}">{{$customer_group->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>{{__('db.name')}} *</strong> </label>
                                    <input type="text" name="customer_name" required class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>{{__('db.Email')}}</label>
                                    <input type="text" name="email" placeholder="example@example.com" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>{{__('db.Phone Number')}} *</label>
                                    <input type="text" name="phone_number" required class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>{{__('db.Address')}}</label>
                                    <input type="text" name="address" required class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>{{__('db.City')}}</label>
                                    <input type="text" name="city" required class="form-control">
                                </div>
                                <div class="form-group">
                                    <input type="hidden" name="pos" value="1">
                                    <button type="button" class="btn btn-primary customer-submit-btn">{{__('db.submit')}}</button>

                                </div>
                            </div>
                            {{ Form::close() }}
                        </div>
                    </div>
                </div>
                <!-- recent transaction modal -->
                <div id="recentTransaction" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
                    <div role="document" class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 id="exampleModalLabel" class="modal-title">{{__('db.Recent Transaction')}}
                                    <div class="badge badge-primary">{{__('db.latest')}} 10</div>
                                </h5>
                                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></span></button>
                            </div>
                            <div class="modal-body">
                                <ul class="nav nav-tabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" href="#sale-latest" role="tab" data-toggle="tab">{{__('db.Sale')}}</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="#draft-latest" role="tab" data-toggle="tab">{{__('db.Draft')}}</a>
                                    </li>
                                </ul>
                                <div class="tab-content">
                                    <div role="tabpanel" class="tab-pane show active" id="sale-latest">
                                        <div class="table-responsive">
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>{{__('db.date')}}</th>
                                                        <th>{{__('db.reference')}}</th>
                                                        <th>{{__('db.customer')}}</th>
                                                        <th>{{__('db.grand total')}}</th>
                                                        <th>{{__('db.action')}}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>

                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div role="tabpanel" class="tab-pane fade" id="draft-latest">
                                        <div class="table-responsive">
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>{{__('db.date')}}</th>
                                                        <th>{{__('db.reference')}}</th>
                                                        <th>{{__('db.customer')}}</th>
                                                        <th>{{__('db.grand total')}}</th>
                                                        <th>{{__('db.action')}}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sale detaisl -->
                <div id="get-sale-details" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
                    <div role="document" class="modal-dialog">
                        <div class="modal-content">
                            <div class="container mt-3 pb-2 border-bottom">
                                <div class="row">
                                    <div class="col-md-6 d-print-none">
                                        <button id="print-btn" type="button" class="btn btn-default btn-sm"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-3 0h.008v.008H15V10.5Z" /></svg> {{__('db.Print')}}</button>

                                        {{ Form::open(['route' => 'sale.sendmail', 'method' => 'post', 'class' => 'sendmail-form'] ) }}
                                            <input type="hidden" name="sale_id">
                                            <button class="btn btn-default btn-sm d-print-none"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg> {{__('db.Email')}}</button>
                                        {{ Form::close() }}
                                    </div>
                                    <div class="col-md-6 d-print-none">
                                        <button type="button" id="close-btn" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></span></button>
                                    </div>
                                    <div class="col-md-4 text-left">
                                        <img src="{{url('logo', $general_setting->site_logo)}}" width="90px;">
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <h3 id="exampleModalLabel" class="modal-title container-fluid">{{$general_setting->site_title}}</h3>
                                    </div>
                                    <div class="col-md-4 text-right">
                                        <i style="font-size: 15px;">{{__('db.Sale Details')}}</i>
                                    </div>
                                </div>
                            </div>
                            <div id="sale-content" class="modal-body">
                            </div>
                            <br>
                            <table class="table table-bordered product-sale-list">
                                <thead>
                                    <th>#</th>
                                    <th>{{__('db.product')}}</th>
                                    <th>{{__('db.Batch No')}}</th>
                                    <th>{{__('db.Qty')}}</th>
                                    <th>{{__('db.Returned')}}</th>
                                    <th>{{__('db.Unit Price')}}</th>
                                    <th>{{__('db.Tax')}}</th>
                                    <th>{{__('db.Discount')}}</th>
                                    <th>{{__('db.Subtotal')}}</th>
                                    <th>{{__('db.Delivered')}}</th>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                            <div id="sale-footer" class="modal-body"></div>
                        </div>
                    </div>
                </div>
                <!-- add cash register modal -->
                <div id="cash-register-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
                    <div role="document" class="modal-dialog">
                        <div class="modal-content">
                            {!! Form::open(['route' => 'cashRegister.store', 'method' => 'post']) !!}
                            <div class="modal-header">
                                <h5 id="exampleModalLabel" class="modal-title">{{__('db.Add Cash Register')}}</h5>
                                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></span></button>
                            </div>
                            <div class="modal-body">
                                <p class="italic"><small>{{__('db.The field labels marked with * are required input fields')}}.</small></p>
                                <div class="row">
                                    <div class="col-md-6 form-group warehouse-section">
                                        <label>{{__('db.Warehouse')}} *</strong> </label>
                                        <select required name="warehouse_id" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="{{ __('db.Select warehouse...') }}">
                                            @foreach($lims_warehouse_list as $warehouse)
                                            <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                                            @endforeach
                                        </select>
                                        <x-validation-error fieldName="warehouse_id" />
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>{{__('db.Cash in Hand')}} *</strong> </label>
                                        <input type="number" step="any" name="cash_in_hand" required class="form-control">
                                    </div>
                                    <div class="col-md-12 form-group">
                                        <button type="submit" class="btn btn-primary">{{__('db.submit')}}</button>
                                    </div>
                                </div>
                            </div>
                            {{ Form::close() }}
                        </div>
                    </div>
                </div>
                <!-- cash register details modal -->
                <div id="register-details-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
                    <div role="document" class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 id="exampleModalLabel" class="modal-title">{{__('db.Cash Register Details')}}</h5>
                                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></span></button>
                            </div>
                            <div class="modal-body">
                                <p>{{__('db.Please review the transaction and payments')}}</p>
                                <div class="row">
                                    <div class="col-md-12">
                                        <table class="table table-hover">
                                            <tbody>
                                                <tr>
                                                    <td>{{__('db.Cash in Hand')}}:</td>
                                                    <td id="cash_in_hand" class="text-right">0</td>
                                                </tr>
                                                <tr>
                                                    <td>{{__('db.Total Sale Amount')}}:</td>
                                                    <td id="total_sale_amount" class="text-right"></td>
                                                </tr>
                                                <tr>
                                                    <td>{{__('db.Total Payment')}}:</td>
                                                    <td id="total_payment" class="text-right"></td>
                                                </tr>
                                                @if(in_array("cash",$options))
                                                <tr>
                                                    <td>{{__('db.Cash Payment')}}:</td>
                                                    <td id="cash_payment" class="text-right"></td>
                                                </tr>
                                                @endif
                                                @if(in_array("card",$options))
                                                <tr>
                                                    <td>{{__('db.Credit Card Payment')}}:</td>
                                                    <td id="credit_card_payment" class="text-right"></td>
                                                </tr>
                                                @endif
                                                @if(in_array("cheque",$options))
                                                <tr>
                                                    <td>{{__('db.Cheque Payment')}}:</td>
                                                    <td id="cheque_payment" class="text-right"></td>
                                                </tr>
                                                @endif
                                                @if(in_array("gift_card",$options))
                                                <tr>
                                                    <td>{{__('db.Gift Card Payment')}}:</td>
                                                    <td id="gift_card_payment" class="text-right"></td>
                                                </tr>
                                                @endif
                                                @if(in_array("deposit",$options))
                                                <tr>
                                                    <td>{{__('db.Deposit Payment')}}:</td>
                                                    <td id="deposit_payment" class="text-right"></td>
                                                </tr>
                                                @endif
                                                @if(in_array("paypal",$options) && (strlen(env('PAYPAL_LIVE_API_USERNAME'))>0) && (strlen(env('PAYPAL_LIVE_API_PASSWORD'))>0) && (strlen(env('PAYPAL_LIVE_API_SECRET'))>0))
                                                <tr>
                                                    <td>{{__('db.Paypal Payment')}}:</td>
                                                    <td id="paypal_payment" class="text-right"></td>
                                                </tr>
                                                @endif
                                                <tr>
                                                    <td>{{__('db.Total Sale Return')}}:</td>
                                                    <td id="total_sale_return" class="text-right"></td>
                                                </tr>
                                                <tr>
                                                    <td>{{__('db.Total Expense')}}:</td>
                                                    <td id="total_expense" class="text-right"></td>
                                                </tr>
                                                <tr>
                                                    <td>{{__('db.Total Supplier Payment')}}:</td>
                                                    <td id="total_supplier_payment" class="text-right"></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>{{__('db.Total Cash')}}:</strong></td>
                                                    <td id="total_cash" class="text-right"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="col-md-6" id="closing-section">
                                        <form action="{{route('cashRegister.close')}}" method="POST">
                                            @csrf
                                            <input type="hidden" name="cash_register_id">
                                            <button type="submit" class="btn btn-primary">{{__('db.Close Register')}}</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- today sale modal -->
                <div id="today-sale-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
                    <div role="document" class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 id="exampleModalLabel" class="modal-title">{{__('db.Today Sale')}}</h5>
                                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></span></button>
                            </div>
                            <div class="modal-body">
                                <p>{{__('db.Please review the transaction and payments')}}</p>
                                <div class="row">
                                    <div class="col-md-12">
                                        <table class="table table-hover">
                                            <tbody>
                                                <tr>
                                                    <td>{{__('db.Total Sale Amount')}}:</td>
                                                    <td class="total_sale_amount text-right"></td>
                                                </tr>
                                                <tr>
                                                    <td>{{__('db.Cash Payment')}}:</td>
                                                    <td class="cash_payment text-right"></td>
                                                </tr>
                                                <tr>
                                                    <td>{{__('db.Credit Card Payment')}}:</td>
                                                    <td class="credit_card_payment text-right"></td>
                                                </tr>
                                                <tr>
                                                    <td>{{__('db.Cheque Payment')}}:</td>
                                                    <td class="cheque_payment text-right"></td>
                                                </tr>
                                                <tr>
                                                    <td>{{__('db.Gift Card Payment')}}:</td>
                                                    <td class="gift_card_payment text-right"></td>
                                                </tr>
                                                <tr>
                                                    <td>{{__('db.Deposit Payment')}}:</td>
                                                    <td class="deposit_payment text-right"></td>
                                                </tr>
                                                @if(in_array("paypal",$options) && (strlen(env('PAYPAL_LIVE_API_USERNAME'))>0) && (strlen(env('PAYPAL_LIVE_API_PASSWORD'))>0) && (strlen(env('PAYPAL_LIVE_API_SECRET'))>0))
                                                <tr>
                                                    <td>{{__('db.Paypal Payment')}}:</td>
                                                    <td class="paypal_payment text-right"></td>
                                                </tr>
                                                @endif
                                                <tr>
                                                    <td>{{__('db.Total Payment')}}:</td>
                                                    <td class="total_payment text-right"></td>
                                                </tr>
                                                <tr>
                                                    <td>{{__('db.Total Sale Return')}}:</td>
                                                    <td class="total_sale_return text-right"></td>
                                                </tr>
                                                <tr>
                                                    <td>{{__('db.Total Expense')}}:</td>
                                                    <td class="total_expense text-right"></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>{{__('db.Total Cash')}}:</strong></td>
                                                    <td class="total_cash text-right"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- today profit modal -->
                <div id="today-profit-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
                    <div role="document" class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 id="exampleModalLabel" class="modal-title">{{__('db.Today Profit')}}</h5>
                                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></span></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <select required name="warehouseId" class="form-control">
                                            <option value="0">{{__('db.All Warehouse')}}</option>
                                            @foreach($lims_warehouse_list as $warehouse)
                                            <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-12 mt-2">
                                        <table class="table table-hover">
                                            <tbody>
                                                <tr>
                                                    <td>{{__('db.Product Revenue')}}:</td>
                                                    <td class="product_revenue text-right"></td>
                                                </tr>
                                                <tr>
                                                    <td>{{__('db.Product Cost')}}:</td>
                                                    <td class="product_cost text-right"></td>
                                                </tr>
                                                <tr>
                                                    <td>{{__('db.Expense')}}:</td>
                                                    <td class="expense_amount text-right"></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>{{__('db.Profit')}}:</strong></td>
                                                    <td class="profit text-right"></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</section>
<section id="print-layout" class="">
</section>
<div style="width:100%;max-width:350px;position:fixed;top:5%;left:50%;transform:translateX(-50%);z-index:999">
    <button type="button" class="btn btn-danger" id="closeScannerBtn" style="display:none"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
    <div id="reader" style="width:100%;"></div>
</div>
@endsection

<!-- expense modal -->
<div id="expense-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
        <h5 id="exampleModalLabel" class="modal-title">{{ __('db.Add Expense') }}</h5>
        <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
        </div>
        <div class="modal-body">
        <p class="italic"><small>{{ __('db.The field labels marked with * are required input fields') }}.</small></p>
        {!! Form::open(['route' => 'expenses.store', 'method' => 'post']) !!}
        <?php
        $lims_expense_category_list = DB::table('expense_categories')->where('is_active', true)->get();
        if (Auth::user()->role_id > 2)
            $lims_warehouse_list = DB::table('warehouses')->where([
            ['is_active', true],
            ['id', Auth::user()->warehouse_id]
            ])->get();
        else
            $lims_warehouse_list = DB::table('warehouses')->where('is_active', true)->get();
        $lims_account_list = \App\Models\Account::where('is_active', true)->get();
        ?>
        <div class="row">
            <div class="col-md-6 form-group">
            <label>{{ __('db.Date') }}</label>
            <input type="text" name="created_at" class="form-control date" placeholder="{{__('db.Choose date')}}" value="{{date($general_setting->date_format,strtotime('now'))}}"/>
            </div>
            <div class="col-md-6 form-group">
            <label>{{ __('db.Expense Category') }} *</label>
            <select name="expense_category_id" class="selectpicker form-control" required data-live-search="true" data-live-search-style="begins" title="{{ __('db.Select Expense Category...') }}">
                @foreach($lims_expense_category_list as $expense_category)
                <option value="{{$expense_category->id}}">{{$expense_category->name . ' (' . $expense_category->code. ')'}}</option>
                @endforeach
            </select>
            </div>
            <div class="col-md-6 form-group">
            <label>{{ __('db.Warehouse') }} *</label>
            <select name="warehouse_id" class="selectpicker form-control" required data-live-search="true" data-live-search-style="begins" title="{{ __('db.Select Warehouse...') }}">
                @foreach($lims_warehouse_list as $warehouse)
                <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                @endforeach
            </select>
            </div>
            <div class="col-md-6 form-group">
            <label>{{ __('db.Amount') }} *</label>
            <input type="number" name="amount" step="any" required class="form-control">
            </div>
            <div class="col-md-6 form-group">
            <label> {{ __('db.Account') }}</label>
            <select class="form-control selectpicker" name="account_id">
                @foreach($lims_account_list as $account)
                @if($account->is_default)
                <option selected value="{{$account->id}}">{{$account->name}} [{{$account->account_no}}]</option>
                @else
                <option value="{{$account->id}}">{{$account->name}} [{{$account->account_no}}]</option>
                @endif
                @endforeach
            </select>
            </div>
        </div>
        <div class="form-group">
            <label>{{ __('db.Note') }}</label>
            <textarea name="note" rows="3" class="form-control"></textarea>
        </div>
        <div class="form-group">
            <input type="hidden" name="cash_register" value="" />
            <button type="submit" class="btn btn-primary">{{ __('db.submit') }}</button>
        </div>
        {{ Form::close() }}
        </div>
    </div>
    </div>
</div>
<!-- end expense modal -->

<!-- supplier payment modal -->
<div id="add-supplier-payment" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 id="exampleModalLabel" class="modal-title">{{__('db.Add Payment')}}</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
                {!! Form::open(['route' => 'supplier.clearDue', 'method' => 'post', 'class' => 'supplier-payment-form' ]) !!}
                    <div class="row">
                        <div class="col-md-6 mt-1">
                            <label>{{__('db.Supplier')}}</label>
                            <select name="supplier_id" id="supplier_list" class="form-control" data-live-search="true" data-live-search-style="begins" title="{{ __('db.Select Supplier...') }}">
                                <option value=""></option>
                            </select>
                        </div>
                        <div class="col-md-6 mt-1">
                            <label>{{__('db.Due')}}</label>
                            <input type="number" class="form-control" readonly name="balance">
                        </div>
                        <div class="col-md-12 mt-1">
                            <label>{{__('db.Amount')}} *</label>
                            <input type="number" name="amount" step="any" class="form-control" required>
                        </div>
                        <div class="col-md-12 mt-1">
                            <label>{{__('db.Note')}}</label>
                            <textarea name="note" rows="4" class="form-control"></textarea>
                        </div>
                    </div>
                    <input type="hidden" name="cash_register" value="" />
                    <button type="submit" class="btn btn-primary">{{__('db.submit')}}</button>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</div>
<!-- end supplier payment modal -->


@push('scripts')
<script src="https://unpkg.com/html5-qrcode"></script>
<audio id="audio" src="{{url('beep/beep-07.mp3')}}" autoplay="false" ></audio>
<script>
    function playSound() {
        var sound = document.getElementById("audio");
        sound.play();
    }
    
    $(document).ready(function() {

        $('#product-search-input').focus();

        //Get featured product data on right section
        $.get('{{ url("sales/getproducts") }}/' + warehouse_id + '/featured/1', function(response) {
            populateProduct(response);
        });

        let typingTimer;
        const doneTypingInterval = 300;
        const $input = $('#product-search-input');
        const $results = $('#product-results-container');
        const $noResults = $('#no-results-message');

        function clearResults() {
            $results.empty().css('padding', '0');
            $noResults.hide();
        }

        function searchProducts(search) {
            $results.css('padding', '0 10px 15px');
            $results.html('<div class="loader " title="4" style="border:none;min-height:300px"><svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="24px" height="30px" viewBox="0 0 24 30" style="enable-background:new 0 0 50 50;" xml:space="preserve"><rect x="0" y="0" width="4" height="10" fill="#333"><animateTransform attributeType="xml" attributeName="transform" type="translate" values="0 0; 0 20; 0 0" begin="0" dur="0.6s" repeatCount="indefinite"></animateTransform></rect><rect x="10" y="0" width="4" height="10" fill="#333"><animateTransform attributeType="xml" attributeName="transform" type="translate" values="0 0; 0 20; 0 0" begin="0.2s" dur="0.6s" repeatCount="indefinite"></animateTransform></rect><rect x="20" y="0" width="4" height="10" fill="#333"><animateTransform attributeType="xml" attributeName="transform" type="translate" values="0 0; 0 20; 0 0" begin="0.4s" dur="0.6s" repeatCount="indefinite"></animateTransform></rect></svg></div>');
            $noResults.hide();

            $.ajax({
                url: '{{url("/")}}/sales/search/' + warehouse_id + '/' + search,
                type: 'GET',
                success: function (data) {
                    $results.empty();
                    if (data.length > 0) {
                        $noResults.hide();
                        data.forEach(function (product) {
                            let productHtml = '';
                            let displayStock = '';

                            if(authUser > 2) {
                                displayStock = '';
                            } else {
                                displayStock = ` | ${product.qty} {{ __('db.In Stock') }} `;
                            }

                            var batch_id = product.product_batch_id ? product.product_batch_id : '';

                            if (product.is_imei == '1' || product.is_imei === 1 || product.is_imei === true) {
                                productHtml = `
                                    <div class="product-img" data-code="${product.code}" 
                                                            data-qty="${product.qty}" 
                                                            data-imei="${product.imei_number}" 
                                                            data-embedded="${product.is_embeded}" 
                                                            data-batch="${batch_id}" 
                                                            data-price="${product.price}">
                                        ${product.name} (${product.code}) | ${product.price} | IMEI: ${product.imei_number}
                                    </div>
                                `;
                            } else if (product.product_batch_id != null) {
                                if(parseInt(product.qty) > 0){
                                    if(product.expired_date == 0) {
                                        product.expired_date = "{{__('db.expired')}}";
                                        var expired = "expired";
                                    } 
                                    productHtml = `
                                        <div class="product-img ${expired}" data-code="${product.code}" 
                                                                            data-qty="${product.qty}" 
                                                                            data-imei="${product.is_imei}" 
                                                                            data-embedded="${product.is_embeded}" 
                                                                            data-batch="${batch_id}" 
                                                                            data-price="${product.price}">
                                            ${product.name} (${product.code}) - ${product.expired_date} | ${product.price} ${displayStock}
                                        </div>
                                    `;
                                }
                            } else {
                                productHtml = `
                                    <div class="product-img" data-code="${product.code}" 
                                                            data-qty="${product.qty}" 
                                                            data-imei="${product.is_imei}" 
                                                            data-embedded="${product.is_embeded}" 
                                                            data-batch="${batch_id}" 
                                                            data-price="${product.price}">
                                        ${product.name} (${product.code}) | ${product.price} ${displayStock}
                                    </div>
                                `;
                            }

                            $results.append(productHtml);
                        });

                        $('.product-img').on('click', function () {
                            clearResults();
                        });

                        // Auto-click if only one result
                        if (data.length === 1) {
                            if(click === 0){
                                $('#product-results-container .product-img').first().trigger('click');
                            }
                            clearResults();
                            click = 1;
                        }

                    } else {
                        clearResults();
                        $noResults.show();
                    }
                },
                error: function () {
                    $noResults.text("Error searching products.").show();
                }
            });
        }

        var click = 0;

        // Trigger on input
        $input.on('input', function () {
            const value = $(this).val().trim();
            if (value.length >= 3) {
                click = 0;
                clearTimeout(typingTimer);
                typingTimer = setTimeout(() => searchProducts(value), doneTypingInterval);
            } else {
                clearResults();
            }
        });

        // Trigger on paste
        $input.on('paste', function (e) {
            const pastedData = (e.originalEvent || e).clipboardData.getData('text');
            if (pastedData.length >= 3) {
                click = 0;
                searchProducts(pastedData.trim());
            }
        });

        // Trigger on blur (click outside)
        $input.on('blur', function () {
            setTimeout(() => {
                // only clear if results are still visible/non-empty
                if ($results.children().length > 0) {
                    clearResults();
                }
            }, 150); // slight delay so clicks still register
        });

    });
</script>


<script>
    const closeScannerBtn = document.getElementById("closeScannerBtn");
    const scanner = document.getElementById("reader");
    const html5Qrcode = new Html5Qrcode('reader');

    function barcode() {
        const qrCodeSuccessCallback = (decodedText, decodedResult) => {
            if (decodedText) {
                document.getElementById('lims_productcodeSearch').value = decodedText;
                html5Qrcode.stop();
                closeScannerBtn.style.display = "none";
            }
        };

        const config = {
            fps: 30,
            qrbox: { width: 300, height: 100 },
            // ðŸ‘‡ Add this line to support Code128
            // formatsToSupport: [ Html5QrcodeSupportedFormats.CODE_128 ]
        };

        html5Qrcode.start({ facingMode: "environment" }, config, qrCodeSuccessCallback);
        closeScannerBtn.style.display = "inline-block";
    }

    closeScannerBtn.addEventListener("click", function () {
        closeScannerBtn.style.display = "none";
        html5Qrcode.stop();
    });
</script>

<script>
    var isEditMode = {{ isset($lims_sale_data) ? 1 : 0 }};

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    ////Start the code is for SaleproSaas///
    @if(config('database.connections.saleprosaas_landlord'))
        numberOfInvoice = <?php echo json_encode($numberOfInvoice)?>;
        $.ajax({
            type: 'GET',
            async: false,
            url: '{{route("package.fetchData", $general_setting->package_id)}}',
            success: function(data) {
                if(data['number_of_invoice'] > 0 && data['number_of_invoice'] <= numberOfInvoice) {
                    location.href = "{{route('sales.index')}}";
                }
            }
        });
    @endif
    ////End the code is for SaleproSaas///

    ///NOT NEEDED - Check///
    $("ul#sale").siblings('a').attr('aria-expanded','true');
    $("ul#sale").addClass("show");
    $("ul#sale #sale-pos-menu").addClass("active");
    ///NOT NEEDED - Check///

    ///start code for mobile////
    var isMobile = false;
    if (($(window).width() < 767)) {
        isMobile = true;
    }

    if(isMobile ==  true){
        $('.loading-message').hide();
        $('.table-container').hide();
        $('.more-payment-options > div > div').addClass('dropdown-menu');
        $('#collapseProducts').addClass('collapse');
        $('#grand-total-m').html($('input[name="grand_total"]').val());
    }


    @if($lims_pos_setting_data)
    var public_key = <?php echo json_encode($lims_pos_setting_data->stripe_public_key) ?>;
    @endif
    var without_stock = <?php echo json_encode($general_setting->without_stock) ?>;
    var alert_product = <?php echo json_encode($alert_product) ?>;
    var currency = <?php echo json_encode($currency) ?>;
    var valid;
    var authUser = <?php echo json_encode($authUser) ?>;
    // array data depend on warehouse
    var lims_product_array = [];
    var product_code = [];
    var product_name = [];
    var product_qty = [];
    var product_type = [];
    var product_id = [];
    var product_list = [];
    var qty_list = [];

    // array data with selection
    var product_price = [];
    var wholesale_price = [];
    var cost = [];
    var product_discount = [];
    var tax_rate = [];
    var tax_name = [];
    var tax_method = [];
    var unit_name = [];
    var unit_operator = [];
    var unit_operation_value = [];
    var is_imei = [];
    var is_variant = [];
    var gift_card_amount = [];
    var gift_card_expense = [];

    // temporary array
    var temp_unit_name = [];
    var temp_unit_operator = [];
    var temp_unit_operation_value = [];

    var deposit = <?php echo json_encode($deposit) ?>;
    var points = <?php echo json_encode($points) ?>;
    var reward_point_setting = <?php echo json_encode($lims_reward_point_setting_data) ?>;

    @if($lims_pos_setting_data)
    var product_row_number = <?php echo json_encode($lims_pos_setting_data->product_number) ?>;
    @endif
    var rowindex;
    var customer_group_rate;
    var row_product_price;
    var pos;
    var keyboard_active = <?php echo json_encode($keybord_active); ?>;
    var role_id = <?php echo json_encode(\Auth::user()->role_id) ?>;
    var warehouse_id = $('#warehouse_id').val();
    var coupon_list = <?php echo json_encode($lims_coupon_list) ?>;
    var currency = <?php echo json_encode($currency) ?>;
    var currencyChange = false;
    var all_permission = '<?php echo json_encode($all_permission) ?>';
    var next_page_url;

    $(window).on('load', async function () {
        //await getProduct(warehouse_id);

        var customer_id = $('#customer_id').val();
        var cus_gr_rt = await $.get('{{ url("sales/getcustomergroup") }}/' + customer_id);
        customer_group_rate = (cus_gr_rt / 100);

        isCashRegisterAvailable(warehouse_id);


        //Get recents sale when clicking recent transaction button
        $.get('{{url("sales/recent-sale")}}', function(data) {
            populateRecentSale(data);
        });
        //Get recents draft when clicking recent transaction button
        $.get('{{url("/sales/recent-draft")}}', function(data) {
            populateRecentDraft(data);
        });

        if(isEditMode){
            processDraftData();
        }

    })

    ///category button
    $('#category-filter').on('click', function(e){
        e.stopPropagation();
        $('.filter-window').show('slide', {direction: 'right'}, 'fast');
        $('.category').show();
        $('.brand').hide();
        $('.products-m').hide();
        $(".table-container").removeClass('brand').removeClass('featured').addClass('category');
    });

    //click on category image on the filter window shown after clicking the category button
    $(document).on('click','.category-img', function(){
        let category_id = $(this).data('category');
        $('.filter-window').hide('slide', {direction: 'right'}, 'fast');
        $(".table-container").children().remove();
        $.get('{{ url("sales/getproducts") }}/' + warehouse_id + '/category/'+ category_id, function(response) {
            populateProduct(response);
        });

        if(isMobile == true){
            $('.filter-window').show('slide', {direction: 'right'}, 'fast');
        }
    });

    ///brand button
    $('#brand-filter').on('click', function(e){
        e.stopPropagation();
        $('.filter-window').show('slide', {direction: 'right'}, 'fast');
        $('.brand').show();
        $('.category').hide();
        $('.products-m').hide();
        $(".table-container").removeClass('category').removeClass('featured').addClass('brand');
    });

    //click on brand image on the filter window shown after clicking the brand button
    $(document).on('click','.brand-img', function(){
        var brand_id = $(this).data('brand');
        $('.filter-window').hide('slide', {direction: 'right'}, 'fast');
        $(".table-container").children().remove();
        $.get('{{ url("sales/getproducts") }}/' + warehouse_id + '/brand/'+ brand_id, function(response) {
            populateProduct(response);
        });

        if(isMobile == true){
            $('.filter-window').show('slide', {direction: 'right'}, 'fast');
        }
    });

    ///featured button
    $('#featured-filter').on('click', function(e){
        $(".table-container").removeClass('category').removeClass('brand').addClass('featured');

        $.get('{{ url("sales/getproducts") }}/' + warehouse_id + '/featured/1', function(response) {
            populateProduct(response);
        });

        if(isMobile == true){
            e.stopPropagation();
            $(".product_list_mobile.table-container").show();
            $('.product_list_mobile').html('');
            let featured_products = $(".table-container .product-grid").clone();
            $('.product_list_mobile').html(featured_products);
            $('.filter-window').show('slide', {direction: 'right'}, 'fast');
            $('.brand').hide();
            $('.category').hide();
        }
    });

    //close button on filter-window
    $(document).on('click','.btn-close', function(e){
        $('.filter-window').hide('slide', {direction: 'right'}, 'fast');
        $(".table-container").removeClass('category').removeClass('brand').removeClass('featured');
        if(isMobile == true){
            $(".table-container").hide();
        }
    });

    /// Start Load more button function///
    $(document).on('click', '.load-more', function() {
        $.ajax({
            url: next_page_url,
            type: "get",
        }).done(function(response) {
            appendProduct(response);
        });
    });

    $('#warehouse_id').on('change', function() {
        warehouse_id = $(this).val();
        // getProduct(warehouse_id);
        isCashRegisterAvailable(warehouse_id);
        $('#featured-filter').trigger('click');
    });

    $('#customer_id').on('change', function() {
        var customer_id = $(this).val();
        $.get('{{url("sales/getcustomergroup")}}/' + customer_id, function(data) {
            customer_group_rate = (data / 100);
        });
    });

    function isCashRegisterAvailable(warehouse_id) {
        $.ajax({
            url: '{{url("cash-register/check-availability")}}/'+warehouse_id,
            type: "GET",
            success:function(data) {
                if(data == 'false') {
                $("#register-details-btn").addClass('d-none');
                $('#cash-register-modal select[name=warehouse_id]').val(warehouse_id);

                if(role_id <= 2)
                    $("#cash-register-modal .warehouse-section").removeClass('d-none');
                else
                    $("#cash-register-modal .warehouse-section").addClass('d-none');

                $('.selectpicker').selectpicker('refresh');
                $("#cash-register-modal").modal('show');
                }
                else{
                    $("#register-details-btn").removeClass('d-none');
                    $("#register-details-btn").data('id', data);
                    $('input[name="cash_register"]').val(data);
                }
            }
        });
    }

    function populateProduct(response) {
        var tableData = '<div class="product-grid">';
        $.each(response.data['name'], function(index) {
            if(response.data['image'][index])
                image = response.data['image'][index];
            else
                image = 'zummXD2dvAtI.png';
            tableData += '<div class="product-img sound-btn" title="'+response.data['name'][index]+'" data-code = "'+response.data['code'][index]+'" data-qty="'+response.data['qty'][index]+'" data-imei="'+response.data['is_imei'][index]+'" data-embedded="'+response.data['is_embeded'][index]+'" data-batch="" data-price="'+response.data['price'][index]+'"><img  src="{{url("/images/product")}}/'+image+'" width="100%" /><p>'+response.data['name'][index]+'</p><span>['+response.data['code'][index]+']</span> <span class="d-block">' + @json(__('db.Qty')) + ': '+response.data['qty'][index]+'</span></div>';
        });

        tableData += '</div>';

        next_page_url = response.next_page_url;
        if (next_page_url) {
            tableData += '<button class="btn btn-primary btn-block load-more"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3" /></svg></button>';
        }
        $(".table-container").html(tableData);

        if(isMobile){
            $('.brand').hide();
            $('.category').hide();
            $('.products-m').show();
            $(".product_list_mobile.table-container").show();
        }else{
            $(".table-container").show();
        }
    }

    function appendProduct(response) {
        var tableData = '';
        $.each(response.data['name'], function(index) {
            if(response.data['image'][index])
                image = response.data['image'][index];
            else
                image = 'zummXD2dvAtI.png';
            tableData += '<div class="product-img sound-btn" title="'+response.data['name'][index]+'" data-code = "'+response.data['code'][index]+'" data-qty="'+response.data['qty'][index]+'" data-imei="'+response.data['is_imei'][index]+'" data-embedded="'+response.data['is_embeded'][index]+'" data-batch="" data-price="'+response.data['price'][index]+'"><img  src="{{url("/images/product")}}/'+image+'" width="100%" /><p>'+response.data['name'][index]+'</p><span>'+response.data['code'][index]+'</span> <span class="d-block">' + @json(__('db.Qty')) + ': '+response.data['qty'][index]+'</span></div>';
        });
        $(".table-container .product-grid").append(tableData);

        next_page_url = response.next_page_url;
        if (!next_page_url) {
            $('.load-more').remove();
        }
    }

    $(document).on('click', '.expired', function() {
        playSound();
        alert(@json(__('db.Product is expired!')));
        return false;
    });

    $(document).on('click', '.product-img', function() {
        playSound();
        
        var customer_id = $('#customer_id').val();
        var warehouse_id = $('#warehouse_id').val();
        var biller_id = $('#biller_id').val();

        @if(in_array('restaurant',explode(',',$general_setting->modules)))
        var table_id = $('#table_id').val();
        var waiter_id = $('#waiter_id').val();
        var service_id = $('#service_id').val();
        @endif

        if(isMobile){
            $('.filter-window').hide('slide', {direction: 'right'}, 'fast');
        }
        if(!customer_id)
            alert(@json(__('db.Please select Customer!')));
        else if(!warehouse_id)
            alert(@json(__('db.Please select Warehouse!')));
        else if(!biller_id)
            alert(@json(__('db.Please select Biller!')));
        @if(in_array('restaurant',explode(',',$general_setting->modules)))
        else if(!table_id && service_id == 1){
            alert(@json(__('db.Please select Table!')));
        }
        else if(!waiter_id && service_id == 1){
            alert(@json(__('db.Please select Waiter!')));
        }
        @endif
        else{
            var data = $(this).data();
            productSearch(data);
        }
    });

    function processDraftData() {
        @if(isset($lims_sale_data))
            let draft_product_data = @json($draft_product_data);
            draft_product_data.forEach(function(product) {
                productSearch(product); // product is already an object
            });
        @endif
    }

    function productSearch(data) {
        if(data.embedded == 1) {
            alert('{{ __("db.This product has been added using the weight scale machine.")}}');
            return;
        }
        var item_code = data.code;
        var pre_qty = 0;
        var flag = true;
        $(".product-code").each(function(i) {
            if ($(this).val().trim() == item_code) {
                rowindex = i;
                if(data.imei != 'null' && data.imei != '') {
                    imeiNumbers = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .imei-number').val();
                    imeiNumbersArray = imeiNumbers.split(",");

                    if(imeiNumbersArray.includes(data.imei)) {
                        alert(@json(__('db.Same imei or serial number is not allowed!')));
                        flag = false;
                        $('#product-search-input').val('');
                        return;
                    }
                }
                pre_qty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val();
            }
        });
        if(flag)
        {
            let product = {
                code: data.code,
                qty: data.qty,
                pre_qty: (parseFloat(pre_qty) + 1),
                imei: data.imei,
                embedded: data.embedded,
                batch: data.batch,
                price: data.price,
                customer_id: $('#customer_id').val()
            };
            //data += '?'+$('#customer_id').val()+'?'+(parseFloat(pre_qty) + 1);
            $.ajax({
                type: 'GET',
                async: false,
                url: '{{url("sales/lims_product_search")}}',
                data: {
                    data: product
                },
                success: function(data) {
                    if(data[23]) {
                        data[15] = 1;
                        pre_qty = 0;
                    }
                    if(pre_qty > 0 && data[21]) {
                        var old_batch = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.batch-no').val();

                        if(old_batch && old_batch != data[22]) {
                            pre_qty = 0;
                            data[15] = 1;
                        }

                    }
                    var flag = 1;
                    if (pre_qty > 0) {
                        var qty = data[15];
                        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val(qty);
                        //pos = product_code.indexOf(data[1]);
                        
                        product_price[rowindex] = parseFloat(data[2] * currency['exchange_rate']) + parseFloat(data[2] * currency['exchange_rate'] * customer_group_rate);

                        checkDiscount(String(qty), true);
                        flag = 0;
                    }
                    $("input[name='product_code_name']").val('');

                    if(flag){
                        addNewProduct(data);
                    }
                    else if(data[18] != 'null' && data[18] != '') {
                        var imeiNumbers = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.imei-number').val();
                        if(imeiNumbers)
                            imeiNumbers += ','+data[18];
                        else
                            imeiNumbers = data[18];
                        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.imei-number').val(imeiNumbers);
                    }
                }
            });
        }

    }

    @if(!empty($lims_product_sale_data))
    const productSale = @json($lims_product_sale_data);
    @else
    const productSale = null;
    @endif

    function addNewProduct(data){
        $('.payment-btn').removeAttr('disabled');
        var newRow = $('<tr id='+ data[1] +'>');
        var cols = '';
        temp_unit_name = (data[6]).split(',');
        //pos = product_code.indexOf(data[1]);

        let stockDisplay = '';

        if (authUser > 2) {
            cols += '<td class="col-sm-5 col-6 product-title"><strong>' + data[0] + '<br><span>' + data[1] + '</span>' + stockDisplay + ' <strong class="product-price d-none"></strong>';
        } else {
            if(data[20].trim() == 'standard' || data[20].trim() == 'combo'){
                if (!data[18] || data[18] == 'null') {
                    stockDisplay = ` | {{ __('db.In Stock') }} : <span class="in-stock">` + data[19] + `</span>`;
                }
            }
            cols += '<td class="col-sm-5 col-6 product-title"><strong class="edit-product btn btn-link" data-toggle="modal" data-target="#editModal">' + data[0] + ' <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg></strong><br><span>' + data[1] + '</span>' + stockDisplay + ' <strong class="product-price d-none"></strong>';
        }

        if(data[12]) {
            cols += '<br><input style="font-size:13px;padding:3px 25px 3px 10px;height:30px !important" type="text" class="form-control batch-no" value="'+data[22]+'" required/> <input type="hidden" class="product-batch-id" name="product_batch_id[]" value="'+data[21]+'"/>';
        }
        else {
            cols += '<input type="text" class="form-control batch-no d-none" disabled/> <input type="hidden" class="product-batch-id" name="product_batch_id[]"/>';
        }

        cols += '</td>';
        cols += '<td class="col-sm-2 product-price d-none d-md-block"></td>';
    cols += '<td class="col-sm-3"><div class="input-group"><span class="input-group-btn">';
    if (!data[18] || data[18] == 'null') {
        cols += '<button type="button" class="btn btn-default minus" style="padding:5px 8px"><i class="dripicons-minus"></i></button></span>';
    }
    cols += '<input type="text" name="qty[]" class="form-control qty numkey input-number" step="any" value="'+data[15]+'" required><span class="input-group-btn">';
    if (!data[18] || data[18] == 'null') {
        cols += '<button type="button" class="btn btn-default plus" style="padding:5px 8px"><i class="dripicons-plus"></i></button>';
    }
    cols += '</span></div>';

    // Delivered quantity
    cols += '<div>{{__("db.Delivered")}}:</div>';
    cols += '<div class="input-group"><span class="input-group-btn">';
    if (!data[18] || data[18] == 'null') {
        cols += '<button type="button" class="btn btn-default dminus" style="padding:5px 8px"><i class="dripicons-minus"></i></button></span>';
    }
    cols += '<input type="text" name="dqty[]" class="form-control dqty numkey input-number" step="any" value="'+data[23]+'" required>';
    if (!data[18] || data[18] == 'null') {
        cols += '<span class="input-group-btn"><button type="button" class="btn btn-default dplus" style="padding:5px 8px"><i class="dripicons-plus"></i></button>';
    }
    cols += '</span></div>';

    // Delivery Status
    cols += '<div class="dstatus">' + data[24] + '</div>';
    cols += '<input type="hidden" name="dstatus[]" class="dstatus-value" />';

    cols += '</td>';

        cols += '<td class="col-sm-2 sub-total"></td>';
        cols += '<input type="hidden" class="product-code" name="product_code[]" value="' + data[1] + '"/>';
        cols += '<input type="hidden" class="product-id" name="product_id[]" value="' + data[9] + '"/>';
        cols += '<input type="hidden" class="product_type" name="product_type[]" value="' + data[20] + '"/>';
        cols += '<input type="hidden" class="product_price" />';
        cols += '<input type="hidden" class="sale-unit" name="sale_unit[]" value="' + temp_unit_name[0] + '"/>';
        cols += '<input type="hidden" class="net_unit_price" name="net_unit_price[]" />';
        cols += '<input type="hidden" class="discount-value" name="discount[]" />';
        cols += '<input type="hidden" class="tax-rate" name="tax_rate[]" value="' + data[3] + '"/>';
        cols += '<input type="hidden" class="tax-value" name="tax[]" />';
        cols += '<input type="hidden" class="tax-name" value="'+data[4]+'" />';
        cols += '<input type="hidden" class="tax-method" value="'+data[5]+'" />';
        cols += '<input type="hidden" class="sale-unit-operator" value="'+data[7]+'" />';
        cols += '<input type="hidden" class="sale-unit-operation-value" value="'+data[8]+'" />';
        cols += '<input type="hidden" class="subtotal-value" name="subtotal[]" />';
        if(data[18] != 'null' && data[18] != '')
            cols += '<input type="hidden" class="imei-number" name="imei_number[]" value="'+data[18]+'" />';
        else
            cols += '<input type="hidden" class="imei-number" name="imei_number[]" value="" />';
        if(data[23]){
            cols += '<input type="hidden" class="topping_product" name="topping_product[]" value="" />';
            cols += '<input type="hidden" class="topping-price" name="topping-price" value="" />';
        }

        newRow.append(cols);
        if(keyboard_active==1) {
            $("table.order-list tbody").prepend(newRow).find('.qty').keyboard({usePreview: false, layout: 'custom', display: { 'accept'  : '&#10004;', 'cancel'  : '&#10006;' }, customLayout : {
            'normal' : ['1 2 3', '4 5 6', '7 8 9','0 {dec} {bksp}','{clear} {cancel} {accept}']}, restrictInput : true, preventPaste : true, autoAccept : true, css: { container: 'center-block dropdown-menu', buttonDefault: 'btn btn-default', buttonHover: 'btn-primary',buttonAction: 'active', buttonDisabled: 'disabled'},});
        }
        else
            $("table.order-list tbody").prepend(newRow);

        rowindex = newRow.index();

        product_price.splice(rowindex, 0, parseFloat(data[2] * currency['exchange_rate']) + parseFloat(data[2] * currency['exchange_rate'] * customer_group_rate));

        if(data[16])
            wholesale_price.splice(rowindex, 0, parseFloat(data[16] * currency['exchange_rate']) + parseFloat(data[16] * currency['exchange_rate'] * customer_group_rate));
        else
            wholesale_price.splice(rowindex, 0, '{{number_format(0, $general_setting->decimal, '.', '')}}');
        cost.splice(rowindex, 0, parseFloat(data[17] * currency['exchange_rate']));
        product_discount.splice(rowindex, 0, '{{number_format(0, $general_setting->decimal, '.', '')}}');
        tax_rate.splice(rowindex, 0, parseFloat(data[3]));
        tax_name.splice(rowindex, 0, data[4]);
        tax_method.splice(rowindex, 0, data[5]);
        unit_name.splice(rowindex, 0, data[6]);
        unit_operator.splice(rowindex, 0, data[7]);
        unit_operation_value.splice(rowindex, 0, data[8]);
        is_imei.splice(rowindex, 0, data[13]);
        is_variant.splice(rowindex, 0, data[14]);

        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product_price').val(product_price[rowindex]);

        checkQuantity(data[15], true);
        checkDiscount(data[15], true);

        if(data[16]) {
            populatePriceOption();
            $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.edit-product').click();
        }

        // if(data[18]) {
        //     $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.imei-number').val(imeiNumbers);
        // }

        if (data[23] && Array.isArray(data[23]) && data[23].length > 0) {
            if(productSale && productSale.length > 0) {

                if (product_discount[rowindex] < 1) {
                    cur_product_id = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .product-id').val();
                    @if (isset($draft_product_discount))
                        if (product_discount[rowindex] < 1) {
                            draft_discounts = @json($draft_product_discount['discount']);
                            product_discount[rowindex] = draft_discounts[cur_product_id];
                        }
                    @endif
                }

                // Find a match for current data[9] (product_id)
                let matchedIndex = productSale.findIndex(p => parseInt(p.product_id) === parseInt(data[9]));

                if (matchedIndex !== -1) {
                    let matchedProduct = productSale[matchedIndex];

                    // Parse toppings
                    let toppings = JSON.parse(matchedProduct.topping_id || '[]');

                    let toppingNames = toppings.map(t => t.name).join(", ");
                    let totalToppingPrice = toppings.reduce((sum, t) => sum + parseFloat(t.price), 0);

                    newRow.find('.product-title').append(`<br><small>Includes: ${toppingNames}</small>`);
                    newRow.find('.topping_product').val(matchedProduct.topping_id);
                    newRow.find('.topping-price').val(totalToppingPrice.toFixed({{$general_setting->decimal}}));

                    const currentPrice = parseFloat(newRow.find('.product-price').text()) || 0;
                    const newPrice = currentPrice + totalToppingPrice;
                    newPrice -= product_discount[rowindex];
                    newRow.find('.product-price').text(newPrice.toFixed({{$general_setting->decimal}}));
                    newRow.find('.sub-total').text(newPrice.toFixed({{$general_setting->decimal}}));

                    // Remove used item from array
                    productSale.splice(matchedIndex, 1);

                    calculateTotal();
                }

            }else{

                openToppingsModal(data, [], rowindex);

                function openToppingsModal(data, selectedToppings = [], rowIndex = null) {
                    let modalContent = '<form id="product-selection-form">';
                    data[23].forEach(product => {
                        const selected = selectedToppings.find(t => t.id == product.id);
                        const isChecked = selected ? 'checked' : '';
                        const qty = selected ? selected.qty : 1;

                        modalContent += `
                            <div class="form-check d-flex align-items-center mb-1">
                                <div>
                                    <input class="form-check-input" type="checkbox" name="productOption" id="product_${product.id}" value="${product.id}" data-name="${product.name}" data-price="${product.price}" ${isChecked}>
                                    <label class="form-check-label" for="product_${product.id}">
                                        ${product.name} (${product.code}) - ${product.price}
                                    </label>
                                </div>
                                <input type="number" name="quantity_${product.id}" id="quantity_${product.id}" class="form-control form-control-sm" style="width: 80px;" min="1" value="${qty}">
                            </div>`;
                    });
                    modalContent += '</form>';

                    const modalHTML = `
                        <div class="modal fade" id="productSelectionModal" tabindex="-1" role="dialog" aria-labelledby="productSelectionModalLabel" aria-hidden="true" data-rowindex="${rowIndex}">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="productSelectionModalLabel">{{__('db.Select Additional Products')}}</h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">${modalContent}</div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{__('db.Cancel')}}</button>
                                        <button type="button" class="btn btn-primary" id="confirmSelection">Confirm</button>
                                    </div>
                                </div>
                            </div>
                        </div>`;

                    // Remove existing modal if any, then append and show new
                    $("#productSelectionModal").remove();
                    $("body").append(modalHTML);
                    $("#productSelectionModal").modal('show');
                }

                // Handle selection confirmation
                $("#confirmSelection").on('click', function () {
                    let selectedToppings = [];
                    let totalAdditionalPrice = 0;

                    if (product_discount[rowindex] < 1) {
                        cur_product_id = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .product-id').val();
                        @if (isset($draft_product_discount))
                            if (product_discount[rowindex] < 1) {
                                draft_discounts = @json($draft_product_discount['discount']);
                                product_discount[rowindex] = draft_discounts[cur_product_id];
                            }
                        @endif
                    }

                    $("input[name='productOption']:checked").each(function () {
                        const qty = parseFloat($(`#quantity_${$(this).val()}`).val() || 1); // define qty first

                        const topping = {
                            id: $(this).val(),
                            name: $(this).data('name'),
                            qty: qty,
                            price: parseFloat($(this).data('price')) * qty
                        };

                        selectedToppings.push(topping);
                        totalAdditionalPrice += topping.price;
                    });

                    if (selectedToppings.length > 0) {
                        // Convert the selected toppings array to JSON
                        const selectedToppingsJson = JSON.stringify(selectedToppings);

                        // Append toppings to the main product row
                        const selectedProductNames = selectedToppings.map(t => `${t.name} (${t.qty})`).join(', ');

                        newRow.find('.product-title').append(`<br><small>Includes: ${selectedProductNames}</small>`);

                        newRow.find('.topping_product').val(selectedToppingsJson); // Store JSON in hidden field

                        // Update the total price
                        const currentPrice = parseFloat(newRow.find('.product-price').text()) || 0;
                        let newPrice = currentPrice + totalAdditionalPrice;
                        newPrice -= product_discount[rowindex];
                        newRow.find('.product-price').text(newPrice.toFixed({{$general_setting->decimal}}));
                        newRow.find('.sub-total').text(newPrice.toFixed({{$general_setting->decimal}}));
                        newRow.find('.topping-price').val(totalAdditionalPrice.toFixed({{$general_setting->decimal}}));
                    }

                    $("#productSelectionModal").modal('hide');
                    $(".modal-backdrop").remove();
                    $("#productSelectionModal").remove();
                    calculateTotal();
                });

                // Stop further processing until the modal is resolved
                return;
            }
        }
    }

    $('#currency').val(currency['id']);

    $('#currency').change(function(){
        var rate = $(this).find(':selected').data('rate');
        var currency_id = $(this).val();
        $('#exchange_rate').val(rate);
        //$('input[name="currency_id"]').val(currency_id);
        currency['exchange_rate'] = rate;
        $("table.order-list tbody .qty").each(function(index) {
            rowindex = index;
            currencyChange = true;
            cur_product_id = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .product-id').val();
            qty = $(this).val();
            $.get('/product-price/' + cur_product_id, function(response) {
                checkDiscount(qty, true, response.price);
                couponDiscount();
            });
        });
    });

    $("#print-btn").on("click", function(){
        var divToPrint=document.getElementById('sale-details');
        var newWin=window.open('','Print-Window');
        newWin.document.open();
        newWin.document.write('<link rel="stylesheet" href="<?php echo asset('vendor/bootstrap/css/bootstrap.min.css') ?>" type="text/css"><style type="text/css">@media print {.modal-dialog { max-width: 1000px;} }</style><body onload="window.print()">'+divToPrint.innerHTML+'</body>');
        //   newWin.document.close();
        //   setTimeout(function(){newWin.close();},10);
    });

    function convertDate(isoDate) {
        var date = new Date(isoDate);
        var day = String(date.getDate()).padStart(2, '0');
        var month = String(date.getMonth() + 1).padStart(2, '0'); // Months are zero-based
        var year = date.getFullYear();

        if('{{$general_setting->date_format}}' == 'd-m-Y') {
            return day + '-' + month + '-' + year;
        } else if('{{$general_setting->date_format}}' == 'd/m/Y') {
            return day + '/' + month + '/' + year;
        } else if('{{$general_setting->date_format}}' == 'd.m.Y') {
            return day + '.' + month + '.' + year;
        } else if('{{$general_setting->date_format}}' == 'm-d-Y') {
            return month + '-' + day + '-' + year;
        } else if('{{$general_setting->date_format}}' == 'm/d/Y') {
            return month + '/' + day + '/' + year;
        } else if('{{$general_setting->date_format}}' == 'm.d.Y') {
            return month + '.' + day + '.' + year;
        } else if('{{$general_setting->date_format}}' == 'Y-m-d') {
            return year + '-' + month + '-' + day;
        } else if('{{$general_setting->date_format}}' == 'Y/m/d') {
            return year + '/' + month + '/' + day;
        } else if('{{$general_setting->date_format}}' == 'Y.m.d') {
            return year + '.' + month + '.' + day;
        }

    }

    $('#currency').val(currency['id']);

    $('#currency').change(function(){
        var rate = $(this).find(':selected').data('rate');
        var currency_id = $(this).val();
        $('#exchange_rate').val(rate);
        //$('input[name="currency_id"]').val(currency_id);
        currency['exchange_rate'] = rate;
        $("table.order-list tbody .qty").each(function(index) {
            rowindex = index;
            currencyChange = true;
            checkDiscount($(this).val(), true);
            couponDiscount();
        });
    });

    if(keyboard_active==1){

        $("input.numkey:text").keyboard({
            usePreview: false,
            layout: 'custom',
            display: {
            'accept'  : '&#10004;',
            'cancel'  : '&#10006;'
            },
            customLayout : {
            'normal' : ['1 2 3', '4 5 6', '7 8 9','0 {dec} {bksp}','{clear} {cancel} {accept}']
            },
            restrictInput : true, // Prevent keys not in the displayed keyboard from being typed in
            preventPaste : true,  // prevent ctrl-v and right click
            autoAccept : true,
            css: {
                // input & preview
                // keyboard container
                container: 'center-block dropdown-menu', // jumbotron
                // default state
                buttonDefault: 'btn btn-default',
                // hovered button
                buttonHover: 'btn-primary',
                // Action keys (e.g. Accept, Cancel, Tab, etc);
                // this replaces "actionClass" option
                buttonAction: 'active'
            },
        });

        $('input[type="text"]').keyboard({
            usePreview: false,
            autoAccept: true,
            autoAcceptOnEsc: true,
            css: {
                // input & preview
                // keyboard container
                container: 'center-block dropdown-menu', // jumbotron
                // default state
                buttonDefault: 'btn btn-default',
                // hovered button
                buttonHover: 'btn-primary',
                // Action keys (e.g. Accept, Cancel, Tab, etc);
                // this replaces "actionClass" option
                buttonAction: 'active',
                // used when disabling the decimal button {dec}
                // when a decimal exists in the input area
                buttonDisabled: 'disabled'
            },
            change: function(e, keyboard) {
                    keyboard.$el.val(keyboard.$preview.val())
                    keyboard.$el.trigger('propertychange')
                }
        });

        $('textarea').keyboard({
            usePreview: false,
            autoAccept: true,
            autoAcceptOnEsc: true,
            css: {
                // input & preview
                // keyboard container
                container: 'center-block dropdown-menu', // jumbotron
                // default state
                buttonDefault: 'btn btn-default',
                // hovered button
                buttonHover: 'btn-primary',
                // Action keys (e.g. Accept, Cancel, Tab, etc);
                // this replaces "actionClass" option
                buttonAction: 'active',
                // used when disabling the decimal button {dec}
                // when a decimal exists in the input area
                buttonDisabled: 'disabled'
            },
            change: function(e, keyboard) {
                    keyboard.$el.val(keyboard.$preview.val())
                    keyboard.$el.trigger('propertychange')
                }
        });

        $('#lims_productcodeSearch').keyboard().autocomplete().addAutocomplete({
            // add autocomplete window positioning
            // options here (using position utility)
            position: {
            of: '#lims_productcodeSearch',
            my: 'top+18px',
            at: 'center',
            collision: 'flip'
            }
        });
    }
    // Add More Button of Multiple Payment Modal
    $('.add-more').on("click", function(e) {
        e.preventDefault();

        var htmlText = `<div class="row new-row">
                            <div class="col-md-3 col-6 mt-2 paying-amount-container">
                                <label>{{__('db.Paying Amount')}} *</label>
                                <input type="text" name="paid_amount[]" value="0" class="form-control paid_amount numkey" step="any">
                            </div>
                            <div class="col-md-3 col-6 mt-2">
                                <input type="hidden" name="paid_by_id[]">
                                <label>{{__('db.Paid By')}}</label>
                                <select name="paid_by_id_select[]" class="form-control selectpicker">
                                    @if(in_array("cash",$options))
                                    <option value="1">Cash</option>
                                    @endif
                                    @if(in_array("gift_card",$options))
                                    <option value="2">Gift Card</option>
                                    @endif
                                    @if(in_array("card",$options))
                                    <option value="3">Credit Card</option>
                                    @endif
                                    @if(in_array("cheque",$options))
                                    <option value="4">Cheque</option>
                                    @endif
                                    @if(in_array("paypal",$options) && (strlen(env('PAYPAL_LIVE_API_USERNAME'))>0) && (strlen(env('PAYPAL_LIVE_API_PASSWORD'))>0) && (strlen(env('PAYPAL_LIVE_API_SECRET'))>0))
                                    <option value="5">Paypal</option>
                                    @endif
                                    @if(in_array("deposit",$options))
                                    <option value="6">Deposit</option>
                                    @endif
                                    @if($lims_reward_point_setting_data && $lims_reward_point_setting_data->is_active)
                                    <option value="7">Points</option>
                                    @endif
                                    @foreach($options as $option)
                                        @if($option !== 'cash' && $option !== 'card' && $option !== 'card' && $option !== 'cheque' && $option !== 'gift_card' && $option !== 'deposit' && $option !== 'paypal' && $option !== 'pesapal')
                                            <option value="{{$option}}">{{ucfirst($option)}}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 col-5 mt-2 cash-received-container">
                                <label>{{__('db.Cash Received')}} *</label>
                                <input type="text" name="paying_amount[]" class="form-control paying_amount numkey" required step="any">
                            </div>
                            <div class="col-1 mt-2">
                                <button class="btn btn-danger remove-row mt-4">X</button>
                            </div></div>`;
        $('.add-more-row').before(htmlText);
        var total_paid_amount = 0;
        $('.paid_amount').each(function(){
            var value = parseFloat($(this).val()) || 0;
            total_paid_amount += value;

        });
        var more_to_pay = ($("#grand-total").text() - total_paid_amount).toFixed({{$general_setting->decimal}});
        $('.paid_amount:last').val(more_to_pay);
        $('.paying_amount:last').val(more_to_pay);
        $('.selectpicker').selectpicker('refresh');
        if ($('.qc').length) {
            $('.qc').data('initial', 1); // Update the data attribute
        }
        calculatePayingAmount();
    });

    $(document).on("click", ".remove-row", function() {
        $(this).parent().parent().remove();
        calculatePayingAmount();
        updateChange();
    });

    $('.customer-submit-btn').on("click", function() {
        $.ajax({
            type:'POST',
            url:"{{route('customer.store')}}",
            data: $("#customer-form").serialize(),
            success:function(response) {
                key = response['id'];
                value = response['name']+' ['+response['phone_number']+']';
                $('select[name="customer_id"]').append('<option value="'+ key +'">'+ value +'</option>');
                $('select[name="customer_id"]').val(key);
                $('.selectpicker').selectpicker('refresh');
                $("#addCustomer").modal('hide');
            }
        });
    });

    $("li#notification-icon").on("click", function (argument) {
        $.get('{{ url("notifications/mark-as-read") }}', function(data) {
            $("span.notification-number").text(alert_product);
        });
    });

    $("#register-details-btn").on("click", function (e) {
        e.preventDefault();
        var cash_register_id = $(this).data('id');
        $.ajax({
            url: '{{url("cash-register/getDetails")}}/'+cash_register_id,
            type: "GET",
            success:function(data) {
                $('#register-details-modal #cash_in_hand').text(data['cash_in_hand']);
                $('#register-details-modal #total_sale_amount').text(data['total_sale_amount']);
                $('#register-details-modal #total_payment').text(data['total_payment']);
                $('#register-details-modal #cash_payment').text(data['cash_payment']);
                $('#register-details-modal #credit_card_payment').text(data['credit_card_payment']);
                $('#register-details-modal #cheque_payment').text(data['cheque_payment']);
                $('#register-details-modal #gift_card_payment').text(data['gift_card_payment']);
                $('#register-details-modal #deposit_payment').text(data['deposit_payment']);
                $('#register-details-modal #paypal_payment').text(data['paypal_payment']);
                $('#register-details-modal #total_sale_return').text(data['total_sale_return']);
                $('#register-details-modal #total_expense').text(data['total_expense']);
                $('#register-details-modal #total_cash').text(data['total_cash']);
                $('#register-details-modal #total_supplier_payment').text(data['total_supplier_payment']);
                $('#register-details-modal input[name=cash_register_id]').val(data['id']);
            }
        });
        $('#register-details-modal').modal('show');
    });

    $("#today-sale-btn").on("click", function (e) {
        e.preventDefault();
        $.ajax({
            url: '{{url("sales/today-sale")}}',
            type: "GET",
            success:function(data) {
                $('#today-sale-modal .total_sale_amount').text(data['total_sale_amount']);
                $('#today-sale-modal .total_payment').text(data['total_payment']);
                $('#today-sale-modal .cash_payment').text(data['cash_payment']);
                $('#today-sale-modal .credit_card_payment').text(data['credit_card_payment']);
                $('#today-sale-modal .cheque_payment').text(data['cheque_payment']);
                $('#today-sale-modal .gift_card_payment').text(data['gift_card_payment']);
                $('#today-sale-modal .deposit_payment').text(data['deposit_payment']);
                $('#today-sale-modal .paypal_payment').text(data['paypal_payment']);
                $('#today-sale-modal .total_sale_return').text(data['total_sale_return']);
                $('#today-sale-modal .total_expense').text(data['total_expense']);
                $('#today-sale-modal .total_cash').text(data['total_cash']);
            }
        });
        $('#today-sale-modal').modal('show');
    });

    $("#today-profit-btn").on("click", function (e) {
        e.preventDefault();
        calculateTodayProfit(0);
    });

    $("#today-profit-modal select[name=warehouseId]").on("change", function() {
        calculateTodayProfit($(this).val());
    });

    function calculateTodayProfit(warehouse_id) {
        $.ajax({
                url: '{{url("sales/today-profit")}}/' + warehouse_id,
                type: "GET",
                success:function(data) {
                    $('#today-profit-modal .product_revenue').text(data['product_revenue']);
                    $('#today-profit-modal .product_cost').text(data['product_cost']);
                    $('#today-profit-modal .expense_amount').text(data['expense_amount']);
                    $('#today-profit-modal .profit').text(data['profit']);
                }
            });
        $('#today-profit-modal').modal('show');
    }

    if(keyboard_active==1){
        $('#lims_productcodeSearch').bind('keyboardChange', function (e, keyboard, el) {
            var customer_id = $('#customer_id').val();
            var warehouse_id = $('#warehouse_id').val();
            var biller_id = $('#biller_id').val();

            @if(in_array('restaurant',explode(',',$general_setting->modules)))
            var table_id = $('#table_id').val();
            var waiter_id = $('#waiter_id').val();
            var service_id = $('#service_id').val();
            @endif

            temp_data = $('#lims_productcodeSearch').val();
            if(!customer_id){
                $('#lims_productcodeSearch').val(temp_data.substring(0, temp_data.length - 1));
                alert(@json(__('db.Please select Customer!')));
            }
            else if(!warehouse_id){
                $('#lims_productcodeSearch').val(temp_data.substring(0, temp_data.length - 1));
                alert(@json(__('db.Please select Warehouse!')));
            }
            else if(!biller_id){
                $('#lims_productcodeSearch').val(temp_data.substring(0, temp_data.length - 1));
                alert(@json(__('db.Please select Biller!')));
            }
            @if(in_array('restaurant',explode(',',$general_setting->modules)))
            else if(!table_id && service_id == 1){
                $('#lims_productcodeSearch').val(temp_data.substring(0, temp_data.length - 1));
                alert(@json(__('db.Please select Table!')));
            }
            else if(!waiter_id && service_id == 1){
                $('#lims_productcodeSearch').val(temp_data.substring(0, temp_data.length - 1));
                alert(@json(__('db.Please select Waiter!')));
            }
            @endif
        });
    }
    else{
        $('#lims_productcodeSearch').on('input', function(){
            var customer_id = $('#customer_id').val();
            var warehouse_id = $('#warehouse_id').val();
            var biller_id = $('#biller_id').val();

            @if(in_array('restaurant',explode(',',$general_setting->modules)))
            var table_id = $('#table_id').val();
            var waiter_id = $('#waiter_id').val();
            var service_id = $('#service_id').val();
            @endif

            temp_data = $('#lims_productcodeSearch').val();
            if(!customer_id){
                $('#lims_productcodeSearch').val(temp_data.substring(0, temp_data.length - 1));
                alert(@json(__('db.Please select Customer!')));
            }
            else if(!warehouse_id){
                $('#lims_productcodeSearch').val(temp_data.substring(0, temp_data.length - 1));
                alert(@json(__('db.Please select Warehouse!')));
            }
            else if(!biller_id){
                $('#lims_productcodeSearch').val(temp_data.substring(0, temp_data.length - 1));
                alert(@json(__('db.Please select Warehouse!')));
            }
            @if(in_array('restaurant',explode(',',$general_setting->modules)))
            else if(!table_id && service_id == 1){
                $('#lims_productcodeSearch').val(temp_data.substring(0, temp_data.length - 1));
                alert(@json(__('db.Please select Table!')));
            }
            else if(!waiter_id && service_id == 1){
                $('#lims_productcodeSearch').val(temp_data.substring(0, temp_data.length - 1));
                alert(@json(__('db.Please select Waiter!')));
            }
            @endif
        });
    }

    $("#print-btn").on("click", function(){
        var divToPrint=document.getElementById('sale-details');
        var newWin=window.open('','Print-Window');
        newWin.document.open();
        newWin.document.write('<link rel="stylesheet" href="<?php echo asset('vendor/bootstrap/css/bootstrap.min.css') ?>" type="text/css"><style type="text/css">@media print {.modal-dialog { max-width: 1000px;} }</style><body onload="window.print()">'+divToPrint.innerHTML+'</body>');
        //   newWin.document.close();
        //   setTimeout(function(){newWin.close();},10);
    });

    function convertDate(isoDate) {
        var date = new Date(isoDate);
        var day = String(date.getDate()).padStart(2, '0');
        var month = String(date.getMonth() + 1).padStart(2, '0'); // Months are zero-based
        var year = date.getFullYear();

        if('{{$general_setting->date_format}}' == 'd-m-Y') {
            return day + '-' + month + '-' + year;
        } else if('{{$general_setting->date_format}}' == 'd/m/Y') {
            return day + '/' + month + '/' + year;
        } else if('{{$general_setting->date_format}}' == 'd.m.Y') {
            return day + '.' + month + '.' + year;
        } else if('{{$general_setting->date_format}}' == 'm-d-Y') {
            return month + '-' + day + '-' + year;
        } else if('{{$general_setting->date_format}}' == 'm/d/Y') {
            return month + '/' + day + '/' + year;
        } else if('{{$general_setting->date_format}}' == 'm.d.Y') {
            return month + '.' + day + '.' + year;
        } else if('{{$general_setting->date_format}}' == 'Y-m-d') {
            return year + '-' + month + '-' + day;
        } else if('{{$general_setting->date_format}}' == 'Y/m/d') {
            return year + '/' + month + '/' + day;
        } else if('{{$general_setting->date_format}}' == 'Y.m.d') {
            return year + '.' + month + '.' + day;
        }

    }


    $(document).on('click', '.view-sale', function() {
        sale_id = $(this).val();

        $.ajax({
            url: '{{url("sales/get-sale")}}/' + sale_id,
            type: 'GET',
            success: function(sale) {
                saleDetails(sale);
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
            }
        });
        $('#recentTransaction').modal('hide')
    });

    $(document).on('click', '#close-btn', function () {
        $('#recentTransaction').modal('show')
    });

    function saleDetails(sale){

        var htmltext = '<strong>{{__("db.date")}}: </strong>'+sale[0]+
            '<br><strong>{{__("db.reference")}}: </strong>'+sale[1]+
            '<br><strong>{{__("db.Warehouse")}}: </strong>'+sale[27]+
            '<br><strong>{{__("db.Sale Status")}}: </strong>'+sale[2]+
            '<br><strong>{{__("db.Currency")}}: </strong>'+sale[31];

        if(sale[32])
            htmltext += '<br><strong>{{__("db.Exchange Rate")}}: </strong>'+sale[32]+'<br>';
        else
            htmltext += '<br><strong>{{__("db.Exchange Rate")}}: </strong>N/A<br>';
        if(sale[33])
            htmltext += '<strong>{{__("db.Table")}}: </strong>'+sale[33]+'<br>';
        if(sale[30])
            htmltext += '<strong>{{__("db.Attach Document")}}: </strong><a href="documents/sale/'+sale[30]+'">Download</a><br>';

        htmltext += '<br><div class="row"><div class="col-md-6"><strong>{{__("db.From")}}:</strong><br>'+sale[3]+'<br>'+sale[4]+'<br>'+sale[5]+'<br>'+sale[6]+'<br>'+sale[7]+'<br>'+sale[8]+
        '</div><div class="col-md-6"><div class="float-right"><strong>{{__("db.To")}}:</strong><br>'+sale[9]+'<br>'+sale[10]+'<br>'+sale[11]+'<br>'+sale[12]+'</div></div></div>';

        $.get('{{ url("sales/product_sale") }}/' + sale[13], function(data){
            $(".product-sale-list tbody").remove();
            var name_code = data[0];
            var qty = data[1];
            var unit_code = data[2];
            var tax = data[3];
            var tax_rate = data[4];
            var discount = data[5];
            var subtotal = data[6];
            var batch_no = data[7];
            var return_qty = data[8];
            var is_delivered = data[9];
            // Check if data[10] exists
            var toppings = data[10] ? data[10] : [];
            var total_qty = 0;
            var newBody = $("<tbody>");

            $.each(name_code, function(index){
                var newRow = $("<tr>");
                var cols = '';
                cols += '<td><strong>' + (index+1) + '</strong></td>';
                cols += '<td>' + name_code[index];

                // Append topping names if toppings[index] exists
                if (toppings[index]) {
                    try {
                        // Parse and extract topping names
                        var toppingData = JSON.parse(toppings[index]);
                        var toppingNames = toppingData.map(topping => topping.name).join(', ');
                        cols += ' (' + toppingNames + ')';
                    } catch (error) {
                        console.error('Error parsing toppings for index', index, toppings[index], error);
                    }
                }

                cols += '</td>';
                cols += '<td>' + batch_no[index] + '</td>';
                cols += '<td>' + qty[index] + ' ' + unit_code[index] + '</td>';
                cols += '<td>' + return_qty[index] + '</td>';
                // Calculate unit price
                var unitPrice = parseFloat(subtotal[index] / qty[index]).toFixed({{$general_setting->decimal}});

                // Calculate topping prices if toppings[index] exists
                var toppingPrices = '';
                if (toppings[index]) {
                    try {
                        var toppingData = JSON.parse(toppings[index]); // Parse topping data
                        toppingPrices = toppingData
                            .map(topping => parseFloat(topping.price).toFixed({{$general_setting->decimal}})) // Extract and format each topping price
                            .join(' + '); // Join prices with '+'
                    } catch (error) {
                        console.error('Error calculating topping prices for index', index, toppings[index], error);
                    }
                }

                cols += '<td>' + unitPrice + ' (' + toppingPrices + ')</td>';
                cols += '<td>' + tax[index] + '(' + tax_rate[index] + '%)' + '</td>';
                cols += '<td>' + discount[index] + '</td>';
                // Update subtotal to include topping prices
                var toppingPricesRowTotal = 0;
                if (toppings[index]) {
                    try {
                        var toppingData = JSON.parse(toppings[index]);
                        toppingPricesRowTotal = toppingData.reduce((sum, topping) => sum + parseFloat(topping.price), 0);
                    } catch (error) {
                        console.error('Error calculating topping prices for index', index, toppings[index], error);
                    }
                }
                subtotal[index] = parseFloat(subtotal[index]) + toppingPricesRowTotal;
                cols += '<td>' + subtotal[index].toFixed({{$general_setting->decimal}}) + '</td>';
                cols += '<td>' + is_delivered[index] + '</td>';
                total_qty += parseFloat(qty[index]);
                newRow.append(cols);
                newBody.append(newRow);
            });

            var newRow = $("<tr>");
            cols = '';
            cols += '<td colspan=3><strong>{{__("db.Total")}}:</strong></td>';
            cols += '<td>' + total_qty + '</td>';
            cols += '<td colspan=2></td>';
            cols += '<td>' + sale[14] + '</td>';
            cols += '<td>' + sale[15] + '</td>';
            cols += '<td>' + sale[16] + '</td>';
            cols += '<td></td>';
            newRow.append(cols);
            newBody.append(newRow);

            var newRow = $("<tr>");
            cols = '';
            cols += '<td colspan=9><strong>{{__("db.Order Tax")}}:</strong></td>';
            cols += '<td>' + sale[17] + '(' + sale[18] + '%)' + '</td>';
            newRow.append(cols);
            newBody.append(newRow);

            var newRow = $("<tr>");
            cols = '';
            cols += '<td colspan=9><strong>{{__("db.Order Discount")}}:</strong></td>';
            cols += '<td>' + sale[19] + '</td>';
            newRow.append(cols);
            newBody.append(newRow);
            if(sale[28]) {
                var newRow = $("<tr>");
                cols = '';
                cols += '<td colspan=9><strong>{{__("db.Coupon Discount")}} ['+sale[28]+']:</strong></td>';
                cols += '<td>' + sale[29] + '</td>';
                newRow.append(cols);
                newBody.append(newRow);
            }

            var newRow = $("<tr>");
            cols = '';
            cols += '<td colspan=9><strong>{{__("db.Shipping Cost")}}:</strong></td>';
            cols += '<td>' + sale[20] + '</td>';
            newRow.append(cols);
            newBody.append(newRow);

            var newRow = $("<tr>");
            cols = '';
            cols += '<td colspan=9><strong>{{__("db.grand total")}}:</strong></td>';
            cols += '<td>' + sale[21] + '</td>';
            newRow.append(cols);
            newBody.append(newRow);

            var newRow = $("<tr>");
            cols = '';
            cols += '<td colspan=9><strong>{{__("db.Paid Amount")}}:</strong></td>';
            cols += '<td>' + sale[22] + '</td>';
            newRow.append(cols);
            newBody.append(newRow);

            var newRow = $("<tr>");
            cols = '';
            cols += '<td colspan=9><strong>{{__("db.Due")}}:</strong></td>';
            cols += '<td>' + parseFloat(sale[21] - sale[22]).toFixed({{$general_setting->decimal}}) + '</td>';
            newRow.append(cols);
            newBody.append(newRow);

            $("table.product-sale-list").append(newBody);
        });
        var htmlfooter = '<p><strong>{{__("db.Sale Note")}}:</strong> '+sale[23]+'</p><p><strong>{{__("db.Staff Note")}}:</strong> '+sale[24]+'</p><strong>{{__("db.Created By")}}:</strong><br>'+sale[25]+'<br>'+sale[26];
        $('#sale-content').html(htmltext);
        $('#sale-footer').html(htmlfooter);
        $('#sale-details').modal('show');
    }


    function populateRecentSale(data) {
        var tableData = '';
        $.each(data, function(index,sale) {
            tableData += '<tr>';
            tableData += '<td>' + convertDate(sale.created_at) + '</td>';
            tableData += '<td>' + sale.reference_no + '</td>';
            tableData += '<td>' + sale.name + '</td>';
            tableData += '<td>' + sale.grand_total + '</td>';

            tableData += '<td>'

            // if (all_permission.includes("sales-edit")) {
                tableData += '<button  type="button" class="btn btn-success btn-sm view-sale" title="{{ __('db.View') }}" data-toggle="modal" data-target="#get-sale-details" value="' + sale.id + '"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg></button>&nbsp';
            // }
            if (all_permission.includes("sales-edit")) {
                tableData += '<a href="sales/' + sale.id + '/edit" class="btn btn-warning btn-sm" title="{{ __('db.Edit') }}"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg></a>&nbsp';
            }
            if (all_permission.includes("sales-delete")) {
                tableData += '<form class="d-inline" action="{{ url("/sales")}}/'+ sale.id +'" method ="POST"><input name="_method" type="hidden" value="DELETE">@csrf';
                tableData += '<button type="submit" class="btn btn-danger btn-sm" onclick="return confirmDelete()" title="{{ __('db.Delete') }}"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg></button>';
                tableData += '</form>';
            }
            tableData += '</td>'

            tableData += '</tr>';
        });

        $("#sale-latest tbody").html(tableData);
    }

    function populateRecentDraft(data) {
        var tableData = '';

        $.each(data, function(index,draft) {
            tableData += '<tr>';
            tableData += '<td>' + convertDate(draft.created_at) + '</td>';
            tableData += '<td>' + draft.reference_no + '</td>';
            tableData += '<td>' + draft.name + '</td>';
            tableData += '<td>' + draft.grand_total + '</td>';

            tableData += '<td>'

            if (all_permission.includes("sales-edit")) {
                tableData += '<a href="{{url('/pos')}}/' + draft.id + '" class="btn btn-warning btn-sm" title="{{ __('db.Edit') }}"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg></a>&nbsp';
            }

            if (all_permission.includes("sales-delete")) {
                tableData += '<form class="d-inline" action="{{ url("/sales")}}/'+ draft.id +'" method ="POST"><input name="_method" type="hidden" value="DELETE">@csrf';
                tableData += '<button type="submit" class="btn btn-danger btn-sm" onclick="return confirmDelete()" title="{{ __('db.Delete') }}"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg></button>';
                tableData += '</form>';
            }
            tableData += '</td>'

            tableData += '</tr>';
        });

        $("#draft-latest tbody").html(tableData);
    }

    $("#myTable").on('click', '.plus', function() {
        rowindex = $(this).closest('tr').index();
        var qty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val();
        var max_qty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').attr('max');
        if(!qty)
            qty = 1;
        // if(max_qty && parseFloat(qty) >= parseFloat(max_qty)) {
        //     alert(@json(__('db.Maximum quantity reached!')));
        //     return;
        // }
        else
            qty = parseFloat(qty) + 1;
        if(is_variant[rowindex]){
            checkQuantity(String(qty), true);
        }else{
            checkDiscount(qty, true);
        }
    });

    $("#myTable").on('click', '.minus', function() {
        rowindex = $(this).closest('tr').index();
        var qty = parseFloat($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val()) - 1;
        if (qty > 0) {
            $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val(qty);

            if(is_variant[rowindex])
                checkQuantity(String(qty), true);
            else
                checkDiscount(qty, '3');
        }
        else {
            qty = 1;
        }

    });

    $("select[name=price_option]").on("change", function () {
        $("#editModal input[name=edit_unit_price]").val($(this).val());
    });

    $("#myTable").on("change", ".batch-no", function () {
        rowindex = $(this).closest('tr').index();
        var product_id = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-id').val();
        var warehouse_id = $('#warehouse_id').val();
        $.get('{{ url("check-batch-availability") }}/' + product_id + '/' + $(this).val() + '/' + warehouse_id, function(data) {
            if(data['message'] != 'ok') {
                alert(data['message']);
                $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.batch-no').val('');
                $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-batch-id').val('');
            }
            else {
                $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-batch-id').val(data['product_batch_id']);
                code = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-code').val();
                //pos = product_code.indexOf(code);
                product_qty[pos] = data['qty'];
            }
        });
    });

    let previousqty = '';

    $("#myTable").on('focus', '.qty', function() {
        previousqty = $(this).val();
    });

    //Change quantity
$("#myTable").on('input', '.qty', function() {
    rowindex = $(this).closest('tr').index();
    if($(this).val() < 1 && $(this).val() != '') {
      $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val(1);
      alert("Quantity can't be less than 1");
    }
    checkDiscount($(this).val(), true);
    updateDstatus(rowindex);
});

    $("#myTable").on('click', '.qty', function() {
        rowindex = $(this).closest('tr').index();
    });

    //Delete product
    $("table.order-list tbody").on("click", ".ibtnDel", function(event) {
        playSound();
        rowindex = $(this).closest('tr').index();
        var qty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.qty').val();
        checkDiscount(qty,false);
        product_price.splice(rowindex, 1);
        wholesale_price.splice(rowindex, 1);
        product_discount.splice(rowindex, 1);
        tax_rate.splice(rowindex, 1);
        tax_name.splice(rowindex, 1);
        tax_method.splice(rowindex, 1);
        unit_name.splice(rowindex, 1);
        unit_operator.splice(rowindex, 1);
        unit_operation_value.splice(rowindex, 1);
        $(this).closest("tr").remove();
        calculateTotal();
        if ($('#tbody-id tr').length < 1) {
            $('.payment-btn').attr('disabled', true);
        }
    });
    

    //Edit product
    $("table.order-list").on("click", ".edit-product", function() {
        rowindex = $(this).closest('tr').index();
        edit();
    });

    //Update product
    $('button[name="update_btn"]').on("click", function() {
        if(is_imei[rowindex]) {
            var imeiNumbers = '';
            $("#editModal .imei-numbers").each(function(i) {
                if (i)
                    imeiNumbers += ','+ $(this).val();
                else
                    imeiNumbers = $(this).val();
            });
            $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.imei-number').val(imeiNumbers);
        }

        var edit_discount = $('input[name="edit_discount"]').val();
        var edit_qty = $('input[name="edit_qty"]').val();
        var edit_unit_price = $('input[name="edit_unit_price"]').val();

        if (parseFloat(edit_discount) > parseFloat(edit_unit_price)) {
            alert(@json(__('db.Invalid Discount Input!')));
            return;
        }

        if(edit_qty < 0) {
            $('input[name="edit_qty"]').val(1);
            edit_qty = 1;
            alert("Quantity can't be less than 0");
        }

        var tax_rate_all = <?php echo json_encode($tax_rate_all) ?>;

        tax_rate[rowindex]  = parseFloat(tax_rate_all[$('select[name="edit_tax_rate"]').val()]);
        tax_name[rowindex]  = $('select[name="edit_tax_rate"] option:selected').text();

        var product_type = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product_type').val();

        product_discount[rowindex] = $('input[name="edit_discount"]').val();
        if(product_type == 'standard'){
            var row_unit_operator = unit_operator[rowindex].slice(0, unit_operator[rowindex].indexOf(","));
            var row_unit_operation_value = unit_operation_value[rowindex].slice(0, unit_operation_value[rowindex].indexOf(","));
            if (row_unit_operator == '*') {
                product_price[rowindex] = $('input[name="edit_unit_price"]').val() / row_unit_operation_value;
            } else {
                product_price[rowindex] = $('input[name="edit_unit_price"]').val() * row_unit_operation_value;
            }
            var position = $('select[name="edit_unit"]').val();
            var temp_operator = temp_unit_operator[position];
            var temp_operation_value = temp_unit_operation_value[position];
            $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.sale-unit').val(temp_unit_name[position]);
            temp_unit_name.splice(position, 1);
            temp_unit_operator.splice(position, 1);
            temp_unit_operation_value.splice(position, 1);

            temp_unit_name.unshift($('select[name="edit_unit"] option:selected').text());
            temp_unit_operator.unshift(temp_operator);
            temp_unit_operation_value.unshift(temp_operation_value);

            unit_name[rowindex] = temp_unit_name.toString() + ',';
            unit_operator[rowindex] = temp_unit_operator.toString() + ',';
            unit_operation_value[rowindex] = temp_unit_operation_value.toString() + ',';

        }
        else {
            product_price[rowindex] = $('input[name="edit_unit_price"]').val();
        }
        checkDiscount(edit_qty, false);

        $('#editModal').modal('hide');
    });

    $('button[name="order_discount_btn"]').on("click", function() {
        calculateGrandTotal();
    });

    $('button[name="shipping_cost_btn"]').on("click", function() {
        calculateGrandTotal();
    });

    $('button[name="order_tax_btn"]').on("click", function() {
        calculateGrandTotal();
    });

    $(".coupon-check").on("click",function() {
        couponDiscount();
    });

    $(".payment-btn").on("click", function() {
        playSound();
        $('.paid_amount').val($("#grand-total").text());
        $('.paying_amount').val($("#grand-total").text());
        $('.qc').data('initial', 1);
    });

    $("#draft-btn").on("click",function(){
        playSound();
        $('input[name="sale_status"]').val(3);
        $('input[name="paying_amount"]').prop('required',false);
        $('input[name="paid_amount"]').prop('required',false);
        var rownumber = $('table.order-list tbody tr:last').index();
        if (rownumber < 0) {
            alert(@json(__('db.Please insert product to order table!')));
        }
        else
            $('.payment-form').submit();
    });

    $("#submit-btn").on("click", function() {
        $('.payment-form').submit();
    });

    $("#gift-card-btn").on("click",function() {
        appendRemoveElement('gift-card');
    });

    $("#credit-card-btn").on("click",function() {
        appendRemoveElement('credit-card');
    });

    $("#cheque-btn").on("click",function() {
        appendRemoveElement('cheque');
    });

    $("#cash-btn").on("click",function() {
        appendRemoveElement('cash');
    });

    $("#moneipoint-btn").on("click",function() {
        appendRemoveElement('moneipoint');
    });

    $("#multiple-payment-btn").on("click",function() {
        appendRemoveElement('multiplepay');
    });

    $("#deposit-btn").on("click",function() {
        appendRemoveElement('deposit');
    });

    $("#point-btn").on("click",function() {
        appendRemoveElement('points');
    });

    $(".pay-options").on("click", function () {
        appendRemoveElement($(this).val(), true);
    });

    function changeLabelText(labelText) {
        $("#received-paying").text(labelText);
    }

    function appendRemoveElement(className, payOption = false){
        ismultiplepayment = 0;
        $('.remove-element').remove();
        $('.selectpicker').selectpicker('refresh');
        $('select[name="paid_by_id_select[]"]').parent().parent().addClass('d-none');
        $('.paid_amount').parent().addClass('d-none');
        $('.paying_amount').parent().addClass('d-none');
        $('.add-more').parent().addClass('d-none');
        $('.total_payable').text($('#grand-total').text());
        $('.total_paying').text($('#grand-total').text());
        $('.due').text(0);
        $('.new-row').remove();
        $('#submit-btn').prop('disabled',false);
        updateChange();

        $("#received-paying").text("Cash Received");
        if (payOption) {
            $("#received-paying").text("Paying Amount");

            let $select = $('select[name="paid_by_id_select[]"]');
            if ($select.find(`option[value="${className}"]`).length === 0) {
                $select.append(`<option value="${className}">${className}</option>`);
            }
            $select.val(className);

            $('.paying_amount').parent().addClass('col-md-12').removeClass('col-md-3 d-none');
            $('.paying_amount').addClass('cash_paying_amount');
        }

        var appendElement = '';
        if (className == 'cash') {
            $('select[name="paid_by_id_select[]"]').val(1);
            $('.paying_amount').parent().addClass('col-md-12').removeClass('col-md-3 d-none');
            $('.paying_amount').addClass('cash_paying_amount');
        }
        else if (className == 'gift-card') {
            $('select[name="paid_by_id_select[]"]').val(2);
            appendElement = `<div class="form-group col-md-12 gift-card remove-element">
                                <label> {{__('db.Gift Card')}} *</label>
                                <input type="hidden" name="gift_card_id">
                                <select id="gift_card_id_select" name="gift_card_id_select" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="{{ __('db.Select Gift Card...') }}"></select>
                            </div>`;
            $.ajax({
                url: '{{url("sales/get_gift_card")}}',
                type: "GET",
                dataType: "json",
                success:function(data) {
                    $('#add-payment select[name="gift_card_id_select"]').empty();
                    $.each(data, function(index) {
                        gift_card_amount[data[index]['id']] = data[index]['amount'];
                        gift_card_expense[data[index]['id']] = data[index]['expense'];
                        $('#add-payment select[name="gift_card_id_select"]').append('<option value="'+ data[index]['id'] +'">'+ data[index]['card_no'] +'</option>');
                    });
                    $('.selectpicker').selectpicker('refresh');
                    $('.selectpicker').selectpicker();
                    $('#gift_card_id_select').selectpicker('toggle'); 
                }
            });
        }
        else if (className == 'credit-card') {
            $('select[name="paid_by_id_select[]"]').val(3);
            appendElement = `<div class="form-group col-md-12 credit-card remove-element">
                                <div class="row">
                                    <div class="col-md-5">
                                        <label>Card Number</label>
                                        <input class="form-control" name="card_number" class="card_name">
                                    </div>
                                    <div class="col-md-5">
                                        <label>Card Holder Name</label>
                                        <input class="form-control" name="card_holder_name">
                                    </div>
                                    <div class="col-md-2">
                                        <label>Card Type</label>
                                        <select class="form-control" name="card_type">
                                            <option>Visa</option>
                                            <option>Master Card</option>
                                        </select>
                                    </div>
                                </div>
                            </div>`;
        }
        else if (className == 'cheque') {
            $('select[name="paid_by_id_select[]"]').val(4);
            appendElement = `<div class="form-group col-md-12 cheque remove-element">
                            <label>{{__('db.Cheque Number')}} *</label>
                            <input type="text" name="cheque_no" class="form-control" value="" required>
                        </div>`;

        }
        else if (className == 'deposit') {
            $('select[name="paid_by_id_select[]"]').val(6);
            if($('input[name="paid_amount[]"]').val() > deposit[$('#customer_id').val()]){
                alert(@json(__('db.Amount exceeds customer deposit! Customer deposit : '))+ deposit[$('#customer_id').val()]);

            }
            else {
                $('#add-payment').modal('show');
            }
        }
        else if (className == 'points') {
            $('select[name="paid_by_id_select[]"]').val(7);
            pointCalculation();
        }
        else if (className == 'multiplepay') {
            ismultiplepayment = 1;
            $('select[name="paid_by_id_select[]"]').val(1);
            $('select[name="paid_by_id_select[]"]').parent().parent().removeClass('d-none');
            $('.paid_amount').parent().removeClass('d-none');
            $('.paying_amount').parent().removeClass('col-md-12 d-none').addClass('col-md-3');
            $('.paying_amount').removeClass('cash_paying_amount')
            $('.add-more').parent().removeClass('d-none');
        }
        $("#payment-select-row .row:eq(0)").append(appendElement);

    }

    function pointCalculation() {
        paid_amount = $('input[name="paid_amount[]"]').val();
        required_point = Math.ceil(paid_amount / reward_point_setting['per_point_amount']);
        if(required_point > points[$('#customer_id').val()]) {
            alert(@json(__('db.Customer does not have sufficient points. Available points: '))+points[$('#customer_id').val()]);
        }
        else {
            $("input[name='used_points']").val(required_point);
        }
    }

    $(document).on("change", 'select[name="paid_by_id_select[]"]', function() {
        updateChange();
        var id = $(this).val();
        var appendElement = '';
        $(".payment-form").off("submit");
        $(this).parent().parent().siblings('.cash-received-container').addClass('d-none');
        $(this).parent().parent().siblings('.gift-card').remove();
        $(this).parent().parent().siblings('.credit-card').remove();
        $(this).parent().parent().siblings('.cheque').remove();
        //cash
        if(id == 1){
            $(this).parent().parent().siblings('.cash-received-container').removeClass('d-none');
        }
        //gift
        else if(id == 2) {
            appendElement = `<div class="form-group col-md-10 gift-card remove-element">
                                <label> {{__('db.Gift Card')}} *</label>
                                <input type="hidden" name="gift_card_id">
                                <select id="gift_card_id_select" name="gift_card_id_select" class="selectpicker form-control" data-live-search="true" data-live-search-style="begins" title="{{ __('db.Select Gift Card...') }}"></select>
                            </div>`;
            $(this).closest('.col-md-3').after(appendElement);

            $.ajax({
                url: '{{url("sales/get_gift_card")}}',
                type: "GET",
                dataType: "json",
                success:function(data) {
                    $('#add-payment select[name="gift_card_id_select"]').empty();
                    $.each(data, function(index) {
                        gift_card_amount[data[index]['id']] = data[index]['amount'];
                        gift_card_expense[data[index]['id']] = data[index]['expense'];
                        $('#add-payment select[name="gift_card_id_select"]').append('<option value="'+ data[index]['id'] +'">'+ data[index]['card_no'] +'</option>');
                    });
                    $('.selectpicker').selectpicker('refresh');
                    $('.selectpicker').selectpicker();
                }
            });
        }
        //credit
        else if (id == 3) {
            appendElement = `<div class="form-group col-md-10 credit-card remove-element">
                                <div class="row">
                                    <div class="col-md-5">
                                        <label>Card Number</label>
                                        <input class="form-control" name="card_number" class="card_name">
                                    </div>
                                    <div class="col-md-5">
                                        <label>Card Holder Name</label>
                                        <input class="form-control" name="card_holder_name">
                                    </div>
                                    <div class="col-md-2">
                                        <label>Card Type</label>
                                        <select class="form-control" name="card_type">
                                            <option>Visa</option>
                                            <option>Master Card</option>
                                        </select>
                                    </div>
                                </div>
                            </div>`;
            $(this).closest('.col-md-3').after(appendElement);
        }
        //cheque
        else if (id == 4) {
            appendElement = `<div class="form-group col-md-10 cheque remove-element">
                            <label>{{__('db.Cheque Number')}} *</label>
                            <input type="text" name="cheque_no" class="form-control" value="" required>
                        </div>`;
            $(this).closest('.col-md-3').after(appendElement);
        }

        //deposit
        else if(id == 6) {

        }
        //point
        else if(id == 7) {
            pointCalculation();
        }
    });

    $(document).on("change", '#add-payment select[name="gift_card_id_select"]', function() {
        var balance = gift_card_amount[$(this).val()] - gift_card_expense[$(this).val()];
        $('#add-payment input[name="gift_card_id"]').val($(this).val());
        if (ismultiplepayment == 0) {
            if($('input[name="paid_amount[]"]').val() > balance){
                $('#submit-btn').prop('disabled',true);
                alert(@json(__('db.Amount exceeds card balance! Gift Card balance: '))+ balance);
            }else{
                $('#submit-btn').prop('disabled',false);
            }
        }else{
            // $(this).parent().parent().siblings('.paying-amount-container').children('.paid_amount').val(balance);
            updateChange();
        }

    });

    function change(paying_amount, paid_amount) {
        $("#change").text( parseFloat(paying_amount - paid_amount).toFixed({{$general_setting->decimal}}));
    }

    // Event listener for changes to paid_amount
    $(document).on("keyup", '.paid_amount', function() {
        let paid_amount = parseFloat($(this).val()) || 0;
        if(paid_amount < 0){
            $(this).val(0);
        }
        // Call the change function to update the change amount for this specific row
        calculatePayingAmount();
        updateChange();
    });
    // Event listener for changes to paid_amount
    $(document).on("keyup", '.paying_amount', function() {
        let paying_amount = parseFloat($(this).val()) || 0;
        if(paying_amount < 0){
            $(this).val(0);
        }
        updateChange();
    });

    $(document).on("keyup", '.cash_paying_amount', function() {
        let paying_amount = parseFloat($(this).val()) || 0;
        let grandTotal = parseFloat($("#grand-total").text()) || 0;
        let paid_amount = 0;
        if(paying_amount < grandTotal){
            $('.paid_amount').val(paying_amount);
            $('.total_paying').text(paying_amount);
            $('.due').text(grandTotal - paying_amount);

            paid_amount = $('.paid_amount').val();
        }else if(paying_amount > grandTotal){
            $('.paid_amount').val(grandTotal);
            $('.total_paying').text(grandTotal);
            $('.due').text(0);

            paid_amount = $('.paid_amount').val();
        }else if(paying_amount == grandTotal){
            $('.paid_amount').val(grandTotal);
            $('.total_paying').text(grandTotal);
            $('.due').text(0);
            paid_amount = $('.paid_amount').val();
        }

        if(paying_amount < 0){
            $(this).val(0);
        }
        updateChange();
    });

    // Update the change text for the specific row
    function updateChange() {
        let change = 0;
        $('select[name="paid_by_id_select[]"]').each(function() {
            if ($(this).val() == '1') {
                let $row = $(this).closest('.row');
                let paying_amount = parseFloat($row.find('.paying_amount').val()) || 0;
                let paid_amount = parseFloat($row.find('.paid_amount').val()) || 0;
                change += paying_amount - paid_amount;
            }
        });
        $('.change').text((change).toFixed({{$general_setting->decimal}}));
    }

    // Function to calculate the total and update the total_payable
    function calculatePayingAmount() {

        let total = 0;
        let due = 0;
        let grandTotal = parseFloat($("#grand-total").text()) || 0;

        // Loop through each paying_amount field and sum their values
        $('.paid_amount').each(function() {
            let value = $(this).val();

            // Check if the value is a valid number
            if ($.isNumeric(value)) {
                total += parseFloat(value);
                due = grandTotal - total;
            }
        });
        // Update the total_payable with the total
        $('.total_paying').text(total);
        $('.due').text(due);
    }

    function confirmDelete() {
        if (confirm(@json(__('db.Are you sure want to delete?')))) {
            return true;
        }
        return false;
    }

    $('.transaction-btn-plus').on("click", function() {
        $(this).addClass('d-none');
        $('.transaction-btn-close').removeClass('d-none');
    });

    $('.transaction-btn-close').on("click", function() {
        $(this).addClass('d-none');
        $('.transaction-btn-plus').removeClass('d-none');
    });

    $('.coupon-btn-plus').on("click", function() {
        $(this).addClass('d-none');
        $('.coupon-btn-close').removeClass('d-none');
    });

    $('.coupon-btn-close').on("click", function() {
        $(this).addClass('d-none');
        $('.coupon-btn-plus').removeClass('d-none');
    });

    $(document).on('click', '.qc-btn', function(e) {
        if($(this).data('amount')) {
            if($('.qc').data('initial')) {
                $('input[name="paying_amount"]').val($(this).data('amount').toFixed({{ $general_setting->decimal }}));
                $('.qc').data('initial', 0);
            }else {
                $('input[name="paying_amount"]').val( (parseFloat($('input[name="paying_amount"]').val()) + $(this).data('amount')).toFixed({{$general_setting->decimal}}));
            }

        }
        else
            $('input[name="paying_amount"]').val('{{number_format(0, $general_setting->decimal, '.','')}}');
        change( $('input[name="paying_amount"]').val(), $('input[name="paid_amount"]').val() );
    });

    function confirmDelete() {
        if (confirm(@json(__('db.Are you sure want to delete?')))) {
            return true;
        }

        return false;
    }

    function populatePriceOption() {
        $('#editModal select[name=price_option]').empty();
        $('#editModal select[name=price_option]').append('<option value="'+ product_price[rowindex] +'">'+ product_price[rowindex] +'</option>');
        if(wholesale_price[rowindex] > 0)
            $('#editModal select[name=price_option]').append('<option value="'+ wholesale_price[rowindex] +'">'+ wholesale_price[rowindex] +'</option>');
        $('.selectpicker').selectpicker('refresh');
    }

    function edit(){
        $(".imei-section").remove();
        if(is_imei[rowindex]) {

            var imeiNumbers = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.imei-number').val();

            if(imeiNumbers.length) {
                imeiArrays = [...new Set(imeiNumbers.split(","))];
                htmlText = `<div class="col-md-8 form-group imei-section">
                            <label>IMEI or Serial Numbers</label>
                            <div class="table-responsive">
                                <table id="imei-table" class="table table-hover">
                                    <tbody>`;
                for (var i = 0; i < imeiArrays.length; i++) {
                    htmlText += `<tr>
                                    <td>
                                        <input type="text" class="form-control imei-numbers" name="imei_numbers[]" value="`+imeiArrays[i]+`" />
                                    </td>
                                    <td>
                                        <button type="button" class="imei-del btn btn-sm btn-danger">X</button>
                                    </td>
                                </tr>`;
                }
                htmlText += `</tbody>
                                </table>
                            </div>
                        </div>`;
                $("#editModal .modal-element").append(htmlText);
            }
        }
        populatePriceOption();
        $("#product-cost").text(cost[rowindex]);
        var row_product_name_code = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('td:nth-child(1) > strong:nth-child(1)').text();
        $('#modal_header').text(row_product_name_code);

        var qty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.qty').val();
        $('input[name="edit_qty"]').val(qty);

        cur_product_id = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .product-id').val();
        // @if (isset($draft_product_discount))
        //     if (product_discount[rowindex] < 1) {
        //         draft_discounts = @json($draft_product_discount['discount']);
        //         product_discount[rowindex] = draft_discounts[cur_product_id];
        //     }
        // @endif

        $('input[name="edit_discount"]').val(parseFloat(product_discount[rowindex]).toFixed({{$general_setting->decimal}}));

        var tax_name_all = <?php echo json_encode($tax_name_all) ?>;
        pos = tax_name_all.indexOf(tax_name[rowindex]);
        $('select[name="edit_tax_rate"]').val(pos);

        var row_product_code = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-code').val();
        var product_type = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product_type').val();
        if(product_type == 'standard'){
            unitConversion();
            temp_unit_name = (unit_name[rowindex]).split(',');
            temp_unit_name.pop();
            temp_unit_operator = (unit_operator[rowindex]).split(',');
            temp_unit_operator.pop();
            temp_unit_operation_value = (unit_operation_value[rowindex]).split(',');
            temp_unit_operation_value.pop();
            $('select[name="edit_unit"]').empty();
            $.each(temp_unit_name, function(key, value) {
                $('select[name="edit_unit"]').append('<option value="' + key + '">' + value + '</option>');
            });
            $("#edit_unit").show();
        }
        else{
            row_product_price = product_price[rowindex];
            $("#edit_unit").hide();
        }
        $('input[name="edit_unit_price"]').val(row_product_price.toFixed({{$general_setting->decimal}}));
        $('.selectpicker').selectpicker('refresh');
    }

    //Delete imei
    $(document).on("click", "table#imei-table tbody .imei-del", function() {
        // Remove the row
        $(this).closest("tr").remove();

        // Decrease qty
        var edit_qty = parseFloat($('input[name="edit_qty"]').val());
        $('input[name="edit_qty"]').val(edit_qty - 1);

        // Check number of remaining <tr> in the same tbody
        var tbody = $(this).closest("tbody");
        var remainingRows = tbody.find("tr").length;

        if (remainingRows === 0) {
            console.log("No IMEI rows left.");
            $('#editModal').modal('hide');
        }

        // var qty = $('#tbody-id tr:nth-child(' + (rowindex + 1) + ')').find('.qty').val();
        // checkDiscount(qty,false);
        $('#tbody-id tr:eq('+rowindex+')').remove();
        calculateTotal();
    });


    function couponDiscount() {
        var rownumber = $('table.order-list tbody tr:last').index();
        if (rownumber < 0) {
            alert(@json(__('db.Please insert product to order table!')))
        }
        else if($("#coupon-code").val() != ''){
            valid = 0;
            $.each(coupon_list, function(key, value) {
                if($("#coupon-code").val() == value['code']){
                    valid = 1;
                    todyDate = <?php echo json_encode(date('Y-m-d'))?>;
                    if(parseFloat(value['quantity']) <= parseFloat(value['used']))
                        alert(@json(__('db.This Coupon is no longer available')));
                    else if(todyDate > value['expired_date'])
                        alert(@json(__('db.This Coupon has expired!')));
                    else if(value['type'] == 'fixed'){
                        if(parseFloat($('input[name="grand_total"]').val()) >= value['minimum_amount']) {
                            $('input[name="grand_total"]').val($('input[name="grand_total"]').val() - (value['amount'] * currency['exchange_rate']));
                            $('#grand-total').text(parseFloat($('input[name="grand_total"]').val()).toFixed({{$general_setting->decimal}}));
                            $('#grand-total-m').text(parseFloat($('input[name="grand_total"]').val()).toFixed({{$general_setting->decimal}}));
                            if(!isEditMode && !$('input[name="coupon_active"]').val()) {
                                alert('Congratulation! You got '+(value['amount'] * currency['exchange_rate'])+' '+currency['code']+' discount');
                            }
                            $(".coupon-check").prop("disabled",true);
                            $("#coupon-code").prop("disabled",true);
                            $('input[name="coupon_active"]').val(1);
                            $("#coupon-modal").modal('hide');
                            $('input[name="coupon_id"]').val(value['id']);
                            $('input[name="coupon_discount"]').val(value['amount'] * currency['exchange_rate']);
                            $('#coupon-text').text(parseFloat(value['amount'] * currency['exchange_rate']).toFixed({{$general_setting->decimal}}));
                        }
                        else
                            alert('Grand Total is not sufficient for discount! Required '+value['minimum_amount']+' '+currency['code']);
                    }
                    else{
                        var grand_total = $('input[name="grand_total"]').val();
                        var coupon_discount = grand_total * (value['amount'] / 100);
                        grand_total = grand_total - coupon_discount;
                        $('input[name="grand_total"]').val(grand_total);
                        $('#grand-total').text(parseFloat(grand_total).toFixed({{$general_setting->decimal}}));
                        $('#grand-total-m').text(parseFloat(grand_total).toFixed({{$general_setting->decimal}}));
                        if(!isEditMode && !$('input[name="coupon_active"]').val()) {
                            alert('Congratulation! You got '+value['amount']+'% discount');
                        }
                        $(".coupon-check").prop("disabled",true);
                        $("#coupon-code").prop("disabled",true);
                        $('input[name="coupon_active"]').val(1);
                        $("#coupon-modal").modal('hide');
                        $('input[name="coupon_id"]').val(value['id']);
                        $('input[name="coupon_discount"]').val(coupon_discount);
                        $('#coupon-text').text(parseFloat(coupon_discount).toFixed({{$general_setting->decimal}}));
                    }
                }
            });
            if(!valid)
                alert(@json(__('db.Invalid coupon code!')));
        }
    }

    function checkDiscount(qty, flag, price = 0) {
        var customer_id = $('#customer_id').val();
        var warehouse_id = $('#warehouse_id').val();
        var product_id = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .product-id').val();

            $.ajax({
                type: 'GET',
                async: false,
                url: '{{url("/")}}/sales/check-discount?qty='+qty+'&customer_id='+customer_id+'&product_id='+product_id+'&warehouse_id='+warehouse_id,
                success: function(data) {
                    if(product_price[rowindex].length == 0){
                        product_price[rowindex] = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .product_price').val();
                    }
                    product_price[rowindex] = parseFloat(product_price[rowindex] * currency['exchange_rate']) + parseFloat(product_price[rowindex] * currency['exchange_rate'] * customer_group_rate);


                    var productDiscount = parseFloat($('#discount').text());

                    if(flag == true)
                        $('#discount').text(productDiscount+data[2]);
                    else if(flag == false)
                        $('#discount').text(productDiscount-data[2]*qty);
                    else if(flag == 'input')
                        $('#discount').text(productDiscount-data[2]*previousqty+data[2]*qty);
                    else
                        $('#discount').text(productDiscount-data[2]);
                }
            });

        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val(qty);
        flag = true;
        checkQuantity(String(qty), flag);
    }

    function checkQuantity(sale_qty, flag) {
        var row_product_code = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-code').val();
        var qty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.qty').attr('max');
        var product_type = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product_type').val();
        if(without_stock == 'no') {
            if(product_type.trim() == 'standard' || product_type.trim() == 'combo') {
                var operator = unit_operator[rowindex].split(',');
                var operation_value = unit_operation_value[rowindex].split(',');
                if(operator[0] == '*')
                    total_qty = sale_qty * operation_value[0];
                else if(operator[0] == '/')
                    total_qty = sale_qty / operation_value[0];
                if (total_qty > parseFloat(qty)) {
                    alert(@json(__('db.Quantity exceeds stock quantity!')));
                    if (flag) {
                        sale_qty = (sale_qty - 1);
                        checkQuantity(sale_qty, true);
                    }
                    else {
                        edit();
                        return;
                    }
                }
                $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.qty').val(sale_qty);
            }
        }
        else
            $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.qty').val(sale_qty);
        if(!flag) {
            $('#editModal').modal('hide');
            $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.qty').val(sale_qty);
        }
        calculateRowProductData(sale_qty);
    }

    function unitConversion() {
        var row_unit_operator = unit_operator[rowindex].slice(0, unit_operator[rowindex].indexOf(","));
        var row_unit_operation_value = unit_operation_value[rowindex].slice(0, unit_operation_value[rowindex].indexOf(","));

        if (row_unit_operator == '*') {
            row_product_price = product_price[rowindex] * row_unit_operation_value;
        } else {
            row_product_price = product_price[rowindex] / row_unit_operation_value;
        }
    }

    function calculateRowProductData(quantity) {
        // if (product_discount[rowindex] < 1) {
        //     cur_product_id = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .product-id').val();
        //     // @if (isset($draft_product_discount))
        //     //     if (product_discount[rowindex] < 1) {
        //     //         draft_discounts = @json($draft_product_discount['discount']);
        //     //         product_discount[rowindex] = draft_discounts[cur_product_id];
        //     //         console.log(product_discount[rowindex]);
        //     //         if(product_discount[rowindex] == undefined || product_discount[rowindex] == null) {
        //     //             checkDiscount(1, false);
        //     //         }
        //     //     }
        //     // @endif
        // }

        if(product_type[pos] == 'standard')
            unitConversion();
        else
            row_product_price = product_price[rowindex];
        if (tax_method[rowindex] == 1) {
            var net_unit_price = row_product_price - product_discount[rowindex];
            var tax = net_unit_price * quantity * (tax_rate[rowindex] / 100);
            var sub_total = (net_unit_price * quantity) + tax;

            if(parseFloat(quantity))
                var sub_total_unit = sub_total / quantity;
            else
                var sub_total_unit = sub_total;
        }
        else {
            var sub_total_unit = row_product_price - product_discount[rowindex];
            var net_unit_price = (100 / (100 + tax_rate[rowindex])) * sub_total_unit;
            var tax = (sub_total_unit - net_unit_price) * quantity;
            var sub_total = sub_total_unit * quantity;
        }

        var topping_price = ($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.topping-price').val() * quantity) || 0;

        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.discount-value').val((product_discount[rowindex] * quantity).toFixed({{$general_setting->decimal}}));
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.tax-rate').val(tax_rate[rowindex].toFixed({{$general_setting->decimal}}));
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.net_unit_price').val(net_unit_price.toFixed({{$general_setting->decimal}}));
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.tax-value').val(tax.toFixed({{$general_setting->decimal}}));
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-price').text(sub_total_unit.toFixed({{$general_setting->decimal}}));
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.sub-total').text((sub_total+topping_price).toFixed({{$general_setting->decimal}}));
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.subtotal-value').val((sub_total+topping_price).toFixed({{$general_setting->decimal}}));

        calculateTotal();
    }

    function calculateTotal() {
        //Sum of quantity
        var total_qty = 0;
        $("table.order-list tbody .qty").each(function(index) {
            if ($(this).val() == '') {
                total_qty += 0;
            } else {
                total_qty += parseFloat($(this).val());
            }
        });
        $('input[name="total_qty"]').val(total_qty);

        //Sum of discount
        var total_discount = 0;
        $("table.order-list tbody .discount-value").each(function() {
            total_discount += parseFloat($(this).val());
        });

        $('input[name="total_discount"]').val(total_discount.toFixed({{$general_setting->decimal}}));

        //Sum of tax
        var total_tax = 0;
        $(".tax-value").each(function() {
            total_tax += parseFloat($(this).val());
        });

        $('input[name="total_tax"]').val(total_tax.toFixed({{$general_setting->decimal}}));

        //Sum of subtotal
        var total = 0;
        $(".sub-total").each(function() {
            total += parseFloat($(this).text());
        });
        $('input[name="total_price"]').val(total.toFixed({{$general_setting->decimal}}));

        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        var item = $('table.order-list tbody tr:last').index();
        if (item == -1) {
            $('#order-discount-val').val(0);
        }
        var total_qty = parseFloat($('input[name="total_qty"]').val());
        var subtotal = parseFloat($('input[name="total_price"]').val());
        var order_tax = parseFloat($('select[name="order_tax_rate_select"]').val());
        var order_discount_type = $('select[name="order_discount_type_select"]').val();
        var order_discount_value = parseFloat($('input[name="order_discount_value"]').val());

        @if (isset($lims_sale_data))
            if (order_discount_value === null || isNaN(order_discount_value) || order_discount_value < 1) {
                order_discount_type = @json($lims_sale_data->order_discount_type);
                order_discount_value = parseFloat(@json($lims_sale_data->order_discount_value));
            }
        @endif

        if (!order_discount_value)
            order_discount_value = {{number_format(0, $general_setting->decimal, '.', '')}};

        if(order_discount_type == 'Flat') {
            if(!currencyChange) {
                var order_discount = parseFloat(order_discount_value);
            }
            else
                var order_discount = parseFloat(order_discount_value*currency['exchange_rate']);
        }
        else
            var order_discount = parseFloat(subtotal * (order_discount_value / 100));

        $("#discount").text(order_discount_value.toFixed({{$general_setting->decimal}}));
        $('input[name="order_discount"]').val(order_discount);
        $('#order-discount-val').val(order_discount_value);
        $('input[name="order_discount_type"]').val(order_discount_type);
        if(!currencyChange)
            var shipping_cost = parseFloat($('input[name="shipping_cost"]').val());
        else
            var shipping_cost = parseFloat($('input[name="shipping_cost"]').val() * currency['exchange_rate']);
        if (!shipping_cost)
            shipping_cost = {{number_format(0, $general_setting->decimal, '.', '')}};

        item = ++item + '(' + total_qty + ')';
        order_tax = (subtotal - order_discount) * (order_tax / 100);
        var grand_total = (subtotal + order_tax + shipping_cost) - order_discount;
        $('input[name="grand_total"]').val(grand_total.toFixed({{$general_setting->decimal}}));

        if($("#coupon-code").val() != '')
            couponDiscount();
        if(!currencyChange)
            var coupon_discount = parseFloat($('input[name="coupon_discount"]').val());
        else
            var coupon_discount = parseFloat($('input[name="coupon_discount"]').val() * currency['exchange_rate']);
        if (!coupon_discount)
            coupon_discount = {{number_format(0, $general_setting->decimal, '.', '')}};
        grand_total -= coupon_discount;

        $('#item').text(item);
        $('input[name="item"]').val($('table.order-list tbody tr:last').index() + 1);
        $('#subtotal').text(subtotal.toFixed({{$general_setting->decimal}}));
        $('#tax').text(order_tax.toFixed({{$general_setting->decimal}}));
        $('input[name="order_tax"]').val(order_tax.toFixed({{$general_setting->decimal}}));
        $('#shipping-cost').text(shipping_cost.toFixed({{$general_setting->decimal}}));
        $('input[name="shipping_cost"]').val(shipping_cost);
        $('#grand-total').text(grand_total.toFixed({{$general_setting->decimal}}));
        $('#grand-total-m').text(grand_total.toFixed({{$general_setting->decimal}}));
        $('input[name="grand_total"]').val(grand_total.toFixed({{$general_setting->decimal}}));
        currencyChange = false;
    }



    function cancel(rownumber) {
        while(rownumber >= 0) {
            product_price.pop();
            wholesale_price.pop();
            product_discount.pop();
            tax_rate.pop();
            tax_name.pop();
            tax_method.pop();
            unit_name.pop();
            unit_operator.pop();
            unit_operation_value.pop();
            $('table.order-list tbody tr:last').remove();
            rownumber--;
        }
        $('input[name="shipping_cost"]').val('');
        $('input[name="order_discount_value"]').val('');
        $('select[name="order_tax_rate_select"]').val(0);
        calculateTotal();
    }

    function confirmCancel() {
        playSound();
        if (confirm(@json(__('db.Are you sure want to cancel?')))) {
            cancel($('table.order-list tbody tr:last').index());
        }
        return false;
    }

    $(document).on('submit', '.payment-form', function(e) {
        $("table.order-list tbody .qty").each(function(index) {
            if ($(this).val() == '') {
                alert(@json(__('db.One of products has no quantity!')));
                e.preventDefault();
            }
        });
        var rownumber = $('table.order-list tbody tr:last').index();
        if (rownumber < 0) {
            alert(@json(__('db.Please insert product to order table!')))
            e.preventDefault();
        }
        else if(parseFloat($('input[name="total_qty"]').val()) <= 0) {
            alert(@json(__('db.Product quantity is 0')));
            e.preventDefault();
        }
        else if( parseFloat( $('input[name="paying_amount[]"]').val() ) < parseFloat( $('input[name="paid_amount[]"]').val() ) ){
            alert(@json(__('db.Paying amount cannot be bigger than recieved amount')));
            e.preventDefault();
        }
        else {
            if ($('input[name="sale_status"]').val() == 1) {
                $("#submit-btn").prop('disabled', true).html('<span class="spinner-border text-light" role="status"></span>');
            }
            $('input[name="paid_by_id"]').val($('select[name="paid_by_id_select"]').val());
            $('select[name="paid_by_id_select[]"]').each(function(index) {
                $('input[name="paid_by_id[]"]').eq(index).val($(this).val());
            });
            $('input[name="order_tax_rate"]').val($('select[name="order_tax_rate_select"]').val());

            e.preventDefault(); // Prevents the default form submission behavior

            $.ajax({
                url: $('.payment-form').attr('action'), // The form's action URL
                type: $('.payment-form').attr('method'), // The form's method (GET or POST)
                data: $('.payment-form').serialize(), // Serialize the form data
                success: function(response) {
                    console.log(response);

                    @if(in_array('restaurant',explode(',',$general_setting->modules)))
                    if ($('input[name="sale_status"]').val() == 1 || $('input[name="sale_status"]').val() == 5) {
                    @else
                    if ($('input[name="sale_status"]').val() == 1) {
                    @endif

                        let link = "{{url('sales/gen_invoice/')}}" +'/'+ response;

                        $('#pos-layout').css('display','none');
                        var head = $('head').html();
                        $('head').html('');
                        $('.ui-helper-hidden-accessible').css('display','none');

                        if (!$('#not_print_invoice').is(':checked')) {
                            $('#print-layout').load(link, function() {
                                setTimeout(function() {
                                    window.print();
                                }, 50);
                            });
                        } else {
                            location.replace('{{ url("/pos") }}');
                        }

                        $("#submit-btn").prop('disabled', false).html("{{__('db.submit')}}");
                        $('#add-payment').modal('hide');
                        cancel($('table.order-list tbody tr:last').index());

                        setTimeout(function() {
                            window.onafterprint = (event) => {
                                if(isMobile == false){
                                    $('#pos-layout').css('display','block');
                                    $('#print-layout').html('');
                                    $('head').html(head);
                                    location.replace('{{url("/pos")}}');
                                }
                            };
                        }, 100);

                        $('input[name="sale_id"]').val('');
                        $('input[name="draft"]').val('');
                        history.replaceState('', '', '{{url("/pos")}}');

                        $.get('{{url("sales/recent-sale")}}', function(data) {
                            populateRecentSale(data);
                        });
                    }
                    else if ($('input[name="sale_status"]').val() == 3) {
                        $('#pos-layout').prepend('<div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{__("db.Sale successfully added to draft")}}</div>');
                        $('input[name="sale_status"]').val(1);
                        cancel($('table.order-list tbody tr:last').index());
                        $.get('{{url("sales/recent-draft")}}', function(data) {
                            populateRecentDraft(data);
                        });
                    }

                },
                error: function(xhr) {
                    console.log('Form submission failed.');
                }
            });
        }
    });

    @if(in_array('restaurant',explode(',',$general_setting->modules)))
    $('#service_id').change(function() {
        if($(this).val() == 1) {
            $('#table_id').prop('disabled', false);
            $('#table_id').selectpicker('refresh');


            $('#waiter_id').prop('disabled', false);
            $('#waiter_id').selectpicker('refresh');

            $('#table_id').prop('required', true);
            $('#waiter_id').prop('required', true);
        }
        else {
            $('#table_id').prop('disabled', true);
            $('#table_id').selectpicker('refresh');

            $('#waiter_id').prop('disabled', true);
            $('#waiter_id').selectpicker('refresh');

            $('#table_id').prop('required', false);
            $('#waiter_id').prop('required', false);
        }
    });

    @endif

    // Load suppliers when the add supplier payment button is clicked
    $(document).on('click', '.add-supplier-payment', function() {
        $.ajax({
            url: "{{ route('supplier.all') }}", // Laravel route helper
            type: "GET",
            dataType: "json",
            success: function(response) {
                let $supplierSelect = $('#supplier_list');
                $supplierSelect.empty(); // Clear existing options
                
                $supplierSelect.append('<option value="">Select Supplier</option>');
                
                $supplierSelect.append(response);

                // Refresh bootstrap-select
                $supplierSelect.selectpicker('refresh');
            },
            error: function(xhr) {
                console.error("Error loading suppliers:", xhr.responseText);
            }
        });
    });

    $(document).on('change', '#supplier_list', function() {
        $('input[name="balance"]').val('');
        let supplierId = $(this).val();

        if (supplierId) {
            $.ajax({
                url: "{{ url('supplier-due') }}/" + supplierId,
                type: "GET",
                dataType: "json",
                success: function(response) {
                    // response[0] = supplier data, response[1] = due
                    let due = response[0];
                    $('input[name="balance"]').val(due);
                },
                error: function(xhr) {
                    console.error("Error fetching supplier due:", xhr.responseText);
                    $('input[name="balance"]').val('');
                }
            });
        } else {
            $('input[name="balance"]').val('');
        }
    });



</script>

<script>
    const display = document.querySelector('.display');
    const buttons = document.querySelectorAll('.btn');

    let currentInput = '';
    let operator = null;
    let previousInput = '';

    function updateDisplay() {
        display.value = currentInput || previousInput || '0';
    }

    function calculate() {
        let result;
        const prev = parseFloat(previousInput);
        const current = parseFloat(currentInput);

        if (isNaN(prev) || isNaN(current)) return;

        switch (operator) {
            case '+':
                result = prev + current;
                break;
            case '-':
                result = prev - current;
                break;
            case 'x':
                result = prev * current;
                break;
            case '÷':
                result = prev / current;
                break;
            case '%':
                result = prev % current;
                break;
            default:
                return;
        }
        currentInput = result.toString();
        operator = null;
        previousInput = '';
    }

    buttons.forEach(button => {
        button.addEventListener('click', (e) => {
            const value = e.target.textContent;

            if (e.target.classList.contains('number') || value === '.') {
                currentInput += value;
            } else if (e.target.classList.contains('operator')) {
                if (currentInput === '') return;
                if (previousInput !== '') calculate();
                operator = value;
                previousInput = currentInput;
                currentInput = '';
            } else if (e.target.classList.contains('equals')) {
                calculate();
            } else if (e.target.classList.contains('ac')) {
                currentInput = '';
                operator = null;
                previousInput = '';
            } else if (e.target.classList.contains('ce')) {
                currentInput = currentInput.slice(0, -1);
            }

            updateDisplay();
        });
    });

    updateDisplay();

</script>


@endpush
