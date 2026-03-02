<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="es">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="format-detection" content="telephone=no,address=no,email=no,date=no" />
        <title>Verifique su correo electrónico</title>
    </head>
    <body style="Margin:0; padding:0; background-color:#f1f5f9; font-family:Arial, Helvetica, sans-serif;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f1f5f9">
            <tr>
                <td align="center" style="padding: 32px 16px;">
                    <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td align="center" style="padding: 0 0 18px; font-size: 18px; line-height: 22px; font-weight: bold; color: #0f172a;">
                                {{ $appName }}
                            </td>
                        </tr>
                    </table>

                    <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="border: 1px solid #e2e8f0;">
                        <tr>
                            <td bgcolor="#2563eb" style="font-size: 0; line-height: 0; height: 6px;">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="padding: 32px 32px 0;">
                                <p style="Margin: 0 0 14px; font-size: 16px; line-height: 24px; color: #334155;">
                                    Hola{{ $displayName ? ' '.$displayName : '' }},
                                </p>
                                <p style="Margin: 0; font-size: 16px; line-height: 24px; color: #334155;">
                                    Para completar su registro en <strong>{{ $appName }}</strong>, verifique su correo electrónico haciendo clic en el siguiente botón:
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td align="center" style="padding: 22px 32px 16px;">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center">
                                    <tr>
                                        <td align="center" bgcolor="#2563eb" style="border: 1px solid #2563eb; mso-padding-alt: 14px 32px;">
                                            <a href="{{ $url }}" style="font-size: 15px; line-height: 18px; font-weight: bold; color: #ffffff; text-decoration: none; padding: 14px 32px; display: inline-block;">
                                                Verificar correo electr&oacute;nico
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0 32px 22px;">
                                <p style="Margin: 0; font-size: 14px; line-height: 20px; color: #64748b; text-align: center;">
                                    Este enlace expira en {{ $expiresMinutes }} minutos.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 0 32px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="border-top: 1px solid #e2e8f0; font-size: 0; line-height: 0;">&nbsp;</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 18px 32px 28px;">
                                <p style="Margin: 0 0 8px; font-size: 13px; line-height: 20px; color: #64748b;">
                                    Si el botón no funciona, copie y pegue este enlace en su navegador:
                                </p>
                                <p style="Margin: 0; font-size: 13px; line-height: 20px;">
                                    <a href="{{ $url }}" style="color: #2563eb; text-decoration: underline;">{{ $url }}</a>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td align="center" style="padding: 18px 32px 0; font-size: 13px; line-height: 20px; color: #94a3b8;">
                                Si usted no creó una cuenta en {{ $appName }}, puede ignorar este correo.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
