<?
	$breadcrumb[] = array("title" => "Add Callout", "link" => "#");
	$resources = array();
?>
<h1><span class="icon_developer_callouts"></span>Add Callout</h1>
<? include bigtree_path("admin/modules/developer/callouts/_nav.php") ?>
<div class="form_container">
	<form method="post" action="<?=$sroot?>create/" enctype="multipart/form-data" class="module">
		<? include bigtree_path("admin/modules/developer/callouts/_form-content.php") ?>
		<footer>
			<input type="submit" class="button blue" value="Create" />
		</footer>
	</form>
</div>

<? include bigtree_path("admin/modules/developer/callouts/_common-js.php") ?>
<script type="text/javascript">
	var resource_count = <?=$x?>;
</script>