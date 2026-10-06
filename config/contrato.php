<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Contrato de prestación de servicio de internet
    |--------------------------------------------------------------------------
    | Texto usado al imprimir el modelo desde la lista / detalle de clientes.
    | Los datos de la empresa se toman de Ajustes generales y SIFEN.
    */
    'titulo' => env('CONTRATO_TITULO', 'Contrato de prestación de servicio de acceso a Internet'),

    'ciudad_firma' => env('CONTRATO_CIUDAD', ''),

    'clausulas' => [
        'objeto' => 'El PRESTADOR se obliga a proveer al SUSCRIPTOR el servicio de acceso a Internet, según el plan o los planes detallados en este documento, en el domicilio de instalación indicado. Todos los planes son ilimitados. La velocidad puede variar según la cantidad de dispositivos conectados, la calidad del cableado interno, interferencias y demás condiciones técnicas del lugar.',

        'permanencia' => 'El presente contrato no impone plazo de permanencia mínima. El SUSCRIPTOR puede solicitar la baja en cualquier momento, debiendo abonar el último mes utilizado. A la baja se procederá al retiro de los equipos instalados que sean propiedad del PRESTADOR.',

        'pago' => 'La contraprestación es mensual, con IVA incluido en el precio de lista del plan. El vencimiento es del 1 al 5 de cada mes. Si no se registra el pago dentro del plazo, el servicio se suspende automáticamente. No se realizan descuentos por días suspendidos por falta de pago. La reactivación es automática una vez acreditado el pago. No se aplican descuentos por cortes de energía, problemas eléctricos internos, falta de poda u otros factores ajenos al PRESTADOR.',

        'uso' => 'El uso del servicio es responsabilidad del SUSCRIPTOR. Es fundamental mantener segura la contraseña Wi‑Fi para evitar accesos no autorizados. El servicio se destina a uso residencial o comercial según el plan contratado, quedando prohibido revenderlo o cederlo a terceros sin autorización escrita del PRESTADOR.',

        'equipos' => 'Los equipos (ONU, antena, router, cables y demás accesorios) se entregan en comodato y son propiedad del PRESTADOR. Cualquier daño, manipulación no autorizada, hurto o pérdida deberá ser abonado por el SUSCRIPTOR según valor de reposición. Al cesar el servicio, el SUSCRIPTOR se obliga a devolver los equipos en buen estado, considerando el desgaste por uso normal.',

        'soporte' => 'Los reportes de falla se atienden en un plazo estimado de 24 a 72 horas hábiles, según el caso y la disponibilidad técnica. El cambio de contraseña Wi‑Fi es sin costo la primera vez; solicitudes posteriores pueden tener costo administrativo.',

        'aceptacion' => 'El SUSCRIPTOR declara haber leído y aceptado las presentes condiciones. La utilización del servicio implica la aceptación de este contrato. Este documento se firma en dos ejemplares de un mismo tenor, uno para cada parte.',
    ],
];
