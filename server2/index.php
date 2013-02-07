<?php

require 'slim/Slim.php';

$app = new Slim();
/*
$app->get('/sliders/', function() { getSliders(); });
$app->get('/sliders/:id/', function($id) { getSlider($id); });
$app->put('/sliders/:id/', function($id) { updateSlider($id); });
$app->post('/sliders/', function() { createNewSlider(); });
*/

$app->get('/lights/:ids/', function($ids) { getLights($ids); });
$app->run();

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
				     "isGroup" => true),
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

	// TODO: Delimit ids by something safer
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
	$dbh = new PDO("mysql:host=localhost;dbname=webdali", "ari", "foo");	
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	return $dbh;
}

?>
