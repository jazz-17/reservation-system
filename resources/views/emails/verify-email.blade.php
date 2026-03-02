<!doctype html>
<html lang="es">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Verifique su correo electrónico</title>
    </head>
    <body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: Arial, Helvetica, sans-serif; -webkit-font-smoothing: antialiased;">
        {{-- Outer wrapper --}}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f1f5f9;">
            <tr>
                <td align="center" style="padding: 32px 16px;">

                    {{-- Header with app name --}}
                    <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="max-width: 560px;">
                        <tr>
                            <td align="center" style="padding-bottom: 24px; font-size: 18px; font-weight: bold; color: #1e293b;">
                                {{ $appName }}
                            </td>
                        </tr>
                    </table>

                    {{-- Card --}}
                    <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="max-width: 560px; background-color: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0;">

                        {{-- Card body --}}
                        <tr>
                            <td style="padding: 36px 32px 0;">
                                <p style="margin: 0 0 20px; font-size: 16px; line-height: 24px; color: #334155;">
                                    Hola{{ $displayName ? ' '.$displayName : '' }},
                                </p>
                                <p style="margin: 0 0 28px; font-size: 16px; line-height: 24px; color: #334155;">
                                    Para completar su registro en <strong>{{ $appName }}</strong>, verifique su correo electrónico haciendo clic en el siguiente botón:
                                </p>
                            </td>
                        </tr>

                        {{-- CTA button --}}
                        <tr>
                            <td align="center" style="padding: 0 32px 28px;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td align="center" style="background-color: #2563eb; border-radius: 6px;">
                                            <a href="{{ $url }}" target="_blank" style="display: inline-block; padding: 14px 32px; font-size: 15px; font-weight: bold; color: #ffffff; text-decoration: none; border-radius: 6px;">
                                                Verificar correo electr&oacute;nico
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        {{-- Expiry notice --}}
                        <tr>
                            <td style="padding: 0 32px 24px;">
                                <p style="margin: 0; font-size: 14px; line-height: 20px; color: #64748b; text-align: center;">
                                    Este enlace expira en {{ $expiresMinutes }} minutos.
                                </p>
                            </td>
                        </tr>

                        {{-- Divider --}}
                        <tr>
                            <td style="padding: 0 32px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="border-top: 1px solid #e2e8f0; font-size: 0; line-height: 0;">&nbsp;</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        {{-- Fallback link --}}
                        <tr>
                            <td style="padding: 20px 32px 28px;">
                                <p style="margin: 0 0 8px; font-size: 13px; line-height: 20px; color: #94a3b8;">
                                    Si el botón no funciona, copie y pegue este enlace en su navegador:
                                </p>
                                <p style="margin: 0; font-size: 13px; line-height: 20px; word-break: break-all;">
                                    <a href="{{ $url }}" style="color: #2563eb;">{{ $url }}</a>
                                </p>
                            </td>
                        </tr>
                    </table>

                    {{-- Footer --}}
                    <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="max-width: 560px;">
                        <tr>
                            <td align="center" style="padding: 24px 32px; font-size: 13px; line-height: 20px; color: #94a3b8;">
                                Si usted no creó una cuenta en {{ $appName }}, puede ignorar este correo.
                            </td>
                        </tr>
                    </table>

                </td>
            </tr>
        </table>
    </body>
</html>
