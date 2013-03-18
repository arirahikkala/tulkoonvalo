<?php // -*- PHP -*-

require 'slim/Slim.php';

$app = new Slim();

$app->get('/data/:id/', function($id) { getObjectData($id); });
$app->get('/lights/:ids/', function($ids) { getLights($ids); });
$app->get('/newlights/:ids/', function($ids) { newGetLights($ids); });
$app->get('/children/:id/', function($id) { getChildren($id); });
$app->get('/allchildren/:id/', function($id) { getAllChildren($id); });
$app->get('/savesliders/:ids/:value/:timer', function($ids, $value, $timer) { saveSliders($ids, $value, $timer); });
$app->get('/poll/:ids/:values/:timers/:enableds', function($ids, $values, $timers, $enableds) { poll($ids, $values, $timers, $enableds); });
$app->get('/togglesliders/:ids/', function($ids) { toggleSliders($ids); });
$app->get('/lightstree/', 'getLightsTree');
$app->post('/lights', 'addGroup');
$app->get('/programs/', 'getPrograms');
$app->run();

function getPrograms() {
	// Get the programs first
	$sql = "select * from programs";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute();
		$programs = $stmt->fetchAll (PDO::FETCH_OBJ);
	}
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
	
	// TODO: Refactor this SQL mess?
	// Get times for each program
	$sql = "select * from program_times where program_id=?";
	foreach ($programs as $cProg) {
		$cid = $cProg->id;
		try {
			$db = getConnection();
			$stmt = $db->prepare($sql);
			$stmt->execute(array($cid));
			$times = $stmt->fetchAll (PDO::FETCH_OBJ);
			
			// Strip useless data
			foreach ($times as $cTime)
				unset($cTime->program_id);
				
			$cProg->times = $times;
		}
		catch(PDOException $e) {
			echo '{"error":{"text":'. $e->getMessage() .'}}';
		}
	}
	
	$sql = "select * from program_levels where program_id=?";
	foreach ($programs as $cProg) {
		$cid = $cProg->id;
		try {
			$db = getConnection();
			$stmt = $db->prepare($sql);
			$stmt->execute(array($cid));
			$levels = $stmt->fetchAll (PDO::FETCH_OBJ);
			
			// Strip useless data
			foreach ($levels as $cLevel)
				unset($cLevel->program_id);
				
			$cProg->levels = $levels;
		}
		catch(PDOException $e) {
			echo '{"error":{"text":'. $e->getMessage() .'}}';
		}
	}
	print(json_encode($programs));
}

function addGroup () {
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

function getLightsTree() {
	print("getlightstree");
}

function togglesliders($ids) {
	$ids_array = preg_split ("/,/", $ids);
	$sql = "delete from light_activations where id=?";
	
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		
		foreach ($ids_array as $cid) {
			$stmt->execute(array($cid));
		}
	}
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
}

// TODO: Should send parameters fancier way?
function poll($ids, $values, $timers, $enableds) {
	$ids_array = preg_split ("/,/", $ids);
	$values_array = preg_split( "/,/", $values);
	$timers_array = preg_split ( "/,/", $timers);
	$enableds_array = preg_split ( "/,/", $enableds);
	
	// TODO: Proper check for values (in other places too...) no empty values "" etc. ?
	if ((count($ids_array) != count($values_array)) ||
	(count($values_array) != count($timers_array)) ||
	(count($timers_array) != count($enableds_array)) )
		return;
		
	// TODO: IP check if necessary
	// Create the array of original values
	$origLevels = array();
	$counter = 0;
	foreach ($ids_array as $cid) {
		$origLevels[$cid] = array();
		
		$value = (int)$values_array[$counter];
		if ($value < 0)
			$value = 0;
		else if ($value > 100)
			$value = 100;
		$origLevels[$cid]["current_level"] = $value;
		
		$timer = (int)$timers_array[$counter];
		if ($timer < 0)
			$timer = 0;
		else if ($timer > 86400)
			$timer = 86400;
		$origLevels[$cid]["timer"] = $timer;
			
		$isEnabled = (int)$enableds_array[$counter];
		if ($isEnabled > 0)
			$isEnabled = false;
		else
			$isEnabled = true;	
		$origLevels[$cid]["enabled"] = $isEnabled;
		
		$counter++;
	}
	$time = time();
	
	// Loop 60 seconds at a time with small pauses in between
	while((time() - $time) < 60) {
		$newLevels = getLevels($ids_array);
		$retArray = array();

		// Original values doesn't match the new one, return new values
		foreach($newLevels as $id => $carray) {
			if (($origLevels[$id]["current_level"] != (int)$carray["current_level"]) ||
			($origLevels[$id]["timer"] != (int)$carray["timer"]) ||
			($origLevels[$id]["enabled"] != (int)$carray["enabled"]) )
				$retArray[$id] = $carray;
		}
		if ($retArray) {
			print(json_encode($retArray));
			break;
		}
		usleep(1000000);
	}
}

function getLevels($ids_array) {
	// TODO: This belongs elsewhere
	date_default_timezone_set('Europe/Helsinki');
	
	// TODO: Implode ids
	$sql = "select * from light_activations where id=?";
	$retArray = array();
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		
		foreach ($ids_array as $cid) {
			$stmt->execute(array($cid));
			$level = $stmt->fetch (PDO::FETCH_OBJ);
			
			try {
				$retArray[$level->id] = array();
				$retArray[$level->id]["current_level"] = (int)$level->current_level;
				$retArray[$level->id]["timer"] = (int)strtotime($level->ends_at)-(int)strtotime($level->activated_at);
				$retArray[$level->id]["enabled"] = true;
			}
			// TODO: Get real rule values for this and the ghost slider
			// Slider was turned off (hopefully)
			catch(Exception $e) {
				$retArray[$cid] = array();
				$retArray[$cid]["enabled"] = false;
				$retArray[$cid]["current_level"] = 0;
				$retArray[$cid]["timer"] = 0;
			}
		}
	}
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
	return($retArray);
}

// Save slider level
function saveSliders($ids, $value, $timer) {
	$currentTime = time();
	
	// Check timer 24h and "negative" limits
	if ($timer < 0) // TODO: What happens if this is sent?
		$timer = 0;
	else if ($timer > 86400)
		$timer = 86400;
	$endTime = $currentTime + $timer;
	
	// TODO: This int convert good enough?
	// Check the slider value
	$value = (int)$value;
	if ($value < 0)
		$value = 0;
	else if ($value > 100)
		$value = 100;
		
	$ids_array = preg_split ("/,/", $ids);
	
	// TODO: IP check here if wanted
	// TODO: Repeated SQL statements seem heavy. Implode IDs!

  // Insert the slider values into DB
	$sql = "insert into light_activations values (?,?,?,?) on duplicate key update current_level=?, activated_at=from_unixtime(?), ends_at=from_unixtime(?)";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		
		foreach ($ids_array as $cid) {
			$stmt->execute(array($value,$currentTime,$endTime,$cid,$value,$currentTime,$endTime));
			$stmt->execute();  // TODO: Another execute makes this work?
		}
		
	}
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
}

// Get light/group data and children
// TODO: Inexistent IDs
function getObjectData ($ids) {
	$ids_array = preg_split ("/,/", $ids);
	$retArray = array();
	
	// TODO: This belongs elsewhere (or just put timestamp in DB)
	// Used in converting SQL datetime into timestamp
	date_default_timezone_set('Europe/Helsinki');
	
	foreach ($ids_array as $id) {
		$lights = newGetLights($id);
		$lights = $lights[0]; // PHP version problem
		
		// Get the time remaining
		$timer = strtotime($lights -> ends_at)-time();
		if ($timer < 0)
			$timer = 0;
		$lights -> timer = $timer;
		
		$children = getChildren($id);
		$childrenIds = array();
		
		// TODO: Move id extract elsewhere
		if (count($children)) {
			// Extract children IDs
			foreach ($children as $child) {
				$childrenIds[] = $child->permanent_id;
			}
		}
		$lights -> children = $childrenIds;
		$lights -> all_children = getAllChildren($id);
		$lights -> ends_at = $lights->ends_at;
		$lights -> timer_full = strtotime($lights->ends_at)-strtotime($lights->activated_at);
		$retArray[] = $lights;
	}
	print(json_encode($retArray));
}

function newGetLights ($ids) {
	// TODO: Delimit ids by something safer
	$ids_array = preg_split ("/,/", $ids);

	// Get joined data from "lights" and "light_activations" tables
	$sql = "select * from lights left join light_activations on lights.permanent_id=light_activations.id where permanent_id=?";

	$lights=array();

	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);

		foreach ($ids_array as $x) {
			$stmt->execute(array($x));
			$lights = $stmt->fetchAll (PDO::FETCH_OBJ);
		}
		//TODO: If empty...
	}
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}

	return ($lights);
}

function getChildren ($id) {
	$sql = "select * from lights where permanent_id in (select child_id from groups where parent_id=?)";
	
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);

		$stmt->execute(array($id));
		$children = $stmt->fetchAll (PDO::FETCH_OBJ);
	}
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}

	return ($children);
}

function getAllChildren ($id) {
	$allChildren = array();
	$tempChildren[] = $id;

	// TODO: Should prevent endless cycle in group creation
	while (count($tempChildren) > 0) {
		$cid = array_pop($tempChildren);
		$cChildren = getChildren($cid);
		
		foreach ($cChildren as $c) {
			$allChildren[] = $c -> permanent_id;
			$tempChildren[] = $c -> permanent_id;
		}
	}
	return($allChildren);
}

function getLights ($ids) {
	$foo = array ("lights" => array (
			      array ("name" => "Aula Etu",
				     "id" => "1",
				     "isGroup" => false,
				     "level" => 50,
				     "activated_at" => 1360230859,
				      "ends_at" => 1893448800),
			      array ("name" => "Aula Taka",
				     "id" => "2",
				     "isGroup" => false),
			      array ("name" => "Aula",
				     "id" => "3",
				     "isGroup  p" => true),
			      array ("name" => "Eteinen",
				     "id" => "4",
				     "isGroup" => true,
				     "level" => 100,
				     "activated_at" => 1360230883,
				     "ends_at" => 1893448800),
			      array ("name" => "Ulkovalo",
				     "id" => "5",
				     "isGroup" => false)),
		      "groups" => array (
			      array ("p" => "3",
				     "c" => "1"),
			      array ("p" => "3",
				     "c" => "2"),
			      array ("p" => "4",
				     "c" => "5")));

	// TODO: Delimit ids by something safer (in other places too)
	$ids_array = preg_split ("/,/", $ids);

	$bar = array();

	foreach ($ids_array as $x) {
		if ($x == "3") {
			$bar[] = "2";
			$bar[] = "1";
		}
		if ($x == "4") {
			$bar[] = "5";
		}
		$bar[] = $x;
	}
	$bar = array_unique ($bar);

	$rv = array ("lights" => array (), "groups" => array ());
	foreach ($bar as $i) {
		foreach ($foo["lights"] as $light) {
			if ($light["id"] == $i) {
				$rv["lights"][] = $light;
			}
		}
		foreach ($foo["groups"] as $group) {
			if ($group["p"] == $i)
				$rv["groups"][] = array ("p" => $i, "c" => $group["c"]);
		}
	}
	print (json_encode ($rv));
}


/*
function getSliders() {
	$sql = "select id, value from sliders_test";
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

function getSlider($id) {
	$sql = "select value from sliders_test where id=?";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute(array ($id));
		$lights = $stmt->fetch (PDO::FETCH_OBJ);

		echo json_encode ($lights, JSON_PRETTY_PRINT);
	}
	
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
}

function updateSlider ($id) {
	$sql = "update sliders_test set value=? where id=?";
	$requestBody = json_decode (Slim::getInstance()->request()->getBody());
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute(array ($requestBody->value, $id));

		echo "{}";

	}
	
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}

}

function createNewSlider () {
	$sql = "insert into sliders_test (value) values (?)";
	$requestBody = json_decode (Slim::getInstance()->request()->getBody());
	$value = $requestBody->value;

	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->execute(array ($value));

		$id = $db->lastInsertId();
		echo "{id: $id, value: $value}";

	}
	
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
}
*/
function getConnection() {
	require ("config.php");
	$dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);	
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	return $dbh;
}

?>
