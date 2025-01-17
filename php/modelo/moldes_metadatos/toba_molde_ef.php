<?php
/*
*
*/
class toba_molde_ef
{
    private $datos;
    protected $molde_datos_tabla = null;

    public function __construct($identificador, $tipo)
    {
        $this->datos['identificador'] = $identificador;
        $this->datos['elemento_formulario'] = $tipo;
        $this->datos['etiqueta'] = $identificador;
        $this->datos['columnas'] = $identificador;
    }

    public function get_identificador()
    {
        return $this->datos['identificador'];
    }

    //---------------------------------------------------
    //-- API de construccion
    //---------------------------------------------------

    public function set_etiqueta($etiqueta)
    {
        $this->datos['etiqueta'] = $etiqueta;
    }

    public function set_orden($orden)
    {
        $this->datos['orden'] = $orden;
    }

    public function set_columnas($columnas)
    {
        if (!is_array($columnas)) {
            throw new error_toba('Las columnas deben definirse mediante un array');
        } else {
            $columnas = implode(', ', $columnas);
        }
        $this->datos['columnas'] = $columnas;
    }

    public function set_propiedad($nombre, $valor)
    {
        $this->datos[$nombre] = $valor;
    }

    //--------------Relaci�n con el datos_tabla de carga -----------------------

    public function tiene_carga_datos_tabla()
    {
        return isset($this->molde_datos_tabla);
    }

    public function set_molde_datos_tabla_carga($molde)
    {
        $this->molde_datos_tabla = $molde;
    }

    /**
     * Genera el datos_tabla utilizado para la tabla y lo asocia al parametro del ef
     */
    public function generar_datos_tabla_carga()
    {
        $this->molde_datos_tabla->generar();
        $clave = $this->molde_datos_tabla->get_clave_componente_generado();
        $this->datos['carga_dt'] = $clave['clave'];
    }

    //------------------------------------------------------------------

    public function get_datos()
    {
        return $this->datos;
    }
}
