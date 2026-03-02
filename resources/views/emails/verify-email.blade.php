<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="es">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="color-scheme" content="light" />
        <meta name="supported-color-schemes" content="light" />
        <meta name="format-detection" content="telephone=no,address=no,email=no,date=no" />
        <title>Verifique su correo electrónico</title>
    </head>
    <body style="Margin:0; padding:0; background-color:#fafafa; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-text-size-adjust:none; color:#52525b; line-height:1.4;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#fafafa">
            <tr>
                <td align="center" style="padding: 25px 16px 0;">
                    <table role="presentation" width="570" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td align="center" style="padding: 0 0 25px; font-size: 19px; line-height: 23px; font-weight: bold; color: #18181b;">
                                {{ $appName }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td align="center" style="padding: 0 16px;">
                    <table role="presentation" width="570" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="border: 1px solid #e4e4e7;">
                        <tr>
                            <td style="padding: 32px;">
                                <h1 style="Margin:0 0 16px; font-size:18px; line-height:1.4; font-weight:bold; color:#18181b; text-align:left;">Hola{{ $displayName ? ' '.$displayName : '' }},</h1>
                                <p style="Margin:0 0 16px; font-size:16px; line-height:1.5em; color:#52525b; text-align:left;">
                                    Para completar su registro en <strong>{{ $appName }}</strong>, verifique su correo electrónico haciendo clic en el siguiente botón:
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding: 0 32px 30px;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center">
                                    <tr>
                                        <td align="center" bgcolor="#112240" style="mso-padding-alt: 0;">
                                            <a href="{{ $url }}" style="font-size:15px; line-height:18px; font-weight:bold; color:#ffffff; text-decoration:none; display:inline-block; background-color:#112240; border-top:8px solid #112240; border-bottom:8px solid #112240; border-left:18px solid #112240; border-right:18px solid #112240;">
                                                Verificar correo electr&oacute;nico
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0 32px 24px;">
                                <p style="Margin:0; font-size:16px; line-height:1.5em; color:#52525b; text-align:left;">
                                    Este enlace expira en {{ $expiresMinutes }} minutos.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0 32px 24px;">
                                <p style="Margin:0 0 4px; font-size:16px; line-height:1.5em; color:#52525b; text-align:left;">
                                    Saludos,<br />{{ $appName }}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0 32px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="border-top: 1px solid #e4e4e7; font-size: 0; line-height: 0;">&nbsp;</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 25px 32px 32px;">
                                <p style="Margin:0 0 8px; font-size:14px; line-height:1.5em; color:#52525b;">
                                    Si tiene problemas haciendo clic en el botón &ldquo;Verificar correo electrónico&rdquo;, copie y pegue la siguiente URL en su navegador:
                                </p>
                                <p style="Margin:0; font-size:14px; line-height:1.5em; word-break:break-all;">
                                    <a href="{{ $url }}" style="color: #112240; text-decoration: underline;">{{ $url }}</a>
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td align="center" style="padding: 25px 32px;">
                    <table role="presentation" width="570" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td align="center" style="font-size: 12px; line-height: 1.5em; color: #a1a1aa;">
                                Si usted no creó una cuenta en {{ $appName }}, puede ignorar este correo.
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding: 10px 0 0; font-size: 12px; line-height: 1.5em; color: #a1a1aa;">
                                &copy; {{ date('Y') }} {{ $appName }}. Todos los derechos reservados.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
