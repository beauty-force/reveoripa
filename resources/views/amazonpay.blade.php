<!DOCTYPE html>
<script src="https://static-fe.payments-amazon.com/OffAmazonPayments/jp/sandbox/lpa/js/Widgets.js"></script>

<div id="AmazonPayButton"></div>

<script>
    OffAmazonPayments.Button("AmazonPayButton", "{{ config('amazonpay.client_id') }}", {
        type: "PwA",
        color: "LightGray",
        size: "medium",
        authorization: function() {
            window.location.href = "{{ route('amazon.pay.create') }}";
        },
        onError: function(error) {
            console.error(error);
        }
    });
</script>
