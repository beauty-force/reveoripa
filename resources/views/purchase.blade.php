<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Gem-oripaポイント購入</title>

        <meta name="csrf-token" content="{{ csrf_token() }}">

        <meta http-equiv="Access-Control-Allow-Origin" content="*" />

        <script src="{{ asset('js/jquery-3.6.3.min.js') }}"></script>

        @if($testOrLive =='test')
            <script src="https://js.test.fincode.jp/v1/fincode.js"></script>
        @else
            <script src="https://js.fincode.jp/v1/fincode.js"></script>     
        @endif

        <!-- Styles -->
        <style>
            * {
                box-sizing: border-box;
                color: #404040;
            }
            .main {
                width:max-content;
                margin-left: auto;
                margin-right: auto;
                padding-bottom: 40px;
                max-width:100%;
            }
            

            #fincode-form {
                height:auto!important;
                box-sizing: border-box;
            }
            

            #fincode-ui {
                width: 100%!important;
                height: 340px!important;
            }

            div.errors {
                color:red;
                font-size: 13px;
                padding: 15px;
            }

            .fincode-logo {
                padding-left: 10px;
                padding-bottom: 12px;
                text-align:center;
            }
            .fincode-logo img{
                height: 30px;
            }

            .fincode-logo a {
                cursor: pointer;
                display: flex;
                align-items: center;
            }

            .product-info {
                padding-top: 12px;
                padding-bottom: 12px;
                text-align:center;
            }
            .product-title {
                font-size: 14px;
                font-weight: 500;
            }
            .product-amount{
                font-size: 24px;
                font-weight: 600;
            }
            button {
                border-radius: 0.375rem;
                border:none;
                
                padding-top: 0.625rem;
                padding-bottom: 0.625rem;
                cursor: pointer;
                margin-left: auto;
                margin-right: auto;
                margin-top:1rem;
                width : 14rem;
            }
            button.submit {
                background-color: #dc2626;
            }
            button.submit:hover {
                background-color: #991b1b;
            }

            button.next {
                background-color: #4da1c6;
            }
            button.next:hover {
                background-color: #29708f;
            }

            button.cancel {
                background-color: #494949;
            }
            button.cancel:hover {
                background-color: #1c1c1c;
            }
            .button-container {
                padding-top:15px;
                text-align: center;
                margin-bottom: 50px;
            }

            .button-container span {
                color: white!important;
            }

            .card-info{
                font-size: 14px;
                padding-left:15px;
                padding-right:15px;
                padding-top: 15px;
            }      
            .card-info .label {
                margin-bottom: 6px;
            }
            
            .card-info img{
                height: 30px;
            }

            @media only screen and (max-width: 425px) {
                #fincode-form {
                    width:100%!important;
                    margin:0px!important;
                }

                .card-info{
                    padding-left:0px;
                    padding-right:0px;
                    padding-top: 5px;
                    margin-bottom: 10px;
                }   
                
                .fincode-logo {
                    padding-left: 0px;
                }
            }

            .notation-commercial {
                text-align:center;
                margin-bottom: 40px;
            }

            .notation-commercial a {
                text-decoration: none;
                font-size: 14px;
                color: #a3a3a3!important;
                margin-bottom: 15px;
                display: inline-block;
            }

            .notation-commercial .powered-label {
                font-size: 12px;
                color: #a3a3a3!important;
            }

            .notation-commercial .powered-label span {
                color: #737373!important;
            }
            .right-reserve {
                font-size: 12px;
                color: #525252!important;
                text-align:center;
            }

            .check-agree {
                text-align: center;
                font-size: 14px;
                padding-top: 18px;
                padding-bottom: 8px;
            }
            .check-agree>input {
                cursor: pointer;
            }

            .check-agree>input, .check-agree>label {
                vertical-align: middle;
            }

            .text-box {
                border: 1px solid #ccc!important;
                padding: 20px;
                border-radius: 10px;
                max-width: 768px;
                width:90%;
                margin:auto;
                align-items:center;
            }
            .text-box strong {
                font-size:1.5rem;
            }
            .icon {
                width:40px;
            }
            .text-center {
                text-align:center;
            }
            .flex {
                display: flex;
            }
            label {
                display: inline-block;
                max-width: 100%;
                margin-bottom: 5px;
                font-weight: 700;
            }
            .form-control {
                display: block;
                width: 100%;
                height: 40px;
                padding: 6px 12px;
                font-size: 14px;
                line-height: 1.42857143;
                color: #555;
                background-color: #fff;
                background-image: none;
                border: 1px solid #ccc;
                border-radius: 4px;
                -webkit-box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
                box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
                -webkit-transition: border-color ease-in-out .15s,box-shadow ease-in-out .15s;
                -o-transition: border-color ease-in-out .15s,box-shadow ease-in-out .15s;
                -webkit-transition: border-color ease-in-out .15s,-webkit-box-shadow ease-in-out .15s;
                transition: border-color ease-in-out .15s,-webkit-box-shadow ease-in-out .15s;
                transition: border-color ease-in-out .15s,box-shadow ease-in-out .15s;
                transition: border-color ease-in-out .15s,box-shadow ease-in-out .15s,-webkit-box-shadow ease-in-out .15s;
            }
            .form-group {
                margin-top:15px;
                margin-bottom:10px;
            }
        </style>

    </head>
    <body class="antialiased">
        <div class="main" id="cardPay">
            <div class="fincode-logo">
                <!-- <img src="https://dashboard.test.fincode.jp/assets/images/logos/vi_02.svg"/> -->
                <a href="{{($is_admin==1)?route('test.user.point'):route('user.point')}}">
                    <svg xmlns="http://www.w3.org/2000/svg"  style="width:24px" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"></path></svg>
                    <img src="{{ asset('images/logo.png') }}"/>
                </a>
            </div>
            
            <div class="product-info">
                <span class="product-title">ポイント購入 </span>
                <br>
                <span class="product-amount"> ¥ {{number_format($amount)}}</span>
            </div>
            <div class="card-info">
                <div class="label">
                    利用可能カード
                </div>
                <div>
                    <img alt="VISA" src="{{ asset('images/credit_cards/1.JPG') }}"/>
                    <img alt="MasterCard" src="{{ asset('images/credit_cards/2.JPG') }}"/>
                    <img alt="JCB" src="{{ asset('images/credit_cards/3.JPG') }}"/>
                    <img alt="Express" src="{{ asset('images/credit_cards/4.JPG') }}"/>
                    <img alt="International" src="{{ asset('images/credit_cards/5.JPG') }}"/>
                </div>
            </div>


            <form id="fincode-form">
                <div id="fincode">
                </div>
                
                <div class="errors">

                </div>
            </form>

            <div class="check-agree">
                <input type="checkbox" id="check-agree" name="check-agree" value="Bike">
                <label for="check-agree" > <a href="{{ route('main.privacy_police') }}"> 個人情報の取り扱い</a>に同意する </label>
            </div>

            <div class="button-container">
                <button class="submit" onclick="makePayment()">
                    <span>お支払い</span>
                </button><br>
                <button class="next" onclick="bankPayment()">
                    <span>銀行振り込みで購入する</span>
                </button>
            </div>
            <div class="notation-commercial">
                <a href="{{ route('main.notation_commercial') }}">特定商取引法に基づく表記</a>
                
                <div class="powered-label">
                    Powered by <span>GMO</span>
                </div>
                
            </div>
            <div class="right-reserve">
                © {{ date("Y") }} reve-oripa all rights reserved
            </div>

        </div>
            
        <div class="main" id="bankPay" style="display:none;">
            <div class="fincode-logo">
                <!-- <img src="https://dashboard.test.fincode.jp/assets/images/logos/vi_02.svg"/> -->
                <a href="{{($is_admin==1)?route('test.user.point'):route('user.point')}}">
                    <svg xmlns="http://www.w3.org/2000/svg"  style="width:24px" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"></path></svg>
                    <img src="{{ asset('images/logo.png') }}"/>
                </a>
            </div>
            
            <h3 class="text-center">銀行振り込みの流れ</h3>
            <div class="text-box flex">
                <span><strong>1</strong></span>
                <span style="margin-left:20px;">振り込み元口座情報の入力</span>
            </div>
            <div class="text-center"><img src="{{ asset('images/arrow_down.png') }}" class="icon"/></div>
            <div class="text-box flex">
                <span><strong>2</strong></span>
                <span style="margin-left:20px;">銀行振り込み</span>
            </div>
            <div class="text-center"><img src="{{ asset('images/arrow_down.png') }}" class="icon"/></div>
            <div class="text-box flex">
                <span><strong>3</strong></span>
                <span style="margin-left:20px;">運営が振り込み確認後、 ポイントの購入完了<br>ポイント反映までお時間がかかる場合がございます。</span>
            </div>

            <br>
            <h3 class="text-center">振り込み元銀行口座情報</h3>

            <form class="text-box">
                <div class="form-group">
                    <label for="account">口座名義 <font style="color:#ee0000;">*</font></label>
                    <input type="text" class="form-control" id="account" placeholder="口座名義">
                </div>
                <div class="form-group">
                    <label for="bank_name">銀行名 <font style="color:#ee0000;">*</font></label>
                    <input type="text" class="form-control" id="bank_name" placeholder="銀行名">
                </div>
            </form>

            
            <div class="button-container">
                <button class="submit" onclick="makeBankPayment()">
                    <span>完了</span>
                </button><br>
                <button class="cancel" onclick="cardPayment()">
                    <span>戻る</span>
                </button>
            </div>
            <div class="notation-commercial">
                <a href="{{ route('main.notation_commercial') }}">特定商取引法に基づく表記</a>
                
                <div class="powered-label">
                    Powered by <span>GMO</span>
                </div>
                
            </div>
            <div class="right-reserve">
                © {{ date("Y") }} reve-oripa all rights reserved
            </div>

        </div>
    
        <div class="main" id="result" style="display:none;">
            <div class="fincode-logo">
                <!-- <img src="https://dashboard.test.fincode.jp/assets/images/logos/vi_02.svg"/> -->
                <a href="{{($is_admin==1)?route('test.user.point'):route('user.point')}}">
                    <svg xmlns="http://www.w3.org/2000/svg"  style="width:24px" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" class="h-6 w-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"></path></svg>
                    <img src="{{ asset('images/logo.png') }}"/>
                </a>
            </div>
            
            <h3 class="text-center">振込申請完了</h3>
            <br>
            
            <div class="text-center">
                <div class="flex">
                    <span style="width:50%; font-weight:bold; padding-right:3rem;">銀行名</span>
                    <span>GMOあおぞらネット銀行</span>
                </div>
                <div class="flex">
                    <span style="width:50%; font-weight:bold; padding-right:3rem;">支店名</span>
                    <span>法人営業部（101）</span>
                </div>
                <div class="flex">
                    <span style="width:50%; font-weight:bold; padding-right:2rem;">口座種類</span>
                    <span>普通</span>
                </div>
                <div class="flex">
                    <span style="width:50%; font-weight:bold; padding-right:2rem;">口座名義</span>
                    <span>カブシキカイシャエーケーティ</span>
                </div>
                <div class="flex">
                    <span style="width:50%; font-weight:bold; padding-right:2rem;">口座番号</span>
                    <span>1941938</span>
                </div>
                <div class="flex">
                    <span style="width:50%; font-weight:bold;">購入ポイント</span>
                    <span>{{$point['point']}} pt</span>
                </div>
                <div class="flex">
                    <span style="width:50%; font-weight:bold; padding-right:4rem;">金額</span>
                    <span>¥ {{number_format($amount)}}</span>
                </div>

                <div class="button-container">
                    <button class="cancel" onclick="toTopPage()">
                        <span>トップページへ</span>
                    </button>
                </div>
            </div>
            
            <div class="notation-commercial">
                <a href="{{ route('main.notation_commercial') }}">特定商取引法に基づく表記</a>
                
                <div class="powered-label">
                    Powered by <span>GMO</span>
                </div>
                
            </div>
            <div class="right-reserve">
                © {{ date("Y") }} reve-oripa all rights reserved
            </div>

        </div>
    <script>
        let fincode = Fincode('{{$fincode_public_key}}');
        let is_admin = '{{$is_admin}}';
        let purchase_process_url = '{{($is_admin==1)?route('test.user.point.purchase_process'): route('user.point.purchase_process')}}';
        var backUrl = '{{($is_admin==1)?route('test.user.point'):route('user.point')}}';
        var purchase_successUrl = '{{($is_admin==1)?route('test.purchase_success'):route('purchase_success')}}';

        var ui = fincode.ui({layout: "vertical"});
        ui.create("payments",{layout: "vertical", hidePayTimes: true});
        ui.mount("fincode",'360');

        
        var order_id = '{{$order_id}}';
        var access_id = '{{$access_id}}';

        var is_busy = false;
        var _token = '{{ csrf_token() }}';
        
        var card_result;

        function bankPayment() {
            $('#cardPay').hide();
            $('#bankPay').show();
        }
        function cardPayment() {
            $('#bankPay').hide();
            $('#cardPay').show();
        }
        function makePayment() {
            let check_agree = document.querySelector("#check-agree");
            if(check_agree.checked==false) {
                alert('個人情報の取り扱いに同意してください！');
                return;
            }
            if (is_busy) { return; }
            
            ui.getFormData().then(result => {
                is_busy = true;
                card_result = result;
                getTokens(result);
                return;
            });
        }

        function makeBankPayment() {
            const transaction = {
                pay_type: "Bank",
                point_id: {{$point['id']}},
                account: $('#account').val(),
                bank_name: $('#bank_name').val(),
                coupon_id: {{ $coupon_id }},
                _token : _token,
            }

            $.ajax({
                url : purchase_process_url,
                type: "POST",
                data: transaction,
                dataType: 'json',
                success : function (data) {
                    // console.log(data)
                    if (data.status == 'success'){
                        $('#bankPay').hide();
                        $('#result').show();
                    }
                }
            });
        }
        function toTopPage() {
            location.href = "{{route('main')}}";
        }

        function backToPage() {
            location.href = backUrl;
        }

        function postFunction(result, cardToken) {
            const transaction = {
                id: order_id,           // オーダーID
                pay_type: "Card", // 決済種別
                access_id: access_id,   // 取引ID 
                expire: result.expire,        // カード有効期限(yymm)
                method: "1",        // 支払い方法  
                token: cardToken,
                holder_name: result.holderName,       // カード名義人
                pay_times: 1,                // トークン発行数
                coupon_id: {{ $coupon_id }},
                _token : _token,
            }

            $.ajax({
                url : purchase_process_url,
                type: "POST",
                data: transaction,
                dataType: 'json',
                success : function (data) {
                    // console.log(data)
                    if (data.status == "CAPTURED"){
                        location.href = purchase_successUrl;
                    } else if (data.status == "AUTHENTICATED") {
                        location.href = data.acs_url;
                    } else {
                        if (data.message) {
                            alert(data.message);
                        } else {
                            alert("決済処理中に問題が発生しました！");
                        }
                    }
                    is_busy = false;
                }
            }).fail(function (jqXHR, textStatus, error) {
                is_busy = false;
            });
            return ;
        }


        function getTokens(result) {
            const card = {
                card_no : result.cardNo,       // カード番号
                expire : result.expire,        // カード有効期限(yymm)
                holder_name: result.holderName,       // カード名義人
                security_code: result.CVC,   // セキュリティコード
                number: 1,                // トークン発行数
            }
            fincode.tokens(card,
                function (status, response) {
                    if (200 === status) {
                        // console.log(response.list[0].token);
                        // console.log(result);
                        postFunction(result, response.list[0].token);
                        // リクエスト正常時の処理
                    } else {
                        // リクエストエラー時の処理
                        is_busy = false;
                    }
                },
                function () {
                    // 通信エラー処理
                    is_busy = false;
                }
            );
        }
    </script>
    </body>

    
</html>
