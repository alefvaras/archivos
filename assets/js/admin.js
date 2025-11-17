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

    // Solicitar Folios a SimpleAPI
    $('#solicitar-folios-form').on('submit', function(e) {
        e.preventDefault();

        var tipoDte = $('#tipo_dte_solicitar').val();
        var cantidad = $('#cantidad_folios').val();
        var $btn = $('#btn-solicitar-folios');
        var $spinner = $('#solicitar-folios-spinner');
        var $result = $('#solicitar-folios-result');
        var btnText = $btn.text();

        if (!tipoDte || !cantidad) {
            $result.html('<div class="notice notice-error"><p>Por favor complete todos los campos</p></div>');
            return;
        }

        if (cantidad < 1 || cantidad > 1000) {
            $result.html('<div class="notice notice-error"><p>La cantidad debe estar entre 1 y 1000 folios</p></div>');
            return;
        }

        if (!confirm('¿Solicitar ' + cantidad + ' folios a SimpleAPI? Esta operación puede tardar unos momentos.')) {
            return;
        }

        $btn.prop('disabled', true).text('Solicitando...');
        $spinner.css('display', 'inline-block');
        $result.html('');

        $.ajax({
            url: simpleDTE.ajax_url,
            type: 'POST',
            data: {
                action: 'simple_dte_solicitar_folios',
                nonce: simpleDTE.nonce,
                tipo_dte: tipoDte,
                cantidad: cantidad
            },
            success: function(response) {
                if (response.success) {
                    $result.html(
                        '<div class="notice notice-success"><p><strong>✓ ' + response.data.message + '</strong></p>' +
                        '<p>Rango de folios: <strong>' + response.data.folio_desde + '</strong> a <strong>' + response.data.folio_hasta + '</strong></p></div>'
                    );

                    // Recargar la página después de 2 segundos para mostrar los nuevos folios
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $result.html('<div class="notice notice-error"><p><strong>Error:</strong> ' + response.data.message + '</p></div>');
                    $btn.prop('disabled', false).text(btnText);
                    $spinner.hide();
                }
            },
            error: function(xhr, status, error) {
                $result.html('<div class="notice notice-error"><p><strong>Error de conexión:</strong> No se pudo completar la solicitud. Por favor intente nuevamente.</p></div>');
                $btn.prop('disabled', false).text(btnText);
                $spinner.hide();
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
                    var xml = response.data.xml;
                    var blob = new Blob([xml], {type: 'text/xml'});
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'RCV-' + fechaDesde + '-' + fechaHasta + '.xml';
                    a.click();

                    $('#resultado-rcv').html('<div class="notice notice-success"><p>' + response.data.mensaje + '</p></div>');
                } else {
                    $('#resultado-rcv').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            }
        });
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

})(jQuery);
