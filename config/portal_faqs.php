<?php

/**
 * FAQs CMS para GET portal/v1/faqs (editable sin release de APK).
 */
return [
    'topics' => [
        [
            'topic' => 'sin_internet',
            'label' => 'Sin Internet',
            'items' => [
                [
                    'id' => 'cut_why',
                    'question' => '¿Por qué se me cortó el internet?',
                    'answer' => 'Las causas más comunes son: equipo apagado o sin luz, cable desconectado, o suspensión por factura vencida. Revisá que el router tenga luces encendidas y que tu servicio no figure suspendido en la app.',
                    'orden' => 1,
                ],
                [
                    'id' => 'cut_restart',
                    'question' => '¿Qué puedo hacer antes de llamar?',
                    'answer' => 'Apagá el router 30 segundos, volvé a encenderlo y esperá 2 minutos. Si no vuelve, corré un Smart Check desde Soporte y abrí un ticket con el resultado.',
                    'orden' => 2,
                ],
            ],
        ],
        [
            'topic' => 'internet_lento',
            'label' => 'Internet lento',
            'items' => [
                [
                    'id' => 'slow_wifi',
                    'question' => '¿Por qué va lento el Wi‑Fi?',
                    'answer' => 'La distancia al router, interferencia y muchos dispositivos pueden bajar la velocidad. Probá cerca del equipo con cable o Wi‑Fi 5 GHz si está disponible, y usá Smart Check para medir.',
                    'orden' => 1,
                ],
                [
                    'id' => 'slow_plan',
                    'question' => '¿Estoy recibiendo la velocidad de mi plan?',
                    'answer' => 'Medí con Smart Check conectado por cable al router. Si el resultado es mucho menor a tu plan de forma sostenida, abrí un ticket de soporte.',
                    'orden' => 2,
                ],
            ],
        ],
        [
            'topic' => 'cambiar_password',
            'label' => 'Cambiar contraseña',
            'items' => [
                [
                    'id' => 'app_pass',
                    'question' => '¿Cómo recupero el acceso a la app?',
                    'answer' => 'Escribinos por WhatsApp de soporte con tu número de documento. Te reenviamos una clave de acceso tras verificar tu identidad.',
                    'orden' => 1,
                ],
                [
                    'id' => 'wifi_pass',
                    'question' => '¿Cómo cambio la clave del Wi‑Fi?',
                    'answer' => 'Ingresá al panel del router (suele estar en la etiqueta del equipo) o pedí asistencia por ticket/WhatsApp para que un técnico te guíe.',
                    'orden' => 2,
                ],
            ],
        ],
        [
            'topic' => 'problema_factura',
            'label' => 'Problema de factura',
            'items' => [
                [
                    'id' => 'bill_see',
                    'question' => '¿Dónde veo mis facturas?',
                    'answer' => 'En la app, pestaña Facturas. Ahí están el monto, vencimiento y saldo pendiente.',
                    'orden' => 1,
                ],
                [
                    'id' => 'bill_pay',
                    'question' => '¿Cómo pago?',
                    'answer' => 'Desde Pagar podés ver métodos disponibles (transferencia, Tigo Money, tarjeta si está habilitada). También podés coordinar por WhatsApp de cobranzas.',
                    'orden' => 2,
                ],
            ],
        ],
        [
            'topic' => 'hablar_asesor',
            'label' => 'Hablar con un asesor',
            'items' => [
                [
                    'id' => 'advisor_wa',
                    'question' => '¿Cómo hablo con un asesor?',
                    'answer' => 'Usá el botón de WhatsApp en Soporte o abrí un ticket desde la app. Un asesor te responderá en horario de atención.',
                    'orden' => 1,
                ],
            ],
        ],
    ],
];
