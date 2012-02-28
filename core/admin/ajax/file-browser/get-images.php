<div class="file_browser_images">
	<?
		if ($_POST["query"]) {
			$items = $admin->getResourceSearchResults($_POST["query"]);
			$perm = "e";
			$bc = array(array("name" => "Search Results","id" => ""));
		} else {
			$perm = $admin->getResourceFolderPermission($_POST["folder"]);
			$items = $admin->getContentsOfResourceFolder($_POST["folder"]);
			$bc = $admin->getResourceFolderBreadcrumb($_POST["folder"]);
		}
		
		if (!$_POST["query"] && $_POST["folder"] > 0) {
			$folder = sqlfetch(sqlquery("SELECT * FROM bigtree_resource_folders WHERE id = '".mysql_real_escape_string($_POST["folder"])."'"));
	?>
	<a href="#<?=$folder["parent"]?>" class="file folder"><span class="file_type file_type_folder"></span> ..</a>
	<?	
		}
	
		if ($perm != "n") {
		
			$minWidth = $_POST["minWidth"];
			$minHeight = $_POST["minHeight"];
			
			$itype_exts = array(IMAGETYPE_PNG => ".png", IMAGETYPE_JPEG => ".jpg", IMAGETYPE_GIF => ".gif");
			
			foreach ($items["folders"] as $folder) {
	?>
	<a href="#<?=$folder["id"]?>" class="file folder<? if ($folder["permission"] == "n") { ?> disabled<? } ?>"><span class="file_type file_type_folder"></span> <?=$folder["name"]?></a>		
	<?
			}
		
			foreach ($items["resources"] as $resource) {
				$file = str_replace("{wwwroot}",$site_root,$resource["file"]);
				$thumbs = json_decode($resource["thumbs"],true);
				$thumb = $thumbs["bigtree_internal_list"];
				$margin = $resource["list_thumb_margin"];
				$thumb = str_replace("{wwwroot}",$www_root,$thumb);
				$disabled = (($minWidth && $minWidth !== "false" && $resource["width"] < $minWidth) || ($minHeight && $minHeight !== "false" && $resource["height"] < $minHeight)) ? " disabled" : "";
				
				// Find the available thumbnails for this image if we're dropping it in a WYSIWYG area.
				$available_thumbs = array();
				foreach ($thumbs as $tk => $tu) {
					if (substr($tk,0,17) != "bigtree_internal_") {
						$available_thumbs[] = array(
							"name" => $tk,
							"file" => $tu
						);
					}
				}
				
				$data = htmlspecialchars(json_encode(array(
					"file" => $resource["file"],
					"thumbs" => $available_thumbs
				)));
	?>
	<a href="<?=$data?>" class="image<?=$disabled?>"><img src="<?=$thumb?>" alt="" style="margin-top: <?=$margin?>px;" /></a>
	<?
			}
		}
		
		$crumb_contents = "";
		foreach ($bc as $crumb) {
			$crumb_contents .= '<li><a href="#'.$crumb["id"].'">'.$crumb["name"].'</a></li>';
		}
	?>
</div>
<script type="text/javascript">
	<? if ($perm == "p") { ?>
	BigTreeFileManager.enableCreate();
	<? } else { ?>
	BigTreeFileManager.disableCreate();
	<? } ?>
	
	BigTreeFileManager.setBreadcrumb("<?=str_replace('"','\"',$crumb_contents)?>");
</script>