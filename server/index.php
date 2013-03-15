<?php

require 'slim/Slim.php';

$app = new Slim();

$app->get('/programs/', function() { getPrograms ();});
$app->get('/programs/:program/', function($program) { getPrograms ("programs", $program);});
$app->get('/programs/:program/lines/', function ($program) { getPrograms ("lines", $program);});
$app->get('/programs/:program/lines/:line/', function ($program, $line) { getPrograms ("lines", $program, $line);});
$app->get('/programs/:program/lines/:line/lights/', function ($program, $line) { getPrograms ("lights", $program, $line);});
$app->get('/programs/:program/lines/:line/lights/:light/', 
	  function ($program, $line, $light) { getPrograms ("lights", $program, $line, $light);});

$app->get('/lightsTree', 'getLightsTree');

$app->get('/lights', 'getLights');
$app->post('/lights/:id/brightness', 'updateGroupBrightness');
$app->post('/lights/:id/name', 'updateGroupName');
$app->post('/lights/:id/parent', 'updateGroupParent');
$app->delete('/lights/:id', 'removeGroup');
$app->post('/lights', 'addGroup'); 
$app->run();

/* getPrograms handles the GET requests for the whole hierarchy under /programs. Each request will return the entirety of the hierarchy under the given point as a JSON object.

For instance, GET /programs/1/lines/3/lights/2 will get you the brightness of the light with ID 2 that the program line with ID 3 in program 1 wants it to have.

arguments: $listwhat: Either "programs", "lines" or "lights"
 */
function getPrograms($listwhat = "programs", $program = null, $line = null, $light = null) {
	$sql = "select p.id as programid, p.name as programname, l.id as lineid, l.time_trigger, l.sensor_trigger, ll.light_id as lightid, ll.brightness, li.name as lightname from programs p left join programs_lines l on p.id = l.program_id left join programs_lines_lights ll on l.id = ll.line_id join lights li on ll.light_id = li.id";
	$args = array();

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

		// nothing to show? Well... show nothing, then. Let the client handle it if they wanted something more.
		if ($stmt->rowCount() == 0) {
			echo "{}";
			return;
		}

		// reindex everything back into a tree-ish structure (yes, it's a tree, don't be fooled by the 
		// array syntax)
		$data = array();
		foreach ($programs as $p) {
			$pid = intval ($p->programid);
			$lineid = intval ($p->lineid);
			$lightid = intval ($p->lightid);

			$data[$pid]['name'] = $p->programname;
			$data[$pid]['programid'] = $pid;
			$data[$pid]['lines'][$lineid]['lineid'] = "$p->lineid" . "_" . "$pid";
			$data[$pid]['lines'][$lineid]['timeTrigger'] = $p->time_trigger;
			$data[$pid]['lines'][$lineid]['sensorTrigger'] = $p->sensor_trigger;
			$data[$pid]['lines'][$lineid]['lights'][$lightid]['lightid'] = "$lightid" . "_" . "$lineid" . "_" . "$pid";
			$data[$pid]['lines'][$lineid]['lights'][$lightid]['name'] = $p->lightname;
			$data[$pid]['lines'][$lineid]['lights'][$lightid]['brightness'] = $p->brightness;
		}

		// drill down to the data that was actually asked for
		// (though note, since the SQL query *was* limited earlier, this won't cause performance problems - 
		// this is just getting the structure right)
		// stay tuned to find out wtf $drillDepth does!
		$drillDepth = 0;
		if (!is_null ($program)) {
			$data = $data[$program];
			$drillDepth++;
		}
		if ($listwhat == "lines" || $listwhat == "lights") {
			$data = $data['lines'];
			$drillDepth++;
		}
		if (!is_null ($line)) {
			$data = $data[$line];
			$drillDepth++;
		}
		if ($listwhat == "lights") {
			$data = $data['lights'];
			$drillDepth++;
		}
		if (!is_null ($light)) {
			$data = $data[$light];
			$drillDepth++;
		}
		
		// somewhat wtf code follows, and you finally find out what that $drillDepth is for!
                // (though who am I kidding, this whole function is incredibly wtf)
		// This is needed for backbone-relational: It assumes that anything that's an
		// associative list is an object, and since we'd indexed things by ID before, well,
                // that *is* an associative list.
		// We need to convince backbone-relational that our lists actually are collections,
		// and to do that, they need to be sequential arrays here in PHP (so that 
		// json_encode will encode them as sequences rather than dictionaries). So we
		// drill back down into the structure once more and forget the indexes of 
		// everything. Every ID is stored with the objects already so we don't need to worry
		// about actually forgetting any information.

		if ($drillDepth == 4) {
			$data = array_values ($data);
		} else if ($drillDepth == 3) { 
			$data['lights'] = array_values($data['lights']);
		} else if ($drillDepth == 2) {
			foreach (array_keys ($data) as $lid) {
				$data[$lid]['lights'] = array_values ($data[$lid]['lights']);
			}
			$data = array_values($data);
		} else if ($drillDepth == 1) {
			foreach (array_keys ($data['lines']) as $lid) {
				$data['lines'][$lid]['lights'] = array_values ($data['lines'][$lid]['lights']);
			}
			$data['lines'] = array_values($data['lines']);
		} else if ($drillDepth == 0) {
			foreach (array_keys ($data) as $pid) {
				foreach (array_keys ($data[$pid]['lines']) as $lid) {
					$data[$pid]['lines'][$lid]['lights'] = array_values ($data[$pid]['lines'][$lid]['lights']);
				}
				$data[$pid]['lines'] = array_values ($data[$pid]['lines']);
			}
			$data = array_values($data);
		}
		
                echo json_encode ($data, JSON_PRETTY_PRINT);
	}

	
	catch(Exception $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
}

// jstree expects a rather specific kind of structure of the data you give it, this takes light
// objects and puts their attributes in the right places for jstree.
// argument: a light object (an object generated with PDO, in FETCH_ASSOC mode, of a row in the 
//                           'lights' table)
// returns: a representation of that object that jstree will understand once json_encode'd
function stuffLightAttributes ($element)
{
	$rv = array();
	foreach ($element as $k => $v) {
		if ($k == "parent") {}
		else if ($k == "isGroup") { $rv['attr']['rel'] = $v ? "group" : "light"; }
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
	$root['children'] = array();
	foreach ($list as $v) {
		if ($v['parent'] == $root['id']) {
			$root['children'][] = reconstructLightTree ($v, $list);
		}
	}
	return stuffLightAttributes ($root);
}

// transmit the lights in the database as a tree, formatted for jstree
// arguments: none
// returns: void
// echoes JSON data
function getLightsTree() {
	$sql = "select name, brightness, parent, id, isGroup from lights";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute();
		$lights = $stmt->fetchAll (PDO::FETCH_ASSOC);
		$tree = array();

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

// transmit the lights in the database as a straightforward list
// arguments: none
// returns: void
// echoes JSON data
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


// the updateGroup* functions are fairly straightforward:
// they get the group's ID
// read the requested thing to update from JSON in the request
// and put it in the database
function updateGroupBrightness($id) {
	$sql = "update lights set brightness=? where id=?";
	$requestBody = json_decode (Slim::getInstance()->request()->getBody());
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute(array ($requestBody->brightness, $id));

		echo "{}";

	}
	
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}

}

function updateGroupParent ($id) {
	$sql = "update lights set parent=? where id=?";
	$requestBody = json_decode (Slim::getInstance()->request()->getBody());
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute(array ($requestBody->parent, $id));

		echo "{}";

	}
	
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
}


function updateGroupName ($id)
{
	$sql = "update lights set name=? where id=?";
	$requestBody = json_decode (Slim::getInstance()->request()->getBody());
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute(array ($requestBody->name, $id));

		echo "{}";

	}
	
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
	
}

function removeGroup ($id)
{       // see T57
	if ($id == 1) {
		echo '{"error": {"text": "The root group can not be removed."}}';
		return;
	}

	$sql = "select isGroup from lights where id=?";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute (array ($id));
		$result = $stmt->fetchAll (PDO::FETCH_OBJ);
		if (!isset ($result[0])) {
			echo '{"error": {"text": No group with that id exists!"';
		}
		else if (! $result[0]->isGroup) {
			echo '{"error": {"text": "Lights can not be removed through this interface."}}';
		}
		else {
			$sql = "delete from lights where id=?";
			$stmt = $db->prepare($sql);
			$stmt->execute (array ($id));
			echo "{}";
		}
	}
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
}

function addGroup ()
{
	$sql = "insert into lights (name, brightness, parent, isGroup) values (?, ?, ?, ?)";
	$requestBody = json_decode (Slim::getInstance()->request()->getBody());
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute(array ($requestBody->name, null, 1, true));
		
		print ("{id: '" + $db->lastInsertId('id') + "'}");
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