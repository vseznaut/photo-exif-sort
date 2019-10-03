<?php


set_time_limit(0);

class photo {

public $geoData = array();
public $db;
public $count;

public function __construct() {

}


public function connect() {
	$this->db = new mysqli("127.0.0.1", "root", "", "geo") or 
		die("Connect failed: %s\n". $this->db->error);

	echo "Connected successfully<br>";
	//print_r($this->db);

	return true;
}


public function getYandex($lat, $lon) {
	
	$latA = round($lat, 3);
	$lonA = round($lon, 3);

	// Достаем в массив из БД
	// ============================================
	$query = "SELECT * FROM data 
		WHERE CAST(latA AS DECIMAL(15,3)) = CAST($latA AS DECIMAL(15,3))
		AND CAST(lonA AS DECIMAL(15,3)) = CAST($lonA AS DECIMAL(15,3))
		LIMIT 0,1";

	

	if ($result = $this->db->query($query) 
		AND $row = $result->fetch_assoc()
	) {

		$this->geoData[$lonA.",".$latA] = $row['address'];
	}
	//print_r($query);print_r($row);exit;	
	// ============================================


	if (!isset($this->geoData[$lonA.",".$latA])) {
	
		$url = "https://geocode-maps.yandex.ru/1.x/";
		$apikey = require('apikey.php');

		$json = array(
		  'geocode' => $lon.",".$lat,
		  'kind' => 'locality',
		  'apikey' => $apikey,
		  'results' =>'1',
		  'skip' => '0',
		  'format' => 'json'
		);

		$response = file_get_contents($url."?".http_build_query($json));
		//$decoded = json_decode($response);

		
		$decoded = json_decode($response);
		$address = @$decoded->response->GeoObjectCollection->featureMember[0]->GeoObject->metaDataProperty->GeocoderMetaData->text;

		$this->geoData[$lonA.",".$latA] = $address;

		// Записываем данные в БД
		// ============================================
		$query = "
			INSERT INTO `geo`.`data` (`latA`, `lonA`, `address`, `response`) 
			VALUES ('".$latA."', '".$lonA."', '".$address."', '".$response."');
		";		
		$this->db->query($query);
		// ============================================

		echo "<b>NEW YANDEX QUERY!!!</b><br>";
		$this->count++;

		if ($this->count>3000) {
			echo "<b>3000 QUERIES!!!</b><br>";
			exit;
		}
	}

	//$decoded = json_decode($this->geoData[$lonA.",".$latA]);
	//echo("<pre>");print_r($this->geoData[$lonA.",".$latA]);exit;

	return $this->geoData[$lonA.",".$latA];
}


public function getGps($exifCoord, $hemi) {

    $degrees = count($exifCoord) > 0 ? $this->gps2Num($exifCoord[0]) : 0;
    $minutes = count($exifCoord) > 1 ? $this->gps2Num($exifCoord[1]) : 0;
    $seconds = count($exifCoord) > 2 ? $this->gps2Num($exifCoord[2]) : 0;

    $flip = ($hemi == 'W' or $hemi == 'S') ? -1 : 1;

    return $flip * ($degrees + $minutes / 60 + $seconds / 3600);

}

public function gps2Num($coordPart) {

    $parts = explode('/', $coordPart);

    if (count($parts) <= 0)
        return 0;

    if (count($parts) == 1)
        return $parts[0];

    return floatval($parts[0]) / floatval($parts[1]);
}


public function getGeo($exif) {

		if (is_array($exif["GPSLongitude"]) AND is_array($exif["GPSLatitude"])) {
			$lon = $this->getGps($exif["GPSLongitude"], $exif['GPSLongitudeRef']);
			$lat = $this->getGps($exif["GPSLatitude"], $exif['GPSLatitudeRef']);
			var_dump($lat, $lon);
			echo "<br>";
			return $this->getYandex($lat, $lon);
		}
		//echo("<pre>");print_r($exif);exit;

		return null;	
}


public function getDirContents($dir, &$results = array()){
    $files = scandir($dir);

    foreach($files as $key => $value){
        $path = realpath($dir.DIRECTORY_SEPARATOR.$value);
        if(!is_dir($path)) {
            $results[] = $path;
        } else if($value != "." && $value != "..") {
            $this->getDirContents($path, $results);
            $results[] = $path;
        }
    }

    return $results;
}

public function moveAndTouch($filename, $full_path, $dt, $is_exif) {
		rename($filename, $full_path);
		touch($full_path, $dt->getTimestamp());

		$full_path = mb_convert_encoding($full_path, "utf-8", "windows-1251");

		if ($is_exif) { 
			echo "EXIF - ";
		} else {
			echo "NO DATA - ";
		}
		echo $full_path,"<br>";

		return true;
}

public function moveImageFile($filename) {

	if (is_dir($filename)) {
		echo "It's dir ".$filename."<br>";
		return true;
	}


	$exif = @exif_read_data($filename);
	$path_parts = pathinfo($filename);
	unset($suffix);

	// Если нет EXIF
	// =================================================
	if (
			($path_parts['extension']=='mov') OR
		  ($path_parts['extension']=='mp4') OR
		  ($path_parts['extension']=='avi')
	) {
		
		$dt = new DateTime();
		$dt->setTimestamp(filectime($filename));

		$start_path = "D:\photos\\video_by_date";
		$is_exif = false;

	} else if (
			($path_parts['extension']=='psd') OR
		  ($path_parts['extension']=='tiff') OR
		  ($path_parts['extension']=='tif')
	) {
		
		$dt = new DateTime();
		$dt->setTimestamp(filectime($filename));

		$start_path = "D:\photos\photos_tiff";
		$is_exif = false;


	} else if (!is_array($exif) OR 
		!isset($exif['DateTime']) OR
		!DateTime::createFromFormat('Y:m:d H:i:s', $exif['DateTime'])
	) {

		$dt = new DateTime();
		$dt->setTimestamp(filectime($filename));

		$start_path = "D:\photos\photos_without_exif";
		$is_exif = false;
	

	} else {

		$dt = DateTime::createFromFormat('Y:m:d H:i:s', $exif['DateTime']);	
		$start_path = "D:\photos\photos_with_exif";
		$is_exif = true;	
		$suffix = $this->getGeo($exif);

	}
	// =================================================

	// =================================================
	if (!is_object($dt)) {
		echo $filename." - Some problem <br>";
		echo "<pre>";
		print_r($exif);
		exit;
	}
	// =================================================

	$year = $start_path."\Year".$dt->format('Y');
	if (!is_dir($year)) mkdir($year);

	$month = $year."\\".$dt->format('Y-m-F');
	if (!is_dir($month)) mkdir($month);

	// Если есть география
	// ================================================
	if (isset($suffix) AND $suffix!=NULL) {
		//$suffix = preg_replace( '/[^a-zа-я0-9\,]+/', '-', strtolower($suffix));
		$suffix = mb_convert_encoding($suffix, "windows-1251", "utf-8");
		$path = $month."\\".$dt->format('Y-m-d')."-".$suffix;
		if (!is_dir($path)) mkdir($path);
		
	} else {
		$path = $month."\\".$dt->format('Y-m-d');
		if (!is_dir($path)) mkdir($path);
	}

	

	$full_path = $this->getUniqueFilename($filename, $path, $dt, 0);
	$this->moveAndTouch($filename, $full_path, $dt, $is_exif);
	return true;

}

public function getUniqueFilename($filename, $path, $dt, $prefix=0) {

	$path_parts = pathinfo($filename);


	$full_path = 
		$path."\\".$dt->format('Y-m-d-H-i-s-')
		.str_pad($prefix, 2, '0', STR_PAD_LEFT)
		."."
		.strtolower($path_parts['extension']);

	// Уходим в глубину итераций
	// ==========================
	if (file_exists($full_path)) {

		// Если файлы одинаковые
		// ===============================================
		if (file_exists($filename) AND 
				file_exists($full_path) AND 
			  (md5_file($filename) == md5_file($full_path))
		) {
			echo "<br><b>".$filename." SAME AS ".$full_path."</b><br>";
			return $full_path;
		
		} else {
			return $this->getUniqueFilename($filename, $path, $dt, $prefix+1);	
		}
	
	// Если файл не существует возвращаем
	// ==========================================================
	} else {
		return $full_path;
	}

}
}

$photo = new Photo();
$photo->connect();

$file_list = $photo->getDirContents('D:\photos\photos_unsorted');


foreach ($file_list as $key => $value) {

	$photo->moveImageFile($value);

}

exit;
