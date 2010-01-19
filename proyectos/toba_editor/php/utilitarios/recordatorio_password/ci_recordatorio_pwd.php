<?php
class ci_recordatorio_pwd extends toba_ci
{
	protected $usuario;
	protected $randr;
	
	function ini()
	{
		//Preguntar en toba::memoria si vienen los parametros
		$this->usuario = toba::memoria()->get_parametro('usuario');
		$this->randr = toba::memoria()->get_parametro('randr');		//Esto hara las veces de unique para la renovacion

		//Esto es por si el chango trata de entrar al item directamente
		$item = toba::memoria()->get_item_solicitado();
		$tms = toba_manejador_sesiones::instancia();
		if ($item[0] == 'toba_editor' && !$tms->existe_usuario_activo()) {
			throw new toba_error_ini_sesion('No se puede correr este item fuera del editor');
		}
	}

	//-----------------------------------------------------------------------------------
	//---- formulario -------------------------------------------------------------------
	//-----------------------------------------------------------------------------------

	function conf__formulario(toba_ei_formulario $form)
	{
		//Probablemente esto vaya vacio a excepcion del usuario si es que se pasa
		if (isset($this->usuario) && (!is_null($this->usuario))) {
			$form->set_datos_defecto(array('usuario' => $this->usuario));
			$form->set_solo_lectura(array('usuario'));
		}
	}

	function evt__formulario__enviar($datos)
	{
		//Miro que vengan los datos que necesito
		if (! isset($datos['usuario'])) {
			throw new toba_error_autenticacion('No se suministro un usuario v�lido');
		}

		if (! isset($datos['email'])) {
			throw new toba_error_autenticacion('Donde debo mandar la nueva contrase�a?');
		}

		//Si el usuario existe, entonces disparo el envio de mail 
		if (! $this->verificar_usuario_activo($datos['usuario'])) {
			throw new toba_error_autenticacion('No se suministro un usuario v�lido');
		} else {
			$this->usuario = $datos['usuario'];
			$this->enviar_mail_aviso_cambio($datos);
			toba::notificacion()->agregar('Se ha enviado un mail a la cuenta especificada, por favor verifiquela' , 'info');
		}
	}

	//-----------------------------------------------------------------------------------
	//---- Configuraciones --------------------------------------------------------------
	//-----------------------------------------------------------------------------------

	function conf__pant_inicial(toba_ei_pantalla $pantalla)
	{
		//Si viene con el random seteado es que esta confirmando el cambio de contrase�a
		if (isset($this->randr)) {
			$pantalla->eliminar_dep('formulario');
			$this->disparar_confirmacion_cambio();
			toba::notificacion()->agregar('La nueva contrase�a fue enviada a su cuenta de mail.', 'info');
		}
	}

	//----------------------------------------------------------------------------------------
	//-------- Procesamiento del pedido ------------------------------------------
	//----------------------------------------------------------------------------------------
	/*
	 * Verifico que el usuario existe a traves de la API de toba_usuario
	 */
	function verificar_usuario_activo($usuario)
	{
		try {
		    toba_usuario::es_usuario_bloqueado($usuario);		//Tengo que verificar que el negro existe
		} catch (toba_error_db $e) {												  //Ni true ni false... revienta... el mono no existe
		    toba::logger()->error('Se intento modificar la clave del usuario:' . $usuario);
		    return false;
		}
		return true;
	}

	/*
	 * Aca envio un primer mail con un link para confirmar el cambio, si no lo usa... fue
	 */
	function enviar_mail_aviso_cambio($datos)
	{
		//Genero un pseudorandom unico... 
		$tmp_rand = $this->get_random_temporal();
		$link = $this->generar_link_confirmacion($this->usuario, $tmp_rand);	//Genero el link para el mail
		
		//Se envia el mail a la direccion especificada por el usuario.
	    $asunto = 'Solicitud de cambio de contrase�a';
	    $cuerpo_mail = '<p>Este mail fue enviado a esta cuenta porque se <strong>solicito un cambio de contrase�a</strong>.'
	    . 'Si usted solicito dicho cambio haga click en el siguiente link: </br></br>'
	    . $link. "</br> El mismo ser� v�lido unicamente por 24hs.</p>";

		//Guardo el random asociado al usuario y envio el mail
		toba::instancia()->get_db()->abrir_transaccion();
	    try {
			$this->guardar_datos_solicitud_cambio($tmp_rand, $datos['email']);
			$mail = new toba_mail($datos['email'], $asunto, $cuerpo_mail);
			$mail->set_html(true);
			$mail->enviar();
			toba::instancia()->get_db()->cerrar_transaccion();
	    } catch (toba_error $e) {
			toba::instancia()->get_db()>abortar_transaccion();
			toba::logger()->debug('Proceso de envio de random a cuenta: '. $e->getMessage());
	    	throw new toba_error('Se produjo en el proceso de cambio, contactese con un administrador del sistema.');
	    }
	}

	/*
	 * Deberia generar un random.. quien sabe que tan bueno o malo sea
	 */
	function get_random_temporal()
	{
		$uuid = uniqid(rand(), true);
		$rnd = sha1( microtime() . $uuid . rand());
		return $rnd;
	}

	/*
	 * Obtiene una url con los parametros necesarios para que se haga la confirmacion
	 */
	function generar_link_confirmacion($usuario, $random)
	{
		$path = toba::proyecto()->get_www();
	    $opciones = array('param_html' => array('tipo' => 'normal' , 'texto' => 'Click Aqui'), 'prefijo' => $_SERVER['SERVER_NAME']. $path['url']);
	    $parametros = array('usuario' => $usuario, 'randr' => $random);
	    return toba::vinculador()->get_url (null, null , $parametros, $opciones);
	}

	/*
	 * Impacta en la base para cambiar la contrase�a del usuario
	 */
	function disparar_confirmacion_cambio()
	{
	    //Aca tengo que generar una clave temporal y enviarsela para que confirme el cambio e ingrese con ella.
	    $clave_tmp = toba_usuario::generar_clave_aleatoria('10');

	    //Recupero mail del usuario junto con el hash de confirmacion
	    $datos_orig = $this->recuperar_datos_solicitud_cambio($this->usuario, $this->randr);

		//Armo el mail nuevo
	    $asunto = 'Nueva contrase�a';
	    $cuerpo_mail = '<p>Se ha recibido su confirmaci�n exitosamente, su contrase�a fue cambiada a: </br>' .
	    $clave_tmp . "</br> Por favor en cuanto pueda cambiela a una contrase�a m�s segura. </br> Gracias. </p> ";

		//Cambio la clave del flaco, envio el nuevo mail y bloqueo el random
		toba::instancia()->get_db()->abrir_transaccion();
	    try {
			//Seteo la clave para el usuario
			toba::usuario($datos_orig[0]['id_usuario'])->set_clave($clave_tmp);
			//Enviar nuevo mail con la clave temporaria
			$mail = new toba_mail($datos_orig[0]['email'], $asunto, $cuerpo_mail);
			$mail->set_html(true);
			$mail->enviar();

			//Bloqueo el pedido para que no pueda ser reutilizado
			$this->bloquear_random_utilizado($this->usuario, $this->randr);
			toba::instancia()->get_db()->cerrar_transaccion();
	    } catch (toba_error $e) {
			toba::instancia()->get_db()->abortar_transaccion();
			toba::logger()->debug('Proceso de cambio de contrase�a en base: ' . $e->getMessage());
			throw new toba_error('Se produjo en el proceso de cambio, contactese con un administrador del sistema.');
	    }
	}

	//-----------------------------------------------------------------------------------------------------------------------------------------------------------------
	//																				METODOS PARA SQLs
	//-----------------------------------------------------------------------------------------------------------------------------------------------------------------
	function guardar_datos_solicitud_cambio($random, $mail)
	{
		$sql = 'UPDATE apex_usuario_pwd_reset SET bloqueado = 1 WHERE usuario = :usuario;';
		//toba::instancia()->get_db()->set_modo_debug(true, true);
		$up_sql = toba::instancia()->get_db()->sentencia_preparar($sql);
		$rs = toba::instancia()->get_db()->sentencia_ejecutar($up_sql, array('usuario'=>$this->usuario));

		$sql = "INSERT INTO apex_usuario_pwd_reset (usuario, random, email) VALUES (:usuario, :random, :mail);";
		//toba::logger()->debug(array('usuario'=>$this->usuario, 'random' => $random, 'mail' => $mail));
		$in_sql = toba::instancia()->get_db()->sentencia_preparar($sql);
		$rs = toba::instancia()->get_db()->sentencia_ejecutar($in_sql, array('usuario'=>$this->usuario, 'random' => $random, 'mail' => $mail));
	}
	
	function recuperar_datos_solicitud_cambio($usuario, $random)
	{
		$sql = "SELECT  usuario as id_usuario,
									   email
					 FROM apex_usuario_pwd_reset
					 WHERE	usuario = :usuario
					 AND random = :random
					 AND age(now() , validez)  < interval '1 day'
					 AND bloqueado = 0;";

		//toba::instancia()->get_db()->set_modo_debug(true, true);
		$id = toba::instancia()->get_db()->sentencia_preparar($sql);
		$rs = toba::instancia()->get_db()->sentencia_consultar($id, array('usuario'=>$usuario, 'random' => $random));
		return $rs;
	}

	function bloquear_random_utilizado($usuario, $random)
	{
		$sql = "UPDATE apex_usuario_pwd_reset  SET bloqueado = 1
					 WHERE 	usuario = :usuario
					 AND random = :random";
		//toba::instancia()->get_db()->set_modo_debug(true, true);
		$id = toba::instancia()->get_db()->sentencia_preparar($sql);
		$rs = toba::instancia()->get_db()->sentencia_ejecutar($id, array('usuario'=>$usuario, 'random' => $random));
	}
}
?>