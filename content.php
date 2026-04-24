<?php

/**
 * Descripction....: direcciones para el control de las rutas del sistema
 * Autor : Marcos 
 */
$valor = 1;
if ($valor != 1) {
    echo "<meta http-equiv='refresh' content='0; url=https://simfcoh.com/'>";
} else {
    if (empty($_GET['module'])) {
        include "./modules/dasboard/views/dash.php";
    } else {
        /**
         * Description : rutas
         * **/

        $_GET['module'] == 'repuestos' ? include "./modules/Electronicas/Repuestos/views/vw_repuestos.php" : false;
        $_GET['module'] == 'maquinas' ? include "./modules/Electronicas/Maquinas/views/maquinas.php" : false;
        $_GET['module'] == 'mantenimientos' ? include "./modules/Electronicas/Mantenimientos/views/mantenimientos.php" : false;
        //  $_GET['module'] == 'listadoProductores' ? include "./modules/productores/views/listaProductores.php" :false;

        $_GET['module'] == 'dasboard' ? include "./modules/dasboard/views/dash.php" : false ;

        // Parametrización
        $_GET['module'] == 'marcas'   ? include "./modules/Parametrizacion/Marcas/views/vw_marcas.php"     : false;
        $_GET['module'] == 'modelos'      ? include "./modules/Parametrizacion/Modelos/views/vw_modelos.php"       : false;
        $_GET['module'] == 'proveedores'  ? include "./modules/Parametrizacion/Proveedores/views/vw_proveedores.php" : false;
        $_GET['module'] == 'tiposRepuestos'  ? include "./modules/Parametrizacion/TiposRepuestos/views/vw_tiposRepuestos.php" : false;
        $_GET['module'] == 'usuarios'        ? include "./modules/Usuarios/views/vw_usuarios.php"                           : false;
        $_GET['module'] == 'divisas'         ? include "./modules/Parametrizacion/Divisas/views/vw_divisas.php"            : false;
    }
}
