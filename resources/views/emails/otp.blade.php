<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Code</title>
    <style>
        /* Reset & Basics */
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            -webkit-font-smoothing: antialiased;
            color: #1f2937;
            height: 100% !important;
            width: 100% !important;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        /* Layout */
        .wrapper {
            width: 100%;
            table-layout: fixed;
            background-color: #f4f7fa;
            padding-bottom: 40px;
        }

        .main-content {
            background-color: #ffffff;
            margin: 0 auto;
            width: 100%;
            max-width: 600px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }

        /* Header */
        .header {
            padding: 40px 0 20px 0;
            text-align: center;
        }

        .header-title {
            font-size: 24px;
            font-weight: 700;
            color: #4f46e5;
            letter-spacing: -0.5px;
            margin: 0;
        }

        /* Body */
        .body-section {
            padding: 0 40px 40px 40px;
            text-align: center;
        }

        .greeting {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #111827;
        }

        .text {
            font-size: 16px;
            line-height: 1.6;
            color: #4b5563;
            margin-bottom: 24px;
        }

        /* OTP Box */
        .otp-container {
            margin: 32px 0;
        }

        .otp-code {
            font-size: 36px;
            font-weight: 800;
            letter-spacing: 8px;
            color: #4f46e5;
            background-color: #f5f3ff;
            padding: 16px 32px;
            border-radius: 8px;
            display: inline-block;
            border: 1px dashed #c7d2fe;
        }

        /* Footer */
        .footer {
            padding-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }

        .footer p {
            margin: 5px 0;
        }

        /* Mobile */
        @media screen and (max-width: 600px) {
            .main-content {
                width: 100% !important;
                border-radius: 0 !important;
            }

            .body-section {
                padding: 20px !important;
            }

            .otp-code {
                font-size: 28px !important;
                letter-spacing: 4px !important;
                padding: 12px 24px !important;
            }
        }
    </style>
</head>

<body>

    <table class="wrapper" role="presentation">
        <tr>
            <td align="center">

                <!-- Spacer -->
                <table role="presentation" width="100%">
                    <tr>
                        <td height="40"></td>
                    </tr>
                </table>

                <!-- Main Card -->
                <table class="main-content" role="presentation">
                    <!-- Decor Top Bar -->
                    <tr>
                        <td height="6" style="background-color: #4f46e5;"></td>
                    </tr>

                    <!-- Header -->
                    <tr>
                        <td class="header">
                            <h1 class="header-title">{{ config('app.name') }}</h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td class="body-section">
                            <h2 class="greeting">{{ ucwords(str_replace('_', ' ', $type)) }} Verification</h2>
                            <p class="text">
                                Hello! Please use the verification code below to complete your
                                {{ str_replace('_', ' ', $type) }} securely.
                            </p>

                            <div class="otp-container">
                                <span class="otp-code">{{ $code }}</span>
                            </div>

                            <p class="text" style="font-size: 14px; color: #6b7280;">
                                This code is valid for {{ config('auth.otp_expiry_time') }} minutes.<br>
                                If you did not request this, please ignore this email.
                            </p>

                            <div class="footer">
                                <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- Spacer -->
                <table role="presentation" width="100%">
                    <tr>
                        <td height="40"></td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

</body>

</html>
