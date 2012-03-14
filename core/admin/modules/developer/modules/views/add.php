<?
	$id = $commands[0];
	$table = $commands[1];
	$title = $commands[2];
	
	$mod = $admin->getModule($id);
	$landing_exists = $admin->doesModuleLandingActionExist($id);

	$breadcrumb[] = array("title" => $mod["name"], "link" => "developer/modules/edit/".$module["id"]."/");
	$breadcrumb[] = array("title" => "Add View", "link" => "#");
?>
<h1><span class="icon_developer_modules"></span>Add View</h1>
<? include BigTree::path("admin/modules/developer/modules/_nav.php") ?>
<div class="form_container">

	<form method="post" action="<?=$developer_root?>modules/views/create/<?=$id?>/" class="module">
		<section>
			<? if ($landing_exists) { ?>
			<div class="alert">
				<img src="<?=$admin_root?>images/alert.png" alt="" />
				<p><strong>Default View Taken:</strong> If this view is for a different edit action, please specify the suffix below (i.e. edit-group's suffix is "group").</p>
			</div>
			<fieldset>
				<label>Add/Edit Suffix</label>
				<input type="text" name="suffix" />
			</fieldset>
			<? } ?>
			
			<fieldset>
				<label>Preview URL <small>(optional, i.e. http://www.website.com/news/preview/ &mdash; the item's id will be entered as the final route)</small></label>
				<input type="text" name="preview_url" value="<?=htmlspecialchars($view["preview_url"])?>" />
			</fieldset>
			
			<div class="left">
				<fieldset>
					<label class="required">Item Title <small>(for example, "Questions" to make the title "Viewing Questions")</small></label>
					<input type="text" class="required" name="title" value="<?=$title?>" />
				</fieldset>
				
				<fieldset>
					<label class="required">Data Table</label>
					<select name="table" id="view_table" class="required" >
						<option></option>
						<? BigTree::getTableSelectOptions($table); ?>
					</select>
				</fieldset>
				
				<fieldset>
					<label>View Type</label>
					<select name="type" id="view_type" class="left" >
						<? foreach ($admin->ViewTypes as $key => $type) { ?>
						<option value="<?=$key?>"<? if ($key == $view["type"]) { ?> selected="selected"<? } ?>><?=htmlspecialchars($type)?></option>
						<? } ?>
					</select>
					&nbsp; <a href="#" class="options icon_settings"></a>
					<input type="hidden" name="options" id="view_options" value="<?=htmlspecialchars($view["options"])?>" />
				</fieldset>
			</div>
			
			<div class="right">
				<fieldset>
					<label>Page Description <small>(instructions for the user)</small></label>
					<textarea name="description" ><?=$view["description"]?></textarea>
				</fieldset>
				
				<fieldset>
					<input type="checkbox" name="uncached" />
					<label class="for_checkbox">Don't Cache View Data <small>(removes parsers, pending changes)</small></label>
				</fieldset>
			</div>
		</section>
		<section class="sub" id="field_area">
			<? if (!$table) { ?>
			<p>Please choose a view table to populate this area.</p>
			<? } else { ?>
			<? include BigTree::path("admin/ajax/developer/load-view-fields.php") ?>
			<? } ?>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<? include BigTree::path("admin/modules/developer/modules/views/_js.php") ?>