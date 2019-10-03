<?php

class Db {

	public $db;

	public function connect() {
		$this->db = new mysqli("127.0.0.1", "root", "", "geo") or 
			die("Connect failed: %s\n". $this->db->error);

		echo "Connected successfully<br>";
		//print_r($this->db);

		return true;
	}

	public function getData($latA, $lonA) {

		// Достаем в массив из БД
		// ============================================
		$query = "SELECT * FROM data 
			WHERE CAST(latA AS DECIMAL(15,3)) = CAST($latA AS DECIMAL(15,3))
			AND CAST(lonA AS DECIMAL(15,3)) = CAST($lonA AS DECIMAL(15,3))
			LIMIT 0,1";


		if ($result = $this->db->query($query) 
			AND $row = $result->fetch_assoc()
		) {
			return $row['address'];		
		}

		return NULL;
		//print_r($query);print_r($row);exit;	
		// ============================================

	}

	public function setData($latA, $lonA, $address, $response) {

		// Записываем данные в БД
		// ============================================
		$query = "
			INSERT INTO `geo`.`data` (`latA`, `lonA`, `address`, `response`) 
			VALUES ('".$latA."', '".$lonA."', '".$address."', '".$response."');
		";		
		$this->db->query($query);
		// ============================================
		
	}
	
}