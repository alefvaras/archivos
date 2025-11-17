/**
 * Simple DTE Admin JavaScript
 */

(function($) {
    'use strict';

    // Generar boleta
    $(document).on('click', '.simple-dte-generar-boleta', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var orderId = $btn.data('order-id');
        var casoPrueba = $('#caso_prueba').val();
        var $messages = $('.simple-dte-messages');

        if (!confirm('¿Generar boleta electrónica para esta orden?')) {
            return;
        }

        $btn.prop('disabled', true).text(simpleDTE.strings.generando);
        $messages.html('');

        $.ajax({
            url: simpleDTE.ajax_url,
            type: 'POST',
            data: {
                action: 'simple_dte_generar_boleta',
                nonce: simpleDTE.nonce,
                order_id: orderId,
                caso_prueba: casoPrueba
            },
            success: function(response) {
                if (response.success) {
                    $messages.html('<div class="notice notice-success"><p>' + response.data.mensaje + '</p></div>');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $messages.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    $btn.prop('disabled', false).text('Generar Boleta Electrónica');
                }
            },
            error: function() {
                $messages.html('<div class="notice notice-error"><p>Error de conexión</p></div>');
                $btn.prop('disabled', false).text('Generar Boleta Electrónica');
            }
        });
    });

    // Generar nota de crédito
    $(document).on('click', '.simple-dte-generar-nc', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var orderId = $btn.data('order-id');
        var codigoRef = $('#codigo_ref').val();
        var $messages = $('.simple-dte-messages');

        if (!confirm('¿Generar nota de crédito para esta orden?')) {
            return;
        }

        $btn.prop('disabled', true).text(simpleDTE.strings.generando);
        $messages.html('');

        $.ajax({
            url: simpleDTE.ajax_url,
            type: 'POST',
            data: {
                action: 'simple_dte_generar_nc',
                nonce: simpleDTE.nonce,
                order_id: orderId,
                codigo_ref: codigoRef
            },
            success: function(response) {
                if (response.success) {
                    $messages.html('<div class="notice notice-success"><p>' + response.data.mensaje + '</p></div>');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $messages.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    $btn.prop('disabled', false).text('Generar Nota de Crédito');
                }
            },
            error: function() {
                $messages.html('<div class="notice notice-error"><p>Error de conexión</p></div>');
                $btn.prop('disabled', false).text('Generar Nota de Crédito');
            }
        });
    });

    // Upload CAF
    $('#upload-caf-form').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        formData.append('action', 'simple_dte_upload_caf');
        formData.append('nonce', simpleDTE.nonce);

        $.ajax({
            url: simpleDTE.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(response.data.mensaje);
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('Error de conexión');
            }
        });
    });

    // Consultar estado
    $('#form-consultar-estado').on('submit', function(e) {
        e.preventDefault();

        var trackId = $('#track_id').val();

        $.ajax({
            url: simpleDTE.ajax_url,
            type: 'POST',
            data: {
                action: 'simple_dte_consultar_estado',
                nonce: simpleDTE.nonce,
                track_id: trackId
            },
            success: function(response) {
                if (response.success) {
                    $('#resultado-estado').html(
                        '<div class="notice notice-success"><p>' +
                        'Estado: <strong>' + response.data.estado + '</strong><br>' +
                        'Glosa: ' + response.data.glosa +
                        '</p></div>'
                    );
                } else {
                    $('#resultado-estado').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            }
        });
    });

    // Consultar DTE
    $('#form-consultar-dte').on('submit', function(e) {
        e.preventDefault();

        var tipoDte = $('#tipo_dte').val();
        var folio = $('#folio').val();

        $.ajax({
            url: simpleDTE.ajax_url,
            type: 'POST',
            data: {
                action: 'simple_dte_consultar_dte',
                nonce: simpleDTE.nonce,
                tipo_dte: tipoDte,
                folio: folio
            },
            success: function(response) {
                if (response.success) {
                    var mensaje = response.data.existe ? 'DTE encontrado' : 'DTE no encontrado';
                    $('#resultado-dte').html('<div class="notice notice-success"><p>' + mensaje + '</p></div>');
                } else {
                    $('#resultado-dte').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            }
        });
    });

    // Generar RCV
    $('#form-generar-rcv').on('submit', function(e) {
        e.preventDefault();

        var fechaDesde = $('#fecha_desde').val();
        var fechaHasta = $('#fecha_hasta').val();
        var $btn = $(this).find('button[type="submit"]');
        var btnText = $btn.text();

        $btn.prop('disabled', true).text('Generando...');
        $('#resultado-rcv').html('');

        $.ajax({
            url: simpleDTE.ajax_url,
            type: 'POST',
            data: {
                action: 'simple_dte_generar_rcv',
                nonce: simpleDTE.nonce,
                fecha_desde: fechaDesde,
                fecha_hasta: fechaHasta
            },
            success: function(response) {
                if (response.success) {
                    // Guardar datos en campos ocultos
                    $('#rcv-periodo').text(fechaDesde + ' - ' + fechaHasta);
                    $('#rcv-cantidad').text(response.data.cantidad_documentos);
                    $('#rcv-xml').val(response.data.xml);
                    $('#area-envio-rcv').show();

                    // Guardar fechas en data attributes para el envío
                    $('#btn-enviar-rcv').data('fecha-desde', fechaDesde).data('fecha-hasta', fechaHasta);
                    $('#btn-descargar-rcv').data('fecha-desde', fechaDesde).data('fecha-hasta', fechaHasta);

                    $('#resultado-rcv').html('<div class="notice notice-success"><p>' + response.data.mensaje + '</p></div>');
                } else {
                    $('#resultado-rcv').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
                $btn.prop('disabled', false).text(btnText);
            },
            error: function() {
                $('#resultado-rcv').html('<div class="notice notice-error"><p>Error de conexión</p></div>');
                $btn.prop('disabled', false).text(btnText);
            }
        });
    });

    // Enviar RCV al SII
    $('#btn-enviar-rcv').on('click', function() {
        var xml = $('#rcv-xml').val();
        var fechaDesde = $(this).data('fecha-desde');
        var fechaHasta = $(this).data('fecha-hasta');
        var $btn = $(this);
        var btnText = $btn.text();

        if (!confirm('¿Enviar RCV del período ' + fechaDesde + ' - ' + fechaHasta + ' al SII?')) {
            return;
        }

        $btn.prop('disabled', true).text('Enviando...');

        $.ajax({
            url: simpleDTE.ajax_url,
            type: 'POST',
            data: {
                action: 'simple_dte_enviar_rcv',
                nonce: simpleDTE.nonce,
                fecha_desde: fechaDesde,
                fecha_hasta: fechaHasta,
                xml: xml
            },
            success: function(response) {
                if (response.success) {
                    alert('RCV enviado exitosamente. Track ID: ' + response.data.track_id);
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    $btn.prop('disabled', false).text(btnText);
                }
            },
            error: function() {
                alert('Error de conexión');
                $btn.prop('disabled', false).text(btnText);
            }
        });
    });

    // Descargar XML RCV
    $('#btn-descargar-rcv').on('click', function() {
        var xml = $('#rcv-xml').val();
        var fechaDesde = $(this).data('fecha-desde');
        var fechaHasta = $(this).data('fecha-hasta');

        var blob = new Blob([xml], {type: 'text/xml'});
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'RCV-' + fechaDesde + '-' + fechaHasta + '.xml';
        a.click();
    });

    // Generar RVD
    $('#form-generar-rvd').on('submit', function(e) {
        e.preventDefault();

        var fecha = $('#fecha_rvd').val();
        var $btn = $(this).find('button[type="submit"]');
        var btnText = $btn.text();

        $btn.prop('disabled', true).text('Generando...');
        $('#resultado-rvd').html('');

        $.ajax({
            url: simpleDTE.ajax_url,
            type: 'POST',
            data: {
                action: 'simple_dte_generar_rvd',
                nonce: simpleDTE.nonce,
                fecha: fecha
            },
            success: function(response) {
                if (response.success) {
                    // Mostrar área de envío con el XML generado
                    $('#rvd-fecha').text(fecha);
                    $('#rvd-cantidad').text(response.data.cantidad);
                    $('#rvd-xml').val(response.data.xml);
                    $('#area-envio-rvd').show();

                    $('#resultado-rvd').html('<div class="notice notice-success"><p>' + response.data.mensaje + '</p></div>');
                } else {
                    $('#resultado-rvd').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
                $btn.prop('disabled', false).text(btnText);
            },
            error: function() {
                $('#resultado-rvd').html('<div class="notice notice-error"><p>Error de conexión</p></div>');
                $btn.prop('disabled', false).text(btnText);
            }
        });
    });

    // Enviar RVD al SII
    $('#btn-enviar-rvd').on('click', function() {
        var xml = $('#rvd-xml').val();
        var fecha = $('#rvd-fecha').text();
        var $btn = $(this);
        var btnText = $btn.text();

        if (!confirm('¿Enviar RVD del ' + fecha + ' al SII?')) {
            return;
        }

        $btn.prop('disabled', true).text('Enviando...');

        $.ajax({
            url: simpleDTE.ajax_url,
            type: 'POST',
            data: {
                action: 'simple_dte_enviar_rvd',
                nonce: simpleDTE.nonce,
                fecha: fecha,
                xml: xml
            },
            success: function(response) {
                if (response.success) {
                    alert('RVD enviado exitosamente. Track ID: ' + response.data.track_id);
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    $btn.prop('disabled', false).text(btnText);
                }
            },
            error: function() {
                alert('Error de conexión');
                $btn.prop('disabled', false).text(btnText);
            }
        });
    });

    // Descargar XML RVD
    $('#btn-descargar-rvd').on('click', function() {
        var xml = $('#rvd-xml').val();
        var fecha = $('#rvd-fecha').text();

        var blob = new Blob([xml], {type: 'text/xml'});
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'RVD-' + fecha + '.xml';
        a.click();
    });

    // Consultar estado de RVD
    $(document).on('click', '.btn-consultar-estado', function() {
        var $btn = $(this);
        var trackId = $btn.data('track-id');
        var btnText = $btn.text();

        $btn.prop('disabled', true).text('Consultando...');

        $.ajax({
            url: simpleDTE.ajax_url,
            type: 'POST',
            data: {
                action: 'simple_dte_consultar_estado_rvd',
                nonce: simpleDTE.nonce,
                track_id: trackId
            },
            success: function(response) {
                if (response.success) {
                    alert('Estado actualizado. Recargando página...');
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    $btn.prop('disabled', false).text(btnText);
                }
            },
            error: function() {
                alert('Error de conexión');
                $btn.prop('disabled', false).text(btnText);
            }
        });
    });

})(jQuery);
