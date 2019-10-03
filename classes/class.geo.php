<?php

class Geo {

public $geoData = array();
public $count;
public $db;

public function getYandex($lat, $lon) {
	
	$latA = round($lat, 3);
	$lonA = round($lon, 3);

	// Получаем данные из БД
	// ============================
	$this->geoData[$lonA.",".$latA] = $this->db->getData($latA, $lonA);

	if (!isset($this->geoData[$lonA.",".$latA]) AND
			$this->geoData[$lonA.",".$latA]!=NULL
	) {
	
		$url = "https://geocode-maps.yandex.ru/1.x/";
		$apikey = require('../config/apikey.php');

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
		$this->db->setData($latA, $lonA, $address, $response);

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


public function getGeo($exif, $db) {

		$this->db = $db;

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

}


