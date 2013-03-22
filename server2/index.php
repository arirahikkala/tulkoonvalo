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
$app->post('/programs/', 'savePrograms');
$app->put('/programs/:id', function($id) { updateProgram($id); });
$app->delete('/programs/:id', function($id) { deleteProgram($id); });

$app->run();

// Convenience DB function for INSERT, UPDATE and DELETE statements
function dbUpdate($db, $sql, $dbArray) {
	try {
		$stmt = $db->prepare($sql);
		$stmt->execute($dbArray);
	}
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
		return($e->getMessage());
	}
}

function deleteTime($id) {
	$db = getConnection();
	
	$sql = "delete from program_times where id=?";	
	dbUpdate($db, $sql, array($id));
}

function deleteLevel($id) {
	$db = getConnection();
	
	$sql = "delete from program_levels where id=?";	
	dbUpdate($db, $sql, array($id));
}

function deleteProgram($id) {
	$db = getConnection();
	
	$sql = "delete from programs where id=?";	
	dbUpdate($db, $sql, array($id));
	
	$sql = "delete from program_times where program_id=?";	
	dbUpdate($db, $sql, array($id));
	
	$sql = "delete from program_levels where program_id=?";	
	dbUpdate($db, $sql, array($id));
}

function updateProgram($id) {
	$params = json_decode(Slim::getInstance()->request()->getBody());
	$retArray = array();
	
	// TODO: Parameter validity/safety conv. function
	$db = getConnection();
	
	$sql = "update programs set name=? where id=?";	
	dbUpdate($db, $sql, array($params->name, $params->id));
	print(json_encode($params));
	foreach ($params->times as $time) {
		// TODO: Need time removing
		// TODO: Make inserted new_* value into false when OK response
		// TODO: new_* name sucks, replace with new_item, try creating it in js too instead here (event type in adding)
		
		if ($time->allow_delete)
			deleteTime($id);
		
		else if ($time->new_time) {
			$sql = "insert into program_times values (null,?,?,?,?,?,?)";	
			dbUpdate($db, $sql, array(
					$id, $time->date_start, 
					$time->date_end, $time->weekdays,
					$time->time_start, $time->time_end)
			);
		}
		else {
			$sql = "update program_times set date_start=?, date_end=?, weekdays=?, time_start=?, time_end=? where id=?";	
			dbUpdate($db, $sql, array(
					$time->date_start, 
					$time->date_end, $time->weekdays,
					$time->time_start, $time->time_end,
					$time->id)
			);
		}
	}

	foreach ($params->levels as $level) {
		// TODO: Need level removing

		if ($time->allow_delete)
			deleteLevel($id);
			
		else if ($level->new_level) {
			$sql = "insert into program_levels values (null,?,?,?,?,?,?)";	
			dbUpdate($db, $sql, array(
					$id, $level->target_id, 
					$level->light_detector, $level->motion_detector,
					$level->light_level, $level->motion_level)
			);
		}
		//| id | program_id | target_id | light_detector | motion_detector | light_level | motion_level 
		else {
			$sql = "update program_levels set program_id=?, light_detector=?, motion_detector=?, light_level=?, motion_level=? where id=?";	
			dbUpdate($db, $sql, array(
					$level->target_id, 
					$level->light_detector, $level->motion_detector,
					$level->light_level, $level->motion_level,
					$level->id)
			);
		}
	}
	if ($retArray) print($retArray);
}

function programValidation($params) {
	$errorMain = array();
	$errorTimes = array();
	$errorLevels = array();
	$retArray = array();
	
	$times = $params->times; // Must not be empty
	$levels = $params->levels; // Must not be empty
	
	$nameLen = strlen($params->name);
	
	// TODO: Form needs testing with lengths etc.
	if ($nameLen == 0 || $nameLen > 32)
		$errorMain["name"] = 0;
		
	if (count($times) == 0)
		$errorMain["times"] = 3;
		
	if (count($levels) == 0)
		$errorMain["levels"] = 4;
		
	// TODO: Additional sanitation here for all values
	// TODO: not same target_id allowed in levels in one program
	
		
	foreach ($params->times as $time) {
	
		if ($time->weekdays == "0000000")
			$errorTimes[$time->cid][] = 1;
			
		$timeFormatError = false;
		
		// Check time format
		foreach(array($time->time_start, $time->time_end) as $t) {
			// Check that the time format is correct
			if (strlen($t) != 5 || (int)substr($t,2,1) != ':') {
				$errorTimes[$time->cid][] = 2;
				break;
			}
			// Check each halve of times
			$hours = true;
			foreach (array((int)substr($t,0,2), (int)substr($t,3,4)) as $tHalf) {
				if (!$hours && ($tHalf > 59 || $tHalf < 0)) {
					$errorTimes[$time->cid][] = 2;
					break;
				}
				else if ($hours && ($tHalf > 23 || $tHalf < 0)) {
					$errorTimes[$time->cid][] = 2;
					break;
				}
				$hours = false;
			}
		}
		
		if (!$timeFormatError && strtotime($time->time_start) > strtotime($time->time_end))
			$errorTimes[$time->cid][] = 5;
	}
	
	// Don't allow same group to be set in sliders multiple times
	$usedLevels = array();
	foreach ($params->levels as $level) {
		$usedLevels[$level->target_id][] = $level->cid;
	}
	foreach ($usedLevels as $group) {
		if (count($group) > 1) {
			foreach ($group as $cid)
				$errorLevels[$cid][] = 6;
		}
	}
	
 	if (count($errorMain) || count($errorTimes) || count($errorLevels)) {
 		$retArray["main_errors"] = $errorMain;
  	$retArray["time_errors"] = $errorTimes;
 		$retArray["level_errors"] = $errorLevels;
 		return($retArray);
 	}
}

// TODO: Use this for editing. If ID is not null update value
function savePrograms() {
	$params = json_decode(Slim::getInstance()->request()->getBody());
	// If errors found in form return
	$retArray = programValidation($params);	
	if ($retArray) {
		Slim::getInstance()->response()->status(400);
		print(json_encode($retArray));
		return;
	}
	print("checking…");
	checkProgramsOverlap($params);
	$times = $params->times;
	$levels = $params->levels;
	
	$db = getConnection();
	
	$sql = "insert into programs values (null,?)";
	dbUpdate($db, $sql, array($params->name));
	$programID = $db->lastInsertId();
	
	// TODO: These common statements could be put into their own functions
	$sql = "insert into program_times values (null,?,?,?,?,?,?)";
	foreach ($params->times as $time) {
			
		dbUpdate($db, $sql, array(
				$programID, $time->date_start, 
				$time->date_end, $time->weekdays,
				$time->time_start, $time->time_end)
		);
	}
	
	$sql = "insert into program_levels values (null,?,?,?,?,?,?)";	
	foreach ($params->levels as $level) {
		dbUpdate($db, $sql, array(
				$programID, $level->target_id, 
				$level->light_detector, $level->motion_detector,
				$level->light_level, $level->motion_level)
		);
	}
	print($programID);
	// TODO: Return to main page with a success message
	// TODO: Fix being unable to edit from other than mainpage tab (e.g. when reload page)
}

function getPrograms($retJson = true) {

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
			$stmt = $db->prepare($sql);
			$stmt->execute(array($cid));
			$times = $stmt->fetchAll (PDO::FETCH_OBJ);
			
			foreach ($times as $cTime) {
				$cTime->new_time = false;
				unset($cTime->program_id);
			}
			
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
			$stmt = $db->prepare($sql);
			$stmt->execute(array($cid));
			$levels = $stmt->fetchAll (PDO::FETCH_OBJ);
			
			// Strip useless data
			foreach ($levels as $cLevel) {
				$cLevel->new_level = false;
				unset($cLevel->program_id);
			}
				
			$cProg->levels = $levels;
		}
		catch(PDOException $e) {
			echo '{"error":{"text":'. $e->getMessage() .'}}';
		}
	}
	if ($retJson){
		print(json_encode($programs));
	}else{
		return($programs);
	}
}

function checkProgramsOverlap ($target) {
	$prog=getPrograms(false);
	$tTimes = $target->times;
	//$tTimes = $prog[count($prog)-1]->times;

	for ($i=1; $i<count($prog); $i++) {
		$times = $prog[$i]->times;
		foreach ($times as $time){
			foreach ($tTimes as $tTime) {
				$weekdays=$tTime->weekdays + $time->weekdays + 30000000;	//30000000 for not losing zeros at the beginning
				if(stripos($weekdays, "2")!==false) {
					if ((strtotime($time->time_start) <= strtotime($tTime->time_start)) && 
					(strtotime($tTime->time_start) < strtotime($time->time_end))) {
						if((strtotime($time->time_start) < strtotime($tTime->time_end)) && 
						(strtotime($tTime->time_end) <= strtotime($time->time_end))) {
							$levels_array=checkLights ($target->levels, $prog[$i]->levels);
							if (!empty($levels_array)) 
								modifyProgram($tTime, $time, $weekdays, $levels_array, 1);
						}else{
							$levels_array=checkLights ($target->levels, $prog[$i]->levels);
							if (!empty($levels_array))
								modifyProgram($tTime, $time, $weekdays, $levels_array, 2);
						}
					}else if((strtotime($time->time_start) < strtotime($tTime->time_end)) && 
					(strtotime($tTime->time_end) <= strtotime($time->time_end))) {
						$levels_array=checkLights ($target->levels, $prog[$i]->levels);
						if (!empty($levels_array))
							modifyProgram($tTime, $time, $weekdays, $levels_array, 3);
					}else if((strtotime($tTime->time_start) < strtotime($time->time_start)) && 
					(strtotime($time->time_end) < strtotime($tTime->time_end))) {
						$levels_array=checkLights ($target->levels, $prog[$i]->levels);
						if (!empty($levels_array))
							modifyProgram($tTime, $time, $weekdays, $levels_array, 4);
					}
				}
			}
		}
	}
	return(true);
}

function checkLights ($levels1, $levels2) {
	$levels_array=array();

	foreach ($levels1 as $level1) {
		$id1=$level1->target_id;
		foreach ($levels2 as $level2){
			$id2=$level2->target_id;
			if ($id1==$id2) {
				$matching_levels=array($level1, $level2);
				array_push($levels_array, $matching_levels);
			}	
		}
	} 
	return ($levels_array);
}

function modifyProgram ($time1, $time2, $weekdays, $levels_array, $type) {
	$modifiedPrograms=array();	
	
	$days_together = array("1" , "2", "3");
	$days_overlap = array("0", "1", "");	//remove previously added 3
	$days_others = array("1", "0", "");
	$overlapDays = str_replace($days_together, $days_overlap, $weekdays);
	$otherDays1=str_replace($days_together, $days_others, ($time1->weekdays + $overlapDays + 30000000));
	$otherDays2=str_replace($days_together, $days_others, ($time2->weekdays + $overlapDays + 30000000));

	$brighters1=array($time1);
	$brighters2=array($time2);

	switch($type){
		//time1 completely inside time2
		case 1:
			$dimmers1_overlap_days=array("time_start" => $time2->time_start, 
			"time_end" => $time1->time_start, "weekdays" => $overlapDays);
			$dimmers2_overlap_days=array("time_start" => $time1->time_end, 
			"time_end" => $time2->time_end, "weekdays" => $overlapDays);
			break;
		//time1's starting time inside time2
		case 2:
			$dimmers1_overlap_days=array("time_start" => $time2->time_end, 
			"time_end" => $time1->time_end, "weekdays" => $overlapDays);
			$dimmers2_overlap_days=array("time_start" => $time2->time_start, 
			"time_end" => $time1->time_start, "weekdays" => $overlapDays);
			break;
		//time1's ending time inside time2
		case 3:
			$dimmers1_overlap_days=array("time_start" => $time1->time_start, 
			"time_end" => $time2->time_start, "weekdays" => $overlapDays);
			$dimmers2_overlap_days=array("time_start" => $time1->time_end, 
			"time_end" => $time2->time_end, "weekdays" => $overlapDays);
			break;
		//time2 completely inside time1
		case 4:
			$dimmers1_overlap_days=array("time_start" => $time1->time_start, 
			"time_end" => $time2->time_start, "weekdays" => $overlapDays);
			$dimmers2_overlap_days=array("time_start" => $time2->time_end, 
			"time_end" => $time1->time_end, "weekdays" => $overlapDays);
			break;
	}
	$dimmers1_other_days=array("time_start" => $time1->time_start, "time_end" => $time1->time_end, "weekdays" => $otherDays1);
	$dimmers2_other_days=array("time_start" => $time2->time_start, "time_end" => $time2->time_end, "weekdays" => $otherDays2);
	$levelToCompare="light_level";
	

	foreach ($levels_array as $level_pair){
		//If there's motion detector on in one of these two, we'll compare that.
		if (($level_pair[0]->motion_detector === true) || 
		($level_pair[1]->motion_detector === true))
			$levelToCompare="motion_level";

		switch($type){
			case 1:
				if ($level_pair[0]->$levelToCompare >= $level_pair[1]->$levelToCompare){
					array_push($brighters1, $level_pair[0]);
					array_push($dimmers1_overlap_days, $level_pair[1]);	//Not really dimmers of pair number 1, just using it
					array_push($dimmers2_overlap_days, $level_pair[1]);
					array_push($dimmers2_other_days, $level_pair[1]);
				}else{
					array_push($brighters2, $level_pair[1]);
					array_push($dimmers1_other_days, $level_pair[0]);
				}
				break;
			case 2: case 3:	
				if ($level_pair[0]->$levelToCompare >= $level_pair[1]->$levelToCompare){
					array_push($brighters1, $level_pair[0]);
					array_push($dimmers2_overlap_days, $level_pair[1]);
					array_push($dimmers2_other_days, $level_pair[1]);
				}else{
					array_push($brighters2, $level_pair[1]);
					array_push($dimmers1_overlap_days, $level_pair[0]);
					array_push($dimmers1_other_days, $level_pair[0]);
				}
				break;
			case 4:
				if ($level_pair[0]->$levelToCompare >= $level_pair[1]->$levelToCompare){
					array_push($brighters1, $level_pair[0]);
					array_push($dimmers2_other_days, $level_pair[1]);
				}else{
					array_push($brighters2, $level_pair[1]);
					array_push($dimmers1_overlap_days, $level_pair[0]);
					array_push($dimmers2_overlap_days, $level_pair[0]);	//Not really dimmers of pair number 2, just using it
					array_push($dimmers2_other_days, $level_pair[0]);
				}
				break;
		}
	}

	array_push($modifiedPrograms, $brighters1, $brighters2, $dimmers1_overlap_days, $dimmers2_overlap_days, $dimmers1_other_days, $dimmers2_other_days);
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

function getConnection() {
	require ("config.php");
	$dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);	
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	return $dbh;
}

?>
