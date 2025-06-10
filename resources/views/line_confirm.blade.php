<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LINE 連携 - ガチャユーザー認証</title>
    <script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
</head>
<body>
    <script>
        function hrefToVerify() {
            window.location.href = "{{ route('line.comfirm_in_site', ['id' => $id]) }}";
        }
        // Initialize LIFF
        liff.init({ liffId: '{{ $liffId }}' }).then(() => {
            console.log("LIFF initialized!");

            // Check if inside LINE app
            if (liff.isInClient()) {
                // Open the external browser
                liff.openWindow({
                    url: "{{ route('line.comfirm_in_site', ['id' => $id]) }}",
                    external: true
                });

                // Close the LIFF WebView inside LINE
                liff.closeWindow();
            } else {
                hrefToVerify();
            }
        }).catch(err => console.error("LIFF init error:", err));
    </script>
</body>
</html>