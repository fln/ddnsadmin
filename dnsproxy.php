<?php

// Load Net/DNS2 library
if (file_exists('Net.phar')) {
	require_once 'phar://Net.phar/DNS2.php';

	spl_autoload_unregister('Net_DNS2::autoload');
	spl_autoload_register(function ($name) {
		if (strncmp($name, 'Net_DNS2', 8) == 0)
			require 'phar://Net.phar'.str_replace('_', '/', substr($name, 3)) . '.php';
	});
} elseif (file_exists('Net/DNS2.php')) {
	require_once 'Net/DNS2.php';
} else {
	output_error('Net_DNS2 PHP library is missing.');
}

//////////////////// Helper funcions ////////////////////

function output_error($error_string, $status_code = 500) {
	http_response_code($status_code);
	die($error_string);
}

function output($data) {
	header('Content-Type: application/json');
	die(json_encode($data));
}

function resolve($r, $name, $type) {
	try {
		$data = $r->query($name, $type);
	} catch(Net_DNS2_Exception $e) {
		output_error($e->getMessage());
	}
	return $data;
}

//////////////////// Request handlers ////////////////////

function supported_key_types() {
	output(array_reverse(Net_DNS2_RR_TSIG::$hash_algorithms));
}

function supported_rr_types() {
	$types = array();
	foreach (Net_DNS2_Lookups::$rr_types_id_to_class as $val) {
		$tmp = explode('_', $val);
		$types[$tmp[3]] = $tmp[3];
	}
	output($types);
}

function zone_to_server($args) {
	foreach (array("zone") as $val)
		if (empty($args[$val]))
			output_error('Invalid request, "'.$val.'" field is mandatory.', 400);


	$r = new Net_DNS2_Resolver();
	$data = resolve($r, $args['zone'], 'NS');

	$ns_list = array_map(function ($r) {return $r->nsdname;}, $data->answer);

	if (empty($ns_list))
		output_error('No NS records for zone "'.$args['zone'].'"');

	sort($ns_list);
	$data = resolve($r, $ns_list[0], "A");
	$a_list = array_map(function ($r) {return $r->address;}, $data->answer);

	output(array("server" => $a_list[0]));
}

function add_record($args) {
	foreach (array("zone", "key-name", "key-type", "key", "server", "record") as $val)
		if (empty($args[$val]))
			output_error('Invalid request, "'.$val.'" field is mandatory.', 400);

	$record = $args['record'];

	foreach (array("name", "ttl", "type", "data") as $val)
		if (empty($record[$val]))
			output_error('Invalid request, "'.$val.'" field is mandatory.', 400);

	$type_name = $record['type'];
	if (empty(Net_DNS2_Lookups::$rr_types_by_name[$type_name]))
		output_error('Resource record type "'.$type_name.'" is not supported.');

	$type_id = Net_DNS2_Lookups::$rr_types_by_name[$type_name];
	if (empty(Net_DNS2_Lookups::$rr_types_id_to_class[$type_id]))
		output_error('Resource record type "'.$type_name.'" is not supported.');
	
	$type_class = Net_DNS2_Lookups::$rr_types_id_to_class[$type_id];

	$u = new Net_DNS2_Updater($args['zone'], array('nameservers' => array($args['server'])));
	try {
		$u->signTSIG($args['key-name'], $args['key'], $args['key-type']);
		$u->add($type_class::fromString($record['name'].' '.$record['ttl'].' IN '.$record['type'].' '.$record['data']));
		$u->update();
	} catch(Net_DNS2_Exception $e) {
		output_error($e->getMessage());
	}
}

function delete_record($args) {
	foreach (array("zone", "key-name", "key-type", "key", "server", "record") as $val)
		if (empty($args[$val]))
			output_error('Invalid request, "'.$val.'" field is mandatory', 400);

	$record = $args['record'];

	foreach (array("name", "type", "data") as $val)
		if (empty($record[$val]))
			output_error('Invalid request, "'.$val.'" field is mandatory', 400);

	$type_name = $record['type'];
	if (empty(Net_DNS2_Lookups::$rr_types_by_name[$type_name]))
		output_error('Resource record type "'.$type_name.'" is not supported.');

	$type_id = Net_DNS2_Lookups::$rr_types_by_name[$type_name];
	if (empty(Net_DNS2_Lookups::$rr_types_id_to_class[$type_id]))
		output_error('Resource record type "'.$type_name.'" is not supported.');
	
	$type_class = Net_DNS2_Lookups::$rr_types_id_to_class[$type_id];

	$u = new Net_DNS2_Updater($args['zone'], array('nameservers' => array($args['server'])));
	try {
		$u->signTSIG($args['key-name'], $args['key'], $args['key-type']);
		$u->delete($type_class::fromString($record['name'].' 0 NONE '.$record['type'].' '.$record['data']));
		$u->update();
	} catch(Net_DNS2_Exception $e) {
		output_error($e->getMessage());
	}
}

function axfr($args) {
	foreach (array("zone", "key-name", "key-type", "key", "server") as $val)
		if (empty($args[$val]))
			output_error('Invalid request, "'.$val.'" field is mandatory', 400);

	$r = new Net_DNS2_Resolver(array('nameservers' => array($args['server'])));
	$r->signTSIG($args['key-name'], $args['key'], $args['key-type']);

	$data = resolve($r, $args['zone'], 'AXFR');
	
	$records = array_map(function ($r) {
		return array(
			'name' => $r->name,
			'ttl' => $r->ttl,
			'type' => $r->type,
			'data' => implode(' ', array_slice(explode(' ', $r), 4))
		);
	}, $data->answer);

	output(array("records" => $records));
}

//////////////////// Main code ////////////////////

if (isset($_SERVER['HTTP_ORIGIN'])) {
	header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
	header('Access-Control-Allow-Credentials: true');
	header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
	header('Allow: POST');
	output_error("Only POST method is allowed.", 405);
}

$args = json_decode(file_get_contents("php://input"), true);

switch ($_SERVER['PATH_INFO']) {
	case '/ping':
		output(array('response' => 'pong'));
		break;
	case '/supported-key-types':
		supported_key_types();
		break;
	case '/supported-rr-types':
		supported_rr_types();
		break;
	case '/zone-to-server':
		zone_to_server($args);
		break;
	case '/axfr':
		axfr($args);
		break;
	case '/delete-record':
		delete_record($args);
		break;
	case '/add-record':
		add_record($args);
		break;
	default:
		output_error('Requested method is not available.', 501);
}
