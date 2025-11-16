/* Metabox */
.akb-be-metabox {
    padding: 12px;
}

.akb-be-metabox p {
    margin: 8px 0;
}

.akb-be-metabox select,
.akb-be-metabox input {
    width: 100%;
    max-width: 300px;
}

.akb-be-metabox .button {
    margin-right: 5px;
}

/* Badges de estado */
.akb-be-estado-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    background: #f0f0f1;
    color: #50575e;
}

.akb-be-estado-badge.rec {
    background: #72aee6;
    color: #fff;
}

.akb-be-estado-badge.rsc,
.akb-be-estado-badge.sok {
    background: #00a32a;
    color: #fff;
}

.akb-be-estado-badge.rfr,
.akb-be-estado-badge.rct {
    background: #dba617;
    color: #fff;
}

.akb-be-estado-badge.pdr {
    background: #f0b849;
    color: #fff;
}

/* Info boxes */
.akb-be-info-box {
    padding: 15px;
    border-radius: 4px;
    margin: 15px 0;
}

.akb-be-info-box.success {
    background: #d4edda;
    border-left: 4px solid #28a745;
    color: #155724;
}

.akb-be-info-box.warning {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    color: #856404;
}

.akb-be-info-box.error {
    background: #f8d7da;
    border-left: 4px solid #dc3545;
    color: #721c24;
}

.akb-be-info-box h4 {
    margin-top: 0;
    margin-bottom: 10px;
}

.akb-be-info-box ul {
    margin: 10px 0 0 20px;
}

/* Página de certificación */
.akb-be-cert-dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.akb-be-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.akb-be-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #e0e0e0;
}

.akb-be-card table {
    margin-top: 15px;
}

.akb-be-card table th {
    text-align: left;
    padding-right: 15px;
    font-weight: 600;
}

/* Test cases */
.akb-be-test-cases ul {
    list-style: none;
    padding: 0;
    margin: 15px 0;
}

.akb-be-test-cases li {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f1;
}

.akb-be-test-cases li:last-child {
    border-bottom: none;
}

/* Progress bar */
.akb-be-progress-bar {
    width: 100%;
    height: 30px;
    background: #f0f0f1;
    border-radius: 4px;
    overflow: hidden;
    margin: 15px 0;
}

.akb-be-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #2271b1, #72aee6);
    transition: width 0.3s ease;
}

#akb-be-set-results {
    list-style: none;
    padding: 0;
    margin: 15px 0;
}

#akb-be-set-results li {
    padding: 8px;
    margin: 5px 0;
    border-radius: 3px;
}

#akb-be-set-results li.success {
    background: #d4edda;
    color: #155724;
}

#akb-be-set-results li.error {
    background: #f8d7da;
    color: #721c24;
}

/* Logs */
.akb-be-logs-container {
    max-height: 600px;
    overflow-y: auto;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
}

.akb-be-log-level {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.akb-be-log-level.debug {
    background: #e0e0e0;
    color: #666;
}

.akb-be-log-level.info {
    background: #d1ecf1;
    color: #0c5460;
}

.akb-be-log-level.warning {
    background: #fff3cd;
    color: #856404;
}

.akb-be-log-level.error {
    background: #f8d7da;
    color: #721c24;
}

/* Alertas */
.akb-be-alert {
    display: inline-block;
    padding: 8px 12px;
    border-radius: 4px;
    font-weight: 500;
}

.akb-be-alert.warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffc107;
}

/* Botones deshabilitados */
.button.disabled {
    opacity: 0.6;
    cursor: not-allowed;
}<?php /**
 * Configuración del plugin para WooCommerce
 */if(!defined('ABSPATH')){exit;}class AKB_BE_Settings{public static function init(){add_filter('woocommerce_get_settings_pages',[__CLASS__,'add_settings_page']);add_action('admin_enqueue_scripts',[__CLASS__,'enqueue_admin_assets']);}public static function enqueue_admin_assets($hook){if(strpos($hook,'woocommerce')===false&&$hook!=='post.php'&&$hook!=='shop_order'){return;}wp_enqueue_style('akb-be-admin',AKB_BE_ASSETS.'admin.css',[],AKB_BE_VERSION);wp_enqueue_script('akb-be-admin',AKB_BE_ASSETS.'admin.js',['jquery'],AKB_BE_VERSION,true);wp_localize_script('akb-be-admin','akbBE',['ajax_url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('akb_be_nonce'),'strings'=>['confirm_generate'=>__('¿Generar boleta para esta orden?','akb-be'),'confirm_set'=>__('¿Enviar set completo de pruebas? Esto generará 5 boletas.','akb-be'),'generating'=>__('Generando...','akb-be'),'success'=>__('Operación exitosa','akb-be'),'error'=>__('Error en la operación','akb-be'),]]);}public static function add_settings_page($settings){$settings[]=new class extends WC_Settings_Page{public function __construct(){$this->id='akb_be';$this->label=__('Boleta Electrónica','akb-be');parent::__construct();}public function get_sections(){return[''=>__('Configuración General','akb-be'),'caf'=>__('Archivo CAF','akb-be'),'logs'=>__('Registros','akb-be')];}public function get_settings($current_section=''){if($current_section==='caf'){return $this->get_caf_settings();}elseif($current_section==='logs'){return $this->get_logs_settings();}return $this->get_general_settings();}private function get_general_settings(){return[['title'=>__('Datos Tributarios del Emisor','akb-be'),'type'=>'title','id'=>'akb_be_emisor_section',],['title'=>__('RUT Emisor','akb-be'),'type'=>'text','id'=>'akb_be_rut_emisor','css'=>'width:200px;','desc'=>__('Sin puntos y con guion, ej: 12345678-9','akb-be'),],['title'=>__('Razón Social','akb-be'),'type'=>'text','id'=>'akb_be_razon_social','css'=>'width:400px;',],['title'=>__('Giro Comercial','akb-be'),'type'=>'text','id'=>'akb_be_giro','css'=>'width:400px;',],['title'=>__('Dirección de Origen','akb-be'),'type'=>'text','id'=>'akb_be_dir_origen','css'=>'width:400px;',],['title'=>__('Comuna de Origen','akb-be'),'type'=>'text','id'=>'akb_be_comuna_origen','css'=>'width:200px;',],['title'=>__('Ciudad de Origen','akb-be'),'type'=>'text','id'=>'akb_be_ciudad_origen','css'=>'width:200px;',],['title'=>__('Número de Resolución SII','akb-be'),'type'=>'text','id'=>'akb_be_nro_resol','css'=>'width:100px;',],['title'=>__('Fecha de Resolución','akb-be'),'type'=>'date','id'=>'akb_be_fch_resol','css'=>'width:150px;',],['type'=>'sectionend','id'=>'akb_be_emisor_section',],['title'=>__('Archivo CAF (XML)','akb-be'),'type'=>'title','id'=>'akb_be_caf_section',],['title'=>__('Subir Archivo CAF','akb-be'),'type'=>'file','id'=>'akb_be_caf_file','desc'=>__('Sube el archivo XML CAF proporcionado por el SII','akb-be'),],['type'=>'sectionend','id'=>'akb_be_caf_section',],];}private function get_caf_settings(){$caf_info=AKB_BE_CAF_Manager::get_caf_info();$stats=AKB_BE_Folio_Manager::get_stats();$settings=[];if($caf_info){$settings[]=['title'=>__('Estado del CAF','akb-be'),'type'=>'info_box','id'=>'akb_be_caf_status','desc'=>sprintf('<div class="akb-be-info-box success">
                                <h4>%s</h4>
                                <ul>
                                    <li><strong>%s:</strong> %d - %d</li>
                                    <li><strong>%s:</strong> %d</li>
                                    <li><strong>%s:</strong> %d</li>
                                    <li><strong>%s:</strong> %d (%.1f%%)</li>
                                </ul></div>',__('CAF Activo','akb-be'),__('Rango de Folios','akb-be'),$stats['rango_desde'],$stats['rango_hasta'],__('Folios Disponibles','akb-be'),$stats['folios_disponibles'],__('Folios Usados','akb-be'),$stats['folios_usados'],__('Folios Restantes','akb-be'),$stats['folios_restantes'],$stats['porcentaje_usado'])];}else{$settings[]=['title'=>__('Estado del CAF','akb-be'),'type'=>'info_box','id'=>'akb_be_caf_status','desc'=>'<div class="akb-be-info-box warning"><h4>'.__('No hay CAF configurado','akb-be').'</h4><p>'.__('Por favor suba un archivo CAF para comenzar a emitir boletas.','akb-be').'</p></div>'];}$settings[]=['type'=>'sectionend','id'=>'akb_be_caf_status'];return $settings;}private function get_logs_settings(){$logs=AKB_BE_Logger::get_recent_logs(50);$html='<div class="akb-be-logs-container"><table class="widefat striped"><thead><tr>';$html.='<th>'.__('Fecha/Hora','akb-be').'</th>';$html.='<th>'.__('Nivel','akb-be').'</th>';$html.='<th>'.__('Mensaje','akb-be').'</th>';$html.='</tr></thead><tbody>';foreach($logs as $log){$nivel_class=strtolower($log['nivel']);$html.=sprintf('<tr><td>%s</td><td><span class="akb-be-log-level %s">%s</span></td><td>%s</td></tr>',esc_html($log['fecha_hora']),esc_attr($nivel_class),esc_html($log['nivel']),esc_html($log['mensaje']));}$html.='</tbody></table></div>';return[['title'=>__('Registros Recientes','akb-be'),'type'=>'title','id'=>'akb_be_logs_section'],['type'=>'html','id'=>'akb_be_logs_display','desc'=>$html],['type'=>'sectionend','id'=>'akb_be_logs_section'],];}public function output(){global $current_section;$settings=$this->get_settings($current_section);foreach($settings as $setting){if(isset($setting['type'])){if($setting['type']==='info_box'||$setting['type']==='html'){echo $setting['desc'];}elseif($setting['type']==='file'){$this->output_file_upload_field($setting);}}}WC_Admin_Settings::output_fields($settings);}private function output_file_upload_field($field){ ?>
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="<?php echo esc_attr($field['id']); ?>"><?php echo esc_html($field['title']); ?></label>
                    </th>
                    <td class="forminp forminp-file">
                        <input type="file" name="<?php echo esc_attr($field['id']); ?>" id="<?php echo esc_attr($field['id']); ?>" accept=".xml" />
                        <p class="description"><?php echo esc_html($field['desc']); ?></p>
                    </td>
                </tr>
                <?php }public function save(){global $current_section;if(!empty($_FILES['akb_be_caf_file']['name'])){$file=$_FILES['akb_be_caf_file'];$validation=AKB_BE_Helpers::validate_upload($file,['xml']);if(!$validation['success']){WC_Admin_Settings::add_error($validation['message']);}else{$upload_dir=AKB_BE_Helpers::create_secure_upload_dir();$filename='caf-'.time().'.xml';$filepath=$upload_dir.$filename;if(@move_uploaded_file($file['tmp_name'],$filepath)){chmod($filepath,0600);update_option('akb_be_caf_path',$filepath);$result=AKB_BE_CAF_Manager::process_caf($filepath);if(!$result['success']){WC_Admin_Settings::add_error($result['message']);}else{WC_Admin_Settings::add_message($result['message']);}}else{WC_Admin_Settings::add_error(__('No se pudo guardar el archivo CAF.','akb-be'));}}}$settings=$this->get_settings($current_section);WC_Admin_Settings::save_fields($settings);}};return $settings;}}<?php /**
 * Gestor de RCOF (Reporte de Consumo de Folios)
 */if(!defined('ABSPATH')){exit;}class AKB_BE_RCOF{/**
     * Inicializar
     */public static function init(){add_action('wp_ajax_akb_be_generar_rcof_manual',[__CLASS__,'ajax_generar_rcof_manual']);}/**
     * Construir RCOF para una orden específica
     */public static function build_rcof_para_orden(WC_Order $order):string|WP_Error{$folio=(int)$order->get_meta('_akb_be_folio',true);if(!$folio){return new WP_Error('akb_rcof_no_folio',__('La orden no tiene folio asignado','akb-be'));}AKB_BE_Logger::debug("Construyendo RCOF para orden {$order->get_id()}, folio {$folio}");$rut_emisor=get_option('akb_be_rut_emisor','');$fecha_hoy=date('Y-m-d');$xml='<?xml version="1.0" encoding="ISO-8859-1"?>';$xml.='<ConsumoFolios xmlns="http://www.sii.cl/SiiDte" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.0" xsi:schemaLocation="http://www.sii.cl/SiiDte ConsumoFolio_v10.xsd">';$xml.='<DocumentoConsumoFolios ID="CF">';$xml.='<Caratula>';$xml.='<RutEmisor>'.esc_html($rut_emisor).'</RutEmisor>';$xml.='<RutEnvia>'.esc_html($rut_emisor).'</RutEnvia>';$xml.='<FchResol>2014-08-22</FchResol>';$xml.='<NroResol>0</NroResol>';$xml.='<FchInicio>'.$fecha_hoy.'</FchInicio>';$xml.='<FchFinal>'.$fecha_hoy.'</FchFinal>';$xml.='<SecEnvio>1</SecEnvio>';$xml.='<TmstFirmaEnv>'.date('Y-m-d\TH:i:s').'</TmstFirmaEnv>';$xml.='</Caratula>';$xml.='<Resumen>';$xml.='<TipoDocumento>39</TipoDocumento>';$xml.='<MntNeto>0</MntNeto>';$xml.='<MntIva>0</MntIva>';$xml.='<TasaIVA>19</TasaIVA>';$xml.='<MntExento>0</MntExento>';$xml.='<MntTotal>0</MntTotal>';$xml.='<FoliosEmitidos>1</FoliosEmitidos>';$xml.='<FoliosAnulados>0</FoliosAnulados>';$xml.='<FoliosUtilizados>1</FoliosUtilizados>';$xml.='<RangoUtilizados>';$xml.='<Inicial>'.$folio.'</Inicial>';$xml.='<Final>'.$folio.'</Final>';$xml.='</RangoUtilizados>';$xml.='</Resumen>';$xml.='</DocumentoConsumoFolios>';$xml.='</ConsumoFolios>';return $xml;}/**
     * Construir RCOF consolidado para múltiples órdenes
     */public static function build_rcof_consolidado(array $orders):string|WP_Error{if(empty($orders)){return new WP_Error('akb_rcof_no_orders',__('No hay órdenes para generar RCOF','akb-be'));}AKB_BE_Logger::info("Construyendo RCOF consolidado para ".count($orders)." órdenes");$folios=[];$monto_total=0;$monto_neto=0;$monto_iva=0;$monto_exento=0;foreach($orders as $order){$folio=(int)$order->get_meta('_akb_be_folio',true);if($folio){$folios[]=$folio;$monto_total+=$order->get_total();}}if(empty($folios)){return new WP_Error('akb_rcof_no_folios',__('No se encontraron folios válidos','akb-be'));}sort($folios);$folio_inicial=$folios;$folio_final=end($folios);$rut_emisor=get_option('akb_be_rut_emisor','');$fecha_hoy=date('Y-m-d');$monto_neto=(int)round($monto_total/1.19);$monto_iva=(int)round($monto_neto*0.19);$xml='<?xml version="1.0" encoding="ISO-8859-1"?>';$xml.='<ConsumoFolios xmlns="http://www.sii.cl/SiiDte" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.0" xsi:schemaLocation="http://www.sii.cl/SiiDte ConsumoFolio_v10.xsd">';$xml.='<DocumentoConsumoFolios ID="CF">';$xml.='<Caratula>';$xml.='<RutEmisor>'.esc_html($rut_emisor).'</RutEmisor>';$xml.='<RutEnvia>'.esc_html($rut_emisor).'</RutEnvia>';$xml.='<FchResol>2014-08-22</FchResol>';$xml.='<NroResol>0</NroResol>';$xml.='<FchInicio>'.$fecha_hoy.'</FchInicio>';$xml.='<FchFinal>'.$fecha_hoy.'</FchFinal>';$xml.='<SecEnvio>1</SecEnvio>';$xml.='<TmstFirmaEnv>'.date('Y-m-d\TH:i:s').'</TmstFirmaEnv>';$xml.='</Caratula>';$xml.='<Resumen>';$xml.='<TipoDocumento>39</TipoDocumento>';$xml.='<MntNeto>'.$monto_neto.'</MntNeto>';$xml.='<MntIva>'.$monto_iva.'</MntIva>';$xml.='<TasaIVA>19</TasaIVA>';$xml.='<MntExento>'.$monto_exento.'</MntExento>';$xml.='<MntTotal>'.(int)$monto_total.'</MntTotal>';$xml.='<FoliosEmitidos>'.count($folios).'</FoliosEmitidos>';$xml.='<FoliosAnulados>0</FoliosAnulados>';$xml.='<FoliosUtilizados>'.count($folios).'</FoliosUtilizados>';$xml.='<RangoUtilizados>';$xml.='<Inicial>'.$folio_inicial.'</Inicial>';$xml.='<Final>'.$folio_final.'</Final>';$xml.='</RangoUtilizados>';$xml.='</Resumen>';$xml.='</DocumentoConsumoFolios>';$xml.='</ConsumoFolios>';return $xml;}/**
     * AJAX: Generar RCOF manual
     */public static function ajax_generar_rcof_manual(){check_ajax_referer('akb_be_nonce','nonce');if(!current_user_can('manage_woocommerce')){wp_send_json_error(['message'=>__('Permisos insuficientes','akb-be')]);}$fecha_desde=sanitize_text_field($_POST['fecha_desde']??date('Y-m-d'));$fecha_hasta=sanitize_text_field($_POST['fecha_hasta']??date('Y-m-d'));$orders=wc_get_orders(['limit'=>-1,'date_created'=>$fecha_desde.'...'.$fecha_hasta,'meta_key'=>'_akb_be_generada','meta_value'=>'yes']);if(empty($orders)){wp_send_json_error(['message'=>__('No se encontraron boletas en el rango de fechas','akb-be')]);}$rcof_xml=self::build_rcof_consolidado($orders);if(is_wp_error($rcof_xml)){wp_send_json_error(['message'=>$rcof_xml->get_error_message()]);}$response=AKB_BE_API::enviar_rcof($rcof_xml);if(is_wp_error($response)){wp_send_json_error(['message'=>$response->get_error_message()]);}wp_send_json_success(['message'=>__('RCOF enviado exitosamente','akb-be'),'folios_count'=>count($orders),'response'=>$response]);}}<?php /**
 * Gestión de metadatos de órdenes
 */if(!defined('ABSPATH')){exit;}class AKB_BE_Order_Meta{/**
     * Inicializar
     */public static function init(){add_action('init',[__CLASS__,'register_meta']);}/**
     * Registrar metadatos personalizados
     */public static function register_meta(){$meta_keys=['_akb_be_generada'=>'string','_akb_be_folio'=>'integer','_akb_be_track_id'=>'integer','_akb_be_estado'=>'string','_akb_be_fecha_envio'=>'string','_akb_be_ultima_consulta'=>'string','_akb_be_dte_xml'=>'string','_akb_be_sobre_xml'=>'string','_akb_be_response_xml'=>'string','_akb_be_rcof_enviado'=>'string','_akb_be_rcof_fecha'=>'string','_akb_be_set_completo_enviado'=>'string','_akb_be_set_completo_resultados'=>'string','_akb_be_set_completo_fecha'=>'string'];foreach($meta_keys as $key=>$type){register_post_meta('shop_order',$key,['type'=>$type,'single'=>true,'show_in_rest'=>false,'sanitize_callback'=>'sanitize_text_field']);if(class_exists('Automattic\WooCommerce\Utilities\OrderUtil')){register_meta('wc_order',$key,['type'=>$type,'single'=>true,'show_in_rest'=>false]);}}}}<?php /**
 * Sistema de logging para el plugin
 */if(!defined('ABSPATH')){exit;}class AKB_BE_Logger{private static $table_name=null;/**
     * Inicializar
     */public static function init(){global $wpdb;self::$table_name=$wpdb->prefix.'akb_be_logs';}/**
     * Log nivel DEBUG
     */public static function debug(string $message,array $context=[],?int $order_id=null){self::log('DEBUG',$message,$context,$order_id);}/**
     * Log nivel INFO
     */public static function info(string $message,array $context=[],?int $order_id=null){self::log('INFO',$message,$context,$order_id);}/**
     * Log nivel WARNING
     */public static function warning(string $message,array $context=[],?int $order_id=null){self::log('WARNING',$message,$context,$order_id);}/**
     * Log nivel ERROR
     */public static function error(string $message,array $context=[],?int $order_id=null){self::log('ERROR',$message,$context,$order_id);}/**
     * Escribir log
     */private static function log(string $level,string $message,array $context=[],?int $order_id=null){global $wpdb;if(get_option('akb_be_debug',false)){$log_message=sprintf('[AKB BE] [%s] %s',$level,$message);if(!empty($context)){$log_message.=' | Context: '.json_encode($context,JSON_UNESCAPED_UNICODE);}error_log($log_message);}if(self::$table_name){$wpdb->insert(self::$table_name,['fecha_hora'=>current_time('mysql'),'nivel'=>$level,'mensaje'=>$message,'contexto'=>!empty($context)?wp_json_encode($context,JSON_UNESCAPED_UNICODE):null,'order_id'=>$order_id],['%s','%s','%s','%s','%d']);}}/**
     * Obtener logs recientes
     */public static function get_recent_logs(int $limit=100,?string $level=null):array{global $wpdb;if(!self::$table_name){return[];}$where='';if($level){$where=$wpdb->prepare(' WHERE nivel = %s',$level);}$query="SELECT * FROM ".self::$table_name.$where." ORDER BY fecha_hora DESC LIMIT %d";return $wpdb->get_results($wpdb->prepare($query,$limit),ARRAY_A);}/**
     * Limpiar logs antiguos
     */public static function clean_old_logs(int $days=30):int{global $wpdb;if(!self::$table_name){return 0;}$date=date('Y-m-d H:i:s',strtotime("-{$days} days"));return $wpdb->query($wpdb->prepare("DELETE FROM ".self::$table_name." WHERE fecha_hora < %s",$date));}}<?php /**
 * Funciones helper reutilizables
 */if(!defined('ABSPATH')){exit;}class AKB_BE_Helpers{/**
     * Verificar si estamos en ambiente de certificación
     */public static function is_cert_env():bool{return get_option('akb_be_env','prod')==='cert';}/**
     * Verificar si estamos en ambiente de pruebas automáticas con set
     */public static function is_testset_env():bool{return get_option('akb_be_env','prod')==='testset';}/**
     * Verificar si estamos en producción
     */public static function is_prod_env():bool{return get_option('akb_be_env','prod')==='prod';}/**
     * Limpiar RUT (quitar puntos y dejar solo guion)
     */public static function limpiar_rut(string $rut):string{$rut=str_replace('.','',$rut);$rut=strtoupper(trim($rut));return $rut;}/**
     * Validar formato de RUT chileno
     */public static function validar_rut(string $rut):bool{$rut=self::limpiar_rut($rut);if(!preg_match('/^(\d{1,8})-([0-9K])$/',$rut,$matches)){return false;}$numero=$matches[1];$dv=$matches[2];return self::calcular_dv($numero)===$dv;}/**
     * Calcular dígito verificador de RUT
     */public static function calcular_dv(string $numero):string{$suma=0;$multiplicador=2;for($i=strlen($numero)-1;$i>=0;$i--){$suma+=(int)$numero[$i]*$multiplicador;$multiplicador=$multiplicador===7?2:$multiplicador+1;}$resto=$suma%11;$dv=11-$resto;if($dv===11)return '0';if($dv===10)return 'K';return(string)$dv;}/**
     * Crear directorio seguro para uploads
     */public static function create_secure_upload_dir():string{$upload_dir=wp_upload_dir();$akb_dir=trailingslashit($upload_dir['basedir']).'akb-be-secure/';if(!file_exists($akb_dir)){wp_mkdir_p($akb_dir);file_put_contents($akb_dir.'.htaccess','Deny from all');file_put_contents($akb_dir.'index.php','<?php // Silence is golden');}return $akb_dir;}/**
     * Crear archivo temporal
     */public static function create_temp_file(string $content,string $prefix='akb_'):string{$temp=tempnam(sys_get_temp_dir(),$prefix);$xml_file=$temp.'.xml';@rename($temp,$xml_file);file_put_contents($xml_file,$content);return $xml_file;}/**
     * Eliminar archivo temporal
     */public static function delete_temp_file(string $filepath):bool{if(file_exists($filepath)){return@unlink($filepath);}return false;}/**
     * Validar archivo subido
     */public static function validate_upload(array $file,array $allowed_extensions):array{if($file['error']!==UPLOAD_ERR_OK){return['success'=>false,'message'=>__('Error al subir el archivo','akb-be')];}$ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));if(!in_array($ext,$allowed_extensions,true)){return['success'=>false,'message'=>sprintf(__('Extensión no permitida. Extensiones permitidas: %s','akb-be'),implode(', ',$allowed_extensions))];}if($file['size']>5*1024*1024){return['success'=>false,'message'=>__('El archivo es demasiado grande. Máximo 5MB','akb-be')];}return['success'=>true];}/**
     * Formatear monto para Chile (separador de miles con punto)
     */public static function format_monto(float $monto):string{return '$'.number_format($monto,0,',','.');}/**
     * Sanitizar XML
     */public static function sanitize_xml(string $xml):string{$xml=str_replace("\xEF\xBB\xBF",'',$xml);$xml=str_replace("\\r\\n","\\n",$xml);$xml=str_replace("\\r","\\n",$xml);return trim($xml);}}<?php /**
 * Clase generadora de Boletas Electrónicas para WooCommerce
 */if(!defined('ABSPATH')){exit;}class AKB_BE_Generator{/**
     * Genera boleta electrónica para una orden WooCommerce
     *
     * @param WC_Order $order
     * @param string|null $caso_prueba (Opcional) para set de prueba
     * @return array|WP_Error Resultados de la generación o error
     */public static function generar_boleta_para_order(WC_Order $order,?string $caso_prueba=null){$required_options=['akb_be_rut_emisor','akb_be_razon_social','akb_be_giro','akb_be_dir_origen','akb_be_comuna_origen','akb_be_ciudad_origen','akb_be_fch_resol','akb_be_nro_resol','akb_be_caf_path'];foreach($required_options as $opt){$val=get_option($opt,'');if(empty($val)){return new WP_Error('akb_missing_option',sprintf(__('La opción %s está vacía. Configúrela antes.','akb-be'),$opt));}if($opt==='akb_be_caf_path'&&!file_exists($val)){return new WP_Error('akb_caf_missing',__('Archivo CAF no encontrado o no válido.','akb-be'));}}$dte_data=['Encabezado'=>['IdDoc'=>['TipoDTE'=>39,'Folio'=>self::reservar_folio(),'FchEmis'=>date('Y-m-d'),'NroResol'=>get_option('akb_be_nro_resol'),'FchResol'=>get_option('akb_be_fch_resol')]],'Emisor'=>['RUTEmisor'=>get_option('akb_be_rut_emisor'),'RznSoc'=>get_option('akb_be_razon_social'),'GiroEmis'=>get_option('akb_be_giro'),'DirOrigen'=>get_option('akb_be_dir_origen'),'CmnaOrigen'=>get_option('akb_be_comuna_origen'),'CiudadOrigen'=>get_option('akb_be_ciudad_origen'),],'Receptor'=>self::build_receptor($order,$caso_prueba),'Detalle'=>self::build_detalle($order,$caso_prueba),'Totales'=>self::build_totales($order,$caso_prueba),];if($caso_prueba){}$result=AKB_BE_API::generar_dte($dte_data);if(is_wp_error($result)){return $result;}if(empty($result['track_id'])){return new WP_Error('akb_api_error',__('No se generó track ID en la respuesta SimpleAPI.','akb-be'));}$order->update_meta_data('_akb_be_folio',$dte_data['Encabezado']['IdDoc']['Folio']);$order->update_meta_data('_akb_be_track_id',$result['track_id']);$order->update_meta_data('_akb_be_estado','REC');$order->update_meta_data('_akb_be_generada','yes');$order->save();return['success'=>true,'message'=>__('Boleta generada correctamente.','akb-be'),'folio'=>$dte_data['Encabezado']['IdDoc']['Folio'],'track_id'=>$result['track_id']];}/**
     * Reserva folio usando Folio Manager
     */private static function reservar_folio(){$folio=AKB_BE_Folio_Manager::reservar_folio();if(is_wp_error($folio)){throw new Exception($folio->get_error_message());}return $folio;}/**
     * Construye datos del Receptor de la boleta a partir del pedido Woo
     */private static function build_receptor(WC_Order $order,?string $caso_prueba=null){$receptor=['RUTRecep'=>'0','RznSocRecep'=>'CONSUMIDOR FINAL','GiroRecep'=>'','DirRecep'=>'','CmnaRecep'=>'','CiudadRecep'=>'',];if(!$caso_prueba){$billing_phone=$order->get_billing_phone();$billing_company=$order->get_billing_company();if($order->get_billing_email()){$receptor['RznSocRecep']=$order->get_billing_first_name().' '.$order->get_billing_last_name();if(!empty($billing_company)){$receptor['RznSocRecep']=$billing_company;}}if($order->get_meta('_billing_rut')){$receptor['RUTRecep']=$order->get_meta('_billing_rut');}$receptor['DirRecep']=$order->get_billing_address_1().' '.$order->get_billing_address_2();$receptor['CmnaRecep']=$order->get_billing_city();$receptor['CiudadRecep']=$order->get_billing_state();if(class_exists('AKB_BE_Comuna_Normalizer')){$receptor['CmnaRecep']=AKB_BE_Comuna_Normalizer::normalizar($receptor['CmnaRecep']);}}return $receptor;}/**
     * Construye detalle de items para la boleta basándose en el pedido WooCommerce
     */private static function build_detalle(WC_Order $order,?string $caso_prueba=null){$items=[];foreach($order->get_items()as $item_id=>$item){$product=$item->get_product();$detalle=['NmbItem'=>$item->get_name(),'QtyItem'=>floatval($item->get_quantity()),'UnmdItem'=>'UNI','PrcItem'=>floatval($item->get_total()/max($item->get_quantity(),1)),'MontoItem'=>floatval($item->get_total()),];$items[]=$detalle;}return $items;}/**
     * Construye totales de la boleta a partir del pedido Woo
     */private static function build_totales(WC_Order $order,?string $caso_prueba=null){$totales=['MntNeto'=>floatval($order->get_subtotal()),'TasaIVA'=>19,'IVA'=>floatval($order->get_total_tax()),'MntTotal'=>floatval($order->get_total()),'MntExe'=>0,'MntExento'=>0,];return $totales;}}<?php /**
 * Gestor de folios - Reserva atómica de folios para evitar duplicados
 */if(!defined('ABSPATH')){exit;}class AKB_BE_Folio_Manager{/**
     * Inicializar
     */public static function init(){}/**
     * Reservar folio de forma atómica usando compare-and-swap
     */public static function reservar_folio():int|WP_Error{global $wpdb;$caf_info=AKB_BE_CAF_Manager::get_caf_info();if(!$caf_info){return new WP_Error('akb_caf_no_found',__('No hay CAF configurado. Por favor suba un archivo CAF.','akb-be'));}$folio_desde=(int)$caf_info['folio_desde'];$folio_hasta=(int)$caf_info['folio_hasta'];$option_name='akb_be_folio_actual';$table=$wpdb->prefix.'options';$folio_actual=(int)get_option($option_name,$folio_desde-1);$max_attempts=10;for($attempt=1;$attempt<=$max_attempts;$attempt++){$nuevo_folio=$folio_actual+1;if($nuevo_folio<$folio_desde||$nuevo_folio>$folio_hasta){AKB_BE_Logger::error('Folio fuera de rango CAF',['folio'=>$nuevo_folio,'rango'=>"$folio_desde - $folio_hasta"]);return new WP_Error('akb_folio_out_of_range',sprintf(__('No hay más folios disponibles en el CAF. Rango: %d - %d','akb-be'),$folio_desde,$folio_hasta));}$rows_affected=$wpdb->update($table,['option_value'=>(string)$nuevo_folio],['option_name'=>$option_name,'option_value'=>(string)$folio_actual],['%s'],['%s','%s']);if($rows_affected===1){AKB_BE_Logger::debug("Folio {$nuevo_folio} reservado correctamente (intento {$attempt})");wp_cache_delete($option_name,'options');return $nuevo_folio;}usleep(random_int(5000,30000));$folio_actual=(int)$wpdb->get_var($wpdb->prepare("SELECT option_value FROM $table WHERE option_name = %s",$option_name));AKB_BE_Logger::debug("Race condition en reserva de folio, reintentando (intento {$attempt})");}AKB_BE_Logger::error('No se pudo reservar folio después de múltiples intentos',['max_attempts'=>$max_attempts]);return new WP_Error('akb_folio_reservation_failed',__('No se pudo reservar folio. Por favor intente nuevamente.','akb-be'));}/**
     * Obtener siguiente folio disponible (sin reservar)
     */public static function get_next_folio():int|WP_Error{$caf_info=AKB_BE_CAF_Manager::get_caf_info();if(!$caf_info){return new WP_Error('akb_caf_no_found',__('No hay CAF configurado','akb-be'));}$folio_actual=(int)get_option('akb_be_folio_actual',$caf_info['folio_desde']-1);$siguiente_folio=$folio_actual+1;if($siguiente_folio>$caf_info['folio_hasta']){return new WP_Error('akb_no_folios',__('No hay más folios disponibles','akb-be'));}return $siguiente_folio;}/**
     * Verificar si un folio está dentro del rango válido
     */public static function is_valid_folio(int $folio):bool{$caf_info=AKB_BE_CAF_Manager::get_caf_info();if(!$caf_info){return false;}return $folio>=$caf_info['folio_desde']&&$folio<=$caf_info['folio_hasta'];}/**
     * Obtener estadísticas de uso de folios
     */public static function get_stats():array{$caf_info=AKB_BE_CAF_Manager::get_caf_info();if(!$caf_info){return['tiene_caf'=>false,'mensaje'=>__('No hay CAF configurado','akb-be')];}$folio_actual=(int)get_option('akb_be_folio_actual',$caf_info['folio_desde']-1);$folios_usados=max(0,$folio_actual-$caf_info['folio_desde']+1);$folios_restantes=max(0,$caf_info['folio_hasta']-$folio_actual);$porcentaje_usado=$caf_info['folios_disponibles']>0?round(($folios_usados/$caf_info['folios_disponibles'])*100,1):0;return['tiene_caf'=>true,'rango_desde'=>$caf_info['folio_desde'],'rango_hasta'=>$caf_info['folio_hasta'],'folio_actual'=>$folio_actual,'folios_disponibles'=>$caf_info['folios_disponibles'],'folios_usados'=>$folios_usados,'folios_restantes'=>$folios_restantes,'porcentaje_usado'=>$porcentaje_usado,'alerta_folios_bajos'=>$folios_restantes<10];}/**
     * Resetear contador de folios (solo para testing)
     */public static function reset_folio_counter():bool{if(!AKB_BE_Helpers::is_cert_env()&&!AKB_BE_Helpers::is_testset_env()){AKB_BE_Logger::warning('Intento de reset de folios en ambiente de producción');return false;}$caf_info=AKB_BE_CAF_Manager::get_caf_info();if(!$caf_info){return false;}update_option('akb_be_folio_actual',$caf_info['folio_desde']-1);AKB_BE_Logger::info('Contador de folios reseteado',['folio_inicial'=>$caf_info['folio_desde']]);return true;}}<?php /**
 * Normalizador de nombres de comunas chilenas para Boleta Electrónica
 * 
 * Convierte variaciones de nombres de comunas a su formato oficial
 * según lo requerido por el SII en el DTE (Documento Tributario Electrónico)
 */if(!defined('ABSPATH')){exit;}class AKB_BE_Comuna_Normalizer{/**
     * Mapeo de variaciones de comunas a su nombre oficial
     * @var array
     */private static $comunas=['santiago'=>'Santiago','stgo'=>'Santiago','providencia'=>'Providencia','las condes'=>'Las Condes','vitacura'=>'Vitacura','lo barnechea'=>'Lo Barnechea','la reina'=>'La Reina','ñuñoa'=>'Ñuñoa','nunoa'=>'Ñuñoa','macul'=>'Macul','peñalolen'=>'Peñalolén','penalolen'=>'Peñalolén','la florida'=>'La Florida','san bernardo'=>'San Bernardo','puente alto'=>'Puente Alto','maipu'=>'Maipú','maipú'=>'Maipú','pudahuel'=>'Pudahuel','quilicura'=>'Quilicura','renca'=>'Renca','conchalí'=>'Conchalí','conchali'=>'Conchalí','huechuraba'=>'Huechuraba','recoleta'=>'Recoleta','independencia'=>'Independencia','quinta normal'=>'Quinta Normal','lo prado'=>'Lo Prado','cerro navia'=>'Cerro Navia','estacion central'=>'Estación Central','estación central'=>'Estación Central','pedro aguirre cerda'=>'Pedro Aguirre Cerda','san miguel'=>'San Miguel','san joaquin'=>'San Joaquín','san joaquín'=>'San Joaquín','la granja'=>'La Granja','la pintana'=>'La Pintana','san ramon'=>'San Ramón','san ramón'=>'San Ramón','el bosque'=>'El Bosque','la cisterna'=>'La Cisterna','lo espejo'=>'Lo Espejo','cerrillos'=>'Cerrillos','padre hurtado'=>'Padre Hurtado','peñaflor'=>'Peñaflor','penaflor'=>'Peñaflor','talagante'=>'Talagante','melipilla'=>'Melipilla','buin'=>'Buin','paine'=>'Paine','colina'=>'Colina','lampa'=>'Lampa','til til'=>'Til Til','valparaiso'=>'Valparaíso','valparaíso'=>'Valparaíso','viña del mar'=>'Viña del Mar','vina del mar'=>'Viña del Mar','viña'=>'Viña del Mar','vina'=>'Viña del Mar','con con'=>'Concón','concon'=>'Concón','quintero'=>'Quintero','puchuncavi'=>'Puchuncaví','puchuncaví'=>'Puchuncaví','quillota'=>'Quillota','la calera'=>'La Calera','limache'=>'Limache','olmue'=>'Olmué','olmué'=>'Olmué','quilpue'=>'Quilpué','quilpué'=>'Quilpué','villa alemana'=>'Villa Alemana','casablanca'=>'Casablanca','san antonio'=>'San Antonio','cartagena'=>'Cartagena','el quisco'=>'El Quisco','el tabo'=>'El Tabo','algarrobo'=>'Algarrobo','santo domingo'=>'Santo Domingo','san felipe'=>'San Felipe','los andes'=>'Los Andes','la ligua'=>'La Ligua','petorca'=>'Petorca','concepcion'=>'Concepción','concepción'=>'Concepción','conce'=>'Concepción','talcahuano'=>'Talcahuano','hualpen'=>'Hualpén','hualpén'=>'Hualpén','chiguayante'=>'Chiguayante','san pedro de la paz'=>'San Pedro de la Paz','coronel'=>'Coronel','lota'=>'Lota','tome'=>'Tomé','tomé'=>'Tomé','penco'=>'Penco','chillan'=>'Chillán','chillán'=>'Chillán','los angeles'=>'Los Ángeles','los ángeles'=>'Los Ángeles','temuco'=>'Temuco','padre las casas'=>'Padre Las Casas','nueva imperial'=>'Nueva Imperial','villarrica'=>'Villarrica','pucon'=>'Pucón','pucón'=>'Pucón','angol'=>'Angol','victoria'=>'Victoria','lautaro'=>'Lautaro','rancagua'=>'Rancagua','machalí'=>'Machalí','machali'=>'Machalí','graneros'=>'Graneros','san fernando'=>'San Fernando','santa cruz'=>'Santa Cruz','rengo'=>'Rengo','san vicente'=>'San Vicente','talca'=>'Talca','curico'=>'Curicó','curicó'=>'Curicó','linares'=>'Linares','cauquenes'=>'Cauquenes','constitucion'=>'Constitución','constitución'=>'Constitución','parral'=>'Parral','molina'=>'Molina','arica'=>'Arica','putre'=>'Putre','iquique'=>'Iquique','alto hospicio'=>'Alto Hospicio','pozo almonte'=>'Pozo Almonte','antofagasta'=>'Antofagasta','calama'=>'Calama','tocopilla'=>'Tocopilla','mejillones'=>'Mejillones','taltal'=>'Taltal','copiapo'=>'Copiapó','copiapó'=>'Copiapó','caldera'=>'Caldera','vallenar'=>'Vallenar','chañaral'=>'Chañaral','chanaral'=>'Chañaral','la serena'=>'La Serena','coquimbo'=>'Coquimbo','ovalle'=>'Ovalle','monte patria'=>'Monte Patria','illapel'=>'Illapel','combarbala'=>'Combarbalá','combarbalá'=>'Combarbalá','vicuña'=>'Vicuña','vicuna'=>'Vicuña','valdivia'=>'Valdivia','la union'=>'La Unión','la unión'=>'La Unión','rio bueno'=>'Río Bueno','río bueno'=>'Río Bueno','panguipulli'=>'Panguipulli','puerto montt'=>'Puerto Montt','puerto varas'=>'Puerto Varas','osorno'=>'Osorno','castro'=>'Castro','ancud'=>'Ancud','quellon'=>'Quellón','quellón'=>'Quellón','calbuco'=>'Calbuco','fresia'=>'Fresia','frutillar'=>'Frutillar','llanquihue'=>'Llanquihue','los muermos'=>'Los Muermos','coyhaique'=>'Coyhaique','puerto aysen'=>'Puerto Aysén','puerto aysén'=>'Puerto Aysén','chile chico'=>'Chile Chico','punta arenas'=>'Punta Arenas','puerto natales'=>'Puerto Natales','puerto williams'=>'Puerto Williams','porvenir'=>'Porvenir'];/**
     * Normalizar nombre de comuna a formato oficial
     * 
     * @param string $comuna Nombre de la comuna a normalizar
     * @return string Nombre normalizado de la comuna
     */public static function normalizar(string $comuna):string{$comuna_lower=mb_strtolower(trim($comuna),'UTF-8');if(isset(self::$comunas[$comuna_lower])){return self::$comunas[$comuna_lower];}return mb_convert_case($comuna,MB_CASE_TITLE,'UTF-8');}/**
     * Obtener lista de todas las comunas normalizadas
     * 
     * @return array Lista de comunas únicas
     */public static function get_comunas_list():array{return array_values(array_unique(self::$comunas));}/**
     * Verificar si una comuna existe en el mapeo
     * 
     * @param string $comuna Nombre de la comuna
     * @return bool True si existe, false si no
     */public static function existe(string $comuna):bool{$comuna_lower=mb_strtolower(trim($comuna),'UTF-8');return isset(self::$comunas[$comuna_lower]);}}<?php /**
 * Utilidades para proceso de certificación SII
 */if(!defined('ABSPATH')){exit;}class AKB_BE_Certificacion{/**
     * Inicializar
     */public static function init(){add_action('admin_menu',[__CLASS__,'add_menu_page']);add_action('wp_ajax_akb_be_ejecutar_set_completo',[__CLASS__,'ajax_ejecutar_set_completo']);}/**
     * Agregar página de menú para certificación
     */public static function add_menu_page(){add_submenu_page('woocommerce',__('Certificación SII','akb-be'),__('Certificación SII','akb-be'),'manage_woocommerce','akb-be-certificacion',[__CLASS__,'render_page']);}/**
     * Renderizar página de certificación
     */public static function render_page(){$ambiente=get_option('akb_be_env','prod');$stats=AKB_BE_Folio_Manager::get_stats(); ?>
        <div class="wrap">
            <h1><?php _e('Certificación SII - Boleta Electrónica','akb-be'); ?></h1>
            
            <div class="akb-be-cert-dashboard">
                
                <!-- Estado Actual -->
                <div class="akb-be-card">
                    <h2><?php _e('Estado Actual','akb-be'); ?></h2>
                    <table class="widefat">
                        <tr>
                            <th><?php _e('Ambiente','akb-be'); ?>:</th>
                            <td><strong><?php echo esc_html(strtoupper($ambiente)); ?></strong></td>
                        </tr>
                        <?php if($stats['tiene_caf']): ?>
                        <tr>
                            <th><?php _e('CAF Activo','akb-be'); ?>:</th>
                            <td><?php echo esc_html($stats['rango_desde'].' - '.$stats['rango_hasta']); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Folios Restantes','akb-be'); ?>:</th>
                            <td><?php echo esc_html($stats['folios_restantes']); ?> (<?php echo esc_html($stats['porcentaje_usado']); ?>% usado)</td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td colspan="2">
                                <span class="akb-be-alert warning"><?php _e('No hay CAF configurado','akb-be'); ?></span>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                
                <!-- Set de Pruebas -->
                <?php if($ambiente!=='prod'): ?>
                <div class="akb-be-card">
                    <h2><?php _e('Set de Pruebas para Certificación','akb-be'); ?></h2>
                    <p><?php _e('Ejecutar el set completo de 5 casos de prueba requeridos por el SII para certificación.','akb-be'); ?></p>
                    
                    <div class="akb-be-test-cases">
                        <ul>
                            <li><strong>CASO-1:</strong> Boleta afecta</li>
                            <li><strong>CASO-2:</strong> Boleta exenta</li>
                            <li><strong>CASO-3:</strong> Boleta mixta (afecta + exenta)</li>
                            <li><strong>CASO-4:</strong> Boleta con descuento</li>
                            <li><strong>CASO-5:</strong> Boleta con unidad de medida especial</li>
                        </ul>
                    </div>
                    
                    <p>
                        <button class="button button-primary button-large" id="akb-be-ejecutar-set" data-nonce="<?php echo wp_create_nonce('akb_be_nonce'); ?>">
                            <?php _e('Ejecutar Set Completo','akb-be'); ?>
                        </button>
                    </p>
                    
                    <div id="akb-be-set-progress" style="display:none;">
                        <h3><?php _e('Progreso:','akb-be'); ?></h3>
                        <div class="akb-be-progress-bar">
                            <div class="akb-be-progress-fill" style="width: 0%"></div>
                        </div>
                        <ul id="akb-be-set-results"></ul>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Órdenes Recientes con Boleta -->
                <div class="akb-be-card">
                    <h2><?php _e('Boletas Recientes','akb-be'); ?></h2>
                    <?php self::render_recent_orders(); ?>
                </div>
                
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#akb-be-ejecutar-set').on('click', function() {
                var $btn = $(this);
                var $progress = $('#akb-be-set-progress');
                var $results = $('#akb-be-set-results');
                var $progressBar = $('.akb-be-progress-fill');
                
                if (!confirm('<?php _e('¿Ejecutar set completo de pruebas? Esto generará 5 boletas.','akb-be'); ?>')) {
                    return;
                }
                
                $btn.prop('disabled', true).text('<?php _e('Ejecutando...','akb-be'); ?>');
                $progress.show();
                $results.empty();
                $progressBar.css('width', '0%');
                
                // Crear orden temporal para las pruebas
                $.post(ajaxurl, {
                    action: 'akb_be_ejecutar_set_completo',
                    nonce: $btn.data('nonce')
                }, function(response) {
                    if (response.success) {
                        $progressBar.css('width', '100%');
                        $results.append('<li class="success">✓ ' + response.data.message + '</li>');
                        
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $results.append('<li class="error">✗ ' + response.data.message + '</li>');
                    }
                    
                    $btn.prop('disabled', false).text('<?php _e('Ejecutar Set Completo','akb-be'); ?>');
                });
            });
        });
        </script>
        <?php }/**
     * Renderizar órdenes recientes con boleta
     */private static function render_recent_orders(){$orders=wc_get_orders(['limit'=>20,'orderby'=>'date','order'=>'DESC','meta_key'=>'_akb_be_generada','meta_value'=>'yes']);if(empty($orders)){echo '<p>'.__('No hay boletas generadas aún.','akb-be').'</p>';return;}echo '<table class="widefat striped">';echo '<thead><tr>';echo '<th>'.__('Orden','akb-be').'</th>';echo '<th>'.__('Fecha','akb-be').'</th>';echo '<th>'.__('Folio','akb-be').'</th>';echo '<th>'.__('Track ID','akb-be').'</th>';echo '<th>'.__('Estado','akb-be').'</th>';echo '<th>'.__('Total','akb-be').'</th>';echo '</tr></thead><tbody>';foreach($orders as $order){$folio=$order->get_meta('_akb_be_folio',true);$track=$order->get_meta('_akb_be_track_id',true);$estado=$order->get_meta('_akb_be_estado',true);echo '<tr>';echo '<td><a href="'.esc_url($order->get_edit_order_url()).'">#'.esc_html($order->get_id()).'</a></td>';echo '<td>'.esc_html($order->get_date_created()->date_i18n('d/m/Y H:i')).'</td>';echo '<td>'.esc_html($folio?:'-').'</td>';echo '<td>'.esc_html($track?:'-').'</td>';echo '<td><span class="akb-be-estado-badge '.esc_attr(strtolower($estado)).'">'.esc_html($estado?:'—').'</span></td>';echo '<td>'.wp_kses_post($order->get_formatted_order_total()).'</td>';echo '</tr>';}echo '</tbody></table>';}/**
     * AJAX: Ejecutar set completo de pruebas
     */public static function ajax_ejecutar_set_completo(){check_ajax_referer('akb_be_nonce','nonce');if(!current_user_can('manage_woocommerce')){wp_send_json_error(['message'=>__('Permisos insuficientes','akb-be')]);}$order=wc_create_order();if(!$order){wp_send_json_error(['message'=>__('No se pudo crear orden de prueba','akb-be')]);}$product=wc_get_product(wc_get_products(['limit'=>1])[0]->get_id());if($product){$order->add_product($product,1);}else{$order->add_product(null,1,['name'=>'Producto de Prueba','total'=>1000]);}$order->calculate_totals();$order->set_status('processing');$order->save();AKB_BE_Logger::info("Iniciando ejecución de set completo desde panel de certificación",[],$order->get_id());$casos=['CASO-1','CASO-2','CASO-3','CASO-4','CASO-5'];$resultados=[];$exitosos=0;foreach($casos as $caso){$resultado=AKB_BE_Generator::generar_boleta_para_order($order,$caso);if(is_wp_error($resultado)){$resultados[$caso]='ERROR: '.$resultado->get_error_message();}else{$resultados[$caso]=sprintf('OK - Folio: %d, Track: %d',$resultado['folio'],$resultado['track_id']);$exitosos++;}sleep(1);}$order->update_meta_data('_akb_be_set_certificacion','yes');$order->update_meta_data('_akb_be_set_resultados',$resultados);$order->save();wp_send_json_success(['message'=>sprintf(__('Set completo ejecutado: %d de %d casos exitosos','akb-be'),$exitosos,count($casos)),'resultados'=>$resultados,'order_id'=>$order->get_id()]);}}<?php /**
 * Gestor de archivos CAF (Código de Autorización de Folios)
 */if(!defined('ABSPATH')){exit;}class AKB_BE_CAF_Manager{/**
     * Inicializar
     */public static function init(){add_action('wp_ajax_akb_be_upload_caf',[__CLASS__,'ajax_upload_caf']);}/**
     * Procesar y validar archivo CAF
     */public static function process_caf(string $filepath):array{if(!file_exists($filepath)){return['success'=>false,'message'=>__('Archivo CAF no encontrado','akb-be')];}$xml_content=file_get_contents($filepath);if($xml_content===false){return['success'=>false,'message'=>__('No se pudo leer el archivo CAF','akb-be')];}libxml_use_internal_errors(true);$xml=simplexml_load_string($xml_content);if($xml===false){$errors=libxml_get_errors();AKB_BE_Logger::error('Error al parsear CAF XML',['errores'=>$errors]);return['success'=>false,'message'=>__('El archivo CAF no es un XML válido','akb-be')];}try{$caf_data=self::extract_caf_data($xml);if(!$caf_data['success']){return $caf_data;}update_option('akb_be_caf_info',$caf_data['data']);update_option('akb_be_caf_path',$filepath);update_option('akb_be_caf_uploaded_at',current_time('mysql'));$folio_actual=get_option('akb_be_folio_actual',0);if($folio_actual<$caf_data['data']['folio_desde']){update_option('akb_be_folio_actual',$caf_data['data']['folio_desde']-1);}AKB_BE_Logger::info('CAF procesado correctamente',$caf_data['data']);return['success'=>true,'message'=>sprintf(__('CAF cargado correctamente. Rango: %d - %d','akb-be'),$caf_data['data']['folio_desde'],$caf_data['data']['folio_hasta']),'data'=>$caf_data['data']];}catch(Exception $e){AKB_BE_Logger::error('Excepción al procesar CAF',['error'=>$e->getMessage()]);return['success'=>false,'message'=>__('Error al procesar CAF: ','akb-be').$e->getMessage()];}}/**
     * Extraer datos del CAF
     */private static function extract_caf_data($xml):array{try{$xml->registerXPathNamespace('caf','http://www.sii.cl/SiiDte');$caf=$xml->xpath('//CAF')?:$xml->xpath('//caf:CAF');if(empty($caf)){return['success'=>false,'message'=>__('No se encontró el nodo CAF en el XML','akb-be')];}$caf=$caf;$da=$caf->DA??$caf->children('http://www.sii.cl/SiiDte')->DA;if(!$da){return['success'=>false,'message'=>__('No se encontró el nodo DA en el CAF','akb-be')];}$tipo_dte=(int)((string)$da->TD);$folio_desde=(int)((string)$da->RNG->D);$folio_hasta=(int)((string)$da->RNG->H);$fecha_autorizacion=(string)$da->FA;$rut_emisor=(string)$da->RE;if($tipo_dte!==39){return['success'=>false,'message'=>sprintf(__('El CAF debe ser de tipo 39 (Boleta Electrónica). Tipo recibido: %d','akb-be'),$tipo_dte)];}if($folio_desde<=0||$folio_hasta<=0||$folio_hasta<$folio_desde){return['success'=>false,'message'=>__('Rango de folios inválido en el CAF','akb-be')];}$rut_configurado=get_option('akb_be_rut_emisor','');if($rut_configurado&&AKB_BE_Helpers::limpiar_rut($rut_emisor)!==AKB_BE_Helpers::limpiar_rut($rut_configurado)){return['success'=>false,'message'=>sprintf(__('El RUT del CAF (%s) no coincide con el RUT configurado (%s)','akb-be'),$rut_emisor,$rut_configurado)];}return['success'=>true,'data'=>['tipo_dte'=>$tipo_dte,'folio_desde'=>$folio_desde,'folio_hasta'=>$folio_hasta,'fecha_autorizacion'=>$fecha_autorizacion,'rut_emisor'=>$rut_emisor,'folios_disponibles'=>$folio_hasta-$folio_desde+1]];}catch(Exception $e){return['success'=>false,'message'=>__('Error al extraer datos del CAF: ','akb-be').$e->getMessage()];}}/**
     * Obtener información del CAF actual
     */public static function get_caf_info():?array{$caf_info=get_option('akb_be_caf_info',[]);if(empty($caf_info)){return null;}$folio_actual=(int)get_option('akb_be_folio_actual',0);$caf_info['folio_actual']=$folio_actual;$caf_info['folios_usados']=max(0,$folio_actual-$caf_info['folio_desde']+1);$caf_info['folios_restantes']=max(0,$caf_info['folio_hasta']-$folio_actual);return $caf_info;}/**
     * Verificar si hay CAF válido
     */public static function has_valid_caf():bool{$caf_info=self::get_caf_info();if(!$caf_info){return false;}$caf_path=get_option('akb_be_caf_path','');if(!file_exists($caf_path)){return false;}if($caf_info['folios_restantes']<=0){return false;}return true;}/**
     * AJAX: Subir CAF
     */public static function ajax_upload_caf(){check_ajax_referer('akb_be_nonce','nonce');if(!current_user_can('manage_woocommerce')){wp_send_json_error(['message'=>__('Permisos insuficientes','akb-be')]);}if(empty($_FILES['caf_file'])){wp_send_json_error(['message'=>__('No se recibió ningún archivo','akb-be')]);}$file=$_FILES['caf_file'];$validation=AKB_BE_Helpers::validate_upload($file,['xml']);if(!$validation['success']){wp_send_json_error(['message'=>$validation['message']]);}$upload_dir=AKB_BE_Helpers::create_secure_upload_dir();$filename='caf-'.time().'.xml';$filepath=$upload_dir.$filename;if(!@move_uploaded_file($file['tmp_name'],$filepath)){wp_send_json_error(['message'=>__('No se pudo guardar el archivo','akb-be')]);}@chmod($filepath,0600);$result=self::process_caf($filepath);if(!$result['success']){@unlink($filepath);wp_send_json_error(['message'=>$result['message']]);}wp_send_json_success(['message'=>$result['message'],'data'=>$result['data']]);}}<?php /** * Cliente API para comunicación con SimpleAPI del SII */if(!defined("ABSPATH")){exit();}class AKB_BE_API{private static function get_base_url():string{return untrailingslashit(get_option("akb_be_base_url",""));}private static function get_api_key():string{return trim(get_option("akb_be_api_key",""));}private static function get_headers():array{return["Authorization"=>self::get_api_key()];}/** * Generar DTE */public static function generar_dte(array $input_data){AKB_BE_Logger::debug("API: generar_dte",["input"=>$input_data]);$cert_path=get_option("akb_be_cert_path","");$caf_path=get_option("akb_be_caf_path","");if(!file_exists($cert_path)){return new WP_Error("akb_cert_missing",__("Certificado no encontrado","akb-be"));}if(!file_exists($caf_path)){return new WP_Error("akb_caf_missing",__("CAF no encontrado","akb-be"));}$url=self::get_base_url()."/api/v1/dte/generar";$boundary=wp_generate_password(24,false,false);$parts=[["name"=>"input","contents"=>wp_json_encode($input_data,JSON_UNESCAPED_UNICODE),],["name"=>"files","filename"=>basename($cert_path),"filepath"=>$cert_path,],["name"=>"files2","filename"=>basename($caf_path),"filepath"=>$caf_path,],];return self::multipart_request($url,$parts,$boundary);}/** * Enviar sobre con DTE */public static function enviar_sobre(string $sobre_xml){AKB_BE_Logger::debug("API: enviar_sobre");$cert_path=get_option("akb_be_cert_path","");if(!file_exists($cert_path)){return new WP_Error("akb_cert_missing",__("Certificado no encontrado","akb-be"));}$url=self::get_base_url()."/api/v1/dte/enviar";$temp_sobre=AKB_BE_Helpers::create_temp_file($sobre_xml,"sobre_");$boundary=wp_generate_password(24,false,false);$parts=[["name"=>"files","filename"=>basename($cert_path),"filepath"=>$cert_path,],["name"=>"files2","filename"=>basename($temp_sobre),"filepath"=>$temp_sobre,],];$result=self::multipart_request($url,$parts,$boundary);AKB_BE_Helpers::delete_temp_file($temp_sobre);return $result;}/** * Enviar RCOF */public static function enviar_rcof(string $rcof_xml){AKB_BE_Logger::debug("API: enviar_rcof");$url=self::get_base_url()."/api/v1/boleta/rcof/enviar";$temp_rcof=AKB_BE_Helpers::create_temp_file($rcof_xml,"rcof_");$boundary=wp_generate_password(24,false,false);$parts=[["name"=>"files2","filename"=>basename($temp_rcof),"filepath"=>$temp_rcof,],];$result=self::multipart_request($url,$parts,$boundary);AKB_BE_Helpers::delete_temp_file($temp_rcof);return $result;}/** * Petición multipart */private static function multipart_request(string $url,array $parts,string $boundary){$payload="";foreach($parts as $part){$payload.="--{$boundary}\r\n";$payload.='Content-Disposition: form-data; name="'.$part["name"].'"';if(isset($part["filename"])){$payload.='; filename="'.$part["filename"].'"';}$payload.="\\r\\n";$payload.=isset($part["filename"])?"Content-Type: application/octet-stream\\r\\n":"Content-Type: text/plain\\r\\n";$payload.="\\r\\n";if(isset($part["filepath"])){$payload.=file_get_contents($part["filepath"]);}else{$payload.=$part["contents"];}$payload.="\\r\\n";}$payload.="--{$boundary}--\r\n";AKB_BE_Logger::debug("HTTP POST",["url"=>$url,"size"=>strlen($payload),]);$response=wp_remote_post($url,["headers"=>array_merge(self::get_headers(),["Content-Type"=>"multipart/form-data; boundary=".$boundary,]),"body"=>$payload,"timeout"=>60,]);if(is_wp_error($response)){AKB_BE_Logger::error("HTTP error",["error"=>$response->get_error_message(),]);return $response;}$status=wp_remote_retrieve_response_code($response);$body=wp_remote_retrieve_body($response);AKB_BE_Logger::debug("HTTP response",["status"=>$status,"length"=>strlen($body),]);if($status<200||$status>=300){return new WP_Error("akb_http_error",sprintf("HTTP %d: %s",$status,$body));}$data=json_decode($body,true);return is_array($data)?$data:["raw"=>$body];}}<?php /**
 * Gestión del área de administración y metabox
 */if(!defined('ABSPATH')){exit;}class AKB_BE_Admin{/**
     * Inicializar
     */public static function init(){add_action('add_meta_boxes',[__CLASS__,'add_metabox']);add_action('wp_ajax_akb_be_generar_boleta',[__CLASS__,'ajax_generar_boleta']);add_action('wp_ajax_akb_be_consultar_estado',[__CLASS__,'ajax_consultar_estado']);AKB_BE_Logger::debug('Admin inicializado');}/**
     * Agregar metabox a órdenes
     */public static function add_metabox(){$screen=wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()?wc_get_page_screen_id('shop-order'):'shop_order';add_meta_box('akb_be_metabox',__('Boleta Electrónica','akb-be'),[__CLASS__,'render_metabox'],$screen,'side','high');AKB_BE_Logger::debug('Metabox agregado');}/**
     * Renderizar metabox
     */public static function render_metabox($post_or_order){$order=$post_or_order instanceof WC_Order?$post_or_order:wc_get_order($post_or_order->ID);if(!$order){echo '<p>'.__('No se pudo cargar la orden','akb-be').'</p>';return;}$folio=$order->get_meta('_akb_be_folio',true);$track_id=$order->get_meta('_akb_be_track_id',true);$estado=$order->get_meta('_akb_be_estado',true);$fecha_envio=$order->get_meta('_akb_be_fecha_envio',true);$ambiente=get_option('akb_be_env','prod'); ?>
        <div class="akb-be-metabox">
            
            <p>
                <strong><?php _e('Ambiente:','akb-be'); ?></strong> 
                <span class="akb-be-env-badge <?php echo esc_attr($ambiente); ?>">
                    <?php echo esc_html(strtoupper($ambiente)); ?>
                </span>
            </p>
            
            <?php if($folio): ?>
                <p><strong><?php _e('Folio:','akb-be'); ?></strong> <?php echo esc_html($folio); ?></p>
                <p><strong><?php _e('Track ID:','akb-be'); ?></strong> <?php echo esc_html($track_id); ?></p>
                <p>
                    <strong><?php _e('Estado:','akb-be'); ?></strong> 
                    <span class="akb-be-estado-badge <?php echo esc_attr(strtolower($estado)); ?>">
                        <?php echo esc_html($estado?:'—'); ?>
                    </span>
                </p>
                <?php if($fecha_envio): ?>
                    <p><strong><?php _e('Enviada:','akb-be'); ?></strong> <?php echo esc_html($fecha_envio); ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p class="akb-be-info"><?php _e('Boleta no generada aún','akb-be'); ?></p>
            <?php endif; ?>
            
            <hr>
            
            <p>
                <label for="akb-be-caso-prueba"><?php _e('Caso de Prueba (opcional):','akb-be'); ?></label>
                <select id="akb-be-caso-prueba" class="widefat">
                    <option value=""><?php _e('(Sin Set)','akb-be'); ?></option>
                    <option value="CASO-1">CASO-1 - Boleta Afecta</option>
                    <option value="CASO-2">CASO-2 - Boleta Exenta</option>
                    <option value="CASO-3">CASO-3 - Boleta Mixta</option>
                    <option value="CASO-4">CASO-4 - Con Descuento</option>
                    <option value="CASO-5">CASO-5 - Unidad Especial</option>
                </select>
            </p>
            
            <p>
                <button type="button" class="button button-primary button-large widefat akb-be-btn-generar" 
                        data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                    <?php _e('Generar y Enviar Boleta','akb-be'); ?>
                </button>
            </p>
            
            <?php if($track_id): ?>
            <p>
                <button type="button" class="button button-secondary widefat akb-be-btn-consultar" 
                        data-order-id="<?php echo esc_attr($order->get_id()); ?>">
                    <?php _e('Actualizar Estado','akb-be'); ?>
                </button>
            </p>
            <?php endif; ?>
            
            <?php wp_nonce_field('akb_be_nonce','akb_be_nonce_field'); ?>
            
        </div>
        <?php }/**
     * AJAX: Generar boleta
     */public static function ajax_generar_boleta(){check_ajax_referer('akb_be_nonce','nonce');if(!current_user_can('manage_woocommerce')){wp_send_json_error(['message'=>__('Permisos insuficientes','akb-be')]);}$order_id=absint($_POST['order_id']??0);$caso_prueba=sanitize_text_field($_POST['caso_prueba']??'');AKB_BE_Logger::info("AJAX: Generando boleta",['order_id'=>$order_id,'caso'=>$caso_prueba]);$order=wc_get_order($order_id);if(!$order){wp_send_json_error(['message'=>__('Orden no encontrada','akb-be')]);}if(!$caso_prueba&&$order->get_meta('_akb_be_generada',true)==='yes'){wp_send_json_error(['message'=>__('Esta orden ya tiene boleta generada','akb-be')]);}$resultado=AKB_BE_Generator::generar_boleta_para_order($order,$caso_prueba?:null);if(is_wp_error($resultado)){AKB_BE_Logger::error("Error al generar boleta",['error'=>$resultado->get_error_message()],$order_id);wp_send_json_error(['message'=>$resultado->get_error_message()]);}AKB_BE_Logger::info("Boleta generada exitosamente via AJAX",$resultado,$order_id);wp_send_json_success($resultado);}/**
     * AJAX: Consultar estado (simplificado - sin llamada a API real por ahora)
     */public static function ajax_consultar_estado(){check_ajax_referer('akb_be_nonce','nonce');if(!current_user_can('manage_woocommerce')){wp_send_json_error(['message'=>__('Permisos insuficientes','akb-be')]);}$order_id=absint($_POST['order_id']??0);$order=wc_get_order($order_id);if(!$order){wp_send_json_error(['message'=>__('Orden no encontrada','akb-be')]);}$track_id=(int)$order->get_meta('_akb_be_track_id',true);if(!$track_id){wp_send_json_error(['message'=>__('Esta orden no tiene TrackID','akb-be')]);}$estado_actual=$order->get_meta('_akb_be_estado',true)?:'REC';AKB_BE_Logger::info("Consulta de estado via AJAX",['order_id'=>$order_id,'track_id'=>$track_id,'estado'=>$estado_actual]);wp_send_json_success(['estado'=>$estado_actual,'track_id'=>$track_id,'message'=>__('Estado actual: ','akb-be').$estado_actual]);}}<?php if(!defined('ABSPATH')){exit;}define('AKB_BE_VERSION','2.0.0');define('AKB_BE_PATH',plugin_dir_path(__FILE__));define('AKB_BE_URL',plugin_dir_url(__FILE__));define('AKB_BE_INC',AKB_BE_PATH.'includes/');define('AKB_BE_ASSETS',AKB_BE_URL.'assets/');add_action('init','akb_be_load_textdomain');function akb_be_load_textdomain(){load_plugin_textdomain('akb-be',false,dirname(plugin_basename(__FILE__)).'/languages');}function akb_be_check_woocommerce(){if(!class_exists('WooCommerce')){add_action('admin_notices','akb_be_wc_inactive_notice');return false;}return true;}function akb_be_wc_inactive_notice(){if(current_user_can('activate_plugins')){ ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('AKB Boleta Electrónica','akb-be'); ?>:</strong>
                <?php esc_html_e('Este plugin requiere WooCommerce activo para funcionar.','akb-be'); ?>
            </p>
        </div>
        <?php }}add_action('plugins_loaded','akb_be_init',11);function akb_be_init(){if(!akb_be_check_woocommerce()){return;}$required_files=['class-akb-be-logger.php','class-akb-be-helpers.php','class-akb-be-caf-manager.php','class-akb-be-folio-manager.php','class-akb-be-comuna-normalizer.php','class-akb-be-api.php','class-akb-be-generator.php','class-akb-be-rcof.php','class-akb-be-settings.php','class-akb-be-order-meta.php','class-akb-be-admin.php','class-akb-be-certificacion.php'];foreach($required_files as $file){$filepath=AKB_BE_INC.$file;if(!file_exists($filepath)){add_action('admin_notices',function()use($file){ ?>
                <div class="notice notice-error">
                    <p>
                        <strong>AKB Boleta Electrónica:</strong> 
                        Falta el archivo requerido: <code><?php echo esc_html($file); ?></code>
                    </p>
                </div>
                <?php });return;}require_once $filepath;}try{AKB_BE_Logger::init();AKB_BE_CAF_Manager::init();AKB_BE_Folio_Manager::init();AKB_BE_Settings::init();AKB_BE_Order_Meta::init();AKB_BE_Admin::init();AKB_BE_RCOF::init();AKB_BE_Certificacion::init();if(get_option('akb_be_debug',false)){error_log('[AKB BE] Plugin inicializado correctamente');}}catch(Exception $e){add_action('admin_notices',function()use($e){ ?>
            <div class="notice notice-error">
                <p>
                    <strong>AKB Boleta Electrónica:</strong> 
                    Error al inicializar: <?php echo esc_html($e->getMessage()); ?>
                </p>
            </div>
            <?php });error_log('[AKB BE] Error al inicializar: '.$e->getMessage());}}register_activation_hook(__FILE__,'akb_be_activate');function akb_be_activate(){if(!class_exists('WooCommerce')){deactivate_plugins(plugin_basename(__FILE__));wp_die(esc_html__('Este plugin requiere WooCommerce para funcionar.','akb-be'),esc_html__('Error de Activación','akb-be'),['back_link'=>true]);}global $wpdb;$table_name=$wpdb->prefix.'akb_be_logs';$charset_collate=$wpdb->get_charset_collate();$sql="CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        fecha_hora datetime NOT NULL,
        nivel varchar(20) NOT NULL,
        mensaje text NOT NULL,
        contexto longtext,
        order_id bigint(20),
        PRIMARY KEY (id),
        KEY fecha_hora (fecha_hora),
        KEY nivel (nivel),
        KEY order_id (order_id)
    ) $charset_collate;";require_once ABSPATH.'wp-admin/includes/upgrade.php';dbDelta($sql);add_option('akb_be_env','cert');add_option('akb_be_debug',true);add_option('akb_be_folio_actual',0);error_log('[AKB BE] Plugin activado correctamente');}