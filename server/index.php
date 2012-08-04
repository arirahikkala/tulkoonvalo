<?php

require 'slim/Slim.php';

$app = new Slim();

$app->get('/programs', 'getPrograms');
$app->get('/lights', 'getLightsTree');
$app->get('/lightsFlat', 'getLights');
$app->put('/lightsFlat/:id', 'updateLightBrightness');
$app->run();

function getPrograms() {
	$sql = "select name from programs";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute();
		$programs = $stmt->fetchAll (PDO::FETCH_OBJ);

		echo json_encode ($programs);
	}
	
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}

}

// put attributes in a light into the places where jstree expects them
function stuffLightAttributes ($element)
{
	$rv = [];
	foreach ($element as $k => $v) {
		if ($k == "parent") {}
		else if ($k == "name") { $rv['data'] = $v; }
		else if ($k == "children") { $rv['children'] = $v; }
		else { $rv['attr'][$k] = $v; }
	}

	return ($rv);
}

// turn the parent-pointer structure we got from SQL into an actual tree, of a
// format that can be directly JSON-ed for jstree's use.
// O(n^2) performance (every item in the list is scanned once for each root, 
// and each item is a root) - almost certainly ok for the datasets this
// program will have to deal with.
// arguments: $root: An array with an 'id' element
//            $list: An array of arrays with a 'parent' and an 'id' element
// returns: A tree of elements, with each element's children in ['children']
function reconstructLightTree ($root, $list)
{
	$root['children'] = [];
	foreach ($list as $v) {
		if ($v['parent'] == $root['id']) {
			$root['children'][] = reconstructLightTree ($v, $list);
		}
	}
	return stuffLightAttributes ($root);
}

function getLightsTree() {
	$sql = "select name, brightness, parent, id from lights";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute();
		$lights = $stmt->fetchAll (PDO::FETCH_ASSOC);
		$tree = [];

		foreach ($lights as $key => $s) {
			if (is_null ($s['parent'])) {
				$tree[] = reconstructLightTree ($s, $lights);
			}
		}

		echo json_encode ($tree, JSON_PRETTY_PRINT);
	}
	
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
}

function getLights() {
	$sql = "select name, brightness, parent, id from lights";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute();
		$lights = $stmt->fetchAll (PDO::FETCH_OBJ);

		echo json_encode ($lights, JSON_PRETTY_PRINT);
	}
	
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}

}

function updateLightBrightness($id) {
	$sql = "update lights set brightness=? where id=?";
	$requestBody = json_decode (Slim::getInstance()->request()->getBody());
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute(array ($requestBody->brightness, $id));

		echo "{}";
//		echo json_encode ($lights, JSON_PRETTY_PRINT);

	}
	
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}

}

function getConnection() {
	require ("config.php");
	$dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);	
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	return $dbh;
}

?>