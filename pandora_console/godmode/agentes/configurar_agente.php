<?php

// Pandora FMS - http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2010 Artica Soluciones Tecnologicas
// Please see http://pandorafms.org for full contribution list

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation for version 2.
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.


// Load global vars
global $config;

enterprise_include ('godmode/agentes/configurar_agente.php');

check_login ();

//See if id_agente is set (either POST or GET, otherwise -1
$id_agente = (int) get_parameter ("id_agente");
$group = 0;
if ($id_agente)
	$group = get_agent_group ($id_agente);

if (! give_acl ($config["id_user"], $group, "AW")) {
	audit_db ($config['id_user'], $_SERVER['REMOTE_ADDR'], "ACL Violation",
		"Trying to access agent manager");
	require ("general/noaccess.php");
	return;
}

require_once ('include/functions_modules.php');
require_once ('include/functions_alerts.php');
require_once ('include/functions_reporting.php');

// Get passed variables
$tab = get_parameter ('tab', 'main');
$alerttype = get_parameter ('alerttype');
$id_agent_module = (int) get_parameter ('id_agent_module');

// Init vars
$descripcion = "";
$comentarios = "";
$campo_1 = "";
$campo_2 = "";
$campo_3 = "";
$maximo = 0;
$minimo = 0;
$nombre_agente = "";
$direccion_agente = get_parameter ('direccion', '');
$intervalo = 300;
$id_server = "";
$max_alerts = 0;
$modo = 1;
$update_module = 0;
$modulo_id_agente = "";
$modulo_id_tipo_modulo = "";
$modulo_nombre = "";
$modulo_descripcion = "";
$alerta_id_aam = "";
$alerta_campo1 = "";
$alerta_campo2 = "";
$alerta_campo3 = "";
$alerta_dis_max = "";
$alerta_dis_min = "";
$alerta_min_alerts = 0;
$alerta_max_alerts = 1;
$alerta_time_threshold = "";
$alerta_descripcion = "";
$disabled = "";
$id_parent = 0;
$modulo_max = "";
$modulo_min = "";
$module_interval = "";
$tcp_port = "";
$tcp_send = "";
$tcp_rcv = "";
$snmp_oid = "";
$ip_target = "";
$snmp_community = "";
$combo_snmp_oid = "";
$agent_created_ok = 0;
$create_agent = 0;
$alert_text = "";
$time_from= "";
$time_to = "";
$alerta_campo2_rec = "";
$alerta_campo3_rec = "";
$alert_id_agent = "";
$alert_d1 = 1;
$alert_d2 = 1;
$alert_d3 = 1;
$alert_d4 = 1;
$alert_d5 = 1;
$alert_d6 = 1;
$alert_d7 = 1;
$alert_recovery = 0;
$alert_priority = 0;
$server_name = '';
$grupo = 0;
$id_os = 9; // Windows
$custom_id = "";
$cascade_protection = 0;
$icon_path = '';
$update_gis_data = 0;

$create_agent = (bool) get_parameter ('create_agent');

// Create agent
if ($create_agent) {
	$nombre_agente = (string) get_parameter_post ("agente",'');
	$direccion_agente = (string) get_parameter_post ("direccion",'');
	$grupo = (int) get_parameter_post ("grupo");
	$intervalo = (string) get_parameter_post ("intervalo", 300);
	$comentarios = (string) get_parameter_post ("comentarios", '');
	$modo = (int) get_parameter_post ("modo");
	$id_parent = (string) get_parameter_post ("id_parent",'');
	$id_parent = (int) get_agent_id ($id_parent);
	$server_name = (string) get_parameter_post ("server_name");
	$id_os = (int) get_parameter_post ("id_os");
	$disabled = (int) get_parameter_post ("disabled");
	$custom_id = (string) get_parameter_post ("custom_id",'');
	$cascade_protection = (int) get_parameter_post ("cascade_protection", 0);
	$icon_path = (string) get_parameter_post ("icon_path",'');
	$update_gis_data = (int) get_parameter_post("update_gis_data", 0);

	$fields = get_db_all_fields_in_table('tagent_custom_fields');
	
	if($fields === false) $fields = array();
	
	$field_values = array();
	
	foreach($fields as $field) {
		$field_values[$field['id_field']] = (string) get_parameter_post ('customvalue_'.$field['id_field'], '');
	}

	// Check if agent exists (BUG WC-50518-2)
	if ($nombre_agente == "") {
		$agent_creation_error = __('No agent name specified');
		$agent_created_ok = 0;
	}
	elseif (get_agent_id ($nombre_agente)) {
		$agent_creation_error = __('There is already an agent in the database with this name');
		$agent_created_ok = 0;
	}
	else {
		$id_agente = process_sql_insert ('tagente', 
			array ('nombre' => $nombre_agente,
				'direccion' => $direccion_agente,
				'id_grupo' => $grupo, 'intervalo' => $intervalo,
				'comentarios' => $comentarios, 'modo' => $modo,
				'id_os' => $id_os, 'disabled' => $disabled,
				'cascade_protection' => $cascade_protection,
				'server_name' => $server_name,
				'id_parent' => $id_parent, 'custom_id' => $custom_id,
				'icon_path' => $icon_path,
				'update_gis_data' => $update_gis_data));
		enterprise_hook ('update_agent', array ($id_agente));
		if ($id_agente !== false) {
			// Create custom fields for this agent
			foreach($field_values as $key => $value) {
				process_sql_insert ('tagent_custom_data',
				 array('id_field' => $key,'id_agent' => $id_agente, 'description' => $value));
			}
			// Create address for this agent in taddress
			agent_add_address ($id_agente, $direccion_agente);
			
			$agent_created_ok = true;

			audit_db ($config['id_user'], $_SERVER['REMOTE_ADDR'], "Agent management",
				"Created agent $nombre_agente");
		}
		else {
			$id_agente = 0;
			$agent_creation_error = __('Could not be created');
		}
	}
}

// Show tabs
$img_style = array ("class" => "top", "width" => 16);

// TODO: Change to use print_page_header
if ($id_agente) {
	
	/* View tab */
	$viewtab['text'] = '<a href="index.php?sec=estado&amp;sec2=operation/agentes/ver_agente&amp;id_agente='.$id_agente.'">' 
			. print_image ("images/zoom.png", true, array ("title" =>__('View')))
			. '</a>';
			
	if($tab == 'view')
		$viewtab['active'] = true;
	else
		$viewtab['active'] = false;
	
	/* Main tab */
	$maintab['text'] = '<a href="index.php?sec=gagente&amp;sec2=godmode/agentes/configurar_agente&amp;tab=main&amp;id_agente='.$id_agente.'">' 
			. print_image ("images/cog.png", true, array ("title" =>__('Setup')))
			. '</a>';
	if($tab == 'main')
	
		$maintab['active'] = true;
	else
		$maintab['active'] = false;
		
	/* Module tab */
	$moduletab['text'] = '<a href="index.php?sec=gagente&amp;sec2=godmode/agentes/configurar_agente&amp;tab=module&amp;id_agente='.$id_agente.'">' 
			. print_image ("images/lightbulb.png", true, array ("title" =>__('Modules')))
			. '</a>';
	
	if($tab == 'module')
		$moduletab['active'] = true;
	else
		$moduletab['active'] = false;
		
	/* Alert tab */
	$alerttab['text'] = '<a href="index.php?sec=gagente&amp;sec2=godmode/agentes/configurar_agente&amp;tab=alert&amp;id_agente='.$id_agente.'">' 
			. print_image ("images/bell.png", true, array ("title" =>__('Alerts')))
			. '</a>';
	
	if($tab == 'alert')
		$alerttab['active'] = true;
	else
		$alerttab['active'] = false;		
		
	/* Template tab */
	$templatetab['text'] = '<a href="index.php?sec=gagente&amp;sec2=godmode/agentes/configurar_agente&amp;tab=template&amp;id_agente='.$id_agente.'">' 
			. print_image ("images/network.png", true, array ("title" =>__('Module templates')))
			. '</a>';
	
	if($tab == 'template')
		$templatetab['active'] = true;
	else
		$templatetab['active'] = false;		
	
	
	/* Inventory */
	$inventorytab = enterprise_hook ('inventory_tab');

	if ($inventorytab == -1)
		$inventorytab = "";

	/* Collection */
	$collectiontab = enterprise_hook('collection_tab');

	if ($collectiontab == -1)
		$collectiontab = "";
	
	/* Group tab */
	
	$grouptab['text'] = '<a href="index.php?sec=gagente&sec2=godmode/agentes/modificar_agente&ag_group='.$group.'">'
			. print_image ("images/agents_group.png", true, array( "title" => __('Group')))
			. '</a>';
	
	$grouptab['active'] = false;
	
	$gistab = "";
	
	/* GIS tab */
	if ($config['activate_gis']) {
		
		$gistab['text'] = '<a href="index.php?sec=gagente&sec2=godmode/agentes/configurar_agente&tab=gis&id_agente='.$id_agente.'">'
			. print_image ("images/world.png", true, array ( "title" => __('GIS data')))
			. '</a>';

		if ($tab == "gis")
			$gistab['active'] = true;
		else
			$gistab['active'] = false;
	}
	
	$onheader = array('view' => $viewtab, 'separator' => "", 'main' => $maintab, 'module' => $moduletab, 'alert' => $alerttab, 'template' => $templatetab, 'inventory' => $inventorytab, 'collection'=> $collectiontab, 'group' => $grouptab, 'gis' => $gistab);

	print_page_header (__('Agent configuration').' -&nbsp;'.printTruncateText(get_agent_name ($id_agente)), "images/setup.png", false, "", true, $onheader);
	
}
// Create agent 
else {

	print_page_header (__('Agent manager'), "images/bricks.png", false, "create_agent", true);

}

$delete_conf_file = (bool) get_parameter('delete_conf_file');

if ($delete_conf_file) {
	$correct = false;
	// Delete remote configuration
	if (isset ($config["remote_config"])) {
		$agent_md5 = md5 (get_agent_name ($id_agente,'none'), FALSE);
		
		if (file_exists ($config["remote_config"]."/md5/".$agent_md5.".md5")) {
			// Agent remote configuration editor
			$file_name = $config["remote_config"]."/conf/".$agent_md5.".conf";
			$correct = @unlink ($file_name);
			
			$file_name = $config["remote_config"]."/md5/".$agent_md5.".md5";
			$correct = @unlink ($file_name);
		}
	}
	
	print_result_message ($correct,
		__('Conf file deleted successfully'),
		__('Could not delete conf file'));
}


// Show agent creation results
if ($create_agent) {
	print_result_message ($agent_created_ok,
		__('Successfully created'),
		__('Could not be created'));
}

// Fix / Normalize module data
if (isset( $_GET["fix_module"])) { 
	$id_module = get_parameter_get ("fix_module",0);
	// get info about this module
	$media = get_agentmodule_data_average ($id_module, 30758400); //Get average over the year
	$media *= 1.3;
	$error = "";
	//If the value of media is 0 or something went wrong, don't delete
	if (!empty ($media)) {
		$sql = sprintf ("DELETE FROM tagente_datos WHERE datos > %f AND id_agente_modulo = %d", $media, $id_module);
		$result = process_sql ($sql);
	} else {
		$result = false;
		$error = " - ".__('No data to normalize');
	}
	
	print_result_message ($result,
		__('Deleted data above %d', $media),
		__('Error normalizing module %s', $error));
}

$update_agent = (bool) get_parameter ('update_agent');

// Update AGENT
if ($update_agent) { // if modified some agent paramenter
	$id_agente = (int) get_parameter_post ("id_agente");
	$nombre_agente = str_replace('`','&lsquo;',(string) get_parameter_post ("agente", ""));
	$direccion_agente = (string) get_parameter_post ("direccion", '');
	$address_list = (string) get_parameter_post ("address_list", '');
	if ($address_list != $direccion_agente && $direccion_agente == get_agent_address ($id_agente) && $address_list != get_agent_address ($id_agente)) {
		//If we selected another IP in the drop down list to be 'primary': 
		// a) field is not the same as selectbox
		// b) field has not changed from current IP
		// c) selectbox is not the current IP
		if ($address_list != 0)
			$direccion_agente = $address_list;
	}
	$grupo = (int) get_parameter_post ("grupo", 0);
	$intervalo = (int) get_parameter_post ("intervalo", 300);
	$comentarios = str_replace('`','&lsquo;',(string) get_parameter_post ("comentarios", ""));
	$modo = (bool) get_parameter_post ("modo", 0); //Mode: Learning or Normal
	$id_os = (int) get_parameter_post ("id_os");
	$disabled = (bool) get_parameter_post ("disabled");
	$server_name = (string) get_parameter_post ("server_name", "");
	$id_parent = (string) get_parameter_post ("id_parent");
	$id_parent = (int) get_agent_id ($id_parent);
	$custom_id = (string) get_parameter_post ("custom_id", "");
	$cascade_protection = (int) get_parameter_post ("cascade_protection", 0);
	$icon_path = (string) get_parameter_post ("icon_path",'');
	$update_gis_data = (int) get_parameter_post("update_gis_data", 0);
	
	$fields = get_db_all_fields_in_table('tagent_custom_fields');
	
	if($fields === false) $fields = array();
	
	$field_values = array();
	
	foreach($fields as $field) {
		$field_values[$field['id_field']] = (string) get_parameter_post ('customvalue_'.$field['id_field'], '');
	}
	
	
	foreach($field_values as $key => $value) {
		$old_value = get_db_all_rows_filter('tagent_custom_data', array('id_agent' => $id_agente, 'id_field' => $key));
	
		if($old_value === false) {
			// Create custom field if not exist
			process_sql_insert ('tagent_custom_data',
				 array('id_field' => $key,'id_agent' => $id_agente, 'description' => $value));
		}else {		
			process_sql_update ('tagent_custom_data',
				 array('description' => $value),
				 array('id_field' => $key,'id_agent' => $id_agente));
		}
	}
	
	//Verify if there is another agent with the same name but different ID
	if ($nombre_agente == "") { 
		echo '<h3 class="error">'.__('No agent name specified').'</h3>';	
	//If there is an agent with the same name, but a different ID
	} elseif (get_agent_id ($nombre_agente) > 0 && get_agent_id ($nombre_agente) != $id_agente) {
		echo '<h3 class="error">'.__('There is already an agent in the database with this name').'</h3>';
	} else {
		//If different IP is specified than previous, add the IP
		if ($direccion_agente != '' && $direccion_agente != get_agent_address ($id_agente))
			agent_add_address ($id_agente, $direccion_agente);
		
		//If IP is set for deletion, delete first
		if (isset ($_POST["delete_ip"])) {
			$delete_ip = get_parameter_post ("address_list");
			agent_delete_address ($id_agente, $delete_ip);
		}
	
		$result = process_sql_update ('tagente', 
			array ('disabled' => $disabled,
				'id_parent' => $id_parent,
				'id_os' => $id_os,
				'modo' => $modo,
				'nombre' => $nombre_agente,
				'direccion' => $direccion_agente,
				'id_grupo' => $grupo,
				'intervalo' => $intervalo,
				'comentarios' => $comentarios,
				'cascade_protection' => $cascade_protection,
				'server_name' => $server_name,
				'custom_id' => $custom_id,
				'icon_path' => $icon_path,
				'update_gis_data' => $update_gis_data),
			array ('id_agente' => $id_agente));
			
		if ($result === false) {
			print_error_message (__('There was a problem updating the agent'));
		} else {
			enterprise_hook ('update_agent', array ($id_agente));
			print_success_message (__('Successfully updated'));
			audit_db ($config['id_user'], $_SERVER['REMOTE_ADDR'], "Agent management",
		"Updated agent $nombre_agente");

		}
	}
}

// Read agent data
// This should be at the end of all operation checks, to read the changes - $id_agente doesn't have to be retrieved
if ($id_agente) {
	//This has been done in the beginning of the page, but if an agent was created, this id might change
	$id_grupo = get_agent_group ($id_agente);
	if (give_acl ($config["id_user"], $id_grupo, "AW") != 1) {
		audit_db($config["id_user"],$_SERVER['REMOTE_ADDR'], "ACL Violation","Trying to admin an agent without access");
		require ("general/noaccess.php");
		exit;
	}
	
	$agent = get_db_row ('tagente', 'id_agente', $id_agente);
	if (empty ($agent)) {
		//Close out the page
		print_error_message (__('There was a problem loading the agent'));
		return;
	}
	
	$intervalo = $agent["intervalo"]; // Define interval in seconds
	$nombre_agente = $agent["nombre"];
	$direccion_agente = $agent["direccion"];
	$grupo = $agent["id_grupo"];
	$ultima_act = $agent["ultimo_contacto"];
	$comentarios = $agent["comentarios"];
	$server_name = $agent["server_name"];
	$modo = $agent["modo"];
	$id_os = $agent["id_os"];
	$disabled = $agent["disabled"];
	$id_parent = $agent["id_parent"];
	$custom_id = $agent["custom_id"];
	$cascade_protection = $agent["cascade_protection"];
	$icon_path = $agent["icon_path"];
	$update_gis_data = $agent["update_gis_data"];
}

$update_module = (bool) get_parameter ('update_module');
$create_module = (bool) get_parameter ('create_module');
$delete_module = (bool) get_parameter ('delete_module');
$duplicate_module = (bool) get_parameter ('duplicate_module');
$edit_module = (bool) get_parameter ('edit_module');

// GET DATA for MODULE UPDATE OR MODULE INSERT
if ($update_module || $create_module) {
	$id_grupo = get_agent_group ($id_agente);
	
	if (! give_acl ($config["id_user"], $id_grupo, "AW")) {
		audit_db ($config["id_user"], $_SERVER['REMOTE_ADDR'], "ACL Violation",
			"Trying to create a module without admin rights");
		require ("general/noaccess.php");
		exit;
	}
	$id_module_type = (int) get_parameter ('id_module_type');
	$name = (string) get_parameter ('name');
	$description = (string) get_parameter ('description');
	$id_module_group = (int) get_parameter ('id_module_group');
	$flag = (bool) get_parameter ('flag');

	// Don't read as (float) because it lost it's decimals when put into MySQL
	// where are very big and PHP uses scientific notation, p.e:
	// 1.23E-10 is 0.000000000123
	
	$post_process = (string) get_parameter ('post_process');
	$prediction_module = (int) get_parameter ('prediction_module');
	$max_timeout = (int) get_parameter ('max_timeout');
	$min = (int) get_parameter_post ("min");
	$max = (int) get_parameter ('max');
	$interval = (int) get_parameter ('module_interval', $intervalo);
	$id_plugin = (int) get_parameter ('id_plugin');
	$id_export = (int) get_parameter ('id_export');
	$disabled = (bool) get_parameter ('disabled');
	$tcp_send = (string) get_parameter ('tcp_send');
	$tcp_rcv = (string) get_parameter ('tcp_rcv');
	$tcp_port = (int) get_parameter ('tcp_port');

	$custom_string_1 = (string) get_parameter ('custom_string_1');
	$custom_string_2 = (string) get_parameter ('custom_string_2');
	$custom_string_3 = (string) get_parameter ('custom_string_3');
	$custom_integer_1 = (int) get_parameter ('custom_integer_1');
	$custom_integer_2 = (int) get_parameter ('custom_integer_2');

	// Services are an enterprise feature, 
    // so we got the parameters using this function.

	enterprise_hook ('get_service_parameters');
	
	$agent_name = (string) get_parameter('agent_name',get_agent_name ($id_agente));

	$snmp_community = (string) get_parameter ('snmp_community');
	$snmp_oid = (string) get_parameter ('snmp_oid');

	if (empty ($snmp_oid)) {
		/* The user did not set any OID manually but did a SNMP walk */
		$snmp_oid = (string) get_parameter ('select_snmp_oid');
	}

	if ($id_module_type >= 15 && $id_module_type <= 18){
		// New support for snmp v3
		$tcp_send = (string) get_parameter ('snmp_version');
		$plugin_user = (string) get_parameter ('snmp3_auth_user');
		$plugin_pass = (string) get_parameter ('snmp3_auth_pass');
		$plugin_parameter = (string) get_parameter ('snmp3_auth_method');

		$custom_string_1 = (string) get_parameter ('snmp3_privacy_method');
		$custom_string_2 = (string) get_parameter ('snmp3_privacy_pass');
		$custom_string_3 = (string) get_parameter ('snmp3_security_level');
	}
	else {
		$plugin_user = (string) get_parameter ('plugin_user');
		if (get_parameter('id_module_component_type') == 7)
			$plugin_pass = (int) get_parameter ('plugin_pass');
		else
			$plugin_pass = (string) get_parameter ('plugin_pass');
			
		$plugin_parameter = (string) get_parameter ('plugin_parameter');
	}
		
	$ip_target = (string) get_parameter ('ip_target');
	$custom_id = (string) get_parameter ('custom_id');
	$history_data = (int) get_parameter('history_data');
	$min_warning = (float) get_parameter ('min_warning');
	$max_warning = (float) get_parameter ('max_warning');
	$min_critical = (float) get_parameter ('min_critical');
	$max_critical = (float) get_parameter ('max_critical');
	$ff_event = (int) get_parameter ('ff_event');
	
	$active_snmp_v3 = get_parameter('active_snmp_v3');
	if ($active_snmp_v3) {
	//
	}
}

// MODULE UPDATE
if ($update_module) {
	$id_agent_module = (int) get_parameter ('id_agent_module');
	
	$result = update_agent_module ($id_agent_module,
		array ('descripcion' => $description,
			'id_module_group' => $id_module_group,
			'nombre' => $name,
			'max' => $max,
			'min' => $min,
			'module_interval' => $interval,
			'tcp_port' => $tcp_port,
			'tcp_send' => $tcp_send,
			'tcp_rcv' => $tcp_rcv,
			'snmp_community' => $snmp_community,
			'snmp_oid' => $snmp_oid,
			'ip_target' => $ip_target,
			'flag' => $flag,
			'disabled' => $disabled,
			'id_export' => $id_export,
			'plugin_user' => $plugin_user,
			'plugin_pass' => $plugin_pass,
			'plugin_parameter' => $plugin_parameter,
			'id_plugin' => $id_plugin,
			'post_process' => $post_process,
			'prediction_module' => $prediction_module,
			'max_timeout' => $max_timeout,
			'custom_id' => $custom_id,
			'history_data' => $history_data,
			'min_warning' => $min_warning,
			'max_warning' => $max_warning,
			'min_critical' => $min_critical,
			'max_critical' => $max_critical,
			'custom_string_1' => $custom_string_1,
			'custom_string_2' => $custom_string_2,
			'custom_string_3' => $custom_string_3,
			'custom_integer_1' => $custom_integer_1,
			'custom_integer_2' => $custom_integer_2,
			'min_ff_event' => $ff_event));
	
	if ($result === false) {
		echo '<h3 class="error">'.__('There was a problem updating module').'</h3>';
		$edit_module = true;
	} else {
		echo '<h3 class="suc">'.__('Module successfully updated').'</h3>';
		$id_agent_module = false;
		$edit_module = false;

		$agent = get_db_row ('tagente', 'id_agente', $id_agente);

		audit_db ($config['id_user'], $_SERVER['REMOTE_ADDR'], "Agent management",
		"Updated module '$name' for agent ".$agent["nombre"]);
	}
}

// MODULE INSERT
if ($create_module) {
	if (isset ($_POST["combo_snmp_oid"])) {
		$combo_snmp_oid = get_parameter_post ("combo_snmp_oid");
	}
	if ($snmp_oid == ""){
		$snmp_oid = $combo_snmp_oid;
	}
	
	$id_module = (int) get_parameter ('id_module');
	
	$id_agent_module = create_agent_module ($id_agente, $name,
		array ('id_tipo_modulo' => $id_module_type,
			'descripcion' => $description, 
			'max' => $max,
			'min' => $min, 
			'snmp_oid' => $snmp_oid,
			'snmp_community' => $snmp_community,
			'id_module_group' => $id_module_group, 
			'module_interval' => $interval,
			'ip_target' => $ip_target,
			'tcp_port' => $tcp_port,
			'tcp_rcv' => $tcp_rcv, 
			'tcp_send' => $tcp_send,
			'id_export' => $id_export, 
			'plugin_user' => $plugin_user,
			'plugin_pass' => $plugin_pass, 
			'plugin_parameter' => $plugin_parameter,
			'id_plugin' => $id_plugin, 
			'post_process' => $post_process,
			'prediction_module' => $prediction_module,
			'max_timeout' => $max_timeout, 
			'disabled' => $disabled,
			'id_modulo' => $id_module,
			'custom_id' => $custom_id,
			'history_data' => $history_data,
			'min_warning' => $min_warning,
			'max_warning' => $max_warning,
			'min_critical' => $min_critical,
			'max_critical' => $max_critical,
			'custom_string_1' => $custom_string_1,
			'custom_string_2' => $custom_string_2,
			'custom_string_3' => $custom_string_3,
			'custom_integer_1' => $custom_integer_1,
			'custom_integer_2' => $custom_integer_2,
			'min_ff_event' => $ff_event
		));
	
	if ($id_agent_module === false) {
		echo '<h3 class="error">'.__('There was a problem adding module').'</h3>';
		$edit_module = true;
		$moduletype = $id_module;
	} else {
		echo '<h3 class="suc">'.__('Module added successfully').'</h3>';
		$id_agent_module = false;
		$edit_module = false;

		$agent = get_db_row ('tagente', 'id_agente', $id_agente);
		audit_db ($config['id_user'], $_SERVER['REMOTE_ADDR'], "Agent management",
		"Added module '$name' for agent ".$agent["nombre"]);
	}
}

// MODULE DELETION
// =================
if ($delete_module){ // DELETE agent module !
	$id_borrar_modulo = (int) get_parameter_get ("delete_module",0);
	$module_data = get_db_row ('tagente_modulo', 'id_agente_modulo', $id_borrar_modulo);
	$id_grupo = (int) dame_id_grupo ($id_agente);
	
	if (! give_acl ($config["id_user"], $id_grupo, "AW")) {
		audit_db($config["id_user"],$_SERVER['REMOTE_ADDR'], "ACL Violation",
		"Trying to delete a module without admin rights");
		require ("general/noaccess.php");
		exit;
	}
	
	if ($id_borrar_modulo < 1) {
		audit_db ($config["id_user"],$_SERVER['REMOTE_ADDR'], "HACK Attempt",
		"Expected variable from form is not correct");
		require ("general/noaccess.php");
		exit;
	}
	
	enterprise_include_once('include/functions_config_agents.php');
	enterprise_hook('deleteLocalModuleInConf', array(get_agentmodule_agent($id_borrar_modulo), get_agentmodule_name($id_borrar_modulo)));
	
	//Init transaction
	$error = 0;
	process_sql_begin ();
	
	// First delete from tagente_modulo -> if not successful, increment
	// error. NOTICE that we don't delete all data here, just marking for deletion
	// and delete some simple data.
	
	if (process_sql ("UPDATE tagente_modulo SET nombre = 'pendingdelete', disabled = 1, delete_pending = 1 WHERE id_agente_modulo = ".$id_borrar_modulo) === false)
		$error++;
	
	if (process_sql ("DELETE FROM tagente_estado WHERE id_agente_modulo = ".$id_borrar_modulo) === false)
		$error++;

	if (process_sql ("DELETE FROM tagente_datos_inc WHERE id_agente_modulo = ".$id_borrar_modulo) === false)
		$error++;

	if (delete_alert_agent_module($id_borrar_modulo) === false)
		$error++;
	

	//Check for errors
	if ($error != 0) {
		process_sql_rollback ();
		print_error_message (__('There was a problem deleting the module'));
	} else {
		process_sql_commit ();
		print_success_message (__('Module deleted succesfully'));

		$agent = get_db_row ('tagente', 'id_agente', $id_agente);
		audit_db ($config['id_user'], $_SERVER['REMOTE_ADDR'], "Agent management",
		"Deleted module '".$module_data["nombre"]."' for agent ".$agent["nombre"]);
	}
}

// MODULE DUPLICATION
// =================
if ($duplicate_module){ // DUPLICATE agent module !
	$id_duplicate_module = (int) get_parameter_get ("duplicate_module",0);
	$result = copy_agent_module_to_agent ($id_duplicate_module,
				get_agentmodule_agent($id_duplicate_module),
				__('copy of').' '.get_agentmodule_name($id_duplicate_module));
	debugPrint(var_dump($result));
}

// UPDATE GIS
// ==========
$updateGIS = get_parameter('update_gis', 0);
if ($updateGIS) {
	$updateGisData = get_parameter("update_gis_data");
	$lastLatitude = get_parameter("latitude");
	$lastLongitude = get_parameter("longitude");
	$lastAltitude = get_parameter("altitude");
	$idAgente = get_parameter("id_agente");
	
	$previusAgentGISData = get_db_row_sql("SELECT *
		FROM tgis_data_status WHERE tagente_id_agente = " . $idAgente);
	
	process_sql_begin();
	
	process_sql_update('tagente', array('update_gis_data' => $updateGisData),
		array('id_agente' => $idAgente));
		
	if ($previusAgentGISData !== false) {
		process_sql_insert('tgis_data_history', array(
			"longitude" => $previusAgentGISData['stored_longitude'],
			"latitude" => $previusAgentGISData['stored_latitude'],
			"altitude" => $previusAgentGISData['stored_altitude'],
			"start_timestamp" => $previusAgentGISData['start_timestamp'],
			"end_timestamp" => date( 'Y-m-d H:i:s'),
			"description" => "Save by Pandora Console",
			"manual_placement" => $previusAgentGISData['manual_placement'],
			"number_of_packages" => $previusAgentGISData['number_of_packages'],
			"tagente_id_agente" => $previusAgentGISData['tagente_id_agente']
		));
		process_sql_update('tgis_data_status', array(
			"tagente_id_agente" => $idAgente,
			"current_longitude" => $lastLongitude,
			"current_latitude" => $lastLatitude,
			"current_altitude" => $lastAltitude,
			"stored_longitude" => $lastLongitude,
			"stored_latitude" => $lastLatitude,
			"stored_altitude" => $lastAltitude,
			"start_timestamp" => date( 'Y-m-d H:i:s'),
			"manual_placement" => 1,
			"description" => "Update by Pandora Console"),
			array("tagente_id_agente" => $idAgente));
	}
	else {
		process_sql_insert('tgis_data_status', array(
			"tagente_id_agente" => $idAgente,
			"current_longitude" => $lastLongitude,
			"current_latitude" => $lastLatitude,
			"current_altitude" => $lastAltitude,
			"stored_longitude" => $lastLongitude,
			"stored_latitude" => $lastLatitude,
			"stored_altitude" => $lastAltitude,
			"manual_placement" => 1,
			"description" => "Insert by Pandora Console"
		));
	}
	process_sql_commit();
}

// -----------------------------------
// Load page depending on tab selected
// -----------------------------------
switch ($tab) {
	case "main":
		require ("agent_manager.php");
		break;
	case "module":
		if ($id_agent_module || $edit_module) {
			require ("module_manager_editor.php");
		} else {
			require ("module_manager.php");
		}
		break;
	case "alert":
		/* Because $id_agente is set, it will show only agent alerts */
		require ("godmode/alerts/alert_list.php");
		break;
	case "template":
		require ("agent_template.php");
		break;
	case "gis":
		require("agent_conf_gis.php");
		break;
	default:
		if (enterprise_hook ('switch_agent_tab', array ($tab)))
			//This will make sure that blank pages will have at least some
			//debug info in them - do not translate debug
			print_error_message ("DEBUG: Invalid tab specified in ".__FILE__.":".__LINE__);
}
?>
