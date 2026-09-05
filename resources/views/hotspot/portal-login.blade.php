<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width,initial-scale=1"
    >

    <title>
        {{ $branding['portal_title'] }}
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
        }

        .card {
            width: 100%;
            max-width: 390px;
            background: white;
            border-radius: 18px;
            padding: 28px;
            box-shadow:
                0 20px 50px
                rgba(15,23,42,.12);
        }

        .brand {
            color:
                {{ $branding['primary_color'] }};
            font-size: 24px;
            font-weight: 800;
            text-align: center;
        }

        .title {
            margin-top: 8px;
            text-align: center;
            color: #64748b;
        }

        input {
            width: 100%;
            margin-top: 12px;
            padding: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 16px;
        }

        button {
            width: 100%;
            margin-top: 16px;
            border: 0;
            border-radius: 10px;
            padding: 13px;
            background:
                {{ $branding['primary_color'] }};
            color: white;
            font-weight: 700;
            font-size: 16px;
        }

        .error {
            margin-top: 12px;
            color: #dc2626;
            text-align: center;
        }

        .support,
        .terms {
            margin-top: 18px;
            font-size: 12px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>

<body>

$(if chap-id)
<form
    name="sendin"
    action="$(link-login-only)"
    method="post"
>
    <input
        type="hidden"
        name="username"
    >

    <input
        type="hidden"
        name="password"
    >

    <input
        type="hidden"
        name="dst"
        value="$(link-orig)"
    >

    <input
        type="hidden"
        name="popup"
        value="true"
    >
</form>

<script src="/md5.js"></script>

<script>
function doLogin() {
    document.sendin.username.value =
        document.login.username.value;

    document.sendin.password.value =
        hexMD5(
            '$(chap-id)'
            + document.login.password.value
            + '$(chap-challenge)'
        );

    document.sendin.submit();

    return false;
}
</script>
$(endif)

<div class="card">
    <div class="brand">
        {{ $branding['brand_name'] }}
    </div>

    <div class="title">
        {{ $branding['portal_title'] }}
    </div>

    <form
        name="login"
        action="$(link-login-only)"
        method="post"
        $(if chap-id)
        onsubmit="return doLogin()"
        $(endif)
    >
        <input
            type="hidden"
            name="dst"
            value="$(link-orig)"
        >

        <input
            type="hidden"
            name="popup"
            value="true"
        >

        <input
            name="username"
            type="text"
            placeholder="Voucher username"
            autocomplete="username"
            required
        >

        <input
            name="password"
            type="password"
            placeholder="Voucher password"
            autocomplete="current-password"
            required
        >

        <button type="submit">
            Connect
        </button>
    </form>

    $(if error)
    <div class="error">
        $(error)
    </div>
    $(endif)

    @if(
        !empty(
            $branding[
                'support_text'
            ]
        )
        ||
        !empty(
            $branding[
                'support_phone'
            ]
        )
    )
        <div class="support">
            {{ $branding['support_text'] }}

            @if(
                !empty(
                    $branding[
                        'support_phone'
                    ]
                )
            )
                <br>
                {{ $branding['support_phone'] }}
            @endif
        </div>
    @endif

    @if(
        !empty(
            $branding[
                'terms_text'
            ]
        )
    )
        <div class="terms">
            {{ $branding['terms_text'] }}
        </div>
    @endif
</div>

</body>
</html>
