<?
	/*
		Class: BigTreeAdmin
			The main class used by the admin for manipulating and retrieving data.
	*/

	class BigTreeAdmin {

		var $PerPage = 15;

		// !View Types
		var $ViewTypes = array(
			"searchable" => "Searchable List",
			"draggable" => "Draggable List",
			"images" => "Image List",
			"grouped" => "Grouped List",
			"images-grouped" => "Grouped Image List"
		);

		// !Reserved Column Names
		var $ReservedColumns = array(
			"id",
			"position",
			"archived",
			"approved"
		);

		// !View Actions
		var $ViewActions = array(
			"approve" => array(
				"key" => "approved",
				"name" => "Approve",
				"class" => "icon_approve icon_approve_on"
			),
			"archive" => array(
				"key" => "archived",
				"name" => "Archive",
				"class" => "icon_archive"
			),
			"feature" => array(
				"key" => "featured",
				"name" => "Feature",
				"class" => "icon_feature icon_feature_on"
			),
			"edit" => array(
				"key" => "id",
				"name" => "Edit",
				"class" => "icon_edit"
			),
			"delete" => array(
				"key" => "id",
				"name" => "Delete",
				"class" => "icon_delete"
			)
		);
		
		/*
			Constructor:
				Initializes the user's permissions.
		*/
		
		function __construct() {
			if (isset($_SESSION["bigtree"]["email"])) {
				$this->ID = $_SESSION["bigtree"]["id"];
				$this->User = $_SESSION["bigtree"]["email"];
				$this->Level = $_SESSION["bigtree"]["level"];
				$this->Name = $_SESSION["bigtree"]["name"];
				$this->Permissions = $_SESSION["bigtree"]["permissions"];
			} elseif (isset($_COOKIE["bigtree"]["email"])) {
				$user = mysql_escape_string($_COOKIE["bigtree"]["email"]);
				$pass = mysql_escape_string($_COOKIE["bigtree"]["password"]);
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_users WHERE email = '$user' AND password = '$pass'"));
				if ($f) {
					$this->ID = $f["id"];
					$this->User = $user;
					$this->Level = $f["level"];
					$this->Name = $f["name"];
					$this->Permissions = json_decode($f["permissions"],true);
					$_SESSION["bigtree"]["id"] = $f["id"];
					$_SESSION["bigtree"]["email"] = $f["email"];
					$_SESSION["bigtree"]["level"] = $f["level"];
					$_SESSION["bigtree"]["name"] = $f["name"];
					$_SESSION["bigtree"]["permissions"] = $this->Permissions;
				}
			}
		}
		
		/*
			Function: archivePage
				Archives a page.
			
			Parameters:
				page - Either a page id or page entry.
		
			Returns:
				true if successful. false if the logged in user doesn't have permission.
			
			See Also:
				<archivePageChildren>
		*/

		function archivePage($page) {
			if (is_array($page)) {
				$page = mysql_real_escape_string($page["id"]);
			} else {
				$page = mysql_real_escape_string($page);
			}

			$access = $this->getPageAccessLevel($page);
			if ($access == "p") {
				sqlquery("UPDATE bigtree_pages SET archived = 'on' WHERE id = '$page'");
				$this->archivePageChildren($page);
				$this->growl("Pages","Archived Page");
				$this->track("bigtree_pages",$page,"archived");
				return true;
			}
			return false;
		}
		
		/*
			Function: archivePageChildren
				Archives a page's children and sets the archive status to inherited.
			
			Parameters:
				page - A page id.
			
			See Also:
				<archivePage>
		*/

		function archivePageChildren($page) {
			$page = mysql_real_escape_string($page);
			$q = sqlquery("SELECT * FROM bigtree_pages WHERE parent = '$page'");
			while ($f = sqlfetch($q)) {
				if (!$f["archived"]) {
					sqlquery("UPDATE bigtree_pages SET archived = 'on', archived_inherited = 'on' WHERE id = '".$f["id"]."'");
					$this->track("bigtree_pages",$f["id"],"archived");
					$this->archivePageChildren($f["id"]);
				}
			}
		}

		/*
			Function: autoIPL
				Automatically converts links to internal page links.
			
			Parameters:
				html - A string of contents that may contain URLs
			
			Returns:
				A string with hard links converted into internal page links.
		*/

		function autoIPL($html) {
			// If this string is actually just a URL, IPL it.
			if (substr($html,0,7) == "http://" || substr($html,0,8) == "https://") {
				$html = $this->makeIPL($html);
			// Otherwise, switch all the image srcs and javascripts srcs and whatnot to {wwwroot}.
			} else {
				$html = preg_replace_callback('^href="([a-zA-Z0-9\:\/\.\?\=\-]*)"^','bigtree_regex_set_ipl',$html);
				$html = str_replace($GLOBALS["www_root"],"{wwwroot}",$html);
			}
			return $html;
		}
		
		/*
			Function: checkHTML
				Checks a block of HTML for broken links/images
			
			Parameters:
				relative_path - The starting path of the page containing the HTML (so that relative links, i.e. "good/" know where to begin)
				html - A string of HTML
				external - Whether to check external links (slow) or not
		
			Returns:
				An array of errors.
		*/

		function checkHTML($relative_path,$html,$external = false) {
			if (!$html) {
				return array();
			}
			$errors = array();
			$doc = new DOMDocument();
			$doc->loadHTML($html);
			// Check A tags.
			$links = $doc->getElementsByTagName("a");
			foreach ($links as $link) {
				$href = $link->getAttribute("href");
				$href = str_replace(array("{wwwroot}","%7Bwwwroot%7D"),$GLOBALS["www_root"],$href);
				if (substr($href,0,4) == "http" && strpos($href,$GLOBALS["www_root"]) === false) {
					// External link, not much we can do but alert that it's dead
					if ($external) {
						if (strpos($href,"#") !== false)
							$href = substr($href,0,strpos($href,"#")-1);
						if (!$this->urlExists($href)) {
							$errors["a"][] = $href;
						}
					}
				} elseif (substr($href,0,6) == "ipl://") {
					if (!$this->iplExists($href)) {
						$errors["a"][] = $href;
					}
				} elseif (substr($href,0,7) == "mailto:" || substr($href,0,1) == "#" || substr($href,0,5) == "data:") {
					// Don't do anything, it's a page mark, data URI, or email address
				} elseif (substr($href,0,4) == "http") {
					// It's a local hard link
					if (!$this->urlExists($href)) {
						$errors["a"][] = $href;
					}
				} else {
					// Local file.
					$local = $relative_path.$href;
					if (!$this->urlExists($local)) {
						$errors["a"][] = $local;
					}
				}
			}
			// Check IMG tags.
			$images = $doc->getElementsByTagName("img");
			foreach ($images as $image) {
				$href = $image->getAttribute("src");
				$href = str_replace(array("{wwwroot}","%7Bwwwroot%7D"),$GLOBALS["www_root"],$href);
				if (substr($href,0,4) == "http" && strpos($href,$GLOBALS["www_root"]) === false) {
					// External link, not much we can do but alert that it's dead
					if ($external) {
						if (!$this->urlExists($href)) {
							$errors["img"][] = $href;
						}
					}
				} elseif (substr($href,0,6) == "ipl://") {
					if (!$this->iplExists($href)) {
						$errors["a"][] = $href;
					}
				} elseif (substr($href,0,5) == "data:") {
					// Do nothing, it's a data URI
				} elseif (substr($href,0,4) == "http") {
					// It's a local hard link
					if (!$this->urlExists($href)) {
						$errors["img"][] = $href;
					}
				} else {
					// Local file.
					$local = $relative_path.$href;
					if (!$this->urlExists($local)) {
						$errors["img"][] = $local;
					}
				}
			}
			return array($errors);
		}
		
		/*
			Function: clearCache
				Removes all files in the cache directory.
		*/

		function clearCache() {
			$d = opendir($GLOBALS["server_root"]."cache/");
			while ($f = readdir($d)) {
				if ($f != "." && $f != ".." && !is_dir($GLOBALS["server_root"]."cache/".$f)) {
					unlink($GLOBALS["server_root"]."cache/".$f);
				}
			}
		}
		
		/*
			Function: createPage
				Creates a page.
				Does not check permissions.
			
			Parameters:
				data - An array of page information.
			
			Returns:
				The id of the newly created page.
		*/

		function createPage($data) {
			global $cms;
			
			// Loop through the posted data, make sure no session hijacking is done.
			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_") {
					if (is_array($val)) {
						$$key = mysql_real_escape_string(json_encode($val));
					} else {
						$$key = mysql_real_escape_string($val);
					}
				}
			}
			
			// If there's an external link, make sure it's a relative URL
			if ($external) {
				$external = $this->makeIPL($external);
			}
			
			
			// Who knows what they may have put in for a route, so we're not going to use the mysql_real_escape_string version.
			$route = $data["route"];
			if (!$route) {
				// If they didn't specify a route use the navigation title
				$route = $cms->urlify($data["nav_title"]);
			} else {
				// Otherwise sanitize the one they did provide.
				$route = $cms->urlify($route);
			}
			
			// We need to figure out a unique route for the page.  Make sure it doesn't match a directory in /site/
			$original_route = $route;
			$x = 2;
			// Reserved paths.
			if ($parent == 0) {
				while (file_exists($GLOBALS["server_root"]."site/".$route."/")) {
					$route = $original_route."-".$x;
					$x++;
				}
			}
			
			// Make sure it doesn't have the same route as any of its siblings.
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pages WHERE `route` = '$route' AND parent = '$parent'"));
			while ($f) {
				$route = $original_route."-".$x;
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pages WHERE `route` = '$route' AND parent = '$parent'"));
				$x++;
			}
			
			// If we have a parent, get the full navigation path, otherwise, just use this route as the path since it's top level.
			if ($parent) {
				$path = $this->getFullNavigationPath($parent)."/".$route;
			} else {
				$path = $route;
			}
			
			// If we set a publish at date, make it the proper MySQL format.
			if ($publish_at) {
				$publish_at = "'".date("Y-m-d",strtotime($publish_at))."'";
			} else {
				$publish_at = "NULL";
			}

			// If we set an expiration date, make it the proper MySQL format.
			if ($expire_at) {
				$expire_at = "'".date("Y-m-d",strtotime($expire_at))."'";
			} else {
				$expire_at = "NULL";
			}
			
			// Make the title, navigation title, description, keywords, and external link htmlspecialchar'd -- these are all things we'll be echoing in the HTML so we might as well make them valid now instead of at display time.
			
			$title = htmlspecialchars($title);
			$nav_title = htmlspecialchars($nav_title);
			$meta_description = htmlspecialchars($meta_description);
			$meta_keywords = htmlspecialchars($meta_keywords);
			$external = htmlspecialchars($external);

			// Make the page!
			sqlquery("INSERT INTO bigtree_pages (`parent`,`nav_title`,`route`,`path`,`in_nav`,`title`,`template`,`external`,`new_window`,`resources`,`callouts`,`meta_keywords`,`meta_description`,`last_edited_by`,`created_at`,`updated_at`,`publish_at`,`expire_at`,`max_age`) VALUES ('$parent','$nav_title','$route','$path','$in_nav','$title','$template','$external','$new_window','$resources','$callouts','$meta_keywords','$meta_description','".$this->ID."',NOW(),NOW(),$publish_at,$expire_at,'$max_age')");

			$id = sqlid();

			// Handle tags
			if (is_array($data["_tags"])) {
				foreach ($data["_tags"] as $tag) {
					sqlquery("INSERT INTO bigtree_tags_rel (`module`,`entry`,`tag`) VALUES ('0','$id','$tag')");
				}
			}

			// If there was an old page that had previously used this path, dump its history so we can take over the path.
			sqlquery("DELETE FROM bigtree_route_history WHERE old_route = '$path'");
			
			// Dump the cache, we don't really know how many pages may be showing this now in their nav.
			$this->clearCache();
			// Let search engines know this page now exists.
			$this->pingSearchEngines();
			// Audit trail.
			$this->track("bigtree_pages",$id,"created");

			return $id;
		}
		
		/*
			Function: createPendingPage
				Creates a pending page entry in bigtree_pending_changes
			
			Parameters:
				data - An array of page information.
		
			Returns:
				The id of the pending change.
		*/

		function createPendingPage($data) {
			global $cms;
			
			// Make a relative URL for external links.
			if ($data["external"]) {
				$data["external"] = $this->makeIPL($data["external"]);
			}
			
			// Save the tags, then dump them from the saved changes array.
			$tags = mysql_real_escape_string(json_encode($data["_tags"]));
			unset($data["_tags"]);
			
			// Make the nav title, title, external link, keywords, and description htmlspecialchar'd for displaying on the front end / the form again.
			$data["nav_title"] = htmlspecialchars($data["nav_title"]);
			$data["title"] = htmlspecialchars($data["title"]);
			$data["external"] = htmlspecialchars($data["external"]);
			$data["meta_keywords"] = htmlspecialchars($data["meta_keywords"]);
			$data["meta_description"] = htmlspecialchars($data["meta_description"]);
			
			// JSON encode the changes and stick them in the database.
			$data = mysql_real_escape_string(json_encode($data));
			sqlquery("INSERT INTO bigtree_pending_changes (`user`,`date`,`title`,`table`,`changes`,`tags_changes`,`type`,`module`,`pending_page_parent`) VALUES ('".$this->ID."',NOW(),'New Page Created','bigtree_pages','$data','$tags','NEW','','".mysql_real_escape_string($data["parent"])."')");
			$id = sqlid();
			
			// Audit trail
			$this->track("bigtree_pages","p$id","created-pending");

			return $id;
		}
		
		/*
			Function: deletePage
				Deletes a page or a pending page.
				Checks permissions.
			
			Parameters:
				page - A page id or a pending page id prefixed with a "p"
			
			Returns:
				true if successful. Stops page execution if permission issues occur.
		*/		

		function deletePage($page) {
			global $cms;

			$page = mysql_real_escape_string($page);

			$r = $this->getPageAccessLevelByUser($page,$this->ID);
			if ($r == "p") {
				// If the page isn't numeric it's most likely prefixed by the "p" so it's pending.
				if (!is_numeric($page)) {
					sqlquery("DELETE FROM bigtree_pending_changes WHERE id = '".mysql_real_escape_string(substr($page,1))."'");
					$this->growl("Pages","Deleted Page");
					$this->track("bigtree_pages","p$page","deleted-pending");
				} else {
					sqlquery("DELETE FROM bigtree_pages WHERE id = '$page'");
					// Delete the children as well.
					$this->deletePageChildren($page);
					$this->growl("Pages","Deleted Page");
					$this->track("bigtree_pages",$page,"deleted");
				}

				return true;
			}
			$this->stop("You do not have permission to delete this page.");
		}
		
		/*
			Function: deletePageChildren
				Deletes the children of a page and recurses downward.
				Does not check permissions.
			
			Parameters:
				id - The parent id to delete children for.
		*/

		function deletePageChildren($id) {
			$q = sqlquery("SELECT * FROM bigtree_pages WHERE parent = '$id'");
			while ($f = sqlfetch($q)) {
				$this->deletePageChildren($f["id"]);
			}
			sqlquery("DELETE FROM bigtree_pages WHERE parent = '$id'");
			$this->track("bigtree_pages",$id,"deleted");
		}
		
		/*
			Function: getActionClass
				Returns the button class for the given action and item.
			
			Parameters:
				action - The action for the item (edit, feature, approve, etc)
				item - The entry to check the action for.
			
			Returns:
				Class name for the <a> tag.
				
				For example, if the item is already featured, this returns "icon_featured icon_featured_on" for the "feature" action.
				If the item isn't already featured, it would simply return "icon_featured" for the "feature" action.
		*/

		function getActionClass($action,$item) {
			$class = "";
			if ($item["bigtree_pending"] && $action != "edit" && $action != "delete") {
				return "icon_disabled";
			}
			if ($action == "feature") {
				$class = "icon_feature";
				if ($item["featured"]) {
					$class .= " icon_feature_on";
				}
			}
			if ($action == "edit") {
				$class = "icon_edit";
			}
			if ($action == "delete") {
				$class = "icon_delete";
			}
			if ($action == "approve") {
				$class = "icon_approve";
				if ($item["approved"]) {
					$class .= " icon_approve_on";
				}
			}
			if ($action == "archive") {
				$class = "icon_archive";
				if ($item["archived"]) {
					$class .= " icon_archive_on";
				}
			}
			if ($action == "preview") {
				$class = "icon_preview";
			}
			return $class;
		}
		
		/*
			Function: getArchivedNavigationByParent
				Returns an alphabetic list of navigation that is archived under the given parent.
			
			Parameters:
				parent - The ID of the parent page
			
			Returns:
				An array of page entries.
		*/

		function getArchivedNavigationByParent($parent) {
			$nav = array();
			$q = sqlquery("SELECT id,nav_title as title,parent,external,new_window,template,publish_at,expire_at,path FROM bigtree_pages WHERE parent = '$parent' AND archived = 'on' ORDER BY nav_title asc");
			while ($nav_item = sqlfetch($q)) {
				$nav_item["external"] = str_replace("{wwwroot}",$GLOBALS["www_root"],$nav_item["external"]);
				$nav[] = $nav_item;
			}
			return $nav;
		}
		
		/*
			Function: getAutoModuleActions
				Return a list of module forms and views.
				Used by the API for reconstructing forms and views.
			
			Parameters:
				module - The module id to pull forms/views for.
			
			Returns:
				An array of module actions with "form" and "view" columns replaced with form and view data.
			
			See Also:
				<BigTreeAutoModule.getForm>
				<BigTreeAutoModule.getView>
		*/
		
		function getAutoModuleActions($module) {
			$items = array();
			$id = mysql_real_escape_string($module);
			$q = sqlquery("SELECT * FROM bigtree_module_actions WHERE module = '$id' AND (form != 0 OR view != 0) AND in_nav = 'on' ORDER BY position DESC, id ASC");
			while ($f = sqlfetch($q)) {
				if ($f["form"]) {
					$f["form"] = BigTreeAutoModule::getForm($f["form"]);
					$f["type"] = "form";
				} elseif ($f["view"]) {
					$f["view"] = BigTreeAutoModule::getView($f["view"]);
					$f["type"] = "view";
				}
				$items[] = $f;
			}
			return $items;
		}
		
		/*
			Function: getAvailableModuleRoute
				Returns a route for a module that won't collide with another module.
			
			Parameters:
				route - The preferred route.
			
			Returns:
				A safe route.
		*/

		function getAvailableModuleRoute($route) {
			$existing = array();
			$d = opendir($GLOBALS["server_root"]."core/admin/modules/");
			while ($f = readdir($d)) {
				if ($f != "." && $f != "..") {
					$existing[] = $f;
				}
			}
			$d = opendir($GLOBALS["server_root"]."core/admin/");
			while ($f = readdir($d)) {
				if ($f != "." && $f != "..") {
					$existing[] = $f;
				}
			}
			$q = sqlquery("SELECT * FROM bigtree_modules");
			while ($f = sqlfetch($q)) {
				$existing[] = $f["route"];
			}

			$x = 2;
			$oroute = $route;
			while (in_array($route,$existing)) {
				$route = $oroute."-".$x;
				$x++;
			}
			return $route;
		}
		
		/*
			Function: getBasicTemplates
				Returns a list of non-routed templates ordered by position.	
			
			Returns:
				An array of template entries.
		*/

		function getBasicTemplates() {
			$q = sqlquery("SELECT * FROM bigtree_templates WHERE level <= '".$this->Level."' ORDER BY position desc");
			$items = array();
			while ($f = sqlfetch($q)) {
				if (!$f["routed"]) {
					$items[] = $f;
				}
			}
			return $items;
		}
		
		/*
			Function: getChange
				Get a pending change.
			
			Parameters:
				id - The id of the pending change.
			
			Returns:
				A pending change entry from the bigtree_pending_changes table.
		*/

		function getChange($id) {
			return sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '$id'"));
		}

		/*
			Function: getChangeEditLink
				Returns a link to where the item involved in the pending change can be edited.

			Parameters:
				change - The ID of the change or the change array from the database.

			Returns:
				A string containing a link to the admin.
		*/

		function getChangeEditLink($change) {
			if (!is_array($change)) {
				$change = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '$change'"));
			}

			if ($change["table"] == "bigtree_pages" && $change["item_id"]) {
				return $GLOBALS["admin_root"]."pages/edit/".$change["item_id"]."/";
			}

			if ($change["table"] == "bigtree_pages") {
				return $GLOBALS["admin_root"]."pages/edit/p".$change["id"]."/";
			}

			$modid = $change["module"];
			$module = sqlfetch(sqlquery("SELECT * FROM bigtree_modules WHERE id = '$modid'"));
			$form = sqlfetch(sqlquery("SELECT * FROM bigtree_module_forms WHERE `table` = '".$change["table"]."'"));
			$action = sqlfetch(sqlquery("SELECT * FROM bigtree_module_actions WHERE `form` = '".$form["id"]."' AND in_nav = ''"));

			if (!$change["item_id"]) {
				$change["item_id"] = "p".$change["id"];
			}

			if ($action) {
				return $GLOBALS["admin_root"].$module["route"]."/".$action["route"]."/".$change["item_id"]."/";
			} else {
				return $GLOBALS["admin_root"].$module["route"]."/edit/".$change["item_id"]."/";
			}
		}
		
		/*
			Function: getFullNavigationPath
				Calculates the full navigation path for a given page ID.
			
			Parameters:
				id - The page ID to calculate the navigation path for.
			
			Returns:
				The navigation path (normally found in the "path" column in bigtree_pages).
		*/

		function getFullNavigationPath($id, $path = array()) {
			global $cms;
			
			// We can change $GLOBALS["root_page"] to drive multiple sites from different branches of the Pages tree.
			$root_page = isset($GLOBALS["root_page"]) ? $_GLOBALS["root_page"] : 0;
			
			$f = sqlfetch(sqlquery("SELECT route,id,parent FROM bigtree_pages WHERE id = '$id'"));
			$path[] = $cms->urlify($f["route"]);
			if ($f["parent"] != $root_page && $f["parent"] != 0) {
				return $this->getFullNavigationPath($f["parent"],$path);
			}
			$path = implode("/",array_reverse($path));
			return $path;
		}
		
		/*
			Function: getHiddenNavigationByParent
				Returns an alphabetic list of navigation that is hidden under the given parent.
			
			Parameters:
				parent - The ID of the parent page
			
			Returns:
				An array of page entries.
		*/

		function getHiddenNavigationByParent($parent) {
			$nav = array();
			$q = sqlquery("SELECT id,nav_title as title,parent,external,new_window,template,publish_at,expire_at,path FROM bigtree_pages WHERE parent = '$parent' AND in_nav = '' AND archived != 'on' ORDER BY nav_title asc");
			while ($nav_item = sqlfetch($q)) {
				$nav_item["external"] = str_replace("{wwwroot}",$GLOBALS["www_root"],$nav_item["external"]);
				$nav[] = $nav_item;
			}
			return $nav;
		}
		
		/*
			Function: getModule
				Returns an entry from the bigtree_modules table.
			
			Parameters:
				id - The id of the module.
			
			Returns:
				A module entry with the "gbp" column decoded.
		*/

		function getModule($id) {
			$id = mysql_real_escape_string($id);
			$module = sqlfetch(sqlquery("SELECT * FROM bigtree_modules WHERE id = '$id'"));
			if (!$module) {
				return false;
			}

			$module["gbp"] = json_decode($module["gbp"],true);
			return $module;
		}
		
		/*
			Function: getModuleAction
				Returns an entry from the bigtree_module_actions table.
			
			Parameters:
				id - The id of the action.
			
			Returns:
				A module action entry.
		*/

		function getModuleAction($id) {
			$id = mysql_real_escape_string($id);
			return sqlfetch(sqlquery("SELECT * FROM bigtree_module_actions WHERE id = '$id'"));
		}
		
		/*
			Function: getModuleActionForForm
				Returns the related module action for an auto module form.
			
			Parameters:
				form - The id of a form or a form entry.
			
			Returns:
				A module action entry.
		*/

		function getModuleActionForForm($form) {
			if (is_array($form)) {
				$form = mysql_real_escape_string($form["id"]);
			} else {
				$form = mysql_real_escape_string($form);
			}
			return sqlfetch(sqlquery("SELECT * FROM bigtree_module_actions WHERE form = '$form'"));
		}
		
		/*
			Function: getModuleActionForView
				Returns the related module action for an auto module view.
			
			Parameters:
				view - The id of a view or a view entry.
			
			Returns:
				A module action entry.
		*/

		function getModuleActionForView($view) {
			if (is_array($form)) {
				$view = mysql_real_escape_string($view["id"]);
			} else {
				$view = mysql_real_escape_string($view);
			}
			return sqlfetch(sqlquery("SELECT * FROM bigtree_module_actions WHERE view = '$view'"));
		}
		
		/*
			Function: getModuleActions
				Returns a list of module actions in positioned order.
			
			Parameters:
				module - A module id or a module entry.
			
			Returns:
				An array of module action entries.
		*/

		function getModuleActions($module) {
			if (is_array($module)) {
				$module = mysql_real_escape_string($module["id"]);
			} else {
				$module = mysql_real_escape_string($module);
			}
			$items = array();
			$q = sqlquery("SELECT * FROM bigtree_module_actions WHERE module = '$module' ORDER BY position DESC, id ASC");
			while ($f = sqlfetch($q)) {
				$items[] = $f;
			}
			return $items;
		}
		
		/*
			Function: getHiddenNavigationByParent
				Returns a list of positioned navigation that is in navigation under the given parent.
				Does not return module navigation.
			
			Parameters:
				parent - The ID of the parent page
			
			Returns:
				An array of page entries.
		*/

		function getNaturalNavigationByParent($parent,$levels = 1) {
			$nav = array();
			$q = sqlquery("SELECT id,nav_title as title,parent,external,new_window,template,publish_at,expire_at,path FROM bigtree_pages WHERE parent = '$parent' AND in_nav = 'on' AND archived != 'on' ORDER BY position DESC, id ASC");
			while ($nav_item = sqlfetch($q)) {
				$nav_item["external"] = str_replace("{wwwroot}",$GLOBALS["www_root"],$nav_item["external"]);
				if ($levels > 1) {
					$nav_item["children"] = $this->getNaturalNavigationByParent($f["id"],$levels - 1);
				}
				$nav[] = $nav_item;
			}
			return $nav;
		}
		
		/*
			Function: getPageVersion
				Returns a version of a page from the bigtree_page_versions table.
			
			Parameters:
				id - The id of the page version.
			
			Returns:
				A page version entry from the table.
		*/

		function getPageVersion($id) {
			$id = mysql_real_escape_string($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_page_versions WHERE id = '$id'"));
			return $item;
		}
		
		/*
			Function: getPendingChange
				Returns a pending change from the bigtree_pending_changes table.
			
			Parameters:
				id - The id of the change.
			
			Returns:
				A entry from the table with the "changes" column decoded.
		*/

		function getPendingChange($id) {
			$id = mysql_real_escape_string($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '$id'"));
			if (!$item) {
				return false;
			}
			$item["changes"] = json_decode($item["changes"],true);
			return $item;
		}
		
		/*
			Function: getPendingNavigationByParent
				Returns a list of pending pages under a given parent ordered by most recent.
			
			Parameters:
				parent - The ID of the parent page
				in_nav - "on" returns pages in navigation, "" returns hidden pages
			
			Returns:
				An array of pending page titles/ids.
		*/

		function getPendingNavigationByParent($parent,$in_nav = "on") {
			$nav = array();
			$titles = array();
			$q = sqlquery("SELECT * FROM bigtree_pending_changes WHERE pending_page_parent = '$parent' AND `table` = 'bigtree_pages' AND type = 'NEW' ORDER BY date DESC");
			while ($f = sqlfetch($q)) {
				$page = json_decode($f["changes"],true);
				if ($page["in_nav"] == $in_nav) {
					$titles[] = $page["nav_title"];
					$page["bigtree_pending"] = true;
					$page["title"] = $page["nav_title"];
					$page["id"] = "p".$f["id"];
					$nav[] = $page;
				}
			}
			array_multisort($titles,$nav);
			return $nav;
		}

		/*
			Function: getPendingPage
				Returns a page from the database with all its pending changes applied.

			Parameters:
				id - The ID of the live page or the ID of a pending page with "p" preceding the ID.

			Returns:
				A decoded page array with pending changes applied and related tags.

			See Also:
				<BigTreeCMS.getPage>
		*/

		function getPendingPage($id) {
			// Get the live page.
			if (is_numeric($id)) {
				global $cms;
				$page = $cms->getPage($id);
				if (!$page) {
					return false;
				}
				$page["tags"] = $this->getTagsForPage($id);
				// Get pending changes for this page.
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = '".$page["id"]."'"));
			} else {
				$page = array();
				// Get the page.
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE `id` = '".mysql_real_escape_string(substr($id,1))."'"));
				if ($f) {
					$f["id"] = $id;
				} else {
					return false;
				}
			}

			// Sweep through pending changes and apply them to the page
			if ($f) {
				$page["updated_at"] = $f["date"];
				$changes = json_decode($f["changes"],true);
				foreach ($changes as $key => $val) {
					if ($key == "external") {
						$val = $cms->getInternalPageLink($val);
					}
					$page[$key] = $val;
				}
				// Decode the tag changes, apply them back.
				$tags = array();
				$temp_tags = json_decode($f["tags_changes"],true);
				if (is_array($temp_tags)) {
					foreach ($temp_tags as $tag) {
						$tags[] = sqlfetch(sqlquery("SELECT * FROM bigtree_tags WHERE id = '$tag'"));
					}
				}
				$page["tags"] = $tags;
				// Say that changes exist
				$page["changes_applied"] = true;
			}
			return $page;
		}
		
		/*
			Function: getRoutedTemplates
				Returns a list of routed templates ordered by position.
			
			Returns:
				An array of template entries.
		*/

		function getRoutedTemplates() {
			$q = sqlquery("SELECT * FROM bigtree_templates WHERE level <= '".$this->Level."' ORDER BY position desc");
			$items = array();
			while ($f = sqlfetch($q)) {
				if ($f["routed"]) {
					$items[] = $f;
				}
			}
			return $items;
		}
		
		/*
			Function: getTagsForPage
				Returns a list of tags a page is tagged with.
			
			Parameters:
				page - Either a page id or a page entry.
			
			Returns:
				An array of tags.
		*/

		function getTagsForPage($page) {
			if (is_array($page)) {
				$page = mysql_real_escape_string($page["id"]);
			} else {
				$page = mysql_real_escape_string($page);
			}
			
			$tags = array();
			$q = sqlquery("SELECT bigtree_tags.* FROM bigtree_tags JOIN bigtree_tags_rel WHERE bigtree_tags_rel.tag = bigtree_tags.id AND bigtree_tags_rel.entry = '$page' AND bigtree_tags_rel.module = '0' ORDER BY bigtree_tags.tag");
			while ($f = sqlfetch($q)) {
				$tags[] = $f;
			}
			return $tags;
		}
		
		/*
			Function: getTemplates
				Returns a list of templates ordered by position.
			
			Returns:
				An array of template entries.
		*/

		function getTemplates() {
			$items = array();
			$q = sqlquery("SELECT * FROM bigtree_templates ORDER BY position DESC, id ASC");
			while ($f = sqlfetch($q)) {
				$items[] = $f;
			}
			return $items;
		}
		
		/*
			Function: growl
				Sets up a growl session for the next page reload.
			
			Parameters:
				title - The section message for the growl.
				message - The description of what happened.
				type - The icon to draw.
		*/

		function growl($title,$message,$type = "success") {
			$_SESSION["bigtree"]["flash"] = array("message" => $message, "title" => $title, "type" => $type);
		}
		
		/*
			Function: htmlClean
				Removes things that shouldn't be in the <body> of an HTML document from a string.
			
			Parameters:
				html - A string of HTML
			
			Returns:
				A clean string of HTML for echoing in <body>
		*/

		function htmlClean($html) {
			return str_replace("<br></br>","<br />",strip_tags($html,"<a><abbr><address><area><article><aside><audio><b><base><bdo><blockquote><body><br><button><canvas><caption><cite><code><col><colgroup><command><datalist><dd><del><details><dfn><div><dl><dt><em><emded><fieldset><figcaption><figure><footer><form><h1><h2><h3><h4><h5><h6><header><hgroup><hr><i><iframe><img><input><ins><keygen><kbd><label><legend><li><link><map><mark><menu><meter><nav><noscript><object><ol><optgroup><option><output><p><param><pre><progress><q><rp><rt><ruby><s><samp><script><section><select><small><source><span><strong><style><sub><summary><sup><table><tbody><td><textarea><tfoot><th><thead><time><title><tr><ul><var><video><wbr>"));
		}
		
		/*
			Function: iplExists
				Determines whether an internal page link still exists or not.
			
			Parameters:
				ipl - An internal page link
			
			Returns:
				True if it is still a valid link, otherwise false.
		*/

		function iplExists($ipl) {
			global $cms;
			$ipl = explode("//",$ipl);
			
			// See if the page it references still exists.
			$nav_id = $ipl[1];
			if (!sqlrows(sqlquery("SELECT id FROM bigtree_pages WHERE id = '$nav_id'"))) {
				return false;
			}
			
			// Decode the commands attached to the page
			$commands = json_decode(base64_decode($ipl[2]),true);
			// If there are no commands, we're good.
			if (!isset($commands[0]) || !$commands[0]) {
				return true;
			}
			// If it's a hash tag link, we're also good.
			if (substr($commands[0],0,1) == "#") {
				return true;
			}
			// Get template for the navigation id to see if it's a routed template
			$t = sqlfetch(sqlquery("SELECT bigtree_templates.routed FROM bigtree_templates JOIN bigtree_pages ON bigtree_templates.id = bigtree_pages.template WHERE bigtree_pages.id = '$nav_id'"));
			// If we're a routed template, we're good.
			if ($t["routed"]) {
				return true;
			}
			
			// We may have been on a page, but there's extra routes that don't go anywhere or do anything so it's a 404.
			return false;
		}
		
		/*
			Function: makeIPL
				Creates an internal page link out of a URL.
				
			Paramters:
				url - A URL
			
			Returns:
				An internal page link (if possible) or just the same URL (if it's not internal).
		*/

		function makeIPL($url) {
			global $cms;
			$command = explode("/",rtrim(str_replace($GLOBALS["www_root"],"",$url),"/"));
			list($navid,$commands) = $cms->getNavId($command);
			if (!$navid) {
				return str_replace(array($GLOBALS["www_root"],$GLOBALS["resource_root"]),"{wwwroot}",$url);
			}
			return "ipl://".$navid."//".base64_encode(json_encode($commands));
		}
		
		/*
			Function: pingSearchEngines
				Sends the latest sitemap.xml out to search engine ping services if enabled in settings.	
		*/

		function pingSearchEngines() {
			global $cms;
			if ($cms->getSetting("ping-search-engines") == "on") {
				$google = file_get_contents("http://www.google.com/webmasters/tools/ping?sitemap=".urlencode($GLOBALS["www_root"]."sitemap.xml"));
				$ask = file_get_contents("http://submissions.ask.com/ping?sitemap=".urlencode($GLOBALS["www_root"]."sitemap.xml"));
				$yahoo = file_get_contents("http://search.yahooapis.com/SiteExplorerService/V1/ping?sitemap=".urlencode($GLOBALS["www_root"]."sitemap.xml"));
				$bing = file_get_contents("http://www.bing.com/webmaster/ping.aspx?siteMap=".urlencode($GLOBALS["www_root"]."sitemap.xml"));
			}
		}
		
		/*
			Function: stop
				Stops processing of the Admin area and shows a message in the default layout.
			
			Parameters:
				message - Content to show (error, permission denied, etc)
		*/

		function stop($message = "") {
			global $cms,$admin,$www_root,$admin_root,$site,$breadcrumb;
			echo $message;
			$content = ob_get_clean();
			include bigtree_path("admin/layouts/default.php");
			die();
		}
		
		/*
			Function: submitPageChange
				Adds a pending change to the bigtree_pending_changes table for the page.
				Determines what has changed and only stores the changed fields.
				Does not check permissions.
			
			Parameters:
				page - The page id or pending page id
				changes - An array of changes
		*/

		function submitPageChange($page,$changes) {
			global $cms;
			if ($page[0] == "p") {
				// It's still pending...
				$existing_page = array();
				$pending = true;
				$type = "NEW";
			} else {
				// It's an existing page
				$pending = false;
				$existing_page = $cms->getPage($page);
				$type = "EDIT";
			}

			$template = $existing_page["template"];
			if (!$pending) {
				$existing_pending_change = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = '$page'"));
			} else {
				$existing_pending_change = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '".substr($page,1)."'"));
			}
			
			// Save tags separately
			$tags = mysql_real_escape_string(json_encode($changes["_tags"]));
			unset($changes["_tags"]);

			// If there's already a change in the queue, update it with this latest info.
			if ($existing_pending_change) {
				$comments = json_decode($f["comments"],true);
				if ($existing_pending_change["user"] == $this->ID) {
					$comments[] = array(
						"user" => "BigTree",
						"date" => date("F j, Y @ g:ia"),
						"comment" => "A new revision has been made."
					);
				} else {
					$user = $this->getUser($this->ID);
					$comments[] = array(
						"user" => "BigTree",
						"date" => date("F j, Y @ g:ia"),
						"comment" => "A new revision has been made.  Owner switched to ".$user["name"]."."
					);
				}
				
				// If this is a pending change, just replace all the changes
				if ($pending) {
					$changes = mysql_real_escape_string(json_encode($changes));
				// Otherwise, we need to check what's changed.
				} else {
					$original_changes = json_decode($existing_pending_change["changes"],true);
					if (isset($original_changes["template"])) {
						$template = $original_changes["template"];
					}
					if (isset($changes["external"])) {
						$changes["external"] = $this->makeIPL($changes["external"]);
					}

					foreach ($changes as $key => $val) {
						if ($val != $existing_page[$key] && isset($existing_page[$key])) {
							$original_changes[$key] = $val;
						}
					}

					$changes = mysql_real_escape_string(json_encode($original_changes));
				}

				$comments = mysql_real_escape_string(json_encode($comments));
				sqlquery("UPDATE bigtree_pending_changes SET comments = '$comments', changes = '$changes', tags_changes = '$tags', date = NOW(), user = '".$this->ID."', type = '$type' WHERE id = '".$f["id"]."'");
				
				$this->track("bigtree_pages",$page,"updated-draft");

			// We're submitting a change to a presently published page with no pending changes.
			} else {
				$original_changes = array();

				foreach ($changes as $key => $val) {
					if ($key == "external") {
						$val = $this->makeIPL($val);
					}

					if (isset($existing_page[$key]) && $val != $existing_page[$key]) {
						$original_changes[$key] = $val;
					}
				}

				$changes = mysql_real_escape_string(json_encode($original_changes));
				if ($type == "DELETE") {
					sqlquery("INSERT INTO bigtree_pending_changes (`user`,`date`,`table`,`item_id`,`changes`,`type`,`title`) VALUES ('".$this->ID."',NOW(),'bigtree_pages','$page','$changes','DELETE','Page Deletion Pending')");
				} else {
					sqlquery("INSERT INTO bigtree_pending_changes (`user`,`date`,`table`,`item_id`,`changes`,`tags_changes`,`type`,`title`) VALUES ('".$this->ID."',NOW(),'bigtree_pages','$page','$changes','$tags','EDIT','Page Change Pending')");
				}

				$this->track("bigtree_pages",$page,"saved-draft");
			}

			return sqlid();
		}
		
		/*
			Function: track
				Logs a user's actions to the audit trail table.
			
			Parameters:
				table - The table affected by the user.
				entry - The primary key of the entry affected by the user.
				type - The action taken by the user (delete, edit, create, etc.)
		*/

		function track($table,$entry,$type) {
			$table = mysql_real_escape_string($table);
			$entry = mysql_real_escape_string($entry);
			$type = mysql_real_escape_string($type);
			sqlquery("INSERT INTO bigtree_audit_trail VALUES (`table`,`user`,`entry`,`date`,`type`) VALUES ('$table','".$admin->ID."','$entry',NOW(),'$type')");
		}
		
		/*
			Function: unarchivePage
				Unarchives a page and all its children that inherited archived status.
				Checks permissions.
			
			Parameters:
				page - The page id or page entry.
			
			Returns:
				true if successful. false if permission was denied.
		*/

		function unarchivePage($page) {
			if (is_array($page)) {
				$page = mysql_real_escape_string($page["id"]);
			} else {
				$page = mysql_real_escape_string($page);
			}
			$access = $this->getPageAccessLevel($page);
			if ($access == "p") {
				sqlquery("UPDATE bigtree_pages SET archived = '' WHERE id = '$page'");
				$this->track("bigtree_pages",$page,"unarchived");
				$this->unarchivePageChildren($page);
				return true;
			}
			return false;
		}
		
		/*
			Function: unarchivePageChildren
				Unarchives a page's children that have the archived_inherited status.
				Does not checks permissions.
			
			Parameters:
				id - The parent page id.
		*/

		function unarchivePageChildren($id) {
			$q = sqlquery("SELECT * FROM bigtree_pages WHERE parent = '$id'");
			while ($f = sqlfetch($q)) {
				if ($f["archived_inherited"]) {
					sqlquery("UPDATE bigtree_pages SET archived = '', archived_inherited = '' WHERE id = '".$f["id"]."'");
					$this->track("bigtree_pages",$f["id"],"unarchived");
					$this->archivePageChildren($f["id"]);
				}
			}
		}
		
		/*
			Function: ungrowl
				Destroys the growl session.
		*/

		function ungrowl() {
			unset($_SESSION["bigtree"]["flash"]);
		}
		
		/*
			Function: urlExists
				Attempts to connect to a URL using cURL.
			
			Parameters:
				url - The URL to connect to.
			
			Returns:
				true if it can connect, false if connection failed.
		*/

		function urlExists($url) {
			$handle = curl_init($url);
			if ($handle === false) {
				return false;
			}
			curl_setopt($handle, CURLOPT_HEADER, false);
			curl_setopt($handle, CURLOPT_FAILONERROR, true);
			// Request as Firefox so that servers don't reject us for not having headers.
			curl_setopt($handle, CURLOPT_HTTPHEADER, Array("User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.15) Gecko/20080623 Firefox/2.0.0.15") );
			curl_setopt($handle, CURLOPT_NOBODY, true);
			curl_setopt($handle, CURLOPT_RETURNTRANSFER, false);
			$connectable = curl_exec($handle);
			curl_close($handle);
			return $connectable;
		}
		
		/*
			Function: unCache
				Removes the cached copy of a given page.
				
			Parameters:
				page - Either a page id or a page entry.
		*/

		function unCache($page) {
			global $cms;
			if (is_array($page)) {
				$file = $GLOBALS["server_root"]."cache/".base64_encode($page["path"]."/");
			} else {
				$file = $GLOBALS["server_root"]."cache/".base64_encode(str_replace($GLOBALS["www_root"],"",$cms->getLink($page)));		
			}
			if (file_exists($file)) {
				unlink($file);
			}
		}
		
		/*
			Function: updateChildPagePaths
				Updates the paths for pages who are descendants of a given page to reflect the page's new route.
				Also sets route history if the page has changed paths.
			
			Parameters:
				page - The page id.
		*/

		function updateChildPagePaths($page) {
			global $cms;
			
			$page = mysql_real_escape_string($page);
			$q = sqlquery("SELECT * FROM bigtree_pages WHERE parent = '$page'");
			while ($f = sqlfetch($q)) {
				$oldpath = $f["path"];
				$path = $this->getFullNavigationPath($f["id"]);
				if ($oldpath != $path) {
					sqlquery("DELETE FROM bigtree_route_history WHERE old_route = '$path' OR old_route = '$oldpath'");
					sqlquery("INSERT INTO bigtree_route_history (`old_route`,`new_route`) VALUES ('$oldpath','$path')");
					sqlquery("UPDATE bigtree_pages SET path = '$path' WHERE id = '".$f["id"]."'");
					$this->updateChildPagePaths($f["id"]);
				}
			}
		}
		
		/*
			Function: updatePage
				Updates a page.
				Does not check permissions.
			
			Paramters:
				page - The page id to update.
				data - The page data to update with.	
		*/

		function updatePage($page,$data) {
			global $cms;

			$page = mysql_real_escape_string($page);

			// Save the existing copy as a draft, remove drafts for this page that are one month old or older.
			sqlquery("DELETE FROM bigtree_page_versions WHERE page = '$page' AND updated_at < '".date("Y-m-d",strtotime("-31 days"))."' AND saved != 'on'");
			// Get the current copy
			$current = sqlfetch(sqlquery("SELECT * FROM bigtree_pages WHERE id = '$page'"));
			foreach ($current as $key => $val) {
				$$key = mysql_real_escape_string($val);
			}
			// Copy it to the saved versions
			sqlquery("INSERT INTO bigtree_page_versions (`page`,`title`,`meta_keywords`,`meta_description`,`template`,`external`,`new_window`,`resources`,`callouts`,`author`,`updated_at`) VALUES ('$page','$title','$meta_keywords','$meta_description','$template','$external','$new_window','$resources','$callouts','$last_edited_by','$updated_at')");

			// Remove this page from the cache
			$this->unCache($page);

			// Set local variables in a clean fashion that prevents _SESSION exploitation.  Also, don't let them somehow overwrite $page and $current.
			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_" && $key != "current" && $key != "page") {
					if (is_array($val)) {
						$$key = mysql_real_escape_string(json_encode($val));
					} else {
						$$key = mysql_real_escape_string($val);
					}
				}
			}

			// Make an ipl:// or {wwwroot}'d version of the URL
			if ($external) {
				$external = $this->makeIPL($external);
			}

			// If somehow we didn't provide a parent page (like, say, the user didn't have the right to change it) then pull the one from before.  Actually, this might be exploitable… look into it later.
			if (!isset($data["parent"])) {
				$parent = $current["parent"];
			}

			// Create a route if we don't have one, otherwise, make sure the one they provided doesn't suck.
			$route = $data["route"];
			if (!$route) {
				$route = $cms->urlify($data["nav_title"]);
			} else {
				$route = $cms->urlify($route);
			}

			// Get a unique route
			$oroute = $route;
			$x = 2;
			// Reserved paths.
			if ($parent == 0) {
				while (file_exists($GLOBALS["server_root"]."site/".$route."/")) {
					$route = $oroute."-".$x;
					$x++;
				}
			}
			// Existing pages.
			$f = sqlfetch(sqlquery("SELECT id FROM bigtree_pages WHERE `route` = '$route' AND parent = '$parent' AND id != '$page'"));
			while ($f) {
				$route = $oroute."-".$x;
				$f = sqlfetch(sqlquery("SELECT id FROM bigtree_pages WHERE `route` = '$route' AND parent = '$parent' AND id != '$page'"));
				$x++;
			}

			// We have no idea how this affects the nav, just wipe it all.
			if ($current["nav_title"] != $nav_title || $current["route"] != $route || $current["in_nav"] != $in_nav || $current["parent"] != $parent) {
				$this->clearCache();
			}

			// Make sure we set the publish date to NULL if it wasn't provided or we'll have a page that got published at 0000-00-00
			if ($publish_at) {
				$publish_at = "'".date("Y-m-d",strtotime($publish_at))."'";
			} else {
				$publish_at = "NULL";
			}

			// Same goes for the expiration date.
			if ($expire_at) {
				$expire_at = "'".date("Y-m-d",strtotime($expire_at))."'";
			} else {
				$expire_at = "NULL";
			}

			// Set the full path, saves DB access time on the front end.
			if ($parent) {
				$path = $this->getFullNavigationPath($parent)."/".$route;
			} else {
				$path = $route;
			}

			// htmlspecialchars stuff so that it doesn't need to be re-encoded when echo'd on the front end.
			$title = htmlspecialchars($title);
			$nav_title = htmlspecialchars($nav_title);
			$meta_description = htmlspecialchars($meta_description);
			$meta_keywords = htmlspecialchars($meta_keywords);
			$external = htmlspecialchars($external);

			// Update the database
			sqlquery("UPDATE bigtree_pages SET `parent` = '$parent', `nav_title` = '$nav_title', `route` = '$route', `path` = '$path', `in_nav` = '$in_nav', `title` = '$title', `template` = '$template', `external` = '$external', `new_window` = '$new_window', `resources` = '$resources', `callouts` = '$callouts', `meta_keywords` = '$meta_keywords', `meta_description` = '$meta_description', `last_edited_by` = '".$this->ID."', updated_at = NOW(), publish_at = $publish_at, expire_at = $expire_at, max_age = '$max_age' WHERE id = '$page'");

			// Remove any pending drafts
			sqlquery("DELETE FROM bigtree_pending_changes WHERE `table` = 'bigtree_pages' AND item_id = '$page'");

			// Remove old paths from the redirect list
			sqlquery("DELETE FROM bigtree_route_history WHERE old_route = '$path' OR old_route = '".$current["path"]."'");

			// Create an automatic redirect from the old path to the new one.
			if ($current["path"] != $path) {
				sqlquery("INSERT INTO bigtree_route_history (`old_route`,`new_route`) VALUES ('$oldpath','$newpath')");

				// Update all child page routes, ping those engines, clean those caches
				$this->updateChildPagePaths($page);
				$this->pingSearchEngines();
				$this->clearCache();
			}

			// Handle tags
			sqlquery("DELETE FROM bigtree_tags_rel WHERE module = '0' AND entry = '$page'");
			if (is_array($data["_tags"])) {
				foreach ($data["_tags"] as $tag) {
					sqlquery("INSERT INTO bigtree_tags_rel (`module`,`entry`,`tag`) VALUES ('0','$page','$tag')");
				}
			}
			
			// Audit trail.
			$this->track("bigtree_pages",$page,"updated");

			return $page;
		}
		
		/*
			Function: getModuleByRoute
				Returns a module entry for the given route.
			
			Parameters:
				route - A module route.
			
			Returns:
				A module entry with the "gbp" column decoded or false if a module was not found.
		*/
		
		function getModuleByRoute($route) {
			$route = mysql_real_escape_string($route);
			$module = sqlfetch(sqlquery("SELECT * FROM bigtree_modules WHERE route = '$route'"));
			if (!$module) {
				return false;
			}

			$module["gbp"] = json_decode($module["gbp"],true);
			return $module;
		}
		
		/*
			Function: getModuleForm
		*/

		function getModuleForm($id) {
			$id = mysql_real_escape_string($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_module_forms WHERE id = '$id'"));
			$item["fields"] = json_decode($item["fields"],true);
			return $item;
		}

		function getModuleGroup($id) {
			$id = mysql_real_escape_string($id);
			return sqlfetch(sqlquery("SELECT * FROM bigtree_module_groups WHERE id = '$id'"));
		}

		function getModuleGroupById($id) { return $this->getModuleGroup($id); }

		function getModuleGroupByName($name) {
			$name = mysql_real_escape_string($name);
			return sqlfetch(sqlquery("SELECT * FROM bigtree_module_groups WHERE name = '$name'"));
		}
		
		function getModuleGroupByRoute($route) {
			$name = mysql_real_escape_string($route);
			return sqlfetch(sqlquery("SELECT * FROM bigtree_module_groups WHERE route = '$route'"));
		}

		function getModuleGroups($sort = "position DESC, id ASC") {
			$items = array();
			$q = sqlquery("SELECT * FROM bigtree_module_groups ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				$items[$f["id"]] = $f;
			}
			return $items;
		}

		function getModuleIdByRoute($route) {
			$f = sqlfetch(sqlquery("SELECT id FROM bigtree_modules WHERE route = '$route'"));
			if (!$f) {
				return false;
			}
			return $f["id"];
		}

		function getModuleNavigation($module) {
			$items = array();
			$q = sqlquery("SELECT * FROM bigtree_module_actions WHERE module = '$module' AND in_nav = 'on' ORDER BY position DESC, id ASC");
			while ($f = sqlfetch($q)) {
				$items[] = $f;
			}
			return $items;
		}

		function getModulePackage($id) {
			$id = mysql_real_escape_string($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_module_packages WHERE id = '$id'"));
			if (!$item) {
				return false;
			}
			$item["details"] = json_decode($item["details"],true);
			return $item;
		}

		function getModulePackageByFoundryId($id) {
			$id = mysql_real_escape_string($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_module_packages WHERE foundry_id = '$id'"));
			if (!$item) {
				return false;
			}
			$item["details"] = json_decode($item["details"],true);
			return $item;
		}

		function getModulePackages($sort = "name ASC") {
			$packages = array();
			$q = sqlquery("SELECT * FROM bigtree_module_packages ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				$packages[] = $f;
			}
			return $packages;
		}

		function getModules($sort = "id ASC",$auth = true) {
			$items = array();
			$q = sqlquery("SELECT bigtree_modules.*,bigtree_module_groups.name AS group_name FROM bigtree_modules LEFT JOIN bigtree_module_groups ON bigtree_modules.`group` = bigtree_module_groups.id ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				if ($this->checkAccess($f["id"]) || !$auth) {
					$items[$f["id"]] = $f;
				}
			}
			return $items;
		}

		function getModulesByGroup($group,$sort = "position DESC, id ASC",$auth = true) {
			if (is_array($group))
				$group = $group["id"];
			$items = array();
			$q = sqlquery("SELECT * FROM bigtree_modules WHERE `group` = '$group' ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				if ($this->checkAccess($f["id"]) || !$auth) {
					$items[$f["id"]] = $f;
				}
			}
			return $items;
		}

		function getModuleView($id) {
			$id = mysql_real_escape_string($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_module_views WHERE id = '$id'"));
			$item["fields"] = json_decode($item["fields"],true);
			$item["options"] = json_decode($item["options"],true);
			$item["actions"] = json_decode($item["actions"],true);
			return $item;
		}

		function moduleActionExists($module,$route) {
			$module = mysql_real_escape_string($module);
			$route = mysql_real_escape_string($route);
			$f = sqlfetch(sqlquery("SELECT id FROM bigtree_module_actions WHERE module = '$module' AND route = '$route'"));
			if ($f) {
				return true;
			}

			return false;
		}

		// !Users Module Functions

		/*
			Function: changePassword
				Changes a user's password via a password change hash and redirects to a success page.

			Paramters:
				hash - The unique hash generated by <forgotPassword>.
				password - The user's new password.

			See Also:
				<forgotPassword>

		*/

		function changePassword($hash,$password) {
			global $config;

			$hash = mysql_real_escape_string($hash);
			$user = sqlfetch(sqlquery("SELECT * FROM bigtree_users WHERE change_password_hash = '$hash'"));

			$phpass = new PasswordHash($config["password_depth"], TRUE);
			$password = mysql_real_escape_string($phpass->HashPassword($password));

			sqlquery("UPDATE bigtree_users SET password = '$password', change_password_hash = '' WHERE id = '".$user["id"]."'");
			header("Location: ".$GLOBALS["admin_root"]."login/reset-success/");
			die();
		}

		function createUser($data) {
			global $config;

			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_" && !is_array($val)) {
					$$key = mysql_real_escape_string($val);
				}
			}

			// See if the user already exists
			$r = sqlrows(sqlquery("SELECT * FROM bigtree_users WHERE email = '$email'"));
			if ($r > 0) {
				return false;
			}

			$permissions = mysql_real_escape_string(json_encode($data["permissions"]));

			if ($level > $this->Level) {
				$level = $this->Level;
			}

			$phpass = new PasswordHash($config["password_depth"], TRUE);
			$password = mysql_real_escape_string($phpass->HashPassword($data["password"]));

			sqlquery("INSERT INTO bigtree_users (`email`,`password`,`name`,`company`,`level`,`permissions`) VALUES ('$email','$password','$name','$company','$level','$permissions')");
			$id = sqlid();

			$this->track("bigtree_users",$id,"created");

			return $id;
		}

		function deleteUser($id) {
			$id = mysql_real_escape_string($id);
			// If this person has higher access levels than the person trying to update them, fail.
			$current = $this->getUser($id);
			if ($current["level"] > $this->Level) {
				return false;
			}

			sqlquery("DELETE FROM bigtree_users WHERE id = '$id'");
			$this->track("bigtree_users",$id,"deleted");

			return true;
		}

		/*
			Function: forgotPassword
				Creates a new password change hash and sends an email to the user.

			Parameters:
				email - The user's email address

			Returns:
				Redirects if the email address was found, returns false if the user doesn't exist.

			See Also:
				<changePassword>
		*/

		function forgotPassword($email) {
			$email = mysql_real_escape_string($email);
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_users WHERE email = '$email'"));
			if (!$f) {
				return false;
			}

			$hash = mysql_real_escape_string(md5(md5(md5(uniqid("bigtree-hash".microtime(true))))));
			sqlquery("UPDATE bigtree_users SET change_password_hash = '$hash' WHERE id = '".$f["id"]."'");

			mail($email,"Reset Your Password","A user with the IP address ".$_SERVER["REMOTE_ADDR"]." has requested to reset your password.\n\nIf this was you, please click the link below:\n".$GLOBALS["admin_root"]."login/reset-password/$hash/","From: no-reply@bigtreecms.com");
			header("Location: ".$GLOBALS["admin_root"]."login/forgot-success/");
			die();
		}

		function getAllUsers() {
			$items = array();
			$q = sqlquery("SELECT * FROM bigtree_users ORDER BY name");
			while ($f = sqlfetch($q)) {
				$items[] = $f;
			}

			return $items;
		}

		function getPageOfUsers($page = 0,$query = "") {
			if ($query) {
				$qparts = explode(" ",$query);
				$qp = array();
				foreach ($qparts as $part) {
					$part = mysql_real_escape_string($part);
					$qp[] = "(name LIKE '%$part%' OR email LIKE '%$part%' OR company LIKE '%$part%')";
				}
				$q = sqlquery("SELECT * FROM bigtree_users WHERE ".implode(" AND ",$qp)." ORDER BY name LIMIT ".($page*$this->PerPage).",".$this->PerPage);
			} else {
				$q = sqlquery("SELECT * FROM bigtree_users ORDER BY name LIMIT ".($page*$this->PerPage).",".$this->PerPage);
			}

			$items = array();
			while ($f = sqlfetch($q)) {
				$items[] = $f;
			}

			return $items;
		}

		/*
			Function: getPendingChanges
				Returns a list of changes that the logged in user has access to publish.

			Parameters:
				user - The user id to retrieve changes for. Defaults to the logged in user.

			Returns:
				An array of changes sorted by most recent.
		*/

		function getPendingChanges($user = false) {
			if (!$user) {
				$user = $this->getUser($this->ID);
			} else {
				$user = $this->getUser($user);
			}

			$changes = array();
			// Setup the default search array to just be pages
			$search = array("`module` = ''");
			// Add each module the user has publisher permissions to
			if (is_array($user["permissions"]["module"])) {
				foreach ($user["permissions"]["module"] as $module => $permission) {
					if ($permission == "p") {
						$search[] = "`module` = '$module'";
					}
				}
			}
			// Add module group based permissions as well
			if (is_array($user["permissions"]["gbp"])) {
				foreach ($user["permissions"]["gbp"] as $module => $groups) {
					foreach ($groups as $group => $permission) {
						if ($permission == "p") {
							$search[] = "`module` = '$module'";
						}
					}
				}
			}

			$q = sqlquery("SELECT * FROM bigtree_pending_changes WHERE ".implode(" OR ",$search)." ORDER BY date DESC");

			while ($f = sqlfetch($q)) {
				$ok = false;

				// If they're an admin, they've got it.
				if ($user["level"] > 0) {
					$ok = true;
				// Check permissions on a page if it's a page.
				} elseif ($f["table"] == "bigtree_pages") {
					if (!$f["item_id"]) {
						$id = "p".$f["id"];
					} else {
						$id = $f["item_id"];
					}
					$r = $this->getPageAccessLevelByUser($f["item_id"],$admin->ID);
					// If we're a publisher, this is ours!
					if ($r == "p") {
						$ok = true;
					}
				} else {
					// Check our list of modules.
					if ($user["permissions"]["module"][$f["module"]] == "p") {
						$ok = true;
					} else {
						// Check our group based permissions
					}
				}

				// We're a publisher, get the info about the change and put it in the change list.
				if ($ok) {
					$mod = $this->getModule($f["module"]);
					$user = $this->getUser($f["user"]);
					$comments = unserialize($f["comments"]);
					if (!is_array($comments)) {
						$comments = array();
					}

					$f["mod"] = $mod;
					$f["user"] = $user;
					$f["comments"] = $comments;
					$changes[] = $f;
				}
			}

			return $changes;
		}

		function getUser($id) {
			$id = mysql_real_escape_string($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_users WHERE id = '$id'"));
			if ($item["level"] > 0) {
				$permissions = array();
				$q = sqlquery("SELECT * FROM bigtree_modules");
				while ($f = sqlfetch($q)) {
					$permissions["module"][$f["id"]] = "p";
				}
				$item["permissions"] = $permissions;
			} else {
				$item["permissions"] = json_decode($item["permissions"],true);
			}
			$item["alerts"] = json_decode($item["alerts"],true);
			return $item;
		}

		function getUsersPageCount($query = "") {
			if ($query) {
				$qparts = explode(" ",$query);
				$qp = array();
				foreach ($qparts as $part) {
					$part = mysql_real_escape_string($part);
					$qp[] = "(name LIKE '%$part%' OR email LIKE '%$part%' OR company LIKE '%$part%')";
				}
				$q = sqlquery("SELECT id FROM bigtree_users WHERE ".implode(" AND ",$qp));
			} else {
				$q = sqlquery("SELECT id FROM bigtree_users");
			}

			$r = sqlrows($q);
			$pages = ceil($r / $this->PerPage);
			if ($pages == 0)
				$pages = 1;
			return $pages;
		}

		/*
			Function: updateProfile
				Updates a user's name, company, digest setting, and (optionally) password.

			Parameters:
				data - Array containing name / company / daily_digest / password.
		*/

		function updateProfile($data) {
			global $config;

			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_" && !is_array($val)) {
					$$key = mysql_real_escape_string($val);
				}
			}

			$id = mysql_real_escape_string($this->ID);

			if ($data["password"]) {
				$phpass = new PasswordHash($config["password_depth"], TRUE);
				$password = mysql_real_escape_string($phpass->HashPassword($data["password"]));
				sqlquery("UPDATE bigtree_users SET `password` = '$password', `name` = '$name', `company` = '$company', `daily_digest` = '$daily_digest' WHERE id = '$id'");
			} else {
				sqlquery("UPDATE bigtree_users SET `name` = '$name', `company` = '$company', `daily_digest` = '$daily_digest' WHERE id = '$id'");
			}
		}

		/*
			Function: updateUser
				Updates a user.

			Parameters:
				id - The user's "id"
				data - A key/value array containing email, name, company, level, permissions, alerts, daily_digest, and (optionally) password.

			Returns:
				True if successful.  False if the logged in user doesn't have permission to change the user or there was an email collision.
		*/

		function updateUser($id,$data) {
			global $config;
			$id = mysql_real_escape_string($id);

			// See if there's an email collission
			$r = sqlrows(sqlquery("SELECT * FROM bigtree_users WHERE email = '".mysql_real_escape_string($data["email"])."' AND id != '$id'"));
			if ($r) {
				return false;
			}


			// If this person has higher access levels than the person trying to update them, fail.
			$current = $this->getUser($id);
			if ($current["level"] > $this->Level) {
				return false;
			}

			// If we didn't pass in a level because we're editing ourselves, use the current one.
			if (!$level || $this->ID == $current["id"]) {
				$level = $current["level"];
			}

			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_" && !is_array($val)) {
					$$key = mysql_real_escape_string($val);
				}
			}

			$permissions = mysql_real_escape_string(json_encode($data["permissions"]));
			$alerts = mysql_real_escape_string(json_encode($data["alerts"]));

			if ($data["password"]) {
				$phpass = new PasswordHash($config["password_depth"], TRUE);
				$password = mysql_real_escape_string($phpass->HashPassword($data["password"]));
				sqlquery("UPDATE bigtree_users SET `email` = '$email', `password` = '$password', `name` = '$name', `company` = '$company', `level` = '$level', `permissions` = '$permissions', `alerts` = '$alerts', `daily_digest` = '$daily_digest' WHERE id = '$id'");
			} else {
				sqlquery("UPDATE bigtree_users SET `email` = '$email', `name` = '$name', `company` = '$company', `level` = '$level', `permissions` = '$permissions', `alerts` = '$alerts', `daily_digest` = '$daily_digest' WHERE id = '$id'");
			}

			$this->track("bigtree_users",$id,"updated");

			return true;
		}

		/*
			Function: createSetting
				Creates a setting.

			Parameters:
				data - An array of settings information. Available fields: "id", "name", "description", "type", "locked", "module", "encrypted", "system"

			Returns:
				True if successful, false if a setting already exists with the ID given.
		*/

		function createSetting($data) {
			// Avoid _SESSION hijacking.
			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_" && !is_array($val)) {
					$$key = mysql_real_escape_string(htmlspecialchars($val));
				}
			}

			// See if there's already a setting with this ID
			$r = sqlrows(sqlquery("SELECT id FROM bigtree_settings WHERE id = '$id'"));
			if ($r) {
				return false;
			}

			sqlquery("INSERT INTO bigtree_settings (`id`,`name`,`description`,`type`,`locked`,`module`,`encrypted`,`system`) VALUES ('$id','$name','$description','$type','$locked','$module','$encrypted','$system')");
			// Audit trail.
			$this->track("bigtree_settings",$id,"created");

			return true;
		}

		function getAllSettings() {
			$items = array();
			if ($this->Level < 2) {
				$q = sqlquery("SELECT * FROM bigtree_settings WHERE locked != 'on' AND system != 'on' ORDER BY title");
			} else {
				$q = sqlquery("SELECT * FROM bigtree_settings WHERE system != 'on' ORDER BY title");
			}
			while ($f = sqlfetch($q)) {
				foreach ($f as $key => $val) {
					$f[$key] = str_replace("{wwwroot}",$GLOBALS["www_root"],$val);
				}
				$f["value"] = json_decode($f["value"],true);
				if ($f["encrypted"] == "on") {
					$f["value"] = "[Encrypted Text]";
				}
				$items[] = $f;
			}
			return $items;
		}

		function getModuleSettings($module) {
			$items = array();
			$module = mysql_real_escape_string($module);
			if ($this->Level < 2) {
				$q = sqlquery("SELECT * FROM bigtree_settings WHERE module = '$module' AND locked != 'on' AND system != 'on' ORDER BY title");
			} else {
				$q = sqlquery("SELECT * FROM bigtree_settings WHERE module = '$module' AND system != 'on' ORDER BY title");
			}
			while ($f = sqlfetch($q)) {
				foreach ($f as $key => $val) {
					$f[$key] = str_replace("{wwwroot}",$GLOBALS["www_root"],$val);
				}
				$f["value"] = json_decode($f["value"],true);
				if ($f["encrypted"] == "on") {
					$f["value"] = "[Encrypted Text]";
				}
				$items[] = $f;
			}
			return $items;
		}

		function getPageOfSettings($page = 0,$query = "") {
			global $cms;
			if ($query) {
				$qparts = explode(" ",$query);
				$qp = array();
				foreach ($qparts as $part) {
					$part = mysql_real_escape_string($part);
					$qp[] = "(name LIKE '%$part%' OR `value` LIKE '%$part%' OR description LIKE '%$part%')";
				}
				if ($this->Level < 2) {
					$q = sqlquery("SELECT * FROM bigtree_settings WHERE ".implode(" AND ",$qp)." AND locked != 'on' AND system != 'on' ORDER BY name LIMIT ".($page*$this->PerPage).",".$this->PerPage);
				} else {
					$q = sqlquery("SELECT * FROM bigtree_settings WHERE ".implode(" AND ",$qp)." AND system != 'on' ORDER BY name LIMIT ".($page*$this->PerPage).",".$this->PerPage);
				}
			} else {
				if ($this->Level < 2) {
					$q = sqlquery("SELECT * FROM bigtree_settings WHERE locked != 'on' AND system != 'on' ORDER BY name LIMIT ".($page*$this->PerPage).",".$this->PerPage);
				} else {
					$q = sqlquery("SELECT * FROM bigtree_settings WHERE system != 'on' ORDER BY name LIMIT ".($page*$this->PerPage).",".$this->PerPage);
				}
			}
			$items = array();
			while ($f = sqlfetch($q)) {
				foreach ($f as $key => $val) {
					$f[$key] = str_replace("{wwwroot}",$GLOBALS["www_root"],$val);
				}
				$f["value"] = json_decode($f["value"],true);
				if ($f["encrypted"] == "on") {
					$f["value"] = "[Encrypted Text]";
				}
				$items[] = $f;
			}
			return $items;
		}

		function getSetting($id) {
			global $config;
			$id = mysql_real_escape_string($id);

			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_settings WHERE id = '$id'"));
			if (!$f) {
				return false;
			}

			foreach ($f as $key => $val) {
				$f[$key] = str_replace("{wwwroot}",$GLOBALS["www_root"],$val);
			}
			if ($f["encrypted"]) {
				$v = sqlfetch(sqlquery("SELECT AES_DECRYPT(`value`,'".mysql_real_escape_string($config["settings_key"])."') AS `value` FROM bigtree_settings WHERE id = '$id'"));
				$f["value"] = $v["value"];
			}
			$f["value"] = json_decode($f["value"],true);
			return $f;
		}

		function getSettings() {
			$settings = array();
			$q = sqlquery("SELECT * FROM bigtree_settings WHERE system != 'on' ORDER BY name");
			while ($f = sqlfetch($q)) {
				$settings[] = $f;
			}

			return $settings;
		}

		function getSettingsPageCount($query = "") {
			if ($query) {
				$qparts = explode(" ",$query);
				$qp = array();
				foreach ($qparts as $part) {
					$part = mysql_real_escape_string($part);
					$qp[] = "(name LIKE '%$part%' OR value LIKE '%$part%' OR description LIKE '%$part%')";
				}
				if ($this->Level < 2) {
					$q = sqlquery("SELECT id FROM bigtree_settings WHERE system != 'on' AND locked != 'on' AND ".implode(" AND ",$qp));
				} else {
					$q = sqlquery("SELECT id FROM bigtree_settings WHERE system != 'on' AND ".implode(" AND ",$qp));
				}
			} else {
				if ($this->Level < 2) {
					$q = sqlquery("SELECT id FROM bigtree_settings WHERE system != 'on' AND locked != 'on'");
				} else {
					$q = sqlquery("SELECT id FROM bigtree_settings WHERE system != 'on'");
				}
			}

			$r = sqlrows($q);
			$pages = ceil($r / $this->PerPage);
			if ($pages == 0) {
				$pages = 1;
			}

			return $pages;
		}

		function settingExists($id) {
			return sqlrows(sqlquery("SELECT id FROM bigtree_settings WHERE id = '".mysql_real_escape_string($id)."'"));
		}

		function updateSetting($old_id,$data) {
			$existing = $this->getSetting($old_id);
			$old_id = mysql_real_escape_string($old_id);

			foreach ($data as $key => $val) {
				if (substr($key,0,1) != "_" && !is_array($val)) {
					$$key = mysql_real_escape_string($val);
				}
			}

			if ($old_id != $id) {
				$r = sqlrows(sqlquery("SELECT id FROM bigtree_settings WHERE id = '$id'"));
				if ($r) {
					return false;
				}
			}

			sqlquery("UPDATE bigtree_settings SET id = '$id', type = '$type', name = '$name', description = '$description', locked = '$locked', module = '$module', encrypted = '$encrypted' WHERE id = '$old_id'");

			// If encryption status has changed, update the value
			if ($existing["encrypted"] && !$encrypted) {
				sqlquery("UPDATE bigtree_settings SET value = AES_DECRYPT(value,'".mysql_real_escape_string($config["settings_key"])."') WHERE id = '$id'");
			}
			if (!$existing["encrypted"] && $encrypted) {
				sqlquery("UPDATE bigtree_settings SET value = AES_ENCRYPT(value,'".mysql_real_escape_string($config["settings_key"])."') WHERE id = '$id'");
			}

			$this->track("bigtree_settings",$id,"updated");

			return true;
		}

		function updateSettingValue($id,$value) {
			global $config;
			$item = $this->getSetting($id);
			$id = mysql_real_escape_string($id);

			$value = mysql_real_escape_string(json_encode($value));

			if ($item["encrypted"]) {
				sqlquery("UPDATE bigtree_settings SET `value` = AES_ENCRYPT('$value','".mysql_real_escape_string($config["settings_key"])."') WHERE id = '$id'");
			} else {
				sqlquery("UPDATE bigtree_settings SET `value` = '$value' WHERE id = '$id'");
			}

			$this->track("bigtree_settings",$id,"updated-value");
		}

		// !Callout Functions
		function getCallout($id) {
			$id = mysql_real_escape_string($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_callouts WHERE id = '$id'"));
			$item["resources"] = json_decode($item["resources"],true);
			return $item;
		}

		function getCalloutById($id) { return $this->getCallout($id); }

		function getCallouts() {
			$callouts = array();
			$q = sqlquery("SELECT * FROM bigtree_callouts ORDER BY position DESC, id ASC");
			while ($f = sqlfetch($q)) {
				$callouts[] = $f;
			}
			return $callouts;
		}

		// !Feed Functions
		function getFeeds() {
			$feeds = array();
			$q = sqlquery("SELECT * FROM bigtree_feeds ORDER BY name");
			while ($f = sqlfetch($q)) {
				$feeds[] = $f;
			}
			return $feeds;
		}

		// !Field Type Functions
		function getFieldType($id) {
			$id = mysql_real_escape_string($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_field_types WHERE id = '$id'"));
			if (!$item) {
				return false;
			}
			$item["files"] = json_decode($item["files"],true);
			return $item;
		}

		function getFieldTypeByFoundryId($id) {
			$id = mysql_real_escape_string($id);
			$item = sqlfetch(sqlquery("SELECT * FROM bigtree_field_types WHERE foundry_id = '$id'"));
			if (!$item) {
				return false;
			}
			$item["files"] = json_decode($item["files"],true);
			return $item;
		}

		function getFieldTypeById($id) { return $this->getFieldType($id); }

		function getFieldTypes($sort = "name ASC") {
			$types = array();
			$q = sqlquery("SELECT * FROM bigtree_field_types ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				$types[] = $f;
			}
			return $types;
		}

		// !SEO Functions

		function getPageSEORating($page,$content) {
			global $cms;
			$template = $cms->getTemplate($page["template"]);
			$tsources = array();
			$h1_field = "";
			$body_fields = array();

			if (is_array($template)) {
				foreach ($template["resources"] as $item) {
					if ($item["seo_body"]) {
						$body_fields[] = $item["id"];
					}
					if ($item["seo_h1"]) {
						$h1_field = $item["id"];
					}
					$tsources[$item["id"]] = $item;
				}
			}

			if (!$h1_field && $tsources["page_header"]) {
				$h1_field = "page_header";
			}
			if (!count($body_fields) && $tsources["page_content"]) {
				$body_fields[] = "page_content";
			}


			$textStats = new TextStatistics;
			$recommendations = array();

			// Get an SEO rating out of 100 points
			// ===================================
			// - Having a title - 5 points
			// - Having a unique title - 5 points
			// - Title does not exceed 72 characters and has at least 4 words - 5 points
			// - Having a meta description - 5 points
			// - Meta description that is less than 165 characters - 5 points
			// - Having an h1 - 10 points
			// - Having page content - 5 points
			// - Having at least 300 words in your content - 15 points
			// - Having links in your content - 5 points
			// - Having external links in your content - 5 points
			// - Having one link for every 120 words of content - 5 points
			// - Readability Score - up to 20 points
			// - Fresh content - up to 10 points

			$score = 0;

			// Check if they have a page title.
			if ($page["title"]) {
				$score += 5;
				// They have a title, let's see if it's unique
				$q = sqlquery("SELECT * FROM bigtree_pages WHERE title = '".mysql_real_escape_string($page["title"])."' AND id != '".$page["page"]."'");
				if ($r == 0) {
					// They have a unique title
					$score += 5;
				} else {
					$recommendations[] = "Your page title should be unique. ".($r-1)." other page(s) have the same title.";
				}
				$words = $textStats->word_count($page["title"]);
				$length = mb_strlen($page["title"]);
				if ($words >= 4 && $length <= 72) {
					// Fits the bill!
					$score += 5;
				} else {
					$recommendations[] = "Your page title should be no more than 72 characters and should contain at least 4 words.";
				}
			} else {
				$recommendations[] = "You should enter a page title.";
			}

			// Check for meta description
			if ($page["meta_description"]) {
				$score += 5;
				// They have a meta description, let's see if it's no more than 165 characters.
				if (mb_strlen($page["meta_description"]) <= 165) {
					$score += 5;
				} else {
					$recommendations[] = "Your meta description should be no more than 165 characters.  It is currently ".mb_strlen($page["meta_description"])." characters.";
				}
			} else {
				$recommendations[] = "You should enter a meta description.";
			}

			// Check for an H1
			if (!$h1_field || $content[$h1_field]) {
				$score += 10;
			} else {
				$recommendations[] = "You should enter a page header.";
			}
			// Check the content!
			if (!count($body_fields)) {
				// If this template doesn't for some reason have a seo body resource, give the benefit of the doubt.
				$score += 65;
			} else {
				$regular_text = "";
				$stripped_text = "";
				foreach ($body_fields as $field) {
					$regular_text .= $content[$field]." ";
					$stripped_text .= strip_tags($content[$field])." ";
				}
				// Check to see if there is any content
				if ($stripped_text) {
					$score += 5;
					$words = $textStats->word_count($stripped_text);
					$readability = $textStats->flesch_kincaid_reading_ease($stripped_text);
					$number_of_links = substr_count($regular_text,"<a ");
					$number_of_external_links = substr_count($regular_text,'href="http://');

					// See if there are at least 300 words.
					if ($words >= 300) {
						$score += 15;
					} else {
						$recommendations[] = "You should enter at least 300 words of page content.  You currently have ".$words." word(s).";
					}

					// See if we have any links
					if ($number_of_links) {
						$score += 5;
						// See if we have at least one link per 120 words.
						if (floor($words / 120) <= $number_of_links) {
							$score += 5;
						} else {
							$recommendations[] = "You should have at least one link for every 120 words of page content.  You currently have $number_of_links link(s).  You should have at least ".floor($words / 120).".";
						}
						// See if we have any external links.
						if ($number_of_external_links) {
							$score += 5;
						} else {
							$recommendations[] = "Having an external link helps build Page Rank.";
						}
					} else {
						$recommendations[] = "You should have at least one link in your content.";
					}

					// Check on our readability score.
					if ($readability >= 90) {
						$score += 20;
					} else {
						$read_score = round(($readability / 90),2);
						$recommendations[] = "Your readability score is ".($read_score*100)."%.  Using shorter sentences and words with less syllables will make your site easier to read by search engines and users.";
						$score += ceil($read_score * 20);
					}
				} else {
					$recommendations[] = "You should enter page content.";
				}

				// Check page freshness
				$updated = strtotime($page["updated_at"]);
				$age = time()-$updated-(60*24*60*60);
				// See how much older it is than 2 months.
				if ($age > 0) {
					$age_score = 10 - floor(2 * ($age / (30*24*60*60)));
					if ($age_score < 0) {
						$age_score = 0;
					}
					$score += $age_score;
					$recommendations[] = "Your content is around ".ceil(2 + ($age / (30*24*60*60)))." months old.  Updating your page more frequently will make it rank higher.";
				} else {
					$score += 10;
				}
			}

			$color = "#008000";
			if ($score <= 50) {
				$color = color_mesh("#CCAC00","#FF0000",100-(100 * $score / 50));
			} elseif ($score <= 80) {
				$color = color_mesh("#008000","#CCAC00",100-(100 * ($score-50) / 30));
			}

			return array("score" => $score, "recommendations" => $recommendations, "color" => $color);
		}

		// !Authorization Functions

		// Utility for form field types / views -- we already know module group permissions are enabled so we skip some overhead
		function canAccessGroup($module,$group) {
			if ($this->Level > 0) {
				return true;
			}

			$id = $module["id"];

			if ($this->Permissions["module"][$id] && $this->Permissions["module"][$id] != "n") {
				return true;
			}

			if (is_array($this->Permissions["module_gbp"][$id])) {
				$gp = $this->Permissions["module_gbp"][$id][$group];
				if ($gp && $gp != "n") {
					return true;
				}
			}

			return false;
		}

		function checkAccess($module) {
			if (is_array($module)) {
				$module = $module["id"];
			}

			if ($this->Level > 0) {
				return true;
			}

			if ($this->Permissions["module"][$module] && $this->Permissions["module"][$module] != "n") {
				return true;
			}

			if (is_array($this->Permissions["module_gbp"][$module])) {
				foreach ($this->Permissions["module_gbp"][$module] as $p) {
					if ($p != "n") {
						return true;
					}
				}
			}

			return false;
		}

		// Pass in the entire module array (or just the ID if not passing in $item), returns the permission level for the given item.
		// If no item is passed in, it will give the access level for the module ignoring gbp.
		function getAccessLevel($module,$item = array(),$table = "") {
			if ($this->Level > 0) {
				return "p";
			}

			$id = is_array($module) ? $module["id"] : $module;

			$perm = $this->Permissions["module"][$id];

			// If group based permissions aren't on or we're a publisher of this module it's an easy solution… or if we're not even using the table.
			if (!$item || !$module["gbp"]["enabled"] || $perm == "p" || $table != $module["gbp"]["table"]) {
				return $perm;
			}

			if (is_array($this->Permissions["module_gbp"][$id])) {
				$gv = $item[$module["gbp"]["group_field"]];
				$gp = $this->Permissions["module_gbp"][$id][$gv];

				if ($gp != "n") {
					return $gp;
				}
			}

			return $perm;
		}

		// Since cached items don't use their normal columns...
		function getCachedAccessLevel($module,$item = array(),$table = "") {
			if ($this->Level > 0) {
				return "p";
			}

			$id = is_array($module) ? $module["id"] : $module;

			$perm = $this->Permissions["module"][$id];

			// If group based permissions aren't on or we're a publisher of this module it's an easy solution… or if we're not even using the table.
			if (!$item || !$module["gbp"]["enabled"] || $perm == "p" || $table != $module["gbp"]["table"]) {
				return $perm;
			}

			if (is_array($this->Permissions["module_gbp"][$id])) {
				$gv = $item["gbp_field"];
				$gp = $this->Permissions["module_gbp"][$id][$gv];

				if ($gp != "n") {
					return $gp;
				}
			}

			return $perm;
		}

		// Get a list of all groups the user has access to in a module.
		function getAccessGroups($module) {
			if ($this->Level > 0) {
				return true;
			}

			if ($this->Permissions["module"][$module] && $this->Permissions["module"][$module] != "n") {
				return true;
			}

			$groups = array();
			if (is_array($this->Permissions["module_gbp"][$module])) {
				foreach ($this->Permissions["module_gbp"][$module] as $group => $permission) {
					if ($permission && $permission != "n") {
						$groups[] = $group;
					}
				}
			}
			return $groups;
		}

		function getPageAccessLevel($page) {
			return $this->getPageAccessLevelByUser($page,$this->ID);
		}

		function getPageAccessLevelByUser($page,$user) {
			$u = $this->getUser($user);
			if ($u["level"] > 0) {
				return "p";
			}

			if (!is_numeric($page) && $page[0] == "p") {
				$f = sqlfetch(sqlquery("SELECT * FROM bigtree_pending_changes WHERE id = '".substr($page,1)."'"));
				if ($f["user"] == $user) {
					return "p";
				}
				$pdata = json_decode($f["changes"],true);
				return $this->getPageAccessLevelByUser($pdata["parent"],$admin->ID);
			}

			$pp = $this->Permissions["page"][$page];
			if ($pp == "n") {
				return false;
			}

			if ($pp && $pp != "i") {
				return $pp;
			}

			$parent = sqlfetch(sqlquery("SELECT parent FROM bigtree_pages WHERE id = '".mysql_real_escape_string($page)."'"),true);
			$pp = $this->Permissions["page"][$parent];
			while ((!$pp || $pp == "i") && $parent) {
				$parent = sqlfetch(sqlquery("SELECT parent FROM bigtree_pages WHERE id = '$parent'"),true);
				$pp = $this->Permissions["page"][$parent];
			}

			if (!$pp || $pp == "i" || $pp == "n") {
				return false;
			}

			return $pp;
		}

		function getPageAccessType($page) {
			return $this->getPageAccessTypeByUserId($page,$this->ID);
		}

		function getPageAccessTypeByUserId($page,$user,$origin = true) {
			$page = mysql_real_escape_string($page);
			if ($origin) {
				$u = $this->getUser($user);
				if ($u["level"] > 0) {
					return "i";
				}
			}
			$f = sqlfetch(sqlquery("SELECT permissions,parent FROM bigtree_pages WHERE id = '$page'"));
			$rights = json_decode($f["permissions"],true);
			if (isset($rights[$user])) {
				if ($origin) {
					return $rights[$user]["type"];
				}
				if ($rights[$user]["type"] == "t") {
					return "i";
				}
			}
			if ($f["parent"] > -1) {
				return $this->getPageAccessTypeByUserId($f["parent"],$user,false);
			}
			return false;
		}

		function login($email,$password,$stay_logged_in = false) {
			global $path;
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_users WHERE email = '".mysql_real_escape_string($email)."'"));
			$phpass = new PasswordHash($config["password_depth"], TRUE);
			$ok = $phpass->CheckPassword($password,$f["password"]);
			if ($ok) {
				if ($stay_logged_in) {
					setcookie('bigtree[email]',$f["email"],time()+31*60*60*24,str_replace($GLOBALS["domain"],"",$GLOBALS["www_root"]));
					setcookie('bigtree[password]',$f["password"],time()+31*60*60*24,str_replace($GLOBALS["domain"],"",$GLOBALS["www_root"]));
				}

				$_SESSION["bigtree"]["id"] = $f["id"];
				$_SESSION["bigtree"]["email"] = $f["email"];
				$_SESSION["bigtree"]["level"] = $f["level"];
				$_SESSION["bigtree"]["name"] = $f["name"];
				$_SESSION["bigtree"]["permissions"] = json_decode($f["permissions"],true);

				if ($path[1] == "login") {
					header("Location: ".$GLOBALS["admin_root"]);
				} else {
					header("Location: ".$GLOBALS["domain"].$_SERVER["REQUEST_URI"]);
				}
				die();
			} else {
				return false;
			}
		}

		function logout() {
			setcookie("bigtree[email]","",time()-3600,str_replace($GLOBALS["domain"],"",$GLOBALS["www_root"]));
			setcookie("bigtree[password]","",time()-3600,str_replace($GLOBALS["domain"],"",$GLOBALS["www_root"]));
			unset($_SESSION["bigtree"]);
			header("Location: ".$GLOBALS["admin_root"]);
			die();
		}

		function requireAccess($module) {
			global $cms,$admin_root,$css,$js,$site;
			if ($this->Level > 0)
				return "p";
			if (!isset($this->Permissions[$module]) || $this->Permissions[$module] == "") {
				ob_clean();
				include bigtree_path("admin/pages/_denied.php");
				$content = ob_get_clean();
				include bigtree_path("admin/layouts/default.php");
				die();
			}
			return $this->Permissions[$module];
		}

		function requireLevel($level) {
			global $cms,$admin_root,$css,$js,$site;
			if (!isset($this->Level) || $this->Level < $level) {
				ob_start();
				include bigtree_path("admin/pages/_denied.php");
				$content = ob_get_clean();
				include bigtree_path("admin/layouts/default.php");
				die();
			}
		}

		function requirePublisher($module) {
			global $cms,$admin_root,$css,$js,$site;
			if ($this->Level > 0)
				return true;
			if ($this->Permissions[$module] != "p") {
				ob_clean();
				include bigtree_path("admin/pages/_denied.php");
				$content = ob_get_clean();
				include bigtree_path("admin/layouts/default.php");
				die();
			}
			return true;
		}

		//! API Related Functions

		function getAPIToken($email,$password) {
			global $config;
			$f = sqlfetch(sqlquery("SELECT * FROM bigtree_users WHERE email = '".mysql_real_escape_string($email)."'"));
			$phpass = new PasswordHash($config["password_depth"], TRUE);
			$ok = $phpass->CheckPassword($password,$f["password"]);
			if ($ok) {
				$existing = sqlfetch(sqlquery("SELECT * FROM bigtree_api_tokens WHERE temporary = 'on' AND user = '".$f["id"]."' AND expires > NOW()"));
				if ($existing) {
					sqlquery("UPDATE bigtree_api_tokens SET expires = '".date("Y-m-d H:i:s",strtotime("+1 day"))."' WHERE id = '".$existing["id"]."'");
					return $existing["token"];
				}
				$token = str_rand(30);
				$r = sqlrows(sqlquery("SELECT * FROM bigtree_api_tokens WHERE token = '$token'"));
				while ($r) {
					$token = str_rand(30);
					$r = sqlrows(sqlquery("SELECT * FROM bigtree_api_tokens WHERE token = '$token'"));
				}
				sqlquery("DELETE FROM bigtree_api_tokens WHERE user = '".$f["id"]."' AND temporary = 'on'");
				sqlquery("INSERT INTO bigtree_api_tokens (`token`,`user`,`expires`,`temporary`) VALUES ('$token','".$f["id"]."','".date("Y-m-d H:i:s",strtotime("+1 day"))."','on')");
				return $token;
			}
			return false;
		}


		function getPageOfTokens($page = 0,$query = "") {
			if ($query) {
				$q = sqlquery("SELECT * FROM bigtree_api_tokens WHERE token LIKE '%".mysql_real_escape_string($query)."%' ORDER BY id DESC LIMIT ".($page*$this->PerPage).",".$this->PerPage);
			} else {
				$q = sqlquery("SELECT * FROM bigtree_api_tokens ORDER BY id DESC LIMIT ".($page*$this->PerPage).",".$this->PerPage);
			}
			$items = array();
			while ($f = sqlfetch($q)) {
				$f["user"] = $this->getUser($f["user"]);
				$items[] = $f;
			}
			return $items;
		}

		function getTokensPageCount($query = "") {
			if ($query) {
				$q = sqlquery("SELECT id FROM bigtree_api_tokens WHERE token LIKE '%".mysql_real_escape_string($query)."%'");
			} else {
				$q = sqlquery("SELECT id FROM bigtree_api_tokens");
			}
			$r = sqlrows($q);
			$pages = ceil($r / $this->PerPage);
			if ($pages == 0) {
				$pages = 1;
			}
			return $pages;
		}

		function requireAPILevel($level) {
			if ($this->Level < $level) {
				echo bigtree_api_encode(array("success" => false,"error" => "Permission level is too low."));
				die();
			}
		}

		function requireAPIModuleAccess($module) {
			if (!$this->Permissions[$module]) {
				echo bigtree_api_encode(array("success" => false,"error" => "Not permitted."));
				die();
			}
		}

		function requireAPIModulePublisherAccess($module) {
			if ($this->Permissions[$module] != "p") {
				echo bigtree_api_encode(array("success" => false,"error" => "Publishing permission required."));
				die();
			}
		}

		function requireAPIWrite() {
			if ($this->ReadOnly) {
				echo bigtree_api_encode(array("success" => false,"error" => "Not available in read only mode."));
				die();
			}
		}

		function validateToken($token) {
			$t = sqlfetch(sqlquery("SELECT * FROM bigtree_api_tokens WHERE token = '$token' AND (expires > NOW() OR temporary != 'on')"));
			if (!$t) {
				return false;
			}

			$user = $this->getUser($t["user"]);
			$this->ID = $user["id"];
			$this->User = $user["email"];
			$this->Level = $user["level"];
			$this->Name = $user["name"];
			$this->Permissions = $user["permissions"];
			$this->ReadOnly = $t["readonly"];
			return true;
		}

		/*
			Function: getContentsOfResourceFolder
				Returns a list of resources and subfolders in a folder (based on user permissions).

			Parameters:
				folder - The id of a folder or a folder entry.
				sort - The column to sort the folder's files on (default: date DESC).

			Returns:
				An array of two arrays - folders and resources - that a user has access to.
		*/

		function getContentsOfResourceFolder($folder, $sort = "date DESC") {
			if (is_array($folder)) {
				$folder = $folder["id"];
			}
			$folder = mysql_real_escape_string($folder);

			$folders = array();
			$resources = array();

			$q = sqlquery("SELECT * FROM bigtree_resource_folders WHERE parent = '$folder' ORDER BY name");
			while ($f = sqlfetch($q)) {
				if ($this->Level > 0 || $this->getResourceFolderPermission($f["id"]) != "n") {
					$folders[] = $f;
				}
			}

			$q = sqlquery("SELECT * FROM bigtree_resources WHERE folder = '$folder' ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				$resources[] = $f;
			}

			return array("folders" => $folders, "resources" => $resources);
		}

		/*
			Function: getResourceFolderBreadcrumb
				Returns a breadcrumb of the given folder.

			Parameters:
				folder - The id of a folder or a folder entry.

			Returns:
				An array of arrays containing the name and id of folders above.
		*/

		function getResourceFolderBreadcrumb($folder,$crumb = array()) {
			if (!is_array($folder)) {
				$folder = sqlfetch(sqlquery("SELECT * FROM bigtree_resource_folders WHERE id = '".mysql_real_escape_string($folder)."'"));
			}

			if ($folder) {
				$crumb[] = array("id" => $folder["id"], "name" => $folder["name"]);
			}

			if ($folder["parent"]) {
				return $this->getResourceFolderBreadcrumb($folder["parent"],$crumb);
			} else {
				$crumb[] = array("id" => 0, "name" => "Home");
				return array_reverse($crumb);
			}
		}

		/*
			Function: getResourceFolderPermission
				Returns the permission level of the current user for the folder.

			Parameters:
				folder - The id of a folder or a folder entry.

			Returns:
				"p" if a user can create folders and upload files, "e" if the user can see/use files, "n" if a user can't access this folder.
		*/

		function getResourceFolderPermission($folder) {
			// User is an admin or developer
			if ($this->Level > 0) {
				return "p";
			}

			// We're going to save the folder entry in case we need its parent later.
			if (is_array($folder)) {
				$id = $folder["id"];
			} else {
				$id = $folder;
			}

			$p = $this->Permissions["resources"][$id];
			// If p is already no, creator, or consumer we can just return it.
			if ($p && $p != "i") {
				return $p;
			} else {
				// If folder is 0, we're already at home and can't check a higher folder for permissions.
				if (!$folder) {
					return "e";
				}

				// If a folder entry wasn't passed in, we need it to find its parent.
				if (!is_array($folder)) {
					$folder = sqlfetch(sqlquery("SELECT parent FROM bigtree_resource_folders WHERE id = '".mysql_real_escape_string($id)."'"));
				}
				// If we couldn't find the folder anymore, just say they can consume.
				if (!$folder) {
					return "e";
				}

				// Return the parent's permissions
				return $this->getResourceFolderPermission($folder["parent"]);
			}
		}

		/*
			Function: getResourceSearchResults
				Returns a list of folders and files that match the given query string.

			Parameters:
				query - A string of text to search folders' and files' names to.
				sort - The column to sort the files on (default: date DESC).

			Returns:
				An array of two arrays - folders and files - with permission levels.
		*/

		function getResourceSearchResults($query, $sort = "date DESC") {
			$query = mysql_real_escape_string($query);
			$folders = array();
			$resources = array();
			$permission_cache = array();

			$q = sqlquery("SELECT * FROM bigtree_resource_folders WHERE name LIKE '%$query%' ORDER BY name");
			while ($f = sqlfetch($q)) {
				$f["permission"] = $this->getResourceFolderPermission($f);
				// We're going to cache the folder permissions so we don't have to fetch them a bunch of times if many files have the same folder.
				$permission_cache[$f["id"]] = $f["permission"];

				$folders[] = $f;
			}

			$q = sqlquery("SELECT * FROM bigtree_resources WHERE name LIKE '%$query%' ORDER BY $sort");
			while ($f = sqlfetch($q)) {
				// If we've already got the permission cahced, use it.  Otherwise, fetch it and cache it.
				if ($permission_cache[$f["folder"]]) {
					$f["permission"] = $permission_cache[$f["folder"]];
				} else {
					$f["permission"] = $this->getResourceFolderPermission($f["folder"]);
					$permission_cache[$f["folder"]] = $f["permission"];
				}

				$resources[] = $f;
			}

			return array("folders" => $folders, "resources" => $resources);
		}

		/*
			Function: emailDailyDigest
				Sends out a daily digest email to all who have subscribed.
		*/

		function emailDailyDigest() {
			$qusers = sqlquery("SELECT * FROM bigtree_users where daily_digest = 'on'");
			while ($user = sqlfetch($qusers)) {
				$changes = $this->getPendingChanges($user["id"]);
				$alerts = $this->getContentAlerts($user["id"]);

				// Start building the email
				$body =  "BigTree Daily Digest\n";
				$body .= "====================\n";
				$body .= $GLOBALS["admin_root"]."\n\n";
				
				if (is_array($alerts) && count($alerts)) {
					$body .= "Content Age Alerts\n";
					$body .= "------------------\n\n";
					
					foreach ($alerts as $alert) {
						$body .= $alert["nav_title"]." - ".$alert["current_age"]." Days Old\n";
						$body .= $GLOBALS["www_root"].$alert["path"]."/\n";
						$body .= $GLOBALS["admin_root"]."pages/edit/".$alert["id"]."/\n\n";
					}
				}

				if (count($changes)) {
					$body .= "Pending Changes\n";
					$body .= "---------------\n\n";

					foreach ($changes as $change) {
						if ($change["title"]) {
					        $body .= $change["title"];
					    } else {
					        $body .= $change["mod"]["name"]." - ";

					        if ($change["type"] == "NEW") {
					        	$body .= "Addition";
					        } elseif ($change["type"] == "EDIT") {
					        	$body .= "Edit";
					        }
					    }
					    $body .= "\n".$change["user"]["name"]." has submitted this change request.\n";
					    $body .= $this->getChangeEditLink($change)."\n\n";
						
					}
				}
				
				if (count($alerts) || count($changes)) {
					mail($user["email"],"BigTree Daily Digest",$body,"From: BigTree Digest <mailer@bigtreecms.com>");
				}
			}
		}
		
		/*
			Function: getContentAlerts
				Gets a list of pages with content older than their Max Content Age that a user follows.
			
			Parameters:
				user - The user id to pull alerts for or a user entry.
			
			Returns:
				An array of arrays containing a page title, path, and id.
		*/
		
		function getContentAlerts($user) {
			if (is_array($user)) {
				$user = $this->getUser($user["id"]);
			} else {
				$user = $this->getUser($user);
			}
			
			if (!is_array($user["alerts"])) {
				return false;
			}
			
			$alerts = array();
			// We're going to generate a list of pages the user cares about first to get their paths.
			$where = array();
			foreach ($user["alerts"] as $alert => $status) {
				$where[] = "id = '".mysql_real_escape_string($alert)."'";
			}
			if (!count($where)) {
				return false;
			}
			// If we care about the whole tree, skip the madness.
			if ($alerts[0] == "on") {
				$q = sqlquery("SELECT nav_title,id,path,updated_at,DATEDIFF('".date("Y-m-d")."',updated_at) AS current_age FROM bigtree_pages WHERE max_age > 0 AND DATEDIFF('".date("Y-m-d")."',updated_at) > max_age ORDER BY current_age DESC");
			} else {
				$paths = array();
				$q = sqlquery("SELECT path FROM bigtree_pages WHERE ".implode(" OR ",$where));
				while ($f = sqlfetch($q)) {
					$paths[] = "path = '".mysql_real_escape_string($f["path"])."' OR path LIKE '".mysql_real_escape_string($f["path"])."/%'";
				}
				// Find all the pages that are old that contain our paths
				$q = sqlquery("SELECT nav_title,id,path,updated_at,DATEDIFF('".date("Y-m-d")."',updated_at) AS current_age FROM bigtree_pages WHERE max_age > 0 AND (".implode(" OR ",$paths).") AND DATEDIFF('".date("Y-m-d")."',updated_at) > max_age ORDER BY current_age DESC");
			}
			
			while ($f = sqlfetch($q)) {
				$alerts[] = $f;
			}
			
			return $alerts;
		}
		
		/*
			Function: getCachedFieldTypes
				Caches available field types and returns them.
			
			Returns:
				Array of three arrays of field types (template, module, and callout).
		*/
		
		function getCachedFieldTypes() {
			// Used cached values if available, otherwise query the DB
			if (file_exists($GLOBALS["server_root"]."cache/form-field-types.btc")) {
				$types = json_decode(file_get_contents($GLOBALS["server_root"]."cache/form-field-types.btc"),true);
			} else {
				$types["module"] = array(
					"text" => "Text",
					"textarea" => "Text Area",
					"html" => "HTML Area",
					"upload" => "Upload",
					"list" => "List",
					"checkbox" => "Checkbox",
					"date" => "Date Picker",
					"time" => "Time Picker",
					"photo-gallery" => "Photo Gallery",
					"array" => "Array of Items",
					"route" => "Generated Route",
					"custom" => "Custom Function"
				);
				$types["template"] = $types["module"];
				$types["callout"] = array(
					"text" => "Text",
					"textarea" => "Text Area",
					"html" => "HTML Area",
					"upload" => "Upload",
					"list" => "List",
					"checkbox" => "Checkbox",
					"date" => "Date Picker",
					"time" => "Time Picker",
					"array" => "Array of Items",
					"custom" => "Custom Function"
				);

				$q = sqlquery("SELECT * FROM bigtree_field_types ORDER BY name");
				while ($f = sqlfetch($q)) {
					if ($f["pages"]) {
						$types["template"][$f["id"]] = $f["name"];
					}
					if ($f["modules"]) {
						$types["module"][$f["id"]] = $f["name"];
					}
					if ($f["callouts"]) {
						$types["callout"][$f["id"]] = $f["name"];
					}
				}
				file_put_contents($GLOBALS["server_root"]."cache/form-field-types.btc",json_encode($types));
			}
			
			return $types;
		}
	}
?>