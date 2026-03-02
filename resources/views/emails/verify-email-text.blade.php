Hola{{ $displayName ? ' '.$displayName : '' }},

Para completar su registro en {{ $appName }}, verifique su correo electrónico.

Verificar correo:
{!! $url !!}

Este enlace expira en {{ $expiresMinutes }} minutos.

Si usted no creó una cuenta, puede ignorar este correo.
