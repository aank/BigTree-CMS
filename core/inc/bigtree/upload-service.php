<?
	/*
		Class: BigTreeUploadService
			Controls where files are uploaded (local and cloud storage)
	*/
	
	class BigTreeUploadService {
		
		var $Service = "";
		var $S3,$S3Buckets,$S3BucketData;
		var $RSAuth,$RSConn,$RSContainers,$RSContainerData;
		
		/*
			Constructor:
				Retrieves the current desired service and image processing availability.
		*/
		
		function __construct() {
			global $cms;
			$ups = $cms->getSetting("bigtree-internal-upload-service");
			// If for some reason the setting doesn't exist, make one.
			if (!is_array($ups) || !$ups["service"]) {
				$this->Service = "local";
				$this->optipng = false;
				$this->jpegtran = false;
				sqlquery("INSERT INTO bigtree_settings (`id`,`system`) VALUES ('bigtree-internal-upload-service','on')");
			} else {
				$this->Service = $ups["service"];
				$this->optipng = $ups["optipng"];
				$this->jpegtran = $ups["jpegtran"];
			}
		}
		
		/*
			Function: delete
				Deletes a file from the active upload service.
			
			Parameters:
				file_location - The URL of the file.
		*/
		
		function delete($file_location) {
			if ($this->Service == "local") {
				return $this->deleteLocal($file_location);
			} elseif ($this->Service == "s3") {
				return $this->deleteS3($file_location);
			} elseif ($this->Service == "rackspace") {
				return $this->deleteRackspace($file_location);
			} else {
				die("BigTree Critical Error: Unknown Upload Service In Effect");
			}
		}
		
		/*
			Function: deleteLocal
				Private function for the delete call when local storage is active.
			
			See Also:
				<delete>
		*/
		
		private function deleteLocal($file_location) {
			unlink(str_replace("{wwwroot}",$GLOBALS["site_root"],$file_location));
		}
		
		/*
			Function: deleteS3
				Private function for the delete call when Amazon S3 is active.
			
			See Also:
				<delete>
		*/
		
		private function deleteS3($file_location) {
			global $cms;
			
			if (!$this->S3) {
				$keys = $cms->getSetting("bigtree-internal-s3-keys");
				$this->S3 = new S3($keys["access_key_id"],$keys["secret_access_key"]);
			}
			
			$bucket = substr($file_location,7,strpos($file_location,".")-7);
			
			$file_location = strrev($file_location);
			$file = strrev(substr($file_location,0,strpos($file_location,"/")));
			
			$this->S3->deleteObject($bucket,$file);
		}
		
		/*
			Function: deleteRackspace
				Private function for the delete call when Rackspace Cloud Files is active.
			
			See Also:
				<delete>
		*/
		
		private function deleteRackspace($file_location) {
			global $cms;
			
			if (!$this->Rackspace) {
				$keys = $cms->getSetting("bigtree-internal-rackspace-keys");
				$this->RSAuth = new CF_Authentication($keys["username"],$keys["api_key"]);
				$this->RSAuth->authenticate();
				$this->RSConn = new CF_Connection($this->RSAuth);
			}
			
			if (!$this->RSContainers) {
				$this->RSContainers = $cms->getSetting("bigtree-internal-rackspace-containers");
			}
			
			$parts = safe_pathinfo($file_location);
			
			foreach ($this->RSContainers as $key => $val) {
				if ($val == $parts["dirname"]) {
					$path = $key;
				}
			}
			
			$container = $this->RSConn->get_container($path);
			$container->delete_object($parts["basename"]);
		}
		
		/*
			Function: replace
				Uploads a file to the current upload service and replaces any existing file with the same file_name.
			
			Parameters:
				local_file - The absolute path to the local file you wish to upload.
				file_name - The file name at the upload end point.
				relative_path - The directory to store the file in (for local files, also used to generate a bucket ID).
				remove_original - Whether to delete the local_file or not.
			
			Returns:
				The URL to the uploaded file.
		*/
		
		function replace($local_file,$file_name,$relative_path,$remove_original = true) {
			if ($this->Service == "local") {
				return $this->replaceLocal($local_file,$file_name,$relative_path,$remove_original);
			} elseif ($this->Service == "s3") {
				return $this->replaceS3($local_file,$file_name,$relative_path,$remove_original);
			} elseif ($this->Service == "rackspace") {
				return $this->replaceRackspace($local_file,$file_name,$relative_path,$remove_original);
			} else {
				die("BigTree Critical Error: Unknown Upload Service In Effect");
			}
		}
		
		/*
			Function: replaceLocal
				Private function for the replace call when local storage is active.
			
			See Also:
				<replace>
		*/
		
		private function replaceLocal($local_file,$file_name,$relative_path,$remove_original) {
			if ($remove_original) {	
				$success = bigtree_move($local_file,$GLOBALS["site_root"].$relative_path.$file_name);
			} else {
				$success = bigtree_copy($local_file,$GLOBALS["site_root"].$relative_path.$file_name);
			}
			
			if ($success) {
				return "{wwwroot}".$relative_path.$file_name;
			} else {
				return false;
			}
		}
		
		/*
			Function: replaceS3
				Private function for the replace call when Amazon S3 is active.
			
			See Also:
				<replace>
		*/
		
		private function replaceS3($local_file,$file_name,$relative_path,$remove_original) {
			global $cms;
			
			if (!$this->S3) {
				$keys = $cms->getSetting("bigtree-internal-s3-keys");
				$this->S3 = new S3($keys["access_key_id"],$keys["secret_access_key"]);
			}
			
			if (!$this->S3Buckets) {
				$this->S3Buckets = $cms->getSetting("bigtree-internal-s3-buckets");
			}
			
			// If we don't have a bucket for this path yet, make one.
			$bucket = $this->S3Buckets[$relative_path];
			if (!$bucket) {
				$bucket = uniqid("bigtree-");
				$this->S3Buckets[$relative_path] = $bucket;
				$this->S3->putBucket($bucket,S3::ACL_PUBLIC_READ);
				$admin = new BigTreeAdmin;
				$admin->updateSettingValue("bigtree-internal-s3-buckets",$this->S3Buckets);
			}
			
			$this->S3->putObjectFile($local_file,$this->S3Buckets[$relative_path],$file_name,S3::ACL_PUBLIC_READ);
			
			// Update the list of files in this bucket locally.
			$existing_files[$file_name] = true;
			$this->S3BucketData[$bucket] = $existing_files;
			
			// Remove the original file we were uploading.
			if ($remove_original) {
				unlink($local_file);
			}
			
			return "http://$bucket.s3.amazonaws.com/$file_name";
		}
		
		/*
			Function: replaceRackspace
				Private function for the replace call when Rackspace Cloud Files is active.
			
			See Also:
				<replace>
		*/
		
		private function replaceRackspace($local_file,$file_name,$relative_path,$remove_original) {
			global $cms;
			
			if (!$this->Rackspace) {
				$keys = $cms->getSetting("bigtree-internal-rackspace-keys");
				$this->RSAuth = new CF_Authentication($keys["username"],$keys["api_key"]);
				$this->RSAuth->authenticate();
				$this->RSConn = new CF_Connection($this->RSAuth);
			}
			
			if (!$this->RSContainers) {
				$this->RSContainers = $cms->getSetting("bigtree-internal-rackspace-containers");
			}
			
			$relative_path = str_replace("/","-",rtrim($relative_path,"/"));
			
			// If we don't have a bucket for this path yet, make one.
			$url = $this->RSContainers[$relative_path];
			if (!$url) {
				$container = $this->RSConn->create_container($relative_path);
				$url = $container->make_public();

				$this->RSContainers[$relative_path] = $url;
				$admin = new BigTreeAdmin;
				$admin->updateSettingValue("bigtree-internal-rackspace-containers",$this->RSContainers);
			} else {
				$container = $this->RSConn->get_container($relative_path);
			}
			
			// Create the object
			$object = $container->create_object($file_name);
			$object->load_from_filename($local_file);
			
			// Update the list of files in this container locally.
			$existing_files[] = $file_name;
			$this->RSContainerData[$relative_path] = $existing_files;
			
			// Remove the original file we were uploading.
			if ($remove_original) {
				unlink($local_file);
			}
			
			return $url."/".$file_name;
		}
		
		/*
			Function: upload
				Uploads a file to the current upload service
			
			Parameters:
				local_file - The absolute path to the local file you wish to upload.
				file_name - The desired file name at the upload end point.
				relative_path - The directory to store the file in (for local files, also used to generate a bucket ID).
				remove_original - Whether to delete the local_file or not.
			
			Returns:
				The URL to the uploaded file.
		*/
		
		function upload($local_file,$file_name,$relative_path,$remove_original = true) {
			if ($this->Service == "local") {
				return $this->uploadLocal($local_file,$file_name,$relative_path,$remove_original);
			} elseif ($this->Service == "s3") {
				return $this->uploadS3($local_file,$file_name,$relative_path,$remove_original);
			} elseif ($this->Service == "rackspace") {
				return $this->uploadRackspace($local_file,$file_name,$relative_path,$remove_original);
			} else {
				die("BigTree Critical Error: Unknown Upload Service In Effect");
			}
		}
		
		/*
			Function: uploadLocal
				Private function for the upload call when local storage is active.
			
			See Also:
				<upload>
		*/
		
		private function uploadLocal($local_file,$file_name,$relative_path,$remove_original) {
			$safe_name = get_safe_filename($GLOBALS["site_root"].$relative_path,$file_name);
			
			if ($remove_original) {
				$success = bigtree_move($local_file,$GLOBALS["site_root"].$relative_path.$safe_name);
			} else {
				$success = bigtree_copy($local_file,$GLOBALS["site_root"].$relative_path.$safe_name);
			}
			
			if ($success) {
				return "{wwwroot}".$relative_path.$safe_name;
			} else {
				return false;
			}
		}
		
		/*
			Function: uploadS3
				Private function for the upload call when Amazon S3 is active.
			
			See Also:
				<upload>
		*/
		
		private function uploadS3($local_file,$file_name,$relative_path,$remove_original) {
			global $cms;
			
			if (!$this->S3) {
				$keys = $cms->getSetting("bigtree-internal-s3-keys");
				$this->S3 = new S3($keys["access_key_id"],$keys["secret_access_key"]);
			}
			
			if (!$this->S3Buckets) {
				$this->S3Buckets = $cms->getSetting("bigtree-internal-s3-buckets");
			}
			
			// If we don't have a bucket for this path yet, make one.
			$bucket = $this->S3Buckets[$relative_path];
			if (!$bucket) {
				$bucket = uniqid("bigtree-");
				$this->S3Buckets[$relative_path] = $bucket;
				$this->S3->putBucket($bucket,S3::ACL_PUBLIC_READ);
				$admin = new BigTreeAdmin;
				$admin->updateSettingValue("bigtree-internal-s3-buckets",$this->S3Buckets);
			}
			
			// Check to see if this is a unique file name for this bucket, if not, get one.
			$existing_files = $this->S3BucketData[$bucket];
			if (!$existing_files) {
				$existing_files = $this->S3->getBucket($bucket);
				$this->S3BucketData[$bucket] = $existing_files;
			}
			
			// Get a nice clean file name
			$parts = safe_pathinfo($file_name);
			$clean_name = $cms->urlify($parts["filename"]);
			if (strlen($clean_name) > 50) {
				$clean_name = substr($clean_name,0,50);
			}
			$file_name = $clean_name.".".$parts["extension"];
			
			// Loop until we get a good file name.
			$x = 2;
			$original = $file_name;
			while ($existing_files[$file_name]) {
				$file_name = $clean_name."-$x.".$parts["extension"];
				$x++;
			}
			
			$this->S3->putObjectFile($local_file,$this->S3Buckets[$relative_path],$file_name,S3::ACL_PUBLIC_READ);
			
			// Update the list of files in this bucket locally.
			$existing_files[$file_name] = true;
			$this->S3BucketData[$bucket] = $existing_files;
			
			// Remove the original file we were uploading.
			if ($remove_original) {
				unlink($local_file);
			}
			
			return "http://$bucket.s3.amazonaws.com/$file_name";
		}
		
		/*
			Function: uploadRackspace
				Private function for the upload call when Rackspace Cloud Files is active.
			
			See Also:
				<upload>
		*/
		
		private function uploadRackspace($local_file,$file_name,$relative_path,$remove_original) {
			global $cms;
						
			if (!$this->Rackspace) {
				$keys = $cms->getSetting("bigtree-internal-rackspace-keys");
				$this->RSAuth = new CF_Authentication($keys["username"],$keys["api_key"]);
				$this->RSAuth->authenticate();
				$this->RSConn = new CF_Connection($this->RSAuth);
			}
			
			if (!$this->RSContainers) {
				$this->RSContainers = $cms->getSetting("bigtree-internal-rackspace-containers");
			}

			$relative_path = str_replace("/","-",rtrim($relative_path,"/"));
			
			// If we don't have a bucket for this path yet, make one.
			$url = $this->RSContainers[$relative_path];
			if (!$url) {
				$container = $this->RSConn->create_container($relative_path);
				$url = $container->make_public();

				$this->RSContainers[$relative_path] = $url;
				$admin = new BigTreeAdmin;
				$admin->updateSettingValue("bigtree-internal-rackspace-containers",$this->RSContainers);
			} else {
				$container = $this->RSConn->get_container($relative_path);
			}
			
			// Check to see if this is a unique file name for this bucket, if not, get one.
			$existing_files = $this->RSContainerData[$relative_path];
			if (!$existing_files) {
				$existing_files = $container->list_objects();
				$this->RSContainerData[$relative_path] = $existing_files;
			}
			
			// Get a nice clean file name
			$parts = safe_pathinfo($file_name);
			$clean_name = $cms->urlify($parts["filename"]);
			if (strlen($clean_name) > 50) {
				$clean_name = substr($clean_name,0,50);
			}
			$file_name = $clean_name.".".$parts["extension"];
			
			// Loop until we get a good file name.
			$x = 2;
			$original = $file_name;
			while (in_array($file_name,$existing_files)) {
				$file_name = $clean_name."-$x.".$parts["extension"];
				$x++;
			}
			
			// Create the object
			$object = $container->create_object($file_name);
			$object->load_from_filename($local_file);
			
			// Update the list of files in this container locally.
			$existing_files[] = $file_name;
			$this->RSContainerData[$relative_path] = $existing_files;
			
			// Remove the original file we were uploading.
			if ($remove_original) {
				unlink($local_file);
			}
			
			return $url."/".$file_name;
		}
	}
?>