<?php

class Files {

	public $db, $geo;

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

	public function moveImageFile($filename, $db, $geo) {

		$this->db  = $db;
		$this->geo = $geo;

		if (is_dir($filename)) {
			echo "It's dir ".$filename."<br>";
			return true;
		}

		$exif 			= @exif_read_data($filename);
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
			$suffix = $geo->getGeo($exif, $this->db);

		}
		// =================================================

		// =================================================
		if (!is_object($dt)) {
			echo $filename." - Major problem <br>";
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
