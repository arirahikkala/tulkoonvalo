<?php

require 'slim/Slim.php';

$app = new Slim();

//$app->get('/programs(:/program(/lines/:line))', 'getPrograms');
$app->get('/programs/', function() { getPrograms ();});
$app->get('/programs/:program/', function($program) { getPrograms ("programs", $program);});
$app->get('/programs/:program/lines/', function ($program) { getPrograms ("lines", $program);});
$app->get('/programs/:program/lines/:line/', function ($program, $line) { getPrograms ("lines", $program, $line);});
$app->get('/programs/:program/lines/:line/lights/', function ($program, $line) { getPrograms ("lights", $program, $line);});
$app->get('/programs/:program/lines/:line/lights/:light/', 
	  function ($program, $line, $light) { getPrograms ("lights", $program, $line, $light);});

$app->get('/lights', 'getLightsTree');
$app->get('/lightsFlat', 'getLights');
$app->put('/lightsFlat/:id', 'updateLightBrightness');
$app->run();

function getPrograms($listwhat = "programs", $program = null, $line = null, $light = null) {
	$sql = "select p.id as programid, p.name as programname, l.id as lineid, l.time_trigger, l.sensor_trigger, ll.light_id as lightid, ll.brightness, li.name as lightname from programs p left join programs_lines l on p.id = l.program_id left join programs_lines_lights ll on l.id = ll.line_id join lights li on ll.light_id = li.id";
	$args = [];

	if (!is_null ($light)) {
		$sql .= " where p.id = :programid and l.id = :lineid and ll.light_id = :lightid";
		$args[":programid"] = $program;
		$args[":lineid"] = $line;
		$args[":lightid"] = $light;
	} else if (!is_null ($line)) {
		$sql .= " where p.id = :programid and l.id = :lineid";
		$args[":programid"] = $program;
		$args[":lineid"] = $line;
	} else if (!is_null ($program)) {
		$sql .= " where p.id = :programid";
		$args[":programid"] = $program;
	}

	$sql .= " order by programid, lineid, lightid";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute ($args);
		$programs = $stmt->fetchAll (PDO::FETCH_OBJ);

		if ($stmt->rowCount() == 0) {
			echo "{}";
			return;
		}

		$data = [];
		foreach ($programs as $p) {
			$pid = intval ($p->programid);
			$lineid = intval ($p->lineid);
			$lightid = intval ($p->lightid);

			$data[$pid]['name'] = $p->programname;
			$data[$pid]['id'] = $pid;
			$data[$pid]['lines'][$lineid]['timeTrigger'] = $p->time_trigger;
			$data[$pid]['lines'][$lineid]['sensorTrigger'] = $p->sensor_trigger;
			$data[$pid]['lines'][$lineid]['lights'][$lightid]['id'] = $lightid;
			$data[$pid]['lines'][$lineid]['lights'][$lightid]['name'] = $p->lightname;
			$data[$pid]['lines'][$lineid]['lights'][$lightid]['brightness'] = $p->brightness;
		}

		if (!is_null ($program))
			$data = $data[$program];
		if ($listwhat == "lines" || $listwhat == "lights")
			$data = $data['lines'];
		if (!is_null ($line))
			$data = $data[$line];
		if ($listwhat == "lights")
			$data = $data['lights'];
		if (!is_null ($light))
			$data = $data[$light];

		echo json_encode ($data, JSON_PRETTY_PRINT);
	}

	
	catch(Exception $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
}

// put attributes into the places where jstree expects them
function stuffLightAttributes ($element)
{
	$rv = [];
	foreach ($element as $k => $v) {
		if ($k == "parent") {}
		else if ($k == "name") { $rv['data'] = str_replace('\' ', '\'', ucwords(str_replace('\'', '\' ', strtolower($v))));}
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