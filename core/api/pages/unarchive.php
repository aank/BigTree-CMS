<?
	/*
	|Name: Unarchive Page|
	|Description: Unarchives a page or requests unarchival if an user is an editor.|
	|Readonly: NO|
	|Level: 0|
	|Parameters: 
		id: Page's Database ID|
	|Returns:
		status: "APPROVED" for immediate change or "PENDING"|
	*/
	
	$p = $admin->getPageAccessLevel($_POST["id"]);
	if ($p != "p") {
		echo BigTree::apiEncode(array("success" => false,"error" => "You do not have permission to edit this page."));
	} else {
		$admin->unarchivePage($_POST["id"]);
		echo BigTree::apiEncode(array("success" => true));
	}
?>