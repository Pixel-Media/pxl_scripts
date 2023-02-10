<?php
if(!function_exists('limpiar_tel')){
    /**
     * [para limpiar números de teléfono]
     * @param  string $telefono el número de teléfono completo sin limpiar
     * @return string           el número limpio
     */
    function limpiar_tel($telefono){
        $telefono=str_replace("-", "",$telefono);
        $telefono=str_replace("(", "",$telefono);
        $telefono=str_replace(")", "",$telefono);
        $telefono=str_replace(".", "",$telefono);
        $telefono=str_replace(" ", "",$telefono);
        return $telefono;
    }
}
if(!function_exists('format_fecha')){
/**
 * Devuelve la fecha formateada d/m/Y
 * @param  int $fecha La fecha en timestamp
 * @return string        Devuelve  string vacio si no se entrega fecha o  la fecha formateada
 */
 function format_fecha($fecha){
        if($fecha != ''){
            return date("d/m/Y", $fecha);
        }else{
            return '';
        }
    }
}
if(!function_exists('limpiar_input')){
    /**
 * Sanitiza un input de usuario
 * @param  'string'  $string_name La variable a limpiar
 * @param  string  $metodo      post o get
 * @param  string  $tipo        int, string, text, html, float
 * @param  boolean $strict      devuelve la variable con solo numeros o letras
 * @return mixed               La variable limpia
 */
function limpiar_input($string_name, $metodo = 'post',  $tipo = 'string', $strict = false){
        global $site_encoding, $html_purifier_config_extra, $htmlpurifier_extra_defs;
        if($tipo == 'string'){
            if($metodo == 'post'){
                if(isset($_POST[$string_name])){
                    $var = trim(filter_var($_POST[$string_name], FILTER_SANITIZE_STRING));
                }else{
                    return false;
                }
            }elseif($metodo == 'get'){
                if(isset($_GET[$string_name])){
                    $var = trim(filter_var($_GET[$string_name], FILTER_SANITIZE_STRING));
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }elseif($tipo == 'int'){
            if($metodo == 'post'){
                if(isset($_POST[$string_name])){
                    $var = intval(filter_var($_POST[$string_name], FILTER_SANITIZE_STRING));
                }else{
                    return false;
                }
            }elseif($metodo == 'get'){
                if(isset($_GET[$string_name])){
                    $var = intval(filter_var($_GET[$string_name], FILTER_SANITIZE_STRING));
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }elseif($tipo == 'text'){
            if($metodo == 'post'){
                if(isset($_POST[$string_name])){
                    $var = trim($_POST[$string_name]);
                }else{
                    return false;
                }
            }elseif($metodo == 'get'){
                if(isset($_GET[$string_name])){
                    $var = trim($_GET[$string_name]);
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }elseif($tipo == 'html'){
            require_once 'scripts/html_purifier/htmlpurifier_html5.php';
            $html_purifier_config = HTMLPurifier_Config::createDefault();
            $html_purifier_config->set('HTML.Doctype', 'HTML 4.01 Transitional');
            $html_purifier_config->set('CSS.AllowTricky', true);
            //$html_purifier_config->set('Cache.SerializerPath', '/tmp');
            if(isset($site_encoding) and $site_encoding != ''){
                $html_purifier_config->set('Core.Encoding', $site_encoding);
            }else{
                $html_purifier_config->set('Core.Encoding', 'ISO-8859-1');
            }

            $html_purifier_config->set('AutoFormat.AutoParagraph', TRUE);
            $html_purifier_config->set('Core.AggressivelyFixLt', TRUE);
            if(isset($html_purifier_config_extra) and count($html_purifier_config_extra) > 0){
                foreach($html_purifier_config_extra as $extra_config){
                    $html_purifier_config->set($extra_config[0], $extra_config[1]);
                }
            }

            $allowed = array(
              'img[src|alt|title|class|data-mce-src|data-mce-json|data-yt-video]',
              'figure', 'figcaption',
              'video[src|type|width|height|poster|preload|controls]', 'source[src|type]',
              'a[href|target|class]',
              'iframe[width|height|src|frameborder|allowfullscreen]',
              'strong', 'b', 'i', 'u', 'em', 'br', 'font',
              'h1[class]', 'h2[class]', 'h3[class]', 'h4[class]', 'h5[class]', 'h6[class]',
              'p[style|class]', 'div[style]', 'center', 'address[style]',
              'span[style|class]', 'pre[style]',
              'ul', 'ol', 'li',
              'table[class]', 'th[width|height|border|style|class|colspan]',
              'tr[width|height|border|style|class]', 'td[width|height|border|style|class|colspan]',
              'hr[class]',
              'section[class]',
              'div[class|id|data-tipo|data-gal]'
            );
            $html_purifier_config->set('Attr.AllowedFrameTargets', array('_blank'));
            $html_purifier_config = load_htmlpurifier($allowed, $html_purifier_config);

            /*$html_purifier_config = HTMLPurifier_Config::createDefault();*/


            $html_purifier = new HTMLPurifier($html_purifier_config);

            if($metodo == 'post'){
                if(isset($_POST[$string_name])){
                    $var = $html_purifier->purify(trim($_POST[$string_name]));
                }else{
                    return false;
                }
            }elseif($metodo == 'get'){
                if(isset($_GET[$string_name])){
                    $var = $html_purifier->purify(trim($_GET[$string_name]));
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }elseif($tipo == 'float'){
            if($metodo == 'post'){
                if(isset($_POST[$string_name])){
                    $var = (float)(filter_var($_POST[$string_name], FILTER_SANITIZE_STRING));
                }else{
                    return false;
                }
            }elseif($metodo == 'get'){
                if(isset($_GET[$string_name])){
                    $var = (float)(filter_var($_GET[$string_name], FILTER_SANITIZE_STRING));
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }else{
            return false;
        }
        if($strict){
            $var = preg_replace("/[^A-Za-z0-9_.]/", "", $var);
        }
        return $var;
    }
}
if(!function_exists('nombre_tabla')){
/**
 * Devuelve un valor de un item para una tabla
 * @param  int  $id           El id buscado
 * @param  string  $tabla        la tabla
 * @param  string  $campo        el campo solicitado
 * @param  string  $nombre_id    el campo del id de la tabla
 * @return [type]                [description]
 */
function nombre_tabla($id, $tabla, $campo = 'nombre', $nombre_id = 'id'){
    global  $mysql;
    if($id != '' and $id !== NULL and $id !== 0){
        $q = "SELECT * FROM ".$tabla." WHERE ".$nombre_id." = '".$id."'";
        //echo $q;
        $res = $mysql->query($q);
        if(!$res){
            var_dump($res);
            echo $q;
            die();
        }
        if(mysqli_num_rows($res) > 0){
            $array = mysqli_fetch_array($res);
            return $array[$campo];
        }else{
            return '';
        }
    }else{
        return '';
    }
}
}
if(!function_exists('valid_mail')){

/**
 * Verifica si el correo es válido
 * @param  string $mail el correo
 * @return boolean       devuelve cierto si es válido y falso si no lo es
 */
function valid_mail($mail){
    return filter_var($mail, FILTER_VALIDATE_EMAIL) && preg_match('/@.+\./', $mail);
}
}
if(!function_exists('createselectitems')){

// Crea las opciones de un campo select, las variables que se dan son el query ya hecho, el objeto seleccionado, el campo del valor ( o sea en la tabla como se llama el campo del valor en el select) y el campo del nombre (en la tabla) si no se tiene nada seleccionado apararece un texto, seleccione uno.
//la variable limpiear, la quinta, es booleana, true, si desea poner una opcion, que se pueda limpiar la seleccion (opcion todos).
function createselectitems($query, $selected, $campovalor, $camponombre, $limpiar = false, $idioma = "es"){
    global $seleccione_txt, $limpiar_txt;
    $output = "";
    if($selected == '' or $selected == 'noopcion' or ($selected == 0 and is_numeric($selected))){
        $output .= $output."<option value='noopcion' selected='selected'>".$seleccione_txt[$idioma]."</option>";
    }elseif($limpiar){
        $output .= "<option value='noopcion'>".$limpiar_txt[$idioma]."</option>";
    }
    while ($array = mysqli_fetch_array($query)){
        $output .= "<option value='".$array[$campovalor]."'";
        if ($selected == $array[$campovalor]){
            $output .= " selected='selected' >";
        }else {
            $output .= " >";
        }
        $output .= "".$array[$camponombre]."</option>";
    }
    return $output;
}
}
if(!function_exists('createselectitems_array')){
// Crea las opciones de un campo select, las variables que se dan son el array ya hecho, el objeto seleccionado, EL ARRAY DEBE VENIR EN EL FORMATO (la llave sera el valor del select y el valor en el array será el display en el select). Si no se tiene nada seleccionado apararece un texto, seleccione uno.
function createselectitems_array($array, $selected, $limpiar = false, $idioma = 'es'){
    global $seleccione_txt, $limpiar_txt;
    $output = "";
    if($selected == '' or $selected == 'noopcion' or $selected == 'NULL' or $selected == 0){
        //$output .= "<option value='noopcion' selected='selected'>".$seleccione_txt[$idioma]."</option>";
        $output .= "<option value='noopcion' selected='selected'> </option>";
    }elseif($limpiar){
        $output .= "<option value='noopcion'>".$limpiar_txt[$idioma]."</option>";
    }
    foreach($array as $key => $value) {
        $output = $output."<option value='".$key."'";
        if ($selected == $key){
            $output = $output." selected='selected'>";
        }else {
            $output = $output.">";
        }
        $output = $output.$value."</option>";
    }
    return $output;
}
}
if(!function_exists('id_slug')){
/**
 * le etregas un slug y retorna un id
 * @param  [type] $slug        [description]
 * @param  [type] $tabla       [description]
 * @param  string $nombre_slug [description]
 * @return [type]              [description]
 */
function id_slug($slug, $tabla, $nombre_slug="slug"){
    global $mysql, $id_empresa;
    $q = "SELECT * FROM ".$tabla." WHERE ".$nombre_slug." = '".$slug."'";
    $res = $mysql->query($q);
    //var_dump($result);
    if($res and $res->num_rows > 0){
        $array = $res->fetch_array();
        return $array['id'];
    }else{
        //echo "HOLAM";
        return false;
    }
}
}
if(!function_exists('redirect') and !strstr($_SERVER['PHP_SELF'], 'adminer.php')){
/**
 * Redirige automaticamente a la pagina que se envia, es null se regresa a index.php
 * @param  [type] $dir [description]
 * @return [type]      [description]
 */
function redirect($dir = NULL){
    global $main_url;
    if($dir == NULL){
        $dir = $main_url;
    }
    header('Location: '.$dir);
    die();
}
}
?>