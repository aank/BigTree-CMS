<?
	$breadcrumb[] = array("title" => "Add Group", "link" => "developer/modules/groups/add/");
?>
<h1><span class="icon_developer_modules"></span>Add Group</h1>
<? include bigtree_path("admin/modules/developer/modules/_nav.php"); ?>
<div class="form_container">
	<form method="post" action="<?=$saroot?>modules/groups/create/" class="module">
		<header><h2>Group Details</h2></header>
		<section>
			<div class="left">
				<fieldset>
					<label class="required">Name</label>
					<input type="text" name="name" value="" class="required" />
				</fieldset>
			</div>
		</section>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>