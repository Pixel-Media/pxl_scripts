<?php

if (!class_exists('pxl_auth')) {
/**
 * Nombre: pxl_auth
 * Función: Clase para registrar e inicar sesion de usuarios en el sistema
 */
	class pxl_auth{

		/**
		 * propiedades de inicializacion de la clase
		 * los valores provienen de las variables globales del sistema y se pueden sobreescribir
		 * @var array
		 */
		private $default_options = array(
			"mysql"=>"mysql",
			"table_users"=>"DB_USUARIOS",
			"table_users_nonce"=>"DB_USUARIOS_NONCE",
			"permite_recordar_pw"=>"permitir_recordar_pw",
			"permite_recordar_nombre_cookie" => "session_name_remember",
			"permite_recordar_cookie_domain"=> "session_cookie_dir",
			"permite_recordar_forzar_ssl"=> "forzar_ssl"

		);
		/**
		 * Define si permite FB login
		 * @var boolean
		 */
		public $fb_login = false;

		/**
		 * Los datos de la conexion con facebook
		 * @var array
		 */
		public $fb_datos = array('app_id'=>'', 'app_secret'=>'', 'api_version'=>'v13.0', 'url_sesion_ajax'=> '');

		/**
		 * Define si permit google login
		 * @var boolean
		 */
		public $google_login = false;

		/**
		 * tipos de login del usuario
		 * @var array
		 */
		private $login_type = array(
			"1", //"Registro web",
			"2", //"Facebook",
			"3", //"Google"
		);

		/**
		 * tipos de estados de actividad del usuario en el sistema
		 * @var array
		 */
		private $user_status = array(
			"1", //"activo",
			"2", //"inactivo",
			"3", //"por validar"
		);

		/**
		 * Parámetros para validación del contraseñas
		 * @var array
		 */
		public $password_validacion = array(
			'min_length' => 8,
			'con_numero'=>true,
			'con_mayuscula'=>true,
			'con_minuscula'=>true,
			'con_caracteres_especiales'=>true
		);

		/**
		 * caracteres validos de la contraseña
		 * que se genera automaticamente al registrarse
		 * @var string
		 */
		private $password_valid_chars = "abcdefghijkmnpqrstuvwxyz123456789";

		/**
		 * El largo de los passwords por defecto
		 * @var integer
		 */
		public $password_length = 8;

		/**
		 * algoritmo para generar la contraseña
		 * @var [type]
		 */
		private $password_algo = PASSWORD_BCRYPT;

		/**
		 * costo del algoritmo de encriptacion
		 * ver documentacion https://www.php.net/manual/es/password.constants.php
		 * otras fuentes https://stackoverflow.com/questions/38840672/what-is-the-cost-option-in-password-hash
		 * @var integer
		 */
		private $password_algo_cost = 10;

		/**
		 * tabla de usuarios permanentes
		 * @var [type]
		 */
		private $table_users;

		/**
		 * tabla de usuarios temporales antes de ser permanentes
		 * se usa justo despues del registro y se deja de usar cuando
		 * el usuario verifica su identidad en el sistema
		 * @var string
		 */
		private $table_users_nonce;

		/**
		 * tiempo (en strtotime) extra de expiracion del token de los usuarios nonce para el registro
		 * @var string
		 */
		private $nonce_token_expiration_registro = "12 hour";

		/**
		 * tiempo (en strtotime) extra de expiracion del token de los usuarios nonce para los cookies para recordar login
		 * @var string
		 */
		private $nonce_token_expiration_cookie = "1 month";

		/**
		 * Si el sistema permitirá recordar el usuario por cookie
		 * @var boolean
		 */
		public $permite_recordar_pw = true;

		/**
		 * El nombre del cookie para recordar el usuario
		 * @var string
		 */
		private $permite_recordar_nombre_cookie = 'rm';

		/**
		 * El dominio para crear las cookies de recordar
		 * @var string
		 */
		private $permite_recordar_cookie_domain = 'domain.com';

		/**
		 * Si forzamos el ssl en los cookies de recordar contraseña
		 * @var boolean
		 */
		private $permite_recordar_forzar_ssl = true;

		/**
		 * conexion mysql. se toma de la variable global del sistema
		 * se inicializa en el constructor
		 * @var object
		 */
		private $mysql;


		/**
		 * define los valores de las propiedades de inicializacion de la clase
		 * @param array $options valores de inicializacion
		 */
		public function __construct($options = array()){

			foreach($this->default_options as $nombre_var=>$default){

				global $$default;

				if(isset($options[$nombre_var])){
					$this->{$nombre_var} = $options[$nombre_var];
				}else{
					if(isset($$default)){
						$this->{$nombre_var} = $$default;
					}else{
						echo "No tiene valor para la variable ".$nombre_var;
					}
				}
			}
			$this->tablas_existen();
		}

		/**
		 * devuelve booleano si permite o no login social
		 * @return boolean
		 */
		public function login_social_permitido(){
			if($this->fb_login or $this->google_login){
				return true;
			}else{
				return false;
			}
		}
		/**
		 * HAce el login social , crea al usuario o lo busca dependiendo de cual red social
		 * @param  [type] $userProfile [description]
		 * @param  [type] $tipo_login  [description]
		 * @return [type]              [description]
		 */
		public function login_social($userProfile, $tipo_login){
			if($this->login_social_permitido()){
				if($tipo_login == 3 and !$this->google_login){
					return false;
				}
				if($tipo_login == 2 and !$this->fb_login){
					return false;
				}
				$q = "SELECT * FROM ".$this->table_users." WHERE id_externo = '".$userProfile->identifier."' AND tipo = ".$tipo_login;
				//echo $q;
				$res = $this->mysql->query($q);
				if($res and $res->num_rows > 0){
					$array = $res->fetch_assoc();
					$q = "UPDATE ".$this->table_users." SET nombre = '".addslashes($userProfile->firstName)."', apellido = '".addslashes($userProfile->lastName)."', email = '".addslashes($userProfile->email)."', delete_code = NULL, ts_delete = 0, status = 1 WHERE id = ".$array['id'];
					$this->mysql->query($q);
					return $this->iniciar_sesion($array['id']);
				}else{
					$q = "INSERT INTO ".$this->table_users." (id, nombre, apellido, fecha_nacimiento, pais, nickname, email, password, cambiar_password, tipo, ultimo_login, id_externo, verificado, status, ts_creacion, ts_modificacion) VALUES ('', '".addslashes($userProfile->firstName)."', '".addslashes($userProfile->lastName)."', NULL, NULL, '', '".addslashes($userProfile->email)."', '', 0, ".$tipo_login.", ".time().", '".$userProfile->identifier."', 1, 1, ".time().", ".time().")";
					$res = $this->mysql->query($q);
					if($res){
						$id = $this->mysql->insert_id;
						return $this->iniciar_sesion($id);
					}
				}
			}
		}
		/**
		 * Verifica si las tablas de usuario y nonce existen o las crean
		 * @return NULL
		 */
		private function tablas_existen(){
			$q = "CREATE TABLE IF NOT EXISTS ".$this->table_users." (
	  			id int(11) NOT NULL AUTO_INCREMENT,
	  			nombre varchar(250) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	  			email varchar(250) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	  			password varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	  			cambiar_password int(11) NOT NULL DEFAULT 1,
	  			tipo int(11) NOT NULL,
	  			ultimo_login bigint(20) DEFAULT NULL,
	  			id_externo varchar(100) DEFAULT NULL,
	  			delete_code varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
	  			verificado int(11) NOT NULL DEFAULT 0,
	  			status int(11) NOT NULL DEFAULT 3,
	  			ts_creacion bigint(20) NOT NULL,
	  			ts_modificacion bigint(20) NOT NULL,
	  			ts_delete bigint(20) NOT NULL,
	  			PRIMARY KEY (id),
	  			UNIQUE KEY email_tipo (email,tipo),
	  			UNIQUE KEY delete_code (delete_code),
	  			KEY ts_creacion (ts_creacion),
	  			KEY ts_modificacion (ts_modificacion)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
			$this->mysql->query($q);
			$q = "CREATE TABLE IF NOT EXISTS ".$this->table_users_nonce." (
	  			id int(11) NOT NULL AUTO_INCREMENT,
	  			id_user int(11) NOT NULL,
	  			token varchar(500) COLLATE utf8_unicode_ci NOT NULL,
	  			ip varchar(150) COLLATE utf8_unicode_ci NOT NULL,
	  			ts_vence bigint(20) NOT NULL,
	  			PRIMARY KEY (id),
	  			UNIQUE KEY token (token),
	  			KEY id_user (id_user),
	  			KEY ts_vence (ts_vence)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
			$this->mysql->query($q);

		}
		/**
		 * inicia sesión un usuario
		 * @param  array $params
		 * @return bool $params
		 */
		public function user_login($email, $password){
			$user_data = $this->user_data(strtolower($email), 'email', 1);//para el tipo de registro del web
			if(!isset($user_data['email']) or !isset($user_data['password'])){
				return false;
			}
			if(password_verify($password, $user_data["password"])){
				//echo "usuario logeado";
				return $this->iniciar_sesion($user_data['id']);
			}else{
				return false;
			}
		}
		/**
		 * Verifica el password con las opciones que tiene el objeto definidas
		 * @param  string $password el password a verificar
		 * @return mixed           devuelve true si es válido o el código de error si no lo es
		 */
		public function valid_password($password){
			$error = '';
		    if($this->password_validacion['con_caracteres_especiales'] and !preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)){
		        $error = 'sin_caracteres_especiales';
		    }
	        if ($this->password_validacion['con_minuscula'] and !preg_match('`[a-z]`', $password)){
	            $error = 'sin_minusculas';
	        }
	        if ($this->password_validacion['con_mayuscula'] and !preg_match('`[A-Z]`', $password)){
	            $error = 'sin_mayusculas';
	        }
	        if ($this->password_validacion['con_numero'] and !preg_match('`[0-9]`', $password)){
	            $error = 'sin_numeros';
	        }
	        if (strlen($password) < $this->password_validacion['min_length']){
	            $error = 'pocos_caracteres';
	        }
	        if($error == ''){
	        	return true;
	        }else{
	        	return $error;
	        }

		}
		/**
		 * Modifica el password de un usuario
		 * @param  int $id       el id del usuario
		 * @param  string $password el nuevo password
		 * @return boolean           true si se cambió y false si no lo hizo o no se puede
		 */
		public function update_password($id, $password){
			if($this->valid_password($password) === false){
				return false;
			}
			$q = "UPDATE ".$this->table_users." SET cambiar_password = 0, password = '".$this->hash_password($password)."', ts_modificacion = ".time()." WHERE id = ".$id;
			if($this->mysql->query($q)){
				$_SESSION['cambiar_password'] = false;
				return true;
			}else{
				return false;
			}
		}
		/**
		 * 	Inicia la sesion del usuario
		 * @param  int $id El id del usuario
		 * @return boolean	true si la pudo iniciar y false si no pudo
		 */
		private function iniciar_sesion($id){
			$datos = $this->user_data($id, 'id');
			if(isset($datos['status']) and $datos['status'] == 1){
				$_SESSION['userid'] = $id;
				$cambiar_password = false;
				if($datos['cambiar_password'] == 1){
					$cambiar_password = true;
				}
				$_SESSION['cambiar_password'] = $cambiar_password;
				$_SESSION['ultimo_login'] = $datos['ultimo_login'];
				$_SESSION['nombre'] = $datos['nombre'];
				$_SESSION['tipo_registro'] = $datos['tipo'];
				$this->mysql->query("UPDATE ".$this->table_users." SET ultimo_login = ".time()." WHERE id = ".$id);
				return true;
			}else{
				return false;
			}

		}
		/**
		 * destruye las variables de la sesion
		 * @return null
		*/
		public function logout(){
			unset($_SESSION['userid']);
			unset($_SESSION['last_login']);
			unset($_SESSION['cambiar_password']);
			if($this->permite_recordar_pw){
				setcookie($this->permite_recordar_nombre_cookie, '', strtotime('-'.$this->nonce_token_expiration_cookie), '/' , $this->permite_recordar_cookie_domain, $this->permite_recordar_forzar_ssl , true);
			}
		}
		/**
		 * Crea un token con tiempo de vigencia de registro
		 * @param  int $id El id de usuario
		 * @return string     El token creado
		 */
		public function create_token_registro($id){
			$token = $this->create_token($id, $this->nonce_token_expiration_registro);
			return $token;
		}
		/**
		 * Se registra un usuario
		 * @param  string $nombre El nombre del usuario
		 * @param  string $email  el Email del usuario (debe ser unico)
		 * @param  array  $params parametros adicionales para guardar en la tabla, el key es el nombre del campo en la bdd y el value es el valor
		 * @return mixed         Si lo inserta devuelve el id, de lo contrario devuelve false
		 */
		public function user_register($nombre, $email, $params = array()){

			$fields = '';
			$values = '';

			if (!empty($params)) {
				$fields = array();
				$values = array();

				foreach ($params as $key => $value) {
					array_push($fields,$key);
					array_push($values,"'".addslashes($value)."'");
				}

				$fields = ",".implode(",", $fields);
				$values = ",".implode(",", $values);
			}

			$q = "INSERT INTO ".$this->table_users;
			$q .= " (nombre, email, password, tipo, status, ts_creacion, ts_modificacion".$fields;
			$q .= ") VALUES ('".addslashes($nombre)."','".strtolower($email)."','".$this->hash_password($this->random_password(20))."','1','3', ".time().", ".time().$values.")";
			if ($this->mysql->query($q)) {
				return $this->mysql->insert_id;
			}else{
				return false;
			}
		}

		/**
		 * Verifica si el usuario está logueado
		 * @return boolean true si está logueado y false si no
		 */
		public function is_logged(){
			if(isset($_SESSION['userid'])){
				return true;
			}else{
				return false;
			}
		}
		/**
		 * valida la estructura de un email ej. usuario[at]dominio[.]extension
		 * @param  string $email 	El email a validar
		 * @return bool devuelve true si es email valido false si no lo es
		 */
		public function is_email_valid($email){
			if(filter_var($email, FILTER_VALIDATE_EMAIL) !== false){
				return true;
			}else{
				return false;
			}
		}

		/**
		 * Verifica si el email existe, en caso que se esté modificando se debe enviar el email con el id para comparar los demás registros
		 * @param  string $email El email a verificar
		 * @param  int $id El id del usuario actual
		 * @param  int $tipo_registr El id del tipo de registro
		 * @return boolean        true si existe, false si no existe
		 */
		public function email_exists($email, $id = 0, $tipo_registro = 1){
			$q = "SELECT email FROM ".$this->table_users." WHERE email = '".$email."' AND tipo = ".$tipo_registro;
			if($id != 0){
				$q .= ' AND id != '.$id;
			}

			$res = $this->mysql->query($q);

			if(mysqli_num_rows($res) > 0){
				return true;
			}else{
				return false;
			}
		}


		/**
		 * Verifica si el usuario existe
		 * @param  string $campo_key Es el campo se que va a buscar en la base de datos
		 * @param  string $campo Se utiliza para poder buscar tanto por email como por id
		 * @param  int $tipo_registr El id del tipo de registro
		 * @return boolean           true si existe, false si no
		 */
		public function user_exists($campo_key, $campo = 'email', $tipo_registro = 1){
			$q = "SELECT * FROM ".$this->table_users." WHERE ".$campo." = '".$campo_key."' AND tipo = ".$tipo_registro;

			$res = $this->mysql->query($q);

			if(mysqli_num_rows($res) > 0){
				return true;
			}else{
				return false;
			}
		}

		/**
		 * Devuelve todos los datos del usuario
		 * @param  string $campo_key Es el campo se que va a buscar en la base de datos
		 * @param  string $campo Se utiliza para poder buscar tanto por email como por id
		 * @param  int $tipo_registro Busca al usuario entre los tipos de registro especificados
		 * @return mixed        los datos del usuario en un array, o false si no se encontró
		 */
		public function user_data($campo_key, $campo = 'email', $tipo_registro = 1){
			$q = "SELECT * FROM ".$this->table_users." WHERE ".$campo." = '".$campo_key."'";
			if($campo != 'id'){
				$q .= ' AND tipo = '.$tipo_registro;
			}

			$res = $this->mysql->query($q);

			if($res->num_rows > 0){
				return $res->fetch_assoc();
			}else{
				return false;
			}
		}

		/**
		 * Crea un password random
		 * @param  integer $length el largo del password
		 * @return string          el password creado
		 */
		private function random_password($length = 0){
			$i = 1;
			$chars = $this->password_valid_chars;
			$password = "";

			if ($length == 0) {
				$length = $this->password_length;
			}

			while ($i <= $length) {
				$password .= $chars[mt_rand(0,strlen($chars)-1)];
				$i++;
			}

			return $password;
		}

		/**
		 * Crea un hash de un password
		 * @param  string $password contraseña a hashear
		 * @return string devuelte una contraseña hasheada
		 */
		public function hash_password($password){
			$options = array(
				"cost" => $this->password_algo_cost
			);

			return password_hash($password, $this->password_algo, $options);
		}

		/**
		 * Crea un token enviandole el id de usuario y el tiempo de expiracion del mismo
		 * @param  int $id_user    Id de usuario
		 * @param  string $expiration el tiempo de expiracion en notacion strtotime de PHP
		 * @return string             el token completo
		 */
		private function create_token($id_user,$expiration=""){
			$this->flush_nonce_users();

			$ip = $_SERVER['REMOTE_ADDR'];
			$q = "INSERT INTO ".$this->table_users_nonce." (id_user, token, ip, ts_vence) VALUES ('".$id_user."','[[token]]','".$ip."','".strtotime("+ ".$expiration)."')";
			$token = hash ('sha256', openssl_random_pseudo_bytes (25));
			while(!$this->mysql->query(str_replace('[[token]]', $token, $q))){
				$token = hash ('sha256', openssl_random_pseudo_bytes (25));
			}
			return $token;
		}

		/**
		 * Crea el cookie
		 * @param  int $id_user El id del usuario
		 * @return boolean          cierto si crea el cookie y falso si no
		 */
		public function crear_cookie_remember($id_user){
		    $hash = $this->create_token($id_user, $this->nonce_token_expiration_cookie);
		    if(setcookie($this->permite_recordar_nombre_cookie, $hash, strtotime('+'.$this->nonce_token_expiration_cookie), '/' , $this->permite_recordar_cookie_domain, $this->permite_recordar_forzar_ssl , true)){
		    	return true;
		    }else{
		    	return false;
		    }
		}
		/**
		 * Verifica un cookie y crea una sesion del usuario, tambien vuelve a crear una nueva cookie si todo va bien.
		 * @return boolean true si se verificó y creó la nueva y false de lo contrario
		 */
		public function verificar_cookie_remember(){
			if(isset($_COOKIE[$this->permite_recordar_nombre_cookie]) and $_COOKIE[$this->permite_recordar_nombre_cookie] != ''){
				$id_user = $this->verificar_token($_COOKIE[$this->permite_recordar_nombre_cookie]);
				if($id_user === false){
					return false;
				}
				if($this->crear_cookie_remember($id_user)){
					if(!$this->is_logged()){
						$this->iniciar_sesion($id_user);
					}
					return true;
				}else{
					return false;
				}
			}else{
				return false;
			}
		}

		/**
		 * Verifica si un token está activo y es válido
		 * @param  string $token   El token a comprobar
		 * @param  int $id_user El id del usuario a comprobar
		 * @return boolean          true si el token es válido y false si no lo es
		 */
		public function verificar_token($token, $id_user = 0){
			$this->flush_nonce_users();
			$q = "SELECT * FROM ".$this->table_users_nonce." WHERE token = '".$token."' ";
			if($id_user != 0){
				$q .= " AND id_user = ".$id_user." ";
			}
			$q .= " AND ts_vence >= ".time();
			$res = $this->mysql->query($q);
			if($res and $res->num_rows > 0){
				//$this->borrar_token($token);//probaremos sin borrar el token.. igual se vence en 12 horas
				if($id_user ==  0){
					$array = $res->fetch_assoc();
					return $array['id_user'];
				}else{
					return true;
				}
			}else{
				return false;
			}
		}

		/**
		 * Borra un token específico, que ya fue utilizado
		 * @param  string $token el token a borrar
		 * @return boolean        true si ejecutó y borró bien y false de lo contrario
		 */
		private function borrar_token($token){
			return $this->mysql->query("DELETE FROM ".$this->table_users_nonce." WHERE token = '".$token."'");
		}

		/**
		 * 	Verifica una cuenta a través de un token, cambia el status y el campo de verificado
		 * @param  string $token   El token
		 * @param  int $id_user El id del usuario
		 * @return boolean          true si verifica y false si no lo hace
		 */
		public function verificar_cuenta($token, $id_user){
			$datos_user = $this->user_data($id_user, 'id');
			if($datos_user['status'] == 2){//si el usuario está inactivo a propósito
				return false;
			}
			if($this->verificar_token($token, $id_user)){
				if($this->mysql->query("UPDATE ".$this->table_users." SET verificado = 1, status = 1, cambiar_password = 1 WHERE id = ".$id_user)){
					return $this->iniciar_sesion($id_user);
				}else{
					return false;
				}
			}
		}

		/**
		 * Borra los nonces de los usuarios que ya estén vencidos
		 * @return boolean si se ejecutó o no
		 */
		private function flush_nonce_users(){
			$q = "DELETE FROM ".$this->table_users_nonce." WHERE ts_vence < ".time();
			return($this->mysql->query($q));
		}

		/**
		 * retorna el id de sesion del usuario
		 * @return string variable de sesion
		 */
		public function user_id(){
			if(!isset($_SESSION['userid'])){
				return false;
			}
			return $_SESSION['userid'];
		}
		/**
		 * Devuelve el script de fb
		 * @return [type] [description]
		 */
		public function fb_script(){
			$html = "
			window.fbAsyncInit = function() {
		    FB.init({
		      appId      : '".$this->fb_datos['app_id']."',
		      cookie     : true,
		      xfbml      : true,
		      version    : '".$this->fb_datos['api_version']."'
		    });

		    FB.AppEvents.logPageView();
		    fb_checkLoginState();

		  };

		  (function(d, s, id){
		     var js, fjs = d.getElementsByTagName(s)[0];
		     if (d.getElementById(id)) { return;}
		     js = d.createElement(s); js.id = id;
		     js.src = 'https://connect.facebook.net/en_US/sdk.js';
		     fjs.parentNode.insertBefore(js, fjs);
		   }(document, 'script', 'facebook-jssdk'));
		  function fb_checkLoginState(){
		  	var respuesta;
		  	FB.getLoginStatus(function(response) {
		  		if(response.status == 'connected'){
		  			FB.api('/me?fields=id,email,first_name,last_name', function(response2) {
		  				$.ajax({
		  					type: 'POST',
		  					url: '".$this->fb_datos['url_sesion_ajax']."',
		  					data: { var1: response2.id, var2: response2.email, var3: response2.first_name, var4: response2.last_name, var5:response.authResponse.signedRequest},
		  					dataType: 'json'
		  				}).done(function ( data ) {
		  					if(data.ok){";
		  					if(!$this->is_logged()){
		  						$html .= "window.location.reload();";
		  					}
		  					$html .= "}
						});
				    });
		  		}
		  		respuesta = response;
			});
		  	return respuesta;
		  }";
	  		return $html;
		}

		public function get_tabla_users(){
			return $this->table_users;
		}
		/**
		 * Devuelve el script de js para loguearse a fb
		 * @var string
		 */
		public function fb_script_login($jquery_selector = '#fb_login'){
			$html = "function ini_fb_login(){
				$('".$jquery_selector."').on('click', function(e){
					e.preventDefault();
					var status = fb_checkLoginState();
					if(status.status == 'connected'){
						window.location.reload();
					}else{
						FB.login(function(response){
							fb_checkLoginState();
						}, {scope: 'email'});
					}
				});
			}";
			return $html;
		}
		/**
		 * Script de JS para hacer logout por FB
		 * @param  string $jquery_selector [description]
		 * @return [type]                  [description]
		 */
		public function fb_script_logout($jquery_selector = '.logout_link'){
			$html = "function ini_fb_logout(){
				$('".$jquery_selector."').on('click', function(e){
					e.preventDefault();
					fb_logout($(this).attr('href'));
					})
				}
				function fb_logout(dir){
					var status = fb_checkLoginState();
					if(status.status == 'connected'){
						FB.logout(function(response) {
					});/**/
					}
					window.location.href = dir;
				}";
			return $html;
		}
		public function delete_code_confirmation($code){
			$q = "SELECT * FROM ".$this->table_users." WHERE delete_code = '".$code."'";
			$res = $this->mysql->query($q);
			$error = '';
			$ts_removido = '';
			if($res and $res->num_rows > 0){
				$array = $res->fetch_assoc();
				$ts_removido = $array['ts_delete'];
			}else{
				$error = "We don't have this record";
			}
			return array('ts_removido'=>$ts_removido, 'error' =>$error);
		}
		/**
		 * parsea un request firmado de FB y devuelve el id
		 * @param  [type] $signed_request [description]
		 * @return [type]                 [description]
		 */
		public function fb_parse_signed_request($signed_request) {
			list($encoded_sig, $payload) = explode('.', $signed_request, 2);

			$sig = $this->base64_url_decode($encoded_sig);
			$data = json_decode($this->base64_url_decode($payload), true);
			$secret = $this->fb_datos['app_secret'];
			$expected_sig = hash_hmac('sha256', $payload, $secret, $raw = true);
			if ($sig !== $expected_sig) {
				error_log('Bad Signed JSON signature!');
				return null;
			}

			return $data;
		}
		/**
		 * bas64 decode para urls
		 * @param  [type] $input [description]
		 * @return [type]        [description]
		 */
		public function base64_url_decode($input) {
			return base64_decode(strtr($input, '-_', '+/'));
		}
		/**
		 * Verifica si el codigo de borrado de FB existe en la tabla de usuarios (es unico)
		 * @param  [type] $codigo [description]
		 * @return [type]         [description]
		 */
		public function codigo_delete_existe($codigo){
			$q = "SELECT * FROM ".$this->table_users." WHERE delete_code = '".$codigo."'";
			$res = $this->mysql->query($q);
			if($res and $res->num_rows > 0){
				return true;
			}else{
				return false;
			}
		}
		/**
		 * metodo para borra el usuario de facebook
		 * @param  [type] $signed_request [description]
		 * @return [type]                 [description]
		 */
		function fb_delete($signed_request){
			$error = '';
			$code = '';
			$data = $this->fb_parse_signed_request($signed_request);
			$id_externo = $data['user_id'];
			$user_data = $this->user_data($id_externo, $campo = 'id_externo', $tipo_registro = 2);
			if($user_data === false){
				$error = "User doesn't Exists";
			}else{
				$code = $this->random_password(10);
				while($this->codigo_delete_existe($code)){
					$code = $this->random_password(10);
				}
				$q = "UPDATE ".$this->table_users." SET status = 2, delete_code = '".$code."', ts_delete = ".time().", nombre = 'Removido por Facebook' WHERE id = ".$user_data['id'];
				$res = $this->mysql->query($q);
				if(!$res){
					$error = 'Process Failed';
					$code = '';
				}

			}
			$response = array('code'=>$code, 'error'=>$error);
			return $response;
		}
	}
}
?>