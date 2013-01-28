<?php

require 'slim/Slim.php';

$app = new Slim();

$app->get('/sliders/', function() { getSliders(); });
$app->get('/sliders/:id/', function($id) { getSlider($id); });

$app->run();

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
		$lights = $stmt->fetchAll (PDO::FETCH_OBJ);

		echo json_encode ($lights, JSON_PRETTY_PRINT);
	}
	
	catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
}

function getConnection() {
	$dbh = new PDO("mysql:host=localhost;dbname=webdali", "ari", "foo");	
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	return $dbh;
}


?>